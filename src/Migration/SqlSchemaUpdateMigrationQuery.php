<?php

namespace Okvpn\Component\Migration\Migration;

class SqlSchemaUpdateMigrationQuery extends SqlMigrationQuery implements SchemaUpdateQuery
{
    /**
     * {@inheritdoc}
     */
    public function isUpdateRequired()
    {
        return true;
    }
}
