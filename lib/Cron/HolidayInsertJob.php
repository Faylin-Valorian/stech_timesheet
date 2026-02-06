<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Cron;

use OCP\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IConfig;

class HolidayInsertJob extends TimedJob {
    private $db;
    private $config;

    public function __construct(ITimeFactory $time, IDBConnection $db, IConfig $config) {
        parent::__construct($time);
        $this->db = $db;
        $this->config = $config;
        
        // Run every hour (3600s) to check for the day change.
        // We use a config flag to ensure it only actually inserts once per day.
        $this->setInterval(3600); 
    }

    protected function run($argument): void {
        $today = date('Y-m-d');
        
        // 1. Check if Today is a Holiday
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from('stech_holidays')
           ->where($qb->expr()->lte('holiday_start_date', $qb->createNamedParameter($today)))
           ->andWhere($qb->expr()->gte('holiday_end_date', $qb->createNamedParameter($today)))
           ->andWhere($qb->expr()->eq('holiday_archive', $qb->createNamedParameter(0))); // Only active
        
        $holiday = $qb->executeQuery()->fetch();

        if (!$holiday) {
            return; // Not a holiday, stop here.
        }

        // 2. Prevention: Check if we already ran successfully for this specific date
        $lastRun = $this->config->getAppValue('stech_timesheet', 'last_holiday_run_date', '');
        if ($lastRun === $today) {
            return; // Already processed today
        }

        // 3. Get All Active Users
        // We query the app's local user table to respect the 'Active' flag
        $qbUsers = $this->db->getQueryBuilder();
        $qbUsers->select('uid')
                ->from('stech_users')
                ->where($qbUsers->expr()->eq('is_active', $qbUsers->createNamedParameter(1)));
        
        $users = $qbUsers->executeQuery()->fetchAll();

        // 4. Insert Timesheet for Each Active User
        foreach ($users as $user) {
            $uid = $user['uid'];

            // Safety: Check if user already has ANY entry for today (Manual override protection)
            $check = $this->db->getQueryBuilder();
            $check->select('timesheet_id')
                  ->from('stech_timesheet')
                  ->where($check->expr()->eq('user_id', $check->createNamedParameter($uid)))
                  ->andWhere($check->expr()->eq('timesheet_date', $check->createNamedParameter($today)))
                  ->setMaxResults(1);
            
            if ($check->executeQuery()->fetch()) {
                continue; // User already has data, skip
            }

            // Insert 8am - 5pm, 60m break (8.0 Hours)
            $insert = $this->db->getQueryBuilder();
            $insert->insert('stech_timesheet')
                   ->values([
                       'user_id' => $insert->createNamedParameter($uid),
                       'timesheet_date' => $insert->createNamedParameter($today),
                       'time_in' => $insert->createNamedParameter('08:00:00'),
                       'time_out' => $insert->createNamedParameter('17:00:00'),
                       'break_minutes' => $insert->createNamedParameter(60),
                       'time_total' => $insert->createNamedParameter(8.0),
                       'is_submitted' => $insert->createNamedParameter(1), // Auto-submit
                       'submission_status' => $insert->createNamedParameter('approved'), // Auto-approve
                       'notes' => $insert->createNamedParameter('Holiday: ' . $holiday['holiday_name'])
                   ])
                   ->execute();
            
            $tsId = $insert->getLastInsertId();

            // Insert Activity Record
            $actInsert = $this->db->getQueryBuilder();
            $actInsert->insert('stech_activity')
                      ->values([
                          'timesheet_id' => $actInsert->createNamedParameter($tsId),
                          'activity_description' => $actInsert->createNamedParameter($holiday['holiday_name']),
                          'activity_percent' => $actInsert->createNamedParameter(100),
                          'is_pto' => $actInsert->createNamedParameter(1) // Mark as PTO/Holiday
                      ])
                      ->execute();
        }

        // 5. Mark as Done for Today
        $this->config->setAppValue('stech_timesheet', 'last_holiday_run_date', $today);
    }
}