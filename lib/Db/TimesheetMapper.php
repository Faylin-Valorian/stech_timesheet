<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;

class TimesheetMapper extends QBMapper {
    /**
     * @param IDBConnection $db
     */
    public function __construct(IDBConnection $db) {
        // Link the mapper to the correct table and Entity class
        parent::__construct($db, 'stech_timesheets', Timesheet::class);
    }

    /**
     * Fetch active jobs for dropdowns
     * @return array
     */
    public function getActiveJobs(): array {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')
           ->from('stech_jobs')
           ->where($qb->expr()->eq('job_archive', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
           ->orderBy('job_name', 'ASC')
           ->executeQuery()
           ->fetchAll();
    }

    /**
     * Fetch enabled states for dropdowns
     * @return array
     */
    public function getEnabledStates(): array {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')
           ->from('stech_states')
           ->where($qb->expr()->eq('is_enabled', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
           ->orderBy('state_name', 'ASC')
           ->executeQuery()
           ->fetchAll();
    }

    /**
     * Fetch counties filtered by state abbreviation
     * @param string $stateAbbr
     * @return array
     */
    public function getCountiesByState(string $stateAbbr): array {
        $qbState = $this->db->getQueryBuilder();
        $state = $qbState->select('fips_code')
                ->from('stech_states')
                ->where($qbState->expr()->eq('state_abbr', $qbState->createNamedParameter($stateAbbr)))
                ->executeQuery()
                ->fetch();

        if (!$state) {
            return [];
        }

        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')
           ->from('stech_counties')
           ->where($qb->expr()->eq('state_fips', $qb->createNamedParameter($state['fips_code'])))
           ->andWhere($qb->expr()->eq('is_enabled', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
           ->orderBy('county_name', 'ASC')
           ->executeQuery()
           ->fetchAll();
    }

    /**
     * Find a specific timesheet by ID and User
     * @param int $id
     * @param string $uid
     * @return array|null
     */
    public function getTimesheetById(int $id, string $uid): ?array {
        $qb = $this->db->getQueryBuilder();
        $res = $qb->select('*')
           ->from('stech_timesheets')
           ->where($qb->expr()->eq('timesheet_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
           ->andWhere($qb->expr()->eq('userid', $qb->createNamedParameter($uid)))
           ->executeQuery()
           ->fetch();

        return $res ?: null;
    }

    /**
     * Find raw entries for a date range (used by Service layer)
     * @param string $userId
     * @param string $start
     * @param string $end
     * @return array
     */
    public function findRawEntries(string $userId, string $start, string $end): array {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')
           ->from('stech_timesheets')
           ->where($qb->expr()->eq('userid', $qb->createNamedParameter($userId)))
           ->andWhere($qb->expr()->gte('timesheet_date', $qb->createNamedParameter($start)))
           ->andWhere($qb->expr()->lte('timesheet_date', $qb->createNamedParameter($end)))
           ->andWhere($qb->expr()->eq('archive', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
           ->executeQuery()
           ->fetchAll();
    }

    /**
     * Fetch activities for specific timesheet IDs
     * @param array $ids
     * @return array
     */
    public function getActivitiesGrouped(array $ids): array {
        if (empty($ids)) {
            return [];
        }

        $qb = $this->db->getQueryBuilder();
        $acts = $qb->select('*')
                  ->from('stech_activity')
                  ->where($qb->expr()->in('timesheet_id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
                  ->executeQuery()
                  ->fetchAll();

        $grouped = [];
        foreach($acts as $a) {
            $grouped[$a['timesheet_id']][] = $a;
        }
        return $grouped;
    }

    /**
     * Get activities for a single timesheet ID
     * @param int $id
     * @return array
     */
    public function getActivitiesByTimesheet(int $id): array {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')
           ->from('stech_activity')
           ->where($qb->expr()->eq('timesheet_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
           ->executeQuery()
           ->fetchAll();
    }

    /**
     * Get Administrative settings
     * @return array
     */
    public function getAdminSettings(): array {
        $settings = [];
        try {
            $qb = $this->db->getQueryBuilder();
            $rows = $qb->select('*')
                       ->from('stech_admin_settings')
                       ->executeQuery()
                       ->fetchAll();

            foreach ($rows as $r) {
                $settings[$r['setting_key']] = $r['setting_value'];
            }
        } catch (\Exception $e) {
            // Log error if necessary
        }
        return $settings;
    }
}