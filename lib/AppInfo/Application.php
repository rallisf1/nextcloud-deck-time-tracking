<?php

declare(strict_types=1);

namespace OCA\DeckTimeTracking\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\User\Events\UserDeletedEvent;
use OCA\Deck\Event\CardDeletedEvent;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Service\PermissionService;
use OCA\DeckTimeTracking\Controller\TimesheetController;
use OCA\DeckTimeTracking\Listeners\BeforeTemplateRenderedListener;
use OCA\DeckTimeTracking\Listeners\AssigneeCleanupListener;
use OCA\DeckTimeTracking\Listeners\CardCleanupListener;
use OCA\DeckTimeTracking\Middleware\DeckResponseMiddleware;
use OCA\DeckTimeTracking\Notification\Notifier;
use OCA\DeckTimeTracking\Provider\CalendarProvider;
use OCA\DeckTimeTracking\Db\TimesheetMapper;
use Psr\Container\ContainerInterface;

class Application extends App implements IBootstrap {
	public const APP_ID = 'decktimetracking';

	public function __construct() {
		parent::__construct(self::APP_ID);

		$container = $this->getContainer();

		/**
		 * Controllers
		 */
		$container->registerService('TimesheetController', function(ContainerInterface $c){
			return new TimesheetController(
				$c->get('AppName'),
				$c->get('Request'),
				$c->get('TimesheetMapper'),
				$c->get('CardMapper'),
				$c->get(PermissionService::class),
				$c->get(AssignmentMapper::class),
				$c->get('IUserSession'),
				$c->get('NotificationHelper')
			);
		});

		/**
		 * Mappers
		 */
		$container->registerService('TimesheetMapper', function(ContainerInterface $c){
			return new TimesheetMapper($c->get('ServerContainer')->getDatabaseConnection());
		});

	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, BeforeTemplateRenderedListener::class);
		$context->registerEventListener(UserDeletedEvent::class, AssigneeCleanupListener::class);
		$context->registerEventListener(CardDeletedEvent::class, CardCleanupListener::class);
		$context->registerMiddleware(DeckResponseMiddleware::class, true);
		$context->registerCalendarProvider(CalendarProvider::class);
		$context->registerNotifierService(Notifier::class);
	}

	public function boot(IBootContext $context): void {
	}
}
