<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Analysis\Travel\Service;

use OCA\StechTimesheet\Db\TimesheetMapper;

class TravelService {
    private $timesheetMapper;

    public function __construct(TimesheetMapper $timesheetMapper) {
        $this->timesheetMapper = $timesheetMapper;
    }

    public function process(array $rows): array {
        $travel = [
            'total_miles' => 0, 
            'per_diem_days' => 0, 
            'overnight_stays' => 0, 
            'total_expenses' => 0.0
        ];

        $states = []; 
        $counties = []; 
        $processedTids = [];

        // Fetch enabled states for Map coloring logic
        $enabledStates = array_column($this->timesheetMapper->getEnabledStates(), 'state_name');

        foreach ($rows as $row) {
            $tid = $row['timesheet_id'];
            
            // Travel data is stored at the Timesheet level (once per day), not per job split.
            // We must ensure we only count it once per timesheet ID.
            if (in_array($tid, $processedTids)) {
                continue;
            }
            $processedTids[] = $tid;

            // --- 1. Metrics ---
            $travel['total_miles'] += (int)($row['travel_miles'] ?? 0);
            if (((int)($row['travel_per_diem'] ?? 0)) === 1) $travel['per_diem_days']++;
            if (((int)($row['travel_overnight'] ?? 0)) === 1) $travel['overnight_stays']++;
            $travel['total_expenses'] += (float)($row['travel_extra_expenses'] ?? 0);

            // --- 2. Geography (Map Data) ---
            $stateName = $row['full_state_name'] ?? $row['travel_state'] ?? 'Unknown';
            $userName = $row['userid'] ?? 'Unknown';

            // State Logic
            if (!isset($states[$stateName])) {
                $states[$stateName] = [
                    'count' => 0, 
                    'is_enabled' => in_array($stateName, $enabledStates),
                    'visitors' => []
                ];
            }
            $states[$stateName]['count']++;
            if (!isset($states[$stateName]['visitors'][$userName])) {
                $states[$stateName]['visitors'][$userName] = 0;
            }
            $states[$stateName]['visitors'][$userName]++;

            // County Logic
            $countyName = trim(str_ireplace(' County', '', $row['travel_county'] ?? ''));
            if ($countyName) {
                $key = $stateName . '|' . $countyName;
                if (!isset($counties[$key])) {
                    $counties[$key] = [
                        'count' => 0, 
                        'is_enabled' => in_array($stateName, $enabledStates), // Inherit state status
                        'visitors' => []
                    ];
                }
                $counties[$key]['count']++;
                if (!isset($counties[$key]['visitors'][$userName])) {
                    $counties[$key]['visitors'][$userName] = 0;
                }
                $counties[$key]['visitors'][$userName]++;
            }
        }

        return [
            'travel' => $travel,
            'states' => $states,
            'counties' => $counties
        ];
    }
}