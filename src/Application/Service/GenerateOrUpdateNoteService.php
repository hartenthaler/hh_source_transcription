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

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Application\Service;

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\I18N;
use Hartenthaler\Webtrees\Module\SourceTranscription\Application\Factory\NoteContentFactory;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\ValueObject\NoteStrategy;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\NoteLinkRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\RevisionRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\TranscriptionRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Webtrees\SharedNoteGateway;
use Hartenthaler\Webtrees\Module\SourceTranscription\Support\HashService;
use RuntimeException;

final class GenerateOrUpdateNoteService
{
    public function __construct(
        private readonly TranscriptionRepository $transcriptionRepository,
        private readonly RevisionRepository      $revisionRepository,
        private readonly NoteLinkRepository      $noteLinkRepository,
        private readonly SharedNoteGateway       $sharedNoteGateway,
        private readonly NoteContentFactory      $noteContentFactory,
        private readonly HashService             $hashService,
    )
    {
    }

    public function applyRevisionToCurrentNote(
        int    $transcription_id,
        int    $revision_id,
        string $strategy = NoteStrategy::UPDATE_IF_UNCHANGED
    ): string
    {
        $transcription = $this->transcriptionRepository->find($transcription_id);
        $revision = $this->revisionRepository->find($revision_id);

        if ($transcription === null) {
            throw new RuntimeException(I18N::translate('Transcription not found: %s', $transcription_id));
        }

        if ($revision === null) {
            throw new RuntimeException(I18N::translate('Revision not found: %s', $revision_id));
        }

        $note_text = $this->noteContentFactory->buildTranscriptNote(
            $transcription->title,
            $revision
        );

        return DB::transaction(function () use ($transcription, $revision, $note_text, $strategy): string {
            $current_link = $this->noteLinkRepository->currentLinkForTranscription($transcription->id);

            if ($strategy === NoteStrategy::ALWAYS_UPDATE && $current_link !== null) {
                $note_xref = $current_link->note_xref;

                $this->sharedNoteGateway->updateSharedNote(
                    $transcription->tree_id,
                    $note_xref,
                    $note_text
                );

                $this->createCurrentLink($transcription->id, $revision->id, $note_xref, $revision->created_by_user_id, $note_text, 'updated_from_revision');

                return $note_xref;
            }

            if ($strategy === NoteStrategy::UPDATE_IF_UNCHANGED && $current_link !== null) {
                $current_text = $this->sharedNoteGateway->readSharedNote(
                    $transcription->tree_id,
                    $current_link->note_xref
                );

                $current_hash = $current_text === null ? null : $this->hashService->sha256($current_text);

                if ($current_hash !== null && $current_hash === $current_link->note_hash_at_link_time) {
                    $note_xref = $current_link->note_xref;

                    $this->sharedNoteGateway->updateSharedNote(
                        $transcription->tree_id,
                        $note_xref,
                        $note_text
                    );

                    $this->createCurrentLink($transcription->id, $revision->id, $note_xref, $revision->created_by_user_id, $note_text, 'updated_from_revision');

                    return $note_xref;
                }
            }

            $note_xref = $this->sharedNoteGateway->createSharedNote(
                $transcription->tree_id,
                $note_text
            );

            $this->createCurrentLink($transcription->id, $revision->id, $note_xref, $revision->created_by_user_id, $note_text, 'generated_from_revision');

            return $note_xref;
        });
    }

    private function createCurrentLink(
        int    $transcription_id,
        int    $revision_id,
        string $note_xref,
        int    $user_id,
        string $note_text,
        string $link_type
    ): void
    {
        $this->noteLinkRepository->createLink([
            'transcription_id' => $transcription_id,
            'revision_id' => $revision_id,
            'note_xref' => $note_xref,
            'link_type' => $link_type,
            'created_by_user_id' => $user_id,
            'is_current' => 1,
            'note_hash_at_link_time' => $this->hashService->sha256($note_text),
        ]);

        $this->noteLinkRepository->markCurrent($transcription_id, $note_xref);
        $this->transcriptionRepository->setCurrentNoteXref($transcription_id, $note_xref);
    }
}