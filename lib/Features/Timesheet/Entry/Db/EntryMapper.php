<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Timesheet\Entry\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;

class EntryMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'stech_timesheets');
    }

    // --- Dropdowns ---
    public function getActiveJobs(): array {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')->from('stech_jobs')
            ->where($qb->expr()->eq('job_archive', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
            ->orderBy('job_name', 'ASC')->executeQuery()->fetchAll();
    }

    public function getEnabledStates(): array {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')->from('stech_states')
            ->where($qb->expr()->eq('is_enabled', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
            ->orderBy('state_name', 'ASC')->executeQuery()->fetchAll();
    }

    public function getCountiesByState(string $stateAbbr): array {
        $qbState = $this->db->getQueryBuilder();
        $state = $qbState->select('fips_code')->from('stech_states')
                ->where($qbState->expr()->eq('state_abbr', $qbState->createNamedParameter($stateAbbr)))
                ->executeQuery()->fetch();

        if (!$state) return [];

        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')->from('stech_counties')
            ->where($qb->expr()->eq('state_fips', $qb->createNamedParameter($state['fips_code'])))
            ->andWhere($qb->expr()->eq('is_enabled', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
            ->orderBy('county_name', 'ASC')->executeQuery()->fetchAll();
    }

    // --- Single Record Fetch ---
    public function getEntryById(int $id, string $uid): ?array {
        $qb = $this->db->getQueryBuilder();
        $res = $qb->select('*')->from('stech_timesheets')
            ->where($qb->expr()->eq('timesheet_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('userid', $qb->createNamedParameter($uid)))
            ->executeQuery()->fetch();
        return $res ?: null;
    }

    public function getActivities(int $tid): array {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')->from('stech_activity')
            ->where($qb->expr()->eq('timesheet_id', $qb->createNamedParameter($tid, IQueryBuilder::PARAM_INT)))
            ->executeQuery()->fetchAll();
    }

    // --- Writes ---
    public function createEntry(array $values): int {
        $qb = $this->db->getQueryBuilder();
        $qb->insert('stech_timesheets');
        foreach ($values as $col => $val) {
            $qb->setValue($col, $qb->createNamedParameter($val));
        }
        $qb->executeStatement();
        return (int)$this->db->lastInsertId('*PREFIX*stech_timesheets');
    }

    public function updateEntry(int $id, array $values): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_timesheets');
        foreach ($values as $col => $val) {
            $qb->set($col, $qb->createNamedParameter($val));
        }
        $qb->where($qb->expr()->eq('timesheet_id', $qb->createNamedParameter($id)))
           ->executeStatement();
    }

    public function replaceActivities(int $tid, array $activities): void {
        // 1. Delete Old
        $this->db->prepare("DELETE FROM `*PREFIX*stech_activity` WHERE `timesheet_id` = ?")->execute([$tid]);
        
        // 2. Insert New
        if (!empty($activities)) {
            $stmt = $this->db->prepare("INSERT INTO `*PREFIX*stech_activity` (`timesheet_id`, `activity_description`, `activity_percent`) VALUES (?, ?, ?)");
            foreach ($activities as $act) {
                if (!empty($act['desc'])) {
                    $stmt->execute([$tid, $act['desc'], (int)$act['percent']]);
                }
            }
        }
    }

    public function toggleArchive(int $id, string $uid, int $status): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('stech_timesheets')
           ->set('archive', $qb->createNamedParameter($status, IQueryBuilder::PARAM_INT))
           ->where($qb->expr()->eq('timesheet_id', $qb->createNamedParameter($id)))
           ->andWhere($qb->expr()->eq('userid', $qb->createNamedParameter($uid)))
           ->executeStatement();
    }
}