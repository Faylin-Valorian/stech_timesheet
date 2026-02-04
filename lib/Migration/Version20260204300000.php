<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version20260204300000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('stech_jobs')) {
            $table = $schema->getTable('stech_jobs');

            // Add job_description if it is missing
            if (!$table->hasColumn('job_description')) {
                $table->addColumn('job_description', 'text', [
                    'notnull' => false,
                    'length' => 1024,
                    'default' => ''
                ]);
            }
        }

        return $schema;
    }
}