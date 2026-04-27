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
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\SettingsRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\TranscriptionRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Webtrees\SharedNoteGateway;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Webtrees\SourceGateway;
use Hartenthaler\Webtrees\Module\SourceTranscription\SourceTranscription;

final class EnsureTagNoteService
{
    /**
     * @param SettingsRepository $settingsRepository
     * @param TranscriptionRepository $transcriptionRepository
     * @param SharedNoteGateway $sharedNoteGateway
     * @param SourceGateway $sourceGateway
     */
    public function __construct(
        private readonly SettingsRepository $settingsRepository,
        private readonly TranscriptionRepository $transcriptionRepository,
        private readonly SharedNoteGateway $sharedNoteGateway,
        private readonly SourceGateway $sourceGateway,
    ) {
    }

    public function ensureForTranscription(int $transcription_id): string
    {
        $transcription = $this->transcriptionRepository->find($transcription_id);

        if ($transcription === null) {
            throw new \RuntimeException('Transcription not found: ' . $transcription_id);
        }

        $tag_text = $this->settingsRepository->get('default_tag_text',
                        SourceTranscription::DEFAULT_TAG_PREFIX . SourceTranscription::DEFAULT_TAG_VALUE);

        $existing_note_xref = $this->findExistingTagNote(
            $transcription->tree_id,
            $transcription->source_xref,
            $tag_text
        );

        if ($existing_note_xref !== null) {
            $this->transcriptionRepository->setTagNoteXref($transcription_id, $existing_note_xref);

            return $existing_note_xref;
        }

        $note_xref = $this->sharedNoteGateway->createSharedNote(
            $transcription->tree_id,
            $tag_text
        );

        $this->sourceGateway->linkNoteToSource(
            $transcription->tree_id,
            $transcription->source_xref,
            $note_xref
        );

        $this->transcriptionRepository->setTagNoteXref($transcription_id, $note_xref);

        return $note_xref;
    }

    private function findExistingTagNote(
        int $tree_id,
        string $source_xref,
        string $tag_text
    ): ?string {
        $gedcom = DB::table('sources')
            ->where('s_file', '=', $tree_id)
            ->where('s_id', '=', $source_xref)
            ->value('s_gedcom');

        if ($gedcom === null) {
            return null;
        }

        foreach (preg_split('/\R/u', (string) $gedcom) ?: [] as $line) {
            if (!preg_match('/^\d+\s+NOTE\s+@([^@]+)@/u', $line, $match)) {
                continue;
            }

            $note_xref = $match[1];
            $note_text = $this->sharedNoteGateway->readSharedNote($tree_id, $note_xref);

            if (trim((string) $note_text) === trim($tag_text)) {
                return $note_xref;
            }
        }

        return null;
    }
}