<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Timesheet\Entry\Service;

use OCA\StechTimesheet\Features\Timesheet\Entry\Db\EntryMapper;

class EntryService {
    private $mapper;

    public function __construct(EntryMapper $mapper) {
        $this->mapper = $mapper;
    }

    public function getFormAttributes(): array {
        return [
            'jobs' => $this->mapper->getActiveJobs(), 
            'states' => $this->mapper->getEnabledStates()
        ];
    }

    public function getCounties(string $stateAbbr): array {
        return $this->mapper->getCountiesByState($stateAbbr);
    }

    public function getEntryDetails(int $id, string $uid): ?array {
        $entry = $this->mapper->getEntryById($id, $uid);
        if ($entry) {
            $entry['activities'] = $this->mapper->getActivities($id);
        }
        return $entry;
    }

    public function saveEntry(string $uid, array $data): int {
        // 1. Validation Logic
        $date = $data['date'] ?? null;
        if (!$date) throw new \Exception('Date is required.');
        
        if (empty($data['time_in']) && empty($data['req_per_diem'])) {
            throw new \Exception('Start Time required unless Per Diem only.');
        }

        // 2. Prepare Main Record
        $hasRoadScanning = (isset($data['road_scanning']) && $data['road_scanning'] == 1);
        $hasFirstLast = (isset($data['first_last_day']) && $data['first_last_day'] == 1);
        $hasOvernight = (isset($data['overnight']) && $data['overnight'] == 1);
        $hasPerDiem = (isset($data['req_per_diem']) && $data['req_per_diem'] == 1);
        $hasMiles = !empty($data['miles']);

        $values = [
            'timesheet_date' => $date,
            'time_in' => !empty($data['time_in']) ? $data['time_in'] : null,
            'time_out' => !empty($data['time_out']) ? $data['time_out'] : null,
            'time_break' => (int)($data['break_min'] ?? 0),
            'time_total' => (float)($data['total_hours'] ?? 0),
            'additional_comments' => $data['comments'] ?? '',
            'travel' => ($hasPerDiem || $hasMiles || $hasRoadScanning || $hasFirstLast || $hasOvernight) ? 1 : 0,
            'travel_per_diem' => $hasPerDiem ? 1 : 0,
            'travel_road_scanning' => $hasRoadScanning ? 1 : 0,
            'travel_first_last_day' => $hasFirstLast ? 1 : 0,
            'travel_overnight' => $hasOvernight ? 1 : 0,
            'travel_state' => $data['state'] ?? null,
            'travel_county' => $data['county'] ?? null,
            'travel_miles' => (int)($data['miles'] ?? 0),
            'travel_extra_expenses' => (float)($data['extra_expense'] ?? 0),
            'archive' => 0 
        ];

        // 3. Insert or Update
        if (!empty($data['timesheet_id'])) {
            $tid = (int)$data['timesheet_id'];
            $this->mapper->updateEntry($tid, $values); // UID is implicit via Mapper check usually, but for update we trust ID
        } else {
            $values['userid'] = $uid;
            $tid = $this->mapper->createEntry($values);
        }

        // 4. Handle Activities
        $activities = [];
        if (isset($data['work_desc']) && is_array($data['work_desc'])) {
            foreach ($data['work_desc'] as $idx => $desc) {
                if (!empty($desc)) {
                    $activities[] = [
                        'desc' => $desc,
                        'percent' => (int)($data['work_percent'][$idx] ?? 0)
                    ];
                }
            }
        }
        $this->mapper->replaceActivities($tid, $activities);

        return $tid;
    }

    public function setArchiveStatus(int $id, string $uid, int $status): void {
        $this->mapper->toggleArchive($id, $uid, $status);
    }
}