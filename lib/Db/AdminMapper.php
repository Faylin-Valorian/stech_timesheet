<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class AdminMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'stech_access_rules');
    }

    /**
     * Fetch all access rules from the database.
     */
    public function getAccessRules(): array {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')
                  ->from('stech_access_rules')
                  ->executeQuery()
                  ->fetchAll();
    }

    /**
     * Fetch all system settings.
     */
    public function getSettings(): array {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')
                  ->from('stech_admin_settings')
                  ->executeQuery()
                  ->fetchAll();
    }

    /**
     * Fetch local employee status map.
     */
    public function getEmployeeStatusMap(): array {
        try {
            $qb = $this->db->getQueryBuilder();
            $rows = $qb->select('*')->from('stech_employees')->executeQuery()->fetchAll();
            $statusMap = [];
            foreach($rows as $row) {
                $statusMap[$row['uid']] = (int)$row['is_active'];
            }
            return $statusMap;
        } catch (\Exception $e) {
            return [];
        }
    }
}