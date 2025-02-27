<?php

declare(strict_types=1);

namespace OCA\DeckTimeTracking\Listeners;

use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
use OCP\Util;

class BeforeTemplateRenderedListener implements IEventListener {
	private $request;

	public function __construct(IRequest $request) {
		$this->request = $request;
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			return;
		}

		if (!$event->isLoggedIn()) {
			return;
		}
		
        $pathInfo = $this->request->getPathInfo();

		if (str_starts_with($pathInfo, '/apps/deck')) {
			Util::addScript('decktimetracking', 'deck');
            Util::addStyle('decktimetracking', 'deck');
		}

    }
}
