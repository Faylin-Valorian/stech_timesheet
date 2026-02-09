<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\IDBConnection;

class Version20260204100000 extends SimpleMigrationStep {

    /** @var IDBConnection */
    private $db;

    public function __construct(IDBConnection $db) {
        $this->db = $db;
    }

    public function name(): string {
        return 'Initial Installation - Schema & Data';
    }

    public function description(): string {
        return 'Creates tables and seeds initial data (States, Counties, Jobs, and Access Rules)';
    }

    /**
     * Step 1: Create Database Tables
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // 1. Core Timesheets Table
        if (!$schema->hasTable('stech_timesheets')) {
            $table = $schema->createTable('stech_timesheets');
            $table->addColumn('timesheet_id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('userid', 'string', ['length' => 64, 'notnull' => true]);
            $table->addColumn('timesheet_date', 'date', ['notnull' => true]);
            $table->addColumn('time_in', 'time', ['notnull' => false]);
            $table->addColumn('time_out', 'time', ['notnull' => false]);
            $table->addColumn('time_break', 'integer', ['default' => 0]);
            $table->addColumn('time_total', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0]);
            $table->addColumn('travel', 'integer', ['default' => 0]);
            $table->addColumn('travel_road_scanning', 'integer', ['notnull' => false, 'default' => 0]);
            $table->addColumn('travel_first_last_day', 'integer', ['notnull' => false, 'default' => 0]);
            $table->addColumn('travel_overnight', 'integer', ['notnull' => false, 'default' => 0]);
            $table->addColumn('travel_per_diem', 'integer', ['default' => 0]);
            $table->addColumn('travel_state', 'string', ['length' => 10, 'notnull' => false]);
            $table->addColumn('travel_county', 'string', ['length' => 100, 'notnull' => false]);
            $table->addColumn('travel_miles', 'integer', ['default' => 0]);
            $table->addColumn('travel_extra_expenses', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0]);
            $table->addColumn('additional_comments', 'text', ['notnull' => false]);
            $table->addColumn('archive', 'integer', ['default' => 0]);
            $table->setPrimaryKey(['timesheet_id']);
            $table->addIndex(['userid'], 'idx_stech_ts_user');
        }

        // 2. Activities Table
        if (!$schema->hasTable('stech_activity')) {
            $table = $schema->createTable('stech_activity');
            $table->addColumn('activity_id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('timesheet_id', 'integer', ['notnull' => true]);
            $table->addColumn('activity_description', 'string', ['length' => 255]);
            $table->addColumn('activity_percent', 'integer', ['default' => 0]);
            $table->setPrimaryKey(['activity_id']);
            $table->addIndex(['timesheet_id'], 'idx_stech_act_ts');
        }

        // 3. Jobs Table
        if (!$schema->hasTable('stech_jobs')) {
            $table = $schema->createTable('stech_jobs');
            $table->addColumn('job_id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('job_name', 'string', ['length' => 255]);
            $table->addColumn('job_description', 'text', ['notnull' => false, 'length' => 1024, 'default' => '']);
            $table->addColumn('job_archive', 'integer', ['default' => 0]);
            $table->addColumn('is_pto', 'integer', ['default' => 0]);
            $table->addColumn('job_revenue', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0]);
            $table->addColumn('job_expense_budget', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0]);
            $table->addColumn('job_hourly_cost', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0]);
            $table->setPrimaryKey(['job_id']);
        }

        // 4. Access Rules Table (RBAC)
        if (!$schema->hasTable('stech_access_rules')) {
            $table = $schema->createTable('stech_access_rules');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('rule_key', 'string', ['length' => 64]);
            $table->addColumn('allowed_groups', 'text', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['rule_key'], 'stech_access_idx');
        }

        // 5. Admin Settings Table
        if (!$schema->hasTable('stech_admin_settings')) {
            $table = $schema->createTable('stech_admin_settings');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('setting_key', 'string', ['length' => 64]);
            $table->addColumn('setting_value', 'text', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['setting_key'], 'stech_settings_idx');
        }

        // 6. US States Table
        if (!$schema->hasTable('stech_states')) {
            $table = $schema->createTable('stech_states');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('state_name', 'string', ['length' => 100, 'notnull' => true]);
            $table->addColumn('state_abbr', 'string', ['length' => 10, 'notnull' => true]);
            $table->addColumn('fips_code', 'string', ['length' => 10, 'notnull' => true]);
            $table->addColumn('is_enabled', 'integer', ['default' => 1]);
            $table->setPrimaryKey(['id']);
        }

        // 7. US Counties Table
        if (!$schema->hasTable('stech_counties')) {
            $table = $schema->createTable('stech_counties');
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('county_name', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('state_fips', 'string', ['length' => 10, 'notnull' => true]);
            $table->addColumn('is_enabled', 'integer', ['default' => 1]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['state_fips'], 'idx_stech_counties_fips');
        }

        // 8. Holidays Table
        if (!$schema->hasTable('stech_holidays')) {
            $table = $schema->createTable('stech_holidays');
            $table->addColumn('holiday_id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('holiday_name', 'string', ['length' => 255]);
            $table->addColumn('holiday_start_date', 'date');
            $table->addColumn('holiday_end_date', 'date');
            $table->addColumn('holiday_bg', 'string', ['length' => 255, 'default' => '']);
            $table->addColumn('holiday_archive', 'integer', ['default' => 0]);
            $table->setPrimaryKey(['holiday_id']);
        }

        return $schema;
    }

    /**
     * Step 2: Seed Initial Data
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        
        // 1. Seed Jobs with Randomized Financial Defaults
        $this->db->prepare("
            INSERT INTO `*PREFIX*stech_jobs` (job_name, is_pto, job_revenue, job_expense_budget, job_hourly_cost) VALUES
            ('Site Survey', 0, FLOOR(1000 + (RAND() * 2000)), FLOOR(100 + (RAND() * 500)), FLOOR(50 + (RAND() * 50))), 
            ('Installation', 0, FLOOR(5000 + (RAND() * 10000)), FLOOR(1000 + (RAND() * 2000)), FLOOR(80 + (RAND() * 70))), 
            ('Maintenance', 0, FLOOR(2000 + (RAND() * 3000)), FLOOR(200 + (RAND() * 800)), FLOOR(80 + (RAND() * 70))), 
            ('Repair', 0, FLOOR(1500 + (RAND() * 4000)), FLOOR(500 + (RAND() * 1000)), FLOOR(90 + (RAND() * 60))), 
            ('Travel', 0, 0, FLOOR(500 + (RAND() * 1000)), FLOOR(40 + (RAND() * 20))), 
            ('Office Work', 0, 0, 0, FLOOR(30 + (RAND() * 20))), 
            ('Training', 0, 0, FLOOR(500 + (RAND() * 500)), FLOOR(40 + (RAND() * 20))), 
            ('Vacation', 1, 0, 0, 0), 
            ('Sick Leave', 1, 0, 0, 0)
        ")->execute();

        // 2. Seed ALL US States
        $this->db->prepare("
            INSERT INTO `*PREFIX*stech_states` (state_name, state_abbr, fips_code, is_enabled) VALUES
            ('Alabama', 'AL', '01', 1), ('Alaska', 'AK', '02', 1), ('Arizona', 'AZ', '04', 1),
            ('Arkansas', 'AR', '05', 1), ('California', 'CA', '06', 1), ('Colorado', 'CO', '08', 1),
            ('Connecticut', 'CT', '09', 1), ('Delaware', 'DE', '10', 1), ('District of Columbia', 'DC', '11', 1),
            ('Florida', 'FL', '12', 1), ('Georgia', 'GA', '13', 1), ('Hawaii', 'HI', '15', 1),
            ('Idaho', 'ID', '16', 1), ('Illinois', 'IL', '17', 1), ('Indiana', 'IN', '18', 1),
            ('Iowa', 'IA', '19', 1), ('Kansas', 'KS', '20', 1), ('Kentucky', 'KY', '21', 1),
            ('Louisiana', 'LA', '22', 1), ('Maine', 'ME', '23', 1), ('Maryland', 'MD', '24', 1),
            ('Massachusetts', 'MA', '25', 1), ('Michigan', 'MI', '26', 1), ('Minnesota', 'MN', '27', 1),
            ('Mississippi', 'MS', '28', 1), ('Missouri', 'MO', '29', 1), ('Montana', 'MT', '30', 1),
            ('Nebraska', 'NE', '31', 1), ('Nevada', 'NV', '32', 1), ('New Hampshire', 'NH', '33', 1),
            ('New Jersey', 'NJ', '34', 1), ('New Mexico', 'NM', '35', 1), ('New York', 'NY', '36', 1),
            ('North Carolina', 'NC', '37', 1), ('North Dakota', 'ND', '38', 1), ('Ohio', 'OH', '39', 1),
            ('Oklahoma', 'OK', '40', 1), ('Oregon', 'OR', '41', 1), ('Pennsylvania', 'PA', '42', 1),
            ('Rhode Island', 'RI', '44', 1), ('South Carolina', 'SC', '45', 1), ('South Dakota', 'SD', '46', 1),
            ('Tennessee', 'TN', '47', 1), ('Texas', 'TX', '48', 1), ('Utah', 'UT', '49', 1),
            ('Vermont', 'VT', '50', 1), ('Virginia', 'VA', '51', 1), ('Washington', 'WA', '53', 1),
            ('West Virginia', 'WV', '54', 1), ('Wisconsin', 'WI', '55', 1), ('Wyoming', 'WY', '56', 1)
        ")->execute();

        // 3. Seed Counties (Chunks)
        // Chunk A
        $this->db->prepare("
            INSERT INTO `*PREFIX*stech_counties` (county_name, state_fips, is_enabled) VALUES
            ('Autauga County', '01', 1), ('Baldwin County', '01', 1), ('Barbour County', '01', 1), ('Bibb County', '01', 1), ('Blount County', '01', 1), ('Bullock County', '01', 1), ('Butler County', '01', 1), ('Calhoun County', '01', 1), ('Chambers County', '01', 1), ('Cherokee County', '01', 1), ('Chilton County', '01', 1), ('Choctaw County', '01', 1), ('Clarke County', '01', 1), ('Clay County', '01', 1), ('Cleburne County', '01', 1), ('Coffee County', '01', 1), ('Colbert County', '01', 1), ('Conecuh County', '01', 1), ('Coosa County', '01', 1), ('Covington County', '01', 1), ('Crenshaw County', '01', 1), ('Cullman County', '01', 1), ('Dale County', '01', 1), ('Dallas County', '01', 1), ('DeKalb County', '01', 1), ('Elmore County', '01', 1), ('Escambia County', '01', 1), ('Etowah County', '01', 1), ('Fayette County', '01', 1), ('Franklin County', '01', 1), ('Geneva County', '01', 1), ('Greene County', '01', 1), ('Hale County', '01', 1), ('Henry County', '01', 1), ('Houston County', '01', 1), ('Jackson County', '01', 1), ('Jefferson County', '01', 1), ('Lamar County', '01', 1), ('Lauderdale County', '01', 1), ('Lawrence County', '01', 1), ('Lee County', '01', 1), ('Limestone County', '01', 1), ('Lowndes County', '01', 1), ('Macon County', '01', 1), ('Madison County', '01', 1), ('Marengo County', '01', 1), ('Marion County', '01', 1), ('Marshall County', '01', 1), ('Mobile County', '01', 1), ('Monroe County', '01', 1), ('Montgomery County', '01', 1), ('Morgan County', '01', 1), ('Perry County', '01', 1), ('Pickens County', '01', 1), ('Pike County', '01', 1), ('Randolph County', '01', 1), ('Russell County', '01', 1), ('St. Clair County', '01', 1), ('Shelby County', '01', 1), ('Sumter County', '01', 1), ('Talladega County', '01', 1), ('Tallapoosa County', '01', 1), ('Tuscaloosa County', '01', 1), ('Walker County', '01', 1), ('Washington County', '01', 1), ('Wilcox County', '01', 1), ('Winston County', '01', 1),
            ('Aleutians East Borough', '02', 1), ('Aleutians West Census Area', '02', 1), ('Anchorage Municipality', '02', 1), ('Bethel Census Area', '02', 1), ('Bristol Bay Borough', '02', 1), ('Denali Borough', '02', 1), ('Dillingham Census Area', '02', 1), ('Fairbanks North Star Borough', '02', 1), ('Haines Borough', '02', 1), ('Hoonah-Angoon Census Area', '02', 1), ('Juneau City and Borough', '02', 1), ('Kenai Peninsula Borough', '02', 1), ('Ketchikan Gateway Borough', '02', 1), ('Kodiak Island Borough', '02', 1), ('Kusilvak Census Area', '02', 1), ('Lake and Peninsula Borough', '02', 1), ('Matanuska-Susitna Borough', '02', 1), ('Nome Census Area', '02', 1), ('North Slope Borough', '02', 1), ('Northwest Arctic Borough', '02', 1), ('Petersburg Borough', '02', 1), ('Prince of Wales-Hyder Census Area', '02', 1), ('Sitka City and Borough', '02', 1), ('Skagway Municipality', '02', 1), ('Southeast Fairbanks Census Area', '02', 1), ('Valdez-Cordova Census Area', '02', 1), ('Wrangell City and Borough', '02', 1), ('Yakutat City and Borough', '02', 1), ('Yukon-Koyukuk Census Area', '02', 1),
            ('Apache County', '04', 1), ('Cochise County', '04', 1), ('Coconino County', '04', 1), ('Gila County', '04', 1), ('Graham County', '04', 1), ('Greenlee County', '04', 1), ('La Paz County', '04', 1), ('Maricopa County', '04', 1), ('Mohave County', '04', 1), ('Navajo County', '04', 1), ('Pima County', '04', 1), ('Pinal County', '04', 1), ('Santa Cruz County', '04', 1), ('Yavapai County', '04', 1), ('Yuma County', '04', 1),
            ('Arkansas County', '05', 1), ('Ashley County', '05', 1), ('Baxter County', '05', 1), ('Benton County', '05', 1), ('Boone County', '05', 1), ('Bradley County', '05', 1), ('Calhoun County', '05', 1), ('Carroll County', '05', 1), ('Chicot County', '05', 1), ('Clark County', '05', 1), ('Clay County', '05', 1), ('Cleburne County', '05', 1), ('Cleveland County', '05', 1), ('Columbia County', '05', 1), ('Conway County', '05', 1), ('Craighead County', '05', 1), ('Crawford County', '05', 1), ('Crittenden County', '05', 1), ('Cross County', '05', 1), ('Dallas County', '05', 1), ('Desha County', '05', 1), ('Drew County', '05', 1), ('Faulkner County', '05', 1), ('Franklin County', '05', 1), ('Fulton County', '05', 1), ('Garland County', '05', 1), ('Grant County', '05', 1), ('Greene County', '05', 1), ('Hempstead County', '05', 1), ('Hot Spring County', '05', 1), ('Howard County', '05', 1), ('Independence County', '05', 1), ('Izard County', '05', 1), ('Jackson County', '05', 1), ('Jefferson County', '05', 1), ('Johnson County', '05', 1), ('Lafayette County', '05', 1), ('Lawrence County', '05', 1), ('Lee County', '05', 1), ('Lincoln County', '05', 1), ('Little River County', '05', 1), ('Logan County', '05', 1), ('Lonoke County', '05', 1), ('Madison County', '05', 1), ('Marion County', '05', 1), ('Miller County', '05', 1), ('Mississippi County', '05', 1), ('Monroe County', '05', 1), ('Montgomery County', '05', 1), ('Nevada County', '05', 1), ('Newton County', '05', 1), ('Ouachita County', '05', 1), ('Perry County', '05', 1), ('Phillips County', '05', 1), ('Pike County', '05', 1), ('Poinsett County', '05', 1), ('Polk County', '05', 1), ('Pope County', '05', 1), ('Prairie County', '05', 1), ('Pulaski County', '05', 1), ('Randolph County', '05', 1), ('Saline County', '05', 1), ('Scott County', '05', 1), ('Searcy County', '05', 1), ('Sebastian County', '05', 1), ('Sevier County', '05', 1), ('Sharp County', '05', 1), ('St. Francis County', '05', 1), ('Stone County', '05', 1), ('Union County', '05', 1), ('Van Buren County', '05', 1), ('Washington County', '05', 1), ('White County', '05', 1), ('Woodruff County', '05', 1), ('Yell County', '05', 1),
            ('Alameda County', '06', 1), ('Alpine County', '06', 1), ('Amador County', '06', 1), ('Butte County', '06', 1), ('Calaveras County', '06', 1), ('Colusa County', '06', 1), ('Contra Costa County', '06', 1), ('Del Norte County', '06', 1), ('El Dorado County', '06', 1), ('Fresno County', '06', 1), ('Glenn County', '06', 1), ('Humboldt County', '06', 1), ('Imperial County', '06', 1), ('Inyo County', '06', 1), ('Kern County', '06', 1), ('Kings County', '06', 1), ('Lake County', '06', 1), ('Lassen County', '06', 1), ('Los Angeles County', '06', 1), ('Madera County', '06', 1), ('Marin County', '06', 1), ('Mariposa County', '06', 1), ('Mendocino County', '06', 1), ('Merced County', '06', 1), ('Modoc County', '06', 1), ('Mono County', '06', 1), ('Monterey County', '06', 1), ('Napa County', '06', 1), ('Nevada County', '06', 1), ('Orange County', '06', 1), ('Placer County', '06', 1), ('Plumas County', '06', 1), ('Riverside County', '06', 1), ('Sacramento County', '06', 1), ('San Benito County', '06', 1), ('San Bernardino County', '06', 1), ('San Diego County', '06', 1), ('San Francisco County', '06', 1), ('San Joaquin County', '06', 1), ('San Luis Obispo County', '06', 1), ('San Mateo County', '06', 1), ('Santa Barbara County', '06', 1), ('Santa Clara County', '06', 1), ('Santa Cruz County', '06', 1), ('Shasta County', '06', 1), ('Sierra County', '06', 1), ('Siskiyou County', '06', 1), ('Solano County', '06', 1), ('Sonoma County', '06', 1), ('Stanislaus County', '06', 1), ('Sutter County', '06', 1), ('Tehama County', '06', 1), ('Trinity County', '06', 1), ('Tulare County', '06', 1), ('Tuolumne County', '06', 1), ('Ventura County', '06', 1), ('Yolo County', '06', 1), ('Yuba County', '06', 1),
            ('Adams County', '08', 1), ('Alamosa County', '08', 1), ('Arapahoe County', '08', 1), ('Archuleta County', '08', 1), ('Baca County', '08', 1), ('Bent County', '08', 1), ('Boulder County', '08', 1), ('Broomfield County', '08', 1), ('Chaffee County', '08', 1), ('Cheyenne County', '08', 1), ('Clear Creek County', '08', 1), ('Conejos County', '08', 1), ('Costilla County', '08', 1), ('Crowley County', '08', 1), ('Custer County', '08', 1), ('Delta County', '08', 1), ('Denver County', '08', 1), ('Dolores County', '08', 1), ('Douglas County', '08', 1), ('Eagle County', '08', 1), ('Elbert County', '08', 1), ('El Paso County', '08', 1), ('Fremont County', '08', 1), ('Garfield County', '08', 1), ('Gilpin County', '08', 1), ('Grand County', '08', 1), ('Gunnison County', '08', 1), ('Hinsdale County', '08', 1), ('Huerfano County', '08', 1), ('Jackson County', '08', 1), ('Jefferson County', '08', 1), ('Kiowa County', '08', 1), ('Kit Carson County', '08', 1), ('Lake County', '08', 1), ('La Plata County', '08', 1), ('Larimer County', '08', 1), ('Las Animas County', '08', 1), ('Lincoln County', '08', 1), ('Logan County', '08', 1), ('Mesa County', '08', 1), ('Mineral County', '08', 1), ('Moffat County', '08', 1), ('Montezuma County', '08', 1), ('Montrose County', '08', 1), ('Morgan County', '08', 1), ('Otero County', '08', 1), ('Ouray County', '08', 1), ('Park County', '08', 1), ('Phillips County', '08', 1), ('Pitkin County', '08', 1), ('Prowers County', '08', 1), ('Pueblo County', '08', 1), ('Rio Blanco County', '08', 1), ('Rio Grande County', '08', 1), ('Routt County', '08', 1), ('Saguache County', '08', 1), ('San Juan County', '08', 1), ('San Miguel County', '08', 1), ('Sedgwick County', '08', 1), ('Summit County', '08', 1), ('Teller County', '08', 1), ('Washington County', '08', 1), ('Weld County', '08', 1), ('Yuma County', '08', 1)
        ")->execute();

        // Chunk B
        $this->db->prepare("
            INSERT INTO `*PREFIX*stech_counties` (county_name, state_fips, is_enabled) VALUES
            ('Alachua County', '12', 1), ('Baker County', '12', 1), ('Bay County', '12', 1), ('Bradford County', '12', 1), ('Brevard County', '12', 1), ('Broward County', '12', 1), ('Calhoun County', '12', 1), ('Charlotte County', '12', 1), ('Citrus County', '12', 1), ('Clay County', '12', 1), ('Collier County', '12', 1), ('Columbia County', '12', 1), ('DeSoto County', '12', 1), ('Dixie County', '12', 1), ('Duval County', '12', 1), ('Escambia County', '12', 1), ('Flagler County', '12', 1), ('Franklin County', '12', 1), ('Gadsden County', '12', 1), ('Gilchrist County', '12', 1), ('Glades County', '12', 1), ('Gulf County', '12', 1), ('Hamilton County', '12', 1), ('Hardee County', '12', 1), ('Hendry County', '12', 1), ('Hernando County', '12', 1), ('Highlands County', '12', 1), ('Hillsborough County', '12', 1), ('Holmes County', '12', 1), ('Indian River County', '12', 1), ('Jackson County', '12', 1), ('Jefferson County', '12', 1), ('Lafayette County', '12', 1), ('Lake County', '12', 1), ('Lee County', '12', 1), ('Leon County', '12', 1), ('Levy County', '12', 1), ('Liberty County', '12', 1), ('Madison County', '12', 1), ('Manatee County', '12', 1), ('Marion County', '12', 1), ('Martin County', '12', 1), ('Miami-Dade County', '12', 1), ('Monroe County', '12', 1), ('Nassau County', '12', 1), ('Okaloosa County', '12', 1), ('Okeechobee County', '12', 1), ('Orange County', '12', 1), ('Osceola County', '12', 1), ('Palm Beach County', '12', 1), ('Pasco County', '12', 1), ('Pinellas County', '12', 1), ('Polk County', '12', 1), ('Putnam County', '12', 1), ('St. Johns County', '12', 1), ('St. Lucie County', '12', 1), ('Santa Rosa County', '12', 1), ('Sarasota County', '12', 1), ('Seminole County', '12', 1), ('Sumter County', '12', 1), ('Suwannee County', '12', 1), ('Taylor County', '12', 1), ('Union County', '12', 1), ('Volusia County', '12', 1), ('Wakulla County', '12', 1), ('Walton County', '12', 1), ('Washington County', '12', 1),
            ('Appling County', '13', 1), ('Atkinson County', '13', 1), ('Bacon County', '13', 1), ('Baker County', '13', 1), ('Baldwin County', '13', 1), ('Banks County', '13', 1), ('Barrow County', '13', 1), ('Bartow County', '13', 1), ('Ben Hill County', '13', 1), ('Berrien County', '13', 1), ('Bibb County', '13', 1), ('Bleckley County', '13', 1), ('Brantley County', '13', 1), ('Brooks County', '13', 1), ('Bryan County', '13', 1), ('Bulloch County', '13', 1), ('Burke County', '13', 1), ('Butts County', '13', 1), ('Calhoun County', '13', 1), ('Camden County', '13', 1), ('Candler County', '13', 1), ('Carroll County', '13', 1), ('Catoosa County', '13', 1), ('Charlton County', '13', 1), ('Chatham County', '13', 1), ('Chattahoochee County', '13', 1), ('Chattooga County', '13', 1), ('Cherokee County', '13', 1), ('Clarke County', '13', 1), ('Clay County', '13', 1), ('Clayton County', '13', 1), ('Clinch County', '13', 1), ('Cobb County', '13', 1), ('Coffee County', '13', 1), ('Colquitt County', '13', 1), ('Columbia County', '13', 1), ('Cook County', '13', 1), ('Coweta County', '13', 1), ('Crawford County', '13', 1), ('Crisp County', '13', 1), ('Dade County', '13', 1), ('Dawson County', '13', 1), ('Decatur County', '13', 1), ('DeKalb County', '13', 1), ('Dodge County', '13', 1), ('Dooly County', '13', 1), ('Dougherty County', '13', 1), ('Douglas County', '13', 1), ('Early County', '13', 1), ('Echols County', '13', 1), ('Effingham County', '13', 1), ('Elbert County', '13', 1), ('Emanuel County', '13', 1), ('Evans County', '13', 1), ('Fannin County', '13', 1), ('Fayette County', '13', 1), ('Floyd County', '13', 1), ('Forsyth County', '13', 1), ('Franklin County', '13', 1), ('Fulton County', '13', 1), ('Gilmer County', '13', 1), ('Glascock County', '13', 1), ('Glynn County', '13', 1), ('Gordon County', '13', 1), ('Grady County', '13', 1), ('Greene County', '13', 1), ('Gwinnett County', '13', 1), ('Habersham County', '13', 1), ('Hall County', '13', 1), ('Hancock County', '13', 1), ('Haralson County', '13', 1), ('Harris County', '13', 1), ('Hart County', '13', 1), ('Heard County', '13', 1), ('Henry County', '13', 1), ('Houston County', '13', 1), ('Irwin County', '13', 1), ('Jackson County', '13', 1), ('Jasper County', '13', 1), ('Jeff Davis County', '13', 1), ('Jefferson County', '13', 1), ('Jenkins County', '13', 1), ('Johnson County', '13', 1), ('Jones County', '13', 1), ('Lamar County', '13', 1), ('Lanier County', '13', 1), ('Laurens County', '13', 1), ('Lee County', '13', 1), ('Liberty County', '13', 1), ('Lincoln County', '13', 1), ('Long County', '13', 1), ('Lowndes County', '13', 1), ('Lumpkin County', '13', 1), ('McDuffie County', '13', 1), ('McIntosh County', '13', 1), ('Macon County', '13', 1), ('Madison County', '13', 1), ('Marion County', '13', 1), ('Meriwether County', '13', 1), ('Miller County', '13', 1), ('Mitchell County', '13', 1), ('Monroe County', '13', 1), ('Montgomery County', '13', 1), ('Morgan County', '13', 1), ('Murray County', '13', 1), ('Muscogee County', '13', 1), ('Newton County', '13', 1), ('Oconee County', '13', 1), ('Oglethorpe County', '13', 1), ('Paulding County', '13', 1), ('Peach County', '13', 1), ('Pickens County', '13', 1), ('Pierce County', '13', 1), ('Pike County', '13', 1), ('Polk County', '13', 1), ('Pulaski County', '13', 1), ('Putnam County', '13', 1), ('Quitman County', '13', 1), ('Rabun County', '13', 1), ('Randolph County', '13', 1), ('Richmond County', '13', 1), ('Rockdale County', '13', 1), ('Schley County', '13', 1), ('Screven County', '13', 1), ('Seminole County', '13', 1), ('Spalding County', '13', 1), ('Stephens County', '13', 1), ('Stewart County', '13', 1), ('Sumter County', '13', 1), ('Talbot County', '13', 1), ('Taliaferro County', '13', 1), ('Tattnall County', '13', 1), ('Taylor County', '13', 1), ('Telfair County', '13', 1), ('Terrell County', '13', 1), ('Thomas County', '13', 1), ('Tift County', '13', 1), ('Toombs County', '13', 1), ('Towns County', '13', 1), ('Treutlen County', '13', 1), ('Troup County', '13', 1), ('Turner County', '13', 1), ('Twiggs County', '13', 1), ('Union County', '13', 1), ('Upson County', '13', 1), ('Walker County', '13', 1), ('Walton County', '13', 1), ('Ware County', '13', 1), ('Warren County', '13', 1), ('Washington County', '13', 1), ('Wayne County', '13', 1), ('Webster County', '13', 1), ('Wheeler County', '13', 1), ('White County', '13', 1), ('Whitfield County', '13', 1), ('Wilcox County', '13', 1), ('Wilkes County', '13', 1), ('Wilkinson County', '13', 1), ('Worth County', '13', 1),
            ('Hawaii County', '15', 1), ('Honolulu County', '15', 1), ('Kalawao County', '15', 1), ('Kauai County', '15', 1), ('Maui County', '15', 1),
            ('Ada County', '16', 1), ('Adams County', '16', 1), ('Bannock County', '16', 1), ('Bear Lake County', '16', 1), ('Benewah County', '16', 1), ('Bingham County', '16', 1), ('Blaine County', '16', 1), ('Boise County', '16', 1), ('Bonner County', '16', 1), ('Bonneville County', '16', 1), ('Boundary County', '16', 1), ('Butte County', '16', 1), ('Camas County', '16', 1), ('Canyon County', '16', 1), ('Caribou County', '16', 1), ('Cassia County', '16', 1), ('Clark County', '16', 1), ('Clearwater County', '16', 1), ('Custer County', '16', 1), ('Elmore County', '16', 1), ('Franklin County', '16', 1), ('Fremont County', '16', 1), ('Gem County', '16', 1), ('Gooding County', '16', 1), ('Idaho County', '16', 1), ('Jefferson County', '16', 1), ('Jerome County', '16', 1), ('Kootenai County', '16', 1), ('Latah County', '16', 1), ('Lemhi County', '16', 1), ('Lewis County', '16', 1), ('Lincoln County', '16', 1), ('Madison County', '16', 1), ('Minidoka County', '16', 1), ('Nez Perce County', '16', 1), ('Oneida County', '16', 1), ('Owyhee County', '16', 1), ('Payette County', '16', 1), ('Power County', '16', 1), ('Shoshone County', '16', 1), ('Teton County', '16', 1), ('Twin Falls County', '16', 1), ('Valley County', '16', 1), ('Washington County', '16', 1)
        ")->execute();

        // Chunk C (Texas)
        $this->db->prepare("
            INSERT INTO `*PREFIX*stech_counties` (county_name, state_fips, is_enabled) VALUES
            ('Anderson County', '48', 1), ('Andrews County', '48', 1), ('Angelina County', '48', 1), ('Aransas County', '48', 1), ('Archer County', '48', 1), ('Armstrong County', '48', 1), ('Atascosa County', '48', 1), ('Austin County', '48', 1), ('Bailey County', '48', 1), ('Bandera County', '48', 1), ('Bastrop County', '48', 1), ('Baylor County', '48', 1), ('Bee County', '48', 1), ('Bell County', '48', 1), ('Bexar County', '48', 1), ('Blanco County', '48', 1), ('Borden County', '48', 1), ('Bosque County', '48', 1), ('Bowie County', '48', 1), ('Brazoria County', '48', 1), ('Brazos County', '48', 1), ('Brewster County', '48', 1), ('Briscoe County', '48', 1), ('Brooks County', '48', 1), ('Brown County', '48', 1), ('Burleson County', '48', 1), ('Burnet County', '48', 1), ('Caldwell County', '48', 1), ('Calhoun County', '48', 1), ('Callahan County', '48', 1), ('Cameron County', '48', 1), ('Camp County', '48', 1), ('Carson County', '48', 1), ('Cass County', '48', 1), ('Castro County', '48', 1), ('Chambers County', '48', 1), ('Cherokee County', '48', 1), ('Childress County', '48', 1), ('Clay County', '48', 1), ('Cochran County', '48', 1), ('Coke County', '48', 1), ('Coleman County', '48', 1), ('Collin County', '48', 1), ('Collingsworth County', '48', 1), ('Colorado County', '48', 1), ('Comal County', '48', 1), ('Comanche County', '48', 1), ('Concho County', '48', 1), ('Cooke County', '48', 1), ('Coryell County', '48', 1), ('Cottle County', '48', 1), ('Crane County', '48', 1), ('Crockett County', '48', 1), ('Crosby County', '48', 1), ('Culberson County', '48', 1), ('Dallam County', '48', 1), ('Dallas County', '48', 1), ('Dawson County', '48', 1), ('Deaf Smith County', '48', 1), ('Delta County', '48', 1), ('Denton County', '48', 1), ('DeWitt County', '48', 1), ('Dickens County', '48', 1), ('Dimmit County', '48', 1), ('Donley County', '48', 1), ('Duval County', '48', 1), ('Eastland County', '48', 1), ('Ector County', '48', 1), ('Edwards County', '48', 1), ('Ellis County', '48', 1), ('El Paso County', '48', 1), ('Erath County', '48', 1), ('Falls County', '48', 1), ('Fannin County', '48', 1), ('Fayette County', '48', 1), ('Fisher County', '48', 1), ('Floyd County', '48', 1), ('Foard County', '48', 1), ('Fort Bend County', '48', 1), ('Franklin County', '48', 1), ('Freestone County', '48', 1), ('Frio County', '48', 1), ('Gaines County', '48', 1), ('Galveston County', '48', 1), ('Garza County', '48', 1), ('Gillespie County', '48', 1), ('Glasscock County', '48', 1), ('Goliad County', '48', 1), ('Gonzales County', '48', 1), ('Gray County', '48', 1), ('Grayson County', '48', 1), ('Gregg County', '48', 1), ('Grimes County', '48', 1), ('Guadalupe County', '48', 1), ('Hale County', '48', 1), ('Hall County', '48', 1), ('Hamilton County', '48', 1), ('Hansford County', '48', 1), ('Hardeman County', '48', 1), ('Hardin County', '48', 1), ('Harris County', '48', 1), ('Harrison County', '48', 1), ('Hartley County', '48', 1), ('Haskell County', '48', 1), ('Hays County', '48', 1), ('Hemphill County', '48', 1), ('Henderson County', '48', 1), ('Hidalgo County', '48', 1), ('Hill County', '48', 1), ('Hockley County', '48', 1), ('Hood County', '48', 1), ('Hopkins County', '48', 1), ('Houston County', '48', 1), ('Howard County', '48', 1), ('Hudspeth County', '48', 1), ('Hunt County', '48', 1), ('Hutchinson County', '48', 1), ('Irion County', '48', 1), ('Jack County', '48', 1), ('Jackson County', '48', 1), ('Jasper County', '48', 1), ('Jeff Davis County', '48', 1), ('Jefferson County', '48', 1), ('Jim Hogg County', '48', 1), ('Jim Wells County', '48', 1), ('Johnson County', '48', 1), ('Jones County', '48', 1), ('Karnes County', '48', 1), ('Kaufman County', '48', 1), ('Kendall County', '48', 1), ('Kenedy County', '48', 1), ('Kent County', '48', 1), ('Kerr County', '48', 1), ('Kimble County', '48', 1), ('King County', '48', 1), ('Kinney County', '48', 1), ('Kleberg County', '48', 1), ('Knox County', '48', 1), ('Lamar County', '48', 1), ('Lamb County', '48', 1), ('Lampasas County', '48', 1), ('La Salle County', '48', 1), ('Lavaca County', '48', 1), ('Lee County', '48', 1), ('Leon County', '48', 1), ('Liberty County', '48', 1), ('Limestone County', '48', 1), ('Lipscomb County', '48', 1), ('Live Oak County', '48', 1), ('Llano County', '48', 1), ('Loving County', '48', 1), ('Lubbock County', '48', 1), ('Lynn County', '48', 1), ('McCulloch County', '48', 1), ('McLennan County', '48', 1), ('McMullen County', '48', 1), ('Madison County', '48', 1), ('Marion County', '48', 1), ('Martin County', '48', 1), ('Mason County', '48', 1), ('Matagorda County', '48', 1), ('Maverick County', '48', 1), ('Medina County', '48', 1), ('Menard County', '48', 1), ('Midland County', '48', 1), ('Milam County', '48', 1), ('Mills County', '48', 1), ('Mitchell County', '48', 1), ('Montague County', '48', 1), ('Montgomery County', '48', 1), ('Moore County', '48', 1), ('Morris County', '48', 1), ('Motley County', '48', 1), ('Nacogdoches County', '48', 1), ('Navarro County', '48', 1), ('Newton County', '48', 1), ('Nolan County', '48', 1), ('Nueces County', '48', 1), ('Ochiltree County', '48', 1), ('Oldham County', '48', 1), ('Orange County', '48', 1), ('Palo Pinto County', '48', 1), ('Panola County', '48', 1), ('Parker County', '48', 1), ('Parmer County', '48', 1), ('Pecos County', '48', 1), ('Polk County', '48', 1), ('Potter County', '48', 1), ('Presidio County', '48', 1), ('Rains County', '48', 1), ('Randall County', '48', 1), ('Reagan County', '48', 1), ('Real County', '48', 1), ('Red River County', '48', 1), ('Reeves County', '48', 1), ('Refugio County', '48', 1), ('Roberts County', '48', 1), ('Robertson County', '48', 1), ('Rockwall County', '48', 1), ('Runnels County', '48', 1), ('Rusk County', '48', 1), ('Sabine County', '48', 1), ('San Augustine County', '48', 1), ('San Jacinto County', '48', 1), ('San Patricio County', '48', 1), ('San Saba County', '48', 1), ('Schleicher County', '48', 1), ('Scurry County', '48', 1), ('Shackelford County', '48', 1), ('Shelby County', '48', 1), ('Sherman County', '48', 1), ('Smith County', '48', 1), ('Somervell County', '48', 1), ('Starr County', '48', 1), ('Stephens County', '48', 1), ('Sterling County', '48', 1), ('Stonewall County', '48', 1), ('Sutton County', '48', 1), ('Swisher County', '48', 1), ('Tarrant County', '48', 1), ('Taylor County', '48', 1), ('Terrell County', '48', 1), ('Terry County', '48', 1), ('Throckmorton County', '48', 1), ('Titus County', '48', 1), ('Tom Green County', '48', 1), ('Travis County', '48', 1), ('Trinity County', '48', 1), ('Tyler County', '48', 1), ('Upshur County', '48', 1), ('Upton County', '48', 1), ('Uvalde County', '48', 1), ('Val Verde County', '48', 1), ('Van Zandt County', '48', 1), ('Victoria County', '48', 1), ('Walker County', '48', 1), ('Waller County', '48', 1), ('Ward County', '48', 1), ('Washington County', '48', 1), ('Webb County', '48', 1), ('Wharton County', '48', 1), ('Wheeler County', '48', 1), ('Wichita County', '48', 1), ('Wilbarger County', '48', 1), ('Willacy County', '48', 1), ('Williamson County', '48', 1), ('Wilson County', '48', 1), ('Winkler County', '48', 1), ('Wise County', '48', 1), ('Wood County', '48', 1), ('Yoakum County', '48', 1), ('Young County', '48', 1), ('Zapata County', '48', 1), ('Zavala County', '48', 1)
        ")->execute();

        // 4. Seed Default RBAC Access Rules (Includes NEW keys)
        $this->db->prepare("
            INSERT INTO `*PREFIX*stech_access_rules` (rule_key, allowed_groups) VALUES
            ('admin_global_access', '[\"admin\"]'),
            ('analysis_tab', '[\"admin\"]'),
            ('view_archive_toggle', '[\"admin\"]'),
            
            ('admin_users', '[\"admin\"]'),
            ('admin_access', '[\"admin\"]'),
            ('admin_payroll', '[\"admin\"]'),
            ('admin_holidays', '[\"admin\"]'),
            ('admin_jobs', '[\"admin\"]'),
            ('admin_locations', '[\"admin\"]'),

            ('analysis_view_others', '[\"admin\"]'),
            ('analysis_travel', '[\"admin\"]'),
            ('analysis_financial', '[\"admin\"]'),
            ('analysis_location', '[\"admin\"]'),
            ('analysis_job_breakdown', '[\"admin\"]')
        ")->execute();
    }
}