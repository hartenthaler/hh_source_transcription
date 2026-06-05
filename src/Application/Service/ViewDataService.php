<?php

/*
 * webtrees: online genealogy application
 * Copyright (C) 2026 webtrees development team
 *                    <https://webtrees.net>
 *
 * Source Transcription (webtrees custom module):
 * Copyright (C) 2026 Hermann Hartenthaler
 *                     <https://ahnen.hartenthaler.eu>
 */

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Application\Service;

use DateTimeImmutable;
use DateTimeZone;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Mime;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Timestamp;
use Fisharebest\Webtrees\Tree;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Entity\Transcription;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Entity\TranscriptionRevision;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Enum\PrimaryForm;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Enum\PrimaryLanguage;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Enum\PrimaryScript;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Enum\RevisionOriginType;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Enum\TranscriptionStatus;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Enum\TranskribusJobFileStatus;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Enum\TranskribusJobStatus;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\ValueObject\ProviderPresentation;
use Hartenthaler\Webtrees\Module\SourceTranscription\Internationalization\MoreI18N;
use Hartenthaler\Webtrees\Module\SourceTranscription\Http\RequestHandlers\MediaFilesForMediaAction;
use Hartenthaler\Webtrees\Module\SourceTranscription\Http\RequestHandlers\MediaForSourceAction;
use Hartenthaler\Webtrees\Module\SourceTranscription\Http\RequestHandlers\SourceForManualAction;
use Hartenthaler\Webtrees\Module\SourceTranscription\Support\TranscriptionSlug;

use function e;
use function dirname;
use function filemtime;
use function in_array;
use function is_file;
use function max;
use function min;
use function parse_str;
use function parse_url;
use function pathinfo;
use function route;
use function strtok;
use function strtoupper;
use function trim;
use function view;

use const PATHINFO_EXTENSION;
use const PHP_URL_PATH;
use const PHP_URL_QUERY;

final class ViewDataService
{
    private const string CELL_FORMAT = 'text-decoration-none d-block';
    private const string MUTED = 'text-secondary fst-italic';

    public function cellFormat(): string
    {
        return self::CELL_FORMAT;
    }

    public function mutedClass(): string
    {
        return self::MUTED;
    }

    public function statusBadgeHtml(bool $status): string
    {
        return $status
            ? '<span class="badge bg-success">' . MoreI18N::xlate('yes') . '</span>'
            : '<span class="badge bg-secondary">' . MoreI18N::xlate('no') . '</span>';
    }

    public function providerStatusBadgeHtml(?string $status): string
    {
        return match ($status) {
            'success' => '<span class="badge bg-success">' . I18N::translate('successful') . '</span>',
            'failed' => '<span class="badge bg-danger">' . I18N::translate('failed') . '</span>',
            default => '<span class="badge bg-secondary">' . I18N::translate('not tested') . '</span>',
        };
    }

    public function timestampHtml(?string $timestamp): string
    {
        if ($timestamp === null || $timestamp === '') {
            return '<span class="' . self::MUTED . '">' . e(I18N::translate('None')) . '</span>';
        }

        $parsed_timestamp = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $timestamp, new DateTimeZone('UTC'));

        if ($parsed_timestamp === false) {
            $parsed_timestamp = DateTimeImmutable::createFromFormat('Y-m-d H:i', $timestamp, new DateTimeZone('UTC'));
        }

        if ($parsed_timestamp === false) {
            return e($timestamp);
        }

        $localized_timestamp = new Timestamp(
            $parsed_timestamp->getTimestamp(),
            Site::getPreference('TIMEZONE'),
            I18N::locale()->code()
        );

        return '<time datetime="' . e($localized_timestamp->toDateTimeString()) . '" title="' . e($localized_timestamp->isoFormat('LLLL')) . '" dir="auto">' .
            e($localized_timestamp->isoFormat('L LT')) .
            '</time>';
    }

    /**
     * @param array<string,int|string|null> $filters
     * @return array<string,mixed>
     */
    public function dashboardViewData(Tree $tree, array $filters, array $transcriptions, array $provider_options): array
    {
        $dashboard_url = route('source-transcription-dashboard', ['tree' => $tree->name()]);
        $form_action = $dashboard_url;
        $form_hidden = [];
        $form_query = parse_url($dashboard_url, PHP_URL_QUERY);

        if ($form_query !== null) {
            parse_str($form_query, $form_hidden);
            $form_action = strtok($dashboard_url, '?') ?: $dashboard_url;
        }

        $current_sort = (string) ($filters['sort'] ?? 'created');
        $current_direction = (string) ($filters['direction'] ?? 'desc');
        $current_status = ($filters['status'] ?? null) !== null ? (string) $filters['status'] : '';
        $current_provider = ($filters['provider'] ?? null) !== null ? (string) $filters['provider'] : '';
        $current_source_xref = ($filters['source_xref'] ?? null) !== null ? (string) $filters['source_xref'] : '';
        $current_media_xref = ($filters['media_xref'] ?? null) !== null ? (string) $filters['media_xref'] : '';
        $current_page = (int) ($filters['page'] ?? 1);
        $total_pages = (int) ($filters['total_pages'] ?? 1);
        $total = (int) ($filters['total'] ?? count($transcriptions));
        $per_page = (int) ($filters['per_page'] ?? 20);

        if ($current_source_xref !== '') {
            $form_hidden['source_xref'] = $current_source_xref;
        }

        if ($current_media_xref !== '') {
            $form_hidden['media_xref'] = $current_media_xref;
        }

        return [
            'cell_format' => self::CELL_FORMAT,
            'muted' => self::MUTED,
            'url' => $dashboard_url,
            'form_action' => $form_action,
            'form_hidden' => $form_hidden,
            'current_sort' => $current_sort,
            'current_direction' => $current_direction,
            'current_status' => $current_status,
            'current_provider' => $current_provider,
            'current_source_xref' => $current_source_xref,
            'current_media_xref' => $current_media_xref,
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'total' => $total,
            'first_item' => $total > 0 ? (($current_page - 1) * $per_page) + 1 : 0,
            'last_item' => $total > 0 ? min($total, $current_page * $per_page) : 0,
            'rows' => $this->dashboardRows($tree, $transcriptions),
            'sort_urls' => $this->dashboardSortUrls($tree, $current_sort, $current_direction, $current_status, $current_provider, $current_source_xref, $current_media_xref),
            'sort_indicators' => $this->dashboardSortIndicators($current_sort, $current_direction),
            'pagination' => $this->pagination($tree, $current_sort, $current_direction, $current_status, $current_provider, $current_source_xref, $current_media_xref, $current_page, $total_pages),
            'provider_labels' => $this->providerLabels($provider_options),
        ];
    }

    /**
     * @param array<int,object> $jobs
     * @param array<int,array<int,object>> $job_files
     * @return array<int,array<string,mixed>>
     */
    public function transkribusJobRows(Tree $tree, array $jobs, array $job_files): array
    {
        $rows = [];

        foreach ($jobs as $job) {
            $source = Registry::sourceFactory()->make((string) $job->source_xref, $tree);
            $media = Registry::mediaFactory()->make((string) $job->media_xref, $tree);

            $rows[] = [
                'id' => (string) $job->id,
                'title' => (string) $job->title,
                'source_html' => $source !== null
                    ? '<a href="' . e($source->url()) . '">' . $source->fullName() . '</a>'
                    : '<span class="' . self::MUTED . '">' . I18N::translate('Deleted') . '</span>',
                'media_html' => $media !== null
                    ? '<a href="' . e($media->url()) . '">' . $media->fullName() . '</a>'
                    : '<span class="' . self::MUTED . '">' . I18N::translate('Deleted') . '</span>',
                'badge_class' => match (TranskribusJobStatus::tryFrom((string) $job->status)) {
                    TranskribusJobStatus::UPLOADED => 'bg-success',
                    TranskribusJobStatus::UPLOADING => 'bg-warning text-dark',
                    TranskribusJobStatus::UPLOAD_FAILED => 'bg-danger',
                    null => 'bg-secondary',
                },
                'status_label' => TranskribusJobStatus::label((string) $job->status),
                'files' => $this->transkribusFileRows($job_files[(int) $job->id] ?? []),
                'last_message' => (string) ($job->last_message ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>
     */
    public function detailViewData(Tree $tree, Transcription $transcription, array $revisions): array
    {
        $note = $transcription->current_note_xref !== null
            ? Registry::noteFactory()->make($transcription->current_note_xref, $tree)
            : null;

        return [
            'cell_format' => self::CELL_FORMAT,
            'muted' => self::MUTED,
            'provider' => $this->provider($transcription->provider_key, $tree),
            'status_badge_class' => $this->transcriptionStatusBadgeClass($transcription->status),
            'status_label' => TranscriptionStatus::labels()[$transcription->status] ?? $transcription->status,
            'current_note_html' => $this->noteLinkHtml($transcription->current_note_xref, $note),
            'primary_language' => PrimaryLanguage::label($transcription->primary_language_tag),
            'primary_script' => PrimaryScript::label($transcription->primary_script_tag),
            'primary_form' => PrimaryForm::label($transcription->primary_form),
            'media_viewer_assets' => $this->mediaViewerAssets(),
            'compare_form' => count($revisions) >= 2 ? $this->compareFormData($tree, $transcription, $revisions) : null,
            'revision_rows' => $this->revisionRows($tree, $transcription, $revisions),
        ];
    }

    public function mediaRestrictionHtml(string $media_restriction, Tree $tree): string
    {
        return $media_restriction === ''
            ? ''
            : Registry::elementFactory()->make('OBJE:RESN')->labelValue($media_restriction, $tree);
    }

    /**
     * @return array{css_url:string,js_url:string,openseadragon_url:string|null,openseadragon_available:bool}
     */
    public function mediaViewerAssets(): array
    {
        $resources_dir = dirname(__DIR__, 3) . '/resources';
        $module_dir = dirname(__DIR__, 3);
        $asset_route = [
            'module' => '_hh_source_transcription_',
            'action' => 'Asset',
        ];
        $css_asset = 'css/media-viewer.css';
        $js_asset = 'js/media-viewer.js';
        $openseadragon_asset = 'openseadragon/openseadragon.min.js';
        $css_path = $resources_dir . DIRECTORY_SEPARATOR . $css_asset;
        $js_path = $resources_dir . DIRECTORY_SEPARATOR . $js_asset;
        $openseadragon_path = $module_dir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $openseadragon_asset;
        $openseadragon_available = is_file($openseadragon_path);

        return [
            'css_url' => route('module', $asset_route + ['asset' => $css_asset, 'hash' => is_file($css_path) ? (string) filemtime($css_path) : '0']),
            'js_url' => route('module', $asset_route + ['asset' => $js_asset, 'hash' => is_file($js_path) ? (string) filemtime($js_path) : '0']),
            'openseadragon_url' => $openseadragon_available
                ? route('source-transcription-vendor-asset', ['asset' => $openseadragon_asset, 'hash' => (string) filemtime($openseadragon_path)])
                : null,
            'openseadragon_available' => $openseadragon_available,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function createManualFormData(Tree $tree): array
    {
        return [
            'source_url' => route(SourceForManualAction::class, ['tree' => $tree->name(), 'at' => '@']),
            'media_url' => route(MediaForSourceAction::class, ['tree' => $tree->name()]),
            'language_options' => PrimaryLanguage::labels(),
            'script_options' => PrimaryScript::labels(),
            'form_options' => PrimaryForm::labels(),
            'messages' => [
                'no_results' => I18N::translate('No results found'),
                'search_source' => I18N::translate('Search for a source'),
                'select_source_first' => I18N::translate('Select a source first'),
                'loading_media' => I18N::translate('Loading media objects…'),
                'no_linked_media' => I18N::translate('No linked media objects found'),
                'no_media_selected' => I18N::translate('No media object selected'),
                'could_not_load_media' => I18N::translate('Could not load media objects'),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function createTranskribusFormData(Tree $tree): array
    {
        return [
            'source_url' => route(SourceForManualAction::class, ['tree' => $tree->name(), 'at' => '@']),
            'media_url' => route(MediaForSourceAction::class, ['tree' => $tree->name()]),
            'media_files_url' => route(MediaFilesForMediaAction::class, ['tree' => $tree->name()]),
            'messages' => [
                'no_results' => I18N::translate('No results found'),
                'search_source' => I18N::translate('Search for a source'),
                'select_source_first' => I18N::translate('Select a source first'),
                'select_media_first' => I18N::translate('Select a media object first.'),
                'loading_media' => I18N::translate('Loading media objects...'),
                'loading_files' => I18N::translate('Loading media files...'),
                'no_linked_media' => I18N::translate('No linked media objects found'),
                'select_media' => I18N::translate('Select a media object'),
                'no_files' => I18N::translate('No media files found.'),
                'could_not_load_media' => I18N::translate('Could not load media objects'),
                'could_not_load_files' => I18N::translate('Could not load media files.'),
            ],
        ];
    }

    private function transcriptionStatusBadgeClass(string $status): string
    {
        return match (TranscriptionStatus::tryFrom($status)) {
            TranscriptionStatus::NEW => 'bg-secondary',
            TranscriptionStatus::IN_PROGRESS => 'bg-warning text-dark',
            TranscriptionStatus::READY_FOR_REVIEW => 'bg-info text-dark',
            TranscriptionStatus::FINAL => 'bg-success',
            TranscriptionStatus::REOPENED => 'bg-primary',
            TranscriptionStatus::CANCELED => 'bg-light text-dark',
            null => 'bg-light text-dark',
        };
    }

    /**
     * @param array<int,Transcription> $transcriptions
     * @return array<int,array<string,mixed>>
     */
    private function dashboardRows(Tree $tree, array $transcriptions): array
    {
        $rows = [];

        foreach ($transcriptions as $transcription) {
            $source = Registry::sourceFactory()->make($transcription->source_xref, $tree);
            $media = $transcription->media_xref !== null
                ? Registry::mediaFactory()->make($transcription->media_xref, $tree)
                : null;
            $note = $transcription->current_note_xref !== null
                ? Registry::noteFactory()->make($transcription->current_note_xref, $tree)
                : null;

            $rows[] = [
                'title' => $transcription->title,
                'detail_url' => route('source-transcription-detail', [
                    'tree' => $tree->name(),
                    'transcription_id' => $transcription->id,
                    'slug' => TranscriptionSlug::fromTitle($transcription->title),
                ]),
                'action_url' => route('source-transcription-detail', [
                    'tree' => $tree->name(),
                    'transcription_id' => $transcription->id,
                ]),
                'source_html' => $source !== null
                    ? '<a href="' . e($source->url()) . '" class="' . self::CELL_FORMAT . '">' . ($source->fullName() ?: I18N::translate('Source record')) . '</a>'
                    : '<span class="' . self::MUTED . '">' . e(I18N::translate('Deleted')) . '</span>',
                'media_html' => $this->dashboardMediaHtml($media),
                'provider' => $this->provider($transcription->provider_key, $tree),
                'status_badge_class' => $this->transcriptionStatusBadgeClass($transcription->status),
                'status_label' => TranscriptionStatus::labels()[$transcription->status] ?? $transcription->status,
                'created_html' => $this->timestampHtml($transcription->created_at),
                'updated_html' => $this->timestampHtml($transcription->updated_at),
                'note_html' => $note !== null
                    ? '<a href="' . e($note->url()) . '" class="' . self::CELL_FORMAT . '">' . $note->fullName() . '</a>'
                    : '<span class="' . self::MUTED . '">' . e(I18N::translate('None')) . '</span>',
            ];
        }

        return $rows;
    }

    private function dashboardMediaHtml(?Media $media): string
    {
        if ($media === null) {
            return '<span class="' . self::MUTED . '">' . e(I18N::translate('None')) . '</span>';
        }

        $media_files = $media->mediaFiles();

        if ($media_files->isEmpty()) {
            return '<a href="' . e($media->url()) . '" class="' . self::CELL_FORMAT . '">' . ($media->fullName() ?: I18N::translate('Media object')) . '</a>';
        }

        $html = '';

        foreach ($media_files as $media_file) {
            $preview = $media_file->isExternal()
                ? $this->externalMediaIconHtml($media_file->filename())
                : $media_file->displayImage(38, 75, 'contain', []);

            $label = $media_file->title() !== '' ? $media_file->title() : $media_file->filename();
            $html .= '<div class="d-flex align-items-start gap-2 mb-1">';
            $html .= '<div class="text-center flex-shrink-0" style="width: 44px;">' . $preview . '</div>';
            $html .= '<div><a href="' . e($media->url()) . '" class="' . self::CELL_FORMAT . '">' . e($label) . '</a></div>';
            $html .= '</div>';
        }

        return $html;
    }

    private function externalMediaIconHtml(string $filename): string
    {
        $external_path = (string) (parse_url($filename, PHP_URL_PATH) ?: $filename);
        $external_extension = strtoupper(pathinfo($external_path, PATHINFO_EXTENSION));
        $external_mime = Mime::TYPES[$external_extension] ?? '';

        return '<span class="fs-5 text-muted" title="' . e(I18N::translate('External media URL')) . '">' .
            ($external_mime !== '' ? view('icons/mime', ['type' => $external_mime]) : view('icons/link')) .
            '</span>';
    }

    /**
     * @return array{label:string,url:string,icon:string,title:string}
     */
    private function provider(string $provider_key, Tree $tree): array
    {
        return [
            'label' => ProviderPresentation::label($provider_key),
            'url' => ProviderPresentation::url($provider_key, $tree),
            'icon' => ProviderPresentation::icon($provider_key),
            'title' => ProviderPresentation::title($provider_key),
        ];
    }

    private function noteLinkHtml(?string $xref, mixed $note): string
    {
        if ($xref !== null && $note !== null) {
            return '<a href="' . e($note->url()) . '" class="' . self::CELL_FORMAT . '">' . $note->fullName() . '</a>';
        }

        if ($xref !== null) {
            return e($xref);
        }

        return '<span class="' . self::MUTED . '">' . I18N::translate('None') . '</span>';
    }

    /**
     * @param array<int,TranscriptionRevision> $revisions
     * @return array<string,mixed>
     */
    private function compareFormData(Tree $tree, Transcription $transcription, array $revisions): array
    {
        $compare_url = route('source-transcription-compare-revisions', ['tree' => $tree->name(), 'transcription_id' => $transcription->id]);
        $compare_url_parts = parse_url($compare_url);
        $hidden = [];

        if (isset($compare_url_parts['query'])) {
            parse_str($compare_url_parts['query'], $hidden);
        }

        return [
            'action' => isset($compare_url_parts['query']) ? (string) ($compare_url_parts['path'] ?? '') : $compare_url,
            'hidden' => $hidden,
            'options' => array_map(
                static fn (TranscriptionRevision $revision): array => [
                    'id' => $revision->id,
                    'label' => I18N::translate('Revision %s', (string) $revision->revision_no),
                    'current' => $revision->is_current_revision,
                ],
                $revisions
            ),
        ];
    }

    /**
     * @param array<int,TranscriptionRevision> $revisions
     * @return array<int,array<string,mixed>>
     */
    private function revisionRows(Tree $tree, Transcription $transcription, array $revisions): array
    {
        return array_map(
            fn (TranscriptionRevision $revision): array => [
                'id' => $revision->id,
                'revision_no' => (string) $revision->revision_no,
                'is_current' => $revision->is_current_revision,
                'origin_label' => RevisionOriginType::labels()[$revision->origin_type] ?? $revision->origin_type,
                'generated_note_html' => $this->revisionNoteHtml($tree, $revision),
                'changed_by' => $revision->generated_note_changed_by_user_name,
                'changed_at_html' => $this->timestampHtml($revision->generated_note_changed_at),
                'text_preview' => mb_substr($revision->content_text, 0, 500),
                'make_current_url' => route('source-transcription-make-revision-current', [
                    'tree' => $tree->name(),
                    'transcription_id' => $transcription->id,
                    'revision_id' => $revision->id,
                ]),
            ],
            $revisions
        );
    }

    private function revisionNoteHtml(Tree $tree, TranscriptionRevision $revision): string
    {
        if ($revision->generated_note_xref === null) {
            return '';
        }

        $note = Registry::noteFactory()->make($revision->generated_note_xref, $tree);

        if ($note === null) {
            return e($revision->generated_note_xref);
        }

        return '<a href="' . e($note->url()) . '">' . e($revision->generated_note_xref) . '</a>';
    }

    /**
     * @return array<string,string>
     */
    private function providerLabels(array $provider_options): array
    {
        $labels = [];

        foreach ($provider_options as $provider_key) {
            $labels[$provider_key] = ProviderPresentation::label($provider_key);
        }

        return $labels;
    }

    /**
     * @return array<string,string>
     */
    private function dashboardSortUrls(Tree $tree, string $current_sort, string $current_direction, string $current_status, string $current_provider, string $source_xref, string $media_xref): array
    {
        $urls = [];

        foreach (['title', 'status', 'provider', 'created', 'updated'] as $sort) {
            $default_direction = in_array($sort, ['created', 'updated'], true) ? 'desc' : 'asc';
            $direction = $current_sort === $sort && $current_direction === 'asc' ? 'desc' : $default_direction;
            $urls[$sort] = $this->dashboardUrl($tree, $current_sort, $current_direction, $current_status, $current_provider, $source_xref, $media_xref, 1, [
                'sort' => $sort,
                'direction' => $direction,
            ]);
        }

        return $urls;
    }

    /**
     * @return array<string,string>
     */
    private function dashboardSortIndicators(string $current_sort, string $current_direction): array
    {
        $indicators = [];

        foreach (['title', 'status', 'provider', 'created', 'updated'] as $sort) {
            $indicators[$sort] = $current_sort !== $sort
                ? ''
                : ($current_direction === 'asc' ? ' <span aria-hidden="true">&uarr;</span>' : ' <span aria-hidden="true">&darr;</span>');
        }

        return $indicators;
    }

    /**
     * @return array{previous:string,next:string,pages:array<int,array{number:int,url:string,active:bool}},disabled_previous:bool,disabled_next:bool}
     */
    private function pagination(Tree $tree, string $sort, string $direction, string $status, string $provider, string $source_xref, string $media_xref, int $current_page, int $total_pages): array
    {
        $pages = [];

        for ($page = 1; $page <= $total_pages; $page++) {
            $pages[] = [
                'number' => $page,
                'url' => $this->dashboardUrl($tree, $sort, $direction, $status, $provider, $source_xref, $media_xref, $page),
                'active' => $page === $current_page,
            ];
        }

        return [
            'previous' => $this->dashboardUrl($tree, $sort, $direction, $status, $provider, $source_xref, $media_xref, max(1, $current_page - 1)),
            'next' => $this->dashboardUrl($tree, $sort, $direction, $status, $provider, $source_xref, $media_xref, min($total_pages, $current_page + 1)),
            'pages' => $pages,
            'disabled_previous' => $current_page <= 1,
            'disabled_next' => $current_page >= $total_pages,
        ];
    }

    /**
     * @param array<string,string|int|null> $overrides
     */
    private function dashboardUrl(Tree $tree, string $sort, string $direction, string $status, string $provider, string $source_xref, string $media_xref, int $page, array $overrides = []): string
    {
        $query = [
            'tree' => $tree->name(),
            'sort' => $sort,
            'direction' => $direction,
            'status' => $status,
            'provider' => $provider,
            'source_xref' => $source_xref,
            'media_xref' => $media_xref,
            'page' => $page,
        ];

        foreach ($overrides as $key => $value) {
            $query[$key] = $value;
        }

        $query = array_filter($query, static fn ($value): bool => $value !== null && $value !== '' && $value !== 1);

        return route('source-transcription-dashboard', $query);
    }

    /**
     * @param array<int,object> $files
     * @return array<int,array<string,string>>
     */
    private function transkribusFileRows(array $files): array
    {
        $rows = [];

        foreach ($files as $file) {
            $rows[] = [
                'filename' => (string) $file->filename,
                'upload_reference' => (string) ($file->upload_reference ?? ''),
                'status_label' => TranskribusJobFileStatus::label((string) $file->status),
                'message' => trim((string) ($file->message ?? '')),
            ];
        }

        return $rows;
    }
}
