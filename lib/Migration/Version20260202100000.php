<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version20260202100000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if ($schema->hasTable('stech_holidays')) {
            $table = $schema->getTable('stech_holidays');
            
            // Add column for background styling (URL or CSS Color)
            if (!$table->hasColumn('holiday_bg')) {
                $table->addColumn('holiday_bg', 'string', [
                    'notnull' => false,
                    'length' => 255,
                    'default' => ''
                ]);
            }
        }

        return $schema;
    }
}