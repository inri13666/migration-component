<?php

namespace Okvpn\Component\Migration\Tests\Unit\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

use Okvpn\Component\Migration\Migration\MigrationExecutor;
use Okvpn\Component\Migration\Migration\MigrationState;
use Okvpn\Component\Migration\Migration\QueryBag;
use Okvpn\Component\Migration\Migration\SqlSchemaUpdateMigrationQuery;
use Okvpn\Component\Migration\Tests\Unit\Fixture\TestPackage\IndexMigration;
use Okvpn\Component\Migration\Tests\Unit\Fixture\TestPackage\Test1Bundle\Migrations\Schema\Test1BundleInstallation;
use Okvpn\Component\Migration\Tests\Unit\Fixture\TestPackage\UpdatedColumnIndexMigration;

class MigrationExecutorTest extends AbstractTestMigrationExecutor
{
    /** @var MigrationExecutor */
    protected $executor;

    protected function setUp()
    {
        parent::setUp();

        $this->executor = new MigrationExecutor($this->queryExecutor);
        $this->executor->setLogger($this->logger);
    }

    public function testIndexesSuccessful()
    {
        $migrations = [
            new MigrationState(new IndexMigration()),
        ];

        $this->executor->executeUp($migrations);
    }

    public function testIndexFailed()
    {
        $migrations = ['InvalidIndexMigration'];
        $migrationsToExecute = [];
        foreach ($migrations as $migration) {
            $migrationClass = 'Okvpn\\Bundle\\MigrationBundle\\Tests\\Unit\\Fixture\\TestPackage\\' . $migration;
            $migrationsToExecute[] = new MigrationState(new $migrationClass());
        }

        $this->expectException('\RuntimeException');
        $this->expectExceptionMessage(
            'Failed migrations: Okvpn\Component\Migration\Tests\Unit\Fixture\TestPackage\InvalidIndexMigration.'
        );

        $this->executor->executeUp($migrationsToExecute);
        $this->assertEquals(
            '> Okvpn\Component\Migration\Tests\Unit\Fixture\TestPackage\InvalidIndexMigration',
            $this->logger->getMessages()[0]
        );
        $this->assertEquals(
            '  ERROR: Could not create index for column with length more than 255.'
            . ' Please correct "key" column length "index_table" in table in'
            . ' "Okvpn\Component\Migration\Tests\Unit\Fixture\TestPackage\InvalidIndexMigration" migration',
            $this->logger->getMessages()[1]
        );
    }

    public function testUpdatedColumnIndexFailed()
    {
        $migrations = ['IndexMigration', 'UpdatedColumnIndexMigration'];
        $migrationsToExecute = [];
        foreach ($migrations as $migration) {
            $migrationClass = 'Okvpn\\Bundle\\MigrationBundle\\Tests\\Unit\\Fixture\\TestPackage\\' . $migration;
            $migrationsToExecute[] = new MigrationState(new $migrationClass());
        }
        $migrationsToExecute[] = new MigrationState(new Test1BundleInstallation());

        $this->expectException('\RuntimeException');
        $this->expectExceptionMessage(sprintf('Failed migrations: %s.', UpdatedColumnIndexMigration::class));

        $this->executor->executeUp($migrationsToExecute);
        $this->assertEquals(
            '> Okvpn\Component\Migration\Tests\Unit\Fixture\TestPackage\UpdatedColumnIndexMigration',
            $this->logger->getMessages()[2]
        );
        $this->assertEquals(
            '  ERROR: Could not create index for column with length more than 255.'
            . ' Please correct "key" column length "index_table2" in table in'
            . ' "Okvpn\Component\Migration\Tests\Unit\Fixture\TestPackage\UpdatedColumnIndexMigration" migration',
            $this->logger->getMessages()[3]
        );
        $this->assertEquals(
            '> Okvpn\Component\Migration\Tests\Unit\Fixture\TestPackage\Test1Bundle\Migrations\Schema'
            . '\Test1BundleInstallation - skipped',
            $this->logger->getMessages()[4]
        );
    }

    public function testExecuteUpMigrationWithSchemaUpdate()
    {
        $schema = new Schema();

        $platform = $this->getMockBuilder('Doctrine\DBAL\Platforms\AbstractPlatform')
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $schemaUpdateQuery = new SqlSchemaUpdateMigrationQuery('ALTER TABLE');

        $migration = $this->createMock('Okvpn\Component\Migration\Migration\Migration');
        $migration->expects($this->once())
            ->method('up')
            ->willReturnCallback(
                function (Schema $schema, QueryBag $queries) use ($schemaUpdateQuery) {
                    $queries->addQuery($schemaUpdateQuery);
                }
            );

        $this->assertEmpty($schema->getTables());
        $this->executor->executeUpMigration($schema, $platform, $migration);
        $this->assertNotEmpty($schema->getTables()); // schema was updated
    }

    /**
     * @return Table[]
     */
    protected function getTables()
    {
        return [
            new Table(
                'index_table2',
                [
                    new Column(
                        'key',
                        Type::getType('string'),
                        [
                            'length' => 255,
                        ]
                    ),
                ]
            ),
        ];
    }
}
