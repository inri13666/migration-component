<?php

namespace Okvpn\Component\Migration\Migration\Extension;

/**
 * RenameExtensionAwareInterface should be implemented by migrations that depends on a RenameExtension.
 */
interface RenameExtensionAwareInterface
{
    /**
     * Sets the RenameExtension
     *
     * @param RenameExtension $renameExtension
     */
    public function setRenameExtension(RenameExtension $renameExtension);
}
