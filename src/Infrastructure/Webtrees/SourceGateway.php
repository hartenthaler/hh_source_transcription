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

//tbd: die Quelle wird mit der shared NOTE verknüpft, aber die webtrees-internen Querverweis-Tabellen werden nicht aktualisiert Daher umstellen auf Standard-webtrees-Methoden statt dem direkten Zugriff auf die Datenbank.

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Webtrees;

use Fisharebest\Webtrees\DB;

final class SourceGateway
{
    public function linkNoteToSource(int $tree_id, string $source_xref, string $note_xref): void
    {
        $gedcom = DB::table('sources')
            ->where('s_file', '=', $tree_id)
            ->where('s_id', '=', $source_xref)
            ->value('s_gedcom');

        if ($gedcom === null) {
            throw new \RuntimeException('Source not found: ' . $source_xref);
        }

        $gedcom = rtrim((string) $gedcom);

        if (preg_match('/^\d+\s+NOTE\s+@' . preg_quote($note_xref, '/') . '@/mu', $gedcom)) {
            return;
        }

        $gedcom .= PHP_EOL . '1 NOTE @' . $note_xref . '@';

        DB::table('sources')
            ->where('s_file', '=', $tree_id)
            ->where('s_id', '=', $source_xref)
            ->update([
                's_gedcom' => $gedcom,
            ]);
    }
}