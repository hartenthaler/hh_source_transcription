<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Application\Service;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Source;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\SettingsRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\TranscriptionRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Webtrees\MediaObjectGateway;
use Hartenthaler\Webtrees\Module\SourceTranscription\SourceTranscription;
use Throwable;

use function e;
use function preg_match;
use function preg_split;
use function route;
use function trim;
use function view;

final class SourceBadgeService
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
    public function badge(Source $source): ?array
    {
        if (!$this->enabled()) {
            return null;
        }

        if ($this->transcriptionRepository->existsActiveForSource($source->tree(), $source->xref())) {
            return [
                'label' => 'T',
                'class' => 'hh-st-source-badge hh-st-source-badge--transcribed',
                'title' => I18N::translate('This source already has a transcription.'),
                'url' => $this->dashboardUrl($source, ['source_xref' => $source->xref()]),
            ];
        }

        if ($this->hasSuitableLinkedMedia($source)) {
            return [
                'label' => 'T',
                'class' => 'hh-st-source-badge hh-st-source-badge--ready',
                'title' => I18N::translate('This source has media suitable for transcription, but no transcription yet.'),
                'url' => $this->dashboardUrl($source),
            ];
        }

        return [
            'label' => 'T',
            'class' => 'hh-st-source-badge hh-st-source-badge--possible',
            'title' => I18N::translate('This source has no suitable linked media yet, but can still be transcribed.'),
            'url' => $this->dashboardUrl($source),
        ];
    }

    public function badgeHtml(Source $source): string
    {
        $badge = $this->badge($source);

        return $badge === null
            ? ''
            : view(SourceTranscription::viewsNamespace() . '::components/source-badge', ['badge' => $badge]);
    }

    public function linkedTitleHtml(Source $source): string
    {
        return '<span class="hh-st-source-title-line"><a href="' . e($source->url()) . '">' . $source->fullName() . '</a>' . $this->badgeHtml($source) . '</span>';
    }

    /**
     * @param array<string,string> $filters
     */
    private function dashboardUrl(Source $source, array $filters = []): string
    {
        return route('source-transcription-dashboard', ['tree' => $source->tree()->name()] + $filters);
    }

    private function hasSuitableLinkedMedia(Source $source): bool
    {
        $access_level = Auth::accessLevel($source->tree());

        foreach (preg_split('/\R/u', $source->privatizeGedcom($access_level)) ?: [] as $line) {
            if (preg_match('/^\d+\s+OBJE\s+@([^@]+)@/u', $line, $match) !== 1) {
                continue;
            }

            $media = Registry::mediaFactory()->make(trim($match[1]), $source->tree());

            if ($media !== null && $media->canShow($access_level) && $this->mediaObjectGateway->hasTranscriptionSuitableFile($media)) {
                return true;
            }
        }

        return false;
    }
}
