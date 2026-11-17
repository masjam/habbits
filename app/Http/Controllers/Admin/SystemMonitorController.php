<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class SystemMonitorController extends Controller
{
    /**
     * Tampilkan halaman Pemantauan Sistem (System Monitor).
     * Khusus untuk Super Admin.
     */
    public function index(Request $request): Response
    {
        $this->authorizeSuperAdmin();

        $metrics = $this->collectAllMetrics();

        return Inertia::render('Admin/System/Monitor', [
            'initialMetrics' => $metrics,
        ]);
    }

    /**
     * Endpoint API JSON untuk polling / pembaruan realtime status metrik.
     */
    public function metrics(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $metrics = $this->collectAllMetrics();

        return response()->json([
            'success'   => true,
            'timestamp' => Carbon::now()->toIso8601String(),
            'metrics'   => $metrics,
        ]);
    }

    /**
     * Pastikan hanya Superadmin asli atau user ber-role superadmin yang dapat mengakses.
     */
    private function authorizeSuperAdmin(): void
    {
        $user = auth()->user();
        $isSuperAdmin = $user && ($user->isActualSuperadmin() || $user->hasRole('superadmin'));

        if (!$isSuperAdmin) {
            abort(403, 'Akses Ditolak: Hanya Superadmin yang berhak mengakses pemantauan sistem.');
        }
    }

    /**
     * Kumpulkan seluruh metrik sistem secara komprehensif dan aman.
     */
    public function collectAllMetrics(): array
    {
        return [
            'cpu'         => $this->getCpuMetrics(),
            'memory'      => $this->getMemoryMetrics(),
            'storage'     => $this->getStorageMetrics(),
            'database'    => $this->getDatabaseMetrics(),
            'server'      => $this->getServerMetrics(),
            'application' => $this->getApplicationMetrics(),
            'checked_at'  => Carbon::now()->locale('id')->isoFormat('D MMMM Y, HH:mm:ss'),
        ];
    }

    /**
     * ─── 1. CPU METRICS ─────────────────────────────────────────────────────
     */
    private function getCpuMetrics(): array
    {
        $cores = 1;
        $loadAvg = null;
        $usagePercent = null;

        // Deteksi jumlah core
        if (PHP_OS_FAMILY === 'Windows') {
            $coresEnv = getenv('NUMBER_OF_PROCESSORS');
            if ($coresEnv && is_numeric($coresEnv)) {
                $cores = (int) $coresEnv;
            }
        } elseif (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            $cores = substr_count($cpuinfo, 'processor');
            $cores = max(1, $cores);
        }

        // Deteksi Load Average (Linux)
        if (function_exists('sys_getloadavg')) {
            $load = @sys_getloadavg();
            if (is_array($load) && count($load) >= 3) {
                $loadAvg = [
                    '1m'  => round($load[0], 2),
                    '5m'  => round($load[1], 2),
                    '15m' => round($load[2], 2),
                ];
                // Estimasi utilisasi CPU berdasarkan load 1 menit / total cores
                $usagePercent = min(100, round(($load[0] / max(1, $cores)) * 100, 1));
            }
        } elseif (is_readable('/proc/loadavg')) {
            $loadStr = file_get_contents('/proc/loadavg');
            $parts = explode(' ', trim($loadStr));
            if (count($parts) >= 3) {
                $loadAvg = [
                    '1m'  => round((float) $parts[0], 2),
                    '5m'  => round((float) $parts[1], 2),
                    '15m' => round((float) $parts[2], 2),
                ];
                $usagePercent = min(100, round(((float) $parts[0] / max(1, $cores)) * 100, 1));
            }
        }

        // Di Windows, jika loadAvg null coba wmic secara aman dan cepat
        if ($usagePercent === null && PHP_OS_FAMILY === 'Windows' && function_exists('shell_exec')) {
            try {
                $wmicOutput = @shell_exec('wmic cpu get loadpercentage 2>nul');
                if ($wmicOutput && preg_match('/\d+/', $wmicOutput, $m)) {
                    $usagePercent = (int) $m[0];
                }
            } catch (\Throwable $e) {}
        }

        return [
            'cores'         => $cores,
            'usage_percent' => $usagePercent ?? 0,
            'load_average'  => $loadAvg,
            'model'         => $this->getCpuModelName(),
        ];
    }

    private function getCpuModelName(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return getenv('PROCESSOR_IDENTIFIER') ?: 'Windows Processor';
        }

        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            if (preg_match('/model name\s*:\s*(.+)$/m', $cpuinfo, $matches)) {
                return trim($matches[1]);
            }
        }

        return php_uname('m');
    }

    /**
     * ─── 2. MEMORY METRICS ──────────────────────────────────────────────────
     */
    private function getMemoryMetrics(): array
    {
        $systemTotal = 0;
        $systemFree  = 0;
        $systemUsed  = 0;
        $systemUsedPercent = 0;

        // Baca meminfo di Linux
        if (is_readable('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $matchesTotal);
            preg_match('/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $matchesAvail);

            if (!empty($matchesTotal[1]) && !empty($matchesAvail[1])) {
                $systemTotal = (int) $matchesTotal[1] * 1024;
                $systemFree  = (int) $matchesAvail[1] * 1024;
                $systemUsed  = max(0, $systemTotal - $systemFree);
                $systemUsedPercent = round(($systemUsed / $systemTotal) * 100, 1);
            }
        } elseif (PHP_OS_FAMILY === 'Windows' && function_exists('shell_exec')) {
            try {
                $wmicOutput = @shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /value 2>nul');
                if ($wmicOutput) {
                    preg_match('/TotalVisibleMemorySize=(\d+)/i', $wmicOutput, $mTotal);
                    preg_match('/FreePhysicalMemory=(\d+)/i', $wmicOutput, $mFree);

                    if (!empty($mTotal[1]) && !empty($mFree[1])) {
                        $systemTotal = (int) $mTotal[1] * 1024;
                        $systemFree  = (int) $mFree[1] * 1024;
                        $systemUsed  = max(0, $systemTotal - $systemFree);
                        $systemUsedPercent = round(($systemUsed / $systemTotal) * 100, 1);
                    }
                }
            } catch (\Throwable $e) {}
        }

        // Metrik memori proses PHP
        $phpCurrent = memory_get_usage(true);
        $phpPeak    = memory_get_peak_usage(true);
        $phpLimitStr= ini_get('memory_limit') ?: '128M';
        $phpLimit   = $this->parseMemoryLimit($phpLimitStr);

        return [
            'system' => [
                'total_bytes'     => $systemTotal,
                'total_formatted' => $systemTotal > 0 ? $this->formatBytes($systemTotal) : 'N/A',
                'used_bytes'      => $systemUsed,
                'used_formatted'  => $systemUsed > 0 ? $this->formatBytes($systemUsed) : 'N/A',
                'free_bytes'      => $systemFree,
                'free_formatted'  => $systemFree > 0 ? $this->formatBytes($systemFree) : 'N/A',
                'used_percent'    => $systemUsedPercent,
            ],
            'php' => [
                'current_bytes'     => $phpCurrent,
                'current_formatted' => $this->formatBytes($phpCurrent),
                'peak_bytes'        => $phpPeak,
                'peak_formatted'    => $this->formatBytes($phpPeak),
                'limit'             => $phpLimitStr,
                'limit_bytes'       => $phpLimit,
                'used_percent'      => $phpLimit > 0 ? round(($phpCurrent / $phpLimit) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * ─── 3. STORAGE & DISK METRICS ──────────────────────────────────────────
     */
    private function getStorageMetrics(): array
    {
        $basePath = base_path();
        $totalBytes = (float) @disk_total_space($basePath);
        $freeBytes  = (float) @disk_free_space($basePath);
        $usedBytes  = max(0, $totalBytes - $freeBytes);
        $usedPercent = $totalBytes > 0 ? round(($usedBytes / $totalBytes) * 100, 1) : 0;

        // Hitung ukuran direktori-direktori kunci
        $directories = [
            'public_storage' => [
                'name'  => 'Foto Presensi & Lampiran (storage/app/public)',
                'path'  => storage_path('app/public'),
                'bytes' => $this->getDirectorySize(storage_path('app/public')),
            ],
            'logs' => [
                'name'  => 'Log Aplikasi (storage/logs)',
                'path'  => storage_path('logs'),
                'bytes' => $this->getDirectorySize(storage_path('logs')),
            ],
            'cache' => [
                'name'  => 'Framework Cache (storage/framework/cache)',
                'path'  => storage_path('framework/cache'),
                'bytes' => $this->getDirectorySize(storage_path('framework/cache')),
            ],
            'build' => [
                'name'  => 'Aset Web Terkompilasi (public/build)',
                'path'  => public_path('build'),
                'bytes' => $this->getDirectorySize(public_path('build')),
            ],
        ];

        foreach ($directories as $key => $dir) {
            $directories[$key]['formatted'] = $this->formatBytes($dir['bytes']);
        }

        return [
            'disk' => [
                'base_path'       => $basePath,
                'total_bytes'     => $totalBytes,
                'total_formatted' => $this->formatBytes($totalBytes),
                'used_bytes'      => $usedBytes,
                'used_formatted'  => $this->formatBytes($usedBytes),
                'free_bytes'      => $freeBytes,
                'free_formatted'  => $this->formatBytes($freeBytes),
                'used_percent'    => $usedPercent,
            ],
            'directories' => $directories,
        ];
    }

    /**
     * ─── 4. DATABASE METRICS ────────────────────────────────────────────────
     */
    private function getDatabaseMetrics(): array
    {
        $driver = config('database.default');
        $dbName = config("database.connections.{$driver}.database");
        $connectionOk = false;
        $latencyMs = null;
        $dbSizeBytes = 0;
        $tablesCount = 0;
        $topTables = [];

        try {
            $t0 = microtime(true);
            DB::select('SELECT 1');
            $latencyMs = round((microtime(true) - $t0) * 1000, 2);
            $connectionOk = true;

            if ($driver === 'mysql') {
                // Ukuran total database MySQL
                $sizeResult = DB::select(
                    'SELECT SUM(data_length + index_length) AS total_size, COUNT(table_name) as total_tables
                     FROM information_schema.TABLES
                     WHERE table_schema = ?',
                    [$dbName]
                );

                if (!empty($sizeResult[0])) {
                    $dbSizeBytes = (int) ($sizeResult[0]->total_size ?? 0);
                    $tablesCount = (int) ($sizeResult[0]->total_tables ?? 0);
                }

                // Ambil daftar 8 tabel dengan ukuran terbesar
                $topTablesRaw = DB::select(
                    'SELECT table_name, table_rows, data_length, index_length, (data_length + index_length) AS total_size
                     FROM information_schema.TABLES
                     WHERE table_schema = ?
                     ORDER BY total_size DESC
                     LIMIT 8',
                    [$dbName]
                );

                foreach ($topTablesRaw as $row) {
                    $topTables[] = [
                        'name'            => $row->table_name,
                        'rows'            => (int) $row->table_rows,
                        'size_bytes'      => (int) $row->total_size,
                        'size_formatted'  => $this->formatBytes((int) $row->total_size),
                        'data_formatted'  => $this->formatBytes((int) $row->data_length),
                        'index_formatted' => $this->formatBytes((int) $row->index_length),
                    ];
                }
            } elseif ($driver === 'sqlite') {
                if ($dbName && file_exists($dbName)) {
                    $dbSizeBytes = filesize($dbName);
                }
                $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                $tablesCount = count($tables);
            }
        } catch (\Throwable $e) {
            $connectionOk = false;
        }

        // Hitung baris tabel-tabel utama
        $counts = [];
        try {
            $counts['users'] = DB::table('users')->count();
        } catch (\Throwable $e) { $counts['users'] = 0; }

        try {
            $counts['habit_logs'] = DB::table('habit_logs')->count();
        } catch (\Throwable $e) { $counts['habit_logs'] = 0; }

        try {
            $counts['attendances'] = DB::table('attendances')->count();
        } catch (\Throwable $e) { $counts['attendances'] = 0; }

        try {
            // Sesi aktif dalam 2 jam terakhir (120 menit)
            $counts['active_sessions'] = DB::table('sessions')
                ->where('last_activity', '>=', Carbon::now()->subMinutes(120)->timestamp)
                ->count();
        } catch (\Throwable $e) { $counts['active_sessions'] = 0; }

        try {
            $counts['failed_jobs'] = DB::table('failed_jobs')->count();
        } catch (\Throwable $e) { $counts['failed_jobs'] = 0; }

        return [
            'driver'         => $driver,
            'database_name'  => $dbName,
            'connected'      => $connectionOk,
            'latency_ms'     => $latencyMs,
            'size_bytes'     => $dbSizeBytes,
            'size_formatted' => $this->formatBytes($dbSizeBytes),
            'tables_count'   => $tablesCount,
            'top_tables'     => $topTables,
            'row_counts'     => $counts,
        ];
    }

    /**
     * ─── 5. SERVER & ENVIRONMENT METRICS ────────────────────────────────────
     */
    private function getServerMetrics(): array
    {
        $uptimeStr = 'N/A';
        if (is_readable('/proc/uptime')) {
            $uptimeSec = (int) floatval(file_get_contents('/proc/uptime'));
            $days = floor($uptimeSec / 86400);
            $hours = floor(($uptimeSec % 86400) / 3600);
            $mins = floor(($uptimeSec % 3600) / 60);
            $uptimeStr = "{$days}h {$hours}j {$mins}m";
        }

        // Status OPcache
        $opcacheInfo = [
            'enabled'      => false,
            'hit_rate'     => null,
            'memory_used'  => 'N/A',
            'cached_files' => 0,
        ];

        if (function_exists('opcache_get_status')) {
            $status = @opcache_get_status(false);
            if (is_array($status) && !empty($status['opcache_enabled'])) {
                $opcacheInfo['enabled'] = true;
                if (isset($status['opcache_statistics']['opcache_hit_rate'])) {
                    $opcacheInfo['hit_rate'] = round($status['opcache_statistics']['opcache_hit_rate'], 1) . '%';
                }
                if (isset($status['memory_usage']['used_memory'])) {
                    $opcacheInfo['memory_used'] = $this->formatBytes($status['memory_usage']['used_memory']);
                }
                if (isset($status['opcache_statistics']['num_cached_scripts'])) {
                    $opcacheInfo['cached_files'] = (int) $status['opcache_statistics']['num_cached_scripts'];
                }
            }
        }

        // Ekstensi Kunci
        $keyExtensions = [
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'openssl'   => extension_loaded('openssl'),
            'mbstring'  => extension_loaded('mbstring'),
            'curl'      => extension_loaded('curl'),
            'gd'        => extension_loaded('gd'),
            'zip'       => extension_loaded('zip'),
            'fileinfo'  => extension_loaded('fileinfo'),
            'bcmath'    => extension_loaded('bcmath'),
            'redis'     => extension_loaded('redis'),
        ];

        return [
            'php_version'        => PHP_VERSION,
            'laravel_version'    => app()->version(),
            'os_family'          => PHP_OS_FAMILY,
            'os_name'            => php_uname('s') . ' ' . php_uname('r') . ' (' . php_uname('m') . ')',
            'server_software'    => $_SERVER['SERVER_SOFTWARE'] ?? (PHP_SAPI === 'cli' ? 'PHP CLI Server' : 'Web Server'),
            'server_uptime'      => $uptimeStr,
            'timezone'           => config('app.timezone'),
            'current_time'       => Carbon::now()->format('Y-m-d H:i:s T'),
            'max_execution_time' => ini_get('max_execution_time') . 's',
            'post_max_size'      => ini_get('post_max_size'),
            'upload_max_filesize'=> ini_get('upload_max_filesize'),
            'opcache'            => $opcacheInfo,
            'extensions'         => $keyExtensions,
        ];
    }

    /**
     * ─── 6. APPLICATION METRICS ─────────────────────────────────────────────
     */
    private function getApplicationMetrics(): array
    {
        $settings = Setting::allCached();

        return [
            'app_name'         => config('app.name'),
            'app_env'          => config('app.env'),
            'app_debug'        => config('app.debug'),
            'cache_driver'     => config('cache.default'),
            'session_driver'   => config('session.driver'),
            'queue_connection' => config('queue.default'),
            'maintenance_mode' => ($settings['maintenance_mode'] ?? '0') === '1' || ($settings['maintenance_mode'] ?? 'false') === 'true',
            'vapid_configured' => !empty(config('webpush.vapid.public_key')) && !empty(config('webpush.vapid.private_key')),
            'rfid_kiosk_token' => !empty($settings['kiosk_api_token']),
            'dark_mode_active' => ($settings['dark_mode_active'] ?? '0') === '1',
        ];
    }

    /**
     * Helper: Hitung ukuran total folder secara rekursif (dalam bytes).
     */
    private function getDirectorySize(string $path): int
    {
        if (!file_exists($path) || !is_dir($path)) {
            return 0;
        }

        $size = 0;
        try {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (\Throwable $e) {
            return 0;
        }

        return $size;
    }

    /**
     * Helper: Format ukuran bytes ke unit KB, MB, GB yang mudah dibaca manusia.
     */
    private function formatBytes(float|int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        $value = $bytes / pow(1024, $power);
        return round($value, $precision) . ' ' . $units[$power];
    }

    /**
     * Helper: Parse string memory limit (misal '512M', '1G', '-1') ke bytes.
     */
    private function parseMemoryLimit(string $val): int
    {
        $val = trim($val);
        if ($val === '-1') return -1;

        $last = strtolower($val[strlen($val) - 1] ?? '');
        $num = (int) $val;

        switch ($last) {
            case 'g': $num *= 1024 * 1024 * 1024; break;
            case 'm': $num *= 1024 * 1024; break;
            case 'k': $num *= 1024; break;
        }

        return $num;
    }
}
