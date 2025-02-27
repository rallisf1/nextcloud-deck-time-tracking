<?php

declare(strict_types=1);

namespace OCA\DeckTimeTracking\Middleware;

use OCP\AppFramework\Middleware;
use OCP\AppFramework\Http\Response;
use OCA\Deck\Controller\StackController;
use OCA\DeckTimeTracking\Db\TimesheetMapper;
use OCA\DeckTimeTracking\CustomCard;

class DeckResponseMiddleware extends Middleware {
    private TimesheetMapper $timesheetMapper;

    public function __construct(TimesheetMapper $timesheetMapper) {
        $this->timesheetMapper = $timesheetMapper;
    }

    public function afterController($controller, $methodName, Response $response): Response {
        if(get_class($controller) === StackController::class && $methodName === 'index' && is_array($response->getData())) {
            $data = $response->getData();

            foreach ($data as &$stack) {
                $cards = $stack->getCards();
                $newCards = [];
                foreach ($cards as &$card) {
                    $newCard = new CustomCard($card);
                    $newCard->setTimesheets($this->timesheetMapper->findByCardId($card->getId()));
                    $newCards[] = $newCard;
                }
                $stack->setCards($newCards);
            }
    
            $response->setData($data);
        }
        return $response;
    }
}
