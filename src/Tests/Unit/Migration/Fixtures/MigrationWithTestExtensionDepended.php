<?php

namespace Okvpn\Component\Migration\Tests\Unit\Migration\Fixtures;

use Doctrine\DBAL\Schema\Schema;
use Okvpn\Component\Migration\Migration\Migration;
use Okvpn\Component\Migration\Migration\QueryBag;
use Okvpn\Component\Migration\Tests\Unit\Migration\Fixtures\Extension\TestExtensionDepended;
use Okvpn\Component\Migration\Tests\Unit\Migration\Fixtures\Extension\TestExtensionDependedAwareInterface;

class MigrationWithTestExtensionDepended implements
    Migration,
    TestExtensionDependedAwareInterface
{
    protected $testExtensionDepended;

    public function setTestExtensionDepended(
        TestExtensionDepended $testExtensionDepended
    ) {
        $this->testExtensionDepended = $testExtensionDepended;
    }

    public function getTestExtensionDepended()
    {
        return $this->testExtensionDepended;
    }

    public function up(Schema $schema, QueryBag $queries)
    {
    }
}
