<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Admin\User\Service;

use OCP\IUserManager;
use OCP\IGroupManager;
use OCA\StechTimesheet\Features\Admin\User\Db\UserMapper;

class UserService {
    private $mapper;
    private $userManager;
    private $groupManager;

    public function __construct(UserMapper $mapper, 
                                IUserManager $userManager,
                                IGroupManager $groupManager) {
        $this->mapper = $mapper;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
    }

    /**
     * Get all users formatted for the Admin Table
     */
    public function getAllUsers(): array {
        $users = [];
        // Search for all users ('')
        foreach ($this->userManager->search('') as $u) {
            $uid = $u->getUID();
            $users[] = [
                'uid' => $uid,
                'displayName' => $u->getDisplayName(),
                'email' => $u->getEmailAddress(),
                'isEnabled' => $u->isEnabled(),
                'lastLogin' => $u->getLastLogin()
            ];
        }
        return $users;
    }

    /**
     * Toggle a user's enabled status (NC Core function)
     */
    public function toggleUserStatus(string $uid): bool {
        $user = $this->userManager->get($uid);
        if ($user) {
            $newState = !$user->isEnabled();
            $user->setEnabled($newState);
            return $newState;
        }
        return false;
    }

    /**
     * Get all NC Groups for the dropdown
     */
    public function getAllGroups(): array {
        $groups = $this->groupManager->search('');
        $list = [];
        foreach ($groups as $g) { 
            $list[] = ['gid' => $g->getGID(), 'displayName' => $g->getDisplayName()]; 
        }
        return $list;
    }

    public function getAccessRules(): array {
        $rules = [];
        foreach($this->mapper->getAccessRules() as $row) { 
            $rules[$row['rule_key']] = json_decode($row['allowed_groups'] ?? '[]', true); 
        }
        return $rules;
    }

    public function saveAccessRule(string $key, array $groups): void {
        $this->mapper->saveAccessRule($key, json_encode($groups));
    }
}