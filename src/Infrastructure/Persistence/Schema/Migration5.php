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

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Schema;

use Fisharebest\Webtrees\Schema\MigrationInterface;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Upgrade the database schema from version 5 to version 6.
 */
class Migration5 implements MigrationInterface
{
    public function upgrade(): void
    {
        $this->renumberRevisionsDeterministically();

        DB::schema()->table(SchemaManager::TABLE_REVISIONS, static function ($table): void {
            $table->unique(['transcription_id', 'revision_no'], 'idx_revisions_transcription_revision_no');
        });
    }

    private function renumberRevisionsDeterministically(): void
    {
        $rows = DB::table(SchemaManager::TABLE_REVISIONS)
            ->select(['id', 'transcription_id', 'revision_no'])
            ->orderBy('transcription_id')
            ->orderBy('revision_no')
            ->orderBy('id')
            ->get();

        $used_revision_numbers = [];

        foreach ($rows as $row) {
            $transcription_id = (int) $row->transcription_id;
            $revision_no = (int) $row->revision_no;

            if (!isset($used_revision_numbers[$transcription_id])) {
                $used_revision_numbers[$transcription_id] = [];
            }

            if (!isset($used_revision_numbers[$transcription_id][$revision_no])) {
                $used_revision_numbers[$transcription_id][$revision_no] = true;
                continue;
            }

            $revision_no++;

            while (isset($used_revision_numbers[$transcription_id][$revision_no])) {
                $revision_no++;
            }

            $used_revision_numbers[$transcription_id][$revision_no] = true;

            DB::table(SchemaManager::TABLE_REVISIONS)
                ->where('id', '=', (int) $row->id)
                ->update(['revision_no' => $revision_no]);
        }
    }
}
