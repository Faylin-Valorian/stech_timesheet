<?php
namespace OCA\StechTimesheet\Cron;

use OCA\StechTimesheet\Service\HolidayService;
use OCP\BackgroundJob\TimedJob;
use OCP\IUserSession;

class HolidayInsertJob extends TimedJob {
	private $holidayService;
	private $userSession;

	public function __construct(HolidayService $holidayService, IUserSession $userSession) {
		// Nextcloud injects the service here automatically
		$this->holidayService = $holidayService;
		$this->userSession = $userSession;
		$this->setInterval(86400); // Once a day
	}

	protected function run($argument): void {
		$today = new \DateTime();
		// Logic to determine current holiday...
		$holidayName = "Labor Day"; // Example
		
		// Get all active users and apply logic
		// $this->holidayService->autoInsertHolidays($user->getUID(), $today, $holidayName);
	}
}