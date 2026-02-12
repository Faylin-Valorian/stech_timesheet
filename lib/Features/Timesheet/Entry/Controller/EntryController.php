<?php
declare(strict_types=1);

namespace OCA\StechTimesheet\Features\Timesheet\Entry\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCA\StechTimesheet\Features\Timesheet\Entry\Service\EntryService;

class EntryController extends Controller {
    private $service;
    private $userSession;
    private $groupManager;

    public function __construct(IRequest $request, 
                                EntryService $service,
                                IUserSession $userSession,
                                IGroupManager $groupManager) {
        parent::__construct('stech_timesheet', $request);
        $this->service = $service;
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
    }

    private function getEffectiveUserId(): string {
        $currentUser = $this->userSession->getUser();
        if (!$currentUser) return ''; 
        
        $currentUid = $currentUser->getUID();
        $targetUid = $this->request->getParam('target_user');

        if ($targetUid && $targetUid !== $currentUid) {
            if ($this->groupManager->isAdmin($currentUid)) {
                return $targetUid;
            }
        }
        return $currentUid;
    }

    /** @NoAdminRequired @NoCSRFRequired */
    public function getAttributes(): DataResponse {
        return new DataResponse($this->service->getFormAttributes());
    }

    /** @NoAdminRequired @NoCSRFRequired */
    public function getCounties(string $stateAbbr): DataResponse {
        return new DataResponse($this->service->getCounties($stateAbbr));
    }

    /** @NoAdminRequired @NoCSRFRequired */
    public function getEntry(int $id): DataResponse {
        $uid = $this->getEffectiveUserId();
        $entry = $this->service->getEntryDetails($id, $uid);
        
        if (!$entry) return new DataResponse([], 404);
        
        $currentUser = $this->userSession->getUser();
        $isAdmin = $currentUser && $this->groupManager->isAdmin($currentUser->getUID());
        $entry['is_admin'] = $isAdmin;

        return new DataResponse($entry);
    }

    /** @NoAdminRequired @NoCSRFRequired */
    public function saveEntry(): DataResponse {
        $uid = $this->getEffectiveUserId();
        try {
            $tid = $this->service->saveEntry($uid, $this->request->getParams());
            return new DataResponse(['status' => 'success', 'id' => $tid]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 400);
        }
    }

    /** @NoAdminRequired @NoCSRFRequired */
    public function deleteEntry(int $id): DataResponse {
        $uid = $this->getEffectiveUserId();
        $this->service->setArchiveStatus($id, $uid, 1); // 1 = Archived
        return new DataResponse(['status' => 'success']);
    }

    /** @NoAdminRequired @NoCSRFRequired */
    public function restoreEntry(int $id): DataResponse {
        $uid = $this->getEffectiveUserId();
        $this->service->setArchiveStatus($id, $uid, 0); // 0 = Active
        return new DataResponse(['status' => 'success']);
    }
}