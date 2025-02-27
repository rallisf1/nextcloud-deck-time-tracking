<?php

namespace OCA\DeckTimeTracking\Calendar;

use OCA\DeckTimeTracking\Calendar\CalendarObject;
use OCA\DeckTimeTracking\Calendar\TimesheetCalendarBackend;
use OCP\Constants;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Calendar\ICalendar;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class Calendar implements ICalendar {

	protected IUser $user;
	protected IUser $currentUser;
	protected LoggerInterface $logger;
	protected TimesheetCalendarBackend $backend;
	protected IUserManager $userManager;

	public function __construct(
		IUser $currentUser,
		IUser $user,
		LoggerInterface $logger,
		TimesheetCalendarBackend $backend,
		IUserManager $userManager
	) {
		$this->currentUser = $currentUser;
		$this->user = $user;
		$this->logger = $logger;
		$this->backend = $backend;
		$this->userManager = $userManager;
	}

	public function getDisplayColor(): ?string {
		return '#ffab00';
	}

	public function getDisplayName(): ?string {
		return 'Timesheet: ' . $this->user->getDisplayName();
	}

	public function getKey(): string {
		return 'timesheet-' . $this->user->getUID();
	}

	public function getPermissions(): int {
		return Constants::PERMISSION_ALL;
	}

	public function getUri(): string {
		return 'timesheet-calendar-'. $this->user->getUID();
	}

	public function isDeleted(): bool {
		return false;
	}

	public function search(string $pattern, array $searchProperties = [], array $options = [], ?int $limit = null, ?int $offset = null): array {
		$result = [];
		$timesheets = [];
		$filter = [];
		$this->logger->debug("Request calendar", ["pattern" => $pattern, "props" => $searchProperties]);
		try {
			if (empty($searchProperties) || $pattern === "") {
				if(strpos($_SERVER['REQUEST_URI'], 'timesheet-calendar-')) {
					// sadly $this->user doesn't play nice here, let's fetch the UUID from the request url
					preg_match('/.*timesheet-calendar-(.*)$/', $_SERVER['REQUEST_URI'], $matches);
					if(!count($matches)) return [];
					$userId = str_replace('/', '', $matches[1]);
					if(!$userId) return [];
					$this->user = $this->userManager->get($userId);
					if($this->user === null) return [];
					if(array_key_exists('timerange', $options)){
						$filter['start'] = $options['timerange']->start->format('Y-m-d H:i:s');
						$filter['end'] = $options['timerange']->end->format('Y-m-d H:i:s');
					}
					$timesheets = $this->backend->getChildren($this->currentUser->getUID(), $userId, $filter);
				}
			} else {
				if (in_array('X-FILENAME', $searchProperties)) {
					$timesheets[] = $this->backend->findByName($pattern);
				}
				if (in_array('UID', $searchProperties)) {
					$timesheets[] = $this->backend->getChild($pattern);
				}
			}
		} catch (DoesNotExistException $_) {
			return [];
		}

		foreach($timesheets as $timesheet) {
			$obj = new CalendarObject($this, 'timesheet-record-' . $timesheet->getId() . '.ics', $timesheet, $this->user, $this->backend);
			$type = $obj->calendarObject->getComponents()[0]->name;
			$result[] = $obj->calendarObject->$type;
		}

		$this->logger->debug($this->getDisplayName() . " calendar found " . count($result) . " records");

		return $result;
	}

}