<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Http\RequestHandlers;

use Fisharebest\Webtrees\I18N;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function response;
use function view;

class CreateManualAction implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        $title = I18N::translate('Create manual transcription'),

        $content = view('hh_source_transcription::create-manual', [
            'title'         => $title,
            'tree'          => $tree,
        ]);

        return response(view('layouts/default', [
            'title'   => $title,
            'tree'    => $tree,
            'request' => $request,
            'content' => $content,
        ]));
    }
}
