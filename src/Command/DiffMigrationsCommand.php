<?php

namespace Okvpn\Component\Migration\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\ORM\Mapping\ClassMetadata;
use Okvpn\Component\Migration\Command\Helper\MigrationConsoleHelper;
use Okvpn\Component\Migration\Provider\MigrationSchemaProvider;
use Okvpn\Component\Migration\Provider\OrmSchemaProvider;
use Okvpn\Component\Migration\Provider\SchemaProviderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DiffMigrationsCommand extends Command
{
    /**
     * @var array
     */
    protected $allowedTables = [];

    /**
     * @var array
     */
    protected $extendedFieldOptions = [];

    /**
     * @var string
     */
    protected $namespace;

    /**
     * @var string
     */
    protected $className;

    /**
     * @var string
     */
    protected $version;

    /** @var SchemaProviderInterface */
    protected $schemaProvider;

    /** @var SchemaProviderInterface */
    protected $okvpnSchemaProvider;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('okvpn:migration:diff')
            ->addOption('plain-sql', null, InputOption::VALUE_NONE, 'Out schema as plain sql queries')
            ->addOption(
                'namespace',
                null,
                InputOption::VALUE_REQUIRED,
                'Entities Namespace',
                null
            )
            ->addOption(
                'class-name',
                null,
                InputOption::VALUE_OPTIONAL,
                'Class Name',
                null
            )
            ->addOption(
                'migration-version',
                null,
                InputOption::VALUE_OPTIONAL,
                'Migration version',
                'v1_0'
            )
            ->setDescription('Compare current existing database structure with orm structure');
    }

    /**
     * @return MigrationConsoleHelper
     */
    protected function getOkvpnMigrationHelper()
    {
        return $this->getHelper(MigrationConsoleHelper::NAME);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->version = $input->getOption('migration-version');
        $this->namespace = $input->getOption('namespace');
        $this->className = $input->getOption('class-name') ?: sprintf('Migration_%s', $this->version);
        $this->initializeMetadataInformation();
        $doctrine = $this->getOkvpnMigrationHelper()->getDoctrine();
        $connection = $doctrine->getConnection();

        $okvpnSchema = $this->getOkvpnSchemaProvider()->createSchema();
        $ormSchema = $this->getSchemaProvider()->createSchema();
        $schemaDiff = Comparator::compareSchemas($okvpnSchema, $ormSchema);

        $this->removeExcludedTables($schemaDiff);

        if ($input->getOption('plain-sql')) {
            /** @var Connection $connection */
            $sqls = $schemaDiff->toSql($connection->getDatabasePlatform());
            foreach ($sqls as $sql) {
                $output->writeln($sql . ';');
            }
        } else {
            $this->dumpPhpSchema($schemaDiff, $output);
        }
    }

    /**
     * @return OrmSchemaProvider|SchemaProviderInterface
     */
    protected function getSchemaProvider()
    {
        if (!$this->schemaProvider) {
            $this->schemaProvider = new OrmSchemaProvider($this->getOkvpnMigrationHelper()->getDoctrine());
        }

        return $this->schemaProvider;
    }

    /**
     * @return MigrationSchemaProvider|SchemaProviderInterface
     */
    protected function getOkvpnSchemaProvider()
    {
        if (!$this->okvpnSchemaProvider) {
            $this->okvpnSchemaProvider = new MigrationSchemaProvider(
                $this->getOkvpnMigrationHelper()->getMigrationLoader()
            );
        }

        return $this->okvpnSchemaProvider;
    }

    /**
     * Process metadata information.
     */
    protected function initializeMetadataInformation()
    {
        $doctrine = $this->getOkvpnMigrationHelper()->getDoctrine();
        /** @var ClassMetadata[] $allMetadata */
        $allMetadata = $doctrine->getMetadataFactory()->getAllMetadata();
        array_walk(
            $allMetadata,
            function (ClassMetadata $entityMetadata) {
                if (($this->namespace) && ($entityMetadata->namespace != $this->namespace)) {
                    return;
                }
                $this->allowedTables[$entityMetadata->getTableName()] = true;
                foreach ($entityMetadata->getAssociationMappings() as $associationMappingInfo) {
                    if (!empty($associationMappingInfo['joinTable'])) {
                        $joinTableName = $associationMappingInfo['joinTable']['name'];
                        $this->allowedTables[$joinTableName] = true;
                    }
                }
            }
        );
    }

    /**
     * @param SchemaDiff $schema
     * @param OutputInterface $output
     */
    protected function dumpPhpSchema(SchemaDiff $schema, OutputInterface $output)
    {
        $visitor = $this->getOkvpnMigrationHelper()->getSchemaDiffDumper();

        $visitor->acceptSchemaDiff($schema);

        $output->writeln(
            $visitor->dump(
                $this->allowedTables,
                $this->namespace,
                $this->className,
                $this->version,
                $this->extendedFieldOptions
            )
        );
    }

    /**
     * @param SchemaDiff $schemaDiff
     */
    private function removeExcludedTables(SchemaDiff $schemaDiff)
    {
        $excludes = [
            'okvpn_migrations',
        ];

        /** @var Table $v */
        foreach ($schemaDiff->newTables as $k => $v) {
            if (in_array($v->getName(), $excludes) || !isset($this->allowedTables[$v->getName()])) {
                unset($schemaDiff->newTables[$k]);
            }
        }

        /** @var TableDiff $v */
        foreach ($schemaDiff->changedTables as $k => $v) {
            if (in_array($v->name, $excludes) || !isset($this->allowedTables[$v->name])) {
                unset($schemaDiff->changedTables[$k]);
            }
        }
    }
}
