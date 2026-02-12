<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Timesheet\Calendar\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;

class CalendarMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'stech_timesheets');
    }

    public function findRawEntries(string $userId, string $start, string $end, int $archive = 0): array {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')
            ->from('stech_timesheets')
            ->where($qb->expr()->eq('userid', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->gte('timesheet_date', $qb->createNamedParameter($start)))
            ->andWhere($qb->expr()->lte('timesheet_date', $qb->createNamedParameter($end)))
            ->andWhere($qb->expr()->eq('archive', $qb->createNamedParameter($archive, IQueryBuilder::PARAM_INT)))
            ->executeQuery()
            ->fetchAll();
    }

    public function getActivitiesGrouped(array $ids): array {
        if (empty($ids)) return [];

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

    public function getHolidaysForCalendar($start, $end): array {
        $qb = $this->db->getQueryBuilder();
        $query = $qb->select('*')
            ->from('stech_holidays')
            ->where($qb->expr()->eq('holiday_archive', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
            
        if ($start && $end) {
             $query->andWhere($qb->expr()->lte('holiday_start_date', $qb->createNamedParameter($end)))
                   ->andWhere($qb->expr()->gte('holiday_end_date', $qb->createNamedParameter($start)));
        }
        return $query->executeQuery()->fetchAll();
    }

    public function getAdminSettings(): array {
        $settings = [];
        try {
            $qb = $this->db->getQueryBuilder();
            $rows = $qb->select('*')->from('stech_admin_settings')->executeQuery()->fetchAll();
            foreach ($rows as $r) { $settings[$r['setting_key']] = $r['setting_value']; }
        } catch (\Exception $e) {}
        
        if (empty($settings)) {
            return ['pay_frequency' => 14, 'pay_start_date' => '2026-01-07', 'pay_color' => '#34495e'];
        }
        return $settings;
    }

    // Used for PTO calculations in the service
    public function getPtoJobMap(): array {
        $map = [];
        $rows = $this->db->getQueryBuilder()->select('job_name', 'is_pto')->from('stech_jobs')->executeQuery()->fetchAll();
        foreach ($rows as $j) { $map[$j['job_name']] = (int)$j['is_pto']; }
        return $map;
    }

    public function getRawHolidayColor($date): ?string {
        try {
            $qb = $this->db->getQueryBuilder();
            $h = $qb->select('holiday_bg')
                    ->from('stech_holidays')
                    ->where($qb->expr()->lte('holiday_start_date', $qb->createNamedParameter($date)))
                    ->andWhere($qb->expr()->gte('holiday_end_date', $qb->createNamedParameter($date)))
                    ->setMaxResults(1)
                    ->executeQuery()
                    ->fetch();
            return $h ? ($h['holiday_bg'] ?: '#e67e22') : null;
        } catch(\Exception $e) { return null; }
    }
}