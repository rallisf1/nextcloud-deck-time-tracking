<?php

declare(strict_types=1);

namespace OCA\DeckTimeTracking\Controller;

use OCA\DeckTimeTracking\Db\Timesheet;
use OCA\DeckTimeTracking\Db\TimesheetMapper;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class TimesheetController extends ApiController {
    private TimesheetMapper $mapper;
    private IUserSession $userSession;

    public function __construct(
        string $AppName,
        IRequest $request,
        TimesheetMapper $mapper,
        IUserSession $userSession
    ) {
        parent::__construct($AppName, $request);
        $this->mapper = $mapper;
        $this->userSession = $userSession;
    }

    /**
     * Get all time records for a given card.
     */
    public function getCardTimesheets(int $cardId): DataResponse {
        $timesheets = $this->mapper->findByCardId($cardId);
        return new DataResponse($timesheets);
    }

    /**
     * Start a new time tracking entry.
     */
    public function startTracking(int $cardId): DataResponse {
        $userId = $this->userSession->getUser()->getUID();
        $timesheet = new Timesheet();
        $timesheet->setCardId($cardId);
        $timesheet->setUserId($userId);
        $timesheet->setStart(new \DateTime());

        $savedTimesheet = $this->mapper->insert($timesheet);
        return new DataResponse($savedTimesheet);
    }

    /**
     * Stop the active time tracking entry and add a description.
     */
    public function stopTracking(int $id, string $description = ''): DataResponse {
        $timesheet = $this->mapper->find($id);
        if ($timesheet === null) {
            return new DataResponse(['error' => 'Entry not found'], 404);
        }
        if ($timesheet->getUserId() !== $this->userSession->getUser()->getUID()) {
            return new DataResponse(['error' => 'Only the owner can stop his timer'], 403);
        }
        $timesheet->setEnd(new \DateTime());
        $timesheet->setDescription($description);
        $updatedTimesheet = $this->mapper->update($timesheet);

        return new DataResponse($updatedTimesheet);
    }

    /**
     * Create a new timesheet entry from the timesheet tab.
     */
    public function createTimesheet(int $cardId, string $userId, string $start, string|null $end, string $description): DataResponse {
        $timesheet = new Timesheet();
        $timesheet->setCardId($cardId);
        $timesheet->setUserId($userId);
        $timesheet->setStart($start);
        $timesheet->setEnd($end);
        $timesheet->setDescription($description);

        $savedTimesheet = $this->mapper->insert($timesheet);
        return new DataResponse($savedTimesheet);
    }

    /**
     * Edit a timesheet entry from the timesheet tab.
     */
    public function editTimesheet(int $id, string $userId, string $start, string|null $end, string $description): DataResponse {
        $timesheet = $this->mapper->find($id);
        if ($timesheet === null) {
            return new DataResponse(['error' => 'Entry not found'], 404);
        }
        $timesheet->setUserId($userId);
        $timesheet->setStart($start);
        $timesheet->setEnd($end);
        $timesheet->setDescription($description);

        $savedTimesheet = $this->mapper->update($timesheet);
        return new DataResponse($savedTimesheet);
    }

    /**
     * Delete a time record.
     */
    public function deleteTimesheet(int $id): DataResponse {
        $this->mapper->deleteById($id);
        return new DataResponse(['message' => 'Deleted']);
    }
}
