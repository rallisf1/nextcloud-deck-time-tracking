<?php

namespace OCA\DeckTimeTracking\Provider;

use OCA\DeckTimeTracking\Calendar\Calendar;
use OCA\DeckTimeTracking\Calendar\TimesheetCalendarBackend;
use OCP\Calendar\ICalendarProvider;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class CalendarProvider implements ICalendarProvider {

	protected LoggerInterface $logger;
	protected IUserManager $userManager;
    protected TimesheetCalendarBackend $backend;

	public function __construct(LoggerInterface $logger, IUserManager $userManager, TimesheetCalendarBackend $backend) {
		$this->logger = $logger;
		$this->userManager = $userManager;
        $this->backend = $backend;

	}

    public function getCalendars(string $principalUri, array $calendarUris = []): array {
        $calendars = [];
        
		$currentUser = explode('/', $principalUri, 3);
		if (count($currentUser) < 3 || $currentUser[0] !== 'principals' || $currentUser[1] !== 'users') {
			$this->logger->warning('Invalid principal uri given', ['uri' => $principalUri]);
			return [];
		}

		$currentUser = $this->userManager->get($currentUser[2]);
		if ($currentUser === null) {
			$this->logger->warning('Unknown user given', ['uri' => $principalUri]);
			return [];
		}

        $userIds = $this->backend->getUserIds();

        if(count($userIds) > 0) {
            foreach($userIds as $userId) {
				$user = $this->userManager->get($userId);
				if($this->backend->canAccess($currentUser, $user)) {
					$calendars[] = new Calendar($currentUser, $user, $this->logger, $this->backend, $this->userManager);
				}
            }
        }

        return $calendars;
    }
}