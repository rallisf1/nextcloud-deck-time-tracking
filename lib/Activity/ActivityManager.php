<?php

namespace OCA\DeckTimeTracking\Activity;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Service\PermissionService;
use OCA\DeckTimeTracking\AppInfo\Application;
use OCA\DeckTimeTracking\Db\TimesheetMapper;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\IUser;
use OCP\L10N\IFactory;

class ActivityManager {

	private IManager $manager;
	private PermissionService $permissionService;
	private TimesheetMapper $timesheetMapper;
	private CardMapper $cardMapper;
	private AssignmentMapper $assignmentMapper;
	private StackMapper $stackMapper;
	private BoardMapper $boardMapper;
	private IFactory $l10nFactory;

	public function __construct(
		IManager $manager,
		PermissionService $permissionsService,
		TimesheetMapper $timesheetMapper,
		CardMapper $cardMapper,
		StackMapper $stackMapper,
		BoardMapper $boardMapper,
		AssignmentMapper $assignmentMapper,
		IFactory $l10nFactory
	) {
		$this->manager = $manager;
		$this->permissionService = $permissionsService;
		$this->timesheetMapper = $timesheetMapper;
		$this->cardMapper = $cardMapper;
		$this->stackMapper = $stackMapper;
		$this->boardMapper = $boardMapper;
		$this->assignmentMapper = $assignmentMapper;
		$this->l10nFactory = $l10nFactory;
	}

	/**
	 * @param string $subjectIdentifier
	 * @param array $subjectParams
	 * @param bool $ownActivity
	 * @return string
	 */
	public function getActivityFormat($languageCode, $subjectIdentifier, $ownTimesheet, $ownActivity = false) {
		$subject = '';
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);

		switch ($subjectIdentifier) {
            case 'timer-start':
                $subject = $ownActivity ? $l->t('You started working on {board} > {stack} > {card}'): $l->t('{user} started working on {board} > {stack} > {card}');
                break;
            case 'timer-end':
                $subject = $ownActivity ? $l->t('You finished {description} on {board} > {stack} > {card}'): $l->t('{user} finished {description} on {board} > {stack} > {card}');
                break;
            case 'timesheet-edit':
                if($ownTimesheet){
                    $subject = $ownActivity ? $l->t('You updated your timesheet record {description} on {board} > {stack} > {card}'): $l->t('{user} updated your timesheet record {description} on {board} > {stack} > {card}');
                } else {
                    $subject = $ownActivity ? $l->t('You updated timesheet record {description} of {agent} on {board} > {stack} > {card}'): $l->t('{user} updated timesheet record {description} of {agent} on {board} > {stack} > {card}');
                }
                break;
            case 'timesheet-delete':
                if($ownTimesheet){
                    $subject = $ownActivity ? $l->t('You removed your timesheet record {description} on {board} > {stack} > {card}'): $l->t('{user} removed your timesheet record {description} on {board} > {stack} > {card}');
                } else {
                    $subject = $ownActivity ? $l->t('You removed timesheet record {description} of {agent} on {board} > {stack} > {card}'): $l->t('{user} removed timesheet record {description} of {agent} on {board} > {stack} > {card}');
                }
                break;
			default:
				break;
		}
		return $subject;
	}

	public function triggerEvent($timesheet, $type, $author) {
		try {
			$event = $this->createEvent($timesheet, $type, $author);
			if ($event !== null) {
				$this->sendToUsers($event);
			}
		} catch (\Exception $e) {
			// Ignore exception for undefined activities on update events
		}
	}

	/**
	 * @param Timesheet $timesheet
	 * @param string $type
	 * @param bool $ownTimesheet
	 * @param string $author
	 * @return IEvent|null
	 * @throws \Exception
	 */
	private function createEvent($timesheet, $type, $author) {
		$subjectParams = [];

        /** @var Card $card */
        $card = $this->cardMapper->find($timesheet->getCardId(), false);
        /** @var Int $boardId */
		$boardId = $this->cardMapper->findBoardId($card->getId());
		/** @var Board $board */
		$board = $this->boardMapper->find($boardId, false, false);
		/** @var Stack $stack */
		$stack = $this->stackMapper->findStackFromCardId($card->getId());

		$subjectParams['card'] = [
			'id' => $card->getId(),
			'title' => $card->getTitle()
		];

		$subjectParams['board'] = [
			'id' => $boardId,
			'title' => $board->getTitle()
		];

		$subjectParams['stack'] = [
			'id' => $stack->getId(),
			'title' => $stack->getTitle()
		];

		$event = $this->manager->generateEvent();
		$event->setApp('decktimetracking')
			->setType('timesheet')
			->setAuthor($author)
			->setObject('timesheet', (int)$timesheet->getId(), $timesheet->getDescription() ?? '')
			->setSubject($type, $subjectParams)
			->setTimestamp(time());

		return $event;
	}

	/**
	 * Publish activity to all users that can manage/edit the board or are assigned to the card
	 *
	 * @param IEvent $event
	 */
	private function sendToUsers(IEvent $event) {
		$timesheet = $this->timesheetMapper->find($event->getObjectId());
		$boardId = $this->cardMapper->findBoardId($timesheet->getCardId());
		/** @var IUser $user */
		foreach ($this->permissionService->findUsers($boardId) as $user) {
			if($this->canSeeCardActivity($boardId, $timesheet->getCardId(), $user->getUID())) {
				$event->setAffectedUser($user->getUID());
				/** @noinspection DisconnectedForeachInstructionInspection */
				$this->manager->publish($event);
			}
		}
	}

	public function canSeeCardActivity(int $boardId, int $cardId, string $userId): bool {
		try {
			$permissions = $this->permissionService->getPermissions($boardId, $userId);
			if($permissions[Acl::PERMISSION_EDIT] || $permissions[Acl::PERMISSION_MANAGE] || $this->assignmentMapper->isUserAssigned($cardId, $userId)) {
				$card = $this->cardMapper->find($cardId);
				return $card->getDeletedAt() === 0;
			}
			return false;
		} catch (NoPermissionException $e) {
			return false;
		}
	}

}