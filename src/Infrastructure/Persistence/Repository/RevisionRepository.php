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

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\DB;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Entity\TranscriptionRevision;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Schema\SchemaManager;
use RuntimeException;

final class RevisionRepository
{
    private const string TABLE = 'transcription_revisions';

    /**
     * @param array $data
     * @return int
     */
    public function create(array $data): int
    {
        return (int)DB::table(self::TABLE)->insertGetId($data);
    }

    /**
     * @param int $id
     * @return TranscriptionRevision|null
     */
    public function find(int $id): ?TranscriptionRevision
    {
        $row = DB::table(self::TABLE)->where('id', '=', $id)->first();

        return $row === null ? null : $this->map($row);
    }

    /**
     * @param int $transcription_id
     * @return int
     */
    public function nextRevisionNo(int $transcription_id): int
    {
        $this->lockTranscriptionForRevisionAllocation($transcription_id);

        $max = DB::table(self::TABLE)
            ->where('transcription_id', '=', $transcription_id)
            ->max('revision_no');

        return ((int)$max) + 1;
    }

    public function lockTranscriptionForRevisionAllocation(int $transcription_id): void
    {
        $locked_id = DB::table(SchemaManager::TABLE_TRANSCRIPTIONS)
            ->where('id', '=', $transcription_id)
            ->lockForUpdate()
            ->value('id');

        if ($locked_id === null) {
            throw new RuntimeException('Transcription not found: ' . $transcription_id);
        }
    }

    public function latestForTranscription(int $transcription_id): ?TranscriptionRevision
    {
        $row = DB::table(self::TABLE)
            ->where('transcription_id', '=', $transcription_id)
            ->orderByDesc('revision_no')
            ->first();

        return $row === null ? null : $this->map($row);
    }

    public function markCurrent(int $transcription_id, int $revision_id): void
    {
        DB::transaction(function () use ($transcription_id, $revision_id): void {
            DB::table(self::TABLE)
                ->where('transcription_id', '=', $transcription_id)
                ->update(['is_current_revision' => false]);

            DB::table(self::TABLE)
                ->where('id', '=', $revision_id)
                ->where('transcription_id', '=', $transcription_id)
                ->update(['is_current_revision' => true]);
        });
    }

    public function recordGeneratedNoteChange(int $revision_id, string $note_xref): void
    {
        DB::table(self::TABLE)
            ->where('id', '=', $revision_id)
            ->update([
                'generated_note_xref' => $note_xref,
                'generated_note_changed_by_user_id' => Auth::id(),
                'generated_note_changed_by_user_name' => Auth::user()->userName(),
                'generated_note_changed_at' => date('Y-m-d H:i:s'),
            ]);
    }

    private function map(object $row): TranscriptionRevision
    {
        return new TranscriptionRevision(
            id: (int)$row->id,
            transcription_id: (int)$row->transcription_id,
            revision_no: (int)$row->revision_no,
            provider_key: (string)$row->provider_key,
            origin_type: (string)$row->origin_type,
            origin_reference: $row->origin_reference !== null ? (string)$row->origin_reference : null,
            content_format: (string)$row->content_format,
            content_text: (string)$row->content_text,
            content_hash: (string)$row->content_hash,
            created_by_user_id: (int)$row->created_by_user_id,
            created_at: (string)$row->created_at,
            import_comment: isset($row->import_comment) && $row->import_comment !== null ? (string)$row->import_comment : null,
            generated_note_xref: $row->generated_note_xref !== null ? (string)$row->generated_note_xref : null,
            generated_note_changed_by_user_id: isset($row->generated_note_changed_by_user_id) && $row->generated_note_changed_by_user_id !== null ? (int)$row->generated_note_changed_by_user_id : null,
            generated_note_changed_by_user_name: isset($row->generated_note_changed_by_user_name) && $row->generated_note_changed_by_user_name !== null ? (string)$row->generated_note_changed_by_user_name : null,
            generated_note_changed_at: isset($row->generated_note_changed_at) && $row->generated_note_changed_at !== null ? (string)$row->generated_note_changed_at : null,
            is_current_revision: (bool)$row->is_current_revision,
        );
    }

    /**
     * @return array<int, TranscriptionRevision>
     */
    public function findByTranscription(int $transcription_id): array
    {
        return DB::table(self::TABLE)
            ->where('transcription_id', '=', $transcription_id)
            ->orderByDesc('revision_no')
            ->get()
            ->map(fn ($row): TranscriptionRevision => $this->map($row))
            ->all();
    }
}
