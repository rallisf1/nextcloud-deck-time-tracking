<?php

declare(strict_types=1);

namespace OCA\DeckTimeTracking\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20240217141524 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('deck_timesheet')) {
            $table = $schema->createTable('deck_timesheet');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('card_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('user_id', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('start', Types::DATETIME, ['notnull' => true]);
            $table->addColumn('end', Types::DATETIME, ['notnull' => false]); // Nullable until stopped
            $table->addColumn('description', Types::TEXT, ['notnull' => false]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['card_id'], 'timesheet_card_idx');
            $table->addIndex(['user_id'], 'timesheet_user_idx');
        }

        return $schema;
    }

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

}
