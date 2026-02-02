<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version20260203100000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if (!$schema->hasTable('stech_access_rules')) {
            $table = $schema->createTable('stech_access_rules');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            // The key for the area, e.g., 'admin_panel', 'analysis_tab', 'analysis_job_breakdown'
            $table->addColumn('rule_key', 'string', [
                'notnull' => true,
                'length' => 64,
            ]);
            // Stores allowed groups as a JSON string, e.g., ["admin", "managers"]
            $table->addColumn('allowed_groups', 'text', [
                'notnull' => false,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['rule_key'], 'stech_access_key_idx');
        }

        return $schema;
    }
}