<?php

namespace Okvpn\Component\Migration\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Mapping\ClassMetadata;
use Okvpn\Component\Migration\Command\Helper\MigrationConsoleHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DumpMigrationsCommand extends Command
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

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('okvpn:migration:dump')
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
            ->setDescription('Dump existing database structure.');
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
        $this->className = $input->getOption('class-name') ?: 'Installer';
        $this->initializeMetadataInformation();
        $doctrine = $this->getOkvpnMigrationHelper()->getDoctrine();
        /** @var Connection $connection */
        $connection = $doctrine->getConnection();
        /** @var Schema $schema */
        $schema = $connection->getSchemaManager()->createSchema();

        if ($input->getOption('plain-sql')) {
            $sqls = $schema->toSql($connection->getDatabasePlatform());
            foreach ($sqls as $sql) {
                $output->writeln($sql . ';');
            }
        } else {
            $this->dumpPhpSchema($schema, $output);
        }
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
     * @param Schema $schema
     * @param OutputInterface $output
     */
    protected function dumpPhpSchema(Schema $schema, OutputInterface $output)
    {
        $visitor = $this->getOkvpnMigrationHelper()->getSchemaDumper();
        $schema->visit($visitor);

        $output->writeln(
            $visitor->dump(
                $this->allowedTables,
                $this->namespace ?: 'Okvpn',
                $this->className,
                $this->version,
                $this->extendedFieldOptions
            )
        );
    }
}
