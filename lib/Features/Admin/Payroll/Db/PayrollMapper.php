<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Admin\Payroll\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class PayrollMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'stech_admin_settings');
    }

    public function getPayrollSettings(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from('stech_admin_settings')
           ->where($qb->expr()->like('setting_key', $qb->createNamedParameter('pay_%')));
        
        $results = $qb->executeQuery()->fetchAll();
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    public function saveSetting(string $key, string $value): void {
        $qb = $this->db->getQueryBuilder();
        $exists = $qb->select('setting_key')
                     ->from('stech_admin_settings')
                     ->where($qb->expr()->eq('setting_key', $qb->createNamedParameter($key)))
                     ->executeQuery()
                     ->fetch();

        $qb = $this->db->getQueryBuilder();
        if ($exists) {
            $qb->update('stech_admin_settings')
               ->set('setting_value', $qb->createNamedParameter($value))
               ->where($qb->expr()->eq('setting_key', $qb->createNamedParameter($key)))
               ->executeStatement();
        } else {
            $qb->insert('stech_admin_settings')
               ->values([
                   'setting_key' => $qb->createNamedParameter($key),
                   'setting_value' => $qb->createNamedParameter($value)
               ])->executeStatement();
        }
    }
}