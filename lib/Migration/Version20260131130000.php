<?php

declare(strict_types=1);

namespace OCA\StechTimesheet\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\Migration\IMigrationStep;
use OCP\Migration\IOutput;

class Version20260131130000 implements IMigrationStep {

    public function name(): string {
        return 'Add archive column to holidays';
    }

    public function description(): string {
        return 'Adds holiday_archive field to allow soft deletion';
    }

    public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
    }

    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('stech_holidays')) {
            $table = $schema->getTable('stech_holidays');
            if (!$table->hasColumn('holiday_archive')) {
                $table->addColumn('holiday_archive', 'integer', [
                    'notnull' => false,
                    'default' => 0,
                    'unsigned' => true
                ]);
            }
        }

        return $schema;
    }

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
    }
}