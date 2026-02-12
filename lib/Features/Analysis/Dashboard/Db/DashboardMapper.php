<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Analysis\Dashboard\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class DashboardMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        // We bind to the main timesheet table
        parent::__construct($db, 'stech_timesheets');
    }

    /**
     * Fetches raw timesheet data joined with Jobs and States.
     * This query powers ALL analysis tabs.
     */
    public function getFullReportingData(\DateTime $start, \DateTime $end, ?string $userId = null): array {
        $qb = $this->db->getQueryBuilder();
        
        $qb->select('t.*', 
                    'a.activity_description', 'a.activity_percent', 
                    // Join Fields
                    'j.job_name', 'j.is_pto', 
                    'j.job_revenue', 
                    'j.job_hourly_cost', 
                    'j.job_expense_budget',
                    // Location Name
                    's.state_name as full_state_name')
           ->from('stech_timesheets', 't')
           // Join Activities
           ->leftJoin('t', 'stech_activity', 'a', 't.timesheet_id = a.timesheet_id')
           // Join Jobs (Financial Data)
           ->leftJoin('a', 'stech_jobs', 'j', 'a.activity_description = j.job_name')
           // Join States (Location Data)
           ->leftJoin('t', 'stech_states', 's', 't.travel_state = s.state_abbr')
           
           // Filters
           ->where($qb->expr()->gte('t.timesheet_date', $qb->createNamedParameter($start->format('Y-m-d'))))
           ->andWhere($qb->expr()->lte('t.timesheet_date', $qb->createNamedParameter($end->format('Y-m-d'))))
           ->andWhere($qb->expr()->eq('t.archive', $qb->createNamedParameter(0)));

        if ($userId !== null) {
            $qb->andWhere($qb->expr()->eq('t.userid', $qb->createNamedParameter($userId)));
        }

        return $qb->executeQuery()->fetchAll();
    }
}