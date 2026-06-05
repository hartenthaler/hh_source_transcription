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

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Http\RequestHandlers;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Registry;
use Hartenthaler\Webtrees\Module\SourceTranscription\Application\Service\ViewDataService;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Enum\TranscriptionStatus;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\ValueObject\ProviderKey;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\ProviderCredentialRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\SettingsRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\TranskribusJobRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\TranscriptionRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\SourceTranscription;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function response;
use function trim;
use function view;

final class DashboardAction
{
    private const array SORT_OPTIONS = ['title', 'status', 'provider', 'created', 'updated'];

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');

        if (!Auth::isMember($tree)) return response('');

        $title = I18N::translate('Source capture and analysis');

        $repo = Registry::container()->get(TranscriptionRepository::class);
        $job_repo = Registry::container()->get(TranskribusJobRepository::class);
        $settings = Registry::container()->get(SettingsRepository::class);
        $credential_repo = Registry::container()->get(ProviderCredentialRepository::class);
        $view_data_service = Registry::container()->get(ViewDataService::class);
        $discourse_credential = $credential_repo->find(Auth::user()->id(), ProviderKey::DISCOURSE);

        $query_params = $request->getQueryParams();
        $sort = $this->validSort($this->queryParam($query_params, 'sort', 'created'));
        $direction = $this->validDirection($this->queryParam($query_params, 'direction', 'desc'));
        $status = $this->validStatus($this->queryParam($query_params, 'status'));
        $provider_keys = $repo->activeProviderKeysForTree($tree);
        $provider = $this->validProvider($this->queryParam($query_params, 'provider'), $provider_keys);
        $source_xref = $this->queryXref($query_params, 'source_xref');
        $media_xref = $this->queryXref($query_params, 'media_xref');
        $page = max(1, $this->queryInt($query_params, 'page', 1));
        $per_page = $this->dashboardPageSize($settings);
        $dashboard = $repo->dashboardForTree($tree, $sort, $direction, $status, $provider, $page, $per_page, $source_xref, $media_xref);

        $transkribus_jobs = $job_repo->recentForTree($tree);
        $transkribus_job_files = [];

        foreach ($transkribus_jobs as $job) {
            $transkribus_job_files[(int) $job->id] = $job_repo->filesForJob((int) $job->id);
        }

        $content = view('hh_source_transcription::dashboard', [
            'title'            => $title,
            'tree'             => $tree,
            'dashboard'        => $view_data_service->dashboardViewData($tree, $dashboard['filters'] ?? [
                'sort'        => $sort,
                'direction'   => $direction,
                'status'      => $status,
                'provider'    => $provider,
                'source_xref' => $source_xref,
                'media_xref'  => $media_xref,
                'page'        => $dashboard['page'],
                'per_page'    => $dashboard['per_page'],
                'total'       => $dashboard['total'],
                'total_pages' => $dashboard['total_pages'],
            ], $dashboard['items'], $provider_keys),
            'dashboard_filters' => [
                'sort'        => $sort,
                'direction'   => $direction,
                'status'      => $status,
                'provider'    => $provider,
                'source_xref' => $source_xref,
                'media_xref'  => $media_xref,
                'page'        => $dashboard['page'],
                'per_page'    => $dashboard['per_page'],
                'total'       => $dashboard['total'],
                'total_pages' => $dashboard['total_pages'],
            ],
            'status_options'   => TranscriptionStatus::labels(),
            'provider_options' => $provider_keys,
            'transkribus_job_rows' => $view_data_service->transkribusJobRows($tree, $transkribus_jobs, $transkribus_job_files),
            'discourse_authorized' => (bool) ($discourse_credential['has_secret'] ?? false),
            'discourse_settings' => $discourse_credential['settings'] ?? [],
            'discourse_last_test_status' => $discourse_credential['last_test_status'] ?? null,
            'discourse_last_test_message' => $discourse_credential['last_test_message'] ?? null,
        ]);

        return response(view('layouts/default', [
            'title' => $title,
            'tree'    => $tree,
            'request' => $request,
            'content' => $content,
        ]));
    }

    /**
     * @param array<string,mixed> $params
     */
    private function queryParam(array $params, string $key, string $default = ''): string
    {
        $value = $params[$key] ?? $default;

        return is_scalar($value) ? trim((string)$value) : $default;
    }

    /**
     * @param array<string,mixed> $params
     */
    private function queryInt(array $params, string $key, int $default): int
    {
        $value = $params[$key] ?? $default;

        return is_scalar($value) ? (int)$value : $default;
    }

    private function validSort(string $sort): string
    {
        return in_array($sort, self::SORT_OPTIONS, true) ? $sort : 'created';
    }

    private function validDirection(string $direction): string
    {
        return strtolower($direction) === 'asc' ? 'asc' : 'desc';
    }

    private function validStatus(string $status): ?string
    {
        return array_key_exists($status, TranscriptionStatus::labels()) ? $status : null;
    }

    /**
     * @param array<int,string> $provider_keys
     */
    private function validProvider(string $provider, array $provider_keys): ?string
    {
        return in_array($provider, $provider_keys, true) ? $provider : null;
    }

    /**
     * @param array<string,mixed> $params
     */
    private function queryXref(array $params, string $key): ?string
    {
        $xref = trim($this->queryParam($params, $key), '@');

        return $xref === '' ? null : $xref;
    }

    private function dashboardPageSize(SettingsRepository $settings): int
    {
        $page_size = (int)$settings->get(
            SourceTranscription::DASHBOARD_PAGE_SIZE,
            (string)SourceTranscription::DEFAULT_DASHBOARD_PAGE_SIZE
        );

        return min(
            SourceTranscription::MAXIMUM_DASHBOARD_PAGE_SIZE,
            max(SourceTranscription::MINIMUM_DASHBOARD_PAGE_SIZE, $page_size)
        );
    }
}
