<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Service;

use OCA\StechTimesheet\Db\TimesheetMapper;
use OCP\IDBConnection;

class TimesheetService {
    /** @var TimesheetMapper */
    private $mapper;

    /** @var IDBConnection */
    private $db;

    public function __construct(TimesheetMapper $mapper, IDBConnection $db) {
        $this->mapper = $mapper;
        $this->db = $db;
    }

    public function getCalendarEvents(string $userId, string $start, string $end, int $archive = 0): array {
        $events = [];
        $settings = $this->mapper->getAdminSettings();
        
        // 1. Only generate Payroll Markers if we are looking at ACTIVE records
        if ($archive === 0) {
            $events = array_merge($events, $this->generatePayrollMarkers($settings, $start, $end));
        }

        // 2. Fetch raw entries using the Mapper (passing the archive flag)
        $results = $this->mapper->findRawEntries($userId, $start, $end, $archive);
        
        $ids = array_column($results, 'timesheet_id');
        $activities = $this->mapper->getActivitiesGrouped($ids);
        $ptoJobMap = $this->getPtoJobMap();
        $holidayBgMap = $this->getHolidayBgMap();
        $today = date('Y-m-d');

        foreach ($results as $row) {
            $tid = $row['timesheet_id'];
            $totalHours = (float)$row['time_total'];
            $date = $row['timesheet_date'];
            $isClosed = !empty($row['time_out']);
            $comments = $row['additional_comments'] ?? '';

            // Handle Archived Styling
            if ($archive === 1) {
                $title = $isClosed ? $totalHours . 'h (Archived)' : 'Incomplete (Archived)';
                $events[] = [
                    'id' => $tid, 'title' => $title, 'start' => $date, 'color' => '#777777', 
                    'extendedProps' => ['isClosed' => true, 'archive' => 1]
                ];
                continue; 
            }

            // Restore Holiday style logic
            if (strpos($comments, 'Holiday:') === 0) {
                // PATCH: Use DB Color for the Card itself (solid)
                $rawHex = $this->getRawHolidayColor($date) ?? '#e67e22';

                $events[] = [
                    'id' => $tid, 
                    'title' => 'Holiday', 
                    'start' => $date, 
                    'color' => $rawHex, // Solid color for the clickable tab
                    'extendedProps' => [
                        'isClosed' => true, 
                        'customBg' => '' 
                    ]
                ];
                continue;
            }

            // ... (Standard Logic Unchanged) ...
            $regHours = 0.0; 
            $ptoHours = 0.0;
            $acts = $activities[$tid] ?? [];

            if (empty($acts)) { $regHours = $totalHours; } else {
                foreach ($acts as $act) {
                    $jobName = $act['activity_description'];
                    $percent = (float)$act['activity_percent'];
                    $hours = $totalHours * ($percent / 100);
                    if (isset($ptoJobMap[$jobName]) && $ptoJobMap[$jobName] === 1) { $ptoHours += $hours; } else { $regHours += $hours; }
                }
            }
            if (strpos($comments, '[PTO]') !== false) { $ptoHours += $regHours; $regHours = 0; }

            if (empty($row['time_in']) && $row['travel_per_diem'] == 1) {
                $events[] = ['id' => $tid, 'title' => 'Per Diem', 'start' => $date, 'color' => '#17a2b8', 'extendedProps' => ['isClosed' => true]];
            } else {
                if ($regHours > 0.01 || !$isClosed || ($totalHours < 0.01 && $ptoHours < 0.01)) {
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

    // Helper to get raw hex for the "Tab" color (Foreground)
    private function getRawHolidayColor($date): ?string {
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

    private function getHolidayBgMap(): array {
        $map = [];
        try {
            $qb = $this->db->getQueryBuilder();
            $holidays = $qb->select('holiday_start_date', 'holiday_end_date', 'holiday_bg')
                           ->from('stech_holidays')
                           ->where($qb->expr()->isNotNull('holiday_bg'))
                           ->executeQuery()
                           ->fetchAll();
            foreach ($holidays as $h) {
                $curr = new \DateTime($h['holiday_start_date']); 
                $stop = new \DateTime($h['holiday_end_date']);
                
                // PATCH: Use DB Color or default Orange
                $hex = $h['holiday_bg'] ?: '#e67e22';
                // PATCH: Convert to RGBA (0.2 opacity) for the Background Overlay
                $rgba = $this->hex2rgba($hex, 0.2);

                while ($curr <= $stop) { 
                    $map[$curr->format('Y-m-d')] = $rgba; 
                    $curr->modify('+1 day'); 
                }
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
        
        // PATCH: Get User Setting for Payroll Color (Default #34495e)
        $hexColor = $settings['pay_color'] ?? '#34495e';
        // PATCH: Convert to RGBA (0.35 opacity)
        $rgba = $this->hex2rgba($hexColor, 0.35);
        $payBg = $settings['pay_bg_style'] ?? ''; // Keep for custom CSS/Images if used

        $viewStart = new \DateTime($start); 
        $viewEnd = new \DateTime($end);

        $interval = $payStart->diff($viewStart); 
        $daysDiff = (int)$interval->format('%r%a');

        if ($daysDiff >= 0) { 
            $remainder = $daysDiff % $freq; 
            $daysToAdd = ($remainder === 0) ? 0 : ($freq - $remainder); 
            $nextPay = clone $viewStart; 
            $nextPay->modify("+$daysToAdd days"); 
        } else { 
            $nextPay = clone $payStart; 
            $whileSafe = 0;
            while ($nextPay > $viewStart && $whileSafe < 1000) { $nextPay->modify("-$freq days"); $whileSafe++; }
            while ($nextPay < $viewStart && $whileSafe < 1000) { $nextPay->modify("+$freq days"); $whileSafe++; }
        }

        $whileSafe = 0;
        while ($nextPay <= $viewEnd && $whileSafe < 1000) {
            $markers[] = [
                'id' => 'paid-' . $nextPay->format('Ymd'), 
                'title' => 'Payroll', 
                'start' => $nextPay->format('Y-m-d'), 
                'display' => 'background',
                // PATCH: Use Dynamic Color
                'backgroundColor' => $rgba, 
                'extendedProps' => ['isVisual' => true, 'customBg' => $payBg]
            ];
            $nextPay->modify("+$freq days");
            $whileSafe++;
        }
        return $markers;
    }

    // PATCH: Hex to RGBA Helper
    private function hex2rgba($color, $opacity = false) {
        $default = 'rgb(0,0,0)';
        if(empty($color)) return $default; 
        if($color[0] == '#' ) { $color = substr( $color, 1 ); }
        if(strlen($color) == 6) { $hex = array( $color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5] ); }
        elseif( strlen( $color ) == 3 ) { $hex = array( $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] ); }
        else { return $default; }
        $rgb =  array_map('hexdec', $hex);
        if($opacity){
            if(abs($opacity) > 1) $opacity = 1.0;
            $output = 'rgba('.implode(",",$rgb).','.$opacity.')';
        } else {
            $output = 'rgb('.implode(",",$rgb).')';
        }
        return $output;
    }
}