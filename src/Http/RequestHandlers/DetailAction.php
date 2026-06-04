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
use Fisharebest\Webtrees\Services\UserService;
use Hartenthaler\Webtrees\Module\SourceTranscription\Application\Service\GetTranscriptionDetailService;
use Hartenthaler\Webtrees\Module\SourceTranscription\Application\Service\ViewDataService;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\Enum\TranscriptionStatus;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\ValueObject\CollaborationRole;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\ValueObject\ProviderKey;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\TranscriptionCollaboratorRepository;
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

        if (!Auth::isMember($tree)) return response('');

        $title = I18N::translate('Transcription');

        $transcription_id = (int) $request->getAttribute('transcription_id');
        $service = Registry::container()->get(GetTranscriptionDetailService::class);
        $view_data_service = Registry::container()->get(ViewDataService::class);
        $data = $service->get($tree, $transcription_id);
        $transcription = $data['transcription'];
        $user = $request->getAttribute('user');
        $collaborator_repo = Registry::container()->get(TranscriptionCollaboratorRepository::class);
        $active_roles = $collaborator_repo->activeRolesByUserId($transcription_id);
        $current_user_role = $collaborator_repo->roleForUser($transcription_id, $user->id());
        $collaboration_is_editable = !in_array($transcription->status, [
            TranscriptionStatus::FINAL->value,
            TranscriptionStatus::CANCELED->value,
        ], true);
        $can_edit_note = Auth::isEditor($tree) && ($current_user_role !== null || $transcription->provider_key !== ProviderKey::INTERNAL) && $collaboration_is_editable;
        $is_initiator = $current_user_role === CollaborationRole::INITIATOR;

        $eligible_collaborators = Registry::container()
            ->get(UserService::class)
            ->all()
            ->filter(static fn ($candidate): bool => $candidate->id() !== $user->id() && Auth::isEditor($tree, $candidate))
            ->all();

        $active_collaborators = [];
        $user_service = Registry::container()->get(UserService::class);
        foreach ($active_roles as $active_user_id => $role) {
            $active_user = $user_service->find($active_user_id);
            if ($active_user !== null) {
                $active_collaborators[$active_user_id] = [
                    'name' => $active_user->realName() !== '' ? $active_user->realName() : $active_user->userName(),
                    'role' => $role,
                ];
            }
        }

        $content = view('hh_source_transcription::detail', [
            'title'         => $title,
            'tree'          => $tree,
            'transcription' => $transcription,
            'revisions'     => $data['revisions'],
            'detail'        => $view_data_service->detailViewData($tree, $transcription, $data['revisions']),
            'note_text'     => $data['note_text'],
            'note_status'   => $data['note_status'],
            'source'        => $data['source'],
            'media_object'  => $data['media_object'],
            'media_files'   => $data['media_files'],
            'media_restriction' => $data['media_restriction'],
            'media_restriction_html' => $view_data_service->mediaRestrictionHtml($data['media_restriction'], $tree),
            'eligible_collaborators' => $eligible_collaborators,
            'active_collaborators' => $active_collaborators,
            'active_collaborator_ids' => array_keys($active_roles),
            'can_edit_note' => $can_edit_note,
            'can_make_revision_current' => $can_edit_note,
            'can_open_collaboration' => Auth::isEditor($tree) && $collaboration_is_editable,
            'collaboration_is_editable' => $collaboration_is_editable,
            'can_show_collaboration_reopen_hint' => Auth::isEditor($tree) && !$collaboration_is_editable,
            'can_submit_review' => Auth::isEditor($tree) && $current_user_role !== null && in_array($transcription->status, [
                TranscriptionStatus::IN_PROGRESS->value,
                TranscriptionStatus::REOPENED->value,
            ], true),
            'can_finalize' => $is_initiator && $transcription->status === TranscriptionStatus::READY_FOR_REVIEW->value,
            'can_reopen' => ($is_initiator || Auth::isAdmin($user)) && $transcription->status === TranscriptionStatus::FINAL->value,
            'is_internal_collaboration' => $transcription->provider_key === ProviderKey::INTERNAL,
            'can_finalize_manual' => $transcription->provider_key === ProviderKey::MANUAL &&
                Auth::isEditor($tree) &&
                $transcription->status !== TranscriptionStatus::FINAL->value &&
                $transcription->status !== TranscriptionStatus::CANCELED->value,
            'can_reopen_manual' => $transcription->provider_key === ProviderKey::MANUAL &&
                Auth::isEditor($tree) &&
                $transcription->status === TranscriptionStatus::FINAL->value,
        ]);

        return response(view('layouts/default', [
            'title'   => $title,
            'tree'    => $tree,
            'request' => $request,
            'content' => $content,
        ]));
    }
}
