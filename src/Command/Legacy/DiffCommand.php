<?php

namespace Okvpn\Component\Migration\Command\Legacy;

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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DiffCommand extends Command
{
    const NAME = 'akuma:migrations:diff';

    /** @var \Twig_Environment */
    protected $twig;

    /** @var EntityManager */
    protected $doctrine;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var array */
    protected $allowedTables;

    /** @var MigrationSchemaProvider|SchemaProviderInterface */
    protected $okvpnSchemaProvider;

    /** @var OrmSchemaProvider|SchemaProviderInterface */
    protected $schemaProvider;

    /**
     * @param \Twig_Environment|null $twig
     * @param EntityManager|null $em
     * @param EventDispatcherInterface|null $eventDispatcher
     */
    public function __construct(
        \Twig_Environment $twig = null,
        EntityManager $em = null,
        EventDispatcherInterface $eventDispatcher = null
    ) {
        parent::__construct(self::NAME);
        $this->twig = $twig;
        $this->doctrine = $em;
        $this->eventDispatcher = $eventDispatcher;
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
            $migrationFile = sprintf('%s/Version%s.php', 'D:\_dev\sites\es436\server\app\Migrations\Schema', $version);
            file_put_contents($migrationFile, $this->dumpPhpSchema($schemaDiff, 'Migrations\Schema', $version));
            $output->writeln(
                sprintf('Generated new migration class to "<info>%s</info>" from schema differences.', $migrationFile)
            );
        }
    }

    /**
     * @return \Twig_Environment
     */
    protected function getTwig()
    {
        if (!$this->twig) {
            $this->twig = $this->getHelper(MigrationConsoleHelper::NAME)->getTwig();
        }

        return $this->twig;
    }

    /**
     * @return EntityManager
     */
    protected function getDoctrine()
    {
        if (!$this->doctrine) {
            $this->doctrine = $this->getHelper(MigrationConsoleHelper::NAME)->getDoctrine();
        }

        return $this->doctrine;
    }

    /**
     * @return EventDispatcherInterface
     */
    protected function getEventDispatcher()
    {
        if (!$this->eventDispatcher) {
            $this->eventDispatcher = $this->getHelper(MigrationConsoleHelper::NAME)->getEventDispatcher();
        }

        return $this->eventDispatcher;
    }

    /**
     * @return MigrationSchemaProvider|SchemaProviderInterface
     */
    protected function getOkvpnSchemaProvider()
    {
        if (!$this->okvpnSchemaProvider) {
            /** @var MigrationsLoader $loader */
            $loader = $this->getHelper(MigrationConsoleHelper::NAME)->getLegacyMigrationLoader();
            $this->okvpnSchemaProvider = new MigrationSchemaProvider($loader);
        }

        return $this->okvpnSchemaProvider;
    }

    /**
     * @return OrmSchemaProvider|SchemaProviderInterface
     */
    protected function getSchemaProvider()
    {
        if (!$this->schemaProvider) {
            $this->schemaProvider = new OrmSchemaProvider($this->getDoctrine());
        }

        return $this->schemaProvider;
    }

    /**
     * Process metadata information.
     */
    protected function initializeMetadataInformation()
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
    protected function dumpPhpSchema(SchemaDiff $schema, $namespace, $version)
    {
        /** @var SchemaDiffDumper $visitor */
        $visitor = $this->getHelper(MigrationConsoleHelper::NAME)->getSchemaDiffDumper();
        $visitor->setTemplate('schema-diff-legacy-template.php.twig');
        $visitor->acceptSchemaDiff($schema);

        return $visitor->dump(
            $this->allowedTables,
            $namespace,
            sprintf('Version%s', $version),
            $version,
            null
        );
    }
}
