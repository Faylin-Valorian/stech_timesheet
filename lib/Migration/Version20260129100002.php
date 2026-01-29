<?php

declare(strict_types=1);

namespace OCA\StechTimesheet\Migration;

use OCP\IDBConnection;
use OCP\Migration\IMigrationStep;
use OCP\Migration\IOutput;

class Version20260129100002 implements IMigrationStep {

    /** @var IDBConnection */
    private $db;

    public function __construct(IDBConnection $db) {
        $this->db = $db;
    }

    public function name(): string {
        return 'Populates initial data for jobs, states, and counties';
    }

    public function description(): string {
        return 'Inserts default jobs and US state/county data';
    }

    public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
    }

    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
        return $schemaClosure();
    }

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
        
        // 1. Insert Jobs
        // Note: Target table is now *PREFIX*stech_jobs, columns match your spec
        $stmt = $this->db->prepare("
            INSERT INTO `*PREFIX*stech_jobs` (job_name, job_description, job_archive) VALUES
            ('Site Survey', 'Initial site inspection and data gathering', 0),
            ('Installation', 'Hardware or software installation', 0),
            ('Maintenance', 'Routine system maintenance', 0),
            ('Repair', 'Fixing reported issues', 0),
            ('Travel', 'Travel time to/from site', 0),
            ('Office Work', 'Administrative tasks and reporting', 0),
            ('Training', 'Staff training sessions', 0),
            ('Remote Support', 'Phone or remote desktop support', 0)
        ");
        $stmt->execute();

        // 2. Insert States
        // Note: Target table is now *PREFIX*states
        $stmt = $this->db->prepare("
            INSERT INTO `*PREFIX*states` (state_name, state_abbr, fips_code, is_enabled, is_locked) VALUES
            ('Alabama', 'AL', '01', 1, 0),
            ('Alaska', 'AK', '02', 1, 0),
            ('Arizona', 'AZ', '04', 1, 0),
            ('Arkansas', 'AR', '05', 1, 0),
            ('California', 'CA', '06', 1, 0),
            ('Colorado', 'CO', '08', 1, 0),
            ('Connecticut', 'CT', '09', 1, 0),
            ('Delaware', 'DE', '10', 1, 0),
            ('Florida', 'FL', '12', 1, 0),
            ('Georgia', 'GA', '13', 1, 0),
            ('Hawaii', 'HI', '15', 1, 0),
            ('Idaho', 'ID', '16', 1, 0),
            ('Illinois', 'IL', '17', 1, 0),
            ('Indiana', 'IN', '18', 1, 0),
            ('Iowa', 'IA', '19', 1, 0),
            ('Kansas', 'KS', '20', 1, 0),
            ('Kentucky', 'KY', '21', 1, 0),
            ('Louisiana', 'LA', '22', 1, 0),
            ('Maine', 'ME', '23', 1, 0),
            ('Maryland', 'MD', '24', 1, 0),
            ('Massachusetts', 'MA', '25', 1, 0),
            ('Michigan', 'MI', '26', 1, 0),
            ('Minnesota', 'MN', '27', 1, 0),
            ('Mississippi', 'MS', '28', 1, 0),
            ('Missouri', 'MO', '29', 1, 0),
            ('Montana', 'MT', '30', 1, 0),
            ('Nebraska', 'NE', '31', 1, 0),
            ('Nevada', 'NV', '32', 1, 0),
            ('New Hampshire', 'NH', '33', 1, 0),
            ('New Jersey', 'NJ', '34', 1, 0),
            ('New Mexico', 'NM', '35', 1, 0),
            ('New York', 'NY', '36', 1, 0),
            ('North Carolina', 'NC', '37', 1, 0),
            ('North Dakota', 'ND', '38', 1, 0),
            ('Ohio', 'OH', '39', 1, 0),
            ('Oklahoma', 'OK', '40', 1, 0),
            ('Oregon', 'OR', '41', 1, 0),
            ('Pennsylvania', 'PA', '42', 1, 0),
            ('Rhode Island', 'RI', '44', 1, 0),
            ('South Carolina', 'SC', '45', 1, 0),
            ('South Dakota', 'SD', '46', 1, 0),
            ('Tennessee', 'TN', '47', 1, 0),
            ('Texas', 'TX', '48', 1, 0),
            ('Utah', 'UT', '49', 1, 0),
            ('Vermont', 'VT', '50', 1, 0),
            ('Virginia', 'VA', '51', 1, 0),
            ('Washington', 'WA', '53', 1, 0),
            ('West Virginia', 'WV', '54', 1, 0),
            ('Wisconsin', 'WI', '55', 1, 0),
            ('Wyoming', 'WY', '56', 1, 0),
            ('District of Columbia', 'DC', '11', 1, 0)
        ");
        $stmt->execute();

        // 3. Insert Counties
        // Note: Target table is now *PREFIX*counties
        // Using '0' for geo_id and '' for notes as placeholders for the new columns
        $stmt = $this->db->prepare("
            INSERT INTO `*PREFIX*counties` (county_name, state_fips, is_active, is_enabled, is_locked, geo_id, notes) VALUES
            ('Autauga County', '01', 1, 1, 0, '0', ''), ('Baldwin County', '01', 1, 1, 0, '0', ''), ('Barbour County', '01', 1, 1, 0, '0', ''), ('Bibb County', '01', 1, 1, 0, '0', ''), 
            ('Blount County', '01', 1, 1, 0, '0', ''), ('Bullock County', '01', 1, 1, 0, '0', ''), ('Butler County', '01', 1, 1, 0, '0', ''), ('Calhoun County', '01', 1, 1, 0, '0', ''), 
            ('Chambers County', '01', 1, 1, 0, '0', ''), ('Cherokee County', '01', 1, 1, 0, '0', ''), ('Chilton County', '01', 1, 1, 0, '0', ''), ('Choctaw County', '01', 1, 1, 0, '0', ''), 
            ('Clarke County', '01', 1, 1, 0, '0', ''), ('Clay County', '01', 1, 1, 0, '0', ''), ('Cleburne County', '01', 1, 1, 0, '0', ''), ('Coffee County', '01', 1, 1, 0, '0', ''), 
            ('Colbert County', '01', 1, 1, 0, '0', ''), ('Conecuh County', '01', 1, 1, 0, '0', ''), ('Coosa County', '01', 1, 1, 0, '0', ''), ('Covington County', '01', 1, 1, 0, '0', ''), 
            ('Crenshaw County', '01', 1, 1, 0, '0', ''), ('Cullman County', '01', 1, 1, 0, '0', ''), ('Dale County', '01', 1, 1, 0, '0', ''), ('Dallas County', '01', 1, 1, 0, '0', ''), 
            ('DeKalb County', '01', 1, 1, 0, '0', ''), ('Elmore County', '01', 1, 1, 0, '0', ''), ('Escambia County', '01', 1, 1, 0, '0', ''), ('Etowah County', '01', 1, 1, 0, '0', ''), 
            ('Fayette County', '01', 1, 1, 0, '0', ''), ('Franklin County', '01', 1, 1, 0, '0', ''), ('Geneva County', '01', 1, 1, 0, '0', ''), ('Greene County', '01', 1, 1, 0, '0', ''), 
            ('Hale County', '01', 1, 1, 0, '0', ''), ('Henry County', '01', 1, 1, 0, '0', ''), ('Houston County', '01', 1, 1, 0, '0', ''), ('Jackson County', '01', 1, 1, 0, '0', ''), 
            ('Jefferson County', '01', 1, 1, 0, '0', ''), ('Lamar County', '01', 1, 1, 0, '0', ''), ('Lauderdale County', '01', 1, 1, 0, '0', ''), ('Lawrence County', '01', 1, 1, 0, '0', ''), 
            ('Lee County', '01', 1, 1, 0, '0', ''), ('Limestone County', '01', 1, 1, 0, '0', ''), ('Lowndes County', '01', 1, 1, 0, '0', ''), ('Macon County', '01', 1, 1, 0, '0', ''), 
            ('Madison County', '01', 1, 1, 0, '0', ''), ('Marengo County', '01', 1, 1, 0, '0', ''), ('Marion County', '01', 1, 1, 0, '0', ''), ('Marshall County', '01', 1, 1, 0, '0', ''), 
            ('Mobile County', '01', 1, 1, 0, '0', ''), ('Monroe County', '01', 1, 1, 0, '0', ''), ('Montgomery County', '01', 1, 1, 0, '0', ''), ('Morgan County', '01', 1, 1, 0, '0', ''), 
            ('Perry County', '01', 1, 1, 0, '0', ''), ('Pickens County', '01', 1, 1, 0, '0', ''), ('Pike County', '01', 1, 1, 0, '0', ''), ('Randolph County', '01', 1, 1, 0, '0', ''), 
            ('Russell County', '01', 1, 1, 0, '0', ''), ('St. Clair County', '01', 1, 1, 0, '0', ''), ('Shelby County', '01', 1, 1, 0, '0', ''), ('Sumter County', '01', 1, 1, 0, '0', ''), 
            ('Talladega County', '01', 1, 1, 0, '0', ''), ('Tallapoosa County', '01', 1, 1, 0, '0', ''), ('Tuscaloosa County', '01', 1, 1, 0, '0', ''), ('Walker County', '01', 1, 1, 0, '0', ''), 
            ('Washington County', '01', 1, 1, 0, '0', ''), ('Wilcox County', '01', 1, 1, 0, '0', ''), ('Winston County', '01', 1, 1, 0, '0', ''),
            ('Aleutians East Borough', '02', 1, 1, 0, '0', ''), ('Aleutians West Census Area', '02', 1, 1, 0, '0', ''), ('Anchorage Municipality', '02', 1, 1, 0, '0', ''), 
            ('Bethel Census Area', '02', 1, 1, 0, '0', ''), ('Bristol Bay Borough', '02', 1, 1, 0, '0', ''), ('Denali Borough', '02', 1, 1, 0, '0', ''), 
            ('Dillingham Census Area', '02', 1, 1, 0, '0', ''), ('Fairbanks North Star Borough', '02', 1, 1, 0, '0', ''), ('Haines Borough', '02', 1, 1, 0, '0', ''), 
            ('Hoonah-Angoon Census Area', '02', 1, 1, 0, '0', ''), ('Juneau City and Borough', '02', 1, 1, 0, '0', ''), ('Kenai Peninsula Borough', '02', 1, 1, 0, '0', ''), 
            ('Ketchikan Gateway Borough', '02', 1, 1, 0, '0', ''), ('Kodiak Island Borough', '02', 1, 1, 0, '0', ''), ('Kusilvak Census Area', '02', 1, 1, 0, '0', ''), 
            ('Lake and Peninsula Borough', '02', 1, 1, 0, '0', ''), ('Matanuska-Susitna Borough', '02', 1, 1, 0, '0', ''), ('Nome Census Area', '02', 1, 1, 0, '0', ''), 
            ('North Slope Borough', '02', 1, 1, 0, '0', ''), ('Northwest Arctic Borough', '02', 1, 1, 0, '0', ''), ('Petersburg Borough', '02', 1, 1, 0, '0', ''), 
            ('Prince of Wales-Hyder Census Area', '02', 1, 1, 0, '0', ''), ('Sitka City and Borough', '02', 1, 1, 0, '0', ''), ('Skagway Municipality', '02', 1, 1, 0, '0', ''), 
            ('Southeast Fairbanks Census Area', '02', 1, 1, 0, '0', ''), ('Valdez-Cordova Census Area', '02', 1, 1, 0, '0', ''), ('Wrangell City and Borough', '02', 1, 1, 0, '0', ''), 
            ('Yakutat City and Borough', '02', 1, 1, 0, '0', ''), ('Yukon-Koyukuk Census Area', '02', 1, 1, 0, '0', ''),
            ('Apache County', '04', 1, 1, 0, '0', ''), ('Cochise County', '04', 1, 1, 0, '0', ''), ('Coconino County', '04', 1, 1, 0, '0', ''), ('Gila County', '04', 1, 1, 0, '0', ''), 
            ('Graham County', '04', 1, 1, 0, '0', ''), ('Greenlee County', '04', 1, 1, 0, '0', ''), ('La Paz County', '04', 1, 1, 0, '0', ''), ('Maricopa County', '04', 1, 1, 0, '0', ''), 
            ('Mohave County', '04', 1, 1, 0, '0', ''), ('Navajo County', '04', 1, 1, 0, '0', ''), ('Pima County', '04', 1, 1, 0, '0', ''), ('Pinal County', '04', 1, 1, 0, '0', ''), 
            ('Santa Cruz County', '04', 1, 1, 0, '0', ''), ('Yavapai County', '04', 1, 1, 0, '0', ''), ('Yuma County', '04', 1, 1, 0, '0', ''),
            ('Arkansas County', '05', 1, 1, 0, '0', ''), ('Ashley County', '05', 1, 1, 0, '0', ''), ('Baxter County', '05', 1, 1, 0, '0', ''), ('Benton County', '05', 1, 1, 0, '0', ''), 
            ('Boone County', '05', 1, 1, 0, '0', ''), ('Bradley County', '05', 1, 1, 0, '0', ''), ('Calhoun County', '05', 1, 1, 0, '0', ''), ('Carroll County', '05', 1, 1, 0, '0', ''), 
            ('Chicot County', '05', 1, 1, 0, '0', ''), ('Clark County', '05', 1, 1, 0, '0', ''), ('Clay County', '05', 1, 1, 0, '0', ''), ('Cleburne County', '05', 1, 1, 0, '0', ''), 
            ('Cleveland County', '05', 1, 1, 0, '0', ''), ('Columbia County', '05', 1, 1, 0, '0', ''), ('Conway County', '05', 1, 1, 0, '0', ''), ('Craighead County', '05', 1, 1, 0, '0', ''), 
            ('Crawford County', '05', 1, 1, 0, '0', ''), ('Crittenden County', '05', 1, 1, 0, '0', ''), ('Cross County', '05', 1, 1, 0, '0', ''), ('Dallas County', '05', 1, 1, 0, '0', ''), 
            ('Desha County', '05', 1, 1, 0, '0', ''), ('Drew County', '05', 1, 1, 0, '0', ''), ('Faulkner County', '05', 1, 1, 0, '0', ''), ('Franklin County', '05', 1, 1, 0, '0', ''), 
            ('Fulton County', '05', 1, 1, 0, '0', ''), ('Garland County', '05', 1, 1, 0, '0', ''), ('Grant County', '05', 1, 1, 0, '0', ''), ('Greene County', '05', 1, 1, 0, '0', ''), 
            ('Hempstead County', '05', 1, 1, 0, '0', ''), ('Hot Spring County', '05', 1, 1, 0, '0', ''), ('Howard County', '05', 1, 1, 0, '0', ''), ('Independence County', '05', 1, 1, 0, '0', ''), 
            ('Izard County', '05', 1, 1, 0, '0', ''), ('Jackson County', '05', 1, 1, 0, '0', ''), ('Jefferson County', '05', 1, 1, 0, '0', ''), ('Johnson County', '05', 1, 1, 0, '0', ''), 
            ('Lafayette County', '05', 1, 1, 0, '0', ''), ('Lawrence County', '05', 1, 1, 0, '0', ''), ('Lee County', '05', 1, 1, 0, '0', ''), ('Lincoln County', '05', 1, 1, 0, '0', ''), 
            ('Little River County', '05', 1, 1, 0, '0', ''), ('Logan County', '05', 1, 1, 0, '0', ''), ('Lonoke County', '05', 1, 1, 0, '0', ''), ('Madison County', '05', 1, 1, 0, '0', ''), 
            ('Marion County', '05', 1, 1, 0, '0', ''), ('Miller County', '05', 1, 1, 0, '0', ''), ('Mississippi County', '05', 1, 1, 0, '0', ''), ('Monroe County', '05', 1, 1, 0, '0', ''), 
            ('Montgomery County', '05', 1, 1, 0, '0', ''), ('Nevada County', '05', 1, 1, 0, '0', ''), ('Newton County', '05', 1, 1, 0, '0', ''), ('Ouachita County', '05', 1, 1, 0, '0', ''), 
            ('Perry County', '05', 1, 1, 0, '0', ''), ('Phillips County', '05', 1, 1, 0, '0', ''), ('Pike County', '05', 1, 1, 0, '0', ''), ('Poinsett County', '05', 1, 1, 0, '0', ''), 
            ('Polk County', '05', 1, 1, 0, '0', ''), ('Pope County', '05', 1, 1, 0, '0', ''), ('Prairie County', '05', 1, 1, 0, '0', ''), ('Pulaski County', '05', 1, 1, 0, '0', ''), 
            ('Randolph County', '05', 1, 1, 0, '0', ''), ('St. Francis County', '05', 1, 1, 0, '0', ''), ('Saline County', '05', 1, 1, 0, '0', ''), ('Scott County', '05', 1, 1, 0, '0', ''), 
            ('Searcy County', '05', 1, 1, 0, '0', ''), ('Sebastian County', '05', 1, 1, 0, '0', ''), ('Sevier County', '05', 1, 1, 0, '0', ''), ('Sharp County', '05', 1, 1, 0, '0', ''), 
            ('Stone County', '05', 1, 1, 0, '0', ''), ('Union County', '05', 1, 1, 0, '0', ''), ('Van Buren County', '05', 1, 1, 0, '0', ''), ('Washington County', '05', 1, 1, 0, '0', ''), 
            ('White County', '05', 1, 1, 0, '0', ''), ('Woodruff County', '05', 1, 1, 0, '0', ''), ('Yell County', '05', 1, 1, 0, '0', ''),
            ('Alameda County', '06', 1, 1, 0, '0', ''), ('Alpine County', '06', 1, 1, 0, '0', ''), ('Amador County', '06', 1, 1, 0, '0', ''), ('Butte County', '06', 1, 1, 0, '0', ''), 
            ('Calaveras County', '06', 1, 1, 0, '0', ''), ('Colusa County', '06', 1, 1, 0, '0', ''), ('Contra Costa County', '06', 1, 1, 0, '0', ''), ('Del Norte County', '06', 1, 1, 0, '0', ''), 
            ('El Dorado County', '06', 1, 1, 0, '0', ''), ('Fresno County', '06', 1, 1, 0, '0', ''), ('Glenn County', '06', 1, 1, 0, '0', ''), ('Humboldt County', '06', 1, 1, 0, '0', ''), 
            ('Imperial County', '06', 1, 1, 0, '0', ''), ('Inyo County', '06', 1, 1, 0, '0', ''), ('Kern County', '06', 1, 1, 0, '0', ''), ('Kings County', '06', 1, 1, 0, '0', ''), 
            ('Lake County', '06', 1, 1, 0, '0', ''), ('Lassen County', '06', 1, 1, 0, '0', ''), ('Los Angeles County', '06', 1, 1, 0, '0', ''), ('Madera County', '06', 1, 1, 0, '0', ''), 
            ('Marin County', '06', 1, 1, 0, '0', ''), ('Mariposa County', '06', 1, 1, 0, '0', ''), ('Mendocino County', '06', 1, 1, 0, '0', ''), ('Merced County', '06', 1, 1, 0, '0', ''), 
            ('Modoc County', '06', 1, 1, 0, '0', ''), ('Mono County', '06', 1, 1, 0, '0', ''), ('Monterey County', '06', 1, 1, 0, '0', ''), ('Napa County', '06', 1, 1, 0, '0', ''), 
            ('Nevada County', '06', 1, 1, 0, '0', ''), ('Orange County', '06', 1, 1, 0, '0', ''), ('Placer County', '06', 1, 1, 0, '0', ''), ('Plumas County', '06', 1, 1, 0, '0', ''), 
            ('Riverside County', '06', 1, 1, 0, '0', ''), ('Sacramento County', '06', 1, 1, 0, '0', ''), ('San Benito County', '06', 1, 1, 0, '0', ''), ('San Bernardino County', '06', 1, 1, 0, '0', ''), 
            ('San Diego County', '06', 1, 1, 0, '0', ''), ('San Francisco County', '06', 1, 1, 0, '0', ''), ('San Joaquin County', '06', 1, 1, 0, '0', ''), ('San Luis Obispo County', '06', 1, 1, 0, '0', ''), 
            ('San Mateo County', '06', 1, 1, 0, '0', ''), ('Santa Barbara County', '06', 1, 1, 0, '0', ''), ('Santa Clara County', '06', 1, 1, 0, '0', ''), ('Santa Cruz County', '06', 1, 1, 0, '0', ''), 
            ('Shasta County', '06', 1, 1, 0, '0', ''), ('Sierra County', '06', 1, 1, 0, '0', ''), ('Siskiyou County', '06', 1, 1, 0, '0', ''), ('Solano County', '06', 1, 1, 0, '0', ''), 
            ('Sonoma County', '06', 1, 1, 0, '0', ''), ('Stanislaus County', '06', 1, 1, 0, '0', ''), ('Sutter County', '06', 1, 1, 0, '0', ''), ('Tehama County', '06', 1, 1, 0, '0', ''), 
            ('Trinity County', '06', 1, 1, 0, '0', ''), ('Tulare County', '06', 1, 1, 0, '0', ''), ('Tuolumne County', '06', 1, 1, 0, '0', ''), ('Ventura County', '06', 1, 1, 0, '0', ''), 
            ('Yolo County', '06', 1, 1, 0, '0', ''), ('Yuba County', '06', 1, 1, 0, '0', '')
        ");
        $stmt->execute();
    }
}