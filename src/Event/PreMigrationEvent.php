<?php

namespace Okvpn\Component\Migration\Event;

class PreMigrationEvent extends MigrationEvent
{
    /**
     * @var array
     *      key   = bundle name
     *      value = version
     */
    protected $loadedVersions = [];

    /**
     * Gets a list of the latest loaded versions for all bundles
     *
     * @return array
     *      key   = bundle name
     *      value = version
     */
    public function getLoadedVersions()
    {
        return $this->loadedVersions;
    }

    /**
     * Sets a number of already loaded version of the given bundle
     *
     * @param string $version
     */
    public function addLoadedVersion($version)
    {
        $this->loadedVersions[] = $version;
    }
}
