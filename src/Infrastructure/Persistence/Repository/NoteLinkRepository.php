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

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository;

use Fisharebest\Webtrees\DB;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Entity\NoteLink;

final class NoteLinkRepository
{
    private const TABLE = 'transcription_note_links';

    public function createLink(array $data): int
    {
        return (int)DB::table(self::TABLE)->insertGetId($data);
    }

    public function currentLinkForTranscription(int $transcription_id): ?NoteLink
    {
        $row = DB::table(self::TABLE)
            ->where('transcription_id', '=', $transcription_id)
            ->where('is_current', '=', true)
            ->orderByDesc('id')
            ->first();

        return $row === null ? null : $this->map($row);
    }

    public function currentNoteForTranscription(int $transcription_id): ?string
    {
        $row = $this->currentLinkForTranscription($transcription_id);

        return $row?->note_xref;
    }

    public function markCurrent(int $transcription_id, string $note_xref): void
    {
        DB::transaction(function () use ($transcription_id, $note_xref): void {
            DB::table(self::TABLE)
                ->where('transcription_id', '=', $transcription_id)
                ->update(['is_current' => false]);

            DB::table(self::TABLE)
                ->where('transcription_id', '=', $transcription_id)
                ->where('note_xref', '=', $note_xref)
                ->update(['is_current' => true]);
        });
    }

    private function map(object $row): NoteLink
    {
        return new NoteLink(
            id: (int)$row->id,
            transcription_id: (int)$row->transcription_id,
            revision_id: $row->revision_id !== null ? (int)$row->revision_id : null,
            note_xref: (string)$row->note_xref,
            link_type: (string)$row->link_type,
            created_by_user_id: (int)$row->created_by_user_id,
            is_current: (bool)$row->is_current,
            note_hash_at_link_time: $row->note_hash_at_link_time !== null ? (string)$row->note_hash_at_link_time : null,
        );
    }
}