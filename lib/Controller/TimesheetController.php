<?php

declare(strict_types=1);

namespace OCA\DeckTimeTracking\Controller;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Service\PermissionService;
use OCA\DeckTimeTracking\Db\Timesheet;
use OCA\DeckTimeTracking\Db\TimesheetMapper;
use OCA\DeckTimeTracking\Notification\NotificationHelper;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IUserSession;

class TimesheetController extends ApiController {
    private TimesheetMapper $mapper;
	private CardMapper $cardMapper;
    private PermissionService $permissionService;
	private AssignmentMapper $assignmentMapper;
    private IUserSession $userSession;
    private NotificationHelper $notificationHelper;

    public function __construct(
        string $AppName,
        IRequest $request,
        TimesheetMapper $mapper,
		CardMapper $cardMapper,
		PermissionService $permissionService,
        AssignmentMapper $assignmentMapper,
        IUserSession $userSession,
        NotificationHelper $notificationHelper
    ) {
        parent::__construct($AppName, $request);
        $this->mapper = $mapper;
		$this->cardMapper = $cardMapper;
		$this->permissionService = $permissionService;
        $this->assignmentMapper = $assignmentMapper;
        $this->userSession = $userSession;
        $this->notificationHelper = $notificationHelper;
    }

    /**
     * Get all time records for a given card.
     */
    #[NoAdminRequired]
    public function getCardTimesheets(int $cardId): DataResponse {
        $boardId = $this->cardMapper->findBoardId($cardId);
        $userId = $this->userSession->getUser()->getUID();
        $canAccess = false;
        foreach ($this->permissionService->findUsers($boardId) as $user) {
            if($user->getUID() === $userId) {
                $canAccess = true;
                break;
            }
        }
        $timesheets = [];
        if($canAccess) {
            $timesheets = $this->mapper->findByCardId($cardId);
        }
        return new DataResponse($timesheets);
    }

    /**
     * Start a new time tracking entry.
     */
    #[NoAdminRequired]
    public function startTracking(int $cardId): DataResponse {
        $userId = $this->userSession->getUser()->getUID();
        if(!$this->assignmentMapper->isUserAssigned($cardId, $userId)) {
            return new DataResponse(['error' => 'Only assigned users can start a timer'], 403);
        }
        $activeTimers = $this->mapper->findUserActive($userId);
        if(count($activeTimers) > 0) {
            return new DataResponse(['error' => 'There is already an active timer'], 409);
        }
        $timesheet = new Timesheet();
        $timesheet->setCardId($cardId);
        $timesheet->setUserId($userId);
        $timesheet->setStart(new \DateTime());

        $savedTimesheet = $this->mapper->insert($timesheet);

        $this->notificationHelper->sendStart($savedTimesheet);

        return new DataResponse($savedTimesheet);
    }

    /**
     * Stop the active time tracking entry and add a description.
     */
    #[NoAdminRequired]
    public function stopTracking(int $id, string $description = ''): DataResponse {
        $timesheet = $this->mapper->find($id);
        if ($timesheet === null) {
            return new DataResponse(['error' => 'Entry not found'], 404);
        }
        if ($timesheet->getUserId() !== $this->userSession->getUser()->getUID()) {
            return new DataResponse(['error' => 'Only the owner can stop his timer'], 403);
        }
        $timesheet->setEnd(new \DateTime());
        $timesheet->setDescription($description);
        $updatedTimesheet = $this->mapper->update($timesheet);

        $this->notificationHelper->sendEnd($updatedTimesheet);

        return new DataResponse($updatedTimesheet);
    }

    /**
     * Create a new timesheet entry from the timesheet tab.
     */
    #[NoAdminRequired]
    public function createTimesheet(int $cardId, string $userId, string $start, string|null $end, string $description): DataResponse {
        $boardId = $this->cardMapper->findBoardId($cardId);
        $currentUserId = $this->userSession->getUser()->getUID();
        $permissions = $this->permissionService->getPermissions($boardId, $currentUserId);
        $canAccess = false;
        if($currentUserId === $userId || ($permissions && ($permissions[Acl::PERMISSION_EDIT] || $permissions[Acl::PERMISSION_MANAGE]))) {
            $canAccess = true;
        }
        if(!$canAccess) {
            return new DataResponse(['error' => 'Only board managers and editors can create timesheets for others'], 403);
        }
        $timesheet = new Timesheet();
        $timesheet->setCardId($cardId);
        $timesheet->setUserId($userId);
        $timesheet->setStart($start);
        $timesheet->setEnd($end);
        $timesheet->setDescription($description);

        $savedTimesheet = $this->mapper->insert($timesheet);

        $this->notificationHelper->sendEdit($savedTimesheet);

        return new DataResponse($savedTimesheet);
    }

    /**
     * Edit a timesheet entry from the timesheet tab.
     */
    #[NoAdminRequired]
    public function editTimesheet(int $id, string $userId, string $start, string|null $end, string $description): DataResponse {
        $timesheet = $this->mapper->find($id);
        if ($timesheet === null) {
            return new DataResponse(['error' => 'Entry not found'], 404);
        }
        $boardId = $this->cardMapper->findBoardId($timesheet->getCardId());
        $currentUserId = $this->userSession->getUser()->getUID();
        $permissions = $this->permissionService->getPermissions($boardId, $currentUserId);
        $canAccess = false;
        if($currentUserId === $userId || ($permissions && ($permissions[Acl::PERMISSION_EDIT] || $permissions[Acl::PERMISSION_MANAGE]))) {
            $canAccess = true;
        }
        if(!$canAccess) {
            return new DataResponse(['error' => 'Only board managers and editors can edit timesheets for others'], 403);
        }
        $timesheet->setUserId($userId);
        $timesheet->setStart($start);
        $timesheet->setEnd($end);
        $timesheet->setDescription($description);

        $savedTimesheet = $this->mapper->update($timesheet);

        $this->notificationHelper->sendEdit($savedTimesheet);

        return new DataResponse($savedTimesheet);
    }

    /**
     * Delete a time record.
     */
    #[NoAdminRequired]
    public function deleteTimesheet(int $id): DataResponse {
        $timesheet = $this->mapper->find($id);
        if ($timesheet === null) {
            return new DataResponse(['error' => 'Entry not found'], 404);
        }
        $boardId = $this->cardMapper->findBoardId($timesheet->getCardId());
        $currentUserId = $this->userSession->getUser()->getUID();
        $permissions = $this->permissionService->getPermissions($boardId, $currentUserId);
        $canAccess = false;
        if($currentUserId === $timesheet->getUserId() || ($permissions && ($permissions[Acl::PERMISSION_EDIT] || $permissions[Acl::PERMISSION_MANAGE]))) {
            $canAccess = true;
        }
        if(!$canAccess) {
            return new DataResponse(['error' => 'Only board managers and editors can delete timesheets for others'], 403);
        }
        $this->mapper->delete($timesheet);

        $this->notificationHelper->sendDelete($timesheet);

        return new DataResponse(['message' => 'Deleted']);

    }
}
