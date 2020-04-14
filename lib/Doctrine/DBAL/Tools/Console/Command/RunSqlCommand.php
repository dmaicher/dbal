<?php

namespace Doctrine\DBAL\Tools\Console\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Tools\Dumper;
use LogicException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function assert;
use function is_numeric;
use function is_string;
use function stripos;

/**
 * Task for executing arbitrary SQL that can come from a file or directly from
 * the command line.
 */
class RunSqlCommand extends Command
{
    /**
     * @var callable|null
     */
    private $connectionRegistry;

    public function __construct(?callable $connectionRegistry = null)
    {
        parent::__construct();
        $this->connectionRegistry = $connectionRegistry;
        if (null === $connectionRegistry) {
            @trigger_error('Not passing a connection registry callback as the first constructor argument is deprecated', E_USER_DEPRECATED);
        }
    }

    /** @return void */
    protected function configure()
    {
        $this
        ->setName('dbal:run-sql')
        ->setDescription('Executes arbitrary SQL directly from the command line.')
        ->setDefinition([
            new InputOption('connection', null, InputOption::VALUE_REQUIRED, 'The named database connection'),
            new InputArgument('sql', InputArgument::REQUIRED, 'The SQL statement to execute.'),
            new InputOption('depth', null, InputOption::VALUE_REQUIRED, 'Dumping depth of result set.', 7),
            new InputOption('force-fetch', null, InputOption::VALUE_NONE, 'Forces fetching the result.'),
        ])
        ->setHelp(<<<EOT
Executes arbitrary SQL directly from the command line.
EOT
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (null !== $this->connectionRegistry) {
            $conn = call_user_func($this->connectionRegistry, [$input->getOption('connection')]);
        } else {
            // TODO: trigger another deprecation?
            // TODO: exception in case connection option is specified?
            $conn = $this->getHelper('db')->getConnection();
        }

        $sql = $input->getArgument('sql');

        if ($sql === null) {
            throw new RuntimeException("Argument 'SQL' is required in order to execute this command correctly.");
        }

        assert(is_string($sql));

        $depth = $input->getOption('depth');

        if (! is_numeric($depth)) {
            throw new LogicException("Option 'depth' must contains an integer value");
        }

        if (stripos($sql, 'select') === 0 || $input->getOption('force-fetch')) {
            $resultSet = $conn->fetchAll($sql);
        } else {
            $resultSet = $conn->executeUpdate($sql);
        }

        $output->write(Dumper::dump($resultSet, (int) $depth));

        return 0;
    }
}
