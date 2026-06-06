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

namespace Hartenthaler\Webtrees\Module\SourceTranscription;

use Aura\Router\Exception\ImmutableProperty;
use Aura\Router\Exception\RouteAlreadyExists;
use Fisharebest\{Localization\Translation,
    Webtrees\DB,
    Webtrees\I18N,
    Webtrees\Auth,
    Webtrees\Module\AbstractModule,
    Webtrees\Module\ModuleConfigInterface,
    Webtrees\Module\ModuleConfigTrait,
    Webtrees\Module\ModuleCustomInterface,
    Webtrees\Module\ModuleCustomTrait,
    Webtrees\Module\ModuleGlobalInterface,
    Webtrees\Module\ModuleGlobalTrait,
    Webtrees\Media,
    Webtrees\MediaFile,
    Webtrees\Registry,
    Webtrees\Validator,
    Webtrees\View,
    Webtrees\Webtrees,
    Webtrees\Menu,
    Webtrees\Tree,
    Webtrees\Services\ModuleService,
    Webtrees\Services\TreeService,
    Webtrees\Services\UserService,
    Webtrees\Module\ModuleMenuInterface,
    Webtrees\Module\ModuleMenuTrait};
use Psr\{Container\ContainerExceptionInterface,
    Container\NotFoundExceptionInterface,
    Http\Message\ResponseInterface,
    Http\Message\ServerRequestInterface};
use Schwendinger\Webtrees\Module\LinkEnhancer\Services\MarkdownEditorActivationService;
use Hartenthaler\{Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\SettingsRepository,
    Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\ProviderCredentialRepository,
    Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Schema\SchemaManager,
    Webtrees\Module\SourceTranscription\Application\Provider\ProviderConnectionTester,
    Webtrees\Module\SourceTranscription\Application\Service\ViewDataService,
    Webtrees\Module\SourceTranscription\Domain\ValueObject\NoteStrategy,
    Webtrees\Module\SourceTranscription\Domain\ValueObject\ProviderKey,
    Webtrees\Module\SourceTranscription\Http\RequestHandlers\CollaborationStatusAction,
    Webtrees\Module\SourceTranscription\Http\RequestHandlers\AuthorizeDiscourseAction,
    Webtrees\Module\SourceTranscription\Http\RequestHandlers\CompareRevisionsAction,
    Webtrees\Module\SourceTranscription\Http\RequestHandlers\CreateManualAction,
    Webtrees\Module\SourceTranscription\Http\RequestHandlers\CreateTranskribusJobAction,
    Webtrees\Module\SourceTranscription\Http\RequestHandlers\DashboardAction,
    Webtrees\Module\SourceTranscription\Http\RequestHandlers\DetailAction,
    Webtrees\Module\SourceTranscription\Http\RequestHandlers\DiscourseAuthorizationCallbackAction,
    Webtrees\Module\SourceTranscription\Http\RequestHandlers\ManualStatusAction,
    Webtrees\Module\SourceTranscription\Http\RequestHandlers\MakeRevisionCurrentAction,
    Webtrees\Module\SourceTranscription\Http\RequestHandlers\MediaFilesForMediaAction,
    Webtrees\Module\SourceTranscription\Http\RequestHandlers\OpenCollaborationAction,
    Webtrees\Module\SourceTranscription\Http\RequestHandlers\SaveNoteAsRevisionAction,
    Webtrees\Module\SourceTranscription\Http\RequestHandlers\StoreManualAction,
    Webtrees\Module\SourceTranscription\Http\RequestHandlers\SourceForManualAction,
    Webtrees\Module\SourceTranscription\Http\RequestHandlers\UpdateCurrentNoteAction,
    Webtrees\Module\SourceTranscription\Http\RequestHandlers\UploadTranskribusImagesAction,
    Webtrees\Module\SourceTranscription\Http\RequestHandlers\VendorAssetAction,
    Webtrees\Module\SourceTranscription\Http\RequestHandlers\MediaForSourceAction,
    Webtrees\Module\SourceTranscription\Infrastructure\Webtrees\SharedNoteGateway,
    Webtrees\Module\SourceTranscription\Support\HashService,
    Webtrees\Module\SourceTranscription\Support\ModuleFlashMessages as FlashMessages,
    Webtrees\Module\SourceTranscription\Infrastructure\WhatsNew\WhatsNewInterface};

final class SourceTranscription extends AbstractModule implements
    ModuleCustomInterface, ModuleConfigInterface, ModuleMenuInterface, ModuleGlobalInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;
    use ModuleGlobalTrait;
    use ModuleMenuTrait;

    //Custom module version
	public const string CUSTOM_VERSION = '2.2.6.3';

    //Supported webtrees version
    public const string MINIMUM_WEBTREES_VERSION = '2.2.5';

    //Repository of the custom module
    public const string REPOSITORY = 'https://github.com/';

    // User at GitHub
    public const string CUSTOM_GITHUB_USER = 'hartenthaler';

    //Title of custom module
    public const string CUSTOM_TITLE = 'hh_source_transcription';

    //GitHub repository
    public const string GITHUB_REPO = self::CUSTOM_GITHUB_USER . "/" . self::CUSTOM_TITLE;

    // URL to the latest version of the custom module
    public const string CUSTOM_LAST = self::REPOSITORY . self::GITHUB_REPO . '/blob/main/latest-version.txt';

	//Author of the custom module
	public const string CUSTOM_AUTHOR = 'Hermann Hartenthaler';

    //Used database schema version
    public const int CURRENT_SCHEMA_VERSION = 6;

    //Default tag values for transcriptions (NOTE <tag_prefix><tag_value>)
    public const string DEFAULT_TAG_PREFIX = 'TAG: ';
    public const string DEFAULT_TAG_VALUE = 'Transcription';
    public const string DEFAULT_TAG = self::DEFAULT_TAG_PREFIX . self::DEFAULT_TAG_VALUE;


    //Options
    public const string DEFAULT_NOTE_STRATEGY = 'default_note_strategy';
    public const string DEFAULT_TAG_TEXT = 'default_tag_text';
    public const string TINY_MDE = 'tiny_mde';
    public const string TAGGING_SUPPORT = 'tagging_support';
    public const string SOURCE_BADGES = 'source_badges';
    public const string DASHBOARD_PAGE_SIZE = 'dashboard_page_size';
    public const string WHATS_NEW = 'whats_new';
    public const int DEFAULT_DASHBOARD_PAGE_SIZE = 20;
    public const int MINIMUM_DASHBOARD_PAGE_SIZE = 1;
    public const int MAXIMUM_DASHBOARD_PAGE_SIZE = 200;

    //Default provider credential endpoints
    public const string DEFAULT_TRANSKRIBUS_TOKEN_URL = 'https://account.readcoop.eu/auth/realms/readcoop/protocol/openid-connect/token';
    public const string DEFAULT_TRANSKRIBUS_CLIENT_ID = 'processing-api-client';
    public const string DEFAULT_TRANSKRIBUS_UPLOAD_URL = 'https://transkribus.eu/api/v2/uploads';

    /**
     * Privacy information for modules that describe external transcription providers.
     *
     * @return list<array{name:string,url:string,data:list<string>}>
     */
    public static function externalProviderPrivacyNotices(): array
    {
        return [
            [
                'name' => 'Transkribus',
                'url'  => 'https://www.transkribus.org/',
                'data' => [
                    I18N::translate('Selected source and media context.'),
                    I18N::translate('Image files selected by the user for external transcription processing.'),
                    I18N::translate('Transcription job metadata and returned transcription text.'),
                    I18N::translate('Provider credentials required for upload, status checks, and import operations.'),
                ],
            ],
            [
                'name' => 'Discourse',
                'url'  => 'https://discourse.genealogy.net/',
                'data' => [
                    I18N::translate('Selected source and media context.'),
                    I18N::translate('The transcription request text or reading-help post created by the user.'),
                    I18N::translate('Linked or uploaded media files, if the user explicitly submits them.'),
                    I18N::translate('Provider authorization data, such as a user-specific API key, after explicit user authorization.'),
                ],
            ],
        ];
    }

    //ROUTE
    private const string ROUTE_GET_NAME_DASHBOARD = 'source-transcription-dashboard';
    private const string ROUTE_PATH_DASHBOARD = '/tree/{tree}/source-transcriptions';
    private const string ROUTE_GET_NAME_CREATE_MANUAL = 'source-transcription-create-manual';
    private const string ROUTE_POST_NAME_STORE_MANUAL = 'source-transcription-store-manual';
    private const string ROUTE_PATH_CREATE_MANUAL = '/tree/{tree}/source-transcriptions/create-manual';
    private const string ROUTE_GET_NAME_CREATE_TRANSKRIBUS = 'source-transcription-create-transkribus';
    private const string ROUTE_POST_NAME_UPLOAD_TRANSKRIBUS = 'source-transcription-upload-transkribus';
    private const string ROUTE_PATH_CREATE_TRANSKRIBUS = '/tree/{tree}/source-transcriptions/create-transkribus';
    private const string ROUTE_PATH_UPLOAD_TRANSKRIBUS = '/tree/{tree}/source-transcriptions/transkribus/upload';
    private const string ROUTE_GET_NAME_AUTHORIZE_DISCOURSE = 'source-transcription-authorize-discourse';
    private const string ROUTE_PATH_AUTHORIZE_DISCOURSE = '/tree/{tree}/source-transcriptions/discourse/authorize';
    private const string ROUTE_GET_NAME_DISCOURSE_CALLBACK = 'source-transcription-discourse-callback';
    private const string ROUTE_PATH_DISCOURSE_CALLBACK = '/tree/{tree}/source-transcriptions/discourse/callback';
    private const string ROUTE_POST_NAME_UPDATE_NOTE = 'source-transcription-update-note';
    private const string ROUTE_PATH_UPDATE_NOTE = '/tree/{tree}/source-transcriptions/{transcription_id}/update-note';
    private const string ROUTE_POST_NAME_SAVE_NOTE_AS_REVISION = 'source-transcription-save-note-as-revision';
    private const string ROUTE_PATH_SAVE_NOTE_AS_REVISION = '/tree/{tree}/source-transcriptions/{transcription_id}/save-note-as-revision';
    private const string ROUTE_POST_NAME_MAKE_REVISION_CURRENT = 'source-transcription-make-revision-current';
    private const string ROUTE_PATH_MAKE_REVISION_CURRENT = '/tree/{tree}/source-transcriptions/{transcription_id}/revisions/{revision_id}/make-current';
    private const string ROUTE_POST_NAME_OPEN_COLLABORATION = 'source-transcription-open-collaboration';
    private const string ROUTE_PATH_OPEN_COLLABORATION = '/tree/{tree}/source-transcriptions/{transcription_id}/open-collaboration';
    private const string ROUTE_POST_NAME_COLLABORATION_STATUS = 'source-transcription-collaboration-status';
    private const string ROUTE_PATH_COLLABORATION_STATUS = '/tree/{tree}/source-transcriptions/{transcription_id}/collaboration-status';
    private const string ROUTE_POST_NAME_MANUAL_STATUS = 'source-transcription-manual-status';
    private const string ROUTE_PATH_MANUAL_STATUS = '/tree/{tree}/source-transcriptions/{transcription_id}/manual-status';
    //private const string ROUTE_GET_NAME_MEDIA_FOR_SOURCE = 'source-transcription-media-for-source';
    private const string ROUTE_PATH_MEDIA_FOR_SOURCE = '/tree/{tree}/source-transcriptions/media-for-source';
    private const string ROUTE_PATH_MEDIA_FILES_FOR_MEDIA = '/tree/{tree}/source-transcriptions/media-files-for-media';
    private const string ROUTE_PATH_SOURCE_FOR_MANUAL = '/tree/{tree}/source-transcriptions/source-for-manual';
    private const string ROUTE_GET_NAME_DETAIL = 'source-transcription-detail';
    private const string ROUTE_PATH_DETAIL = '/tree/{tree}/source-transcriptions/{transcription_id}';
    private const string ROUTE_GET_NAME_COMPARE_REVISIONS = 'source-transcription-compare-revisions';
    private const string ROUTE_PATH_COMPARE_REVISIONS = '/tree/{tree}/source-transcriptions/{transcription_id}/compare-revisions';
    private const string ROUTE_GET_NAME_VENDOR_ASSET = 'source-transcription-vendor-asset';
    private const string ROUTE_PATH_VENDOR_ASSET = '/source-transcription/vendor/{asset}';

    /**
     * SourceTranscription constructor.
     */
    public function __construct(
        //private readonly SettingsRepository $settingsRepository
    )
    {
    }

    /**
     * Initialization.
     *
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ImmutableProperty
     * @throws RouteAlreadyExists
     */
    public function boot(): void
    {
        $schema_manager = Registry::container()->get(SchemaManager::class);
        //check database schema version and update if necessary
        if (!$schema_manager->allTablesExist()) {
            $schema_manager->deleteAllTables();
            $schema_manager->updateSchema('\Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Schema',
                'schema_version', self::CURRENT_SCHEMA_VERSION);
            $this->setInitialDefaults();
        } else {
            $schema_manager->updateSchema('\Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Schema',
                'schema_version', self::CURRENT_SCHEMA_VERSION);
        }

        //Register a namespace for the views.
        View::registerNamespace('hh_source_transcription', strtr($this->resourcesFolder() . 'views' . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, '/'));
        View::registerCustomView('::lists/sources-table', self::viewsNamespace() . '::lists/sources-table');
        View::registerCustomView('::lists/media-table', self::viewsNamespace() . '::lists/media-table');
        View::registerCustomView('vesta_research_suggestions::lists/sources-table', self::viewsNamespace() . '::lists/sources-table-vesta-research-suggestions');
        View::registerCustomView('::media-page', self::viewsNamespace() . '::media-page');
        View::registerCustomView('::record-page', self::viewsNamespace() . '::record-page');
        View::registerCustomView('::record-page-links', self::viewsNamespace() . '::record-page-links');
        View::registerCustomView('::modules/media-list/page', self::viewsNamespace() . '::modules/media-list/page');
        View::registerCustomView('::modules/source-list/page', self::viewsNamespace() . '::modules/source-list/page');
        View::registerCustomView('::modules/sources_tab/tab', self::viewsNamespace() . '::modules/sources-tab');

        //Enable the TinyMDE editor of custom module linkenhancer
        $settingsRepository = Registry::container()->get(SettingsRepository::class);
        $this->toggleTinyMde($settingsRepository->get(self::TINY_MDE) == 'enabled');
        //if ($settingsRepository->get(self::TINY_MDE) == 'enabled') {
        //   $this->enableTinyMde();
        //}

        //Register routes
        $router = Registry::routeFactory()->routeMap();

        $router->get(
            self::ROUTE_GET_NAME_DASHBOARD,
            self::ROUTE_PATH_DASHBOARD,
            DashboardAction::class
        );

        $router->get(
            self::ROUTE_GET_NAME_CREATE_MANUAL,
            self::ROUTE_PATH_CREATE_MANUAL,
            CreateManualAction::class
        );

        $router->post(
            self::ROUTE_POST_NAME_STORE_MANUAL,
            self::ROUTE_PATH_CREATE_MANUAL,
            StoreManualAction::class
        );

        $router->get(
            self::ROUTE_GET_NAME_CREATE_TRANSKRIBUS,
            self::ROUTE_PATH_CREATE_TRANSKRIBUS,
            CreateTranskribusJobAction::class
        );

        $router->post(
            self::ROUTE_POST_NAME_UPLOAD_TRANSKRIBUS,
            self::ROUTE_PATH_UPLOAD_TRANSKRIBUS,
            UploadTranskribusImagesAction::class
        );

        $router->get(
            self::ROUTE_GET_NAME_AUTHORIZE_DISCOURSE,
            self::ROUTE_PATH_AUTHORIZE_DISCOURSE,
            AuthorizeDiscourseAction::class
        );

        $router->get(
            self::ROUTE_GET_NAME_DISCOURSE_CALLBACK,
            self::ROUTE_PATH_DISCOURSE_CALLBACK,
            DiscourseAuthorizationCallbackAction::class
        );

        $router->post(
            self::ROUTE_POST_NAME_UPDATE_NOTE,
            self::ROUTE_PATH_UPDATE_NOTE,
            UpdateCurrentNoteAction::class
        );

        $router->post(
            self::ROUTE_POST_NAME_SAVE_NOTE_AS_REVISION,
            self::ROUTE_PATH_SAVE_NOTE_AS_REVISION,
            SaveNoteAsRevisionAction::class
        );

        $router->post(
            self::ROUTE_POST_NAME_MAKE_REVISION_CURRENT,
            self::ROUTE_PATH_MAKE_REVISION_CURRENT,
            MakeRevisionCurrentAction::class
        );

        $router->post(
            self::ROUTE_POST_NAME_OPEN_COLLABORATION,
            self::ROUTE_PATH_OPEN_COLLABORATION,
            OpenCollaborationAction::class
        );

        $router->post(
            self::ROUTE_POST_NAME_COLLABORATION_STATUS,
            self::ROUTE_PATH_COLLABORATION_STATUS,
            CollaborationStatusAction::class
        );

        $router->post(
            self::ROUTE_POST_NAME_MANUAL_STATUS,
            self::ROUTE_PATH_MANUAL_STATUS,
            ManualStatusAction::class
        );

        $router->get(
            SourceForManualAction::class,
            self::ROUTE_PATH_SOURCE_FOR_MANUAL,
            SourceForManualAction::class
        );

        $router->get(
            MediaForSourceAction::class,
            self::ROUTE_PATH_MEDIA_FOR_SOURCE,
            MediaForSourceAction::class
        );

        $router->get(
            MediaFilesForMediaAction::class,
            self::ROUTE_PATH_MEDIA_FILES_FOR_MEDIA,
            MediaFilesForMediaAction::class
        );

        $router->get(
            self::ROUTE_GET_NAME_COMPARE_REVISIONS,
            self::ROUTE_PATH_COMPARE_REVISIONS,
            CompareRevisionsAction::class
        );

        $router->get(
            self::ROUTE_GET_NAME_VENDOR_ASSET,
            self::ROUTE_PATH_VENDOR_ASSET,
            VendorAssetAction::class
        )->tokens([
            'asset' => '.+',
        ]);

        //Generic Route at the end!
        $router->get(
            self::ROUTE_GET_NAME_DETAIL,
            self::ROUTE_PATH_DETAIL,
            DetailAction::class
        );

        $this->flashWhatsNew();
    }

    /**
     * Display "What's new" messages as flash messages.
     *
     * @return void
     */
    private function flashWhatsNew(): void
    {
        if (!Auth::check()) {
            return;
        }

        $namespace = 'Hartenthaler\\Webtrees\\Module\\SourceTranscription\\Infrastructure\\WhatsNew';
        $pref = self::WHATS_NEW;
        $current_version = (int) $this->getPreference($pref, '0');

        $target_version = $current_version;
        while (class_exists($namespace . '\\WhatsNew' . $target_version)) {
            $target_version++;
        }

        while ($current_version < $target_version) {
            $class = $namespace . '\\WhatsNew' . $current_version;

            if (class_exists($class)) {
                /** @var WhatsNewInterface $whatsNew */
                $whatsNew = new $class();
                FlashMessages::addMessage(I18N::translate("What's new?") . " " . $whatsNew->getMessage());
            }
            $current_version++;

            $this->setPreference($pref, (string) $current_version);
        }
    }

    /**
     * set the initial defaults for the settings
     *
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function setInitialDefaults(): void
    {
        $settingsRepository = Registry::container()->get(SettingsRepository::class);
        $settingsRepository->setSchemaVersion(0);
        $settingsRepository->set(self::DEFAULT_NOTE_STRATEGY, 'update_if_unchanged');
        $settingsRepository->set(self::DEFAULT_TAG_TEXT, self::DEFAULT_TAG);
        $settingsRepository->set(self::TINY_MDE, 'enabled');
        $settingsRepository->set(self::TAGGING_SUPPORT, 'enabled');
        $settingsRepository->set(self::SOURCE_BADGES, 'enabled');
        $settingsRepository->set(self::DASHBOARD_PAGE_SIZE, (string) self::DEFAULT_DASHBOARD_PAGE_SIZE);
    }


    /**
     * toggles registration for using markdown editor on note fields provided by linkenhancer custom module
     * registration is persisted by linkenhancer custom module so it's not necessary to force registering again each time
     *
     * @param bool $enable
     * @param bool $force
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function toggleTinyMde(bool $enable, bool $force = false): void
    {
        $class = "Schwendinger\\Webtrees\\Module\\LinkEnhancer\\Services\\MarkdownEditorActivationService";
        if (Registry::container()->has($class)) {
            /** @var MarkdownEditorActivationService $mde_service */
            $mde_service = Registry::container()->get($class);
            $existingRule = $mde_service->getCustomRule(self::CUSTOM_TITLE);
            if (
                $force ||
                $enable && !$existingRule ||
                !$enable && $existingRule
            ) {
                $mde_service->setCustomRule(
                    self::CUSTOM_TITLE,  // module name as key
                    $enable ? ["source-transcription-detail", "source-transcription-create-manual"] : [], // handler: usually the short class name / last part of the route name - see js console with enabled debug info
                    $enable ? ["textarea[id$='_text']"] : [] // filter: querySelector filter expressions; here: textarea id ends with "_text"
                );
            }
        }
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\AbstractModule::resourcesFolder()
     */
    public function resourcesFolder(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR;
    }

    /**
     * Get the namespace for the views
     *
     * @return string
     */
    public static function viewsNamespace(): string
    {
        return self::activeModuleName();
    }

    public static function activeModuleName(): string
    {
        return basename(dirname(__DIR__, 1));
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\AbstractModule::title()
     */
    public function title(): string
    {
        return /* I18N: Name of a module. */ I18N::translate("Source Transcription");
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\AbstractModule::description()
     */
    public function description(): string
    {
        return I18N::translate('Manage source transcriptions with manual and provider-based workflows.');
    }

    public function headContent(): string
    {
        try {
            $settings = Registry::container()->get(SettingsRepository::class);

            if ($settings->get(self::SOURCE_BADGES, 'enabled') !== 'enabled') {
                return '';
            }
        } catch (\Throwable) {
            return '';
        }

        return '<link rel="stylesheet" href="' . e($this->assetUrl('css/source-badges.css')) . '">';
    }

    /**
     * Minimum webtrees version
     *
     * @return string
     */
    public function minimumVersion(): string
    {
        return self::MINIMUM_WEBTREES_VERSION;
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleAuthorName()
     */
    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleVersion()
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * A URL that will provide the latest version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return self::CUSTOM_LAST;
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleSupportUrl()
     */
    public function customModuleSupportUrl(): string
    {
        return self::REPOSITORY . self::GITHUB_REPO;
    }

    /**
     * define the default menu order (can be overwritten by admin)
     *
     * @return int
     */
    public function defaultMenuOrder(): int
    {
        return 80;
    }

    /**
     * @param Tree $tree
     *
     * @return bool
     */
    public function canAccess(Tree $tree): bool
    {
        return Auth::accessLevel($tree) >= Auth::ACCESS_MEMBER;
    }

    /**
     * set the main menu entry
     *
     * @param Tree $tree
     * @return Menu|null
     */
    public function getMenu(Tree $tree): ?Menu
    {
        if (!Auth::isMember($tree)) {
            return null;
        }

        return new Menu(
            $this->menuIcon() . I18N::translate('Transcriptions'),
            e(route('source-transcription-dashboard', [
                'tree' => $tree->name(),
            ])),
            $this->name() . ' menu-source-transcription'
        );
    }

    /**
     * Inline SVG icon for the main menu entry.
     *
     * @return string
     */
    private function menuIcon(): string
    {
        $icon_file = $this->resourcesFolder() . 'images' . DIRECTORY_SEPARATOR . 'source-transcription.svg';

        if (!is_file($icon_file)) {
            return '';
        }

        $icon = file_get_contents($icon_file);

        if ($icon === false) {
            return '';
        }

        return '<span aria-hidden="true" style="display:block;width:6.4em;height:3.2em;margin:0 auto .25rem;line-height:1">' . $icon . '</span>';
    }

    /**
     * {@inheritDoc}
     *
     * @param string $language
     *
     * @return array
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customTranslations()
     */
    public function customTranslations(string $language): array
    {
        $lang_dir   = $this->resourcesFolder() . 'lang' . DIRECTORY_SEPARATOR;
        $file       = $lang_dir . $language . '.mo';
        if (file_exists($file)) {
            return (new Translation($file))->asArray();
        } else {
            return [];
        }
    }

    /**
     * Whether the module runs with the webtrees version of this installation
     *
     * @return bool
     */
    public static function runsWithInstalledWebtreesVersion(): bool
    {
        if (version_compare(Webtrees::VERSION, self::MINIMUM_WEBTREES_VERSION, '>=')) {
            return true;
        }

        return false;
    }

    /**
     * View module settings in the control panel
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts' . DIRECTORY_SEPARATOR . 'administration';

        $settings = Registry::container()->get(SettingsRepository::class);
        $selected_user_id = Validator::queryParams($request)->integer('provider_user_id', 0);
        $selected_provider_key = Validator::queryParams($request)->isInArray(array_keys($this->providerOptions()))->string('provider_key', ProviderKey::TRANSKRIBUS);

        $tag_text = $settings->get('default_tag_text', self::DEFAULT_TAG_PREFIX . self::DEFAULT_TAG_VALUE);
        $tag_value = $this->tagValueFromTagText($tag_text);

        return $this->adminSettingsResponse($settings, $tag_value, null, null, $selected_user_id, $selected_provider_key);
    }

    private function adminSettingsResponse(
        SettingsRepository $settings,
        string $tag_value,
        ?array $consistency_check = null,
        ?array $provider_test_result = null,
        int $selected_user_id = 0,
        string $selected_provider_key = ProviderKey::TRANSKRIBUS
    ): ResponseInterface {
        $view_data_service = Registry::container()->get(ViewDataService::class);
        $provider_credentials = $this->providerCredentialViewData($selected_user_id, $selected_provider_key);
        $diagnostics = $this->adminDiagnostics($settings);
        $diagnostics['trees'] = array_map(
            static fn (array $tree_status): array => $tree_status + [
                'markdown_badge_html' => $view_data_service->statusBadgeHtml((bool) $tree_status['markdown_enabled']),
            ],
            $diagnostics['trees']
        );

        return $this->viewResponse(self::CUSTOM_TITLE . '::' . 'admin-settings', [
            'title'                         => $this->title(),
            'module'                        => $this,
            'runs_with_webtrees_version'    => SourceTranscription::runsWithInstalledWebtreesVersion(),
            'diagnostics'                   => $diagnostics,
            'consistency_check'             => $consistency_check,
            'default_note_strategy'         => $settings->get(
                'default_note_strategy',
                NoteStrategy::default()
            ),
            'note_strategies'               => NoteStrategy::labels(),
            'tiny_mde'                      => $settings->get('tiny_mde', ''),
            'tagging_support'               => $settings->get('tagging_support', 'enabled'),
            'source_badges'                 => $settings->get(self::SOURCE_BADGES, 'enabled'),
            'dashboard_page_size'           => $this->dashboardPageSize($settings),
            'tag_prefix'                    => self::DEFAULT_TAG_PREFIX,
            'tag_value'                     => $tag_value,
            'provider_credentials'          => $provider_credentials,
            'provider_status_badge_html'    => $view_data_service->providerStatusBadgeHtml($provider_credentials['transkribus']['last_test_status'] ?? null),
            'provider_test_alert_class'     => $provider_test_result === null ? '' : ((bool) $provider_test_result['success'] ? 'alert-success' : 'alert-danger'),
            'provider_test_result'          => $provider_test_result,
            'status_badges'                 => $this->adminStatusBadges($diagnostics, $view_data_service),
        ]);
    }

    /**
     * Save module settings after returning from the control panel
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $save = Validator::parsedBody($request)->string('save', '');
        $run_consistency_check = Validator::parsedBody($request)->string('run_consistency_check', '');
        $provider_credentials_action = Validator::parsedBody($request)->string('provider_credentials_action', '');

        if ($run_consistency_check === '1') {
            $this->layout = 'layouts/administration';

            $settings = Registry::container()->get(SettingsRepository::class);
            $tag_text = $settings->get('default_tag_text', self::DEFAULT_TAG_PREFIX . self::DEFAULT_TAG_VALUE);
            $tag_value = $this->tagValueFromTagText($tag_text);

            return $this->adminSettingsResponse($settings, $tag_value, $this->runConsistencyCheck($settings));
        }

        if ($provider_credentials_action !== '') {
            $this->layout = 'layouts/administration';

            $settings = Registry::container()->get(SettingsRepository::class);
            $tag_text = $settings->get('default_tag_text', self::DEFAULT_TAG_PREFIX . self::DEFAULT_TAG_VALUE);
            $tag_value = $this->tagValueFromTagText($tag_text);
            $params = (array) $request->getParsedBody();
            $user_id = (int) ($params['provider_user_id'] ?? 0);
            $provider_key = (string) ($params['provider_key'] ?? ProviderKey::DISCOURSE);

            if (!array_key_exists($provider_key, $this->providerOptions())) {
                $provider_key = ProviderKey::TRANSKRIBUS;
            }

            $credential_repository = Registry::container()->get(ProviderCredentialRepository::class);

            if ($provider_credentials_action === 'delete') {
                $credential_repository->delete($user_id, $provider_key);
                FlashMessages::addMessage(I18N::translate('The provider credentials have been deleted.'), 'success');

                return $this->adminSettingsResponse($settings, $tag_value, null, null, $user_id, $provider_key);
            }

            try {
                $credential_repository->save(
                    $user_id,
                    $provider_key,
                    $this->providerSettingsFromParams($provider_key, $params),
                    $this->providerSecretFromParams($provider_key, $params)
                );
            } catch (\Throwable $ex) {
                FlashMessages::addMessage($ex->getMessage(), 'danger');

                return $this->adminSettingsResponse($settings, $tag_value, null, [
                    'success' => false,
                    'message' => $ex->getMessage(),
                ], $user_id, $provider_key);
            }

            if ($provider_credentials_action === 'test') {
                $credential = $credential_repository->find($user_id, $provider_key);
                $result = $credential === null
                    ? ['success' => false, 'message' => I18N::translate('The provider credentials could not be loaded.')]
                    : Registry::container()->get(ProviderConnectionTester::class)->test($provider_key, $credential);

                $credential_repository->recordTestResult($user_id, $provider_key, (bool) $result['success'], (string) $result['message']);
                FlashMessages::addMessage((string) $result['message'], (bool) $result['success'] ? 'success' : 'danger');

                return $this->adminSettingsResponse($settings, $tag_value, null, $result, $user_id, $provider_key);
            }

            FlashMessages::addMessage(I18N::translate('The provider credentials have been saved.'), 'success');

            return $this->adminSettingsResponse($settings, $tag_value, null, null, $user_id, $provider_key);
        }

        //Save the received settings to the user preferences
        if ($save === '1') {
            $body = Validator::parsedBody($request);

            $tag_value = $this->normalizeTagValue(trim($body->string('tag_value', self::DEFAULT_TAG_VALUE)));
            $note_strategy = $body->string('default_note_strategy', NoteStrategy::default());

            //Set default NOTE strategy if not set or incorrect set
            if (!NoteStrategy::isValid($note_strategy)) {
                $note_strategy = NoteStrategy::default();
            }

            $settings = Registry::container()->get(SettingsRepository::class);
            $settings->set('default_tag_text', self::DEFAULT_TAG_PREFIX . $tag_value);
            $settings->set('default_note_strategy', $note_strategy);
            $settings->set('tiny_mde', $body->string('tiny_mde', ''));
            $settings->set('tagging_support', $body->string('tagging_support', ''));
            $settings->set(self::SOURCE_BADGES, $body->string(self::SOURCE_BADGES, ''));
            $settings->set(self::DASHBOARD_PAGE_SIZE, (string) $this->normalizeDashboardPageSize($body->integer(self::DASHBOARD_PAGE_SIZE, self::DEFAULT_DASHBOARD_PAGE_SIZE)));

            //Finally, show a success message
            FlashMessages::addMessage(
                I18N::translate('The preferences for the module “%s” have been updated.', $this->title()),
                'success'
            );
        }
        return redirect($this->getConfigLink());
    }

    private function dashboardPageSize(SettingsRepository $settings): int
    {
        return $this->normalizeDashboardPageSize((int) $settings->get(self::DASHBOARD_PAGE_SIZE, (string) self::DEFAULT_DASHBOARD_PAGE_SIZE));
    }

    private function normalizeDashboardPageSize(int $page_size): int
    {
        return min(
            self::MAXIMUM_DASHBOARD_PAGE_SIZE,
            max(self::MINIMUM_DASHBOARD_PAGE_SIZE, $page_size)
        );
    }

    /**
     * @return array<string,string>
     */
    private function providerOptions(): array
    {
        return [
            ProviderKey::TRANSKRIBUS => 'Transkribus',
        ];
    }

    /**
     * @return array{
     *     users:array<int,string>,
     *     providers:array<string,string>,
     *     selected_user_id:int,
     *     selected_provider_key:string,
     *     discourse:array<string,mixed>,
     *     transkribus:array<string,mixed>
     * }
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function providerCredentialViewData(int $selected_user_id, string $selected_provider_key): array
    {
        $users = [];
        foreach (Registry::container()->get(UserService::class)->all() as $user) {
            $users[$user->id()] = $user->realName() . ' (' . $user->userName() . ')';
        }

        if ($selected_user_id <= 0 || !array_key_exists($selected_user_id, $users)) {
            $selected_user_id = (int) array_key_first($users);
        }

        if (!array_key_exists($selected_provider_key, $this->providerOptions())) {
            $selected_provider_key = ProviderKey::TRANSKRIBUS;
        }

        $credential_repository = Registry::container()->get(ProviderCredentialRepository::class);
        $discourse = $credential_repository->find($selected_user_id, ProviderKey::DISCOURSE);
        $transkribus = $credential_repository->find($selected_user_id, ProviderKey::TRANSKRIBUS);

        return [
            'users'                 => $users,
            'providers'             => $this->providerOptions(),
            'selected_user_id'      => $selected_user_id,
            'selected_provider_key' => $selected_provider_key,
            'discourse'             => [
                'settings'          => $discourse['settings'] ?? ['base_url' => '', 'api_username' => ''],
                'has_secret'        => $discourse['has_secret'] ?? false,
                'last_test_status'  => $discourse['last_test_status'] ?? null,
                'last_test_message' => $discourse['last_test_message'] ?? null,
                'last_test_at'      => $discourse['last_test_at'] ?? null,
            ],
            'transkribus'           => [
                'settings'          => $transkribus['settings'] ?? [
                    'token_url' => self::DEFAULT_TRANSKRIBUS_TOKEN_URL,
                    'client_id' => self::DEFAULT_TRANSKRIBUS_CLIENT_ID,
                    'upload_url' => self::DEFAULT_TRANSKRIBUS_UPLOAD_URL,
                    'username'  => '',
                ],
                'has_secret'        => $transkribus['has_secret'] ?? false,
                'last_test_status'  => $transkribus['last_test_status'] ?? null,
                'last_test_message' => $transkribus['last_test_message'] ?? null,
                'last_test_at'      => $transkribus['last_test_at'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $params
     *
     * @return array<string,string>
     */
    private function providerSettingsFromParams(string $provider_key, array $params): array
    {
        return match ($provider_key) {
            ProviderKey::TRANSKRIBUS => [
                'token_url' => trim((string) ($params['transkribus_token_url'] ?? self::DEFAULT_TRANSKRIBUS_TOKEN_URL)),
                'client_id' => trim((string) ($params['transkribus_client_id'] ?? self::DEFAULT_TRANSKRIBUS_CLIENT_ID)),
                'upload_url' => trim((string) ($params['transkribus_upload_url'] ?? self::DEFAULT_TRANSKRIBUS_UPLOAD_URL)),
                'username'  => trim((string) ($params['transkribus_username'] ?? '')),
            ],
            default => [],
        };
    }

    /**
     * @param array<string,mixed> $params
     */
    private function providerSecretFromParams(string $provider_key, array $params): ?string
    {
        $secret = match ($provider_key) {
            ProviderKey::TRANSKRIBUS => trim((string) ($params['transkribus_password'] ?? '')),
            default => '',
        };

        return $secret === '' ? null : $secret;
    }

    /**
     * @return array{errors:array<int,array{tree:string, transcription:string, message:string}>, warnings:array<int,array{tree:string, transcription:string, message:string}>}
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function runConsistencyCheck(SettingsRepository $settings): array
    {
        $schema_manager = new SchemaManager();

        $result = [
            'errors'   => [],
            'warnings' => [],
        ];

        if (!$schema_manager->allTablesExist()) {
            $this->addConsistencyIssue(
                $result,
                'errors',
                '',
                '',
                I18N::translate('The module database tables are incomplete.')
            );

            return $result;
        }

        $trees = [];
        foreach (Registry::container()->get(TreeService::class)->all() as $tree) {
            $trees[$tree->id()] = $tree;
        }

        $tagging_enabled = $settings->get(self::TAGGING_SUPPORT, 'enabled') === 'enabled';
        $shared_note_gateway = Registry::container()->get(SharedNoteGateway::class);
        $hash_service = Registry::container()->get(HashService::class);

        $transcriptions = DB::table(SchemaManager::TABLE_TRANSCRIPTIONS)
            ->where('is_active', '=', true)
            ->orderBy('tree_id')
            ->orderBy('id')
            ->get();

        foreach ($transcriptions as $transcription) {
            $tree = $trees[(int) $transcription->tree_id] ?? null;
            $tree_label = $tree === null ? I18N::translate('Unknown tree') . ' #' . (int) $transcription->tree_id : $tree->title();
            $transcription_label = '#' . (int) $transcription->id . ' ' . (string) $transcription->title;

            if ($tree === null) {
                $this->addConsistencyIssue($result, 'errors', $tree_label, $transcription_label, I18N::translate('The referenced family tree no longer exists.'));
                continue;
            }

            $source = Registry::sourceFactory()->make((string) $transcription->source_xref, $tree);
            $media = $transcription->media_xref === null ? null : Registry::mediaFactory()->make((string) $transcription->media_xref, $tree);
            $target = $transcription->media_xref === null ? $source : $media;

            if ($source === null) {
                $this->addConsistencyIssue($result, 'errors', $tree_label, $transcription_label, I18N::translate('The referenced source does not exist: %s', (string) $transcription->source_xref));
            }

            if ($transcription->media_xref !== null && $media === null) {
                $this->addConsistencyIssue($result, 'errors', $tree_label, $transcription_label, I18N::translate('The referenced media object does not exist: %s', (string) $transcription->media_xref));
            }

            if ($media !== null) {
                $this->checkMediaFileMetadata($result, $tree_label, $transcription_label, $media);
            }

            if ($source !== null && $transcription->media_xref !== null && !$this->gedcomLinksToNoteOrMedia($source->gedcom(), 'OBJE', (string) $transcription->media_xref)) {
                $this->addConsistencyIssue($result, 'warnings', $tree_label, $transcription_label, I18N::translate('The referenced media object is no longer linked to the source.'));
            }

            $current_note_text = null;
            if ($transcription->current_note_xref === null || trim((string) $transcription->current_note_xref) === '') {
                $this->addConsistencyIssue($result, 'errors', $tree_label, $transcription_label, I18N::translate('The transcription has no current NOTE.'));
            } else {
                $current_note = Registry::noteFactory()->make((string) $transcription->current_note_xref, $tree);

                if ($current_note === null) {
                    $this->addConsistencyIssue($result, 'errors', $tree_label, $transcription_label, I18N::translate('The current NOTE does not exist: %s', (string) $transcription->current_note_xref));
                } elseif ($target === null || !$this->gedcomLinksToNoteOrMedia($target->gedcom(), 'NOTE', (string) $transcription->current_note_xref)) {
                    $this->addConsistencyIssue($result, 'errors', $tree_label, $transcription_label, I18N::translate('The current NOTE is not linked to the expected source or media object: %s', (string) $transcription->current_note_xref));
                } else {
                    $current_note_text = $shared_note_gateway->readSharedNote($tree, (string) $transcription->current_note_xref);
                }
            }

            if ($current_note_text !== null) {
                $current_link = DB::table(SchemaManager::TABLE_NOTE_LINKS)
                    ->where('transcription_id', '=', (int) $transcription->id)
                    ->where('is_current', '=', true)
                    ->orderByDesc('id')
                    ->first();

                if (
                    $current_link !== null &&
                    (string) $current_link->note_xref === (string) $transcription->current_note_xref &&
                    $current_link->note_hash_at_link_time !== null &&
                    $hash_service->sha256($current_note_text) !== (string) $current_link->note_hash_at_link_time
                ) {
                    $this->addConsistencyIssue($result, 'warnings', $tree_label, $transcription_label, I18N::translate('The current NOTE has changed since it was last linked to a revision.'));
                }
            }

            if ($tagging_enabled) {
                if ($transcription->tag_note_xref === null || trim((string) $transcription->tag_note_xref) === '') {
                    $this->addConsistencyIssue($result, 'warnings', $tree_label, $transcription_label, I18N::translate('Tagging is enabled, but the transcription has no tag NOTE.'));
                } else {
                    $tag_note = Registry::noteFactory()->make((string) $transcription->tag_note_xref, $tree);

                    if ($tag_note === null) {
                        $this->addConsistencyIssue($result, 'warnings', $tree_label, $transcription_label, I18N::translate('The tag NOTE does not exist: %s', (string) $transcription->tag_note_xref));
                    } elseif ($target === null || !$this->gedcomLinksToNoteOrMedia($target->gedcom(), 'NOTE', (string) $transcription->tag_note_xref)) {
                        $this->addConsistencyIssue($result, 'warnings', $tree_label, $transcription_label, I18N::translate('The tag NOTE is not linked to the expected source or media object: %s', (string) $transcription->tag_note_xref));
                    }
                }
            }
        }

        $revision_rows = DB::table(SchemaManager::TABLE_REVISIONS . ' AS r')
            ->leftJoin(SchemaManager::TABLE_TRANSCRIPTIONS . ' AS t', 't.id', '=', 'r.transcription_id')
            ->select(['r.*', 't.tree_id', 't.title'])
            ->orderBy('r.transcription_id')
            ->orderBy('r.revision_no')
            ->get();

        $current_revision_counts = [];

        foreach ($revision_rows as $revision) {
            $transcription_id = (int) $revision->transcription_id;
            $current_revision_counts[$transcription_id] ??= 0;

            if ((bool) $revision->is_current_revision) {
                $current_revision_counts[$transcription_id]++;
            }

            if ($revision->tree_id === null) {
                $this->addConsistencyIssue($result, 'errors', '', '#' . $transcription_id, I18N::translate('Revision %s belongs to a transcription that no longer exists.', (string) $revision->revision_no));
                continue;
            }

            if ($revision->generated_note_xref !== null && trim((string) $revision->generated_note_xref) !== '') {
                $tree = $trees[(int) $revision->tree_id] ?? null;
                if ($tree === null) {
                    continue;
                }

                $note = Registry::noteFactory()->make((string) $revision->generated_note_xref, $tree);
                if ($note === null) {
                    $this->addConsistencyIssue(
                        $result,
                        'errors',
                        $tree->title(),
                        '#' . $transcription_id . ' ' . (string) $revision->title,
                        I18N::translate('Revision %s references a NOTE that no longer exists: %s', (string) $revision->revision_no, (string) $revision->generated_note_xref)
                    );
                }
            }
        }

        foreach ($transcriptions as $transcription) {
            $transcription_id = (int) $transcription->id;
            $tree = $trees[(int) $transcription->tree_id] ?? null;
            $tree_label = $tree === null ? I18N::translate('Unknown tree') . ' #' . (int) $transcription->tree_id : $tree->title();
            $transcription_label = '#' . $transcription_id . ' ' . (string) $transcription->title;
            $revision_count = DB::table(SchemaManager::TABLE_REVISIONS)->where('transcription_id', '=', $transcription_id)->count();
            $current_count = $current_revision_counts[$transcription_id] ?? 0;

            if ($revision_count === 0) {
                $this->addConsistencyIssue($result, 'errors', $tree_label, $transcription_label, I18N::translate('The transcription has no revisions.'));
            } elseif ($current_count !== 1) {
                $this->addConsistencyIssue($result, 'errors', $tree_label, $transcription_label, I18N::translate('The transcription has %s current revisions; expected exactly one.', (string) $current_count));
            }

            $current_revision_note_xref = DB::table(SchemaManager::TABLE_REVISIONS)
                ->where('transcription_id', '=', $transcription_id)
                ->where('is_current_revision', '=', true)
                ->value('generated_note_xref');

            if (
                $current_revision_note_xref !== null &&
                $transcription->current_note_xref !== null &&
                (string) $current_revision_note_xref !== (string) $transcription->current_note_xref
            ) {
                $this->addConsistencyIssue($result, 'warnings', $tree_label, $transcription_label, I18N::translate('The current revision references a different NOTE than the transcription current NOTE.'));
            }
        }

        $user_service = Registry::container()->get(UserService::class);
        $collaborators = DB::table(SchemaManager::TABLE_COLLABORATORS . ' AS c')
            ->leftJoin(SchemaManager::TABLE_TRANSCRIPTIONS . ' AS t', 't.id', '=', 'c.transcription_id')
            ->select(['c.*', 't.tree_id', 't.title'])
            ->where('c.is_active', '=', true)
            ->orderBy('c.transcription_id')
            ->get();

        foreach ($collaborators as $collaborator) {
            if ($user_service->find((int) $collaborator->user_id) !== null) {
                continue;
            }

            $tree = $collaborator->tree_id === null ? null : ($trees[(int) $collaborator->tree_id] ?? null);
            $this->addConsistencyIssue(
                $result,
                'warnings',
                $tree?->title() ?? '',
                '#' . (int) $collaborator->transcription_id . ' ' . (string) ($collaborator->title ?? ''),
                I18N::translate('An active collaboration entry references a user that no longer exists: %s', (string) $collaborator->user_id)
            );
        }

        return $result;
    }

    private function checkMediaFileMetadata(array &$result, string $tree_label, string $transcription_label, Media $media): void
    {
        $media_files = $media->mediaFiles()->values();
        $media_file_facts = $media->facts(['FILE'])->values();

        foreach ($media_files as $index => $media_file) {
            /** @var MediaFile $media_file */
            $filename = $media_file->filename();
            $file_label = $filename !== '' ? $filename : $media_file->factId();
            $extension = $this->mediaFileExtension($filename);
            $file_gedcom = (string) ($media_file_facts->get($index)?->gedcom() ?? '');
            $form = $this->mediaFileForm($media, $media_file, $file_gedcom);
            $type = $this->mediaFileType($media, $media_file, $file_gedcom);
            $mime_type = strtolower(trim($media_file->mimeType()));
            $is_external = $media_file->isExternal();

            if ($extension === '') {
                $this->addConsistencyIssue(
                    $result,
                    'warnings',
                    $tree_label,
                    $transcription_label,
                    I18N::translate('Media file %s has no filename extension; FORM is %s and MIME type is %s.', $file_label, $form !== '' ? $form : I18N::translate('None'), $mime_type !== '' ? $mime_type : I18N::translate('None'))
                );
            }

            if (!$is_external && $form === '') {
                $this->addConsistencyIssue(
                    $result,
                    'warnings',
                    $tree_label,
                    $transcription_label,
                    I18N::translate('Media file %s has no GEDCOM FORM value; filename extension is %s and MIME type is %s.', $file_label, $extension !== '' ? $extension : I18N::translate('None'), $mime_type !== '' ? $mime_type : I18N::translate('None'))
                );
            }

            if ($type === '') {
                $this->addConsistencyIssue(
                    $result,
                    'warnings',
                    $tree_label,
                    $transcription_label,
                    I18N::translate('Media file %s has no GEDCOM TYPE value; filename extension is %s and MIME type is %s.', $file_label, $extension !== '' ? $extension : I18N::translate('None'), $mime_type !== '' ? $mime_type : I18N::translate('None'))
                );
            }

            if (!$is_external && $extension !== '' && $form !== '' && $this->canonicalMediaFormat($extension) !== $this->canonicalMediaFormat($form)) {
                $this->addConsistencyIssue(
                    $result,
                    'warnings',
                    $tree_label,
                    $transcription_label,
                    I18N::translate('Media file %s has inconsistent filename extension and GEDCOM FORM: extension %s, FORM %s.', $file_label, $extension, $form)
                );
            }

            if ($mime_type === '' || $mime_type === 'application/octet-stream') {
                $this->addConsistencyIssue(
                    $result,
                    'warnings',
                    $tree_label,
                    $transcription_label,
                    I18N::translate('Media file %s has no specific MIME type: %s. Filename extension is %s and FORM is %s.', $file_label, $mime_type !== '' ? $mime_type : I18N::translate('None'), $extension !== '' ? $extension : I18N::translate('None'), $form !== '' ? $form : I18N::translate('None'))
                );
            }

            if ($extension !== '' && $mime_type !== '' && $mime_type !== 'application/octet-stream' && !$this->mediaMimeMatchesFormat($mime_type, $extension)) {
                $this->addConsistencyIssue(
                    $result,
                    'warnings',
                    $tree_label,
                    $transcription_label,
                    I18N::translate('Media file %s has inconsistent filename extension and MIME type: extension %s, MIME type %s.', $file_label, $extension, $mime_type)
                );
            }

            if (!$is_external && $form !== '' && $mime_type !== '' && $mime_type !== 'application/octet-stream' && !$this->mediaMimeMatchesFormat($mime_type, $form)) {
                $this->addConsistencyIssue(
                    $result,
                    'warnings',
                    $tree_label,
                    $transcription_label,
                    I18N::translate('Media file %s has inconsistent GEDCOM FORM and MIME type: FORM %s, MIME type %s.', $file_label, $form, $mime_type)
                );
            }

            if ($type !== '' && !$this->mediaTypeMatchesMetadata($type, $extension, $mime_type)) {
                $this->addConsistencyIssue(
                    $result,
                    'warnings',
                    $tree_label,
                    $transcription_label,
                    I18N::translate('Media file %s has inconsistent GEDCOM TYPE and technical metadata: TYPE %s, extension %s, MIME type %s.', $file_label, $type, $extension !== '' ? $extension : I18N::translate('None'), $mime_type !== '' ? $mime_type : I18N::translate('None'))
                );
            }
        }
    }

    private function mediaFileExtension(string $filename): string
    {
        $path = parse_url($filename, PHP_URL_PATH);

        return strtolower(pathinfo((string) ($path ?: $filename), PATHINFO_EXTENSION));
    }

    private function mediaFileForm(Media $media, MediaFile $media_file, string $file_gedcom): string
    {
        $form = $this->mediaFileFormFromGedcom($file_gedcom);

        if ($form !== '') {
            return $form;
        }

        $form = strtolower(trim($media_file->format()));

        if ($form !== '' && $this->expectedMediaMetadataForType($form) === null) {
            return $form;
        }

        $filename = $media_file->filename();

        foreach ($this->mediaFileMetadataFromGedcom($media) as $file_metadata) {
            if ($file_metadata['form'] === '' || !$this->mediaFileNamesReferToSameFile($filename, $file_metadata['filename'])) {
                continue;
            }

            return strtolower(trim($file_metadata['form']));
        }

        return '';
    }

    private function mediaFileFormFromGedcom(string $gedcom): string
    {
        return $this->mediaFileTagFromGedcom($gedcom, 'FORM');
    }

    private function mediaFileType(Media $media, MediaFile $media_file, string $file_gedcom): string
    {
        $type = $this->mediaFileTypeFromGedcom($file_gedcom);

        if ($type !== '') {
            return $type;
        }

        $type = strtolower(trim($media_file->type()));

        if ($type !== '') {
            return $type;
        }

        $format = strtolower(trim($media_file->format()));

        if ($format !== '' && $this->expectedMediaMetadataForType($format) !== null) {
            return $format;
        }

        $filename = $media_file->filename();

        foreach ($this->mediaFileMetadataFromGedcom($media) as $file_metadata) {
            if ($file_metadata['type'] === '' || !$this->mediaFileNamesReferToSameFile($filename, $file_metadata['filename'])) {
                continue;
            }

            return strtolower(trim($file_metadata['type']));
        }

        return '';
    }

    private function mediaFileTypeFromGedcom(string $gedcom): string
    {
        return $this->mediaFileTagFromGedcom($gedcom, 'TYPE');
    }

    private function mediaFileTagFromGedcom(string $gedcom, string $tag): string
    {
        if (preg_match('/^\d+[ \t]+' . preg_quote($tag, '/') . '[ \t]+([^\r\n]+)$/m', $gedcom, $match) !== 1) {
            return '';
        }

        return strtolower(trim($match[1]));
    }

    /**
     * @return array<int,array{filename:string,form:string,type:string}>
     */
    private function mediaFileMetadataFromGedcom(Media $media): array
    {
        $metadata = [];
        $current = null;
        $lines = preg_split('/\R/', $media->gedcom());

        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            if (!preg_match('/^(\d+)[ \t]+([A-Z0-9_]+)(?:[ \t]+([^\r\n]*))?$/', $line, $match)) {
                continue;
            }

            $level = (int) $match[1];
            $tag = $match[2];
            $value = trim($match[3] ?? '');

            if ($current !== null && $level <= $current['level']) {
                $metadata[] = [
                    'filename' => $current['filename'],
                    'form'     => $current['form'],
                    'type'     => $current['type'],
                ];
                $current = null;
            }

            if ($tag === 'FILE') {
                $current = [
                    'level'    => $level,
                    'filename' => $value,
                    'form'     => '',
                    'type'     => '',
                ];

                continue;
            }

            if ($current !== null && $level === $current['level'] + 1 && $tag === 'FORM') {
                $current['form'] = $value;
            }

            if ($current !== null && $tag === 'TYPE') {
                $current['type'] = $value;
            }
        }

        if ($current !== null) {
            $metadata[] = [
                'filename' => $current['filename'],
                'form'     => $current['form'],
                'type'     => $current['type'],
            ];
        }

        return $metadata;
    }

    private function mediaFileNamesReferToSameFile(string $left, string $right): bool
    {
        $left_key = $this->mediaFilePathKey($left);
        $right_key = $this->mediaFilePathKey($right);

        if ($left_key === '' || $right_key === '') {
            return false;
        }

        return $left_key === $right_key ||
            str_ends_with($left_key, '/' . $right_key) ||
            str_ends_with($right_key, '/' . $left_key) ||
            basename($left_key) === basename($right_key);
    }

    private function mediaFilePathKey(string $filename): string
    {
        $path = parse_url($filename, PHP_URL_PATH);
        $path = (string) ($path ?: $filename);
        $path = rawurldecode(str_replace('\\', '/', $path));

        return strtolower(trim($path, '/'));
    }

    private function canonicalMediaFormat(string $format): string
    {
        return match (strtolower(trim($format))) {
            'jpeg' => 'jpg',
            'tiff' => 'tif',
            'text' => 'txt',
            default => strtolower(trim($format)),
        };
    }

    private function mediaMimeMatchesFormat(string $mime_type, string $format): bool
    {
        $expected_mime_types = $this->expectedMediaMimeTypes($format);

        return $expected_mime_types === [] || in_array($mime_type, $expected_mime_types, true);
    }

    private function mediaTypeMatchesMetadata(string $type, string $extension, string $mime_type): bool
    {
        $rules = $this->expectedMediaMetadataForType($type);

        if ($rules === null) {
            return false;
        }

        $extension_matches = $extension === '' || $rules['extensions'] === [] || in_array($this->canonicalMediaFormat($extension), $rules['extensions'], true);
        $mime_matches = $mime_type === '' || $mime_type === 'application/octet-stream' || $rules['mime_types'] === [] || in_array($mime_type, $rules['mime_types'], true);

        return $extension_matches && $mime_matches;
    }

    /**
     * @return array{extensions:array<int,string>,mime_types:array<int,string>}|null
     */
    private function expectedMediaMetadataForType(string $type): ?array
    {
        $image_extensions = ['avif', 'gif', 'heic', 'heif', 'jpg', 'png', 'tif', 'webp'];
        $image_mime_types = ['image/avif', 'image/gif', 'image/heic', 'image/heif', 'image/jpeg', 'image/png', 'image/tiff', 'image/webp'];
        $document_extensions = [...$image_extensions, 'doc', 'docx', 'pdf', 'rtf', 'txt'];
        $document_mime_types = [...$image_mime_types, 'application/msword', 'application/pdf', 'application/rtf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/x-rtf', 'text/plain', 'text/rtf'];

        return match (strtoupper(trim($type))) {
            'AUDIO' => [
                'extensions' => ['aac', 'flac', 'm4a', 'mp3', 'oga', 'ogg', 'wav'],
                'mime_types' => ['application/ogg', 'audio/aac', 'audio/flac', 'audio/mp4', 'audio/mp3', 'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/wave', 'audio/x-flac', 'audio/x-m4a', 'audio/x-wav'],
            ],
            'VIDEO' => [
                'extensions' => ['m4v', 'mov', 'mp4', 'ogv', 'webm'],
                'mime_types' => ['application/ogg', 'video/mp4', 'video/ogg', 'video/quicktime', 'video/webm'],
            ],
            'PHOTO', 'TOMBSTONE', 'PAINTING', 'COAT', 'MAP' => [
                'extensions' => $image_extensions,
                'mime_types' => $image_mime_types,
            ],
            'BOOK', 'CARD', 'CERTIFICATE', 'DOCUMENT', 'ELECTRONIC', 'FICHE', 'FILM', 'MAGAZINE', 'MANUSCRIPT', 'NEWSPAPER', 'OTHER' => [
                'extensions' => $document_extensions,
                'mime_types' => $document_mime_types,
            ],
            default => null,
        };
    }

    /**
     * @return array<int,string>
     */
    private function expectedMediaMimeTypes(string $format): array
    {
        return match ($this->canonicalMediaFormat($format)) {
            'aac' => ['audio/aac'],
            'avif' => ['image/avif'],
            'flac' => ['audio/flac', 'audio/x-flac'],
            'gif' => ['image/gif'],
            'heic' => ['image/heic'],
            'heif' => ['image/heif'],
            'jpg' => ['image/jpeg'],
            'm4a' => ['audio/mp4', 'audio/x-m4a'],
            'm4v' => ['video/mp4'],
            'mov' => ['video/quicktime'],
            'mp3' => ['audio/mpeg', 'audio/mp3'],
            'mp4' => ['video/mp4'],
            'oga', 'ogg' => ['audio/ogg', 'application/ogg'],
            'ogv' => ['video/ogg', 'application/ogg'],
            'pdf' => ['application/pdf'],
            'png' => ['image/png'],
            'rtf' => ['application/rtf', 'application/x-rtf', 'text/rtf'],
            'tif' => ['image/tiff'],
            'txt' => ['text/plain'],
            'wav' => ['audio/wav', 'audio/x-wav', 'audio/wave'],
            'webm' => ['video/webm'],
            'webp' => ['image/webp'],
            default => [],
        };
    }

    private function addConsistencyIssue(array &$result, string $severity, string $tree, string $transcription, string $message): void
    {
        $result[$severity][] = [
            'tree'          => $tree,
            'transcription' => $transcription,
            'message'       => $message,
        ];
    }

    private function gedcomLinksToNoteOrMedia(string $gedcom, string $tag, string $xref): bool
    {
        return preg_match('/^\d+\s+' . preg_quote($tag, '/') . '\s+@' . preg_quote($xref, '/') . '@/mu', $gedcom) === 1;
    }

    /**
     * @param array<string,mixed> $diagnostics
     * @return array<string,string>
     */
    private function adminStatusBadges(array $diagnostics, ViewDataService $view_data_service): array
    {
        return [
            'schema_tables_exist' => $view_data_service->statusBadgeHtml((bool) $diagnostics['schema']['tables_exist']),
            'linkenhancer_installed' => $view_data_service->statusBadgeHtml((bool) $diagnostics['linkenhancer']['installed']),
            'linkenhancer_enabled' => $view_data_service->statusBadgeHtml((bool) $diagnostics['linkenhancer']['enabled']),
            'tiny_mde_setting_enabled' => $view_data_service->statusBadgeHtml((bool) $diagnostics['tiny_mde']['setting_enabled']),
            'tiny_mde_service_available' => $view_data_service->statusBadgeHtml((bool) $diagnostics['tiny_mde']['service_available']),
            'tiny_mde_custom_rule_registered' => $view_data_service->statusBadgeHtml((bool) $diagnostics['tiny_mde']['custom_rule_registered']),
        ];
    }

    /**
     * @return array{
     *     schema: array{current:int, target:int, tables_exist:bool},
     *     tiny_mde: array{setting_enabled:bool, service_available:bool, custom_rule_registered:bool},
     *     linkenhancer: array{installed:bool, enabled:bool, name:string},
     *     transcription_trees: array<int,array{title:string, name:string, transcriptions:int, revisions:int}>,
     *     trees: array<int,array{title:string, name:string, format_text:string, markdown_enabled:bool}>
     * }
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function adminDiagnostics(SettingsRepository $settings): array
    {
        $schema_manager = new SchemaManager();
        $tables_exist = $schema_manager->allTablesExist();
        try {
            $current_schema_version = $settings->getSchemaVersion();
        } catch (\Throwable) {
            $current_schema_version = 0;
        }
        $module_service = Registry::container()->get(ModuleService::class);
        $linkenhancer = $module_service->findByName('_linkenhancer_', true);
        $service_available = Registry::container()->has(MarkdownEditorActivationService::class);
        $custom_rule_registered = false;

        if ($service_available) {
            /** @var MarkdownEditorActivationService $mde_service */
            $mde_service = Registry::container()->get(MarkdownEditorActivationService::class);
            $custom_rule_registered = $mde_service->getCustomRule(self::CUSTOM_TITLE) !== [];
        }

        return [
            'schema' => [
                'current'      => $current_schema_version,
                'target'       => self::CURRENT_SCHEMA_VERSION,
                'tables_exist' => $tables_exist,
            ],
            'tiny_mde' => [
                'setting_enabled'        => $settings->get(self::TINY_MDE, '') === 'enabled',
                'service_available'      => $service_available,
                'custom_rule_registered' => $custom_rule_registered,
            ],
            'linkenhancer' => [
                'installed' => $linkenhancer !== null,
                'enabled'   => $linkenhancer !== null && $linkenhancer->isEnabled(),
                'name'      => $linkenhancer?->title() ?? 'linkenhancer',
            ],
            'transcription_trees' => $this->transcriptionStatusByTree($tables_exist),
            'trees' => $this->markdownStatusByTree(),
        ];
    }

    /**
     * @return array<int,array{title:string, name:string, transcriptions:int, revisions:int}>
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function transcriptionStatusByTree(bool $tables_exist): array
    {
        if (!$tables_exist) {
            return [];
        }

        $trees = Registry::container()->get(TreeService::class)->all();
        $status_by_tree = [];

        foreach ($trees as $tree) {
            $status_by_tree[$tree->id()] = [
                'title'             => $tree->title(),
                'name'              => $tree->name(),
                'transcriptions'    => 0,
                'revisions'         => 0,
            ];
        }

        $transcriptions_alias = DB::prefix('t');
        $revisions_alias = DB::prefix('r');

        $rows = DB::table(SchemaManager::TABLE_TRANSCRIPTIONS . ' AS t')
            ->leftJoin(SchemaManager::TABLE_REVISIONS . ' AS r', static function ($join): void {
                $join->on('r.transcription_id', '=', 't.id');
            })
            ->select([
                't.tree_id',
                DB::raw('COUNT(DISTINCT `' . $transcriptions_alias . '`.`id`) AS transcriptions'),
                DB::raw('COUNT(`' . $revisions_alias . '`.`id`) AS revisions'),
            ])
            ->where('t.is_active', '=', true)
            ->groupBy('t.tree_id')
            ->get();

        foreach ($rows as $row) {
            $tree_id = (int) $row->tree_id;

            if (!isset($status_by_tree[$tree_id])) {
                continue;
            }

            $status_by_tree[$tree_id]['transcriptions'] = (int) $row->transcriptions;
            $status_by_tree[$tree_id]['revisions'] = (int) $row->revisions;
        }

        return array_values(array_filter($status_by_tree, static fn (array $status): bool => $status['transcriptions'] > 0));
    }

    /**
     * @return array<int,array{title:string, name:string, format_text:string, markdown_enabled:bool}>
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function markdownStatusByTree(): array
    {
        $trees = Registry::container()->get(TreeService::class)->all();
        $status_by_tree = [];

        foreach ($trees as $tree) {
            $format_text = $tree->getPreference('FORMAT_TEXT');

            $status_by_tree[] = [
                'title'             => $tree->title(),
                'name'              => $tree->name(),
                'format_text'       => $format_text,
                'markdown_enabled'  => $format_text === 'markdown',
            ];
        }

        return $status_by_tree;
    }

    /**
     * define tag value based on parameter or set to default if not defined already
     *
     * @param string $tag_value
     * @return string
     */
    private function normalizeTagValue(string $tag_value): string
    {
        $tag_value = trim($tag_value);

        if (str_starts_with(strtoupper($tag_value), 'TAG:')) {
            $tag_value = trim(substr($tag_value, 4));
        }

        return $tag_value !== '' ? $tag_value : self::DEFAULT_TAG_VALUE;
    }

    /**
     * define tag value (like "TAG: Transcription") from tag text (like "Transcription" or "TAG: Transcription"))
     *
     * @param string|null $tag_text
     * @return string
     */
    private function tagValueFromTagText(?string $tag_text): string
    {
        return $this->normalizeTagValue((string) $tag_text);
    }
}
