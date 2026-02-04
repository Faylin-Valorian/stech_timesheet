<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version20260204400000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('stech_timesheets')) {
            $table = $schema->getTable('stech_timesheets');

            // Add Road Scanning Column
            if (!$table->hasColumn('travel_road_scanning')) {
                $table->addColumn('travel_road_scanning', 'integer', [
                    'notnull' => false,
                    'default' => 0
                ]);
            }

            // Add First/Last Day Column
            if (!$table->hasColumn('travel_first_last_day')) {
                $table->addColumn('travel_first_last_day', 'integer', [
                    'notnull' => false,
                    'default' => 0
                ]);
            }

            // Add Overnight Stay Column
            if (!$table->hasColumn('travel_overnight')) {
                $table->addColumn('travel_overnight', 'integer', [
                    'notnull' => false,
                    'default' => 0
                ]);
            }
        }

        return $schema;
    }
}