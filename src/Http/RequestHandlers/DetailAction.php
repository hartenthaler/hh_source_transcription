<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Http\RequestHandlers;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Hartenthaler\Webtrees\Module\SourceTranscription\Application\Service\GetTranscriptionDetailService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function response;
use function view;

class DetailAction implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        $title = I18N::translate('Transcription');

        $transcription_id = (int) $request->getAttribute('transcription_id');
        $service = Registry::container()->get(GetTranscriptionDetailService::class);
        $data = $service->get($transcription_id);

        $content = view('hh_source_transcription::detail', [
            'title'         => $title,
            'tree'          => $tree,
            'transcription' => $data['transcription'],
            'revisions'     => $data['revisions'],
            'note_text'     => $data['note_text'],
        ]);

        return response(view('layouts/default', [
            'title'   => $title,
            'tree'    => $tree,
            'request' => $request,
            'content' => $content,
        ]));
    }
}
