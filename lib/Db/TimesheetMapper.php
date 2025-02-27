<?php

declare(strict_types=1);

namespace OCA\DeckTimeTracking\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
use OCP\IUser;

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
           ->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter('0')));

        return $this->findEntities($qb);
    }

    /**
     * Find all non-archived/deleted time records for a specific user.
     */
    public function findByPermissions(string $currentUserId, string $userId, int $boardId, array $filter = []): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('t.*')
           ->from($this->table, 't')
           ->join('t', 'deck_cards', 'c', 'c.id = t.card_id')
           ->join('c', 'deck_stacks', 'st', 'st.id = c.stack_id')
           ->join('st', 'deck_boards', 'b', 'b.id = st.board_id')
           ->leftJoin('c', 'deck_assigned_users', 'au', 'au.card_id = c.id AND au.participant = ' . $qb->createNamedParameter($currentUserId, IQueryBuilder::PARAM_STR))
           ->leftJoin('b', 'deck_board_acl', 'acl', 'acl.board_id = b.id AND acl.permission_manage = 1 AND acl.participant = ' . $qb->createNamedParameter($currentUserId, IQueryBuilder::PARAM_STR))
           ->where($qb->expr()->eq('t.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
           ->andWhere($qb->expr()->eq('c.archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
           ->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter('0')))
           ->andWhere($qb->expr()->eq('st.deleted_at', $qb->createNamedParameter('0')))
           ->andWhere($qb->expr()->eq('b.archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
           ->andWhere($qb->expr()->eq('b.deleted_at', $qb->createNamedParameter('0')))
           ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('c.owner', $qb->createNamedParameter($currentUserId, IQueryBuilder::PARAM_STR)),
                    $qb->expr()->eq('b.owner', $qb->createNamedParameter($currentUserId, IQueryBuilder::PARAM_STR)),
                    $qb->expr()->isNotNull('au.participant'),
                    $qb->expr()->isNotNull('acl.participant')
                )
           )
           ->groupBy('t.card_id');
        
        if($boardId > 0) {
            $qb->andWhere($qb->expr()->eq('b.id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)));
        }

        if(count($filter)) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->between('t.start', $qb->createNamedParameter($filter['start'], IQueryBuilder::PARAM_DATETIME_IMMUTABLE), $qb->createNamedParameter($filter['end'], IQueryBuilder::PARAM_DATETIME_IMMUTABLE)),
                    $qb->expr()->between('t.end', $qb->createNamedParameter($filter['start'], IQueryBuilder::PARAM_DATETIME_IMMUTABLE), $qb->createNamedParameter($filter['end'], IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                )
                
            );
        }

        return $this->findEntities($qb);
    }

    /**
     * Find all users with non-archived/deleted time records.
     */
    public function findForCalendar(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('t.*')
           ->from($this->table, 't')
           ->join('t', 'deck_cards', 'c', 'c.id = t.card_id')
           ->where($qb->expr()->eq('c.archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
           ->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter('0')))
           ->groupBy('t.user_id');

        return $this->findEntities($qb);
    }

    /**
     * Delete a time record by ID.
     */
    public function deleteById(int $id): void {
        $entity = $this->find($id);
        $this->delete($entity);
    }

}
