<?php

namespace Okvpn\Component\Migration\Tests\Unit\Migration\Fixtures;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Okvpn\Component\Migration\Migration\Migration;
use Okvpn\Component\Migration\Migration\QueryBag;
use Okvpn\Component\Migration\Migration\Extension\DatabasePlatformAwareInterface;
use Okvpn\Component\Migration\Migration\Extension\NameGeneratorAwareInterface;
use Okvpn\Component\Migration\Tools\DbIdentifierNameGenerator;
use Okvpn\Component\Migration\Tests\Unit\Migration\Fixtures\Extension\TestExtension;
use Okvpn\Component\Migration\Tests\Unit\Migration\Fixtures\Extension\TestExtensionAwareInterface;

class MigrationWithTestExtension implements
    Migration,
    TestExtensionAwareInterface,
    DatabasePlatformAwareInterface,
    NameGeneratorAwareInterface
{
    protected $testExtension;

    protected $platform;

    protected $nameGenerator;

    public function setTestExtension(TestExtension $testExtension)
    {
        $this->testExtension = $testExtension;
    }

    public function getTestExtension()
    {
        return $this->testExtension;
    }

    public function setDatabasePlatform(AbstractPlatform $platform)
    {
        $this->platform = $platform;
    }

    public function getDatabasePlatform()
    {
        return $this->platform;
    }

    public function setNameGenerator(DbIdentifierNameGenerator $nameGenerator)
    {
        $this->nameGenerator = $nameGenerator;
    }

    public function getNameGenerator()
    {
        return $this->nameGenerator;
    }

    public function up(Schema $schema, QueryBag $queries)
    {
    }
}
