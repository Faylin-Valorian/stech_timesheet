<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Admin\Job\Service;

use OCA\StechTimesheet\Features\Admin\Job\Db\JobMapper;

class JobService {
    private $mapper;

    public function __construct(JobMapper $mapper) {
        $this->mapper = $mapper;
    }

    public function getJobList(): array {
        return $this->mapper->getAllJobs();
    }

    public function saveJob(array $data): void {
        // Enforce types before sending to DB
        $data['is_pto'] = isset($data['is_pto']) && $data['is_pto'] === 'true' ? 1 : 0;
        $this->mapper->saveJob($data);
    }

    public function toggleJobStatus(int $id): void {
        $this->mapper->toggleJob($id);
    }
}