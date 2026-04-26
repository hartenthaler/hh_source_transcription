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

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Entity;

final class TranscriptionRevision
{
    public function __construct(
        public readonly int $id,
        public readonly int $transcription_id,
        public readonly int $revision_no,
        public readonly string $provider_key,
        public readonly string $origin_type,
        public readonly ?string $origin_reference,
        public readonly string $content_format,
        public readonly string $content_text,
        public readonly string $content_hash,
        public readonly int $created_by_user_id,
        public readonly ?string $generated_note_xref,
        public readonly bool $is_current_revision,
    ) {
    }
}