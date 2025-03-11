<?php

declare(strict_types=1);

namespace OCA\DeckTimeTracking;

use OCA\Deck\Db\Card;

class CustomCard extends Card {
    protected $timesheets;

    public function __construct(Card $card) {
        parent::__construct();
        $this->addRelation('timesheets');

        // id is privately inherited from Entity, thus we need to set it manually
        $this->setId($card->getId());

        // we can clone the rest of the Card properties from its class itself via a Reflection object
        $reflection = new \ReflectionClass($card);

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PROTECTED) as $property) {
            $name = $property->getName();
            $getter = 'get' . ucfirst($name);
            $setter = 'set' . ucfirst($name);
            $this->$setter($card->$getter());
        }
    }
    /*
    public function getAllAssignedUsers(Card $card) {

    }


	private function getOrigin(Assignment $assignment) {
		if ($assignment->getType() === Assignment::TYPE_USER) {
			$origin = $this->userManager->userExists($assignment->getParticipant());
			return $origin ? new User($assignment->getParticipant(), $this->userManager) : null;
		}
		if ($assignment->getType() === Assignment::TYPE_GROUP) {
			$origin = $this->groupManager->get($assignment->getParticipant());
			return $origin ? new Group($origin) : null;
		}
		if ($assignment->getType() === Assignment::TYPE_CIRCLE) {
			$origin = $this->circleService->getCircle($assignment->getParticipant());
			return $origin ? new Circle($origin) : null;
		}
		return null;
	}
    */
}