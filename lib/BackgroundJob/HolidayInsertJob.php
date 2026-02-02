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
        // Run periodically (e.g., every hour/day depending on NC cron settings)
        $this->setInterval(60 * 60); 
    }

    protected function run($argument) {
        // 1. Define Range: Today to +7 Days
        // This ensures we catch holidays even if added last minute
        $startRange = (new \DateTime())->format('Y-m-d');
        $endRange = (new \DateTime())->modify('+7 days')->format('Y-m-d');

        // 2. Find ALL Active Holidays in this range
        $qbH = $this->db->getQueryBuilder();
        $holidays = $qbH->select('*')
                        ->from('stech_holidays')
                        ->where($qbH->expr()->lte('holiday_start_date', $qbH->createNamedParameter($endRange)))
                        ->andWhere($qbH->expr()->gte('holiday_end_date', $qbH->createNamedParameter($startRange)))
                        ->andWhere($qbH->expr()->eq('holiday_archive', $qbH->createNamedParameter(0)))
                        ->executeQuery()
                        ->fetchAll();

        if (empty($holidays)) {
            return; 
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

        // 4. Process Each Holiday Found
        foreach ($holidays as $h) {
            // Holidays can span multiple days, iterate through them
            $hStart = new \DateTime($h['holiday_start_date']);
            $hEnd = new \DateTime($h['holiday_end_date']);
            
            // Limit loop to the 7-day window to prevent back-filling old dates unnecessarily
            // (Only fill future/current dates within the window)
            $windowStart = new \DateTime($startRange);
            $windowEnd = new \DateTime($endRange);

            // Iterate through every day of the holiday
            while ($hStart <= $hEnd) {
                // Only insert if the specific holiday date falls within our "Upcoming" window
                if ($hStart >= $windowStart && $hStart <= $windowEnd) {
                    $dateStr = $hStart->format('Y-m-d');
                    $this->processDateForUsers($dateStr, $h['holiday_name'], $activeUsers);
                }
                $hStart->modify('+1 day');
            }
        }
    }

    private function processDateForUsers($dateStr, $holidayName, $users) {
        foreach ($users as $user) {
            $uid = $user['uid'];

            // Check if record already exists for this specific day
            $qbCheck = $this->db->getQueryBuilder();
            $exists = $qbCheck->select('timesheet_id')
                              ->from('stech_timesheets')
                              ->where($qbCheck->expr()->eq('userid', $qbCheck->createNamedParameter($uid)))
                              ->andWhere($qbCheck->expr()->eq('timesheet_date', $qbCheck->createNamedParameter($dateStr)))
                              ->executeQuery()
                              ->fetch();

            if (!$exists) {
                $qbInsert = $this->db->getQueryBuilder();
                $qbInsert->insert('stech_timesheets')
                         ->values([
                             'userid' => $qbInsert->createNamedParameter($uid),
                             'timesheet_date' => $qbInsert->createNamedParameter($dateStr),
                             'time_in' => $qbInsert->createNamedParameter('08:00:00'),
                             'time_out' => $qbInsert->createNamedParameter('17:00:00'),
                             'time_break' => $qbInsert->createNamedParameter(60),
                             'time_total' => $qbInsert->createNamedParameter(8.00),
                             'travel' => $qbInsert->createNamedParameter(0),
                             // Mark comment so Controller recognizes it as a System Holiday
                             'additional_comments' => $qbInsert->createNamedParameter('Holiday: ' . $holidayName),
                             'archive' => $qbInsert->createNamedParameter(0)
                         ])
                         ->execute();
            }
        }
    }
}