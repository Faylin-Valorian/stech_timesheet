<?php

declare(strict_types=1);

namespace OCA\StechTimesheet\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\Migration\IMigrationStep;
use OCP\Migration\IOutput;

class Version20260131140000 implements IMigrationStep {

    public function name(): string {
        return 'Create stech_employees table for user status';
    }

    public function description(): string {
        return 'Stores active/inactive status for employees';
    }

    public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
    }

    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('stech_employees')) {
            $table = $schema->createTable('stech_employees');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('uid', 'string', ['length' => 64, 'notnull' => true]);
            $table->addColumn('is_active', 'integer', ['default' => 1, 'notnull' => true]);
            $table->addColumn('status_changed_at', 'datetime', ['notnull' => false]);
            
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['uid'], 'idx_stech_employees_uid');
        }

        return $schema;
    }

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
    }
}