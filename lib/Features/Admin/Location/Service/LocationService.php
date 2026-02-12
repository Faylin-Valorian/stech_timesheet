<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Admin\Location\Service;

use OCA\StechTimesheet\Features\Admin\Location\Db\LocationMapper;

class LocationService {
    private $mapper;

    public function __construct(LocationMapper $mapper) {
        $this->mapper = $mapper;
    }

    public function getStatesList(): array {
        return $this->mapper->getStates();
    }

    public function getCountiesList(string $abbr): array {
        return $this->mapper->getCountiesByState($abbr);
    }

    public function toggleStateStatus(int $id): int {
        return $this->mapper->toggleState($id);
    }

    public function toggleCountyStatus(int $id): int {
        return $this->mapper->toggleCounty($id);
    }
}