<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Admin\User\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class UserMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        // We bind to access_rules, as user data comes from NC Core
        parent::__construct($db, 'stech_access_rules');
    }

    public function getAccessRules(): array {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')
            ->from('stech_access_rules')
            ->executeQuery()
            ->fetchAll();
    }

    public function saveAccessRule(string $key, string $groupsJson): void {
        $qb = $this->db->getQueryBuilder();
        $exists = $qb->select('rule_key')
            ->from('stech_access_rules')
            ->where($qb->expr()->eq('rule_key', $qb->createNamedParameter($key)))
            ->executeQuery()
            ->fetch();

        $qb = $this->db->getQueryBuilder();
        if ($exists) {
            $qb->update('stech_access_rules')
               ->set('allowed_groups', $qb->createNamedParameter($groupsJson))
               ->where($qb->expr()->eq('rule_key', $qb->createNamedParameter($key)))
               ->executeStatement();
        } else {
            $qb->insert('stech_access_rules')
               ->values([
                   'rule_key' => $qb->createNamedParameter($key),
                   'allowed_groups' => $qb->createNamedParameter($groupsJson)
               ])->executeStatement();
        }
    }
}