<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    /**
     * Display backup dashboard and backup history.
     */
    public function index(Request $request)
    {
        $diskName = config(
            'backup.backup.destination.disks.0',
            'local'
        );

        $disk = Storage::disk($diskName);

        $backupDirectory = 'Laravel';

        $files = $disk->files($backupDirectory);

        /*
         * Get all ZIP backups.
         */
        $allBackups = collect($files)
            ->filter(function ($file) {
                return strtolower(
                    pathinfo($file, PATHINFO_EXTENSION)
                ) === 'zip';
            })
            ->map(function ($file) use ($disk) {

                $size = $disk->size($file);

                $lastModified = $disk->lastModified($file);

                return [
                    'path' => $file,
                    'name' => basename($file),
                    'size' => $size,
                    'size_formatted' => $this->formatBytes($size),
                    'last_modified' => $lastModified,
                    'date' => date(
                        'Y-m-d H:i:s',
                        $lastModified
                    ),
                ];
            })
            ->sortByDesc('last_modified')
            ->values();

        /*
         * Statistics before filters.
         */
        $totalBackups = $allBackups->count();

        $totalSize = $allBackups->sum('size');

        $averageSize = $totalBackups > 0
            ? $totalSize / $totalBackups
            : 0;

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
                'age' => $this->backupAge(
                    $latestBackup['last_modified']
                ),
                'age_hours' => $this->backupAgeHours(
                    $latestBackup['last_modified']
                ),
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
        $health = $this->getBackupHealth(
            $latestBackup
        );

        /*
         * Storage statistics.
         */
        $storageStats = $this->getStorageStats(
            $diskName,
            $totalSize
        );

        /*
         * Search.
         */
        if ($request->filled('search')) {

            $search = strtolower(
                $request->search
            );

            $allBackups = $allBackups
                ->filter(function ($backup) use ($search) {

                    return str_contains(
                        strtolower($backup['name']),
                        $search
                    );
                })
                ->values();
        }

        /*
         * Date preset.
         *
         * today
         * 7days
         * 30days
         * all
         */
        $datePreset = $request->get(
            'date_preset',
            'all'
        );

        if ($datePreset !== 'all') {

            $now = now();

            $allBackups = $allBackups
                ->filter(function ($backup) use (
                    $datePreset,
                    $now
                ) {

                    $backupDate = \Carbon\Carbon::createFromTimestamp(
                        $backup['last_modified']
                    );

                    if ($datePreset === 'today') {

                        return $backupDate->isToday();
                    }

                    if ($datePreset === '7days') {

                        return $backupDate->greaterThanOrEqualTo(
                            $now->copy()->subDays(7)
                        );
                    }

                    if ($datePreset === '30days') {

                        return $backupDate->greaterThanOrEqualTo(
                            $now->copy()->subDays(30)
                        );
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

                    return date(
                        'Y-m-d',
                        $backup['last_modified']
                    ) === $selectedDate;
                })
                ->values();
        }

        /*
         * Size filter.
         *
         * small  < 10 MB
         * medium 10 MB - 100 MB
         * large  > 100 MB
         */
        $sizeFilter = $request->get(
            'size_filter',
            'all'
        );

        if ($sizeFilter !== 'all') {

            $allBackups = $allBackups
                ->filter(function ($backup) use ($sizeFilter) {

                    $sizeMb = $backup['size'] / 1024 / 1024;

                    if ($sizeFilter === 'small') {
                        return $sizeMb < 10;
                    }

                    if ($sizeFilter === 'medium') {
                        return $sizeMb >= 10 &&
                            $sizeMb <= 100;
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
        $sort = $request->get(
            'sort',
            'newest'
        );

        if ($sort === 'oldest') {

            $allBackups = $allBackups
                ->sortBy('last_modified')
                ->values();
        }

        if ($sort === 'largest') {

            $allBackups = $allBackups
                ->sortByDesc('size')
                ->values();
        }

        if ($sort === 'smallest') {

            $allBackups = $allBackups
                ->sortBy('size')
                ->values();
        }

        /*
         * Simple pagination.
         */
        $perPage = (int) $request->get(
            'per_page',
            5
        );

        if (!in_array($perPage, [5, 10, 20, 50])) {
            $perPage = 5;
        }

        $currentPage = max(
            1,
            (int) $request->get(
                'page',
                1
            )
        );

        $totalFiltered = $allBackups->count();

        $totalPages = max(
            1,
            (int) ceil(
                $totalFiltered / $perPage
            )
        );

        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }

        $backups = $allBackups
            ->slice(
                ($currentPage - 1) * $perPage,
                $perPage
            )
            ->values();

        /*
         * Pass query parameters to pagination.
         */
        $queryParameters = $request
            ->except('page');

        /*
         * CSV export does not use pagination.
         */
        if ($request->get('export') === 'csv') {

            return $this->exportCsv(
                $allBackups
            );
        }

        return view(
            'backups.index',
            compact(
                'backups',
                'totalBackups',
                'totalSize',
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
     * Show individual backup details.
     */
    public function show(string $filename)
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

        $size = $disk->size($path);

        $lastModified = $disk->lastModified($path);

        $backup = [
            'name' => $filename,

            'path' => $path,

            'size' => $size,

            'size_formatted' => $this->formatBytes(
                $size
            ),

            'date' => date(
                'Y-m-d H:i:s',
                $lastModified
            ),

            'formatted_date' => date(
                'd M Y',
                $lastModified
            ),

            'formatted_time' => date(
                'h:i:s A',
                $lastModified
            ),

            'age' => $this->backupAge(
                $lastModified
            ),

            'age_hours' => $this->backupAgeHours(
                $lastModified
            ),

            'extension' => strtoupper(
                pathinfo(
                    $filename,
                    PATHINFO_EXTENSION
                )
            ),

            'storage_disk' => $diskName,
        ];

        return view(
            'backups.show',
            compact('backup')
        );
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