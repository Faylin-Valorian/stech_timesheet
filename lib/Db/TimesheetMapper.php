<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class TimesheetMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'stech_timesheets', Timesheet::class);
    }

    /**
     * Logic for TimesheetController::getTimesheets
     */
    public function findUserEntries(string $userId, string $start, string $end): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from('stech_timesheets')
           ->where($qb->expr()->eq('userid', $qb->createNamedParameter($userId)))
           ->andWhere($qb->expr()->gte('timesheet_date', $qb->createNamedParameter($start)))
           ->andWhere($qb->expr()->lte('timesheet_date', $qb->createNamedParameter($end)))
           ->andWhere($qb->expr()->eq('archive', $qb->createNamedParameter(0)));
        
        return $this->findEntities($qb);
    }

    /**
     * Fetches current settings for the service layer
     */
    public function getAdminSettings(): array {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')->from('stech_admin_settings')->executeQuery()->fetchAll();
    }
}