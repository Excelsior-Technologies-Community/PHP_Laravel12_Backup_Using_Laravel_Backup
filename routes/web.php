<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BackupController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
Route::post('/backups/run', [BackupController::class, 'runBackup'])->name('backups.run');
Route::post('/backups/cleanup', [BackupController::class, 'cleanup'])->name('backups.cleanup');
Route::get('/backups/download/{filename}', [BackupController::class, 'download'])->name('backups.download');
Route::delete('/backups/{filename}', [BackupController::class, 'destroy'])->name('backups.destroy');
