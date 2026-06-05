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
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Enum\RevisionOriginType;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\NoteLinkRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\RevisionRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\TranscriptionCollaboratorRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\TranscriptionRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Webtrees\SharedNoteGateway;
use Hartenthaler\Webtrees\Module\SourceTranscription\Support\HashService;

final class SaveNoteAsRevisionService
{
    public function __construct(
        private readonly TranscriptionRepository $transcriptionRepository,
        private readonly RevisionRepository $revisionRepository,
        private readonly NoteLinkRepository $noteLinkRepository,
        private readonly SharedNoteGateway $sharedNoteGateway,
        private readonly HashService $hashService,
        private readonly TranscriptionCollaboratorRepository $collaboratorRepository,
        private readonly CollaborationNotificationService $notificationService,
    ) {
    }

    public function saveCurrentNoteAsRevision(
        int $transcription_id,
        int $user_id,
        ?string $comment = null,
        bool $notify_collaborators = true
    ): int {
        $transcription = $this->transcriptionRepository->find($transcription_id);

        if ($transcription === null) {
            throw new \RuntimeException('Transcription not found: ' . $transcription_id);
        }

        if ($transcription->current_note_xref === null) {
            throw new \RuntimeException('No current note available.');
        }

        $note_text = $this->sharedNoteGateway->readSharedNote(
            $transcription->tree,
            $transcription->current_note_xref
        );

        if ($note_text === null) {
            throw new \RuntimeException('Current note could not be read.');
        }

        $note_hash = $this->hashService->sha256($note_text);

        $revision_id = DB::transaction(function () use (
            $transcription,
            $note_text,
            $note_hash,
            $user_id,
            $comment
        ): int {
            $this->revisionRepository->lockTranscriptionForRevisionAllocation($transcription->id);

            $latest = $this->revisionRepository->latestForTranscription($transcription->id);

            if ($latest !== null && $latest->content_hash === $note_hash) {
                return $latest->id;
            }

            $revision_no = $this->revisionRepository->nextRevisionNo($transcription->id);

            $revision_id = $this->revisionRepository->create([
                'transcription_id' => $transcription->id,
                'revision_no' => $revision_no,
                'provider_key' => $transcription->provider_key,
                'origin_type' => RevisionOriginType::MANUAL_NOTE_SAVE->value,
                'origin_reference' => $transcription->current_note_xref,
                'content_format' => 'text/plain',
                'content_text' => $note_text,
                'content_hash' => $note_hash,
                'created_by_user_id' => $user_id,
                'import_comment' => $comment,
                'generated_note_xref' => $transcription->current_note_xref,
                'is_current_revision' => 1,
            ]);

            $this->revisionRepository->markCurrent($transcription->id, $revision_id);
            $this->revisionRepository->recordGeneratedNoteChange(
                $revision_id,
                $transcription->current_note_xref
            );

            $this->noteLinkRepository->createLink([
                'transcription_id' => $transcription->id,
                'revision_id' => $revision_id,
                'note_xref' => $transcription->current_note_xref,
                'link_type' => 'manual_note_save',
                'created_by_user_id' => $user_id,
                'is_current' => 1,
                'note_hash_at_link_time' => $note_hash,
            ]);

            $this->noteLinkRepository->markCurrent(
                $transcription->id,
                $transcription->current_note_xref
            );

            return $revision_id;
        });

        $revision = $this->revisionRepository->find($revision_id);

        if ($notify_collaborators && $revision !== null) {
            $this->notificationService->notifyRevisionCreated(
                $transcription,
                $user_id,
                $revision->revision_no,
                $this->collaboratorRepository->activeUserIds($transcription->id)
            );
        }

        return $revision_id;
    }
}
