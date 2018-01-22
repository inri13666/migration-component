<?php

namespace Okvpn\Component\Migration\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Okvpn\Component\Migration\Command\Helper\MigrationConsoleHelper;
use Okvpn\Component\Migration\Migration\Loader\MigrationsLoader;
use Okvpn\Component\Migration\Provider\MigrationSchemaProvider;
use Okvpn\Component\Migration\Provider\OrmSchemaProvider;
use Okvpn\Component\Migration\Provider\SchemaProviderInterface;
use Okvpn\Component\Migration\Tools\SchemaDiffDumper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DiffCommand extends Command
{
    const NAME = 'akuma:migrations:diff';

    /** @var EntityManager */
    private $doctrine;

    /** @var array */
    private $allowedTables;

    /** @var MigrationSchemaProvider|SchemaProviderInterface */
    private $okvpnSchemaProvider;

    /** @var OrmSchemaProvider|SchemaProviderInterface */
    private $schemaProvider;

    /** @var MigrationConsoleHelper */
    private $helper;

    /**
     * @param MigrationConsoleHelper $helper
     */
    public function __construct(MigrationConsoleHelper $helper = null)
    {
        parent::__construct(self::NAME);

        $this->helper = $helper;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName(self::NAME)
            ->addOption('plain-sql', null, InputOption::VALUE_NONE)
            ->setDescription('Generate a migration by comparing your current database to your mapping information.');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initializeMetadataInformation();

        $okvpnSchema = $this->getOkvpnSchemaProvider()->createSchema();
        $ormSchema = $this->getSchemaProvider()->createSchema();
        $schemaDiff = Comparator::compareSchemas($okvpnSchema, $ormSchema);

        /** @var Connection $connection */
        $connection = $this->getDoctrine()->getConnection();
        $platform = $connection->getDatabasePlatform();

        if (empty($schemaDiff->toSql($platform))) {
            $output->writeln('No changes detected in your mapping information.', 'ERROR');

            return;
        }

        if ($input->getOption('plain-sql')) {
            $sqls = $schemaDiff->toSql($platform);
            foreach ($sqls as $sql) {
                $output->writeln($sql . ';');
            }
        } else {
            $version = (new\DateTime())->format('YmdHis');
            $migrationFile = sprintf(
                '%s%sVersion%s.php',
                $this->getOkvpnMigrationHelper()->getMigrationsDirectory(),
                DIRECTORY_SEPARATOR,
                $version
            );
            file_put_contents($migrationFile, $this->dumpPhpSchema($schemaDiff, $version));
            $output->writeln(
                sprintf('Generated new migration class to "<info>%s</info>" from schema differences.', $migrationFile)
            );
        }
    }


    /**
     * @return MigrationConsoleHelper
     */
    protected function getOkvpnMigrationHelper()
    {
        return $this->helper ?: $this->getHelper(MigrationConsoleHelper::NAME);
    }

    /**
     * @return EntityManager
     */
    private function getDoctrine()
    {
        if (!$this->doctrine) {
            $this->doctrine = $this->getOkvpnMigrationHelper()->getDoctrine();
        }

        return $this->doctrine;
    }

    /**
     * @return MigrationSchemaProvider|SchemaProviderInterface
     */
    private function getOkvpnSchemaProvider()
    {
        if (!$this->okvpnSchemaProvider) {
            /** @var MigrationsLoader $loader */
            $loader = $this->getOkvpnMigrationHelper()->getMigrationLoader();
            $this->okvpnSchemaProvider = new MigrationSchemaProvider($loader);
        }

        return $this->okvpnSchemaProvider;
    }

    /**
     * @return OrmSchemaProvider|SchemaProviderInterface
     */
    private function getSchemaProvider()
    {
        if (!$this->schemaProvider) {
            $this->schemaProvider = new OrmSchemaProvider($this->getDoctrine());
        }

        return $this->schemaProvider;
    }

    /**
     * Process metadata information.
     */
    private function initializeMetadataInformation()
    {
        $doctrine = $this->getDoctrine();
        /** @var ClassMetadata[] $allMetadata */
        $allMetadata = $doctrine->getMetadataFactory()->getAllMetadata();
        array_walk(
            $allMetadata,
            function (ClassMetadata $entityMetadata) {
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
     * @param $namespace
     * @param $version
     *
     * @return string
     */
    private function dumpPhpSchema(
        SchemaDiff $schema,
        $version,
        $namespace = MigrationsLoader::DEFAULT_MIGRATION_NAMESPACE
    ) {
        /** @var SchemaDiffDumper $visitor */
        $visitor = $this->getOkvpnMigrationHelper()->getSchemaDiffDumper();
        $visitor->setTemplate('schema-diff-template.php.twig');
        $visitor->acceptSchemaDiff($schema);

        return $visitor->dump(
            $version,
            $this->allowedTables,
            null,
            sprintf('Version%s', $version),
            $namespace
        );
    }
}
