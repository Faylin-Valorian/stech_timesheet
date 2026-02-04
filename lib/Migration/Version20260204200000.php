<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Migration;

use OCP\IDBConnection;
use OCP\Migration\IMigrationStep;
use OCP\Migration\IOutput;

class Version20260204200000 implements IMigrationStep {

    /** @var IDBConnection */
    private $db;

    public function __construct(IDBConnection $db) {
        $this->db = $db;
    }

    public function name(): string {
        return 'Initial Data Population - States, Counties, and Jobs';
    }

    public function description(): string {
        return 'Seeds all US states, counties, default jobs, and access rules';
    }

    public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {}

    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
        return $schemaClosure();
    }

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
        
        // 1. Seed Jobs with Financial Defaults
        $this->db->prepare("
            INSERT INTO `*PREFIX*stech_jobs` (job_name, is_pto, job_revenue, job_expense_budget, job_hourly_cost) VALUES
            ('Site Survey', 0, 0, 0, 0), ('Installation', 0, 0, 0, 0), 
            ('Maintenance', 0, 0, 0, 0), ('Repair', 0, 0, 0, 0), 
            ('Travel', 0, 0, 0, 0), ('Office Work', 0, 0, 0, 0), 
            ('Training', 0, 0, 0, 0), ('Vacation', 1, 0, 0, 0), 
            ('Sick Leave', 1, 0, 0, 0)
        ")->execute();

        // 2. Seed ALL US States
        $this->db->prepare("
            INSERT INTO `*PREFIX*stech_states` (state_name, state_abbr, fips_code, is_enabled) VALUES
            ('Alabama', 'AL', '01', 1), ('Alaska', 'AK', '02', 1), ('Arizona', 'AZ', '04', 1),
            ('Arkansas', 'AR', '05', 1), ('California', 'CA', '06', 1), ('Colorado', 'CO', '08', 1),
            ('Connecticut', 'CT', '09', 1), ('Delaware', 'DE', '10', 1), ('Florida', 'FL', '12', 1),
            ('Georgia', 'GA', '13', 1), ('Hawaii', 'HI', '15', 1), ('Idaho', 'ID', '16', 1),
            ('Illinois', 'IL', '17', 1), ('Indiana', 'IN', '18', 1), ('Iowa', 'IA', '19', 1),
            ('Kansas', 'KS', '20', 1), ('Kentucky', 'KY', '21', 1), ('Louisiana', 'LA', '22', 1),
            ('Maine', 'ME', '23', 1), ('Maryland', 'MD', '24', 1), ('Massachusetts', 'MA', '25', 1),
            ('Michigan', 'MI', '26', 1), ('Minnesota', 'MN', '27', 1), ('Mississippi', 'MS', '28', 1),
            ('Missouri', 'MO', '29', 1), ('Montana', 'MT', '30', 1), ('Nebraska', 'NE', '31', 1),
            ('Nevada', 'NV', '32', 1), ('New Hampshire', 'NH', '33', 1), ('New Jersey', 'NJ', '34', 1),
            ('New Mexico', 'NM', '35', 1), ('New York', 'NY', '36', 1), ('North Carolina', 'NC', '37', 1),
            ('North Dakota', 'ND', '38', 1), ('Ohio', 'OH', '39', 1), ('Oklahoma', 'OK', '40', 1),
            ('Oregon', 'OR', '41', 1), ('Pennsylvania', 'PA', '42', 1), ('Rhode Island', 'RI', '44', 1),
            ('South Carolina', 'SC', '45', 1), ('South Dakota', 'SD', '46', 1), ('Tennessee', 'TN', '47', 1),
            ('Texas', 'TX', '48', 1), ('Utah', 'UT', '49', 1), ('Vermont', 'VT', '50', 1),
            ('Virginia', 'VA', '51', 1), ('Washington', 'WA', '53', 1), ('West Virginia', 'WV', '54', 1),
            ('Wisconsin', 'WI', '55', 1), ('Wyoming', 'WY', '56', 1), ('District of Columbia', 'DC', '11', 1)
        ")->execute();

        // 3. Seed Initial Counties
        $this->db->prepare("
            INSERT INTO `*PREFIX*stech_counties` (county_name, state_fips, is_enabled) VALUES
            ('Autauga County', '01', 1), ('Baldwin County', '01', 1), ('Barbour County', '01', 1), 
            ('Bibb County', '01', 1), ('Blount County', '01', 1), ('Bullock County', '01', 1), 
            ('Butler County', '01', 1), ('Calhoun County', '01', 1), ('Chambers County', '01', 1), 
            ('Cherokee County', '01', 1), ('Chilton County', '01', 1), ('Choctaw County', '01', 1),
            ('Aleutians East Borough', '02', 1), ('Anchorage Municipality', '02', 1), 
            ('Apache County', '04', 1), ('Cochise County', '04', 1), ('Coconino County', '04', 1),
            ('Arkansas County', '05', 1), ('Ashley County', '05', 1), ('Baxter County', '05', 1),
            ('Alameda County', '06', 1), ('Alpine County', '06', 1), ('Amador County', '06', 1),
            ('Adams County', '08', 1), ('Alamosa County', '08', 1), ('Arapahoe County', '08', 1),
            ('Alachua County', '12', 1), ('Baker County', '12', 1), ('Bay County', '12', 1),
            ('Appling County', '13', 1), ('Atkinson County', '13', 1), ('Bacon County', '13', 1),
            ('Anderson County', '48', 1), ('Andrews County', '48', 1), ('Angelina County', '48', 1)
        ")->execute();

        // 4. Seed Default RBAC Access Rules
        $this->db->prepare("
            INSERT INTO `*PREFIX*stech_access_rules` (rule_key, allowed_groups) VALUES
            ('admin_panel', '[\"admin\"]'),
            ('analysis_tab', '[\"admin\", \"managers\"]'),
            ('analysis_view_others', '[\"admin\", \"managers\"]'),
            ('analysis_travel', '[\"admin\", \"managers\"]'),
            ('analysis_financial', '[\"admin\"]'),
            ('analysis_location', '[\"admin\", \"managers\"]')
        ")->execute();
    }
}