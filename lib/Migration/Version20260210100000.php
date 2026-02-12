<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version20260210100000 extends SimpleMigrationStep {
    /**
     * Adds the job_archived_at column to track when a job was disabled.
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        $table = $schema->getTable('stech_jobs');

        if (!$table->hasColumn('job_archived_at')) {
            $table->addColumn('job_archived_at', 'datetime', [
                'notnull' => false,
                'default' => null,
            ]);
        }

        return $schema;
    }
}