<?php

namespace Okvpn\Component\Migration\EventListener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Okvpn\Component\Migration\Entity\DataMigration;

class DoctrineMetadataListener
{
    const DEFAULT_TABLE = 'okvpn_fixture_data';

    /** @var string */
    private $migrationTable;

    /**
     * @param string $migrationTable
     */
    public function __construct($migrationTable)
    {
        $this->migrationTable = $migrationTable;
    }

    /**
     * @param LoadClassMetadataEventArgs $eventArgs
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {
        /** @var ClassMetadata $metadata */
        $metadata = $eventArgs->getClassMetadata();
        if ($metadata->getName() === DataMigration::class) {
            $table = $metadata->table;
            $table['name'] = $this->migrationTable;
            $table['indexes'] = [
                'idx_' . $this->migrationTable =>
                    [
                        'columns' =>
                            [
                                0 => 'bundle',
                            ],
                    ],
            ];

            $metadata->setPrimaryTable($table);
        }
    }
}
