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
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; If not, see <https://www.gnu.org/licenses/>.
 *
 * Source Transcription
 * A webtrees (https://webtrees.net) 2.2 custom module to transcribe sources
 */

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Webtrees;

use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\MediaFile;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Throwable;

use function chr;
use function function_exists;
use function hexdec;
use function html_entity_decode;
use function in_array;
use function is_string;
use function mb_check_encoding;
use function mb_convert_encoding;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function parse_url;
use function pathinfo;
use function str_contains;
use function strlen;
use function strtolower;
use function substr;
use function str_starts_with;

use const ENT_NOQUOTES;
use const ENT_SUBSTITUTE;
use const PATHINFO_EXTENSION;
use const PHP_URL_PATH;
use const PHP_URL_SCHEME;

final class MediaObjectGateway
{
    private const TEXT_PREVIEW_MAX_BYTES = 1048576;

    private const SOURCE_MEDIA_TYPES = [
        'AUDIO',
        'BOOK',
        'CARD',
        'CERTIFICATE',
        'COAT',
        'DOCUMENT',
        'ELECTRONIC',
        'FICHE',
        'FILM',
        'MAGAZINE',
        'MANUSCRIPT',
        'MAP',
        'NEWSPAPER',
        'OTHER',
        'PAINTING',
        'PHOTO',
        'TOMBSTONE',
        'VIDEO',
    ];

    public function linkNoteToMedia(Tree $tree, string $media_xref, string $note_xref): bool
    {
        $media = Registry::mediaFactory()->make($media_xref, $tree);

        if ($media === null) {
            return false;
        }

        if (preg_match('/^\d+\s+NOTE\s+@' . preg_quote($note_xref, '/') . '@/mu', $media->gedcom())) {
            return true;
        }

        $media->createFact('1 NOTE @' . $note_xref . '@', true);

        return true;
    }

    public function gedcom(Tree $tree, string $media_xref): ?string
    {
        $media = Registry::mediaFactory()->make($media_xref, $tree);

        return $media?->gedcom();
    }

    /**
     * @param Media $media
     *
     * @return string
     */
    public function restriction(Media $media): string
    {
        return $media->facts(['RESN'])
            ->map(static fn ($fact): string => $fact->value())
            ->filter()
            ->implode(', ');
    }

    /**
     * @param Media $media
     *
     * @return array<int, array{file: MediaFile, filename: string, title: string, form: string, type: string, url: string, download_url: string, mime: string, extension: string, is_external: bool, is_embeddable_external: bool, viewer_type: string, text_content: string, text_truncated: bool}>
     */
    public function files(Media $media): array
    {
        $files = [];
        $media_files = $media->mediaFiles()->values();
        $media_file_facts = $media->facts(['FILE'])->values();

        foreach ($media_files as $index => $file) {
            /** @var MediaFile $file */
            $filename = $file->filename();
            $file_gedcom = (string) ($media_file_facts->get($index)?->gedcom() ?? '');
            $extension = $this->extension($filename);
            $mime = $this->mimeType($file, $extension);
            $is_external = $file->isExternal();
            $is_embeddable_external = $is_external && $this->isEmbeddableExternalUrl($filename);
            $viewer_type = $this->viewerType($mime, $extension, $file);
            [$text_content, $text_truncated] = $viewer_type === 'text' && !$is_external
                ? $this->textPreview($file, $extension)
                : ['', false];

            $files[] = [
                'file'     => $file,
                'filename' => $filename,
                'title'    => $file->title(),
                'form'     => $this->mediaFileForm($file, $file_gedcom),
                'type'     => $this->mediaFileType($file, $file_gedcom),
                'url'      => $is_external ? $filename : $file->downloadUrl('inline'),
                'download_url' => $is_external ? $filename : $file->downloadUrl('attachment'),
                'mime'     => $mime,
                'extension' => $extension,
                'is_external' => $is_external,
                'is_embeddable_external' => $is_embeddable_external,
                'viewer_type' => $viewer_type,
                'text_content' => $text_content,
                'text_truncated' => $text_truncated,
            ];
        }

        return $files;
    }

    public function hasTranscriptionSuitableFile(Media $media): bool
    {
        foreach ($media->mediaFiles() as $file) {
            /** @var MediaFile $file */
            $extension = $this->extension($file->filename());
            $mime = $this->mimeType($file, $extension);

            if (in_array($this->viewerType($mime, $extension, $file), ['image', 'pdf', 'text'], true)) {
                return true;
            }
        }

        return false;
    }

    private function extension(string $filename): string
    {
        $path = parse_url($filename, PHP_URL_PATH);

        return strtolower(pathinfo((string) ($path ?: $filename), PATHINFO_EXTENSION));
    }

    private function mediaFileForm(MediaFile $file, string $file_gedcom): string
    {
        $form = strtoupper($this->mediaFileTagFromGedcom($file_gedcom, 'FORM'));

        if ($form !== '') {
            return $form;
        }

        $form = trim($file->format());

        return in_array(strtoupper($form), self::SOURCE_MEDIA_TYPES, true) ? '' : $form;
    }

    private function mediaFileType(MediaFile $file, string $file_gedcom): string
    {
        $type = strtoupper($this->mediaFileTagFromGedcom($file_gedcom, 'TYPE'));

        if ($type !== '') {
            return $type;
        }

        $type = trim($file->type());

        if ($type !== '') {
            return $type;
        }

        $format = trim($file->format());

        return in_array(strtoupper($format), self::SOURCE_MEDIA_TYPES, true) ? $format : '';
    }

    private function mediaFileTagFromGedcom(string $gedcom, string $tag): string
    {
        if (preg_match('/^\d+[ \t]+' . preg_quote($tag, '/') . '[ \t]+([^\r\n]+)$/m', $gedcom, $match) !== 1) {
            return '';
        }

        return strtolower(trim($match[1]));
    }

    private function isEmbeddableExternalUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    private function mimeType(MediaFile $file, string $extension): string
    {
        $mime = $file->mimeType();

        if ($mime !== 'application/octet-stream') {
            return $mime;
        }

        return match ($extension) {
            'aac' => 'audio/aac',
            'ged' => 'text/plain',
            'm4a' => 'audio/mp4',
            'md', 'markdown' => 'text/markdown',
            'rtf' => 'application/rtf',
            'txt' => 'text/plain',
            'wav' => 'audio/wav',
            default => $mime,
        };
    }

    private function viewerType(string $mime, string $extension, MediaFile $file): string
    {
        if ($file->isImage() || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return 'image';
        }

        if ($mime === 'application/pdf' || $extension === 'pdf') {
            return 'pdf';
        }

        if (str_starts_with($mime, 'audio/') || in_array($extension, ['mp3', 'ogg', 'oga', 'wav', 'm4a', 'aac', 'flac'], true)) {
            return 'audio';
        }

        if ($mime === 'video/quicktime' || $extension === 'mov') {
            return 'download';
        }

        if (str_starts_with($mime, 'video/') || in_array($extension, ['mp4', 'm4v', 'webm', 'ogv'], true)) {
            return 'video';
        }

        if (str_starts_with($mime, 'text/') || in_array($extension, ['txt', 'text', 'md', 'markdown', 'ged', 'rtf'], true)) {
            return 'text';
        }

        return 'download';
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function textPreview(MediaFile $file, string $extension): array
    {
        try {
            $content = $file->media()->tree()->mediaFilesystem()->read($file->filename());
        } catch (Throwable) {
            return ['', false];
        }

        $truncated = strlen($content) > self::TEXT_PREVIEW_MAX_BYTES;

        if ($truncated) {
            $content = substr($content, 0, self::TEXT_PREVIEW_MAX_BYTES);
        }

        if ($extension === 'rtf') {
            return [$this->rtfToText($content), $truncated];
        }

        return [$this->normalizeTextEncoding($content), $truncated];
    }

    private function rtfToText(string $rtf): string
    {
        $code_page = 'Windows-1252';

        if (preg_match('/\\\\ansicpg(\d+)/', $rtf, $match) === 1) {
            $code_page = 'Windows-' . $match[1];
        }

        $text = preg_replace('/\{\\\\(?:fonttbl|colortbl|stylesheet|info|pict|object)\b.*?\}/s', '', $rtf);
        $text = is_string($text) ? $text : $rtf;
        $text = preg_replace_callback('/\\\\u(-?\d+)\??/u', static function (array $match): string {
            $code_point = (int) $match[1];

            if ($code_point < 0) {
                $code_point += 65536;
            }

            return html_entity_decode('&#' . $code_point . ';', ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }, $text);
        $text = is_string($text) ? $text : $rtf;
        $text = preg_replace_callback("/\\\\'([0-9a-fA-F]{2})/", function (array $match) use ($code_page): string {
            return $this->normalizeTextEncoding(chr((int) hexdec($match[1])), $code_page);
        }, $text);
        $text = is_string($text) ? $text : $rtf;

        $replacements = [
            '/\\\\par[d]? ?/i' => "\n",
            '/\\\\line ?/i' => "\n",
            '/\\\\tab ?/i' => "\t",
        ];

        foreach ($replacements as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
            $text = is_string($text) ? $text : '';
        }

        $text = preg_replace('/\\\\[a-zA-Z]+-?\d* ?/', '', $text);
        $text = is_string($text) ? $text : '';
        $text = preg_replace('/[{}]/', '', $text);
        $text = is_string($text) ? $text : '';
        $text = preg_replace('/\\\\([\\\\{}])/', '$1', $text);

        return is_string($text) ? $this->normalizeTextEncoding($text) : '';
    }

    private function normalizeTextEncoding(string $content, string $source_encoding = ''): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return substr($content, 3);
        }

        if (str_starts_with($content, "\xFF\xFE")) {
            return $this->convertEncoding(substr($content, 2), 'UTF-16LE');
        }

        if (str_starts_with($content, "\xFE\xFF")) {
            return $this->convertEncoding(substr($content, 2), 'UTF-16BE');
        }

        if ($source_encoding !== '') {
            return $this->convertEncoding($content, $source_encoding);
        }

        if (function_exists('mb_check_encoding') && mb_check_encoding($content, 'UTF-8')) {
            return $content;
        }

        if (str_contains($content, "\0")) {
            return $this->convertEncoding($content, 'UTF-16LE');
        }

        return $this->convertEncoding($content, 'Windows-1252');
    }

    private function convertEncoding(string $content, string $source_encoding): string
    {
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($content, 'UTF-8', $source_encoding);
        }

        return $content;
    }
}
