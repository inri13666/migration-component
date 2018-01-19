<?php

namespace Okvpn\Component\Migration\Tests\Unit\Migration;

use Okvpn\Component\Migration\Migration\SqlSchemaUpdateMigrationQuery;

class SqlSchemaUpdateMigrationQueryTest extends \PHPUnit_Framework_TestCase
{
    public function testIsUpdateRequired()
    {
        $query = new SqlSchemaUpdateMigrationQuery('ALTER TABLE');

        $this->assertInstanceOf('Okvpn\Component\Migration\Migration\SqlMigrationQuery', $query);
        $this->assertInstanceOf('Okvpn\Component\Migration\Migration\SchemaUpdateQuery', $query);
        $this->assertTrue($query->isUpdateRequired());
    }
}
