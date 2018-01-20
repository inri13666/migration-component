<?php

namespace Okvpn\Component\Migration\Migration\Loader;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class LegacyMigrationsLoader extends MigrationsLoader
{
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
     *               key   = bundle name
     *               value = array
     *               .    key   = a migration version or empty string for root migration directory
     *               .    value = full path to a migration directory
     *
     * @return array loaded files
     *               'migrations' => array
     *               .      key   = full file path
     *               .      value = array
     *               .            'bundleName' => bundle name
     *               .            'version'    => migration version
     *               'installers' => array
     *               .      key   = full file path
     *               .      value = bundle name
     *               'bundles'    => string[] names of bundles
     */
    protected function loadMigrationScripts(array $migrationDirectories)
    {
        $migrations = [];
        $installers = [];

        foreach ($migrationDirectories as $bundleName => $bundleMigrationDirectories) {
            foreach ($bundleMigrationDirectories as $migrationPath) {
                $fileFinder = new Finder();
                $fileFinder->depth(0)->files()->name('*.php')->in($migrationPath);

                foreach ($fileFinder as $file) {
                    /** @var SplFileInfo $file */
                    $filePath = $file->getPathname();
                    include_once $filePath;
                    if (preg_match('/^Version([0-9]+)\.php$/i', $file->getFilename(), $matches)) {
                        $migrationVersion = $matches[1];
                        $migrations[$filePath] = ['bundleName' => $bundleName, 'version' => $migrationVersion];
                    };
                }
            }
        }

        return [
            'migrations' => $migrations,
            'installers' => $installers,
            'bundles' => array_keys($migrationDirectories),
        ];
    }

    /**
     * Loads migration objects
     *
     * @param $files
     *
     * @return array
     * @throws \RuntimeException
     */
    protected function loadMigrationObjects($files)
    {
        $migrations = [];
        $installers = [];
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
            $installers,
        ];
    }

    /**
     * Removes already installed migrations
     *
     * @param array $migrationDirectories
     *      key   = bundle name
     *      value = array
     *      .    key   = a migration version or empty string for root migration directory
     *      .    value = full path to a migration directory
     */
    protected function filterMigrations(array &$migrationDirectories)
    {
        if (!empty($this->loadedVersions)) {
            foreach ($migrationDirectories as $bundleName => $bundleMigrationDirectories) {
                $loadedVersion = isset($this->loadedVersions[$bundleName])
                    ? $this->loadedVersions[$bundleName]
                    : null;
                if ($loadedVersion) {
                    foreach (array_keys($bundleMigrationDirectories) as $migrationVersion) {
                        if (empty($migrationVersion) || version_compare($migrationVersion, $loadedVersion) < 1) {
                            unset($migrationDirectories[$bundleName][$migrationVersion]);
                        }
                    }
                }
            }
        }
    }
}
