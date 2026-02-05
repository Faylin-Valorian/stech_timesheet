<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class AnalysisMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'stech_timesheets');
    }

    /**
     * Fetches raw timesheet data joined with Jobs and States.
     * If $userId is null, it fetches data for ALL users (Everyone).
     */
    public function getFullReportingData(\DateTime $start, \DateTime $end, ?string $userId = null): array {
        $qb = $this->db->getQueryBuilder();
        
        $qb->select('t.*', 
                    'a.activity_description', 'a.activity_percent', 
                    // Join Fields
                    'j.job_name', 'j.is_pto', 
                    // CRITICAL: Financial Columns from Job Table
                    'j.job_revenue', 
                    'j.job_hourly_cost', 
                    'j.job_expense_budget',
                    // Location Name
                    's.state_name as full_state_name')
           ->from('stech_timesheets', 't')
           // Join Activities to get Job Names/Percent
           ->leftJoin('t', 'stech_activity', 'a', 't.timesheet_id = a.timesheet_id')
           // CRITICAL: Join Jobs to get Revenue/Cost rates (Match on Name)
           ->leftJoin('a', 'stech_jobs', 'j', 'a.activity_description = j.job_name')
           // Join States for pretty names
           ->leftJoin('t', 'stech_states', 's', 't.travel_state = s.state_abbr')
           
           // Time Range Filter
           ->where($qb->expr()->gte('t.timesheet_date', $qb->createNamedParameter($start->format('Y-m-d'))))
           ->andWhere($qb->expr()->lte('t.timesheet_date', $qb->createNamedParameter($end->format('Y-m-d'))))
           // Only Active Records
           ->andWhere($qb->expr()->eq('t.archive', $qb->createNamedParameter(0)));

        // Handle "Everyone" vs Specific User
        if ($userId !== null) {
            $qb->andWhere($qb->expr()->eq('t.userid', $qb->createNamedParameter($userId)));
        }

        return $qb->executeQuery()->fetchAll();
    }
}