<?php

namespace Okvpn\Component\Migration\Migration\Loader;

use Doctrine\DBAL\Connection;
use Okvpn\Component\Migration\Event\MigrationEvents;
use Okvpn\Component\Migration\Event\PostMigrationEvent;
use Okvpn\Component\Migration\Event\PreMigrationEvent;
use Okvpn\Component\Migration\Migration\CreateMigrationTableMigration;
use Okvpn\Component\Migration\Migration\Installation;
use Okvpn\Component\Migration\Migration\MigrationState;
use Okvpn\Component\Migration\Migration\UpdateBundleVersionMigration;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class MigrationsLoader
{
    const DEFAULT_MIGRATION_NAMESPACE = 'Migrations\Schema';
    const DEFAULT_MIGRATION_PATH = 'Migrations/Schema';

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var string An array with already loaded bundle migration versions
     *             key =   bundle name
     *             value = latest loaded version
     */
    protected $loadedVersions;

    /**
     * @var string Path that located migration files
     */
    protected $migrationPath = self::DEFAULT_MIGRATION_PATH;

    /**
     * @var string Migration table
     */
    protected $migrationTable = CreateMigrationTableMigration::MIGRATION_TABLE;

    /**
     * @var array
     */
    protected $bundles = [];

    /**
     * @param Connection $connection
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        Connection $connection,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param array $bundles
     */
    public function setBundles(array $bundles)
    {
        $this->bundles = $bundles;
    }

    /**
     * @param string $name
     * @param string $path
     *
     * @return $this
     */
    public function addBundle($name, $path)
    {
        $this->bundles[$name] = $path;

        return $this;
    }

    /**
     * @param string $migrationPath
     *
     * @return $this
     */
    public function setMigrationPath($migrationPath)
    {
        $this->migrationPath = $migrationPath;

        return $this;
    }

    /**
     * @param string $migrationTable
     *
     * @return $this
     */
    public function setMigrationTable($migrationTable)
    {
        $this->migrationTable = $migrationTable;

        return $this;
    }

    /**
     * @param bool $excludeLoaded
     *
     * @return \Okvpn\Component\Migration\Migration\MigrationState[]
     * @throws \Exception
     */
    public function getMigrations($excludeLoaded = true)
    {
        $result = [];

        // process "pre" migrations
        $preEvent = new PreMigrationEvent($this->connection);
        $this->eventDispatcher->dispatch(MigrationEvents::PRE_UP, $preEvent);
        $preMigrations = $preEvent->getMigrations();
        //Process CreateMigrationTableMigration and others
        foreach ($preMigrations as $migration) {
            $result[] = new MigrationState($migration);
        }

        $loadedVersions = $excludeLoaded ? $preEvent->getLoadedVersions() : [];
        $migrationDirectories = $this->getMigrationDirectories();
        $migrations = $this->loadMigrationScripts($migrationDirectories, $loadedVersions);

        if (empty($migrations) && empty($result)) {
            return $result;
        }

        foreach ($migrations as $version => $migration) {
            $result[] = new MigrationState($migration, 'OKVPN', $version);
        }

        $result[] = new MigrationState(new UpdateBundleVersionMigration($result, $this->migrationTable));

        // process "post" migrations
        $postEvent = new PostMigrationEvent($this->connection);
        $this->eventDispatcher->dispatch(MigrationEvents::POST_UP, $postEvent);
        $postMigrations = $postEvent->getMigrations();
        foreach ($postMigrations as $migration) {
            $result[] = new MigrationState($migration);
        }

        return $result;
    }

    /**
     * @return MigrationState[]
     */
    public function getPlainMigrations()
    {
        return $this->getMigrations(false);
    }

    /**
     * Gets a list of all directories contain migration scripts
     *
     * @return array
     *      key   = bundle name
     *      value = array
     *      .    key   = a migration version (actually it equals the name of migration directory)
     *      .            or empty string for root migration directory
     *      .    value = full path to a migration directory
     */
    protected function getMigrationDirectories()
    {
        $result = [];

        foreach ($this->bundles as $bundleName => $bundlePath) {

            $bundleMigrationPath = str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $bundlePath . '/' . $this->migrationPath
            );

            $bundleMigrationPath = rtrim($bundleMigrationPath, DIRECTORY_SEPARATOR);
            $result[$bundleName] = [$bundleMigrationPath];
        }

        return $result;
    }

    /**
     * Finds migration files and call "include_once" for each file
     *
     * @param array $migrationDirectories
     * @param $loadedVersions
     *
     * @return array
     */
    protected function loadMigrationScripts(array $migrationDirectories, array $loadedVersions = [])
    {
        $migrations = [];

        foreach ($migrationDirectories as $bundleMigrationDirectories) {
            foreach ($bundleMigrationDirectories as $migrationPath) {
                $fileFinder = new Finder();
                $fileFinder->depth(0)->files()->name('*.php')->in($migrationPath);

                foreach ($fileFinder as $file) {
                    if (preg_match('/^Version([0-9]+)\.php$/i', $file->getFilename(), $matches)) {
                        $migrationVersion = $matches[1];

                        if (!in_array($migrationVersion, $loadedVersions)) {
                            /** @var SplFileInfo $file */
                            $filePath = $file->getPathname();
                            $classes = get_declared_classes();
                            include_once $filePath;
                            $classes = array_diff(get_declared_classes(), $classes);
                            if (count($classes) !== 1) {
                                throw new \Exception('Migration file should contain only one migration class');
                            }
                            $migrationClass = reset($classes);
                            $migrations[$migrationVersion] = new $migrationClass;
                        }
                    };
                }
            }
        }

        return $migrations;
    }

    /**
     * Creates an instances of all classes implement migration scripts
     *
     * @param MigrationState[] $result
     * @param array $files Files contain migration scripts
     *                                'migrations' => array
     *                                .      key   = full file path
     *                                .      value = array
     *                                .            'bundleName' => bundle name
     *                                .            'version'    => migration version
     *                                'bundles'    => string[] names of bundles
     *
     * @throws \RuntimeException if a migration script contains more than one class
     */
    protected function createMigrationObjects(&$result, $files)
    {
        // load migration objects
        list($migrations) = $this->loadMigrationObjects($files);

        // group migration by bundle & version then sort them within same version
        $groupedMigrations = $this->groupAndSortMigrations($files, $migrations);

        var_dump($migrations, $loadedVersions);
        die();

        // add migration objects to result tacking into account bundles order
        foreach ($files['bundles'] as $bundleName) {
            // add migrations to the result
            if (isset($groupedMigrations[$bundleName])) {
                foreach ($groupedMigrations[$bundleName] as $version => $versionedMigrations) {
                    foreach ($versionedMigrations as $migration) {
                        $result[] = new MigrationState(
                            $migration,
                            $bundleName,
                            $version
                        );
                    }
                }
            }
        }
    }

    /**
     * Groups migrations by bundle and version
     * Sorts grouped migrations within the same version
     *
     * @param array $files
     * @param array $migrations
     *
     * @return array
     */
    protected function groupAndSortMigrations($files, $migrations)
    {
        $groupedMigrations = [];
        foreach ($files['migrations'] as $sourceFile => $migration) {
            if (isset($migrations[$sourceFile])) {
                $bundleName = $migration['bundleName'];
                $version = $migration['version'];
                if (!isset($groupedMigrations[$bundleName])) {
                    $groupedMigrations[$bundleName] = [];
                }
                if (!isset($groupedMigrations[$bundleName][$version])) {
                    $groupedMigrations[$bundleName][$version] = [];
                }
                $groupedMigrations[$bundleName][$version][] = $migrations[$sourceFile];
            }
        }

        return $groupedMigrations;
    }

    /**
     * Loads migration objects
     *
     * @param $files
     *
     * @return array
     * @throws \RuntimeException
     */
    protected function loadMigrationObjects($files, $loadedVersions)
    {
        $migrations = [];
        $declared = get_declared_classes();

        foreach ($declared as $className) {
            $reflClass = new \ReflectionClass($className);
            $sourceFile = $reflClass->getFileName();
            if (isset($files['migrations'][$sourceFile])) {
                if (is_subclass_of($className, 'Okvpn\Component\Migration\Migration\Migration')) {
                    $migration = new $className;
                    if (isset($migrations[$sourceFile])) {
                        throw new \RuntimeException('A migration script must contains only one class.');
                    }

                    $migrations[$sourceFile] = $migration;
                }
            }
        }

        return [
            $migrations,
        ];
    }
}
