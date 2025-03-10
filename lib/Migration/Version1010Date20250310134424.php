<?php

declare(strict_types=1);

namespace OCA\DeckTimeTracking\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1010Date20250310134424 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $table = $schema->getTable('deck_timesheet');
		if (!$table->hasColumn('reminder')) {
			$table->addColumn('reminder', Types::DATETIME, [
				'default' => null,
				'notnull' => false,
			]);

			return $schema;
		}

		return null;
    }

}
