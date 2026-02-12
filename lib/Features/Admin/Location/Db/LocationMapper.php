<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Admin\Location\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class LocationMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        // Defaulting to states, but we will use the QueryBuilder for both tables
        parent::__construct($db, 'stech_states');
    }

    public function getStates(): array {
        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')
            ->from('stech_states')
            ->orderBy('state_name', 'ASC')
            ->executeQuery()
            ->fetchAll();
    }

    public function getCountiesByState(string $stateAbbr): array {
        // First get the FIPS code for the state
        $qbState = $this->db->getQueryBuilder();
        $state = $qbState->select('fips_code')
            ->from('stech_states')
            ->where($qbState->expr()->eq('state_abbr', $qbState->createNamedParameter($stateAbbr)))
            ->executeQuery()
            ->fetch();

        if (!$state) return [];

        $qb = $this->db->getQueryBuilder();
        return $qb->select('*')
            ->from('stech_counties')
            ->where($qb->expr()->eq('state_fips', $qb->createNamedParameter($state['fips_code'])))
            ->orderBy('county_name', 'ASC')
            ->executeQuery()
            ->fetchAll();
    }

    public function toggleState(int $id): int {
        $qb = $this->db->getQueryBuilder();
        $row = $qb->select('is_enabled')->from('stech_states')->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))->executeQuery()->fetch();
        
        $newStatus = ((int)$row['is_enabled'] === 1) ? 0 : 1;
        
        $qbUpdate = $this->db->getQueryBuilder();
        $qbUpdate->update('stech_states')
            ->set('is_enabled', $qbUpdate->createNamedParameter($newStatus))
            ->where($qbUpdate->expr()->eq('id', $qbUpdate->createNamedParameter($id)))
            ->executeStatement();
            
        return $newStatus;
    }

    public function toggleCounty(int $id): int {
        $qb = $this->db->getQueryBuilder();
        $row = $qb->select('is_enabled')->from('stech_counties')->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))->executeQuery()->fetch();
        
        $newStatus = ((int)$row['is_enabled'] === 1) ? 0 : 1;
        
        $qbUpdate = $this->db->getQueryBuilder();
        $qbUpdate->update('stech_counties')
            ->set('is_enabled', $qbUpdate->createNamedParameter($newStatus))
            ->where($qbUpdate->expr()->eq('id', $qbUpdate->createNamedParameter($id)))
            ->executeStatement();
            
        return $newStatus;
    }
}