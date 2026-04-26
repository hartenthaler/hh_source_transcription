<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\SourceTranscription\Infrastructure\Persistence\Repository;

use Fisharebest\Webtrees\DB;

final class SettingsRepository
{
    private const TABLE = 'transcription_metadata';

    public function get(string $key, ?string $default = null): ?string
    {
        $value = DB::table(self::TABLE)
            ->where('setting_name', '=', $key)
            ->value('setting_value');

        return $value === null ? $default : (string) $value;
    }

    public function set(string $key, string $value): void
    {
        DB::table(self::TABLE)->updateOrInsert(
            ['setting_name' => $key],
            ['setting_value' => $value]
        );
    }

    public function getSchemaVersion(): int
    {
        return (int) $this->get('schema_version', '0');
    }

    public function setSchemaVersion(int $version): void
    {
        $this->set('schema_version', (string) $version);
    }
}
