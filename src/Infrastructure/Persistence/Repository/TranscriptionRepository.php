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
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tree;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Entity\Transcription;

final class TranscriptionRepository
{
    private const string TABLE = 'transcriptions';
    private const int DEFAULT_DASHBOARD_PER_PAGE = 20;

    private const array DASHBOARD_SORT_COLUMNS = [
        'title'    => 'title',
        'status'   => 'status',
        'provider' => 'provider_key',
        'created'  => 'created_at',
        'updated'  => 'updated_at',
    ];

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
    public function findBySource(Tree $tree, string $source_xref): array
    {
        return DB::table(self::TABLE)
            ->where('tree_id', '=', $tree->id())
            ->where('source_xref', '=', $source_xref)
            ->where('is_active', '=', true)
            ->orderBy('id')
            ->get()
            ->map(fn($row): Transcription => $this->map($row))
            ->all();
    }

    public function existsActiveForSource(Tree $tree, string $source_xref): bool
    {
        return DB::table(self::TABLE)
            ->where('tree_id', '=', $tree->id())
            ->where('source_xref', '=', $source_xref)
            ->where('is_active', '=', true)
            ->exists();
    }

    public function existsActiveForMedia(Tree $tree, string $media_xref): bool
    {
        return DB::table(self::TABLE)
            ->where('tree_id', '=', $tree->id())
            ->where('media_xref', '=', $media_xref)
            ->where('is_active', '=', true)
            ->exists();
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

    public function updateProvider(int $id, string $provider_key, string $interaction_model): void
    {
        DB::table(self::TABLE)
            ->where('id', '=', $id)
            ->update([
                'provider_key' => $provider_key,
                'interaction_model' => $interaction_model,
                'updated_at' => DB::raw('CURRENT_TIMESTAMP'),
            ]);
    }

    private function map(object $row): Transcription
    {
        return new Transcription(
            id: (int)$row->id,
            tree: Registry::container()->get(TreeService::class)->find((int)$row->tree_id),
            source_xref: (string)$row->source_xref,
            media_xref: $row->media_xref !== null ? (string)$row->media_xref : null,
            title: (string)$row->title,
            interaction_model: (string)$row->interaction_model,
            primary_language_tag: $row->primary_language_tag !== null ? (string) $row->primary_language_tag : null,
            primary_script_tag: $row->primary_script_tag !== null ? (string) $row->primary_script_tag : null,
            primary_form: $row->primary_form !== null ? (string) $row->primary_form : null,
            transcription_type: (string)$row->transcription_type,
            provider_key: (string)$row->provider_key,
            status: (string)$row->status,
            tag_note_xref: $row->tag_note_xref !== null ? (string)$row->tag_note_xref : null,
            current_note_xref: $row->current_note_xref !== null ? (string)$row->current_note_xref : null,
            created_by_user_id: (int)$row->created_by_user_id,
            created_at: (string)$row->created_at,
            updated_at: $row->updated_at !== null ? (string)$row->updated_at : null,
            is_active: (bool)$row->is_active,
        );
    }

    /**
     * @param Tree $tree
     * @return array<int, Transcription>
     */
    public function allActiveForTree(Tree $tree): array
    {
        return DB::table(self::TABLE)
            ->where('tree_id', '=', $tree->id())
            ->where('is_active', '=', true)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($row): Transcription => $this->map($row))
            ->all();
    }

    /**
     * @return array{
     *     items: array<int, Transcription>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     total_pages: int
     * }
     */
    public function dashboardForTree(
        Tree $tree,
        string $sort,
        string $direction,
        ?string $status,
        ?string $provider,
        int $page,
        int $per_page = self::DEFAULT_DASHBOARD_PER_PAGE,
        ?string $source_xref = null,
        ?string $media_xref = null,
    ): array {
        $query = DB::table(self::TABLE)
            ->where('tree_id', '=', $tree->id())
            ->where('is_active', '=', true);

        if ($status !== null) {
            $query->where('status', '=', $status);
        }

        if ($provider !== null) {
            $query->where('provider_key', '=', $provider);
        }

        if ($source_xref !== null) {
            $query->where('source_xref', '=', $source_xref);
        }

        if ($media_xref !== null) {
            $query->where('media_xref', '=', $media_xref);
        }

        $total = (int)(clone $query)->count();
        $per_page = max(1, $per_page);
        $total_pages = max(1, (int)ceil($total / $per_page));
        $page = min(max(1, $page), $total_pages);
        $column = self::DASHBOARD_SORT_COLUMNS[$sort] ?? self::DASHBOARD_SORT_COLUMNS['created'];
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        $items = $query
            ->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->forPage($page, $per_page)
            ->get()
            ->map(fn ($row): Transcription => $this->map($row))
            ->all();

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => $total_pages,
        ];
    }

    /**
     * @return array<int,string>
     */
    public function activeProviderKeysForTree(Tree $tree): array
    {
        return DB::table(self::TABLE)
            ->where('tree_id', '=', $tree->id())
            ->where('is_active', '=', true)
            ->distinct()
            ->orderBy('provider_key')
            ->pluck('provider_key')
            ->map(static fn ($provider_key): string => (string)$provider_key)
            ->all();
    }
}
