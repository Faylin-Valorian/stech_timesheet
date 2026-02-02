<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version20260203200000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('stech_jobs')) {
            $table = $schema->getTable('stech_jobs');

            // 1. Total Value of the Job (Revenue)
            if (!$table->hasColumn('job_revenue')) {
                $table->addColumn('job_revenue', 'decimal', [
                    'notnull' => false,
                    'scale' => 2,
                    'precision' => 10,
                    'default' => 0.00
                ]);
            }

            // 2. Budget for Expenses (Materials, Travel, etc.)
            if (!$table->hasColumn('job_expense_budget')) {
                $table->addColumn('job_expense_budget', 'decimal', [
                    'notnull' => false,
                    'scale' => 2,
                    'precision' => 10,
                    'default' => 0.00
                ]);
            }

            // 3. Hourly Cost (Labor Cost per Hour)
            if (!$table->hasColumn('job_hourly_cost')) {
                $table->addColumn('job_hourly_cost', 'decimal', [
                    'notnull' => false,
                    'scale' => 2,
                    'precision' => 10,
                    'default' => 0.00
                ]);
            }
        }

        return $schema;
    }
}