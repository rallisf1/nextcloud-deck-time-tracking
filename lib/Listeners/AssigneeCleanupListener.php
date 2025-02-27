<?php

namespace OCA\DeckTimeTracking\Listeners;

use OCA\DeckTimeTracking\Db\TimesheetMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;

class AssigneeCleanupListener implements IEventListener {
	private TimesheetMapper $timesheetMapper;

	public function __construct(TimesheetMapper $timesheetMapper) {
		$this->timesheetMapper = $timesheetMapper;
	}

	public function handle(Event $event): void {
		if ($event instanceof UserDeletedEvent) {
			$timesheets = $this->timesheetMapper->findByUserId($event->getUser()->getUID());
			foreach ($timesheets as $timesheet) {
				$this->timesheetMapper->delete($timesheet);
			}
		}
	}
}