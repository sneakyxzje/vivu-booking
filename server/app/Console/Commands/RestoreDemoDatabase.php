<?php

namespace App\Console\Commands;

use App\Support\SqlDumpStatements;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RestoreDemoDatabase extends Command
{
    protected $signature = 'demo:restore
        {--database=vivu_demo_defense : New local MySQL database; must start with vivu_demo_}
        {--activate : Update DB_DATABASE in .env after successful restore}';

    protected $description = 'Restore the sanitized defense snapshot into a NEW local database';

    public function handle(): int
    {
        $database = (string) $this->option('database');
        if (!app()->environment('local', 'testing')) {
            $this->error('Demo restore is only available in APP_ENV=local or testing.');
            return self::FAILURE;
        }
        if (!preg_match('/\Avivu_demo_[a-z0-9_]{1,40}\z/D', $database)) {
            $this->error('Use a database name such as vivu_demo_defense (lowercase letters, numbers, underscores).');
            return self::FAILURE;
        }

        $originalConnection = DB::getDefaultConnection();
        $config = DB::connection($originalConnection)->getConfig();
        if (!in_array($config['driver'], ['mysql', 'mariadb'], true)
            || !in_array($config['host'], ['127.0.0.1', 'localhost', '::1'], true)
            || !empty($config['read']) || !empty($config['write']) || !empty($config['prefix'])) {
            $this->error('Configure a local MySQL/MariaDB connection without table prefixes or read/write hosts first.');
            return self::FAILURE;
        }

        $created = false;
        try {
            $envPath = app()->environmentFilePath();
            $env = null;
            if ($this->option('activate')) {
                if (!is_file($envPath) || !is_writable($envPath) || !empty($config['url'])) {
                    throw new RuntimeException('--activate requires a writable .env and separate DB_* settings (no DB_URL).');
                }
                $env = file_get_contents($envPath);
            }

            $sql = file_get_contents(database_path('demo/defense.sql'));
            $manifest = json_decode(file_get_contents(database_path('demo/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
            if (!hash_equals($manifest['sha256'], hash('sha256', $sql))) {
                throw new RuntimeException('Demo snapshot checksum mismatch. Restore the version tracked in Git.');
            }

            // Resolve credentials through Laravel, then explicitly remove any database URL override.
            // CREATE DATABASE deliberately has no IF NOT EXISTS: never write into an existing database.
            $config['url'] = null;
            // Migrator uses getName() while invoking Schema; never retain the source name.
            $config['name'] = 'demo_restore';
            $config['database'] = null;
            config(['database.connections.demo_restore' => $config]);
            DB::purge('demo_restore');
            $connection = DB::connection('demo_restore');
            $exists = $connection->selectOne('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$database]);
            if ($exists) {
                throw new RuntimeException("Database {$database} already exists. Nothing changed; use another --database name.");
            }
            $connection->statement("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $created = true;
            $config['database'] = $database;
            config(['database.connections.demo_restore' => $config]);
            DB::purge('demo_restore');
            DB::setDefaultConnection('demo_restore');
            Schema::clearResolvedInstance('db.schema');
            foreach (SqlDumpStatements::read($sql) as $statement) {
                DB::connection()->unprepared($statement);
            }

            if ($this->call('migrate', ['--database' => 'demo_restore', '--force' => true]) !== self::SUCCESS) {
                throw new RuntimeException('Migration failed. The demo database was not activated.');
            }

            DB::transaction(function () {
                DB::table('users')->update([
                    'password' => Hash::make('Demo-2026!'),
                    'remember_token' => null,
                    'email_verified_at' => now(),
                ]);
                // A public Git fixture must not expose working access links across installations.
                foreach (['bookings', 'group_booking_requests'] as $table) {
                    foreach (DB::table($table)->pluck('id') as $id) {
                        DB::table($table)->where('id', $id)->update(['public_token' => (string) Str::uuid()]);
                    }
                }
            });

            foreach ($manifest['counts'] as $table => $expected) {
                if (DB::table($table)->count() !== $expected) {
                    throw new RuntimeException("Unexpected row count in {$table}.");
                }
            }
            foreach (['jobs', 'failed_jobs', 'sessions', 'cache', 'cache_locks', 'personal_access_tokens', 'password_reset_tokens', 'booking_checkout_verifications', 'booking_passenger_supplements'] as $table) {
                if (DB::table($table)->exists()) {
                    throw new RuntimeException("Unexpected runtime data in {$table}.");
                }
            }
            if ($this->call('bookings:check-seat-consistency') !== self::SUCCESS) {
                throw new RuntimeException('Seat counts do not match. The demo database was not activated.');
            }
            $missingDays = DB::select('SELECT t.id FROM tours t LEFT JOIN tour_itineraries i ON i.tour_id = t.id GROUP BY t.id, t.number_of_days HAVING COUNT(DISTINCT i.day_number) <> t.number_of_days OR MIN(i.day_number) <> 1 OR MAX(i.day_number) <> t.number_of_days');
            $invalidDates = DB::table('tour_schedules')->whereRaw('arrival_at < start_date OR return_departure_at < arrival_at OR end_date < return_departure_at OR end_date < start_date')->exists();
            if ($missingDays || $invalidDates) {
                throw new RuntimeException('Incomplete itinerary or invalid schedule dates in the demo snapshot.');
            }

            if ($env !== null) {
                if (file_get_contents($envPath) !== $env) {
                    throw new RuntimeException('.env changed during restore. Activate the database manually.');
                }
                $updated = preg_match('/^DB_DATABASE=.*$/m', $env)
                    ? preg_replace('/^DB_DATABASE=.*$/m', "DB_DATABASE={$database}", $env)
                    : $env . "\nDB_DATABASE={$database}\n";
                // This backup stays under ignored storage, never in the portable Git fixture.
                $backup = storage_path('app/demo-env-' . Str::uuid() . '.backup');
                if (file_put_contents($backup, $env) === false || file_put_contents($envPath, $updated, LOCK_EX) === false) {
                    throw new RuntimeException('Could not update .env. Activate the database manually.');
                }
                $this->call('config:clear');
                $this->info("Activated {$database}. Restart the server and queue/scheduler workers.");
            } else {
                $this->info("Restored {$database}. Set DB_DATABASE={$database} in .env, then run php artisan config:clear.");
            }
            $this->table(['Role', 'Email', 'Password'], [
                ['Admin', 'admin@example.test', 'Demo-2026!'],
                ['Guide', 'guide@example.test', 'Demo-2026!'],
                ['Customer', 'customer@example.test', 'Demo-2026!'],
            ]);
            $this->line('Demo email addresses do not receive mail. Use your own email for the real OTP demonstration.');
            return self::SUCCESS;
        } catch (Throwable $exception) {
            // Connection exceptions may contain a SQL statement or credentials; print only controlled errors.
            $this->error($exception instanceof RuntimeException && !$exception instanceof \PDOException
                ? $exception->getMessage()
                : 'Restore failed. Check the local MySQL service, connection settings and CREATE DATABASE permission.');
            if ($exception instanceof \PDOException && isset($exception->errorInfo[0], $exception->errorInfo[1])) {
                $this->line('MySQL error: ' . $exception->errorInfo[0] . ' / ' . $exception->errorInfo[1]);
            }
            if ($created) {
                $this->warn("The incomplete database {$database} was kept for inspection. Retry with a new name; no database was deleted.");
            }
            return self::FAILURE;
        } finally {
            DB::setDefaultConnection($originalConnection);
            Schema::clearResolvedInstance('db.schema');
            DB::purge('demo_restore');
        }
    }
}
