<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Admin\Holiday\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class HolidayMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'stech_holidays');
    }

    public function getHolidays(): array {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')
            ->from('stech_holidays')
            ->orderBy('holiday_start_date', 'DESC')
            ->executeQuery()
            ->fetchAll();
    }

    public function saveHoliday(array $data): void {
        $qb = $this->db->getQueryBuilder();
        
        $fields = [
            'holiday_name' => $data['name'],
            'holiday_start_date' => $data['start'],
            'holiday_end_date' => $data['end'],
            'holiday_bg' => $data['bg'] ?? '#e67e22'
        ];

        if (!empty($data['id'])) { 
            $qb->update('stech_holidays');
            foreach ($fields as $col => $val) {
                $qb->set($col, $qb->createNamedParameter($val));
            }
            $qb->where($qb->expr()->eq('holiday_id', $qb->createNamedParameter($data['id'])))
               ->executeStatement();
        } else {
            $qb->insert('stech_holidays');
            foreach ($fields as $col => $val) {
                $qb->setValue($col, $qb->createNamedParameter($val));
            }
            $qb->setValue('holiday_archive', $qb->createNamedParameter(0));
            $qb->executeStatement();
        }
    }

    public function toggleHoliday(int $id): void {
        $qb = $this->db->getQueryBuilder();
        $row = $qb->select('holiday_archive')
            ->from('stech_holidays')
            ->where($qb->expr()->eq('holiday_id', $qb->createNamedParameter($id)))
            ->executeQuery()
            ->fetch();

        if ($row) {
            $newStatus = ((int)$row['holiday_archive'] === 1) ? 0 : 1;
            $qbUpdate = $this->db->getQueryBuilder();
            $qbUpdate->update('stech_holidays')
                ->set('holiday_archive', $qbUpdate->createNamedParameter($newStatus))
                ->where($qbUpdate->expr()->eq('holiday_id', $qbUpdate->createNamedParameter($id)))
                ->executeStatement();
        }
    }

    public function deleteHoliday(int $id): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('stech_holidays')
           ->where($qb->expr()->eq('holiday_id', $qb->createNamedParameter($id)))
           ->executeStatement();
    }
}