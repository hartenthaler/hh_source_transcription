<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Http\RequestHandlers;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\TranscriptionRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function response;
use function view;

final class DashboardAction
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        $title = I18N::translate('Transcriptions');

        $repo = Registry::container()->get(TranscriptionRepository::class);

        $content = view('hh_source_transcription::dashboard', [
            'title' => $title,
            'tree' => $tree,
            'transcriptions' => $repo->allActive(),
        ]);

        return response(view('layouts/default', [
            'title' => $title,
            'tree'    => $tree,
            'request' => $request,
            'content' => $content,
        ]));
    }
}