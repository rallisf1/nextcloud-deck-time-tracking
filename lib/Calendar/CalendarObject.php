<?php

namespace OCA\DeckTimeTracking\Calendar;

use OCA\DeckTimeTracking\Calendar\TimesheetCalendarBackend;
use OCA\DeckTimeTracking\Db\Timesheet;
use OCP\IUser;
use Sabre\CalDAV\ICalendarObject;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAVACL\IACL;
use Sabre\VObject\Component\VCalendar;

class CalendarObject implements ICalendarObject, IACL {

	private $calendar;
	private $name;
	private $sourceItem;
	private $user;
	private $backend;
	public $calendarObject;

	public function __construct(Calendar $calendar, string $name, Timesheet $timesheet, IUser $user, TimesheetCalendarBackend $backend) {
		$this->calendar = $calendar;
		$this->name = $name;
		$this->sourceItem = $timesheet;
		$this->user = $user;
		$this->backend = $backend;
		$this->calendarObject = $this->getCalendarObject();
	}

	private function getCalendarObject(): VCalendar {
		$calendar = new VCalendar();
        $card = $this->backend->getCard($this->sourceItem->getCardId());
		$event = $calendar->createComponent('VEVENT');
		$event->UID = 'timesheet-record-' . $this->sourceItem->getId();
        $event->DTSTART = $this->sourceItem->getStart();
		$event->add('RELATED-TO', 'deck-card-' . $card->getId());
		$event->LOCATION = $this->backend->getCardUrl($card->getId());
		
		if ($this->sourceItem->getEnd() !== null) {
			$event->STATUS = 'COMPLETED';
			$event->DTEND = $this->sourceItem->getEnd();
		} else {
			$event->STATUS = 'IN-PROCESS';
		}

		$labels = $card->getLabels() ?? [];
		$event->CATEGORIES = array_map(function ($label): string {
			return $label->getTitle();
		}, $labels);

		$event->SUMMARY = $this->user->getDisplayName() . ': ' . $card->getTitle();
		$event->DESCRIPTION = $this->sourceItem->getDescription();
		$calendar->add($event);
		return $calendar;
	}

	public function getOwner() {
		return null;
	}

	public function getGroup() {
		return null;
	}

	public function getACL() {
		return $this->calendar->getACL();
	}

	public function setACL(array $acl) {
		throw new Forbidden('Setting ACL is not supported on this node');
	}

	public function getSupportedPrivilegeSet() {
		return null;
	}

	public function put($data) {
		throw new Forbidden('This calendar-object is read-only');
	}

	public function get() {
		if ($this->sourceItem) {
			return $this->calendarObject->serialize();
		}
	}

	public function getContentType() {
		return 'text/calendar; charset=utf-8';
	}

	public function getETag() {
		return '"' . md5($this->sourceItem->getLastModified()) . '"';
	}

	public function getSize() {
		return mb_strlen($this->calendarObject->serialize());
	}

	public function delete() {
		throw new Forbidden('This calendar-object is read-only');
	}

	public function getName() {
		return $this->name;
	}

	public function setName($name) {
		throw new Forbidden('This calendar-object is read-only');
	}

	public function getLastModified() {
		return $this->sourceItem->getLastModified();
	}

}