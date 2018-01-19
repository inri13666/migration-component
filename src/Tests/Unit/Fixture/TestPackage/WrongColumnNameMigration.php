<?php

namespace Okvpn\Component\Migration\Tests\Unit\Fixture\TestPackage;

use Doctrine\DBAL\Schema\Schema;
use Okvpn\Component\Migration\Migration\Migration;
use Okvpn\Component\Migration\Migration\QueryBag;

class WrongColumnNameMigration implements Migration
{
    /**
     * @inheritdoc
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $schema->createTable('wrong_table');
        $table->addColumn('extra_long_column_bigger_30_chars', 'integer');
    }
}
