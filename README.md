# OkvpnMigrationComponent

TODO: Add description


### Silex 1.2 Example

```php
<?php

use Okvpn\Component\Migration\Command\Helper\MigrationConsoleHelper;
use Symfony\Component\{
    Console\Application,
    Console\Helper\HelperSet,
    Console\Helper\QuestionHelper,
    Console\Output\ConsoleOutput
};

// Application
$app = new \Silex\Application();

//.... Silex Application configuration
// for orm.em see here http://dflydev.com/projects/doctrine-orm-service-provider/
// for twig see here https://silex.symfony.com/doc/1.3/providers/twig.html

$application = new Application();
$helperSet = new HelperSet();
$application->setHelperSet($helperSet);
$helperSet->set(new QuestionHelper(), 'dialog');
$helper = new MigrationConsoleHelper($app->offsetGet('twig'), $app->offsetGet('orm.em'), $app->offsetGet('dispatcher'));
//Migrations placed at "../src/AcmeBundle/Migrations/Schema"
$helper->getMigrationLoader()->addBundle('Acme', dirname(__FILE__).'src/AcmeBundle');
//Add more places ...

$helperSet->set($helper);

$application->addCommands([
    new Okvpn\Component\Migration\Command\DumpMigrationsCommand(),
    new Okvpn\Component\Migration\Command\DiffMigrationsCommand(),
    new Okvpn\Component\Migration\Command\LoadMigrationsCommand(),
]);

$application->run();
```
