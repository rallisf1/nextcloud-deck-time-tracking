<?php

namespace OCA\DeckTimeTracking\Listeners;

use OCA\DeckTimeTracking\Db\TimesheetMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCA\Deck\Event\CardDeletedEvent;

class CardCleanupListener implements IEventListener {
	private TimesheetMapper $timesheetMapper;

	public function __construct(TimesheetMapper $timesheetMapper) {
		$this->timesheetMapper = $timesheetMapper;
	}

	public function handle(Event $event): void {
		if ($event instanceof CardDeletedEvent) {
			$timesheets = $this->timesheetMapper->findByCardId($event->getCard()->getId());
			foreach ($timesheets as $timesheet) {
				$this->timesheetMapper->delete($timesheet);
			}
		}
	}
}