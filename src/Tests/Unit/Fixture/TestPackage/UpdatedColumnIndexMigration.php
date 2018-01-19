<?php

namespace Okvpn\Component\Migration\Tests\Unit\Fixture\TestPackage;

use Doctrine\DBAL\Schema\Schema;
use Okvpn\Component\Migration\Migration\Migration;
use Okvpn\Component\Migration\Migration\QueryBag;

class UpdatedColumnIndexMigration implements Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $schema->getTable('index_table2');
        $table->getColumn('key')->setLength(500);
        $table->addIndex(['key'], 'index2');
    }
}
