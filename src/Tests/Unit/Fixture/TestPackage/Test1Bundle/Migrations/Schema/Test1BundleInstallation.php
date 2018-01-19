<?php

namespace Okvpn\Component\Migration\Tests\Unit\Fixture\TestPackage\Test1Bundle\Migrations\Schema;

use Doctrine\DBAL\Schema\Schema;
use Okvpn\Component\Migration\Migration\Installation;
use Okvpn\Component\Migration\Migration\QueryBag;

class Test1BundleInstallation implements Installation
{
    /**
     * @inheritdoc
     */
    public function getMigrationVersion()
    {
        return "v1_0";
    }

    /**
     * @inheritdoc
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $queries->addQuery('CREATE TABLE TEST (id INT AUTO_INCREMENT NOT NULL)');
    }
}
