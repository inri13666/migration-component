<?php

namespace Okvpn\Component\Migration\Tests\Unit\Fixture\TestPackage;

use Doctrine\DBAL\Schema\Schema;
use Okvpn\Component\Migration\Migration\Migration;
use Okvpn\Component\Migration\Migration\QueryBag;

class WrongTableNameMigration implements Migration
{
    /**
     * @inheritdoc
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $schema->createTable('extra_long_table_name_bigger_than_30_chars');
        $table->addColumn('id', 'integer');
    }
}
