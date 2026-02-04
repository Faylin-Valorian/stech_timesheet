<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class AdminMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'stech_admin_settings');
    }

    /**
     * Settings Management
     */
    public function getSettings(): array {
        return $this->db->getQueryBuilder()
            ->select('*')
            ->from('stech_admin_settings')
            ->executeQuery()
            ->fetchAll();
    }

    public function saveSetting(string $key, string $value): void {
        $qb = $this->db->getQueryBuilder();
        $exists = $qb->select('setting_key')
                     ->from('stech_admin_settings')
                     ->where($qb->expr()->eq('setting_key', $qb->createNamedParameter($key)))
                     ->executeQuery()
                     ->fetch();

        if ($exists) {
            $qb = $this->db->getQueryBuilder();
            $qb->update('stech_admin_settings')
               ->set('setting_value', $qb->createNamedParameter($value))
               ->where($qb->expr()->eq('setting_key', $qb->createNamedParameter($key)))
               ->execute();
        } else {
            $qb = $this->db->getQueryBuilder();
            $qb->insert('stech_admin_settings')
               ->values([
                   'setting_key' => $qb->createNamedParameter($key),
                   'setting_value' => $qb->createNamedParameter($value)
               ])->execute();
        }
    }

    /**
     * Access Control Logic
     */
    public function getAccessRules(): array {
        return $this->db->getQueryBuilder()
            ->select('*')
            ->from('stech_access_rules')
            ->executeQuery()
            ->fetchAll();
    }

    public function saveAccessRule(string $key, string $jsonGroups): void {
        $qb = $this->db->getQueryBuilder();
        $exists = $qb->select('id')
                     ->from('stech_access_rules')
                     ->where($qb->expr()->eq('rule_key', $qb->createNamedParameter($key)))
                     ->executeQuery()
                     ->fetch();

        if ($exists) {
            $qb = $this->db->getQueryBuilder();
            $qb->update('stech_access_rules')
               ->set('allowed_groups', $qb->createNamedParameter($jsonGroups))
               ->where($qb->expr()->eq('rule_key', $qb->createNamedParameter($key)))
               ->execute();
        } else {
            $qb = $this->db->getQueryBuilder();
            $qb->insert('stech_access_rules')
               ->values([
                   'rule_key' => $qb->createNamedParameter($key),
                   'allowed_groups' => $qb->createNamedParameter($jsonGroups)
               ])->execute();
        }
    }

    /**
     * Employee Management Logic
     */
    public function getEmployeeStatusMap(): array {
        try {
            $rows = $this->db->getQueryBuilder()
                ->select('*')
                ->from('stech_employees')
                ->executeQuery()
                ->fetchAll();
            $map = [];
            foreach($rows as $row) {
                $map[$row['uid']] = (int)$row['is_active'];
            }
            return $map;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function toggleUserStatus(string $uid, int $newStatus): void {
        $now = date('Y-m-d H:i:s');
        $qb = $this->db->getQueryBuilder();
        $exists = $qb->select('*')
                     ->from('stech_employees')
                     ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
                     ->executeQuery()
                     ->fetch();

        if ($exists) {
            $qb = $this->db->getQueryBuilder();
            $qb->update('stech_employees')
               ->set('is_active', $qb->createNamedParameter($newStatus))
               ->set('status_changed_at', $qb->createNamedParameter($now))
               ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
               ->execute();
        } else {
            $qb = $this->db->getQueryBuilder();
            $qb->insert('stech_employees')
               ->values([
                   'uid' => $qb->createNamedParameter($uid),
                   'is_active' => $qb->createNamedParameter($newStatus),
                   'status_changed_at' => $qb->createNamedParameter($now)
               ])->execute();
        }
    }

    public function archiveUserHolidayEntries(string $uid): void {
        $sql = "UPDATE `*PREFIX*stech_timesheets` AS t 
                SET t.`archive` = 1 
                WHERE t.`userid` = :uid 
                AND t.`timesheet_date` > :today 
                AND EXISTS (
                    SELECT 1 FROM `*PREFIX*stech_holidays` h 
                    WHERE t.`timesheet_date` BETWEEN h.`holiday_start_date` AND h.`holiday_end_date`
                )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['uid' => $uid, 'today' => date('Y-m-d')]);
    }

    /**
     * Inventory & Location Management
     */
    public function getHolidays(): array {
        return $this->db->getQueryBuilder()
            ->select('*')
            ->from('stech_holidays')
            ->orderBy('holiday_start_date', 'DESC')
            ->executeQuery()
            ->fetchAll();
    }

    public function saveHoliday(array $data): void {
        $qb = $this->db->getQueryBuilder();
        if (!empty($data['id'])) { 
            $qb->update('stech_holidays')
            ->set('holiday_name', $qb->createNamedParameter($data['name']))
            ->set('holiday_start_date', $qb->createNamedParameter($data['start']))
            ->set('holiday_end_date', $qb->createNamedParameter($data['end']))
            ->set('holiday_bg', $qb->createNamedParameter($data['bg_style'] ?? ''))
            ->where($qb->expr()->eq('holiday_id', $qb->createNamedParameter($data['id'])))
            ->execute();
        } else {
            $qb->insert('stech_holidays')
            ->values([
                'holiday_name' => $qb->createNamedParameter($data['name']),
                'holiday_start_date' => $qb->createNamedParameter($data['start']),
                'holiday_end_date' => $qb->createNamedParameter($data['end']),
                'holiday_bg' => $qb->createNamedParameter($data['bg_style'] ?? '')
            ])->execute();
        }
    }

    public function toggleHoliday(int $id): void {
        $qb = $this->db->getQueryBuilder();
        $cursor = $qb->select('holiday_archive')
            ->from('stech_holidays')
            ->where($qb->expr()->eq('holiday_id', $qb->createNamedParameter($id)))
            ->executeQuery();
        $row = $cursor->fetch();
        $cursor->closeCursor();

        if ($row) {
            $newStatus = ((int)$row['holiday_archive'] === 1) ? 0 : 1;
            
            $qbUpdate = $this->db->getQueryBuilder();
            $qbUpdate->update('stech_holidays')
                ->set('holiday_archive', $qbUpdate->createNamedParameter($newStatus))
                ->where($qbUpdate->expr()->eq('holiday_id', $qbUpdate->createNamedParameter($id)))
                ->executeStatement();
        }
    }

    public function getJobs(): array {
        return $this->db->getQueryBuilder()
            ->select('*')
            ->from('stech_jobs')
            ->orderBy('job_name', 'ASC')
            ->executeQuery()
            ->fetchAll();
    }

    /**
     * FIX: Updated to save description and financial fields
     */
    public function saveJob(array $data): void {
        $qb = $this->db->getQueryBuilder();
        
        $fields = [
            'job_name' => $data['job_name'],
            'job_description' => $data['job_description'] ?? '', 
            'is_pto' => (int)$data['is_pto'],
            'job_revenue' => (float)($data['job_revenue'] ?? 0),
            'job_expense_budget' => (float)($data['job_expense_budget'] ?? 0),
            'job_hourly_cost' => (float)($data['job_hourly_cost'] ?? 0)
        ];

        if (!empty($data['job_id'])) {
            $qb->update('stech_jobs');
            foreach ($fields as $col => $val) {
                $qb->set($col, $qb->createNamedParameter($val));
            }
            $qb->where($qb->expr()->eq('job_id', $qb->createNamedParameter($data['job_id'])))
               ->execute();
        } else {
            $qb->insert('stech_jobs');
            foreach ($fields as $col => $val) {
                $qb->setValue($col, $qb->createNamedParameter($val));
            }
            $qb->execute();
        }
    }

    public function toggleJob(int $id): void {
        $qb = $this->db->getQueryBuilder();
        $cursor = $qb->select('job_archive')
            ->from('stech_jobs')
            ->where($qb->expr()->eq('job_id', $qb->createNamedParameter($id)))
            ->executeQuery();
        $row = $cursor->fetch();
        $cursor->closeCursor();

        if ($row) {
            $newStatus = ((int)$row['job_archive'] === 1) ? 0 : 1;
            
            $qbUpdate = $this->db->getQueryBuilder();
            $qbUpdate->update('stech_jobs')
                ->set('job_archive', $qbUpdate->createNamedParameter($newStatus))
                ->where($qbUpdate->expr()->eq('job_id', $qbUpdate->createNamedParameter($id)))
                ->executeStatement();
        }
    }

    public function getStatesAdmin(): array {
        return $this->db->getQueryBuilder()
            ->select('*')
            ->from('stech_states')
            ->orderBy('state_name', 'ASC')
            ->executeQuery()
            ->fetchAll();
    }
    
    public function getStates(): array { return $this->getStatesAdmin(); }

    public function toggleState(int $id): void {
        // 1. Find the fips_code using the correct PK 'id'
        $qb = $this->db->getQueryBuilder();
        $state = $qb->select('is_enabled', 'fips_code')
                    ->from('stech_states')
                    ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
                    ->executeQuery()
                    ->fetch();

        if (!$state) return;

        $newState = ((int)$state['is_enabled'] === 1) ? 0 : 1;
        $fips = $state['fips_code'];

        // 2. Toggle State using PK 'id'
        $qbState = $this->db->getQueryBuilder();
        $qbState->update('stech_states')
                ->set('is_enabled', $qbState->createNamedParameter($newState))
                ->where($qbState->expr()->eq('id', $qbState->createNamedParameter($id)))
                ->execute();

        // 3. Toggle Counties using 'state_fips' (linking column)
        $qbCounty = $this->db->getQueryBuilder();
        $qbCounty->update('stech_counties')
                 ->set('is_enabled', $qbCounty->createNamedParameter($newState))
                 ->where($qbCounty->expr()->eq('state_fips', $qbCounty->createNamedParameter($fips)))
                 ->execute();
    }

    public function toggleCounty(int $id, int $newState): void {
        // PK is 'id'
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_counties')
           ->set('is_enabled', $qb->createNamedParameter($newState))
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
           ->execute();
    }

    public function getCountiesByStateAdmin(string $abbr): array {
        $qb = $this->db->getQueryBuilder();
        
        // 1. Get fips_code from stech_states (using state_abbr)
        $state = $qb->select('fips_code')
           ->from('stech_states')
           ->where($qb->expr()->eq('state_abbr', $qb->createNamedParameter($abbr)))
           ->executeQuery()
           ->fetch();

        if (!$state) return []; // Abbreviation not found

        $fips = $state['fips_code'];

        // 2. Get counties from stech_counties (using state_fips)
        $qb2 = $this->db->getQueryBuilder();
        return $qb2->select('*')
                ->from('stech_counties')
                ->where($qb2->expr()->eq('state_fips', $qb2->createNamedParameter($fips)))
                ->orderBy('county_name', 'ASC')
                ->executeQuery()
                ->fetchAll();
    }

    public function getCountiesByState(string $fips): array {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')
            ->from('stech_counties')
            ->where($qb->expr()->eq('state_fips', $qb->createNamedParameter($fips)))
            ->orderBy('county_name', 'ASC')
            ->executeQuery()
            ->fetchAll();
    }
}