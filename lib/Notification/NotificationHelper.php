<?php

declare(strict_types=1);

namespace OCA\DeckTimeTracking\Notification;

use DateTime;
use Exception;
use OCA\DeckTimeTracking\Db\Timesheet;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\User;
use OCA\Deck\Service\PermissionService;
use OCA\DeckTimeTracking\Db\TimesheetMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Notification\IManager;
use OCP\IUserSession;

class NotificationHelper {

	protected CardMapper $cardMapper;
	protected BoardMapper $boardMapper;
	protected AssignmentMapper $assignmentMapper;
	protected PermissionService $permissionService;
	protected IManager $notificationManager;
	protected TimesheetMapper $timesheetMapper;
    protected IUserSession $userSession;
	private array $boards = [];

	public function __construct(
		CardMapper $cardMapper,
		BoardMapper $boardMapper,
		AssignmentMapper $assignmentMapper,
		PermissionService $permissionService,
		IManager $notificationManager,
		TimesheetMapper $timesheetMapper,
        IUserSession $userSession
	) {
		$this->cardMapper = $cardMapper;
		$this->boardMapper = $boardMapper;
		$this->assignmentMapper = $assignmentMapper;
		$this->permissionService = $permissionService;
		$this->notificationManager = $notificationManager;
		$this->timesheetMapper = $timesheetMapper;
		$this->userSession = $userSession;
	}

    public function sendStart(Timesheet $timesheet): void {
        /** @var Card $card */
        $card = $this->cardMapper->find($timesheet->getCardId(), false);
        /** @var Int $boardId */
		$boardId = $this->cardMapper->findBoardId($card->getId());
        try {
            /** @var Board $board */
            $board = $this->getBoard($boardId, false, true);
        } catch (Exception $e) {
			return;
		}
        /** @var User $user */
		foreach ($this->permissionService->findUsers($boardId) as $user) {
            if($user->getUID() === $timesheet->getUserId()) continue; // don't shoot the messenger
            if($user->getUID() === $board->getOwner() || $this->assignmentMapper->isUserAssigned($card->getId(), $user->getUID())) {
                $notification = $this->notificationManager->createNotification();
                $notification
                    ->setApp('decktimetracking')
                    ->setUser((string)$user->getUID())
                    ->setObject('timesheet', (string)$timesheet->getId())
                    ->setSubject('timer-start', [
						$timesheet->getUserId(), $board->getTitle(), $card->getTitle()
					])
					->setDateTime($timesheet->getStart());
                $this->notificationManager->notify($notification);
            }
        }
    }

    public function sendEnd(Timesheet $timesheet): void {
        /** @var Card $card */
        $card = $this->cardMapper->find($timesheet->getCardId(), false);
        /** @var Int $boardId */
		$boardId = $this->cardMapper->findBoardId($card->getId());
        try {
            /** @var Board $board */
            $board = $this->getBoard($boardId, false, true);
        } catch (Exception $e) {
			return;
		}
        /** @var User $user */
		foreach ($this->permissionService->findUsers($boardId) as $user) {
            if($user->getUID() === $timesheet->getUserId()) continue; // don't shoot the messenger
            $permissions = $this->permissionService->getPermissions($boardId, $user->getUID());
            if($permissions[Acl::PERMISSION_EDIT] || $permissions[Acl::PERMISSION_MANAGE] || $this->assignmentMapper->isUserAssigned($card->getId(), $user->getUID())) {
                $notification = $this->notificationManager->createNotification();
                $notification
                    ->setApp('decktimetracking')
                    ->setUser((string)$user->getUID())
                    ->setObject('timesheet', (string)$timesheet->getId())
                    ->setSubject('timer-end', [
						$timesheet->getUserId(), $board->getTitle(), $card->getTitle(), $timesheet->getDescription()
					])
					->setDateTime($timesheet->getEnd());

                $this->notificationManager->notify($notification);
            }
        }
    }

    // Also used for start as it is the same code
    public function sendEdit(Timesheet $timesheet): void {
        /** @var Card $card */
        $card = $this->cardMapper->find($timesheet->getCardId(), false);
        /** @var Int $boardId */
		$boardId = $this->cardMapper->findBoardId($card->getId());
        try {
            /** @var Board $board */
            $board = $this->getBoard($boardId, false, true);
        } catch (Exception $e) {
			return;
		}
        /** @var User $user */
		foreach ($this->permissionService->findUsers($boardId) as $user) {
            $currentUserId = $this->userSession->getUser()->getUID();
            if($user->getUID() === $currentUserId) continue; // don't shoot the messenger
            $permissions = $this->permissionService->getPermissions($boardId, $user->getUID());
            if($permissions[Acl::PERMISSION_EDIT] || $permissions[Acl::PERMISSION_MANAGE] || $this->assignmentMapper->isUserAssigned($card->getId(), $user->getUID())) {
                $notification = $this->notificationManager->createNotification();
                $notification
                    ->setApp('decktimetracking')
                    ->setUser((string)$user->getUID())
                    ->setObject('timesheet', (string)$timesheet->getId())
                    ->setSubject('timesheet-edit', [
						$currentUserId, $board->getTitle(), $card->getTitle(), $timesheet->getDescription(), $timesheet->getUserId()
					])
					->setDateTime(new DateTime());
                $this->notificationManager->notify($notification);
            }
        }
    }

    public function sendDelete(Timesheet $timesheet): void {
        /** @var Card $card */
        $card = $this->cardMapper->find($timesheet->getCardId(), false);
        /** @var Int $boardId */
		$boardId = $this->cardMapper->findBoardId($card->getId());
        try {
            /** @var Board $board */
            $board = $this->getBoard($boardId, false, true);
        } catch (Exception $e) {
			return;
		}
        /** @var User $user */
		foreach ($this->permissionService->findUsers($boardId) as $user) {
            $currentUserId = $this->userSession->getUser()->getUID();
            if($user->getUID() === $currentUserId) continue; // don't shoot the messenger
            $permissions = $this->permissionService->getPermissions($boardId, $user->getUID());
            if($permissions[Acl::PERMISSION_EDIT] || $permissions[Acl::PERMISSION_MANAGE] || $this->assignmentMapper->isUserAssigned($card->getId(), $user->getUID())) {
                $notification = $this->notificationManager->createNotification();
                $notification
                    ->setApp('decktimetracking')
                    ->setUser((string)$user->getUID())
                    ->setObject('timesheet', (string)$timesheet->getId())
                    ->setSubject('timesheet-delete', [
						$currentUserId, $board->getTitle(), $card->getTitle(), $timesheet->getDescription(), $timesheet->getUserId()
					])
					->setDateTime(new DateTime());
                $this->notificationManager->notify($notification);
            }
        }
    }

    public function sendReminder(Timesheet $timesheet): void {
        /** @var Card $card */
        $card = $this->cardMapper->find($timesheet->getCardId(), false);
        /** @var Int $boardId */
		$boardId = $this->cardMapper->findBoardId($card->getId());
        try {
            /** @var Board $board */
            $board = $this->getBoard($boardId, false, true);
        } catch (Exception $e) {
			return;
		}
        $notification = $this->notificationManager->createNotification();
        $notification
            ->setApp('decktimetracking')
            ->setUser((string)$timesheet->getUserId())
            ->setObject('timesheet', (string)$timesheet->getId())
            ->setSubject('timer-reminder', [
                $board->getTitle(), $card->getTitle()
            ])
            ->setDateTime($timesheet->getStart());
        $this->notificationManager->notify($notification);
        // set the reminder date so it doesn't trigger again
        $timesheet->setReminder(new DateTime());
        $this->timesheetMapper->update($timesheet);
    }

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	private function getBoard(int $boardId, bool $withLabels = false, bool $withAcl = false): Board {
		if (!array_key_exists($boardId, $this->boards)) {
			$this->boards[$boardId] = $this->boardMapper->find($boardId, $withLabels, $withAcl);
		}
		return $this->boards[$boardId];
	}
}