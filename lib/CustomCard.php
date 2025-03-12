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
    
}