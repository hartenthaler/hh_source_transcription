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
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Entity\Transcription;

final class TranscriptionRepository
{
    private const string TABLE = 'transcriptions';

    public function create(array $data): int
    {
        return (int)DB::table(self::TABLE)->insertGetId($data);
    }

    public function find(int $id): ?Transcription
    {
        $row = DB::table(self::TABLE)->where('id', '=', $id)->first();

        return $row === null ? null : $this->map($row);
    }

    /**
     * @return array<int,Transcription>
     */
    public function findBySource(int $tree_id, string $source_xref): array
    {
        return DB::table(self::TABLE)
            ->where('tree_id', '=', $tree_id)
            ->where('source_xref', '=', $source_xref)
            ->where('is_active', '=', true)
            ->orderBy('id')
            ->get()
            ->map(fn($row): Transcription => $this->map($row))
            ->all();
    }

    public function setCurrentNoteXref(int $id, ?string $note_xref): void
    {
        DB::table(self::TABLE)
            ->where('id', '=', $id)
            ->update([
                'current_note_xref' => $note_xref,
                'updated_at' => DB::raw('CURRENT_TIMESTAMP'),
            ]);
    }

    public function setTagNoteXref(int $id, ?string $note_xref): void
    {
        DB::table(self::TABLE)
            ->where('id', '=', $id)
            ->update([
                'tag_note_xref' => $note_xref,
                'updated_at' => DB::raw('CURRENT_TIMESTAMP'),
            ]);
    }

    public function updateStatus(int $id, string $status): void
    {
        DB::table(self::TABLE)
            ->where('id', '=', $id)
            ->update([
                'status' => $status,
                'updated_at' => DB::raw('CURRENT_TIMESTAMP'),
            ]);
    }

    private function map(object $row): Transcription
    {
        return new Transcription(
            id: (int)$row->id,
            tree_id: (int)$row->tree_id,
            source_xref: (string)$row->source_xref,
            media_xref: $row->media_xref !== null ? (string)$row->media_xref : null,
            title: (string)$row->title,
            transcription_type: (string)$row->transcription_type,
            provider_key: (string)$row->provider_key,
            status: (string)$row->status,
            tag_note_xref: $row->tag_note_xref !== null ? (string)$row->tag_note_xref : null,
            current_note_xref: $row->current_note_xref !== null ? (string)$row->current_note_xref : null,
            created_by_user_id: (int)$row->created_by_user_id,
            is_active: (bool)$row->is_active,
        );
    }
}