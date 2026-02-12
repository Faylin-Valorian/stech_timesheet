<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Admin\Job\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class JobMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'stech_jobs');
    }

    public function getAllJobs(): array {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')
            ->from('stech_jobs')
            ->orderBy('job_name', 'ASC')
            ->executeQuery()
            ->fetchAll();
    }

    public function saveJob(array $data): void {
        $qb = $this->db->getQueryBuilder();
        
        $fields = [
            'job_name' => $data['job_name'],
            'is_pto' => $data['is_pto'] ?? 0,
            'job_revenue' => $data['job_revenue'] ?? 0,
            'job_expense_budget' => $data['job_expense_budget'] ?? 0,
            'job_hourly_cost' => $data['job_hourly_cost'] ?? 0
        ];

        if (!empty($data['job_id'])) {
            $qb->update('stech_jobs');
            foreach ($fields as $col => $val) {
                $qb->set($col, $qb->createNamedParameter($val));
            }
            $qb->where($qb->expr()->eq('job_id', $qb->createNamedParameter($data['job_id'])))
               ->executeStatement();
        } else {
            $qb->insert('stech_jobs');
            foreach ($fields as $col => $val) {
                $qb->setValue($col, $qb->createNamedParameter($val));
            }
            // Set defaults for new records
            $qb->setValue('job_archive', $qb->createNamedParameter(0));
            $qb->executeStatement();
        }
    }

    public function toggleJob(int $id): void {
        $qb = $this->db->getQueryBuilder();
        $row = $qb->select('job_archive')
            ->from('stech_jobs')
            ->where($qb->expr()->eq('job_id', $qb->createNamedParameter($id)))
            ->executeQuery()
            ->fetch();

        if ($row) {
            $newStatus = ((int)$row['job_archive'] === 1) ? 0 : 1;
            
            $qbUpdate = $this->db->getQueryBuilder();
            $qbUpdate->update('stech_jobs')
                ->set('job_archive', $qbUpdate->createNamedParameter($newStatus));
            
            // If archiving, stamp the time so we can filter history later
            if ($newStatus === 1) {
                $qbUpdate->set('job_archived_at', $qbUpdate->createNamedParameter(date('Y-m-d')));
            } else {
                $qbUpdate->set('job_archived_at', $qbUpdate->createNamedParameter(null));
            }

            $qbUpdate->where($qbUpdate->expr()->eq('job_id', $qbUpdate->createNamedParameter($id)))
                     ->executeStatement();
        }
    }
}