<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version20260201120000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if (!$schema->hasTable('stech_admin_settings')) {
            $table = $schema->createTable('stech_admin_settings');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('setting_key', 'string', [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('setting_value', 'string', [
                'notnull' => false,
                'length' => 255,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['setting_key'], 'stech_settings_key_idx');
        }

        return $schema;
    }
}