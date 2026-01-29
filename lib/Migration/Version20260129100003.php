<?php

declare(strict_types=1);

namespace OCA\StechTimesheet\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\Migration\IMigrationStep;
use OCP\Migration\IOutput;

class Version20260129100003 implements IMigrationStep {

    public function name(): string {
        return 'Adds missing travel_road_scanning column';
    }

    public function description(): string {
        return 'Fixes SQL insert error by adding missing column';
    }

    public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
    }

    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('stech_timesheets')) {
            $table = $schema->getTable('stech_timesheets');
            
            // Check if column exists, if not, add it
            if (!$table->hasColumn('travel_road_scanning')) {
                // Assuming this is a boolean (0 or 1) or a decimal based on the name. 
                // Using integer (default 0) is the safest bet for a flag/checkbox.
                $table->addColumn('travel_road_scanning', 'integer', ['default' => 0, 'notnull' => false]);
            }
        }

        return $schema;
    }

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
    }
}