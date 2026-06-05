<?php

/*
 * webtrees: online genealogy application
 * Copyright (C) 2026 webtrees development team
 *                    <https://webtrees.net>
 *
 * Source Transcription (webtrees custom module):
 * Copyright (C) 2026 Hermann Hartenthaler
 *                     <https://ahnen.hartenthaler.eu>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Webtrees;

use Fisharebest\Webtrees\MediaFile;
use Throwable;

use function apcu_fetch;
use function apcu_store;
use function array_key_exists;
use function array_unique;
use function array_values;
use function count;
use function class_exists;
use function file_put_contents;
use function function_exists;
use function in_array;
use function is_array;
use function is_file;
use function is_scalar;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function md5;
use function parse_url;
use function pathinfo;
use function preg_match;
use function simplexml_load_string;
use function strtolower;
use function sys_get_temp_dir;
use function tempnam;
use function trim;
use function unlink;

use const PATHINFO_EXTENSION;
use const PHP_URL_PATH;

final class MediaMetadataReader
{
    private const NS_RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    private const NS_DC = 'http://purl.org/dc/elements/1.1/';
    private const NS_XMP = 'http://ns.adobe.com/xap/1.0/';
    private const NS_IPTCEX = 'http://iptc.org/std/Iptc4xmpExt/2008-02-29/';

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'tif', 'tiff', 'webp'];

    /**
     * @return array{language:string, description:string, created:string, persons:list<string>, keywords:list<string>, fields:list<array{label:string,value:string}>}
     */
    public function read(MediaFile $file): array
    {
        $empty = $this->emptyMetadata();

        if ($file->isExternal() || !$file->fileExists()) {
            return $empty;
        }

        try {
            $content = $file->media()->tree()->mediaFilesystem()->read($file->filename());
        } catch (Throwable) {
            return $empty;
        }

        if ($content === '') {
            return $empty;
        }

        $cache_key = 'hh_source_transcription:media-metadata:' . md5($file->media()->tree()->id() . ':' . $file->filename() . ':' . $content);

        if (function_exists('apcu_fetch')) {
            $cached = apcu_fetch($cache_key, $ok);
            if ($ok && is_array($cached)) {
                return $this->normalizeMetadata($cached);
            }
        }

        $metadata = $this->metadataFromXmp($this->xmpFromFileContent($content));

        $extension = $this->extension($file->filename());

        if (class_exists('Imagick') && in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            $metadata = $this->mergeMetadata($metadata, $this->metadataFromImagick($content, $extension));
        }

        if (function_exists('apcu_store')) {
            apcu_store($cache_key, $metadata, 3600);
        }

        return $metadata;
    }

    /**
     * @return array{language:string, description:string, created:string, persons:list<string>, keywords:list<string>, fields:list<array{label:string,value:string}>}
     */
    private function emptyMetadata(): array
    {
        return [
            'language' => '',
            'description' => '',
            'created' => '',
            'persons' => [],
            'keywords' => [],
            'fields' => [],
        ];
    }

    private function xmpFromFileContent(string $content): string
    {
        if (preg_match('/<x:xmpmeta\b.*?<\/x:xmpmeta>/s', $content, $match) === 1) {
            return $match[0];
        }

        if (preg_match('/<rdf:RDF\b.*?<\/rdf:RDF>/s', $content, $match) === 1) {
            return $match[0];
        }

        return '';
    }

    private function extension(string $filename): string
    {
        $path = parse_url($filename, PHP_URL_PATH);

        return strtolower(pathinfo((string) ($path ?: $filename), PATHINFO_EXTENSION));
    }

    /**
     * @return array{language:string, description:string, created:string, persons:list<string>, keywords:list<string>, fields:list<array{label:string,value:string}>}
     */
    private function metadataFromImagick(string $content, string $extension): array
    {
        $metadata = $this->emptyMetadata();
        $temporary_file = tempnam(sys_get_temp_dir(), 'hh-source-transcription-metadata-');

        if ($temporary_file === false) {
            return $metadata;
        }

        $path = $temporary_file . '.' . $extension;
        @unlink($temporary_file);

        try {
            if (@file_put_contents($path, $content) === false) {
                return $metadata;
            }

            $imagick = new \Imagick($path);
            $xmp = (string) $imagick->getImageProfile('xmp');
            $metadata = $xmp !== '' ? $this->metadataFromXmp($xmp) : $metadata;

            if ($metadata['description'] === '') {
                $metadata['description'] = $this->firstImageProperty($imagick, [
                    'exif:ImageDescription',
                    'exif:UserComment',
                ]);
            }

            if ($metadata['created'] === '') {
                $metadata['created'] = $this->firstImageProperty($imagick, [
                    'exif:DateTimeOriginal',
                    'exif:DateTimeDigitized',
                    'exif:DateTime',
                ]);
            }

            $metadata['fields'] = $this->mergeFields(
                $metadata['fields'],
                $this->imagePropertyFields($imagick)
            );

            $imagick->destroy();

            return $metadata;
        } catch (Throwable) {
            return $metadata;
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @return array{language:string, description:string, created:string, persons:list<string>, keywords:list<string>, fields:list<array{label:string,value:string}>}
     */
    private function metadataFromXmp(string $xmp): array
    {
        $result = $this->emptyMetadata();

        if ($xmp === '') {
            return $result;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmp);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return $result;
        }

        $xml->registerXPathNamespace('rdf', self::NS_RDF);
        $xml->registerXPathNamespace('dc', self::NS_DC);
        $xml->registerXPathNamespace('xmp', self::NS_XMP);
        $xml->registerXPathNamespace('iptcExt', self::NS_IPTCEX);

        $result['language'] = $this->firstXPathValue($xml, [
            '//dc:language/rdf:Bag/rdf:li[1]',
            '//dc:language/rdf:Seq/rdf:li[1]',
            '//dc:language',
        ]);
        $result['description'] = $this->firstXPathValue($xml, [
            '//dc:description/rdf:Alt/rdf:li[1]',
            '//dc:description',
        ]);
        $result['created'] = $this->firstXPathValue($xml, [
            '//xmp:CreateDate',
            '//xmp:ModifyDate',
        ]);
        $result['persons'] = $this->xpathValues($xml, '//iptcExt:PersonInImage/rdf:Bag/rdf:li');
        $result['keywords'] = $this->xpathValues($xml, '//dc:subject/rdf:Bag/rdf:li');
        $result['fields'] = $this->xmlFields($xml);

        return $result;
    }

    private function firstImageProperty(\Imagick $imagick, array $properties): string
    {
        foreach ($properties as $property) {
            try {
                $value = trim((string) $imagick->getImageProperty($property));
            } catch (Throwable) {
                $value = '';
            }

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return list<array{label:string,value:string}>
     */
    private function imagePropertyFields(\Imagick $imagick): array
    {
        try {
            $properties = $imagick->getImageProperties('*', true);
        } catch (Throwable) {
            return [];
        }

        if (!is_array($properties)) {
            return [];
        }

        $fields = [];

        foreach ($properties as $label => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $this->addField($fields, (string) $label, trim((string) $value));
        }

        return $fields;
    }

    /**
     * @return list<array{label:string,value:string}>
     */
    private function xmlFields(\SimpleXMLElement $xml): array
    {
        $fields = [];
        $this->collectXmlFields($xml, $xml->getName(), $fields);

        return $fields;
    }

    /**
     * @param list<array{label:string,value:string}> $fields
     */
    private function collectXmlFields(\SimpleXMLElement $node, string $path, array &$fields): void
    {
        foreach ($node->getNamespaces(true) + ['' => null] as $prefix => $namespace) {
            $attribute_prefix = $prefix !== '' ? $prefix . ':' : '';
            foreach ($namespace !== null ? $node->attributes($namespace) : $node->attributes() as $name => $value) {
                $this->addField($fields, $path . '/@' . $attribute_prefix . $name, trim((string) $value));
            }
        }

        $has_children = false;

        foreach ($node->getNamespaces(true) + ['' => null] as $prefix => $namespace) {
            $child_prefix = $prefix !== '' ? $prefix . ':' : '';
            $children = $namespace !== null ? $node->children($namespace) : $node->children();

            foreach ($children as $child) {
                $has_children = true;
                $this->collectXmlFields($child, $path . '/' . $child_prefix . $child->getName(), $fields);
            }
        }

        if (!$has_children) {
            $this->addField($fields, $path, trim((string) $node));
        }
    }

    /**
     * @param list<array{label:string,value:string}> $fields
     */
    private function addField(array &$fields, string $label, string $value): void
    {
        if ($label === '' || $value === '') {
            return;
        }

        foreach ($fields as $field) {
            if ($field['label'] === $label && $field['value'] === $value) {
                return;
            }
        }

        $fields[] = [
            'label' => $label,
            'value' => $value,
        ];
    }

    /**
     * @param list<array{label:string,value:string}> $primary
     * @param list<array{label:string,value:string}> $fallback
     * @return list<array{label:string,value:string}>
     */
    private function mergeFields(array $primary, array $fallback): array
    {
        foreach ($fallback as $field) {
            $this->addField($primary, $field['label'], $field['value']);
        }

        return $primary;
    }

    /**
     * @param array{language:string, description:string, created:string, persons:list<string>, keywords:list<string>, fields:list<array{label:string,value:string}>} $primary
     * @param array{language:string, description:string, created:string, persons:list<string>, keywords:list<string>, fields:list<array{label:string,value:string}>} $fallback
     * @return array{language:string, description:string, created:string, persons:list<string>, keywords:list<string>, fields:list<array{label:string,value:string}>}
     */
    private function mergeMetadata(array $primary, array $fallback): array
    {
        foreach (['language', 'description', 'created'] as $key) {
            if ($primary[$key] === '' && $fallback[$key] !== '') {
                $primary[$key] = $fallback[$key];
            }
        }

        foreach (['persons', 'keywords'] as $key) {
            if ($primary[$key] === [] && $fallback[$key] !== []) {
                $primary[$key] = $fallback[$key];
            }
        }

        $primary['fields'] = $this->mergeFields($primary['fields'], $fallback['fields']);

        return $primary;
    }

    /**
     * @param \SimpleXMLElement $xml
     * @param list<string> $queries
     */
    private function firstXPathValue(\SimpleXMLElement $xml, array $queries): string
    {
        foreach ($queries as $query) {
            $values = $this->xpathValues($xml, $query);

            if ($values !== []) {
                return $values[0];
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function xpathValues(\SimpleXMLElement $xml, string $query): array
    {
        $nodes = $xml->xpath($query);

        if ($nodes === false) {
            return [];
        }

        $values = [];

        foreach ($nodes as $node) {
            $value = trim((string) $node);

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param array<mixed> $metadata
     * @return array{language:string, description:string, created:string, persons:list<string>, keywords:list<string>, fields:list<array{label:string,value:string}>}
     */
    private function normalizeMetadata(array $metadata): array
    {
        $empty = $this->emptyMetadata();

        foreach ($empty as $key => $value) {
            if (!array_key_exists($key, $metadata)) {
                $metadata[$key] = $value;
            }
        }

        return [
            'language' => (string) $metadata['language'],
            'description' => (string) $metadata['description'],
            'created' => (string) $metadata['created'],
            'persons' => is_array($metadata['persons']) ? array_values($metadata['persons']) : [],
            'keywords' => is_array($metadata['keywords']) ? array_values($metadata['keywords']) : [],
            'fields' => is_array($metadata['fields']) ? array_values($metadata['fields']) : [],
        ];
    }
}
