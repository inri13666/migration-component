<?php

namespace Okvpn\Component\Migration\Tests\Unit\Migration\Fixtures\Extension;

class TestExtensionDepended implements TestExtensionAwareInterface
{
    protected $testExtension;

    public function setTestExtension(TestExtension $testExtension)
    {
        $this->testExtension = $testExtension;
    }

    public function getTestExtension()
    {
        return $this->testExtension;
    }
}
