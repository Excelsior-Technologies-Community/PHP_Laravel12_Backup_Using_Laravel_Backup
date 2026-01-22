# PHP_Laravel12_Backup_Using_Laravel_Backup

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-red" />
  <img src="https://img.shields.io/badge/PHP-8.2+-blue" />
  <img src="https://img.shields.io/badge/Backup-Spatie-success" />
</p>

---

##  Overview

This project demonstrates a **complete, production-ready backup system** for a **Laravel 12** application using the **Spatie Laravel Backup** package.

It provides **database + file backups**, packaged as ZIP files, and works perfectly on **Windows (XAMPP)** as well as Linux/macOS servers.

---

##  Features

*  Full project file backup
*  MySQL / MariaDB database backup
*  ZIP archive generation
*  Automatic exclusion of vendor & node_modules
*  Windows (XAMPP) compatible
*  Laravel 12 compatible configuration
*  Ready for cloud & scheduled backups

---

##  Folder Structure (Important)

```text
PHP_Laravel12_Backup_Using_Laravel_Backup
│── app
│── bootstrap
│── config
│   └── backup.php
│── database
│── public
│── resources
│── routes
│── storage
│   └── app
│       └── private
│           └── Laravel
│               └── 2026-01-22-xx-xx-xx.zip
│── .env
│── artisan
│── composer.json
```

---

## 1. Requirements

* PHP **8.2 or higher**
* Composer installed
* Laravel **12** project
* MySQL / MariaDB (**XAMPP used in this setup**)
* Windows / Linux / macOS

---

## 2. Create Laravel 12 Project

```bash
composer create-project laravel/laravel PHP_Laravel12_Backup_Using_Laravel_Backup
```

---

## 3. Database Configuration

Edit the `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=
```

Create the database in phpMyAdmin or MySQL:

```sql
CREATE DATABASE laravel;
```

Run migrations:

```bash
php artisan migrate
```

---

## 4. Install Spatie Laravel Backup Package

```bash
composer require spatie/laravel-backup
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Spatie\\Backup\\BackupServiceProvider"
```

This will create:

```text
config/backup.php
```

---

## 5. Backup Configuration (Laravel 12 Compatible)

Use the following full and working configuration in `config/backup.php`:

```php
<?php

return [

    'backup' => [

        'name' => env('APP_NAME', 'laravel-backup'),

        'source' => [

            'files' => [

                'include' => [
                    base_path(),
                ],

                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    base_path('.git'),
                    storage_path('app/backup-temp'),
                ],

                'follow_links' => false,
                'ignore_unreadable_directories' => true,
                'relative_path' => base_path(),
            ],

            'databases' => [
                env('DB_CONNECTION', 'mysql'),
            ],
        ],

        'database_dump_compressor' => null,
        'database_dump_file_timestamp_format' => null,
        'database_dump_filename_base' => 'database',
        'database_dump_file_extension' => '',

        'destination' => [
            'compression_method' => ZipArchive::CM_DEFAULT,
            'compression_level' => 9,
            'filename_prefix' => '',
            'disks' => ['local'],
        ],

        'temporary_directory' => storage_path('app/backup-temp'),
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),
        'encryption' => 'default',
        'tries' => 1,
        'retry_delay' => 0,
    ],

    'notifications' => [
        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => ['mail'],
        ],
    ],

    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'laravel-backup'),
            'disks' => ['local'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],
    ],

];
```

---

## 6. Clear Configuration Cache

```bash
php artisan config:clear
php artisan cache:clear
```

---

##  7. Fix mysqldump Error (Windows + XAMPP)

### Problem

While running the backup command, you may see this error:

```
"mysqldump" is not recognized as an internal or external command,
operable program or batch file.
```

### Reason

Laravel Backup uses the system `mysqldump` command to export the database.
On Windows + XAMPP, the MySQL binary path is not added to the system PATH by default, so Windows cannot find `mysqldump.exe`.

---

### Solution: Add MySQL to System PATH

### Step 7.1: Locate mysqldump.exe

By default, XAMPP installs MySQL here:

```
C:\xampp\mysql\bin
```

Confirm that this file exists:

```
C:\xampp\mysql\bin\mysqldump.exe
```

If this file exists, proceed to the next step.

---

### Step 7.2: Open Environment Variables

**Method (Recommended):**

1. Press **Windows Key**
2. Search for:

   ```
   Environment Variables
   ```

---

### Step 7.3: Open Environment Variables Window

1. In the **System Properties** window, click:
   **Environment Variables…**

   <img width="411" height="472" alt="Screenshot 2026-01-22 120454" src="https://github.com/user-attachments/assets/86431d50-686e-47dd-a566-91ff8511dfaa" />


3. You will see two sections:
   a. User variables
   b. System variables

 **Important:**
We must edit **System variables**, not **User variables**.

---

### Step 7.4: Edit System PATH Variable

1. In **System variables**, find:

   ```
   Path
   ```

2. Select **Path**

3. Click **Edit**

   <img width="616" height="589" alt="Screenshot 2026-01-22 120552" src="https://github.com/user-attachments/assets/be1154fa-3eef-4769-8346-3f582db66b1a" />


5. Click **New**

6. Paste this path:

   ```
   C:\xampp\mysql\bin
   ```

   <img width="524" height="498" alt="Screenshot 2026-01-22 120701" src="https://github.com/user-attachments/assets/f54a51de-069a-4c13-9298-33095732c69b" />


7. Click **OK**

8. Click **OK**

9. Click **OK**

(You will close 3 windows in total)

---

### Step 7.5: Restart Terminal (IMPORTANT)

* Close **PowerShell / CMD / VS Code terminal**
* Open a **new PowerShell or CMD**

This step is mandatory, otherwise the PATH change will not work.

---

### Step 7.6: Test mysqldump

Run the following command:

```bash
mysqldump --version
```

**Expected Output**

```
mysqldump.exe  Ver 10.x.x Distrib MariaDB/MySQL
```

If the version is displayed, `mysqldump` is working correctly.


## 8. Run Backup Command

```bash
php artisan backup:run
```

Expected output:

```text
Starting backup...
Dumping database...
Zipping files...
Backup completed!
```
<img width="641" height="188" alt="Screenshot 2026-01-22 114514" src="https://github.com/user-attachments/assets/d52d9aa1-36c6-4d16-9d35-e77cb4f49e5b" />

---

## 9. Backup Storage Location

```text
storage/app/private/Laravel/
```
<img width="309" height="134" alt="Screenshot 2026-01-22 122647" src="https://github.com/user-attachments/assets/4cbecda6-6ed4-4979-8147-a12c4b700550" />

---

## 10. Important Notes

* ZIP backup files are **binary**
* Do **not** open them in VS Code
* Use **Extract / WinRAR / 7-Zip**

---

