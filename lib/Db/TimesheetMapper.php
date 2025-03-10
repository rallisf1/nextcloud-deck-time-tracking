<?php

declare(strict_types=1);

namespace OCA\DeckTimeTracking\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
use \DateTime;
use \DateInterval;

class TimesheetMapper extends QBMapper {
    private $table = 'deck_timesheet';

    public function __construct(IDBConnection $db) {
        parent::__construct($db, $this->table, Timesheet::class);
    }

    /**
     * Find a time record
     */
    public function find(int $id): Timesheet {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->table)
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

        return $this->findEntity($qb);
    }

    /**
     * Find all time records for a specific card.
     */
    public function findByCardId(int $cardId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->table)
           ->where($qb->expr()->eq('card_id', $qb->createNamedParameter($cardId, IQueryBuilder::PARAM_INT)));

        return $this->findEntities($qb);
    }

    /**
     * Find all non-archived/deleted time records for a specific user.
     */
    public function findByUserId(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('t.*')
           ->from($this->table, 't')
           ->join('t', 'deck_cards', 'c', 'c.id = t.card_id')
           ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
           ->andWhere($qb->expr()->eq('c.archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
           ->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));

        return $this->findEntities($qb);
    }

    /**
     * Find all non-archived/deleted time records for a specific user.
     */
    public function findByCommonBoards(array $boardIds, string $userId, array $filter = []): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('t.*')
           ->from($this->table, 't')
           ->join('t', 'deck_cards', 'c', 'c.id = t.card_id')
           ->join('c', 'deck_stacks', 's', 's.id = c.stack_id')
           ->join('s', 'deck_boards', 'b', 'b.id = s.board_id')
           ->where($qb->expr()->eq('t.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
           ->andWhere($qb->expr()->eq('c.archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
           ->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
           ->andWhere($qb->expr()->eq('s.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
           ->andWhere($qb->expr()->eq('b.archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
           ->andWhere($qb->expr()->eq('b.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
           ->andWhere($qb->expr()->in('s.board_id', $qb->createNamedParameter($boardIds, IQueryBuilder::PARAM_INT_ARRAY)))
           ->groupBy(['t.card_id', 't.id']);

        if(count($filter)) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->between('t.start', $qb->createNamedParameter($filter['start'], IQueryBuilder::PARAM_DATE), $qb->createNamedParameter($filter['end'], IQueryBuilder::PARAM_DATE)),
                    $qb->expr()->between('t.end', $qb->createNamedParameter($filter['start'], IQueryBuilder::PARAM_DATE), $qb->createNamedParameter($filter['end'], IQueryBuilder::PARAM_DATE))
                )
                
            );
        }

        return $this->findEntities($qb);
    }

    /**
     * Find all users with non-archived/deleted time records.
     */
    public function findCalendarUsers(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->selectDistinct('t.user_id')
           ->from($this->table, 't')
           ->join('t', 'deck_cards', 'c', 'c.id = t.card_id')
           ->where($qb->expr()->eq('c.archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
           ->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));

        $result = $qb->execute();
        return $result->fetchAll();
    }

    /**
     * Find all timesheet records that should be reminded.
     */
    public function findForgotten(): array {
        $threshold = 60; // in minutes, TODO make this a setting
        $limit = new DateTime();
        $limit->sub(new DateInterval('PT' . $threshold . 'M'));
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->table)
           ->where($qb->expr()->isNull('end'))
           ->andWhere($qb->expr()->isNotNull('reminder'))
           ->andWhere($qb->expr()->lte('start', $qb->createNamedParameter($limit, IQueryBuilder::PARAM_DATE)));

        return $this->findEntities($qb);
    }

    /**
     * Find all active timesheet records for the user
     */
    public function findUserActive(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->table)
           ->where($qb->expr()->eq('t.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
           ->andWhere($qb->expr()->isNull('end'));

        return $this->findEntities($qb);
    }
}
