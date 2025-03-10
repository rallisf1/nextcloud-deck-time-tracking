<?php

namespace OCA\DeckTimeTracking\Cron;

use OCA\DeckTimeTracking\Db\Timesheet;
use OCA\DeckTimeTracking\Db\TimesheetMapper;
use OCA\DeckTimeTracking\Notification\NotificationHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\Job;
use Psr\Log\LoggerInterface;

class ScheduledNotifications extends Job {

	public function __construct(
		ITimeFactory $time,
		protected TimesheetMapper $timesheetMapper,
		protected NotificationHelper $notificationHelper,
		protected LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	/**
	 * @param $argument
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function run($argument) {
		// Notify timesheet owner about forgotten timers
		$timesheets = $this->timesheetMapper->findForgotten();
		/** @var Timesheet $timesheet */
		foreach ($timesheets as $timesheet) {
			try {
				$this->notificationHelper->sendReminder($timesheet);
			} catch (DoesNotExistException $e) {
				// Skip if any error occurs
				$this->logger->debug('Could not create reminder notification for timesheet with id ' . $timesheet->getId());
			}
		}
	}
}