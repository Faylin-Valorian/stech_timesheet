<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Analysis\Dashboard\Service;

use OCP\IGroupManager;
use OCP\IUserSession;
use OCA\StechTimesheet\Features\Analysis\Dashboard\Db\DashboardMapper;
// New Import
use OCA\StechTimesheet\Features\Timesheet\Calendar\Db\CalendarMapper;

class DashboardService {
    private $mapper;
    private $calendarMapper; // Renamed for clarity

    public function __construct(DashboardMapper $mapper,
                                CalendarMapper $calendarMapper, // Updated Type Hint
                                IGroupManager $groupManager,
                                IUserSession $userSession) {
        $this->mapper = $mapper;
        $this->calendarMapper = $calendarMapper;
        $this->groupManager = $groupManager;
        $this->userSession = $userSession;
    }

    /**
     * --- ACCESS CONTROL ---
     */
    public function checkAccess(string $ruleKey): bool {
        $user = $this->userSession->getUser();
        if (!$user) return false;
        
        // 1. Super Admin ALWAYS has access
        if ($this->groupManager->isAdmin($user->getUID())) return true;

        // 2. Fetch allowed groups from Database via Core Mapper
        $allowedGroups = $this->timesheetMapper->getAccessRule($ruleKey);
        
        // 3. If no rule exists, default to FALSE
        if (empty($allowedGroups)) return false;

        // 4. Check if user is in allowed groups
        $userGroups = $this->groupManager->getUserGroupIds($user);
        foreach ($userGroups as $gid) {
            if (in_array($gid, $allowedGroups)) return true;
        }

        return false;
    }

    /**
     * --- DATA FETCHING ---
     */
    public function getRawData(string $period, ?string $targetUser = null, ?string $customStart = null, ?string $customEnd = null): array {
        // Handle Date Range
        if ($period === 'custom' && $customStart && $customEnd) {
            $start = new \DateTime($customStart);
            $end = new \DateTime($customEnd);
        } else {
            list($start, $end) = $this->getDateRange($period);
        }

        return $this->dashboardMapper->getFullReportingData($start, $end, $targetUser);
    }

    /**
     * --- DATE LOGIC ---
     */
    public function getDateRange(string $period): array {
        $settings = $this->timesheetMapper->getAdminSettings();
        
        if (($settings['pay_frequency'] ?? '') === 'custom_twice') {
            return $this->calcCustomTwiceRange($period, $settings);
        }
        return $this->calcStandardRange($period, $settings);
    }

    private function calcStandardRange(string $period, array $settings): array {
        $freq = (int)($settings['pay_frequency'] ?? 14);
        $refDate = new \DateTime($settings['pay_start_date'] ?? '2024-01-01');
        $now = new \DateTime();
        
        $diff = $now->diff($refDate)->days;
        if ($now < $refDate) $diff = -$diff;
        $cycles = (int)floor($diff / $freq);
        
        $currentStart = clone $refDate;
        $currentStart->modify('+' . ($cycles * $freq) . ' days');
        $currentEnd = (clone $currentStart)->modify('+' . ($freq - 1) . ' days');

        if ($period === 'this_pay_period') return [$currentStart, $currentEnd];
        if ($period === 'last_pay_period') return [(clone $currentStart)->modify('-' . $freq . ' days'), (clone $currentStart)->modify('-1 day')];
        if ($period === 'this_month') return [new \DateTime('first day of this month'), new \DateTime('last day of this month')];
        if ($period === 'ytd') return [new \DateTime('first day of January this year'), new \DateTime('now')];
        
        return [$currentStart, $currentEnd];
    }

    private function calcCustomTwiceRange(string $period, array $settings): array {
        $d1 = (int)($settings['pay_date_1'] ?? 1);
        $d2 = (int)($settings['pay_date_2'] ?? 15);
        
        $now = new \DateTime();
        $y = $now->format('Y');
        $m = $now->format('m');

        $dateA = new \DateTime("$y-$m-$d1");
        $dateB = new \DateTime("$y-$m-$d2");

        if ($dateA > $dateB) { $temp = $dateA; $dateA = $dateB; $dateB = $temp; }

        if ($now < $dateA) {
            $prevB = clone $dateB; $prevB->modify('-1 month');
            $start = (clone $prevB)->modify('+1 day');
            $end = $dateA;
        } elseif ($now >= $dateA && $now < $dateB) {
            $start = (clone $dateA)->modify('+1 day');
            $end = $dateB;
        } else {
            $start = (clone $dateB)->modify('+1 day');
            $nextA = clone $dateA; $nextA->modify('+1 month');
            $end = $nextA;
        }

        if ($period === 'this_pay_period') return [$start, $end];
        
        if ($period === 'last_pay_period') {
            $checkDate = clone $start;
            $checkDate->modify('-5 days');
            
            $pY = $checkDate->format('Y');
            $pM = $checkDate->format('m');
            $pDateA = new \DateTime("$pY-$pM-$d1");
            $pDateB = new \DateTime("$pY-$pM-$d2");
            if ($pDateA > $pDateB) { $t = $pDateA; $pDateA = $pDateB; $pDateB = $t; }

            if ($checkDate < $pDateA) {
                $prevB = clone $pDateB; $prevB->modify('-1 month');
                return [(clone $prevB)->modify('+1 day'), $pDateA];
            } elseif ($checkDate >= $pDateA && $checkDate < $pDateB) {
                return [(clone $pDateA)->modify('+1 day'), $pDateB];
            } else {
                $nextA = clone $pDateA; $nextA->modify('+1 month');
                return [(clone $pDateB)->modify('+1 day'), $nextA];
            }
        }

        if ($period === 'this_month') return [new \DateTime('first day of this month'), new \DateTime('last day of this month')];
        if ($period === 'ytd') return [new \DateTime('first day of January this year'), new \DateTime('now')];

        return [$start, $end];
    }
}