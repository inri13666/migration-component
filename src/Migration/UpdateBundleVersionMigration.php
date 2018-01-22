<?php

namespace Okvpn\Component\Migration\Migration;

use Doctrine\DBAL\Schema\Schema;

class UpdateBundleVersionMigration implements Migration, FailIndependentMigration
{
    /** @var MigrationState[] */
    protected $migrations;

    /** @var string */
    protected $migrationTable;

    /**
     * @param MigrationState[] $migrations
     * @param string $migrationTable
     */
    public function __construct(array $migrations, $migrationTable = null)
    {
        $this->migrations = $migrations;
        $this->migrationTable = $migrationTable ? $migrationTable : CreateMigrationTableMigration::MIGRATION_TABLE;
    }

    /**
     * @inheritdoc
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $date = new \DateTime();
        foreach ($this->migrations as $migration) {
            $sql = sprintf(
                "INSERT INTO %s (bundle, version, loaded_at) VALUES ('%s', '%s', '%s')",
                $this->migrationTable,
                str_replace('\\', '/', get_class($migration->getMigration())),
                $migration->getVersion(),
                $date->format('Y-m-d H:i:s')
            );
            $queries->addQuery($sql);
        }
    }
}
