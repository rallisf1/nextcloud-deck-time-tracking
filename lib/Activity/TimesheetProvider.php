<?php

namespace OCA\DeckTimeTracking\Activity;

use OCA\Deck\NoPermissionException;
use OCA\DeckTimeTracking\Db\TimesheetMapper;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;
use OCP\Activity\IProvider;
use OCP\IURLGenerator;
use OCP\IUserManager;

class TimesheetProvider implements IProvider {

	private string $userId;
	private IURLGenerator $urlGenerator;
	private ActivityManager $activityManager;
	private IUserManager $userManager;
	private TimesheetMapper $timesheetMapper;
	private IURLGenerator $url;

	public function __construct(
        IURLGenerator $urlGenerator,
        ActivityManager $activityManager,
        IUserManager $userManager,
        TimesheetMapper $timesheetMapper,
        $userId,
        IURLGenerator $url
    ){
		$this->userId = $userId;
		$this->urlGenerator = $urlGenerator;
		$this->activityManager = $activityManager;
		$this->timesheetMapper = $timesheetMapper;
		$this->userManager = $userManager;
        $this->url = $url;
	}

	/**
	 * @param string $language The language which should be used for translating, e.g. "en"
	 * @param IEvent $event The current event which should be parsed
	 * @param IEvent|null $previousEvent A potential previous event which you can combine with the current one.
	 *                                   To do so, simply use setChildEvent($previousEvent) after setting the
	 *                                   combined subject on the current event.
	 * @return IEvent
	 * @throws UnknownActivityException Should be thrown if your provider does not know this event
	 * @since 11.0.0
	 */
	public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
		if ($event->getApp() !== 'decktimetracking') {
			throw new UnknownActivityException();
		}

		$event = $this->getIcon($event);

		$subjectIdentifier = $event->getSubject();
		$subjectParams = $event->getSubjectParameters();
		$ownActivity = ($event->getAuthor() === $this->userId);

		/**
		 * Map stored parameter objects to rich string types
		 */

		$author = $event->getAuthor();

		$user = $this->userManager->get($author);
		$params = [];
		if ($user !== null) {
			$params = [
				'user' => [
					'type' => 'user',
					'id' => $author,
					'name' => $user->getDisplayName()
				],
			];
			$event->setAuthor($author);
		} else {
			$params = [
				'user' => [
					'type' => 'user',
					'id' => 'deleted_users',
					'name' => 'deleted_users',
				]
			];
		}

        if(!isset($subjectParams['card']) || !isset($subjectParams['stack']) || !isset($subjectParams['board'])) {
            throw new \InvalidArgumentException("card or stack or board data missing");
        }

        if (!$this->activityManager->canSeeCardActivity($subjectParams['board']['id'], $subjectParams['card']['id'], $event->getAffectedUser())) {
            throw new NoPermissionException('You cannot see this timesheet activity');
        }

        if ($event->getObjectName() === '') {
            $event->setObject($event->getObjectType(), $event->getObjectId(), $subjectParams['card']['title']);
        }

        $params['card'] = [
            'type' => 'highlight',
            'id' => (string)$subjectParams['card']['id'],
            'name' => $subjectParams['card']['title'],
            'link' => $this->getCardUrl($subjectParams['board']['id'], $subjectParams['card']['id']),
        ];

        $params['stack'] = [
            'type' => 'highlight',
            'id' => (string)$subjectParams['stack']['id'],
            'name' => $subjectParams['stack']['title'],
        ];

        $params['board'] = [
            'type' => 'highlight',
            'id' => (string)$subjectParams['board']['id'],
            'name' => $subjectParams['board']['title'],
            'link' => $this->getBoardUrl($subjectParams['board']['id']),
        ];

        $params['agent'] = [
            'type' => 'user',
            'id' => 'deleted_users',
            'name' => 'deleted_users',
        ];

		$timesheet = $this->timesheetMapper->find($event->getObjectId());

        $params['description'] = [
            'type' => 'highlight',
            'id' => (string)$event->getObjectId(),
            'name' => $timesheet->getDescription() ?? 'working',
        ];

        $ownTimesheet = false;

        if($timesheet !== null) {
            $agent = $this->userManager->get($timesheet->getUserId());
            if($agent !== null) {
                $params['agent'] = [
                    'type' => 'user',
                    'id' => $agent->getUID(),
                    'name' => $agent->getDisplayName()
                ];
                $ownTimesheet = ($timesheet->getUserId() === $agent->getUID());
            }
        }

        $event->setLink($params['card']['link']);

		try {
			$subject = $this->activityManager->getActivityFormat($language, $subjectIdentifier, $ownTimesheet, $ownActivity);
			$this->setSubjects($event, $subject, $params);
		} catch (\Exception $e) {
		}
		return $event;
	}

	/**
	 * @param IEvent $event
	 * @param string $subject
	 * @param array $parameters
	 */
	protected function setSubjects(IEvent $event, $subject, array $parameters) {
		$placeholders = $replacements = $richParameters = [];
		foreach ($parameters as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			if (is_array($parameter) && array_key_exists('name', $parameter)) {
				$replacements[] = $parameter['name'];
				$richParameters[$placeholder] = $parameter;
			} else {
				$replacements[] = '';
			}
		}

		$event->setParsedSubject(str_replace($placeholders, $replacements, $subject))
			->setRichSubject($subject, $richParameters);
		$event->setSubject($subject, $parameters);
	}

	private function getIcon(IEvent $event) {
		$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('deck', 'deck-dark.svg')));
		return $event;
	}

	private function getBoardUrl(int $boardId): string {
		return $this->url->linkToRouteAbsolute('deck.page.indexBoard', ['boardId' => $boardId]);
	}

	private function getCardUrl(int $boardId, int $cardId): string {
		return $this->url->linkToRouteAbsolute('deck.page.indexCard', ['boardId' => $boardId, 'cardId' => $cardId]);
	}
}