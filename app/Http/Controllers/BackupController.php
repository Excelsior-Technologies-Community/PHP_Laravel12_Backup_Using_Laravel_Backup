<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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

        $backups = collect($files)
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
         * Search by backup filename.
         */
        if ($request->filled('search')) {

            $search = strtolower($request->search);

            $backups = $backups
                ->filter(function ($backup) use ($search) {

                    return str_contains(
                        strtolower($backup['name']),
                        $search
                    );
                })
                ->values();
        }

        /*
         * Date filter.
         */
        if ($request->filled('date')) {

            $selectedDate = $request->date;

            $backups = $backups
                ->filter(function ($backup) use ($selectedDate) {

                    return date(
                        'Y-m-d',
                        $backup['last_modified']
                    ) === $selectedDate;
                })
                ->values();
        }

        /*
         * Sorting.
         */
        $sort = $request->get('sort', 'newest');

        if ($sort === 'oldest') {

            $backups = $backups
                ->sortBy('last_modified')
                ->values();
        }

        if ($sort === 'largest') {

            $backups = $backups
                ->sortByDesc('size')
                ->values();
        }

        if ($sort === 'smallest') {

            $backups = $backups
                ->sortBy('size')
                ->values();
        }

        /*
         * Dashboard statistics.
         */
        $allBackups = collect($files)
            ->filter(function ($file) {

                return strtolower(
                    pathinfo($file, PATHINFO_EXTENSION)
                ) === 'zip';
            });

        $totalBackups = $allBackups->count();

        $totalSize = $allBackups->sum(
            function ($file) use ($disk) {
                return $disk->size($file);
            }
        );

        $latestBackup = $allBackups
            ->sortByDesc(
                function ($file) use ($disk) {
                    return $disk->lastModified($file);
                }
            )
            ->first();

        $latestBackupData = null;

        if ($latestBackup) {

            $latestBackupData = [
                'name' => basename($latestBackup),

                'size' => $this->formatBytes(
                    $disk->size($latestBackup)
                ),

                'date' => date(
                    'Y-m-d H:i:s',
                    $disk->lastModified($latestBackup)
                ),
            ];
        }

        return view(
            'backups.index',
            compact(
                'backups',
                'totalBackups',
                'totalSize',
                'latestBackupData'
            )
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

            $command = '"' . $php . '" "' . $artisan . '" backup:run';

            $output = [];
            $exitCode = 0;

            exec($command . ' 2>&1', $output, $exitCode);

            $outputText = implode(PHP_EOL, $output);

            Log::info('Web backup execution', [
                'command' => $command,
                'exit_code' => $exitCode,
                'output' => $outputText,
            ]);

            if ($exitCode !== 0) {
                return redirect()
                    ->route('backups.index')
                    ->with('error', 'Backup failed.')
                    ->with('command_output', $outputText);
            }

            return redirect()
                ->route('backups.index')
                ->with('success', 'Backup completed successfully.')
                ->with('command_output', $outputText);
        } catch (\Throwable $e) {

            Log::error('Web backup failed', [
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('backups.index')
                ->with('error', 'Backup failed: ' . $e->getMessage());
        }
    }
    /**
     * Run backup cleanup.
     */
    public function cleanup()
    {
        try {

            $exitCode = Artisan::call('backup:clean');

            $output = Artisan::output();

            if ($exitCode !== 0) {

                Log::error('Backup cleanup failed', [
                    'exit_code' => $exitCode,
                    'output' => $output,
                ]);

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

            Log::error('Backup cleanup failed', [
                'message' => $e->getMessage(),
            ]);

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
     * Download a backup ZIP file.
     */
    public function download(string $filename)
    {
        $diskName = config(
            'backup.backup.destination.disks.0',
            'local'
        );

        $disk = Storage::disk($diskName);

        $path = 'Laravel/' . basename($filename);

        if (!$disk->exists($path)) {

            return redirect()
                ->route('backups.index')
                ->with(
                    'error',
                    'Backup file not found.'
                );
        }

        return $disk->download(
            $path,
            basename($filename)
        );
    }

    /**
     * Delete a backup ZIP file.
     */
    public function destroy(string $filename)
    {
        $diskName = config(
            'backup.backup.destination.disks.0',
            'local'
        );

        $disk = Storage::disk($diskName);

        $path = 'Laravel/' . basename($filename);

        if (!$disk->exists($path)) {

            return redirect()
                ->route('backups.index')
                ->with(
                    'error',
                    'Backup file not found.'
                );
        }

        /*
         * Only ZIP files can be deleted.
         */
        if (
            strtolower(
                pathinfo($filename, PATHINFO_EXTENSION)
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
     * Convert bytes into readable format.
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
            $bytes / pow(1024, $power),
            2
        ) . ' ' . $units[$power];
    }
}
