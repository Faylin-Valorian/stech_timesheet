<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class AdminMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        // Updated constructor to target the core settings table as primary
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

    public function saveSettingValue(string $key, string $value): void {
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

    /**
     * Restores SQL logic for archiving entries on holiday dates for disabled users
     */
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

    public function getJobs(): array {
        return $this->db->getQueryBuilder()
            ->select('*')
            ->from('stech_jobs')
            ->orderBy('job_name', 'ASC')
            ->executeQuery()
            ->fetchAll();
    }

    public function getStates(): array {
        return $this->db->getQueryBuilder()
            ->select('*')
            ->from('stech_states')
            ->orderBy('state_name', 'ASC')
            ->executeQuery()
            ->fetchAll();
    }

    public function toggleStateAndCounties(int $id, int $newState, string $fips): void {
        // Toggle State
        $qbState = $this->db->getQueryBuilder();
        $qbState->update('stech_states')
                ->set('is_enabled', $qbState->createNamedParameter($newState))
                ->where($qbState->expr()->eq('id', $qbState->createNamedParameter($id)))
                ->execute();

        // Toggle all associated Counties
        $qbCounty = $this->db->getQueryBuilder();
        $qbCounty->update('stech_counties')
                 ->set('is_enabled', $qbCounty->createNamedParameter($newState))
                 ->where($qbCounty->expr()->eq('state_fips', $qbCounty->createNamedParameter($fips)))
                 ->execute();
    }

    /**
     * Fetch counties for a specific state using FIPS code
     */
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