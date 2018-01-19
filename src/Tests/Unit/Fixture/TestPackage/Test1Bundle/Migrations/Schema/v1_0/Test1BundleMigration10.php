<?php

namespace Okvpn\Component\Migration\Tests\Unit\Fixture\TestPackage\Test1Bundle\Migrations\Schema\v1_0;

use Doctrine\DBAL\Schema\Schema;
use Okvpn\Component\Migration\Migration\Migration;
use Okvpn\Component\Migration\Migration\QueryBag;

class Test1BundleMigration10 implements Migration
{
    /**
     * @inheritdoc
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $queries->addQuery('CREATE TABLE TEST (id INT AUTO_INCREMENT NOT NULL)');
    }
}
