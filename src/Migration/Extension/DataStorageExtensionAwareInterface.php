<?php

namespace Okvpn\Component\Migration\Migration\Extension;

interface DataStorageExtensionAwareInterface
{
    /**
     * @param DataStorageExtension $dataStorageExtension
     */
    public function setDataStorageExtension(DataStorageExtension $dataStorageExtension);
}
