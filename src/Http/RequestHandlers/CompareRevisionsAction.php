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

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Hartenthaler\Webtrees\Module\SourceTranscription\Application\Service\CompareRevisionsService;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\RevisionRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\TranscriptionRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function response;
use function route;
use function view;

class CompareRevisionsAction implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');

        if (!Auth::isMember($tree)) {
            return response('');
        }

        $transcription_id = (int) $request->getAttribute('transcription_id');
        $params = $request->getQueryParams();
        $left_revision_id = (int) ($params['left_revision_id'] ?? 0);
        $right_revision_id = (int) ($params['right_revision_id'] ?? 0);

        $transcription_repository = Registry::container()->get(TranscriptionRepository::class);
        $revision_repository = Registry::container()->get(RevisionRepository::class);
        $compare_service = Registry::container()->get(CompareRevisionsService::class);

        $transcription = $transcription_repository->find($transcription_id);
        $left_revision = $revision_repository->find($left_revision_id);
        $right_revision = $revision_repository->find($right_revision_id);

        if (
            $transcription === null ||
            $left_revision === null ||
            $right_revision === null ||
            $left_revision->transcription_id !== $transcription_id ||
            $right_revision->transcription_id !== $transcription_id
        ) {
            return response('');
        }

        $title = I18N::translate('Compare revisions');
        $content = view('hh_source_transcription::compare-revisions', [
            'title' => $title,
            'tree' => $tree,
            'transcription' => $transcription,
            'left_title' => $compare_service->revisionTitle($left_revision),
            'right_title' => $compare_service->revisionTitle($right_revision),
            'metadata_rows' => $compare_service->metadataRowsForView($tree, $left_revision, $right_revision),
            'text_diff_rows' => $compare_service->textDiffRowsForView($left_revision, $right_revision),
            'detail_url' => route('source-transcription-detail', [
                'tree' => $tree->name(),
                'transcription_id' => $transcription_id,
            ]),
        ]);

        return response(view('layouts/default', [
            'title' => $title,
            'tree' => $tree,
            'request' => $request,
            'content' => $content,
        ]));
    }
}
