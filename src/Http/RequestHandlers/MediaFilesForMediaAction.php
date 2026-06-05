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

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Http\RequestHandlers;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\MediaFile;
use Fisharebest\Webtrees\Registry;
use Hartenthaler\Webtrees\Module\SourceTranscription\Application\Factory\TranscriptionProviderFactory;
use Hartenthaler\Webtrees\Module\SourceTranscription\Application\Provider\SupportsMediaUploadRulesInterface;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\ValueObject\ProviderKey;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Webtrees\MediaMetadataReader;
use Hartenthaler\Webtrees\Module\SourceTranscription\Internationalization\MoreI18N;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function in_array;
use function intdiv;
use function json_encode;
use function response;
use function trim;

final class MediaFilesForMediaAction
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');

        if (!Auth::isEditor($tree)) {
            return response('');
        }

        $media_xref = trim((string) ($request->getQueryParams()['media_xref'] ?? ''), '@');
        if ($media_xref === '') {
            return $this->jsonResponse([]);
        }

        $media = Registry::mediaFactory()->make($media_xref, $tree);
        if ($media === null || !$media->canShow(Auth::accessLevel($tree))) {
            return $this->jsonResponse([]);
        }

        $items = [];
        $metadata_reader = Registry::container()->get(MediaMetadataReader::class);

        foreach ($media->mediaFiles() as $file) {
            /** @var MediaFile $file */
            $size = $this->fileSize($file);
            $message = $this->uploadMessage($file, $size);

            $items[] = [
                'id'         => $file->factId(),
                'text'       => ($file->title() !== '' ? $file->title() : $file->filename()),
                'filename'   => $file->filename(),
                'mime_type'  => $file->mimeType(),
                'file_size'  => $size,
                'size_label' => MoreI18N::xlate('%s KB', I18N::number(intdiv($size + 1023, 1024))),
                'uploadable' => $message === '',
                'message'    => $message,
                'metadata'   => $metadata_reader->read($file),
            ];
        }

        return $this->jsonResponse($items);
    }

    private function uploadMessage(MediaFile $file, int $size): string
    {
        if ($file->isExternal()) {
            return I18N::translate('External media files cannot be uploaded directly.');
        }

        if (!$file->fileExists()) {
            return I18N::translate('The file does not exist on this server.');
        }

        $provider = Registry::container()->get(TranscriptionProviderFactory::class)->forKey(ProviderKey::TRANSKRIBUS);
        if (!$provider instanceof SupportsMediaUploadRulesInterface) {
            return I18N::translate('The selected provider does not support media uploads.');
        }

        if (!in_array($file->mimeType(), $provider->acceptedMediaMimeTypes(), true)) {
            return I18N::translate('Unsupported file format. Use JPEG, TIFF or PNG.');
        }

        $max_file_size = $provider->maxMediaFileSize();

        if ($max_file_size !== null && $size > $max_file_size) {
            return I18N::translate('The file is larger than 20 MB.');
        }

        return '';
    }

    private function fileSize(MediaFile $file): int
    {
        if ($file->isExternal() || !$file->fileExists()) {
            return 0;
        }

        try {
            return (int) $file->media()->tree()->mediaFilesystem()->fileSize($file->filename());
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @throws JsonException
     */
    private function jsonResponse(array $data): ResponseInterface
    {
        return response(json_encode($data, JSON_THROW_ON_ERROR))
            ->withHeader('Content-Type', 'application/json; charset=UTF-8');
    }
}
