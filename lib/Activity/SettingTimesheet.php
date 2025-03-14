<?php

namespace OCA\DeckTimeTracking\Activity;

use OCA\Deck\Activity\SettingBase;
use OCA\DeckTimeTracking\AppInfo\Application;
use OCP\L10N\IFactory;
use OCP\IConfig;
use OCP\IUserSession;

class SettingTimesheet extends SettingBase {

	private IFactory $l10nFactory;
	private IConfig $config;
	private IUserSession $userSession;

	public function __construct(IFactory $l10nFactory, IConfig $config, IUserSession $userSession) {
		$this->l10nFactory = $l10nFactory;
		$this->config = $config;
		$this->userSession = $userSession;

		$languageCode = $this->config->getUserValue($this->userSession->getUser()->getUID(), 'core', 'lang', $this->l10nFactory->findLanguage());
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);

		parent::__construct($l);
	}

	/**
	 * @return string Lowercase a-z and underscore only identifier
	 * @since 11.0.0
	 */
	public function getIdentifier(): string {
		return 'timesheet';
	}

	/**
	 * @return string A translated string
	 * @since 11.0.0
	 */
	public function getName(): string {
		return $this->l->t('A <strong>timesheet record</strong> has been changed');
	}
}