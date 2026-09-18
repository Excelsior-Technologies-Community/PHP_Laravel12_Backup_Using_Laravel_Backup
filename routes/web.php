<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BackupController;

Route::get('/', function () {
    return redirect()->route('backups.index');
});

/*
|--------------------------------------------------------------------------
| Backup Dashboard
|--------------------------------------------------------------------------
*/

Route::get(
    '/backups',
    [BackupController::class, 'index']
)->name('backups.index');

/*
|--------------------------------------------------------------------------
| Run Backup
|--------------------------------------------------------------------------
*/

Route::post(
    '/backups/run',
    [BackupController::class, 'runBackup']
)->name('backups.run');

/*
|--------------------------------------------------------------------------
| Cleanup
|--------------------------------------------------------------------------
*/

Route::post(
    '/backups/cleanup',
    [BackupController::class, 'cleanup']
)->name('backups.cleanup');

/*
|--------------------------------------------------------------------------
| Module 1: 1-Click Database Restore & Rollback Wizard
|--------------------------------------------------------------------------
*/

Route::post(
    '/backups/{filename}/restore',
    [BackupController::class, 'restore']
)->name('backups.restore');

/*
|--------------------------------------------------------------------------
| Module 2: Automated Backup Integrity & Archive Health Verifier
|--------------------------------------------------------------------------
*/

Route::post(
    '/backups/{filename}/verify',
    [BackupController::class, 'verify']
)->name('backups.verify');

/*
|--------------------------------------------------------------------------
| Backup Details
|--------------------------------------------------------------------------
*/

Route::get(
    '/backups/{filename}/details',
    [BackupController::class, 'show']
)->name('backups.show');

/*
|--------------------------------------------------------------------------
| Download
|--------------------------------------------------------------------------
*/

Route::get(
    '/backups/download/{filename}',
    [BackupController::class, 'download']
)->name('backups.download');

/*
|--------------------------------------------------------------------------
| Bulk Delete
|--------------------------------------------------------------------------
*/

Route::post(
    '/backups/bulk-delete',
    [BackupController::class, 'bulkDelete']
)->name('backups.bulk-delete');

/*
|--------------------------------------------------------------------------
| Delete Single Backup
|--------------------------------------------------------------------------
*/

Route::delete(
    '/backups/{filename}',
    [BackupController::class, 'destroy']
)->name('backups.destroy');