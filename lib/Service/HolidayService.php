<?php
namespace OCA\StechTimesheet\Service;

use OCA\StechTimesheet\Db\TimesheetMapper;
use OCP\IConfig;

class HolidayService {
	private $mapper;
	private $config;

	public function __construct(TimesheetMapper $mapper, IConfig $config) {
		$this->mapper = $mapper;
		$this->config = $config;
	}

	/**
	 * Logic moved from HolidayInsertJob.php
	 */
	public function autoInsertHolidays(string $userId, \DateTime $date, string $holidayName): bool {
		// Check if user already has an entry for this date to avoid duplicates
		$existing = $this->mapper->findByDate($userId, $date);
		if ($existing) {
			return false;
		}

		// Logic for creating the entry
		$newEntry = new \OCA\StechTimesheet\Db\Timesheet();
		$newEntry->setUserId($userId);
		$newEntry->setWorkType('Holiday');
		$newEntry->setComments("Holiday: $holidayName");
		// Use standard holiday hours from config (e.g., 8.0)
		$newEntry->setTotalHours((float)$this->config->getAppValue('stech_timesheet', 'standard_holiday_hours', 8));
		
		$this->mapper->insert($newEntry);
		return true;
	}

	/**
	 * Logic moved from TimesheetController.php
	 */
	public function calculatePTOHours(array $entries): float {
		return array_reduce($entries, function($carry, $entry) {
			// Logic to check is_pto flag or comment
			if ($entry->getWorkType() === 'PTO' || str_contains($entry->getComments(), 'Holiday:')) {
				return $carry + $entry->getTotalHours();
			}
			return $carry;
		}, 0.0);
	}
}