<?php

namespace Okvpn\Component\Migration\Tests\Unit\Migration\Fixtures\Extension;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Okvpn\Component\Migration\Migration\Extension\DatabasePlatformAwareInterface;
use Okvpn\Component\Migration\Migration\Extension\NameGeneratorAwareInterface;
use Okvpn\Component\Migration\Tools\DbIdentifierNameGenerator;

class TestExtension implements DatabasePlatformAwareInterface, NameGeneratorAwareInterface
{
    protected $platform;

    protected $nameGenerator;

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
}
