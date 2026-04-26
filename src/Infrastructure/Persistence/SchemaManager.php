<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence;

use Fisharebest\Webtrees\DB;
use Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository\SettingsRepository;
use Hartenthaler\Webtrees\Module\SourceTranscription\SourceTranscription;
use RuntimeException;

final class SchemaManager
{
    private const TABLE_METADATA = 'transcription_metadata';
    private const TABLE_TRANSCRIPTIONS = 'transcriptions';
    private const TABLE_REVISIONS = 'transcription_revisions';
    private const TABLE_NOTE_LINKS = 'transcription_note_links';

    public function __construct(
        private readonly SettingsRepository $settingsRepository
    ) {
    }

    public function ensureSchema(): void
    {
        if (!$this->metadataTableExists()) {
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
        if (!$this->metadataTableExists()) {
            return 0;
        }

        return $this->settingsRepository->getSchemaVersion();
    }

    private function metadataTableExists(): bool
    {
        return DB::schema()->hasTable(self::TABLE_METADATA);
    }

    private function installVersion1(): void
    {
        DB::schema()->create(self::TABLE_METADATA, static function ($table): void {
            $table->string('setting_name', 64)->primary();
            $table->text('setting_value');
        });

        DB::schema()->create(self::TABLE_TRANSCRIPTIONS, static function ($table): void {
            $table->increments('id');
            $table->integer('tree_id');
            $table->string('source_xref', 20);
            $table->string('media_xref', 20)->nullable();
            $table->string('title', 255);
            $table->string('transcription_type', 32);
            $table->string('provider_key', 32);
            $table->string('status', 32);
            $table->string('tag_note_xref', 20)->nullable();
            $table->string('current_note_xref', 20)->nullable();
            $table->integer('created_by_user_id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->index(['tree_id', 'source_xref'], 'idx_transcriptions_source');
            $table->index(['tree_id', 'media_xref'], 'idx_transcriptions_media');
        });

        DB::schema()->create(self::TABLE_REVISIONS, static function ($table): void {
            $table->increments('id');
            $table->integer('transcription_id');
            $table->integer('revision_no');
            $table->string('provider_key', 32);
            $table->string('origin_type', 32);
            $table->text('origin_reference')->nullable();
            $table->string('content_format', 32);
            $table->longText('content_text');
            $table->char('content_hash', 64);
            $table->integer('created_by_user_id');
            $table->timestamp('created_at')->useCurrent();
            $table->text('import_comment')->nullable();
            $table->string('generated_note_xref', 20)->nullable();
            $table->boolean('is_current_revision')->default(false);

            $table->index('transcription_id', 'idx_revisions_transcription');
            $table->index('content_hash', 'idx_revisions_hash');
        });

        DB::schema()->create(self::TABLE_NOTE_LINKS, static function ($table): void {
            $table->increments('id');
            $table->integer('transcription_id');
            $table->integer('revision_id')->nullable();
            $table->string('note_xref', 20);
            $table->string('link_type', 32);
            $table->timestamp('created_at')->useCurrent();
            $table->integer('created_by_user_id');
            $table->boolean('is_current')->default(false);
            $table->char('note_hash_at_link_time', 64)->nullable();

            $table->index('transcription_id', 'idx_note_links_transcription');
            $table->index('note_xref', 'idx_note_links_note');
        });

        $this->settingsRepository->setSchemaVersion(1);
        $this->settingsRepository->set('default_note_strategy', 'update_if_unchanged');
        $this->settingsRepository->set('default_tag_text', 'TAG: Transcription');
    }

    private function migrate(int $fromVersion, int $toVersion): void
    {
        $version = $fromVersion;

        while ($version < $toVersion) {
            match ($version) {
                1 => $this->migrate1To2(),
                default => throw new RuntimeException('No migration defined from schema version ' . $version),
            };

            $version++;
            $this->settingsRepository->setSchemaVersion($version);
        }
    }

    private function migrate1To2(): void
    {
        // Reserved for the first future schema migration.
    }
}