<?php

namespace Okvpn\Component\Migration\Tests\Unit\Fixture\TestPackage\Test2Bundle\Migrations\Schema\v1_1;

use Doctrine\DBAL\Schema\Schema;
use Okvpn\Component\Migration\Migration\Migration;
use Okvpn\Component\Migration\Migration\OrderedMigrationInterface;
use Okvpn\Component\Migration\Migration\QueryBag;

class Test2BundleMigration11 implements Migration, OrderedMigrationInterface
{
    public function getOrder()
    {
        return 2;
    }

    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $schema->getTable('test1table');
        $table->addColumn('another_column', 'int');
    }
}
