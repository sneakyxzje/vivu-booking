<?php

namespace Tests\Feature;

use App\Support\SqlDumpStatements;
use Tests\TestCase;

class DemoRestoreTest extends TestCase
{
    public function test_refuses_production_before_accessing_a_database(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('demo:restore')->expectsOutputToContain('only available')->assertFailed();
    }

    public function test_refuses_non_demo_names_and_sql_injection(): void
    {
        foreach (['vivu_booking', 'mysql', 'vivu_demo_x`; DROP DATABASE mysql; --'] as $name) {
            $this->artisan('demo:restore', ['--database' => $name])->assertFailed();
        }
    }

    public function test_refuses_sqlite_without_modifying_it(): void
    {
        $this->artisan('demo:restore')->expectsOutputToContain('local MySQL')->assertFailed();
    }

    public function test_refuses_remote_mysql_before_connecting(): void
    {
        config(['database.default' => 'mysql', 'database.connections.mysql.host' => 'production.example.test']);
        $this->artisan('demo:restore')->expectsOutputToContain('local MySQL')->assertFailed();
    }

    public function test_snapshot_is_portable_sanitized_and_matches_its_manifest(): void
    {
        $sql = file_get_contents(database_path('demo/defense.sql'));
        $manifest = json_decode(file_get_contents(database_path('demo/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($manifest['sha256'], hash('sha256', $sql));
        $this->assertDoesNotMatchRegularExpression('/\$2[ayb]\$/', $sql, 'No copied password hashes');
        preg_match_all('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $sql, $emails);
        $this->assertNotEmpty($emails[0]);
        foreach (array_unique($emails[0]) as $email) {
            $this->assertStringEndsWith('@example.test', $email);
        }
        foreach (['jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks', 'sessions', 'personal_access_tokens', 'password_reset_tokens', 'payment_logs', 'notifications', 'ai_chat_messages'] as $table) {
            $this->assertStringNotContainsString("INSERT INTO `{$table}`", $sql);
        }
        foreach (SqlDumpStatements::read($sql) as $statement) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(USE|DROP|TRUNCATE|DELETE|REPLACE|CREATE DATABASE)\b/i', $statement);
        }
    }
}
