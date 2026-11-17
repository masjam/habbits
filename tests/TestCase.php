<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection() instanceof \Illuminate\Database\SQLiteConnection) {
            $pdo = DB::connection()->getPdo();
            if (method_exists($pdo, 'sqliteCreateFunction')) {
                $pdo->sqliteCreateFunction('MONTH', function ($date) {
                    if (!$date) return null;
                    return (int) date('n', strtotime($date));
                });
                $pdo->sqliteCreateFunction('YEAR', function ($date) {
                    if (!$date) return null;
                    return (int) date('Y', strtotime($date));
                });
            }
        }
    }
}

