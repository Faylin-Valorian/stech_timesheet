<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Service;

use OCP\IUserManager;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCA\StechTimesheet\Db\AdminMapper;

class AdminService {
    private $userManager;
    private $mapper;
    private $appData;

    public function __construct(IUserManager $userManager, AdminMapper $mapper, IAppData $appData) {
        $this->userManager = $userManager;
        $this->mapper = $mapper;
        $this->appData = $appData;
    }

    /**
     * FIX: Returns 'displayname' (lowercase) to match frontend JS
     */
    public function getAllUsers(): array {
        $ncUsers = $this->userManager->search('');
        $statusMap = $this->mapper->getEmployeeStatusMap();

        $list = [];
        foreach ($ncUsers as $user) {
            $uid = $user->getUID();
            $isActive = isset($statusMap[$uid]) ? (int)$statusMap[$uid] : 1;
            
            $list[] = [
                'uid' => $uid,
                'displayname' => $user->getDisplayName(), // Lowercase key 'displayname' fixed here
                'email' => $user->getEmailAddress(),
                'is_active' => $isActive
            ];
        }
        return $list;
    }

    public function toggleUserStatus(string $uid): int {
        $map = $this->mapper->getEmployeeStatusMap();
        $current = isset($map[$uid]) ? (int)$map[$uid] : 1;
        $new = ($current === 1) ? 0 : 1;
        
        $this->mapper->toggleUserStatus($uid, $new);
        
        if ($new === 0) {
            $this->mapper->archiveUserHolidayEntries($uid);
        }
        
        return $new;
    }

    public function getLocalImagePath(string $filename): ?string {
        try {
            $folder = $this->appData->getFolder('thumbnails');
            $file = $folder->getFile($filename);
            return $file->getLocalFilePath(); 
        } catch (NotFoundException $e) {
            return null;
        }
    }

    public function saveThumbnail(string $cardId, $resource): void {
        try {
            $folder = $this->appData->getFolder('thumbnails');
        } catch (NotFoundException $e) {
            $folder = $this->appData->newFolder('thumbnails');
        }

        $filename = "thumb_{$cardId}.jpg";
        
        try {
            $file = $folder->getFile($filename);
            $file->delete(); // Overwrite
        } catch (NotFoundException $e) {
            // New file
        }

        $folder->newFile($filename, stream_get_contents($resource));
    }
}