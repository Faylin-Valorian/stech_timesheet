<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * TimesheetMapper
 * Modular database layer for stech_timesheets and related lookup tables.
 * Restores 100% of original logic with modern DB compatibility patches.
 */
class TimesheetMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'stech_timesheets', Timesheet::class);
    }

    /**
     * Get active jobs using the original 'job_archive' column logic.
     * @return array
     */
    public function getActiveJobs(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from('stech_jobs')
           ->where($qb->expr()->eq('job_archive', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
           ->orderBy('job_name', 'ASC');
        
        return $qb->executeQuery()->fetchAll();
    }

    /**
     * Get enabled states for form initialization.
     * @return array
     */
    public function getEnabledStates(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from('stech_states')
           ->where($qb->expr()->eq('is_enabled', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
           ->orderBy('state_name', 'ASC');
        
        return $qb->executeQuery()->fetchAll();
    }

    /**
     * Get counties based on state fips_code mapping.
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
        $qb->select('*')
           ->from('stech_counties')
           ->where($qb->expr()->eq('state_fips', $qb->createNamedParameter($state['fips_code'])))
           ->andWhere($qb->expr()->eq('is_enabled', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
           ->orderBy('county_name', 'ASC');
        
        return $qb->executeQuery()->fetchAll();
    }

    /**
     * Find raw entries for the calendar between specific dates.
     * @param string $userId
     * @param string $start
     * @param string $end
     * @return array
     */
    public function findRawEntries(string $userId, string $start, string $end): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from('stech_timesheets')
           ->where($qb->expr()->eq('userid', $qb->createNamedParameter($userId)))
           ->andWhere($qb->expr()->gte('timesheet_date', $qb->createNamedParameter($start)))
           ->andWhere($qb->expr()->lte('timesheet_date', $qb->createNamedParameter($end)))
           ->andWhere($qb->expr()->eq('archive', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
        
        return $qb->executeQuery()->fetchAll();
    }

    /**
     * Fetch activities for a single timesheet.
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
     * Fetch activities grouped by timesheet IDs for bulk processing.
     * @param array $ids
     * @return array
     */
    public function getActivitiesGrouped(array $ids): array {
        if (empty($ids)) {
            return [];
        }

        $qbAct = $this->db->getQueryBuilder();
        $acts = $qbAct->select('*')
                  ->from('stech_activity')
                  ->where($qbAct->expr()->in('timesheet_id', $qbAct->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
                  ->executeQuery()
                  ->fetchAll();

        $grouped = [];
        foreach($acts as $a) {
            $grouped[$a['timesheet_id']][] = $a;
        }
        return $grouped;
    }

    /**
     * Retrieve admin settings required by the Service layer.
     * @return array
     */
    public function getAdminSettings(): array {
        try {
            $qb = $this->db->getQueryBuilder();
            $rows = $qb->select('*')
                       ->from('stech_admin_settings')
                       ->executeQuery()
                       ->fetchAll();
            
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            return $settings;
        } catch (\Exception $e) {
            return [];
        }
    }
}