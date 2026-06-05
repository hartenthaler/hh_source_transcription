<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Application\Service;

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Media;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\SettingsRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\TranscriptionRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Webtrees\MediaObjectGateway;
use Hartenthaler\Webtrees\Module\SourceTranscription\SourceTranscription;
use Throwable;

use function class_exists;
use function e;
use function route;
use function trim;
use function view;

final class MediaBadgeService
{
    public function __construct(
        private readonly SettingsRepository $settingsRepository,
        private readonly TranscriptionRepository $transcriptionRepository,
        private readonly MediaObjectGateway $mediaObjectGateway,
    ) {
    }

    public function enabled(): bool
    {
        try {
            return $this->settingsRepository->get(SourceTranscription::SOURCE_BADGES, 'enabled') === 'enabled';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{label:string,class:string,title:string,url:string|null}|null
     */
    public function badge(Media $media): ?array
    {
        if (!$this->enabled() || !$this->isLinkedToSource($media)) {
            return null;
        }

        if ($this->transcriptionRepository->existsActiveForMedia($media->tree(), $media->xref())) {
            return [
                'label' => 'T',
                'class' => 'hh-st-transcription-badge hh-st-transcription-badge--transcribed',
                'title' => I18N::translate('This media object is linked to a source and already has a transcription.'),
                'url' => $this->dashboardUrl($media, ['media_xref' => $media->xref()]),
            ];
        }

        if ($this->mediaObjectGateway->hasTranscriptionSuitableFile($media)) {
            return [
                'label' => 'T',
                'class' => 'hh-st-transcription-badge hh-st-transcription-badge--ready',
                'title' => I18N::translate('This media object is linked to a source and is suitable for transcription, but no transcription exists yet.'),
                'url' => $this->dashboardUrl($media),
            ];
        }

        return [
            'label' => 'T',
            'class' => 'hh-st-transcription-badge hh-st-transcription-badge--possible',
            'title' => I18N::translate('This media object is linked to a source, but its files are not suitable for transcription.'),
            'url' => $this->dashboardUrl($media),
        ];
    }

    public function badgeHtml(Media $media): string
    {
        $badge = $this->badge($media);

        return $badge === null
            ? ''
            : view(SourceTranscription::viewsNamespace() . '::components/source-badge', ['badge' => $badge]);
    }

    public function linkedTitleHtml(Media $media): string
    {
        return '<span class="hh-st-record-title-line">'
            . $this->externalMediaBadgeHtml($media, 'before-title')
            . '<a href="' . e($media->url()) . '">' . $media->fullName() . '</a>'
            . $this->badgeHtml($media)
            . $this->externalMediaBadgeHtml($media, 'after-title')
            . '</span>';
    }

    public function titleHtml(Media $media): string
    {
        return '<span class="hh-st-record-title-line">'
            . $this->externalMediaBadgeHtml($media, 'before-title')
            . '<span>' . $media->fullName() . '</span>'
            . $this->badgeHtml($media)
            . $this->externalMediaBadgeHtml($media, 'after-title')
            . '</span>';
    }

    private function externalMediaBadgeHtml(Media $media, string $position): string
    {
        $module_class = '\\Vendor\\Webtrees\\Module\\MediaBadge\\MediaBadgeModule';

        if (!class_exists($module_class)) {
            return '';
        }

        try {
            return view($module_class::MODULE_NAME . '::components/media-badge-list', [
                'record' => $media,
                'position' => $position,
            ]);
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @param array<string,string> $filters
     */
    private function dashboardUrl(Media $media, array $filters = []): string
    {
        return route('source-transcription-dashboard', ['tree' => $media->tree()->name()] + $filters);
    }

    private function isLinkedToSource(Media $media): bool
    {
        return DB::table('sources')
            ->join('link', static function ($join): void {
                $join->on('l_from', '=', 's_id');
                $join->on('l_file', '=', 's_file');
            })
            ->where('l_type', '=', 'OBJE')
            ->where('l_file', '=', $media->tree()->id())
            ->where('l_to', '=', trim($media->xref()))
            ->exists();
    }
}
