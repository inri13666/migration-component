<?php

namespace Okvpn\Component\Migration\Command\Helper;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Okvpn\Component\Migration\Event\MigrationEvents;
use Okvpn\Component\Migration\Event\PreMigrationEvent;
use Okvpn\Component\Migration\EventListener\DoctrineMetadataListener;
use Okvpn\Component\Migration\EventListener\PreUpMigrationListener;
use Okvpn\Component\Migration\Migration\CreateMigrationTableMigration;
use Okvpn\Component\Migration\Migration\Extension\DataStorageExtension;
use Okvpn\Component\Migration\Migration\Extension\RenameExtension;
use Okvpn\Component\Migration\Migration\Loader\MigrationsLoader;
use Okvpn\Component\Migration\Migration\MigrationExecutor;
use Okvpn\Component\Migration\Migration\MigrationExecutorWithNameGenerator;
use Okvpn\Component\Migration\Migration\MigrationExtensionManager;
use Okvpn\Component\Migration\Migration\MigrationQueryExecutor;
use Okvpn\Component\Migration\Tools\DbIdentifierNameGenerator;
use Okvpn\Component\Migration\Tools\SchemaDiffDumper;
use Okvpn\Component\Migration\Tools\SchemaDumper;
use Okvpn\Component\Migration\Twig\SchemaDumperExtension;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MigrationConsoleHelper extends Helper
{
    const NAME = 'okvpn_migrations';

    /** @var string */
    protected $migrationPath;

    /** @var string */
    protected $migrationTable;

    /** @var \Twig_Environment */
    protected $twig;

    /** @var EntityManager */
    protected $doctrine;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var SchemaDumper */
    protected $schemaDumper;

    /** @var SchemaDiffDumper */
    protected $schemaDiffDumper;

    /** @var MigrationsLoader */
    protected $migrationsLoader;

    /** @var MigrationExecutor */
    protected $migrationExecutor;

    /** @var MigrationExtensionManager */
    protected $migrationExtensionManager;

    /**
     * @param \Twig_Environment $twig
     * @param EntityManager $em
     * @param EventDispatcherInterface $eventDispatcher
     * @param string $migrationPath
     * @param string $migrationTable
     *
     * @throws \LogicException
     */
    public function __construct(
        \Twig_Environment $twig,
        EntityManager $em,
        EventDispatcherInterface $eventDispatcher,
        $migrationPath = MigrationsLoader::DEFAULT_MIGRATION_PATH,
        $migrationTable = CreateMigrationTableMigration::MIGRATION_TABLE
    ) {
        $this->migrationPath = $migrationPath;
        $this->migrationTable = $migrationTable;
        $this->twig = $twig;
        $this->doctrine = $em;
        $this->eventDispatcher = $eventDispatcher;

        //Configure Twig
        $this->twig->addExtension(new SchemaDumperExtension($this->doctrine));
        $reflected = new \ReflectionClass(__CLASS__);
        $path = dirname($reflected->getFileName(), 3) . '/Resources/views';
        $loader = $this->twig->getLoader();
        $this->twig->setLoader(new \Twig_Loader_Chain(array(
            new \Twig_Loader_Filesystem($path),
            $loader,
        )));

        //Configure Event Dispatcher
        $this->eventDispatcher->addListener(MigrationEvents::PRE_UP, function (PreMigrationEvent $event) {
            $listener = new PreUpMigrationListener($this->migrationTable);

            return $listener->onPreUp($event);
        });

        //Configure Doctrine Events
        $this->doctrine->getEventManager()->addEventListener(
            Events::loadClassMetadata,
            new DoctrineMetadataListener($this->migrationTable)
        );
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getDoctrine()
    {
        return $this->doctrine;
    }

    /**
     * @return SchemaDumper
     */
    public function getSchemaDumper()
    {
        if (!$this->schemaDumper) {
            $this->schemaDumper = new SchemaDumper($this->getTwig(), $this->migrationPath);
        }

        return $this->schemaDumper;
    }

    /**
     * #@return SchemaDiffDumper
     */
    public function getSchemaDiffDumper()
    {

        if (!$this->schemaDiffDumper) {
            $this->schemaDiffDumper = new SchemaDiffDumper($this->getTwig(), $this->migrationPath);
        }

        return $this->schemaDiffDumper;
    }

    /**
     * @return MigrationsLoader
     */
    public function getMigrationLoader()
    {
        if (!$this->migrationsLoader) {
            $this->migrationsLoader = new MigrationsLoader(
                $this->getDoctrine()->getConnection(),
                $this->eventDispatcher
            );
            $this->migrationsLoader
                ->setMigrationPath($this->migrationPath)
                ->setMigrationTable($this->migrationTable);
        }

        return $this->migrationsLoader;
    }

    /**
     * @return MigrationExecutor
     */
    public function getMigrationExecutor()
    {
        if (!$this->migrationExecutor) {
            $this->migrationExecutor = new MigrationExecutorWithNameGenerator($this->getQueryExecutor());
            $this->migrationExecutor->setExtensionManager($this->getExtensionManager());
            $this->migrationExecutor->setNameGenerator($this->getNameGenerator());
        }

        return $this->migrationExecutor;
    }

    /**
     * @return MigrationExtensionManager
     */
    public function getExtensionManager()
    {
        if (!$this->migrationExtensionManager) {
            $this->migrationExtensionManager = new MigrationExtensionManager();
            $this->migrationExtensionManager->setConnection($this->doctrine->getConnection());
            $this->migrationExtensionManager->setDatabasePlatform($this->doctrine->getConnection()->getDatabasePlatform());
            $this->migrationExtensionManager->setNameGenerator($this->getNameGenerator());
            $this->migrationExtensionManager->addExtension('rename', new RenameExtension());
            $this->migrationExtensionManager->addExtension('rename', new DataStorageExtension());
        }

        return $this->migrationExtensionManager;
    }

    /**
     * @return DbIdentifierNameGenerator
     */
    protected function getNameGenerator()
    {
        return new DbIdentifierNameGenerator();
    }

    /**
     * @return MigrationQueryExecutor
     */
    protected function getQueryExecutor()
    {
        return new MigrationQueryExecutor($this->getDoctrine()->getConnection());
    }

    /**
     * @return \Twig_Environment
     */
    protected function getTwig()
    {
        return $this->twig;
    }
}
