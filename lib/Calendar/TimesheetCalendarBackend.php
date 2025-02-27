<?php

declare(strict_types=1);

namespace OCA\DeckTimeTracking\Calendar;

use OCA\Deck\Db\CardMapper;
use OCA\DeckTimeTracking\Db\TimesheetMapper;
use OCP\IUserManager;
use OCP\IURLGenerator;

class TimesheetCalendarBackend {
	private $timesheetMapper;
    private $cardMapper;
    private $userManager;
    private $urlGenerator;

	public function __construct(
		TimesheetMapper $timesheetMapper,
        CardMapper $cardMapper,
        IUserManager $userManager,
        IURLGenerator $urlGenerator,
	) {
		$this->timesheetMapper = $timesheetMapper;
        $this->cardMapper = $cardMapper;
        $this->userManager = $userManager;
        $this->urlGenerator = $urlGenerator;
	}

    public function getUserIds() {
        $timesheets = $this->timesheetMapper->findForCalendar();
        $users = array_map(function ($timesheet) {
            return $timesheet->getUserId();
        }, $timesheets);
        return $users;
    }

    public function canAccess($currentUser, $user, $boardId = 0) {
        $timesheets = $this->timesheetMapper->findByPermissions($currentUser->getUID(), $user->getUID(), $boardId);
        return count($timesheets) > 0;
    }

    public function getChildren($currentUserId, $userId, $filter) {
        return $this->timesheetMapper->findByPermissions($currentUserId, $userId, 0, $filter);
    }

    public function getChild($calendar_id) {
        $timesheet_id = (int)str_replace('timesheet-record-', '', $calendar_id);
        return $this->timesheetMapper->find($timesheet_id);
    }

    public function findByName($filename) {
        $timesheet_id = (int)str_replace(['timesheet-record-', '.ics'], '', $filename);
        return $this->timesheetMapper->find($timesheet_id);
    }

    public function getCard($cardId) {
        return $this->cardMapper->find($cardId, false);
    }

    public function getCardUrl($cardId) {
        $boardId = $this->cardMapper->findBoardId($cardId);
        return $this->urlGenerator->linkToRouteAbsolute('deck.page.indexCard', ['boardId' => $boardId, 'cardId' => $cardId]);
    }

}