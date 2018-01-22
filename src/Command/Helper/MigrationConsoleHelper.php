<?php

namespace Okvpn\Component\Migration\Command\Helper;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Okvpn\Component\Migration\Entity\DataMigration;
use Okvpn\Component\Migration\Event\MigrationEvents;
use Okvpn\Component\Migration\Event\PreMigrationEvent;
use Okvpn\Component\Migration\EventListener\DoctrineMetadataListener;
use Okvpn\Component\Migration\EventListener\PreUpMigrationListener;
use Okvpn\Component\Migration\Migration\CreateMigrationTableMigration;
use Okvpn\Component\Migration\Migration\Extension\DataStorageExtension;
use Okvpn\Component\Migration\Migration\Extension\RenameExtension;
use Okvpn\Component\Migration\Migration\Loader\LegacyMigrationsLoader;
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
    protected $migrationNamespace;

    /** @var null|string */
    protected $migrationsFolder;

    /** @var string */
    protected $migrationTable;

    /** @var \Twig_Environment */
    protected $twig;

    /** @var EntityManager */
    protected $doctrine;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var SchemaDiffDumper */
    protected $schemaDiffDumper;

    /** @var MigrationsLoader */
    protected $migrationsLoader;

    /** @var MigrationExecutor */
    protected $migrationExecutor;

    /** @var MigrationExtensionManager */
    protected $migrationExtensionManager;

    /**
     * @param EntityManager $em
     * @param EventDispatcherInterface $eventDispatcher
     * @param \Twig_Environment $twig
     * @param string $migrationNamespace
     * @param string $migrationsFolder
     * @param string $migrationTable
     *
     * @throws \Doctrine\ORM\ORMException
     */
    public function __construct(
        EntityManager $em,
        EventDispatcherInterface $eventDispatcher,
        \Twig_Environment $twig = null,
        $migrationsFolder = null,
        $migrationNamespace = MigrationsLoader::DEFAULT_MIGRATION_NAMESPACE,
        $migrationTable = CreateMigrationTableMigration::MIGRATION_TABLE
    ) {
        $this->migrationNamespace = $migrationNamespace;
        $this->migrationsFolder = $migrationsFolder;
        $this->migrationTable = $migrationTable;
        $this->twig = $twig;
        $this->doctrine = $em;
        $this->eventDispatcher = $eventDispatcher;

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

        $metaDriver = $this->doctrine->getConfiguration()->getMetadataDriverImpl();

        if (!$metaDriver instanceof MappingDriverChain) {
            $metaDriverChain = new MappingDriverChain();
            $metaDriverChain->setDefaultDriver($metaDriver);
        } else {
            $metaDriverChain = $metaDriver;
        }

        $namespace = 'Okvpn\Component\Migration\Entity';
        if (!in_array($namespace, $metaDriverChain->getAllClassNames())) {
            $reflection = new \ReflectionClass(DataMigration::class);
            $metaDriverChain->addDriver(
                new AnnotationDriver(new AnnotationReader(), [dirname($reflection->getFileName())]), $namespace
            );
        }
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
     * #@return SchemaDiffDumper
     */
    public function getSchemaDiffDumper()
    {

        if (!$this->schemaDiffDumper) {
            $this->schemaDiffDumper = new SchemaDiffDumper($this->getTwig());
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
                ->addBundle('Okvpn', $this->migrationsFolder)
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
        if (!$this->twig) {
            //Configure Default Twig
            $reflected = new \ReflectionClass(__CLASS__);
            $path = dirname($reflected->getFileName(), 3) . '/Resources/views';
            $loader = new \Twig_Loader_Chain(array(
                new \Twig_Loader_Filesystem($path),
            ));
            $this->twig = new \Twig_Environment($loader);
            $this->twig->addExtension(new SchemaDumperExtension($this->doctrine));
        }

        return $this->twig;
    }

    /**
     * @return string
     */
    public function getMigrationsDirectory()
    {
        $dir = $this->migrationsFolder;
        $dir = $dir ? $dir : getcwd();
        $dir = rtrim($dir, '/');

        if (!file_exists($dir)) {
            throw new \InvalidArgumentException(sprintf('Migrations directory "%s" does not exist.', $dir));
        }

        $dir .= DIRECTORY_SEPARATOR . MigrationsLoader::DEFAULT_MIGRATION_PATH;
        $dir = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $dir), DIRECTORY_SEPARATOR);
        $this->createDirIfNotExists($dir);

        return $dir;
    }

    private function createDirIfNotExists($dir)
    {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
