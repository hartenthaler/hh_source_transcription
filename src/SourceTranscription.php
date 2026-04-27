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

use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Webtrees;
use Hartenthaler\Webtrees\Module\SourceTranscription\Application\Dto\CreateTranscriptionCommand;
use Hartenthaler\Webtrees\Module\SourceTranscription\Application\Service\CreateTranscriptionService;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\ValueObject\ProviderKey;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\RevisionRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\SettingsRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\TranscriptionRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\SchemaManager;
use Hartenthaler\Webtrees\Module\SourceTranscription\Domain\ValueObject\NoteStrategy;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SourceTranscription extends AbstractModule implements
    ModuleInterface, ModuleCustomInterface, ModuleConfigInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;

    //Custom module version
	public const string CUSTOM_VERSION = '2.2.5.0';

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
    public const int CURRENT_SCHEMA_VERSION = 1;

    //Default tag values for transcriptions (NOTE <tag_prefix><tag_value>)
    //tbd should the tag prefix be configurable?
    public const string DEFAULT_TAG_PREFIX = 'TAG: ';
    public const string DEFAULT_TAG_VALUE = 'Transcription';

    /**
     * SourceTranscription constructor.
     */
    public function __construct()
    {
        
    }

    /**
     * Initialization.
     *
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function boot(): void
    {              
        // Register a namespace for the views.
		View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

        Registry::container()->get(SchemaManager::class)->ensureSchema();

        //tbd
        // - Provider registrieren
        // - Routen laden

        // TEMP TEST
        Registry::container()->get(SchemaManager::class)->ensureSchema();

        if (true) {
            $service = Registry::container()->get(CreateTranscriptionService::class);
            $tree_id = 50;
            $transcription_id = $service->createManual(new CreateTranscriptionCommand(
                tree_id: $tree_id,
                source_xref: 'X6753',
                media_xref: null,
                title: 'Manual smoke test',
                provider_key: ProviderKey::MANUAL,
                user_id: 1,
                initial_text: 'Dies ist eine erste manuelle Test-Transkription.',
                comment: 'Smoke test 27.4.'
            ));

            error_log('Created manual transcription id=' . $transcription_id);
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

    /**
     * Get the active module name, e.g. the name of the currently running module
     *
     * @return string
     */
    public static function activeModuleName(): string
    {
        return '_' . basename(dirname(__DIR__, 1)) . '_';
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
        return /* I18N: Name of a module/tab on the individual page. */ I18N::translate("Source Transcription");
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
        $this->layout = 'layouts/administration';

        $settings = Registry::container()->get(SettingsRepository::class);

        $tag_text = $settings->get('default_tag_text', self::DEFAULT_TAG_PREFIX . self::DEFAULT_TAG_VALUE);
        $tag_value = $this->tagValueFromTagText($tag_text);

        return $this->viewResponse($this->name() . '::' . 'admin-settings', [
            'title'                         => $this->title(),
            'runs_with_webtrees_version'    => SourceTranscription::runsWithInstalledWebtreesVersion(),
            'tag_prefix'                    => self::DEFAULT_TAG_PREFIX,
            'tag_value'                     => $tag_value,
            'default_note_strategy'         => $settings->get(
                'default_note_strategy',
                NoteStrategy::default()
            ),
            'note_strategies'               => NoteStrategy::labels(),
            'module'                        => $this,
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
        //Save the received settings to the user preferences
        if ($save === '1') {
            // tbd: use Validator::parsedBody($request)->string|boolean|...('xxx', ''|true|...);
            $params = (array)$request->getParsedBody();

            $tag_value = $this->normalizeTagValue(trim((string)($params['tag_value'] ?? self::DEFAULT_TAG_VALUE)));
            $note_strategy = (string)($params['default_note_strategy']);

            //Set default NOTE strategy if not set or incorrect set
            if (!NoteStrategy::isValid($note_strategy)) {
                $note_strategy = NoteStrategy::default();
            }

            $settings = Registry::container()->get(SettingsRepository::class);
            $settings->set('default_tag_text', self::DEFAULT_TAG_PREFIX . $tag_value);
            $settings->set('default_note_strategy', $note_strategy);

            //Finally, show a success message
            FlashMessages::addMessage(
                I18N::translate('The preferences for the module “%s” have been updated.', $this->title()),
                'success'
            );
        }
        return redirect($this->getConfigLink());
    }

    private function normalizeTagValue(string $tag_value): string
    {
        $tag_value = trim($tag_value);

        if (str_starts_with(strtoupper($tag_value), 'TAG:')) {
            $tag_value = trim(substr($tag_value, 4));
        }

        return $tag_value !== '' ? $tag_value : self::DEFAULT_TAG_VALUE;
    }

    private function tagValueFromTagText(?string $tag_text): string
    {
        return $this->normalizeTagValue((string) $tag_text);
    }
}
