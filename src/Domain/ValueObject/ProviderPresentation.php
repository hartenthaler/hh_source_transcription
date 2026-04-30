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
use Fisharebest\Webtrees\Tree;

use function asset;
use function route;

final class ProviderPresentation
{
    public static function label(string $provider_key): string
    {
        return ProviderLabel::label($provider_key);
    }

    public static function url(string $provider_key, Tree $tree): string
    {
        return match ($provider_key) {
            ProviderLabel::MANUAL => route('source-transcription-dashboard', [
                'tree' => $tree->name(),
            ]),

            ProviderLabel::TRANSKRIBUS => 'https://www.transkribus.org/',
            ProviderLabel::DISCOURSE   => 'https://discourse.genealogy.net/',

            default => '',
        };
    }

    public static function icon(string $provider_key): string
    {
        return match ($provider_key) {
            ProviderLabel::MANUAL => asset('favicon.ico'),

            // Platzhalter; später besser eigene kleine SVG/PNG im Modul
            ProviderLabel::TRANSKRIBUS => '',
            ProviderLabel::DISCOURSE   => '',

            default => '',
        };
    }

    public static function title(string $provider_key): string
    {
        return match ($provider_key) {
            ProviderLabel::MANUAL => I18N::translate('Manual transcription in this webtrees installation'),
            ProviderLabel::TRANSKRIBUS => I18N::translate('Transkribus'),
            ProviderLabel::DISCOURSE => I18N::translate('Community discussion on Discourse'),
            default => ProviderLabel::label($provider_key),
        };
    }
}