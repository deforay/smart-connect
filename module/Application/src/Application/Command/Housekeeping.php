<?php

namespace Application\Command;

use Throwable;
use FilesystemIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Laminas\Db\Adapter\Adapter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'housekeeping',
    description: 'Remove old temporary files, uploads, cache files and stale DB rows.'
)]
class Housekeeping extends Command
{
    // Files that must never be pruned from a cleaned directory, regardless of
    // age or size: directory-protection files (.htaccess / index.php) and VCS
    // marker files that keep otherwise-empty dirs tracked.
    private const PROTECTED_FILES = ['.htaccess', 'index.php', '.gitkeep', '.hgkeep'];

    private Adapter $adapter;

    public function __construct(Adapter $adapter)
    {
        parent::__construct();
        $this->adapter = $adapter;
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Default file retention in days', 30)
            ->addOption('db-days', null, InputOption::VALUE_REQUIRED, 'DB row retention in days', 365)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be deleted without deleting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $defaultDays = max(1, (int) $input->getOption('days'));
        $dbDays = max(1, (int) $input->getOption('db-days'));
        $dryRun = (bool) $input->getOption('dry-run');

        $appPath = defined('APPLICATION_PATH') ? APPLICATION_PATH : getcwd();
        $webRoot = defined('WEB_ROOT') ? WEB_ROOT : $appPath . DIRECTORY_SEPARATOR . 'public';
        $uploadPath = defined('UPLOAD_PATH') ? UPLOAD_PATH : $webRoot . DIRECTORY_SEPARATOR . 'uploads';
        $tempUploadPath = defined('TEMP_UPLOAD_PATH') ? TEMP_UPLOAD_PATH : $webRoot . DIRECTORY_SEPARATOR . 'temporary';

        $output->writeln('');
        $output->writeln('<info>HOUSEKEEPING STARTED: ' . date('Y-m-d H:i:s') . ($dryRun ? ' (DRY RUN)' : '') . '</info>');
        $output->writeln(str_repeat('=', 70));
        $output->writeln("Default file retention: <comment>{$defaultDays} day(s)</comment>, DB retention: <comment>{$dbDays} day(s)</comment>");

        // duration = days to keep (null = no age limit); max_size_mb = cap after
        // which oldest files are removed until the folder is back to 80% of cap.
        // The vlsm-* dirs hold raw payloads spooled by the /api/vlsm,
        // /api/vlsm-eid, /api/vlsm-covid19 and /api/vlsm-metadata endpoints —
        // already parsed during the request, so they only need a short window
        // for debugging before being pruned aggressively.
        $cleanup = [
            $tempUploadPath . DIRECTORY_SEPARATOR . 'vlsm-vl' => ['duration' => 3, 'max_size_mb' => 1000],
            $tempUploadPath . DIRECTORY_SEPARATOR . 'vlsm-eid' => ['duration' => 3, 'max_size_mb' => 1000],
            $tempUploadPath . DIRECTORY_SEPARATOR . 'vlsm-covid19' => ['duration' => 3, 'max_size_mb' => 1000],
            $tempUploadPath . DIRECTORY_SEPARATOR . 'vlsm-reference' => ['duration' => 3, 'max_size_mb' => 1000],
            $tempUploadPath => ['duration' => 7, 'max_size_mb' => 2000],
            $appPath . DIRECTORY_SEPARATOR . 'temporary' => ['duration' => 3, 'max_size_mb' => 500],
            $uploadPath . DIRECTORY_SEPARATOR . 'track-api' . DIRECTORY_SEPARATOR . 'requests' => ['duration' => 120, 'max_size_mb' => 1000],
            $uploadPath . DIRECTORY_SEPARATOR . 'track-api' . DIRECTORY_SEPARATOR . 'responses' => ['duration' => 120, 'max_size_mb' => 1000],
            $uploadPath . DIRECTORY_SEPARATOR . 'not-import-vl' => ['duration' => $defaultDays, 'max_size_mb' => null],
            $appPath . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cache' => ['duration' => null, 'max_size_mb' => 500],
            $appPath . DIRECTORY_SEPARATOR . 'backup' => ['duration' => $defaultDays, 'max_size_mb' => null],
        ];

        $totals = ['files_deleted' => 0, 'dirs_deleted' => 0, 'bytes_freed' => 0, 'errors' => 0];

        $output->writeln("\n<info>FILE SYSTEM CLEANUP</info>");
        $output->writeln(str_repeat('-', 70));

        foreach ($cleanup as $folder => $config) {
            $stats = $this->cleanupDirectory(
                $folder,
                $config['duration'],
                isset($config['max_size_mb']) ? $config['max_size_mb'] * 1024 * 1024 : null,
                $dryRun,
                $output
            );
            foreach ($stats as $key => $value) {
                $totals[$key] += $value;
            }
        }

        $output->writeln("\n<info>DATABASE CLEANUP</info>");
        $output->writeln(str_repeat('-', 70));

        $tablesToCleanup = [
            'activity_log' => "date_time < NOW() - INTERVAL {$dbDays} DAY",
            'user_login_history' => "login_attempted_datetime < NOW() - INTERVAL {$dbDays} DAY",
            'dash_track_api_requests' => "requested_on < NOW() - INTERVAL {$dbDays} DAY",
        ];

        $dbStats = ['rows_deleted' => 0, 'errors' => 0];

        foreach ($tablesToCleanup as $table => $condition) {
            $output->write("Processing table <comment>{$table}</comment>... ");
            try {
                $count = (int) $this->adapter
                    ->query("SELECT COUNT(*) AS c FROM `{$table}` WHERE {$condition}", Adapter::QUERY_MODE_EXECUTE)
                    ->current()['c'];

                if ($count === 0) {
                    $output->writeln('<comment>nothing to delete</comment>');
                    continue;
                }

                if ($dryRun) {
                    $output->writeln("would delete <comment>{$count}</comment> rows");
                } else {
                    $this->adapter->query("DELETE FROM `{$table}` WHERE {$condition}", Adapter::QUERY_MODE_EXECUTE);
                    $output->writeln("deleted <comment>{$count}</comment> rows");
                }
                $dbStats['rows_deleted'] += $count;
            } catch (Throwable $e) {
                $output->writeln('<error>ERROR: ' . $e->getMessage() . '</error>');
                $dbStats['errors']++;
            }
        }

        $output->writeln('');
        $output->writeln('<info>HOUSEKEEPING COMPLETED: ' . date('Y-m-d H:i:s') . '</info>');
        $output->writeln(str_repeat('=', 70));

        $table = new Table($output);
        $table->setHeaders(['Metric', 'Value']);
        $table->setRows([
            ['Files ' . ($dryRun ? 'to delete' : 'deleted'), $totals['files_deleted']],
            ['Empty dirs ' . ($dryRun ? 'to remove' : 'removed'), $totals['dirs_deleted']],
            ['Space ' . ($dryRun ? 'to free' : 'freed'), $this->formatBytes($totals['bytes_freed'])],
            ['DB rows ' . ($dryRun ? 'to delete' : 'deleted'), $dbStats['rows_deleted']],
            ['Errors', $totals['errors'] + $dbStats['errors']],
        ]);
        $table->render();
        $output->writeln('');

        return ($totals['errors'] + $dbStats['errors']) > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Delete files older than $duration days and, if the folder exceeds
     * $maxSizeBytes, keep deleting oldest-first until it is at 80% of the cap.
     */
    private function cleanupDirectory(string $folder, ?int $duration, ?int $maxSizeBytes, bool $dryRun, OutputInterface $output): array
    {
        $stats = ['files_deleted' => 0, 'dirs_deleted' => 0, 'bytes_freed' => 0, 'errors' => 0];

        if (!is_dir($folder)) {
            return $stats;
        }

        $files = [];
        $currentSize = 0;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile() || in_array($file->getFilename(), self::PROTECTED_FILES, true)) {
                    continue;
                }
                $size = $file->getSize();
                $currentSize += $size;
                $files[] = ['path' => $file->getPathname(), 'mtime' => $file->getMTime(), 'size' => $size];
            }
        } catch (Throwable $e) {
            $output->writeln("<error>Failed scanning {$folder}: {$e->getMessage()}</error>");
            $stats['errors']++;
            return $stats;
        }

        $output->writeln("\nProcessing <comment>{$folder}</comment> (" . $this->formatBytes($currentSize) . ')');

        // Oldest first so the size cap trims the least recent files
        usort($files, fn($a, $b): int => $a['mtime'] <=> $b['mtime']);

        $cutoff = $duration !== null ? time() - ($duration * 86400) : null;
        $targetSize = ($maxSizeBytes !== null && $currentSize > $maxSizeBytes) ? (int) ($maxSizeBytes * 0.8) : null;

        foreach ($files as $file) {
            $tooOld = $cutoff !== null && $file['mtime'] < $cutoff;
            $overCap = $targetSize !== null && $currentSize > $targetSize;
            if (!$tooOld && !$overCap) {
                continue;
            }

            if ($dryRun || @unlink($file['path'])) {
                $stats['files_deleted']++;
                $stats['bytes_freed'] += $file['size'];
                $currentSize -= $file['size'];
            } else {
                $stats['errors']++;
            }
        }

        if (!$dryRun) {
            $stats['dirs_deleted'] = $this->pruneEmptyDirs($folder);
        }

        if ($stats['files_deleted'] > 0) {
            $verb = $dryRun ? 'Would delete' : 'Deleted';
            $output->writeln("  {$verb} {$stats['files_deleted']} file(s), " . $this->formatBytes($stats['bytes_freed']));
        } else {
            $output->writeln('  Nothing to delete');
        }
        if ($stats['errors'] > 0) {
            $output->writeln("  <error>{$stats['errors']} error(s)</error>");
        }

        return $stats;
    }

    /**
     * Remove empty directories under $folder (deepest first) and return the count.
     */
    private function pruneEmptyDirs(string $folder): int
    {
        $deleted = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir() && count(scandir($item->getPathname())) === 2 && @rmdir($item->getPathname())) {
                $deleted++;
            }
        }
        return $deleted;
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = min((int) floor(($bytes ? log($bytes) : 0) / log(1024)), count($units) - 1);
        return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
    }
}
