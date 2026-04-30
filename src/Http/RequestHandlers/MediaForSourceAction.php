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

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Http\RequestHandlers;

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Registry;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function response;

final class MediaForSourceAction
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        $query = $request->getQueryParams();

        $source_xref = trim((string) ($query['source_xref'] ?? ''));
        $source_xref = trim($source_xref, '@');

        if ($source_xref === '') {
            return $this->jsonResponse([]);
        }

        $gedcom = DB::table('sources')
            ->where('s_file', '=', $tree->id())
            ->where('s_id', '=', $source_xref)
            ->value('s_gedcom');

        if ($gedcom === null) {
            return $this->jsonResponse([]);
        }

        $media_xrefs = [];

        foreach (preg_split('/\R/u', (string) $gedcom) ?: [] as $line) {
            if (preg_match('/^\d+\s+OBJE\s+@([^@]+)@/u', $line, $match)) {
                $media_xrefs[] = $match[1];
            }
        }

        $media_xrefs = array_values(array_unique($media_xrefs));

        $items = [];

        foreach ($media_xrefs as $media_xref) {
            try {
                $media = Registry::mediaFactory()->make($media_xref, $tree);

                if ($media === null) {
                    continue;
                }

                $items[] = [
                    'id'   => $media_xref,
                    'text' => strip_tags($media->fullName()) . ' (' . $media_xref . ')',
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        return $this->jsonResponse($items);

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