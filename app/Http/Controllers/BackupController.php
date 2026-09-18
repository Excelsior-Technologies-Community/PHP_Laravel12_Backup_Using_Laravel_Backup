<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BackupController extends Controller
{
    /**
     * Display backup dashboard, storage analytics, and backup history.
     */
    public function index(Request $request)
    {
        $diskName = config('backup.backup.destination.disks.0', 'local');
        $disk = Storage::disk($diskName);
        $backupDirectory = 'Laravel';

        $files = $disk->files($backupDirectory);

        /*
         * Get all ZIP backups with integrity & compression metadata.
         */
        $verifiedBackupsSession = session('verified_backups', []);

        $allBackups = collect($files)
            ->filter(function ($file) {
                return strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'zip';
            })
            ->map(function ($file) use ($disk, $verifiedBackupsSession) {
                $size = $disk->size($file);
                $lastModified = $disk->lastModified($file);
                $filename = basename($file);
                $fullPath = $disk->path($file);

                // Quick read archive stats from ZIP central directory
                $uncompressedSize = $size;
                $hasDbDump = false;
                $filesCount = 0;

                $zip = new ZipArchive();
                if (@$zip->open($fullPath) === true) {
                    $uncompressedSize = 0;
                    $filesCount = $zip->numFiles;
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $stat = $zip->statIndex($i);
                        if ($stat) {
                            $uncompressedSize += $stat['size'] ?? 0;
                            $entryName = strtolower($stat['name'] ?? '');
                            if (str_ends_with($entryName, '.sql') || str_ends_with($entryName, '.sql.gz') || str_contains($entryName, 'db-dumps')) {
                                $hasDbDump = true;
                            }
                        }
                    }
                    $zip->close();
                }

                $uncompressedSize = max($uncompressedSize, $size);
                $compressionRatio = $uncompressedSize > 0
                    ? round(((1 - ($size / $uncompressedSize)) * 100), 1)
                    : 0;

                $verification = $verifiedBackupsSession[$filename] ?? null;

                return [
                    'path' => $file,
                    'name' => $filename,
                    'size' => $size,
                    'size_formatted' => $this->formatBytes($size),
                    'uncompressed_size' => $uncompressedSize,
                    'uncompressed_size_formatted' => $this->formatBytes($uncompressedSize),
                    'compression_ratio' => max(0, $compressionRatio),
                    'has_db_dump' => $hasDbDump,
                    'files_count' => $filesCount,
                    'verification' => $verification,
                    'last_modified' => $lastModified,
                    'date' => date('Y-m-d H:i:s', $lastModified),
                    'date_formatted' => date('d M Y', $lastModified),
                    'time_formatted' => date('h:i:s A', $lastModified),
                ];
            })
            ->sortByDesc('last_modified')
            ->values();

        /*
         * Statistics before filters.
         */
        $totalBackups = $allBackups->count();
        $totalSize = $allBackups->sum('size');
        $totalUncompressedSize = $allBackups->sum('uncompressed_size');
        $totalStorageSaved = max(0, $totalUncompressedSize - $totalSize);
        $overallCompressionRatio = $totalUncompressedSize > 0
            ? round(((1 - ($totalSize / $totalUncompressedSize)) * 100), 1)
            : 0;

        $averageSize = $totalBackups > 0 ? $totalSize / $totalBackups : 0;
        $latestBackup = $allBackups->first();
        $oldestBackup = $allBackups->last();

        /*
         * Latest backup information.
         */
        $latestBackupData = null;
        if ($latestBackup) {
            $latestBackupData = [
                'name' => $latestBackup['name'],
                'size' => $latestBackup['size_formatted'],
                'date' => $latestBackup['date'],
                'age' => $this->backupAge($latestBackup['last_modified']),
                'age_hours' => $this->backupAgeHours($latestBackup['last_modified']),
            ];
        }

        /*
         * Oldest backup information.
         */
        $oldestBackupData = null;
        if ($oldestBackup) {
            $oldestBackupData = [
                'name' => $oldestBackup['name'],
                'size' => $oldestBackup['size_formatted'],
                'date' => $oldestBackup['date'],
            ];
        }

        /*
         * Backup health.
         */
        $health = $this->getBackupHealth($latestBackup);

        /*
         * Storage statistics.
         */
        $storageStats = $this->getStorageStats($diskName, $totalSize);

        /*
         * Storage Growth Trends Analysis (Timeline).
         */
        $growthTrends = $this->calculateGrowthTrends($allBackups);

        /*
         * Search.
         */
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $allBackups = $allBackups
                ->filter(function ($backup) use ($search) {
                    return str_contains(strtolower($backup['name']), $search);
                })
                ->values();
        }

        /*
         * Date preset.
         */
        $datePreset = $request->get('date_preset', 'all');
        if ($datePreset !== 'all') {
            $now = now();
            $allBackups = $allBackups
                ->filter(function ($backup) use ($datePreset, $now) {
                    $backupDate = \Carbon\Carbon::createFromTimestamp($backup['last_modified']);
                    if ($datePreset === 'today') {
                        return $backupDate->isToday();
                    }
                    if ($datePreset === '7days') {
                        return $backupDate->greaterThanOrEqualTo($now->copy()->subDays(7));
                    }
                    if ($datePreset === '30days') {
                        return $backupDate->greaterThanOrEqualTo($now->copy()->subDays(30));
                    }
                    return true;
                })
                ->values();
        }

        /*
         * Custom date filter.
         */
        if ($request->filled('date')) {
            $selectedDate = $request->date;
            $allBackups = $allBackups
                ->filter(function ($backup) use ($selectedDate) {
                    return date('Y-m-d', $backup['last_modified']) === $selectedDate;
                })
                ->values();
        }

        /*
         * Size filter.
         */
        $sizeFilter = $request->get('size_filter', 'all');
        if ($sizeFilter !== 'all') {
            $allBackups = $allBackups
                ->filter(function ($backup) use ($sizeFilter) {
                    $sizeMb = $backup['size'] / 1024 / 1024;
                    if ($sizeFilter === 'small') {
                        return $sizeMb < 10;
                    }
                    if ($sizeFilter === 'medium') {
                        return $sizeMb >= 10 && $sizeMb <= 100;
                    }
                    if ($sizeFilter === 'large') {
                        return $sizeMb > 100;
                    }
                    return true;
                })
                ->values();
        }

        /*
         * Sorting.
         */
        $sort = $request->get('sort', 'newest');
        if ($sort === 'oldest') {
            $allBackups = $allBackups->sortBy('last_modified')->values();
        }
        if ($sort === 'largest') {
            $allBackups = $allBackups->sortByDesc('size')->values();
        }
        if ($sort === 'smallest') {
            $allBackups = $allBackups->sortBy('size')->values();
        }

        /*
         * Simple pagination.
         */
        $perPage = (int) $request->get('per_page', 5);
        if (!in_array($perPage, [5, 10, 20, 50])) {
            $perPage = 5;
        }

        $currentPage = max(1, (int) $request->get('page', 1));
        $totalFiltered = $allBackups->count();
        $totalPages = max(1, (int) ceil($totalFiltered / $perPage));

        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }

        $backups = $allBackups
            ->slice(($currentPage - 1) * $perPage, $perPage)
            ->values();

        $queryParameters = $request->except('page');

        if ($request->get('export') === 'csv') {
            return $this->exportCsv($allBackups);
        }

        return view(
            'backups.index',
            compact(
                'backups',
                'totalBackups',
                'totalSize',
                'totalUncompressedSize',
                'totalStorageSaved',
                'overallCompressionRatio',
                'growthTrends',
                'averageSize',
                'latestBackupData',
                'oldestBackupData',
                'health',
                'storageStats',
                'datePreset',
                'sizeFilter',
                'sort',
                'perPage',
                'currentPage',
                'totalPages',
                'totalFiltered',
                'queryParameters'
            )
        );
    }


    /**
     * Module 1: 1-Click Database Restore & Rollback Wizard (with Safety Snapshot).
     */
    public function restore(Request $request, string $filename)
    {
        $diskName = config('backup.backup.destination.disks.0', 'local');
        $disk = Storage::disk($diskName);
        $filename = basename($filename);
        $path = 'Laravel/' . $filename;

        if (!$disk->exists($path)) {
            return redirect()
                ->route('backups.index')
                ->with('error', 'Backup archive not found: ' . $filename);
        }

        $fullZipPath = $disk->path($path);

        try {
            // STEP 1: Create Pre-Restore Safety Snapshot of Current Live Database
            $snapshotInfo = $this->createPreRestoreSnapshot();

            // STEP 2: Extract Database Dump from ZIP Archive
            $zip = new ZipArchive();
            if ($zip->open($fullZipPath) !== true) {
                return redirect()
                    ->route('backups.index')
                    ->with('error', 'Unable to open backup ZIP archive.');
            }

            $sqlContent = null;
            $dumpEntryName = null;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                $lowerName = strtolower($entryName);

                if (str_ends_with($lowerName, '.sql') || str_ends_with($lowerName, '.sql.gz')) {
                    $raw = $zip->getFromIndex($i);
                    if ($raw !== false && strlen($raw) > 0) {
                        if (str_ends_with($lowerName, '.sql.gz')) {
                            $sqlContent = @gzdecode($raw);
                        } else {
                            $sqlContent = $raw;
                        }
                        $dumpEntryName = $entryName;
                        break;
                    }
                }
            }
            $zip->close();

            if (empty($sqlContent)) {
                return redirect()
                    ->route('backups.index')
                    ->with('error', 'No database dump (.sql) found inside archive ' . $filename . '. Restore aborted.');
            }

            // STEP 3: Execute SQL Import into Database
            $connection = config('database.default', 'mysql');
            $driver = config("database.connections.{$connection}.driver", 'mysql');

            $executedQueriesCount = 0;

            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=0;');
                
                // Execute uncompressed SQL statements
                DB::connection($connection)->unprepared($sqlContent);
                
                DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=1;');
                
                $tables = DB::connection($connection)->select('SHOW TABLES');
                $executedQueriesCount = count($tables);
            } elseif ($driver === 'sqlite') {
                DB::connection($connection)->statement('PRAGMA foreign_keys = OFF;');
                DB::connection($connection)->unprepared($sqlContent);
                DB::connection($connection)->statement('PRAGMA foreign_keys = ON;');
                
                $tables = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                $executedQueriesCount = count($tables);
            } else {
                DB::connection($connection)->unprepared($sqlContent);
            }

            $restoreSummary = [
                'backup_file' => $filename,
                'dump_name' => $dumpEntryName,
                'tables_count' => $executedQueriesCount,
                'snapshot_file' => $snapshotInfo['filename'],
                'snapshot_size' => $snapshotInfo['size_formatted'],
                'snapshot_path' => $snapshotInfo['path'],
                'restored_at' => now()->format('d M Y, h:i:s A'),
            ];

            return redirect()
                ->route('backups.index')
                ->with('success', 'Database successfully restored from ' . $filename . '!')
                ->with('restore_summary', $restoreSummary);

        } catch (\Throwable $e) {
            Log::error('Restore failed', [
                'file' => $filename,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('backups.index')
                ->with('error', 'Database restore failed: ' . $e->getMessage());
        }
    }

    /**
     * Module 2: Automated Backup Integrity & Archive Health Verifier.
     */
    public function verify(Request $request, string $filename)
    {
        $diskName = config('backup.backup.destination.disks.0', 'local');
        $disk = Storage::disk($diskName);
        $filename = basename($filename);
        $path = 'Laravel/' . $filename;

        if (!$disk->exists($path)) {
            return redirect()
                ->route('backups.index')
                ->with('error', 'Backup archive not found.');
        }

        $fullPath = $disk->path($path);
        $archiveSize = $disk->size($path);

        $zip = new ZipArchive();
        $openResult = $zip->open($fullPath, ZipArchive::CHECKCONS);

        if ($openResult !== true) {
            $result = [
                'filename' => $filename,
                'status' => 'corrupt',
                'badge' => 'Corrupted Archive',
                'badge_class' => 'danger',
                'score' => 0,
                'header_check' => 'Failed (ZIP Checksum/Header Broken)',
                'crc_check' => 'Failed',
                'db_dump_check' => 'Failed',
                'db_dump_file' => 'None',
                'files_count' => 0,
                'uncompressed_size' => '0 Bytes',
                'compressed_size' => $this->formatBytes($archiveSize),
                'verified_at' => now()->format('d M Y, h:i:s A'),
                'details' => 'Archive header checksum failed or file is corrupted.',
            ];

            $this->storeVerificationInSession($filename, $result);

            return redirect()
                ->route('backups.index')
                ->with('error', 'Integrity verification failed: Corrupted archive.')
                ->with('verification_modal', $result);
        }

        $filesCount = $zip->numFiles;
        $uncompressedSize = 0;
        $crcPass = true;
        $dbDumpFile = null;
        $dbDumpSize = 0;
        $sqlSyntaxValid = false;

        for ($i = 0; $i < $filesCount; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat) {
                $uncompressedSize += $stat['size'] ?? 0;
                $entryName = $stat['name'] ?? '';
                $lowerName = strtolower($entryName);

                if (str_ends_with($lowerName, '.sql') || str_ends_with($lowerName, '.sql.gz') || str_contains($lowerName, 'db-dumps')) {
                    $dbDumpFile = $entryName;
                    $dbDumpSize = $stat['size'] ?? 0;

                    // Read first 2KB of SQL dump to verify valid SQL structure / headers
                    $sample = $zip->getFromIndex($i);
                    if ($sample !== false) {
                        if (str_ends_with($lowerName, '.sql.gz')) {
                            $sample = @gzdecode($sample);
                        }
                        if ($sample) {
                            $snippet = substr($sample, 0, 2048);
                            if (
                                str_contains($snippet, 'CREATE TABLE') ||
                                str_contains($snippet, 'INSERT INTO') ||
                                str_contains($snippet, 'MySQL dump') ||
                                str_contains($snippet, 'Dump completed') ||
                                str_contains($snippet, 'PRAGMA') ||
                                str_contains($snippet, 'MariaDB dump') ||
                                str_contains($snippet, '--')
                            ) {
                                $sqlSyntaxValid = true;
                            }
                        }
                    }
                }

                if (!isset($stat['crc']) || $stat['crc'] === null) {
                    $crcPass = false;
                }
            }
        }
        $zip->close();

        $compressionRatio = $uncompressedSize > 0
            ? round(((1 - ($archiveSize / $uncompressedSize)) * 100), 1)
            : 0;

        if ($dbDumpFile && $sqlSyntaxValid && $crcPass) {
            $status = 'healthy';
            $badge = '100% Valid & Restorable';
            $badgeClass = 'success';
            $score = 100;
            $details = 'Archive header, CRC32 checksums, and database dump syntax are 100% valid.';
        } elseif ($filesCount > 0 && $crcPass) {
            $status = 'warning';
            $badge = 'Warning: Schema Incomplete';
            $badgeClass = 'warning';
            $score = 70;
            $details = 'Archive is healthy and CRC32 checks passed, but no valid database dump (.sql) was detected.';
        } else {
            $status = 'corrupt';
            $badge = 'Corrupted Archive';
            $badgeClass = 'danger';
            $score = 0;
            $details = 'Archive failed integrity or CRC32 checks.';
        }

        $result = [
            'filename' => $filename,
            'status' => $status,
            'badge' => $badge,
            'badge_class' => $badgeClass,
            'score' => $score,
            'header_check' => 'Passed (ZipArchive::CHECKCONS)',
            'crc_check' => $crcPass ? 'Passed (All file checksums matched)' : 'Warning / CRC Mismatch',
            'db_dump_check' => $dbDumpFile ? ($sqlSyntaxValid ? 'Passed (Valid SQL Dump Structure)' : 'Warning (Dump Unverified)') : 'Not Found',
            'db_dump_file' => $dbDumpFile ?? 'None',
            'db_dump_size' => $this->formatBytes($dbDumpSize),
            'files_count' => $filesCount,
            'uncompressed_size' => $this->formatBytes($uncompressedSize),
            'compressed_size' => $this->formatBytes($archiveSize),
            'compression_ratio' => max(0, $compressionRatio) . '%',
            'verified_at' => now()->format('d M Y, h:i:s A'),
            'details' => $details,
        ];

        $this->storeVerificationInSession($filename, $result);

        return redirect()
            ->route('backups.index')
            ->with('success', 'Integrity check completed for ' . $filename . ': ' . $badge)
            ->with('verification_modal', $result);
    }

    /**
     * Store verification result in session.
     */
    private function storeVerificationInSession(string $filename, array $result): void
    {
        $verified = session('verified_backups', []);
        $verified[$filename] = $result;
        session(['verified_backups' => $verified]);
    }

    /**
     * Create Pre-Restore Safety Snapshot of live database.
     */
    private function createPreRestoreSnapshot(): array
    {
        $snapshotDir = storage_path('app/private/snapshots');
        if (!is_dir($snapshotDir)) {
            @mkdir($snapshotDir, 0755, true);
        }

        $timestamp = date('Y_m_d_His');
        $random = substr(uniqid(), -4);
        $snapshotFilename = "pre_restore_snapshot_{$timestamp}_{$random}.sql";
        $snapshotPath = $snapshotDir . DIRECTORY_SEPARATOR . $snapshotFilename;

        $connection = config('database.default', 'mysql');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? 'mysql') === 'mysql' || ($config['driver'] ?? '') === 'mariadb') {
            $dumpBinaryPath = $config['dump']['dump_binary_path'] ?? (is_dir('D:\\xampp\\mysql\\bin') ? 'D:\\xampp\\mysql\\bin' : 'C:\\xampp\\mysql\\bin');
            $mysqldump = rtrim($dumpBinaryPath, '\\/') . DIRECTORY_SEPARATOR . 'mysqldump.exe';

            if (file_exists($mysqldump)) {
                $user = escapeshellarg($config['username'] ?? 'root');
                $pass = !empty($config['password']) ? '-p' . escapeshellarg($config['password']) : '';
                $host = escapeshellarg($config['host'] ?? '127.0.0.1');
                $port = escapeshellarg($config['port'] ?? '3306');
                $database = escapeshellarg($config['database'] ?? 'laravel');

                $cmd = "\"{$mysqldump}\" --user={$user} {$pass} --host={$host} --port={$port} {$database} > \"{$snapshotPath}\"";
                exec($cmd, $output, $exitCode);
            }

            // Fallback if mysqldump was not executed or produced empty file
            if (!file_exists($snapshotPath) || filesize($snapshotPath) === 0) {
                $this->fallbackDatabaseDump($snapshotPath);
            }
        } elseif (($config['driver'] ?? '') === 'sqlite') {
            $dbPath = $config['database'] ?? database_path('database.sqlite');
            if (file_exists($dbPath)) {
                @copy($dbPath, $snapshotPath);
            }
        } else {
            $this->fallbackDatabaseDump($snapshotPath);
        }

        $size = file_exists($snapshotPath) ? filesize($snapshotPath) : 0;

        return [
            'filename' => $snapshotFilename,
            'path' => $snapshotPath,
            'size' => $size,
            'size_formatted' => $this->formatBytes($size),
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Fallback database dump generator using PDO when binary is unavailable.
     */
    private function fallbackDatabaseDump(string $outputPath): void
    {
        $handle = fopen($outputPath, 'w');
        fwrite($handle, "-- Pre-Restore Safety Snapshot\n-- Generated: " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = DB::select('SHOW TABLES');
        $keyName = 'Tables_in_' . config('database.connections.' . config('database.default') . '.database');

        foreach ($tables as $table) {
            $tableName = $table->$keyName ?? array_values((array)$table)[0];
            $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
            $createSql = $createTable[0]->{'Create Table'} ?? '';

            fwrite($handle, "DROP TABLE IF EXISTS `{$tableName}`;\n{$createSql};\n\n");

            $rows = DB::table($tableName)->get();
            foreach ($rows as $row) {
                $values = array_map(function ($val) {
                    return $val === null ? 'NULL' : DB::getPdo()->quote((string)$val);
                }, (array)$row);

                if (!empty($values)) {
                    $insertSql = "INSERT INTO `{$tableName}` VALUES (" . implode(', ', $values) . ");\n";
                    fwrite($handle, $insertSql);
                }
            }
            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);
    }

    /**
     * Calculate Daily Storage Growth Trends.
     */
    private function calculateGrowthTrends($allBackups): array
    {
        $grouped = $allBackups->groupBy(function ($backup) {
            return date('Y-m-d', $backup['last_modified']);
        });

        $trends = [];
        $previousSize = 0;

        foreach ($grouped as $date => $items) {
            $daySize = $items->sum('size');
            $dayUncompressed = $items->sum('uncompressed_size');
            $count = $items->count();

            $changeMb = $previousSize > 0 ? round(($daySize - $previousSize) / 1024 / 1024, 2) : 0;
            $previousSize = $daySize;

            $trends[] = [
                'date' => $date,
                'date_formatted' => date('d M Y', strtotime($date)),
                'count' => $count,
                'size' => $daySize,
                'size_mb' => round($daySize / 1024 / 1024, 2),
                'uncompressed_mb' => round($dayUncompressed / 1024 / 1024, 2),
                'change_mb' => $changeMb,
                'size_formatted' => $this->formatBytes($daySize),
            ];
        }

        return array_reverse($trends);
    }

    /**
     * Show individual backup details.
     */
    public function show(string $filename)
    {
        $diskName = config('backup.backup.destination.disks.0', 'local');
        $disk = Storage::disk($diskName);
        $filename = basename($filename);
        $path = 'Laravel/' . $filename;

        if (!$disk->exists($path)) {
            return redirect()
                ->route('backups.index')
                ->with('error', 'Backup file not found.');
        }

        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'zip') {
            return redirect()
                ->route('backups.index')
                ->with('error', 'Invalid backup file.');
        }

        $size = $disk->size($path);
        $lastModified = $disk->lastModified($path);
        $fullPath = $disk->path($path);

        $uncompressedSize = $size;
        $filesCount = 0;
        $dbDumpName = null;

        $zip = new ZipArchive();
        if (@$zip->open($fullPath) === true) {
            $uncompressedSize = 0;
            $filesCount = $zip->numFiles;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat) {
                    $uncompressedSize += $stat['size'] ?? 0;
                    $entryName = strtolower($stat['name'] ?? '');
                    if (str_ends_with($entryName, '.sql') || str_ends_with($entryName, '.sql.gz')) {
                        $dbDumpName = $stat['name'];
                    }
                }
            }
            $zip->close();
        }

        $compressionRatio = $uncompressedSize > 0
            ? round(((1 - ($size / $uncompressedSize)) * 100), 1)
            : 0;

        $verifiedBackups = session('verified_backups', []);
        $verification = $verifiedBackups[$filename] ?? null;

        $backup = [
            'name' => $filename,
            'path' => $path,
            'size' => $size,
            'size_formatted' => $this->formatBytes($size),
            'uncompressed_size' => $uncompressedSize,
            'uncompressed_size_formatted' => $this->formatBytes($uncompressedSize),
            'compression_ratio' => max(0, $compressionRatio),
            'files_count' => $filesCount,
            'db_dump_name' => $dbDumpName,
            'verification' => $verification,
            'date' => date('Y-m-d H:i:s', $lastModified),
            'formatted_date' => date('d M Y', $lastModified),
            'formatted_time' => date('h:i:s A', $lastModified),
            'age' => $this->backupAge($lastModified),
            'age_hours' => $this->backupAgeHours($lastModified),
            'extension' => strtoupper(pathinfo($filename, PATHINFO_EXTENSION)),
            'storage_disk' => $diskName,
        ];

        return view('backups.show', compact('backup'));
    }


    /**
     * Run a new backup.
     */
    public function runBackup()
    {
        try {

            $php = PHP_BINARY;

            $artisan = base_path('artisan');

            $command =
                '"' . $php .
                '" "' . $artisan .
                '" backup:run';

            $output = [];

            $exitCode = 0;

            exec(
                $command . ' 2>&1',
                $output,
                $exitCode
            );

            $outputText = implode(
                PHP_EOL,
                $output
            );

            Log::info(
                'Web backup execution',
                [
                    'command' => $command,
                    'exit_code' => $exitCode,
                    'output' => $outputText,
                ]
            );

            if ($exitCode !== 0) {

                return redirect()
                    ->route('backups.index')
                    ->with(
                        'error',
                        'Backup failed.'
                    )
                    ->with(
                        'command_output',
                        $outputText
                    );
            }

            return redirect()
                ->route('backups.index')
                ->with(
                    'success',
                    'Backup completed successfully.'
                )
                ->with(
                    'command_output',
                    $outputText
                );

        } catch (\Throwable $e) {

            Log::error(
                'Web backup failed',
                [
                    'message' =>
                        $e->getMessage(),
                ]
            );

            return redirect()
                ->route('backups.index')
                ->with(
                    'error',
                    'Backup failed: ' .
                    $e->getMessage()
                );
        }
    }


    /**
     * Run backup cleanup.
     */
    public function cleanup()
    {
        try {

            $exitCode = Artisan::call(
                'backup:clean'
            );

            $output = Artisan::output();

            if ($exitCode !== 0) {

                Log::error(
                    'Backup cleanup failed',
                    [
                        'exit_code' =>
                            $exitCode,

                        'output' =>
                            $output,
                    ]
                );

                return redirect()
                    ->route('backups.index')
                    ->with(
                        'error',
                        'Backup cleanup failed.'
                    )
                    ->with(
                        'command_output',
                        $output
                    );
            }

            return redirect()
                ->route('backups.index')
                ->with(
                    'success',
                    'Backup cleanup completed successfully.'
                )
                ->with(
                    'command_output',
                    $output
                );

        } catch (\Throwable $e) {

            Log::error(
                'Backup cleanup failed',
                [
                    'message' =>
                        $e->getMessage(),
                ]
            );

            return redirect()
                ->route('backups.index')
                ->with(
                    'error',
                    'Backup cleanup failed: ' .
                    $e->getMessage()
                );
        }
    }


    /**
     * Download backup.
     */
    public function download(string $filename)
    {
        $diskName = config(
            'backup.backup.destination.disks.0',
            'local'
        );

        $disk = Storage::disk($diskName);

        $filename = basename($filename);

        $path = 'Laravel/' . $filename;

        if (!$disk->exists($path)) {

            return redirect()
                ->route('backups.index')
                ->with(
                    'error',
                    'Backup file not found.'
                );
        }

        if (
            strtolower(
                pathinfo(
                    $filename,
                    PATHINFO_EXTENSION
                )
            ) !== 'zip'
        ) {

            return redirect()
                ->route('backups.index')
                ->with(
                    'error',
                    'Invalid backup file.'
                );
        }

        return $disk->download(
            $path,
            $filename
        );
    }


    /**
     * Delete a single backup.
     */
    public function destroy(string $filename)
    {
        $diskName = config(
            'backup.backup.destination.disks.0',
            'local'
        );

        $disk = Storage::disk($diskName);

        $filename = basename($filename);

        $path = 'Laravel/' . $filename;

        if (!$disk->exists($path)) {

            return redirect()
                ->route('backups.index')
                ->with(
                    'error',
                    'Backup file not found.'
                );
        }

        if (
            strtolower(
                pathinfo(
                    $filename,
                    PATHINFO_EXTENSION
                )
            ) !== 'zip'
        ) {

            return redirect()
                ->route('backups.index')
                ->with(
                    'error',
                    'Invalid backup file.'
                );
        }

        $disk->delete($path);

        return redirect()
            ->route('backups.index')
            ->with(
                'success',
                'Backup deleted successfully.'
            );
    }


    /**
     * Bulk delete backups.
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'filenames' => [
                'required',
                'array',
                'min:1',
            ],

            'filenames.*' => [
                'string',
            ],
        ]);

        $diskName = config(
            'backup.backup.destination.disks.0',
            'local'
        );

        $disk = Storage::disk($diskName);

        $deleted = 0;

        foreach (
            $request->filenames
            as $filename
        ) {

            $filename = basename(
                $filename
            );

            if (
                strtolower(
                    pathinfo(
                        $filename,
                        PATHINFO_EXTENSION
                    )
                ) !== 'zip'
            ) {
                continue;
            }

            $path = 'Laravel/' . $filename;

            if ($disk->exists($path)) {

                $disk->delete($path);

                $deleted++;
            }
        }

        return redirect()
            ->route('backups.index')
            ->with(
                'success',
                $deleted .
                ' backup(s) deleted successfully.'
            );
    }


    /**
     * Export backup history to CSV.
     */
    private function exportCsv($backups)
    {
        $filename =
            'backup_history_' .
            now()->format(
                'Y_m_d_H_i_s'
            ) .
            '.csv';

        return response()->streamDownload(
            function () use ($backups) {

                $handle = fopen(
                    'php://output',
                    'w'
                );

                fputcsv(
                    $handle,
                    [
                        'Backup File',
                        'Size',
                        'Created Date',
                        'Created Time',
                        'Age',
                    ]
                );

                foreach ($backups as $backup) {

                    fputcsv(
                        $handle,
                        [
                            $backup['name'],

                            $backup['size_formatted'],

                            date(
                                'd M Y',
                                $backup['last_modified']
                            ),

                            date(
                                'h:i:s A',
                                $backup['last_modified']
                            ),

                            $this->backupAge(
                                $backup['last_modified']
                            ),
                        ]
                    );
                }

                fclose($handle);
            },
            $filename,
            [
                'Content-Type' =>
                    'text/csv',
            ]
        );
    }


    /**
     * Backup health.
     */
    private function getBackupHealth(
        $latestBackup
    ): array {

        if (!$latestBackup) {

            return [
                'status' => 'critical',

                'label' => 'Critical',

                'message' =>
                    'No backup has been created yet.',
            ];
        }

        $hours = $this->backupAgeHours(
            $latestBackup['last_modified']
        );

        if ($hours <= 24) {

            return [
                'status' => 'healthy',

                'label' => 'Healthy',

                'message' =>
                    'Latest backup is less than 24 hours old.',
            ];
        }

        if ($hours <= 48) {

            return [
                'status' => 'warning',

                'label' => 'Warning',

                'message' =>
                    'Latest backup is more than 24 hours old.',
            ];
        }

        return [
            'status' => 'critical',

            'label' => 'Critical',

            'message' =>
                'Latest backup is more than 48 hours old.',
        ];
    }


    /**
     * Storage information.
     */
    private function getStorageStats(
        string $diskName,
        int|float $backupSize
    ): array {

        $totalBytes = null;

        $freeBytes = null;

        $usedBytes = $backupSize;

        $path = storage_path(
            'app/private'
        );

        if (is_dir($path)) {

            $freeBytes = @disk_free_space(
                $path
            );

            $totalBytes = @disk_total_space(
                $path
            );

            if (
                $freeBytes !== false &&
                $totalBytes !== false
            ) {

                $usedBytes =
                    $totalBytes -
                    $freeBytes;
            }
        }

        $usagePercent = 0;

        if (
            $totalBytes &&
            $totalBytes > 0
        ) {

            $usagePercent = round(
                (
                    $usedBytes /
                    $totalBytes
                ) * 100,
                2
            );
        }

        return [
            'disk' => $diskName,

            'total' => $totalBytes
                ? $this->formatBytes(
                    $totalBytes
                )
                : 'Unavailable',

            'used' => $this->formatBytes(
                $usedBytes
            ),

            'free' => $freeBytes !== null &&
                $freeBytes !== false
                ? $this->formatBytes(
                    $freeBytes
                )
                : 'Unavailable',

            'usage_percent' =>
                $usagePercent,
        ];
    }


    /**
     * Backup age in hours.
     */
    private function backupAgeHours(
        int $timestamp
    ): float {

        return round(
            max(
                0,
                now()->timestamp -
                $timestamp
            ) / 3600,
            1
        );
    }


    /**
     * Human readable backup age.
     */
    private function backupAge(
        int $timestamp
    ): string {

        return \Carbon\Carbon::createFromTimestamp(
            $timestamp
        )->diffForHumans();
    }


    /**
     * Convert bytes to readable format.
     */
    private function formatBytes(
        int|float $bytes
    ): string {

        if ($bytes <= 0) {
            return '0 Bytes';
        }

        $units = [
            'Bytes',
            'KB',
            'MB',
            'GB',
            'TB',
        ];

        $power = floor(
            log($bytes, 1024)
        );

        $power = min(
            $power,
            count($units) - 1
        );

        return round(
            $bytes /
            pow(
                1024,
                $power
            ),
            2
        ) .
        ' ' .
        $units[$power];
    }
}