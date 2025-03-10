<?php

declare(strict_types=1);

namespace OCA\DeckTimeTracking\Calendar;

use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\DeckTimeTracking\Db\TimesheetMapper;
use OCP\IURLGenerator;

class TimesheetCalendarBackend {
	private $timesheetMapper;
    private $cardMapper;
    private $boardMapper;
    private $urlGenerator;

	public function __construct(
		TimesheetMapper $timesheetMapper,
        CardMapper $cardMapper,
        BoardMapper $boardMapper,
        IURLGenerator $urlGenerator,
	) {
		$this->timesheetMapper = $timesheetMapper;
        $this->cardMapper = $cardMapper;
        $this->boardMapper = $boardMapper;
        $this->urlGenerator = $urlGenerator;
	}

    public function getUserIds() {
        $rows = $this->timesheetMapper->findCalendarUsers();
        $users = array_map(function ($row) {
            return $row['user_id'];
        }, $rows);
        return $users;
    }

    public function canAccess($currentUser, $user) {
        $currentUserBoardIds = $this->boardMapper->findBoardIds($currentUser->getUID());
        $userBoardIds = $this->boardMapper->findBoardIds($user->getUID());
        return count(array_intersect($currentUserBoardIds, $userBoardIds)) > 0;
    }

    public function getChildren($currentUserId, $userId, $filter) {
        $currentUserBoardIds = $this->boardMapper->findBoardIds($currentUserId);
        $userBoardIds = $this->boardMapper->findBoardIds($userId);
        $commonBoards = array_intersect($currentUserBoardIds, $userBoardIds);
        return $this->timesheetMapper->findByCommonBoards($commonBoards, $userId, $filter);
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