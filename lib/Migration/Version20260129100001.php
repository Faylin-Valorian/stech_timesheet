<?php

declare(strict_types=1);

namespace OCA\StechTimesheet\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\Migration\IMigrationStep;
use OCP\Migration\IOutput;

class Version20260129100001 implements IMigrationStep {

    public function name(): string {
        return 'Creates states, counties, activity, holidays, jobs, and timesheets tables';
    }

    public function description(): string {
        return 'Full schema setup for stech_timesheet v32';
    }

    public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
    }

    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // 1. Table: oc_states (Note: Nextcloud adds 'oc_' automatically)
        if (!$schema->hasTable('states')) {
            $table = $schema->createTable('states');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]); // Internal ID
            $table->addColumn('state_name', 'string', ['length' => 100, 'notnull' => true]); // Fixed typo 'sate_name'
            $table->addColumn('state_abbr', 'string', ['length' => 10, 'notnull' => true]);
            $table->addColumn('fips_code', 'string', ['length' => 10, 'notnull' => true]);
            $table->addColumn('is_enabled', 'integer', ['default' => 1, 'notnull' => true]);
            $table->addColumn('is_locked', 'integer', ['default' => 0, 'notnull' => true]);
            $table->setPrimaryKey(['id']);
        }

        // 2. Table: oc_counties
        if (!$schema->hasTable('counties')) {
            $table = $schema->createTable('counties');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('county_name', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('geo_id', 'string', ['length' => 50, 'notnull' => false]);
            $table->addColumn('state_fips', 'string', ['length' => 10, 'notnull' => true]);
            $table->addColumn('is_active', 'integer', ['default' => 1, 'notnull' => true]);
            $table->addColumn('is_enabled', 'integer', ['default' => 1, 'notnull' => true]);
            $table->addColumn('is_locked', 'integer', ['default' => 0, 'notnull' => true]);
            $table->addColumn('notes', 'text', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['state_fips'], 'idx_counties_state_fips');
        }

        // 3. Table: oc_stech_jobs
        if (!$schema->hasTable('stech_jobs')) {
            $table = $schema->createTable('stech_jobs');
            $table->addColumn('job_id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('job_name', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('job_description', 'text', ['notnull' => false]);
            $table->addColumn('job_archive', 'integer', ['default' => 0, 'notnull' => true]);
            $table->setPrimaryKey(['job_id']);
        }

        // 4. Table: oc_stech_timesheets
        if (!$schema->hasTable('stech_timesheets')) {
            $table = $schema->createTable('stech_timesheets');
            $table->addColumn('timesheet_id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('timesheet_date', 'date', ['notnull' => true]);
            $table->addColumn('time_in', 'time', ['notnull' => false]);
            $table->addColumn('time_out', 'time', ['notnull' => false]);
            $table->addColumn('time_break', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0]);
            $table->addColumn('time_total', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0]);
            $table->addColumn('work_description', 'text', ['notnull' => false]);
            $table->addColumn('travel', 'integer', ['default' => 0]); // Boolean flag
            $table->addColumn('travel_time', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0]);
            $table->addColumn('travel_per_diem', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0]);
            $table->addColumn('travel_first_last_day', 'integer', ['default' => 0]);
            $table->addColumn('travel_overnight', 'integer', ['default' => 0]);
            $table->addColumn('travel_state', 'string', ['length' => 100, 'notnull' => false]);
            $table->addColumn('travel_city', 'string', ['length' => 100, 'notnull' => false]);
            $table->addColumn('travel_miles', 'integer', ['default' => 0]);
            $table->addColumn('travel_extra_expenses', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0]);
            $table->addColumn('additional_comments', 'text', ['notnull' => false]);
            $table->addColumn('event_count', 'integer', ['default' => 0]);
            $table->addColumn('archive', 'integer', ['default' => 0]);
            $table->addColumn('userid', 'string', ['length' => 64, 'notnull' => true]);
            $table->setPrimaryKey(['timesheet_id']);
            $table->addIndex(['userid'], 'idx_stech_timesheets_user');
        }

        // 5. Table: oc_stech_activity
        if (!$schema->hasTable('stech_activity')) {
            $table = $schema->createTable('stech_activity');
            $table->addColumn('activity_id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('activity_description', 'text', ['notnull' => false]);
            $table->addColumn('activity_percent', 'integer', ['default' => 0]);
            $table->addColumn('activity_archive', 'integer', ['default' => 0]);
            $table->addColumn('timesheet_id', 'integer', ['notnull' => true]);
            $table->setPrimaryKey(['activity_id']);
            $table->addIndex(['timesheet_id'], 'idx_stech_activity_timesheet');
        }

        // 6. Table: oc_stech_holidays
        if (!$schema->hasTable('stech_holidays')) {
            $table = $schema->createTable('stech_holidays');
            $table->addColumn('holiday_id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('holiday_start_date', 'date', ['notnull' => true]);
            $table->addColumn('holiday_end_date', 'date', ['notnull' => true]);
            $table->addColumn('holiday_name', 'string', ['length' => 255, 'notnull' => true]);
            $table->setPrimaryKey(['holiday_id']);
        }

        return $schema;
    }

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
    }
}