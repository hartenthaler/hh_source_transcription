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

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Application\Service;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\UserService;
use Hartenthaler\Webtrees\Module\SourceTranscription\Internationalization\MoreI18N;
use Fisharebest\Webtrees\Tree;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Entity\TranscriptionRevision;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Enum\RevisionOriginType;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\ValueObject\ProviderPresentation;

use function e;

final class CompareRevisionsService
{
    public function __construct(
        private readonly UserService $userService,
    ) {
    }

    public function revisionTitle(TranscriptionRevision $revision): string
    {
        return I18N::translate('Revision %s', (string) $revision->revision_no);
    }

    /**
     * @return array<int,array{key:string,label:string,left:string,right:string,changed:bool}>
     */
    public function metadataRows(TranscriptionRevision $left, TranscriptionRevision $right): array
    {
        $rows = [
            ['revision_no', I18N::translate('Revision'), (string) $left->revision_no, (string) $right->revision_no],
            [
                'is_current_revision',
                I18N::translate('Current revision'),
                $left->is_current_revision ? MoreI18N::xlate('yes') : MoreI18N::xlate('no'),
                $right->is_current_revision ? MoreI18N::xlate('yes') : MoreI18N::xlate('no'),
            ],
            ['provider_key', I18N::translate('Provider'), ProviderPresentation::label($left->provider_key), ProviderPresentation::label($right->provider_key)],
            ['origin_type', I18N::translate('Origin'), RevisionOriginType::labels()[$left->origin_type] ?? $left->origin_type, RevisionOriginType::labels()[$right->origin_type] ?? $right->origin_type],
            ['origin_reference', I18N::translate('Origin reference'), $left->origin_reference ?? '', $right->origin_reference ?? ''],
            ['content_format', I18N::translate('Content format'), $left->content_format, $right->content_format],
            ['content_hash', I18N::translate('Content hash'), $left->content_hash, $right->content_hash],
            ['created_by_user_id', I18N::translate('Created by'), $this->userLabel($left->created_by_user_id), $this->userLabel($right->created_by_user_id)],
            ['created_at', I18N::translate('Created at'), $left->created_at, $right->created_at],
            ['import_comment', I18N::translate('Import comment'), $left->import_comment ?? '', $right->import_comment ?? ''],
            ['generated_note_xref', I18N::translate('Generated NOTE'), $left->generated_note_xref ?? '', $right->generated_note_xref ?? ''],
            ['generated_note_changed_by_user_name', I18N::translate('NOTE changed by'), $left->generated_note_changed_by_user_name ?? '', $right->generated_note_changed_by_user_name ?? ''],
            ['generated_note_changed_at', I18N::translate('NOTE changed at'), $left->generated_note_changed_at ?? '', $right->generated_note_changed_at ?? ''],
        ];

        return array_map(
            static fn (array $row): array => [
                'key' => $row[0],
                'label' => $row[1],
                'left' => $row[2],
                'right' => $row[3],
                'changed' => $row[2] !== $row[3],
            ],
            $rows
        );
    }

    /**
     * @return array<int,array{label:string,left_html:string,right_html:string,changed:bool}>
     */
    public function metadataRowsForView(Tree $tree, TranscriptionRevision $left, TranscriptionRevision $right): array
    {
        return array_map(
            fn (array $row): array => [
                'label' => $row['label'],
                'left_html' => $this->metadataValueHtml($tree, $row['key'], $row['left']),
                'right_html' => $this->metadataValueHtml($tree, $row['key'], $row['right']),
                'changed' => $row['changed'],
            ],
            $this->metadataRows($left, $right)
        );
    }

    /**
     * @return array<int,array{type:string,left:?string,right:?string}>
     */
    public function textDiff(TranscriptionRevision $left, TranscriptionRevision $right): array
    {
        $left_lines = $this->splitLines($left->content_text);
        $right_lines = $this->splitLines($right->content_text);
        $left_count = count($left_lines);
        $right_count = count($right_lines);

        if ($left_count * $right_count > 250000) {
            return $this->pairedLineDiff($left_lines, $right_lines);
        }

        $lcs = array_fill(0, $left_count + 1, array_fill(0, $right_count + 1, 0));

        for ($i = $left_count - 1; $i >= 0; $i--) {
            for ($j = $right_count - 1; $j >= 0; $j--) {
                if ($left_lines[$i] === $right_lines[$j]) {
                    $lcs[$i][$j] = $lcs[$i + 1][$j + 1] + 1;
                } else {
                    $lcs[$i][$j] = max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
                }
            }
        }

        $diff = [];
        $i = 0;
        $j = 0;

        while ($i < $left_count && $j < $right_count) {
            if ($left_lines[$i] === $right_lines[$j]) {
                $diff[] = ['type' => 'unchanged', 'left' => $left_lines[$i], 'right' => $right_lines[$j]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $diff[] = ['type' => 'removed', 'left' => $left_lines[$i], 'right' => null];
                $i++;
            } else {
                $diff[] = ['type' => 'added', 'left' => null, 'right' => $right_lines[$j]];
                $j++;
            }
        }

        while ($i < $left_count) {
            $diff[] = ['type' => 'removed', 'left' => $left_lines[$i], 'right' => null];
            $i++;
        }

        while ($j < $right_count) {
            $diff[] = ['type' => 'added', 'left' => null, 'right' => $right_lines[$j]];
            $j++;
        }

        return $diff;
    }

    /**
     * @return array<int,array{row_class:string,left:string,right:string}>
     */
    public function textDiffRowsForView(TranscriptionRevision $left, TranscriptionRevision $right): array
    {
        return array_map(
            static fn (array $row): array => [
                'row_class' => match ($row['type']) {
                    'added' => 'table-success',
                    'removed' => 'table-danger',
                    'changed' => 'table-warning',
                    default => '',
                },
                'left' => $row['left'] ?? '',
                'right' => $row['right'] ?? '',
            ],
            $this->textDiff($left, $right)
        );
    }

    /**
     * @return array<int,string>
     */
    private function splitLines(string $text): array
    {
        if ($text === '') {
            return [];
        }

        return preg_split('/\R/u', $text) ?: [];
    }

    /**
     * @param array<int,string> $left_lines
     * @param array<int,string> $right_lines
     * @return array<int,array{type:string,left:?string,right:?string}>
     */
    private function pairedLineDiff(array $left_lines, array $right_lines): array
    {
        $diff = [];
        $max = max(count($left_lines), count($right_lines));

        for ($i = 0; $i < $max; $i++) {
            $left = $left_lines[$i] ?? null;
            $right = $right_lines[$i] ?? null;

            if ($left === $right) {
                $diff[] = ['type' => 'unchanged', 'left' => $left, 'right' => $right];
            } elseif ($left === null) {
                $diff[] = ['type' => 'added', 'left' => null, 'right' => $right];
            } elseif ($right === null) {
                $diff[] = ['type' => 'removed', 'left' => $left, 'right' => null];
            } else {
                $diff[] = ['type' => 'changed', 'left' => $left, 'right' => $right];
            }
        }

        return $diff;
    }

    private function metadataValueHtml(Tree $tree, string $key, string $value): string
    {
        if ($value === '') {
            return '';
        }

        $record = match ($key) {
            'generated_note_xref' => Registry::noteFactory()->make($value, $tree),
            'origin_reference' => Registry::gedcomRecordFactory()->make(trim($value, '@'), $tree),
            default => null,
        };

        if ($record === null) {
            return e($value);
        }

        return '<a href="' . e($record->url()) . '">' . e($value) . '</a>';
    }

    private function userLabel(int $user_id): string
    {
        $user = $this->userService->find($user_id);

        if ($user === null) {
            return '#' . $user_id;
        }

        return $user->realName() !== '' ? $user->realName() : $user->userName();
    }
}
