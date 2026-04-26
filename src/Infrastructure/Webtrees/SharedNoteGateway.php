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

//tbd: support for pending changes
//tbd: XREF nicht von Hand erzeugen, sondern webtrees-Funktion nutzen
//tbd: NOTE record nicht von Hand erzeugen, sondern Standardfunktion nutzen
//tbd: NOTE:CHAN:... erzeugen

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Webtrees;

use Fisharebest\Webtrees\DB;

final class SharedNoteGateway
{
    public function createSharedNote(int $tree_id, string $text): string
    {
        $xref = $this->nextNoteXref($tree_id);

        DB::table('other')->insert([
            'o_file' => $tree_id,
            'o_type' => 'NOTE',
            'o_id' => $xref,
            'o_gedcom' => $this->buildNoteGedcom($xref, $text),
        ]);

        return $xref;
    }

    public function readSharedNote(int $tree_id, string $note_xref): ?string
    {
        $gedcom = DB::table('other')
            ->where('o_file', '=', $tree_id)
            ->where('o_type', '=', 'NOTE')
            ->where('o_id', '=', $note_xref)
            ->value('o_gedcom');

        return $gedcom === null ? null : $this->extractNoteText((string)$gedcom);
    }

    public function updateSharedNote(int $tree_id, string $note_xref, string $text): void
    {
        DB::table('other')
            ->where('o_file', '=', $tree_id)
            ->where('o_type', '=', 'NOTE')
            ->where('o_id', '=', $note_xref)
            ->update([
                'o_gedcom' => $this->buildNoteGedcom($note_xref, $text),
            ]);
    }

    public function buildNoteGedcom(string $xref, string $text): string
    {
        $text = trim($text);

        // Single-line NOTE, e.g.
        // 0 @N123@ NOTE TAG: Transcription
        if (!str_contains($text, "\n") && !str_contains($text, "\r")) {
            return '0 @' . $xref . '@ NOTE ' . $text;
        }

        $lines = preg_split('/\R/u', $text) ?: [];

        $gedcom = '0 @' . $xref . '@ NOTE';

        foreach ($lines as $line) {
            $gedcom .= PHP_EOL . '1 CONT ' . $line;
        }

        return $gedcom;
    }

    private function extractNoteText(string $gedcom): string
    {
        $lines = preg_split('/\R/u', $gedcom) ?: [];

        if ($lines === []) {
            return '';
        }

        $first_line = $lines[0];

        if (preg_match('/^0\s+@[^@]+@\s+NOTE(?:\s+(.*))?$/u', $first_line, $match)) {
            $initial_text = $match[1] ?? '';

            $result = [];

            if ($initial_text !== '') {
                $result[] = $initial_text;
            }

            foreach (array_slice($lines, 1) as $line) {
                if (preg_match('/^\d+\s+CONT(?:\s+(.*))?$/u', $line, $m)) {
                    $result[] = $m[1] ?? '';
                    continue;
                }

                if (preg_match('/^\d+\s+CONC(?:\s+(.*))?$/u', $line, $m)) {
                    if ($result === []) {
                        $result[] = $m[1] ?? '';
                    } else {
                        $result[array_key_last($result)] .= $m[1] ?? '';
                    }
                }
            }

            return implode(PHP_EOL, $result);
        }

        return '';
    }

    private function nextNoteXref(int $tree_id): string
    {
        $max = DB::table('other')
            ->where('o_file', '=', $tree_id)
            ->where('o_type', '=', 'NOTE')
            ->where('o_id', 'LIKE', 'N%')
            ->selectRaw('MAX(CAST(SUBSTRING(o_id, 2) AS UNSIGNED)) AS max_id')
            ->value('max_id');

        return 'N' . (((int)$max) + 1);
    }
}