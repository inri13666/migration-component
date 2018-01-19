<?php

namespace Okvpn\Component\Migration\Migration\Extension;

use Okvpn\Component\Migration\Tools\DbIdentifierNameGenerator;

/**
 * NameGeneratorAwareInterface should be implemented by extensions that depends on a database identifier name generator.
 */
interface NameGeneratorAwareInterface
{
    /**
     * Sets the database identifier name generator
     *
     * @param DbIdentifierNameGenerator $nameGenerator
     */
    public function setNameGenerator(DbIdentifierNameGenerator $nameGenerator);
}
