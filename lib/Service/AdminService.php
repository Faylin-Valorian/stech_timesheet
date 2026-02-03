<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Service;

use OCP\IUserManager;
use OCA\StechTimesheet\Db\AdminMapper;

class AdminService {
    private $userManager;
    private $mapper;

    public function __construct(IUserManager $userManager, AdminMapper $mapper) {
        $this->userManager = $userManager;
        $this->mapper = $mapper;
    }

    /**
     * Merges Nextcloud users with local status and applies sorting logic.
     */
    public function getProcessedUserList(): array {
        $ncUsers = $this->userManager->search('');
        $statusMap = $this->mapper->getEmployeeStatusMap();

        $result = []; 
        foreach ($ncUsers as $u) {
            $uid = $u->getUID();
            $isActive = $statusMap[$uid] ?? 1;
            $result[] = [
                'uid' => $uid, 
                'displayname' => $u->getDisplayName(), 
                'email' => $u->getEmailAddress(), 
                'is_active' => $isActive
            ];
        }
        
        // Sort by active status (active first) then display name.
        usort($result, function($a, $b) {
            if ($a['is_active'] !== $b['is_active']) return $b['is_active'] - $a['is_active']; 
            return strcasecmp($a['displayname'], $b['displayname']);
        });

        return $result;
    }
}