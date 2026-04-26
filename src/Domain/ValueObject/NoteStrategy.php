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

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Domain\ValueObject;

use Fisharebest\Webtrees\I18N;

final class NoteStrategy
{
    //Options for NOTE strategy
    public const string ALWAYS_NEW = 'always_new';
    public const string UPDATE_IF_UNCHANGED = 'update_if_unchanged';
    public const string ALWAYS_UPDATE = 'always_update';

    /**
     * @return array<string,string>
     */
    public static function labels(): array
    {
        return [
            self::ALWAYS_NEW => I18N::translate('Always create a new NOTE'),
            self::UPDATE_IF_UNCHANGED => I18N::translate('Update existing NOTE only if unchanged'),
            self::ALWAYS_UPDATE => I18N::translate('Always update existing NOTE'),
        ];
    }

    public static function isValid(string $value): bool
    {
        return array_key_exists($value, self::labels());
    }

    public static function default(): string
    {
        return self::UPDATE_IF_UNCHANGED;
    }
}