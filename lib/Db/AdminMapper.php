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

    /**
     * FIX: Added missing method
     */
    public function toggleHoliday(int $id): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_holidays')
           ->set('is_active', '1 - is_active', false)
           ->where($qb->expr()->eq('holiday_id', $qb->createNamedParameter($id)))
           ->executeStatement();
    }

    public function getJobs(): array {
        return $this->db->getQueryBuilder()
            ->select('*')
            ->from('stech_jobs')
            ->orderBy('job_name', 'ASC')
            ->executeQuery()
            ->fetchAll();
    }

    public function saveJob(array $data): void {
        $qb = $this->db->getQueryBuilder();
        if (!empty($data['job_id'])) {
            $qb->update('stech_jobs')
                ->set('job_name', $qb->createNamedParameter($data['job_name']))
                ->set('is_pto', $qb->createNamedParameter($data['is_pto'] ?? 0))
                ->set('job_revenue', $qb->createNamedParameter($data['job_revenue'] ?? 0))
                ->set('job_expense_budget', $qb->createNamedParameter($data['job_expense_budget'] ?? 0))
                ->set('job_hourly_cost', $qb->createNamedParameter($data['job_hourly_cost'] ?? 0))
                ->where($qb->expr()->eq('job_id', $qb->createNamedParameter($data['job_id'])))
                ->execute();
        } else {
            $qb->insert('stech_jobs')
                ->values([
                    'job_name' => $qb->createNamedParameter($data['job_name']),
                    'is_pto' => $qb->createNamedParameter($data['is_pto'] ?? 0),
                    'job_revenue' => $qb->createNamedParameter($data['job_revenue'] ?? 0),
                    'job_expense_budget' => $qb->createNamedParameter($data['job_expense_budget'] ?? 0),
                    'job_hourly_cost' => $qb->createNamedParameter($data['job_hourly_cost'] ?? 0)
                ])->execute();
        }
    }

    /**
     * FIX: Added missing method
     */
    public function toggleJob(int $id): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_jobs')
           ->set('is_active', '1 - is_active', false)
           ->where($qb->expr()->eq('job_id', $qb->createNamedParameter($id)))
           ->executeStatement();
    }

    /**
     * FIX: Renamed to match controller call getStatesAdmin
     */
    public function getStatesAdmin(): array {
        return $this->db->getQueryBuilder()
            ->select('*')
            ->from('stech_states')
            ->orderBy('state_name', 'ASC')
            ->executeQuery()
            ->fetchAll();
    }
    
    // Kept for backward compatibility if needed
    public function getStates(): array {
        return $this->getStatesAdmin();
    }

    /**
     * FIX: Renamed/Aliased toggleStateAndCounties to toggleState to match controller
     */
    public function toggleState(int $id): void {
        // Find FIPS code first
        $qb = $this->db->getQueryBuilder();
        $state = $qb->select('*')
                    ->from('stech_states')
                    ->where($qb->expr()->eq('state_id', $qb->createNamedParameter($id)))
                    ->executeQuery()
                    ->fetch();

        if (!$state) return;

        $newState = ((int)$state['is_enabled'] === 1) ? 0 : 1;
        $fips = $state['state_fips']; // Ensure column name matches your DB schema

        // Toggle State
        $qbState = $this->db->getQueryBuilder();
        $qbState->update('stech_states')
                ->set('is_enabled', $qbState->createNamedParameter($newState))
                ->where($qbState->expr()->eq('state_id', $qbState->createNamedParameter($id)))
                ->execute();

        // Toggle all associated Counties
        $qbCounty = $this->db->getQueryBuilder();
        $qbCounty->update('stech_counties')
                 ->set('is_enabled', $qbCounty->createNamedParameter($newState))
                 ->where($qbCounty->expr()->eq('state_fips', $qbCounty->createNamedParameter($fips)))
                 ->execute();
    }

    public function toggleCounty(int $id, int $newState): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_counties')
           ->set('is_enabled', $qb->createNamedParameter($newState))
           ->where($qb->expr()->eq('county_id', $qb->createNamedParameter($id)))
           ->execute();
    }

    /**
     * FIX: Renamed/Aliased to getCountiesByStateAdmin to match controller
     */
    public function getCountiesByStateAdmin(string $abbr): array {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')
                ->from('stech_counties')
                ->where($qb->expr()->eq('state_abbr', $qb->createNamedParameter($abbr)))
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