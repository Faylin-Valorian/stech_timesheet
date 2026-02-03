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

    public function getProcessedUserList(): array {
        $ncUsers = $this->userManager->search('');
        $statusMap = $this->mapper->getEmployeeStatusMap();
        $result = []; 
        foreach ($ncUsers as $u) {
            $uid = $u->getUID();
            $result[] = ['uid' => $uid, 'displayname' => $u->getDisplayName(), 'email' => $u->getEmailAddress(), 'is_active' => $statusMap[$uid] ?? 1];
        }
        usort($result, function($a, $b) {
            if ($a['is_active'] !== $b['is_active']) return $b['is_active'] - $a['is_active']; 
            return strcasecmp($a['displayname'], $b['displayname']);
        });
        return $result;
    }

    public function getLocalImagePath(string $filename): ?string {
        $appPath = \OC::$server->getAppManager()->getAppPath('stech_timesheet');
        $localPath = $appPath . '/img/' . basename($filename);
        return file_exists($localPath) ? $localPath : null;
    }

    public function saveThumbnail(string $cardId, $sourceStream): void {
        $fileName = 'thumb-' . $cardId . '.png';
        try {
            try { $folder = $this->appData->getFolder('thumbnails'); } 
            catch (NotFoundException $e) { $folder = $this->appData->newFolder('thumbnails'); }
            try { $folder->getFile($fileName)->delete(); } catch(NotFoundException $e) {}
            $file = $folder->newFile($fileName);
            $file->putContent($sourceStream);
            $this->mapper->saveSettingValue('thumb_path_' . $cardId, $fileName);
        } catch (\Exception $e) { throw new \Exception('Storage failed: ' . $e->getMessage()); }
    }
}