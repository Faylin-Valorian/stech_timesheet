<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class AnalysisMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        // This mapper primarily targets the timesheets table for reporting
        parent::__construct($db, 'stech_timesheets');
    }

    /**
     * Replaces the complex query originally in AnalysisController::getStats
     */
    public function getFullReportingData(string $start, string $end, ?string $uid): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('t.*', 
                    'a.activity_description', 'a.activity_percent', 
                    'j.job_id', 'j.job_name', 'j.job_revenue', 'j.job_expense_budget', 'j.job_hourly_cost', 'j.is_pto',
                    'st.state_name as full_state_name')
           ->from('stech_timesheets', 't')
           ->leftJoin('t', 'stech_activity', 'a', 't.timesheet_id = a.timesheet_id')
           ->leftJoin('a', 'stech_jobs', 'j', 'a.activity_description = j.job_name')
           ->leftJoin('t', 'stech_states', 'st', 't.travel_state = st.state_abbr')
           ->where($qb->expr()->gte('t.timesheet_date', $qb->createNamedParameter($start)))
           ->andWhere($qb->expr()->lte('t.timesheet_date', $qb->createNamedParameter($end)));

        if ($uid !== null) {
            $qb->andWhere($qb->expr()->eq('t.userid', $qb->createNamedParameter($uid)));
        }

        return $qb->executeQuery()->fetchAll();
    }
}