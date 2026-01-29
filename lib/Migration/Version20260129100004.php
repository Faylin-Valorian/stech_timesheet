<?php

declare(strict_types=1);

namespace OCA\StechTimesheet\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\Migration\IMigrationStep;
use OCP\Migration\IOutput;

class Version20260129100004 implements IMigrationStep {

    public function name(): string {
        return 'Adds missing travel_county column';
    }

    public function description(): string {
        return 'Fixes SQL insert error by adding missing travel_county column';
    }

    public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
    }

    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('stech_timesheets')) {
            $table = $schema->getTable('stech_timesheets');
            
            // Check if column exists, if not, add it
            if (!$table->hasColumn('travel_county')) {
                // Adding as string, length 100 to match city/state fields
                $table->addColumn('travel_county', 'string', ['length' => 100, 'notnull' => false]);
            }
        }

        return $schema;
    }

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
    }
}