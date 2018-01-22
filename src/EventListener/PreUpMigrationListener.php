<?php

namespace Okvpn\Component\Migration\EventListener;

use Okvpn\Component\Migration\Event\PreMigrationEvent;
use Okvpn\Component\Migration\Migration\CreateMigrationTableMigration;

class PreUpMigrationListener
{
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
     * @param PreMigrationEvent $event
     */
    public function onPreUp(PreMigrationEvent $event)
    {
        if ($event->isTableExist($this->migrationTable)) {
            $data = $event->getData(
                sprintf(
                    'select * from %s where id in (select max(id) from %s group by bundle)',
                    $this->migrationTable,
                    $this->migrationTable
                )
            );

            foreach ($data as $val) {
                $event->addLoadedVersion($val['version']);
            }
        } else {
            $event->addMigration(new CreateMigrationTableMigration($this->migrationTable));
        }
    }
}
