<?php

namespace OCA\StechTimesheet\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\Log\ILogger;

class Version010000Date20260124120000 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        
        // LOGGING START
        $logger = \OCP\Server::get(\OCP\ILogger::class);
        $logger->info('STECH MIGRATION: Starting schema update...', ['app' => 'stech_timesheet']);

        // 1. Table: oc_stech_states
        if (!$schema->hasTable('stech_states')) {
            $logger->info('STECH MIGRATION: Creating table stech_states', ['app' => 'stech_timesheet']);
            $table = $schema->createTable('stech_states');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('state_name', 'string', ['notnull' => true, 'length' => 100]);
            $table->addColumn('state_abbr', 'string', ['notnull' => true, 'length' => 10]);
            $table->addColumn('fips_code', 'string', ['notnull' => false, 'length' => 10]);
            $table->addColumn('is_enabled', 'boolean', ['notnull' => true, 'default' => true]);
            $table->addColumn('is_locked', 'boolean', ['notnull' => true, 'default' => false]);
            $table->setPrimaryKey(['id']);
        }

        // 2. Table: oc_stech_counties
        if (!$schema->hasTable('stech_counties')) {
            $logger->info('STECH MIGRATION: Creating table stech_counties', ['app' => 'stech_timesheet']);
            $table = $schema->createTable('stech_counties');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('county_name', 'string', ['notnull' => true, 'length' => 100]);
            $table->addColumn('geo_id', 'string', ['notnull' => false, 'length' => 50]);
            $table->addColumn('state_fips', 'string', ['notnull' => false, 'length' => 50]);
            $table->addColumn('is_active', 'boolean', ['notnull' => true, 'default' => true]);
            $table->addColumn('is_enabled', 'boolean', ['notnull' => true, 'default' => true]);
            $table->addColumn('is_locked', 'boolean', ['notnull' => true, 'default' => false]);
            $table->addColumn('notes', 'text', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
        }

        // 3. Table: oc_stech_timesheets
        if (!$schema->hasTable('stech_timesheets')) {
            $logger->info('STECH MIGRATION: Creating table stech_timesheets', ['app' => 'stech_timesheet']);
            $table = $schema->createTable('stech_timesheets');
            $table->addColumn('timesheet_id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('userid', 'string', ['notnull' => true, 'length' => 64]);
            $table->addColumn('timesheet_date', 'date', ['notnull' => true]);
            $table->addColumn('time_in', 'string', ['notnull' => false, 'length' => 20]);
            $table->addColumn('time_out', 'string', ['notnull' => false, 'length' => 20]);
            $table->addColumn('time_break', 'integer', ['notnull' => false, 'default' => 0]);
            $table->addColumn('time_total', 'decimal', ['notnull' => false, 'scale' => 2, 'precision' => 10]);
            $table->addColumn('travel', 'boolean', ['notnull' => true, 'default' => false]);
            $table->addColumn('travel_time', 'decimal', ['notnull' => false, 'scale' => 2, 'precision' => 10]);
            $table->addColumn('travel_per_diem', 'boolean', ['notnull' => true, 'default' => false]);
            $table->addColumn('travel_first_last_day', 'boolean', ['notnull' => true, 'default' => false]);
            $table->addColumn('travel_overnight', 'boolean', ['notnull' => true, 'default' => false]);
            $table->addColumn('travel_road_scanning', 'boolean', ['notnull' => true, 'default' => false]);
            $table->addColumn('travel_state', 'string', ['notnull' => false, 'length' => 100]);
            $table->addColumn('travel_county', 'string', ['notnull' => false, 'length' => 100]);
            $table->addColumn('travel_city', 'string', ['notnull' => false, 'length' => 100]);
            $table->addColumn('travel_miles', 'integer', ['notnull' => false, 'default' => 0]);
            $table->addColumn('travel_extra_expenses', 'decimal', ['notnull' => false, 'scale' => 2, 'precision' => 10]);
            $table->addColumn('additional_comments', 'text', ['notnull' => false]);
            $table->addColumn('event_count', 'integer', ['notnull' => false, 'default' => 0]);
            $table->addColumn('archive', 'boolean', ['notnull' => true, 'default' => false]);
            
            $table->setPrimaryKey(['timesheet_id']);
            $table->addIndex(['userid'], 'timesheets_userid_idx');
        }

        // 4. Table: oc_stech_activity
        if (!$schema->hasTable('stech_activity')) {
            $logger->info('STECH MIGRATION: Creating table stech_activity', ['app' => 'stech_timesheet']);
            $table = $schema->createTable('stech_activity');
            $table->addColumn('activity_id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('timesheet_id', 'integer', ['notnull' => true]);
            $table->addColumn('activity_description', 'string', ['notnull' => true, 'length' => 255]);
            $table->addColumn('activity_percent', 'integer', ['notnull' => true, 'default' => 0]);
            $table->addColumn('activity_archive', 'boolean', ['notnull' => true, 'default' => false]);
            
            $table->setPrimaryKey(['activity_id']);
            $table->addIndex(['timesheet_id'], 'activity_timesheet_id_idx');
        }

        // 5. Table: oc_stech_holidays
        if (!$schema->hasTable('stech_holidays')) {
            $logger->info('STECH MIGRATION: Creating table stech_holidays', ['app' => 'stech_timesheet']);
            $table = $schema->createTable('stech_holidays');
            $table->addColumn('holiday_id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('holiday_name', 'string', ['notnull' => true, 'length' => 255]);
            $table->addColumn('holiday_start_date', 'date', ['notnull' => true]);
            $table->addColumn('holiday_end_date', 'date', ['notnull' => true]);
            $table->setPrimaryKey(['holiday_id']);
        }

        // 6. Table: oc_stech_jobs
        if (!$schema->hasTable('stech_jobs')) {
            $logger->info('STECH MIGRATION: Creating table stech_jobs', ['app' => 'stech_timesheet']);
            $table = $schema->createTable('stech_jobs');
            $table->addColumn('job_id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('job_name', 'string', ['notnull' => true, 'length' => 255]);
            $table->addColumn('job_description', 'string', ['notnull' => false, 'length' => 255]);
            $table->addColumn('job_archive', 'boolean', ['notnull' => true, 'default' => false]);
            $table->setPrimaryKey(['job_id']);
        }

        return $schema;
    }
}