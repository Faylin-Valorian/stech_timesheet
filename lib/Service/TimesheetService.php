<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Service;

use OCA\StechTimesheet\Db\TimesheetMapper;

class TimesheetService {
    private $mapper;

    public function __construct(TimesheetMapper $mapper) {
        $this->mapper = $mapper;
    }

    public function getCalendarEvents(string $userId, string $start, string $end): array {
        $events = [];
        $settings = $this->getPayrollSettings();
        
        // 1. Generate Payroll Markers
        $events = array_merge($events, $this->generatePayrollMarkers($settings, $start, $end));

        // 2. Fetch and Format Timesheets
        $entries = $this->mapper->findUserEntries($userId, $start, $end);
        foreach ($entries as $entry) {
            $isClosed = !empty($entry->getTimeOut());
            $events[] = [
                'id' => $entry->getId(),
                'title' => $isClosed ? $entry->getTimeTotal() . ' hrs' : 'Active',
                'start' => $entry->getTimesheetDate(),
                'color' => $isClosed ? '#28a745' : '#ffc107',
                'extendedProps' => ['isClosed' => $isClosed]
            ];
        }

        return $events;
    }

    private function getPayrollSettings(): array {
        $rows = $this->mapper->getSettings();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    private function generatePayrollMarkers(array $settings, string $start, string $end): array {
        $markers = [];
        $payStart = new \DateTime($settings['pay_start_date'] ?? '2026-01-07');
        $freq = (int)($settings['pay_frequency'] ?? 14);
        $current = clone $payStart;
        $viewEnd = new \DateTime($end);

        while ($current <= $viewEnd) {
            if ($current >= new \DateTime($start)) {
                $markers[] = [
                    'id' => 'pay-' . $current->format('Ymd'),
                    'title' => 'Payroll',
                    'start' => $current->format('Y-m-d'),
                    'color' => '#34495e',
                    'display' => 'block'
                ];
            }
            $current->modify("+$freq days");
        }
        return $markers;
    }
}