<?php

namespace Okvpn\Component\Migration\Tests\Unit\Migration\Fixtures\Extension;

interface TestExtensionDependedAwareInterface
{
    public function setTestExtensionDepended(
        TestExtensionDepended $testExtensionDepended
    );
}
