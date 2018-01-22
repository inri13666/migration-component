<?php

namespace Okvpn\Component\Migration\Command;

use Okvpn\Component\Migration\Command\Helper\MigrationConsoleHelper;
use Okvpn\Component\Migration\Migration\Loader\MigrationsLoader;
use Okvpn\Component\Migration\Migration\MigrationExecutor;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class LoadCommand extends Command
{
    const NAME = 'akuma:migrations:load';

    /** @var MigrationConsoleHelper */
    protected $helper;

    public function __construct(MigrationConsoleHelper $helper = null)
    {
        parent::__construct(self::NAME);

        $this->helper = $helper;
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName(self::NAME)
            ->setDescription('Execute migration scripts.')
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Causes the generated by migrations SQL statements to be physically executed against your database.'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Outputs list of migrations without apply them.'
            )
            ->addOption(
                'show-queries',
                null,
                InputOption::VALUE_NONE,
                'Outputs list of database queries for each migration file.'
            );
    }

    /**
     * @inheritdoc
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');
        $showQueries = $input->getOption('show-queries');

        if ($force || $dryRun) {
            $migrationLoader = $this->getMigrationLoader();
            $migrations = $migrationLoader->getMigrations();
            if (!empty($migrations)) {
                $output->writeln($dryRun ? 'List of migrations:' : 'Process migrations...');

                if ($dryRun && !$showQueries) {
                    foreach ($migrations as $item) {
                        $output->writeln(sprintf('  <comment>> %s</comment>', get_class($item->getMigration())));
                    }
                } else {

                    $showQueries = $input->getOption('show-queries');
                    $queryLogger = $this->getConsoleLogger(
                        $output,
                        $showQueries ? OutputInterface::VERBOSITY_NORMAL : OutputInterface::VERBOSITY_VERBOSE
                    );

                    $executor = $this->getMigrationExecutor();
                    $executor->setLogger($this->getConsoleLogger($output));
                    $executor->getQueryExecutor()->setLogger($queryLogger);
                    $executor->executeUp($migrations, $dryRun);
                }
            } else {
                $output->writeln('There are no migrations to be loaded');
            }
        } else {
            $output->writeln(
                '<comment>ATTENTION</comment>: Database backup is highly recommended before executing this command.'
            );
            $output->writeln('');
            $output->writeln('To force execution run command with <info>--force</info> option:');
            $output->writeln(sprintf('    <info>%s --force</info>', $this->getName()));
        }
    }

    /**
     * @return MigrationConsoleHelper
     */
    protected function getOkvpnMigrationHelper()
    {
        return $this->helper ?: $this->getHelper(MigrationConsoleHelper::NAME);
    }

    /**
     * @return MigrationsLoader
     */
    protected function getMigrationLoader()
    {
        return $this->getOkvpnMigrationHelper()->getMigrationLoader();
    }

    /**
     * @return MigrationExecutor
     */
    protected function getMigrationExecutor()
    {
        return $this->getOkvpnMigrationHelper()->getMigrationExecutor();
    }

    /**
     * @param OutputInterface $output
     * @param int $debug
     *
     * @return ConsoleLogger
     */
    protected function getConsoleLogger(OutputInterface $output, $debug = OutputInterface::VERBOSITY_NORMAL)
    {
        $logger = new ConsoleLogger($output, [
            LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::ALERT => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::CRITICAL => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO => $debug,
            LogLevel::DEBUG => $debug,
        ]);

        return $logger;
    }
}