<?php

namespace Application\Command;

use Laminas\Db\Adapter\Adapter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Who is still calling the legacy API.
 *
 * Retiring /api/* is a judgement call about hundreds of LIS installations that
 * upgrade on their own schedule, and setting api.legacy_sunset without this
 * report is guessing. A lab that appears under "legacy" and not under "v2" will
 * stop syncing on the cutoff date.
 */
#[AsCommand(
    name: 'api-usage',
    description: 'Report which labs use the v2 API and which are still on the legacy /api/* endpoints.'
)]
class ApiUsage extends Command
{
    public function __construct(private Adapter $adapter)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'days',
            null,
            InputOption::VALUE_REQUIRED,
            'Only count legacy calls made in the last N days',
            '90'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = max(1, (int) $input->getOption('days'));

        $clients = $this->query(
            "SELECT lab_id, label, instance_uuid, status, last_seen
             FROM dash_api_clients
             ORDER BY last_seen IS NULL, last_seen DESC"
        );

        // Legacy traffic is everything the tracker recorded that v2 did not
        // write; v2 tags its own rows with a request_type of 'api-v2-*'.
        $legacy = $this->query(
            "SELECT facility_id AS lab_id,
                    MAX(requested_on) AS last_call,
                    COUNT(*) AS calls
             FROM dash_track_api_requests
             WHERE requested_on >= DATE_SUB(NOW(), INTERVAL ? DAY)
               AND (request_type IS NULL OR request_type NOT LIKE 'api-v2%')
             GROUP BY facility_id
             ORDER BY last_call DESC",
            [$days]
        );

        $io->title('Smart Connect API usage');

        $io->section(sprintf('v2 clients (%d enrolled)', count($clients)));
        if ($clients === []) {
            $io->warning('No LIS has enrolled yet. Retiring the legacy API now would cut off every lab.');
        } else {
            $table = new Table($output);
            $table->setHeaders(['Lab', 'Label', 'Instance', 'Status', 'Last seen']);
            foreach ($clients as $row) {
                $table->addRow([
                    $row['lab_id'] ?? '—',
                    $row['label'] ?? '—',
                    $this->shorten((string) ($row['instance_uuid'] ?? '')),
                    $row['status'],
                    $row['last_seen'] ?? 'never called',
                ]);
            }
            $table->render();
        }

        $io->section(sprintf('Legacy /api/* callers in the last %d days (%d)', $days, count($legacy)));
        if ($legacy === []) {
            $io->success('Nothing has called the legacy endpoints in this window — the cutoff looks safe.');
        } else {
            $table = new Table($output);
            $table->setHeaders(['Lab', 'Calls', 'Last call', 'Enrolled in v2?']);
            $enrolledLabs = array_filter(array_column($clients, 'lab_id'), static fn($id): bool => $id !== null);
            foreach ($legacy as $row) {
                $table->addRow([
                    $row['lab_id'] ?? '—',
                    $row['calls'],
                    $row['last_call'],
                    in_array($row['lab_id'], $enrolledLabs) ? 'yes' : 'NO — would be cut off',
                ]);
            }
            $table->render();

            $stranded = array_filter(
                $legacy,
                static fn(array $row): bool => !in_array($row['lab_id'], $enrolledLabs)
            );

            if ($stranded !== []) {
                $io->warning(sprintf(
                    '%d lab(s) still call the legacy API and have not enrolled in v2. Setting api.legacy_sunset to a past date would stop their syncing.',
                    count($stranded)
                ));
            } else {
                $io->success('Every legacy caller has also enrolled in v2.');
            }
        }

        return Command::SUCCESS;
    }

    /** @return array<int, array<string, mixed>> */
    private function query(string $sql, array $params = []): array
    {
        $result = $params === []
            ? $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE)
            : $this->adapter->query($sql, $params);

        return $result->toArray();
    }

    private function shorten(string $value, int $length = 16): string
    {
        return strlen($value) <= $length ? $value : substr($value, 0, $length - 1) . '…';
    }
}
