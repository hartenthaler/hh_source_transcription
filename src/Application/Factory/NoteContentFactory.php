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

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Application\Factory;

use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Entity\TranscriptionRevision;

final class NoteContentFactory
{
    public function buildTranscriptNote(
        string                $title,
        TranscriptionRevision $revision
    ): string
    {
        return
            'Transcription' . PHP_EOL .
            'Title: ' . $title . PHP_EOL .
            'Revision: ' . $revision->revision_no . PHP_EOL .
            'Provider: ' . $revision->provider_key . PHP_EOL .
            'Origin: ' . $revision->origin_type . PHP_EOL .
            PHP_EOL .
            '--- Begin transcription ---' . PHP_EOL .
            PHP_EOL .
            $revision->content_text . PHP_EOL .
            PHP_EOL .
            '--- End transcription ---';
    }
}