<?php

namespace Okvpn\Component\Migration\Tests\Unit\Migration\Extension;

use Okvpn\Component\Migration\Migration\Extension\DataStorageExtension;

class DataStorageExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testGet()
    {
        $dataStorage = new DataStorageExtension();
        $dataStorage->put('test', ['test1' => 'test1']);

        $this->assertEquals(
            $dataStorage->get('test'),
            ['test1' => 'test1']
        );

        $this->assertTrue($dataStorage->has('test'));
    }

    public function testHas()
    {
        $dataStorage = new DataStorageExtension();
        $dataStorage->put('test', ['test1' => 'test1']);

        $this->assertTrue($dataStorage->has('test'));
    }
}
