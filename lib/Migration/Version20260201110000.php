<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version20260201110000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('stech_jobs')) {
            $table = $schema->getTable('stech_jobs');
            if (!$table->hasColumn('is_pto')) {
                $table->addColumn('is_pto', 'integer', [
                    'notnull' => true,
                    'default' => 0,
                    'length' => 1
                ]);
            }
        }

        return $schema;
    }
}