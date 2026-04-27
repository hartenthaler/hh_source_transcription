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
use Hartenthaler\Webtrees\Module\SourceTranscription\Application\Dto\CreateTranscriptionCommand;
use Hartenthaler\Webtrees\Module\SourceTranscription\Application\Provider\ProviderMetadata;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\ValueObject\InteractionModel;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\ValueObject\ProviderKey;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\ValueObject\RevisionOriginType;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\ValueObject\TranscriptionStatus;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\ValueObject\TranscriptionType;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\RevisionRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\TranscriptionRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Support\HashService;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\ValueObject\NoteStrategy;
use InvalidArgumentException;

final class CreateTranscriptionService
{
    /**
     * @param TranscriptionRepository $transcriptionRepository
     * @param RevisionRepository $revisionRepository
     * @param HashService $hashService
     * @param GenerateOrUpdateNoteService $generateOrUpdateNoteService
     * @param EnsureTagNoteService $ensureTagNoteService
     */
    public function __construct(
        private readonly TranscriptionRepository $transcriptionRepository,
        private readonly RevisionRepository $revisionRepository,
        private readonly HashService $hashService,
        private readonly GenerateOrUpdateNoteService $generateOrUpdateNoteService,
        private readonly EnsureTagNoteService $ensureTagNoteService,
    ) {
    }

    public function createManual(CreateTranscriptionCommand $command): int
    {
        if ($command->provider_key !== ProviderKey::MANUAL) {
            throw new InvalidArgumentException('Only manual transcriptions are supported by createManual().');
        }

        if (trim($command->source_xref) === '') {
            throw new InvalidArgumentException('source_xref must not be empty.');
        }

        if (trim($command->title) === '') {
            throw new InvalidArgumentException('title must not be empty.');
        }

        return DB::transaction(function () use ($command): int {
            $transcription_id = $this->transcriptionRepository->create([
                'tree_id' => $command->tree_id,
                'source_xref' => $command->source_xref,
                'media_xref' => $command->media_xref,
                'title' => $command->title,
                'interaction_model' => ProviderMetadata::interactionModel($command->provider_key),
                'transcription_type' => TranscriptionType::TRANSCRIPTION,
                'provider_key' => ProviderKey::MANUAL,
                'status' => TranscriptionStatus::NEW,
                'tag_note_xref' => null,
                'current_note_xref' => null,
                'created_by_user_id' => $command->user_id,
                'is_active' => 1,
            ]);

            $revision_id = $this->revisionRepository->create([
                'transcription_id' => $transcription_id,
                'revision_no' => 1,
                'provider_key' => ProviderKey::MANUAL,
                'origin_type' => RevisionOriginType::MANUAL_ENTRY,
                'origin_reference' => null,
                'content_format' => 'text/plain',
                'content_text' => $command->initial_text,
                'content_hash' => $this->hashService->sha256($command->initial_text),
                'created_by_user_id' => $command->user_id,
                'import_comment' => $command->comment,
                'generated_note_xref' => null,
                'is_current_revision' => 1,
            ]);

            $this->revisionRepository->markCurrent($transcription_id, $revision_id);
            $this->generateOrUpdateNoteService->applyRevisionToCurrentNote(
                $transcription_id,
                $revision_id,
                NoteStrategy::UPDATE_IF_UNCHANGED
            );

            $this->ensureTagNoteService->ensureForTranscription($transcription_id);

            return $transcription_id;
        });
    }
}