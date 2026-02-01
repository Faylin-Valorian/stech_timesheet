<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IDBConnection;

class HolidayInsertJob extends TimedJob {
    private $db;

    public function __construct(ITimeFactory $time, IDBConnection $db) {
        parent::__construct($time);
        $this->db = $db;
        // Run once a day (intervals are in seconds)
        $this->setInterval(24 * 60 * 60);
    }

    protected function run($argument) {
        // 1. Calculate Target Date (Today + 7 Days)
        $targetDate = (new \DateTime())->modify('+7 days')->format('Y-m-d');

        // 2. Check if this target date is a Holiday
        $qbH = $this->db->getQueryBuilder();
        $holiday = $qbH->select('*')
                       ->from('stech_holidays')
                       ->where($qbH->expr()->lte('holiday_start_date', $qbH->createNamedParameter($targetDate)))
                       ->andWhere($qbH->expr()->gte('holiday_end_date', $qbH->createNamedParameter($targetDate)))
                       ->andWhere($qbH->expr()->eq('holiday_archive', $qbH->createNamedParameter(0))) // Only active holidays
                       ->setMaxResults(1)
                       ->executeQuery()
                       ->fetch();

        if (!$holiday) {
            return; // No holiday 7 days from now
        }

        // 3. Get all Active Users
        $qbU = $this->db->getQueryBuilder();
        $activeUsers = $qbU->select('uid')
                           ->from('stech_employees')
                           ->where($qbU->expr()->eq('is_active', $qbU->createNamedParameter(1)))
                           ->executeQuery()
                           ->fetchAll();

        if (empty($activeUsers)) {
            return;
        }

        // 4. Insert Timesheet for each Active User
        foreach ($activeUsers as $user) {
            $uid = $user['uid'];

            // Check if record already exists to prevent duplicates
            $qbCheck = $this->db->getQueryBuilder();
            $exists = $qbCheck->select('timesheet_id')
                              ->from('stech_timesheets')
                              ->where($qbCheck->expr()->eq('userid', $qbCheck->createNamedParameter($uid)))
                              ->andWhere($qbCheck->expr()->eq('timesheet_date', $qbCheck->createNamedParameter($targetDate)))
                              ->executeQuery()
                              ->fetch();

            if (!$exists) {
                $qbInsert = $this->db->getQueryBuilder();
                $qbInsert->insert('stech_timesheets')
                         ->values([
                             'userid' => $qbInsert->createNamedParameter($uid),
                             'timesheet_date' => $qbInsert->createNamedParameter($targetDate),
                             'time_in' => $qbInsert->createNamedParameter('08:00:00'),
                             'time_out' => $qbInsert->createNamedParameter('17:00:00'),
                             'time_break' => $qbInsert->createNamedParameter(60), // 60 min break
                             'time_total' => $qbInsert->createNamedParameter(8.00), // 8 hours total
                             'travel' => $qbInsert->createNamedParameter(0),
                             'additional_comments' => $qbInsert->createNamedParameter('Holiday: ' . $holiday['holiday_name']),
                             'archive' => $qbInsert->createNamedParameter(0)
                         ])
                         ->execute();
                
                // Note: We intentionally DO NOT insert into 'stech_activity' 
                // per the requirement: "enters it as a record with no job description"
            }
        }
    }
}