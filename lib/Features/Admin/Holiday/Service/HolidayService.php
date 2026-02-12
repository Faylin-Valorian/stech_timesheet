<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Admin\Holiday\Service;

use OCA\StechTimesheet\Features\Admin\Holiday\Db\HolidayMapper;

class HolidayService {
    private $mapper;

    public function __construct(HolidayMapper $mapper) {
        $this->mapper = $mapper;
    }

    public function getAllHolidays(): array {
        return $this->mapper->getHolidays();
    }

    public function saveHoliday(array $data): void {
        // Basic validation could go here
        $this->mapper->saveHoliday($data);
    }

    public function toggleHoliday(int $id): void {
        $this->mapper->toggleHoliday($id);
    }

    public function deleteHoliday(int $id): void {
        $this->mapper->deleteHoliday($id);
    }
}