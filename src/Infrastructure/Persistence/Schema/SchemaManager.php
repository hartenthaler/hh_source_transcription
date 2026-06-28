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

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Schema;

use Fisharebest\Webtrees\Registry;
use Hartenthaler\Webtrees\Module\SourceTranscription\SourceTranscription;
use Illuminate\Database\Capsule\Manager as DB;
use Fisharebest\Webtrees\Schema\MigrationInterface;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\SettingsRepository;
use PDOException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

final class SchemaManager
{
    //Define the database table names
    public const string TABLE_METADATA = 'transcription_metadata';
    public const string TABLE_TRANSCRIPTIONS = 'transcriptions';
    public const string TABLE_REVISIONS = 'transcription_revisions';
    public const string TABLE_NOTE_LINKS = 'transcription_note_links';
    public const string TABLE_COLLABORATORS = 'transcription_collaborators';
    public const string TABLE_PROVIDER_CREDENTIALS = 'transcription_provider_credentials';
    public const string TABLE_TRANSKRIBUS_JOBS = 'transkribus_jobs';
    public const string TABLE_TRANSKRIBUS_JOB_FILES = 'transkribus_job_files';

    /*
    public function __construct(

    ) {
    }

    public function ensureSchema(): void
    {
        if (!$this->allTablesExist()) {
            $this->installVersion1();
            return;
        }

        $current = $this->settingsRepository->getSchemaVersion();
        $target  = SourceTranscription::CURRENT_SCHEMA_VERSION;

        if ($current > $target) {
            throw new RuntimeException(
                'Source Transcriptions database schema is newer than this module version.'
            );
        }

        if ($current < $target) {
            $this->migrate($current, $target);
        }
    }

    public function currentVersion(): int
    {
        if (!DB::schema()->hasTable(self::TABLE_METADATA)) {
            return 0;
        }

        return $this->settingsRepository->getSchemaVersion();
    }
*/
    /**
     * check if all database tables exist
     *
     * @return bool
     */
    public function allTablesExist(): bool
    {
        return DB::schema()->hasTable(self::TABLE_METADATA) &&
            DB::schema()->hasTable(self::TABLE_TRANSCRIPTIONS) &&
            DB::schema()->hasTable(self::TABLE_REVISIONS) &&
            DB::schema()->hasTable(self::TABLE_NOTE_LINKS);
    }

    /**
     * delete all database tables for source transcriptions
     *
     * @return void
     */
    public function deleteAllTables(): void
    {
        DB::schema()->dropIfExists(self::TABLE_NOTE_LINKS);
        DB::schema()->dropIfExists(self::TABLE_COLLABORATORS);
        DB::schema()->dropIfExists(self::TABLE_TRANSKRIBUS_JOB_FILES);
        DB::schema()->dropIfExists(self::TABLE_TRANSKRIBUS_JOBS);
        DB::schema()->dropIfExists(self::TABLE_PROVIDER_CREDENTIALS);
        DB::schema()->dropIfExists(self::TABLE_REVISIONS);
        DB::schema()->dropIfExists(self::TABLE_TRANSCRIPTIONS);
        DB::schema()->dropIfExists(self::TABLE_METADATA);
    }

    /**
     * update database schema
     * the same as Database::getSchema, but use module settings instead of site settings
     *
     * taken from modules_v4/vesta_common/VestaModuleTrait.php
     *
     * @param $namespace
     * @param $schema_name
     * @param $target_version
     * @return bool
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function updateSchema($namespace, $schema_name, $target_version): bool
    {
        $settingsRepository = Registry::container()->get(SettingsRepository::class);
        try {
            $current_version = $settingsRepository->getSchemaVersion();
        } catch (PDOException $ex) {
            // During initial installation, the site_preference table won’t exist.
            $current_version = 0;
        }

        $updates_applied = false;

        // Update the schema, one version at a time.
        while ($current_version < $target_version) {

            $class = $namespace . '\\Migration' . $current_version;
            /** @var MigrationInterface $migration */
            $migration = new $class();
            $migration->upgrade();
            $current_version++;

            //when a module is first installed, we may not be able to setPreference at this point
            ////(if this is called e.g. from SetName())
            //because of foreign key constraints:
            //the module may not have been inserted in the 'module' table at this point!
            //cf. ModuleService.all()
            //
            //not that critical, we can just set the preference next time
            //
            //let's just check this directly (using ModuleService at this point may lead to looping, if we're indirectly called from there)
            if (DB::table('module')->where('module_name', '=', Registry::container()->get(SourceTranscription::class)->name())->exists()) {
                $settingsRepository->set($schema_name, (string) $current_version);
            }
            $updates_applied = true;
        }
        if ($updates_applied && $settingsRepository->getSchemaVersion() < $target_version) {
            $settingsRepository->setSchemaVersion($target_version);
        }

        return $updates_applied;
    }
}
