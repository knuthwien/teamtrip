<?php

declare(strict_types=1);

namespace OCA\TeamTrip\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0100Date20260920113000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('teamtrip_trips')) {
            $table = $schema->createTable('teamtrip_trips');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('uuid', Types::STRING, ['notnull' => true, 'length' => 36]);
            $table->addColumn('owner_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => '']);
            $table->addColumn('departure_at', Types::STRING, ['notnull' => false, 'length' => 40]);
            $table->addColumn('arrival_at', Types::STRING, ['notnull' => false, 'length' => 40]);
            $table->addColumn('revision', Types::BIGINT, ['notnull' => true, 'default' => 1]);
            $table->addColumn('snapshot', Types::TEXT, ['notnull' => true]);
            $table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $table->addColumn('deleted_at', Types::BIGINT, ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['uuid'], 'teamtrip_trip_uuid');
            $table->addIndex(['owner_uid'], 'teamtrip_trip_owner');
            $table->addIndex(['updated_at'], 'teamtrip_trip_update');
        }

        if (!$schema->hasTable('teamtrip_shares')) {
            $table = $schema->createTable('teamtrip_shares');
            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('trip_uuid', Types::STRING, ['notnull' => true, 'length' => 36]);
            $table->addColumn('user_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('role', Types::STRING, ['notnull' => true, 'length' => 8, 'default' => 'read']);
            $table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['trip_uuid', 'user_uid'], 'teamtrip_share_unique');
            $table->addIndex(['user_uid'], 'teamtrip_share_user');
        }

        return $schema;
    }
}
