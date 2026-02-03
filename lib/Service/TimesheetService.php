<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Service;

use OCA\StechTimesheet\Db\TimesheetMapper;
use OCP\IDBConnection;

class TimesheetService {
    private $mapper;
    private $db;

    public function __construct(TimesheetMapper $mapper, IDBConnection $db) {
        $this->mapper = $mapper;
        $this->db = $db;
    }

    public function getCalendarEvents(string $userId, string $start, string $end): array {
        $events = [];
        $settings = $this->mapper->getAdminSettings();
        $holidayBgMap = $this->getHolidayBgMap();
        
        // 1. Generate Payroll Markers
        $events = array_merge($events, $this->generatePayrollMarkers($settings, $start, $end));

        // 2. Process Entries
        $results = $this->mapper->findRawEntries($userId, $start, $end);
        $activities = $this->mapper->getActivitiesGrouped(array_column($results, 'timesheet_id'));
        $ptoJobMap = $this->getPtoJobMap();
        $today = date('Y-m-d');

        foreach ($results as $row) {
            $tid = $row['timesheet_id'];
            $totalHours = (float)$row['time_total'];
            $date = $row['timesheet_date'];
            $isClosed = !empty($row['time_out']);
            $comments = $row['additional_comments'] ?? '';

            if (strpos($comments, 'Holiday:') === 0) {
                $events[] = [
                    'id' => $tid, 'title' => 'Holiday', 'start' => $date, 'color' => '#e67e22',
                    'extendedProps' => ['isClosed' => true, 'isVisual' => true, 'customBg' => $holidayBgMap[$date] ?? '']
                ];
                continue;
            }

            $regHours = 0.0; $ptoHours = 0.0;
            $acts = $activities[$tid] ?? [];
            if (empty($acts)) { $regHours = $totalHours; } 
            else {
                foreach ($acts as $act) {
                    $jobName = $act['activity_description'];
                    $percent = (float)$act['activity_percent'];
                    $hours = $totalHours * ($percent / 100);
                    if (isset($ptoJobMap[$jobName]) && $ptoJobMap[$jobName] === 1) { $ptoHours += $hours; } 
                    else { $regHours += $hours; }
                }
            }
            if (strpos($comments, '[PTO]') !== false) { $ptoHours += $regHours; $regHours = 0; }

            if (empty($row['time_in']) && $row['travel_per_diem'] == 1) {
                $events[] = ['id' => $tid, 'title' => 'Per Diem', 'start' => $date, 'color' => '#17a2b8', 'extendedProps' => ['isClosed' => true]];
            } else {
                if ($regHours > 0.01 || !$isClosed) {
                    $color = $isClosed ? '#28a745' : '#ffc107';
                    $title = $isClosed ? round($regHours, 2) . ' hrs' : 'Active';
                    if ($date < $today && !$isClosed) { $color = '#dc3545'; $title = 'Missing Out'; }
                    $events[] = ['id' => $tid, 'title' => $title, 'start' => $date, 'color' => $color, 'extendedProps' => ['isClosed' => $isClosed]];
                }
            }
            if ($ptoHours > 0.01) {
                $events[] = ['id' => $tid, 'title' => 'Vacation ' . round($ptoHours, 2) . ' hrs', 'start' => $date, 'color' => '#9b59b6', 'extendedProps' => ['isClosed' => true]];
            }
        }
        return $events;
    }

    private function getHolidayBgMap(): array {
        $map = [];
        try {
            $qb = $this->db->getQueryBuilder();
            $holidays = $qb->select('holiday_start_date', 'holiday_end_date', 'holiday_bg')->from('stech_holidays')->where($qb->expr()->isNotNull('holiday_bg'))->executeQuery()->fetchAll();
            foreach ($holidays as $h) {
                $curr = new \DateTime($h['holiday_start_date']); $stop = new \DateTime($h['holiday_end_date']);
                while ($curr <= $stop) { $map[$curr->format('Y-m-d')] = $h['holiday_bg']; $curr->modify('+1 day'); }
            }
        } catch (\Exception $e) {}
        return $map;
    }

    private function getPtoJobMap(): array {
        $map = [];
        $rows = $this->db->getQueryBuilder()->select('job_name', 'is_pto')->from('stech_jobs')->executeQuery()->fetchAll();
        foreach ($rows as $j) { $map[$j['job_name']] = (int)$j['is_pto']; }
        return $map;
    }

    private function generatePayrollMarkers(array $settings, string $start, string $end): array {
        $markers = [];
        $payStart = new \DateTime($settings['pay_start_date'] ?? '2026-01-07');
        $freq = (int)($settings['pay_frequency'] ?? 14);
        $payBg = $settings['pay_bg_style'] ?? '';
        $viewStart = new \DateTime($start); $viewEnd = new \DateTime($end);

        $interval = $payStart->diff($viewStart); $daysDiff = (int)$interval->format('%r%a');
        if ($daysDiff >= 0) { $remainder = $daysDiff % $freq; $daysToAdd = ($remainder === 0) ? 0 : ($freq - $remainder); $nextPay = clone $viewStart; $nextPay->modify("+$daysToAdd days"); } 
        else { $nextPay = clone $payStart; while ($nextPay > $viewStart) $nextPay->modify("-$freq days"); while ($nextPay < $viewStart) $nextPay->modify("+$freq days"); }

        while ($nextPay <= $viewEnd) {
            $markers[] = ['id' => 'paid-' . $nextPay->format('Ymd'), 'title' => 'Payroll', 'start' => $nextPay->format('Y-m-d'), 'color' => '#34495e', 'display' => 'block', 'extendedProps' => ['isVisual' => true, 'isClosed' => true, 'customBg' => $payBg]];
            $nextPay->modify("+$freq days");
        }
        return $markers;
    }
}