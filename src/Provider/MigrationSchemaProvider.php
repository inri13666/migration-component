<?php

namespace Okvpn\Component\Migration\Provider;

use Doctrine\DBAL\Schema\Schema;
use Okvpn\Component\Migration\Migration\Loader\MigrationsLoader;
use Okvpn\Component\Migration\Migration\MigrationState;
use Okvpn\Component\Migration\Migration\QueryBag;

/**
 * A schema provider that uses the current migrations to generate schemas.
 */
final class MigrationSchemaProvider implements SchemaProviderInterface
{
    /** @var MigrationsLoader */
    private $migrationsLoader;

    /**
     * @param MigrationsLoader $migrationsLoader
     */
    public function __construct(MigrationsLoader $migrationsLoader)
    {
        $this->migrationsLoader = $migrationsLoader;
    }

    /**
     * {@inheritdoc}
     */
    public function createSchema()
    {
        $migrations = $this->migrationsLoader->getPlainMigrations();
        $toSchema = new Schema();
        $queryBag = new QueryBag();
        /** @var MigrationState $migrationState */
        foreach ($migrations as $migrationState) {
            $migrationState->getMigration()->up($toSchema, $queryBag);
        }

        return $toSchema;
    }
}
