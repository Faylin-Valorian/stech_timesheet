<?php

declare(strict_types=1);

namespace OCA\StechTimesheet\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\Migration\IMigrationStep;
use OCP\Migration\IOutput;

class Version20260130100001 implements IMigrationStep {

    public function name(): string {
        return 'Create admin settings table';
    }

    public function description(): string {
        return 'Stores admin panel configurations like descriptions and custom text';
    }

    public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
    }

    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('stech_admin_settings')) {
            $table = $schema->createTable('stech_admin_settings');
            
            // Key (e.g., 'desc_users', 'desc_holidays')
            $table->addColumn('setting_key', 'string', [
                'notnull' => true,
                'length' => 64,
            ]);
            
            // Value (The text description)
            $table->addColumn('setting_value', 'text', [
                'notnull' => false,
            ]);

            $table->setPrimaryKey(['setting_key']);
        }

        return $schema;
    }

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
    }
}