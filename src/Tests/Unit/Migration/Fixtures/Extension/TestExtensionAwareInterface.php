<?php

namespace Okvpn\Component\Migration\Tests\Unit\Migration\Fixtures\Extension;

interface TestExtensionAwareInterface
{
    public function setTestExtension(TestExtension $testExtension);
}
