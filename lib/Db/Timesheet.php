<?php

declare(strict_types=1);

namespace OCA\DeckTimeTracking\Db;

use OCP\AppFramework\Db\Entity;
use \DateTime;

/**
 * @method int getCardId()
 * @method void setCardId(int $cardId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getUserName()
 * @method void setUserName(string $userId)
 * @method \DateTime getStart()
 * @method void setStart(\DateTime $start)
 * @method ?\DateTime getEnd()
 * @method void setEnd(?\DateTime $end)
 * @method ?string getDescription()
 * @method void setDescription(?string $description)
 */
class Timesheet extends Entity implements \JsonSerializable {
    // just wanted to say that this is really stupid that you can't have undefined typed properties, it kinda defeats the purpose...
    protected $cardId;
    protected $userId;
    protected $start;
    protected ?\DateTime $end = null;
    protected ?string $description = null;

    public function __construct() {
        $this->addType('userId', 'string');
        $this->addType('cardId', 'integer');
        $this->addType('description', 'string');
        $this->addType('start', 'datetime');
        $this->addType('end', 'datetime');
    }

	public function getLastModified() {
		return $this->end === null ? $this->start : $this->end;
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'user_id' => $this->userId,
            'card_id' => $this->cardId,
			'start' => $this->start,
			'end' => $this->end,
			'description' => $this->description,
		];
	}
}
