<?php

declare(strict_types=1);

namespace OCA\StechTimesheet\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20260129100001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates stech_jobs, stech_states, and stech_counties tables';
    }

    public function up(Schema $schema): void
    {
        // 1. Create stech_jobs
        if (!$schema->hasTable('stech_jobs')) {
            $table = $schema->createTable('stech_jobs');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('job_name', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('job_description', 'text', ['notnull' => false]);
            $table->addColumn('job_archive', 'integer', ['default' => 0, 'notnull' => true]);
            $table->setPrimaryKey(['id']);
        }

        // 2. Create stech_states
        if (!$schema->hasTable('stech_states')) {
            $table = $schema->createTable('stech_states');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('state_name', 'string', ['length' => 100, 'notnull' => true]);
            $table->addColumn('state_abbr', 'string', ['length' => 10, 'notnull' => true]);
            $table->addColumn('fips_code', 'string', ['length' => 10, 'notnull' => true]);
            $table->addColumn('is_enabled', 'integer', ['default' => 1, 'notnull' => true]);
            $table->addColumn('is_locked', 'integer', ['default' => 0, 'notnull' => true]);
            $table->setPrimaryKey(['id']);
        }

        // 3. Create stech_counties
        if (!$schema->hasTable('stech_counties')) {
            $table = $schema->createTable('stech_counties');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('county_name', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('state_fips', 'string', ['length' => 10, 'notnull' => true]);
            $table->addColumn('is_active', 'integer', ['default' => 1, 'notnull' => true]);
            $table->addColumn('is_enabled', 'integer', ['default' => 1, 'notnull' => true]);
            $table->addColumn('is_locked', 'integer', ['default' => 0, 'notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['state_fips'], 'idx_stech_counties_state_fips');
        }
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('stech_counties');
        $schema->dropTable('stech_states');
        $schema->dropTable('stech_jobs');
    }
}