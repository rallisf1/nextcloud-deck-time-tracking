<?php

namespace OCA\DeckTimeTracking\Notification;

use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\StackMapper;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {
	/** @var IFactory */
	protected $l10nFactory;
	/** @var IURLGenerator */
	protected $url;
	/** @var IUserManager */
	protected $userManager;
	/** @var CardMapper */
	protected $cardMapper;
	/** @var StackMapper */
	protected $stackMapper;
	/** @var BoardMapper */
	protected $boardMapper;

	public function __construct(
		IFactory $l10nFactory,
		IURLGenerator $url,
		IUserManager $userManager,
		CardMapper $cardMapper,
		StackMapper $stackMapper,
		BoardMapper $boardMapper,
	) {
		$this->l10nFactory = $l10nFactory;
		$this->url = $url;
		$this->userManager = $userManager;
		$this->cardMapper = $cardMapper;
		$this->stackMapper = $stackMapper;
		$this->boardMapper = $boardMapper;
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return 'decktimetracking';
	}

	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return 'Timesheet';
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 * @since 9.0.0
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
        $l = $this->l10nFactory->get('decktimetracking', $languageCode);
		if ($notification->getApp() !== 'decktimetracking') {
			throw new UnknownNotificationException();
		}
		$notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath('deck', 'deck-dark.svg')));
		$params = $notification->getSubjectParameters();

		$cardId = (int)$notification->getObjectId();
		$stack = $this->stackMapper->findStackFromCardId($cardId);
		$boardId = $stack ? (int)$stack->getBoardId() : null;
		if (!$boardId) {
			throw new AlreadyProcessedException();
		}

		switch ($notification->getSubject()) {
            case 'timer-start':
				$initiator = $this->userManager->get($params[0]);
				if ($initiator !== null) {
					$dn = $initiator->getDisplayName();
				} else {
					$dn = $params[0];
				}
				$notification->setParsedSubject(
					$l->t('User %s started working on %s > %s > %s', [$dn, $params[1], $stack->getTitle(), $params[2]])
				);
				$notification->setRichSubject(
					$l->t('{user} started working on {board} > {stack} > {card}'),
					[
						'card' => [
							'type' => 'deck-card',
							'id' => $cardId,
							'name' => $params[2],
							'boardname' => $params[1],
							'stackname' => $stack->getTitle(),
							'link' => $this->getCardUrl($boardId, $cardId),
						],
						'board' => [
							'type' => 'deck-board',
							'id' => $boardId,
							'name' => $params[1],
							'link' => $this->getBoardUrl($boardId),
						],
						'stack' => [
							'type' => 'highlight',
							'id' => $stack->getId(),
							'name' => $stack->getTitle(),
						],
						'user' => [
							'type' => 'user',
							'id' => $params[0],
							'name' => $dn,
						]
					]
				);
				break;
			case 'timer-end':
				$initiator = $this->userManager->get($params[0]);
				if ($initiator !== null) {
					$dn = $initiator->getDisplayName();
				} else {
					$dn = $params[0];
				}
				$desc = $params[3] !== null && strlen($params[3] > 3) ? $params[3] : 'working';
				$notification->setParsedSubject(
					$l->t('User %s finished %s on %s > %s > %s', [$dn, $desc, $params[1], $stack->getTitle(), $params[2]])
				);
				$notification->setRichSubject(
					$l->t('{user} finished {description} on {board} > {stack} > {card}'),
					[
						'card' => [
							'type' => 'deck-card',
							'id' => $cardId,
							'name' => $params[2],
							'boardname' => $params[1],
							'stackname' => $stack->getTitle(),
							'link' => $this->getCardUrl($boardId, $cardId),
						],
						'board' => [
							'type' => 'deck-board',
							'id' => $boardId,
							'name' => $params[1],
							'link' => $this->getBoardUrl($boardId),
						],
						'stack' => [
							'type' => 'highlight',
							'id' => $stack->getId(),
							'name' => $stack->getTitle(),
						],
						'user' => [
							'type' => 'user',
							'id' => $params[0],
							'name' => $dn,
						],
						'description' => [
							'type' => 'highlight',
							'id' => $notification->getObjectId(),
							'name' => $desc,
						]
					]
				);
				break;
			case 'timesheet-edit':
				$initiator = $this->userManager->get($params[0]);
				if ($initiator !== null) {
					$dn = $initiator->getDisplayName();
				} else {
					$dn = $params[0];
				}
				$agent = $this->userManager->get($params[4]);
				if ($agent !== null) {
					$an = $agent->getDisplayName();
				} else {
					$an = $params[4];
				}
				$desc = $params[3] !== null && strlen($params[3] > 3) ? $params[3] : '';
				$notification->setParsedSubject(
					$l->t('User %s updated timesheet record %s of %s on %s > %s > %s', [$dn, $desc, $an, $params[1], $stack->getTitle(), $params[2]])
				);
				$notification->setRichSubject(
					$l->t('{user} updated timesheet record {description} of {agent} on {board} > {stack} > {card}'),
					[
						'card' => [
							'type' => 'deck-card',
							'id' => $cardId,
							'name' => $params[2],
							'boardname' => $params[1],
							'stackname' => $stack->getTitle(),
							'link' => $this->getCardUrl($boardId, $cardId),
						],
						'board' => [
							'type' => 'deck-board',
							'id' => $boardId,
							'name' => $params[1],
							'link' => $this->getBoardUrl($boardId),
						],
						'user' => [
							'type' => 'user',
							'id' => $params[0],
							'name' => $dn,
						],
						'stack' => [
							'type' => 'highlight',
							'id' => $stack->getId(),
							'name' => $stack->getTitle(),
						],
						'agent' => [
							'type' => 'user',
							'id' => $params[4],
							'name' => $an,
						],
						'description' => [
							'type' => 'highlight',
							'id' => $notification->getObjectId(),
							'name' => $desc,
						]
					]
				);
				break;
			case 'timesheet-delete':
				$initiator = $this->userManager->get($params[0]);
				if ($initiator !== null) {
					$dn = $initiator->getDisplayName();
				} else {
					$dn = $params[0];
				}
				$agent = $this->userManager->get($params[4]);
				if ($agent !== null) {
					$an = $agent->getDisplayName();
				} else {
					$an = $params[4];
				}
				$desc = $params[3] !== null && strlen($params[3] > 3) ? $params[3] : '';
				$notification->setParsedSubject(
					$l->t('User %s removed timesheet record %s of %s on %s > %s > %s', [$dn, $desc, $an, $params[1], $stack->getTitle(), $params[2]])
				);
				$notification->setRichSubject(
					$l->t('{user} removed timesheet record {description} of {agent} on {board} > {stack} > {card}'),
					[
						'card' => [
							'type' => 'deck-card',
							'id' => $cardId,
							'name' => $params[2],
							'boardname' => $params[1],
							'stackname' => $stack->getTitle(),
							'link' => $this->getCardUrl($boardId, $cardId),
						],
						'board' => [
							'type' => 'deck-board',
							'id' => $boardId,
							'name' => $params[1],
							'link' => $this->getBoardUrl($boardId),
						],
						'user' => [
							'type' => 'user',
							'id' => $params[0],
							'name' => $dn,
						],
						'stack' => [
							'type' => 'highlight',
							'id' => $stack->getId(),
							'name' => $stack->getTitle(),
						],
						'agent' => [
							'type' => 'user',
							'id' => $params[4],
							'name' => $an,
						],
						'description' => [
							'type' => 'highlight',
							'id' => $notification->getObjectId(),
							'name' => $desc,
						]
					]
				);
				break;
			case 'timer-reminder':
				$notification->setParsedSubject(
					$l->t('Did you forget your timer on %s > %s > %s ?', [$params[0], $stack->getTitle(), $params[1]])
				);
				$notification->setRichSubject(
					$l->t('Did you forget your timer on {board} > {stack} > {card} ?'),
					[
						'card' => [
							'type' => 'deck-card',
							'id' => $cardId,
							'name' => $params[1],
							'boardname' => $params[0],
							'stackname' => $stack->getTitle(),
							'link' => $this->getCardUrl($boardId, $cardId),
						],
						'board' => [
							'type' => 'deck-board',
							'id' => $boardId,
							'name' => $params[0],
							'link' => $this->getBoardUrl($boardId),
						],
						'stack' => [
							'type' => 'highlight',
							'id' => $stack->getId(),
							'name' => $stack->getTitle(),
						],
					]
				);
				break;
		}
		$notification->setLink($this->getCardUrl($boardId, $cardId));
		return $notification;
	}

	private function getBoardUrl(int $boardId): string {
		return $this->url->linkToRouteAbsolute('deck.page.indexBoard', ['boardId' => $boardId]);
	}

	private function getCardUrl(int $boardId, int $cardId): string {
		return $this->url->linkToRouteAbsolute('deck.page.indexCard', ['boardId' => $boardId, 'cardId' => $cardId]);
	}
}