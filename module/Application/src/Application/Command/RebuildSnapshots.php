<?php

namespace Application\Command;

use Throwable;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\Driver\ResultInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'rebuild-snapshots',
    description: 'Rebuild the dash_form_*_current snapshot tables (last 12 months of data).'
)]
class RebuildSnapshots extends Command
{
    private const SNAPSHOT_TABLES = [
        'vl' => 'dash_form_vl',
        'eid' => 'dash_form_eid',
        'covid19' => 'dash_form_covid19',
    ];

    private Adapter $adapter;

    public function __construct(Adapter $adapter)
    {
        parent::__construct();
        $this->adapter = $adapter;
    }

    protected function configure(): void
    {
        // Default to all — the app's "current tables" session toggle switches
        // all three tables at once, so they must stay in sync.
        $this->addOption(
            'tests',
            null,
            InputOption::VALUE_REQUIRED,
            'Comma-separated list of test types to rebuild (' . implode(',', array_keys(self::SNAPSHOT_TABLES)) . ')',
            implode(',', array_keys(self::SNAPSHOT_TABLES))
        );
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Rebuild even if daily_snapshot_rebuild is off in dash_global_config'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('force') && !$this->rebuildEnabledInGlobalConfig()) {
            $output->writeln(
                'Snapshot rebuild is turned off (dash_global_config.daily_snapshot_rebuild). '
                . 'Set it to "yes" in the admin settings, or run with --force.'
            );
            return Command::SUCCESS;
        }

        $requested = array_filter(array_map('trim', explode(',', (string) $input->getOption('tests'))));
        $unknown = array_diff($requested, array_keys(self::SNAPSHOT_TABLES));
        if ($unknown !== []) {
            $output->writeln('<error>Unknown test type(s): ' . implode(', ', $unknown) . '</error>');
            return Command::INVALID;
        }

        $errors = 0;
        foreach ($requested as $testType) {
            $table = self::SNAPSHOT_TABLES[$testType];
            $output->write("Rebuilding <comment>{$table}_current</comment>... ");
            try {
                $this->adapter->query("DROP TABLE IF EXISTS `{$table}_current`", Adapter::QUERY_MODE_EXECUTE);
                $this->adapter->query("CREATE TABLE `{$table}_current` LIKE `{$table}`", Adapter::QUERY_MODE_EXECUTE);
                $result = $this->adapter->query(
                    "INSERT INTO `{$table}_current`
                     SELECT * FROM `{$table}`
                     WHERE sample_collection_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)",
                    Adapter::QUERY_MODE_EXECUTE
                );
                $rows = $result instanceof ResultInterface ? $result->getAffectedRows() : 0;
                $output->writeln("<info>{$rows} rows</info>");
            } catch (Throwable $e) {
                $output->writeln('<error>ERROR: ' . $e->getMessage() . '</error>');
                $errors++;
            }
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * OFF by default: a missing row, or any value other than yes/1/true/on,
     * means the nightly rebuild is skipped.
     */
    private function rebuildEnabledInGlobalConfig(): bool
    {
        $row = $this->adapter
            ->query(
                "SELECT `value` FROM `dash_global_config` WHERE `name` = 'daily_snapshot_rebuild'",
                Adapter::QUERY_MODE_EXECUTE
            )
            ->current();

        return !empty($row)
            && in_array(strtolower(trim((string) $row['value'])), ['yes', '1', 'true', 'on'], true);
    }
}
