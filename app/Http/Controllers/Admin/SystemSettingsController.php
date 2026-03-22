<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class SystemSettingsController extends Controller
{
    /**
     * Display system settings.
     */
    public function show(): JsonResponse
    {
        $settings = [
            'application' => [
                'name' => Config::get('app.name'),
                'url' => Config::get('app.url'),
                'timezone' => Config::get('app.timezone'),
                'locale' => Config::get('app.locale'),
                'fallback_locale' => Config::get('app.fallback_locale'),
                'debug' => Config::get('app.debug'),
                'env' => Config::get('app.env'),
            ],
            'database' => [
                'default' => Config::get('database.default'),
                'connections' => array_keys(Config::get('database.connections')),
            ],
            'cache' => [
                'default' => Config::get('cache.default'),
                'prefix' => Config::get('cache.prefix'),
            ],
            'queue' => [
                'default' => Config::get('queue.default'),
                'connections' => array_keys(Config::get('queue.connections')),
            ],
            'mail' => [
                'default' => Config::get('mail.default'),
                'from_address' => Config::get('mail.from.address'),
                'from_name' => Config::get('mail.from.name'),
            ],
            'services' => [
                'sanctum' => [
                    'enabled' => true,
                    'expiration' => Config::get('sanctum.expiration'),
                ],
            ],
            'features' => [
                'rate_limiting' => true,
                'audit_logging' => true,
                'webhooks' => true,
                'exports' => true,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'settings' => $settings,
            ],
            'message' => 'System settings retrieved successfully',
        ], 200);
    }

    /**
     * Update system settings.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'application' => ['nullable', 'array'],
            'application.name' => ['nullable', 'string', 'max:255'],
            'application.url' => ['nullable', 'url'],
            'application.timezone' => ['nullable', 'timezone'],
            'application.locale' => ['nullable', 'string', 'max:10'],
            'application.debug' => ['nullable', 'boolean'],

            'cache' => ['nullable', 'array'],
            'cache.default' => ['nullable', 'string'],
            'cache.prefix' => ['nullable', 'string'],

            'queue' => ['nullable', 'array'],
            'queue.default' => ['nullable', 'string'],

            'features' => ['nullable', 'array'],
            'features.rate_limiting' => ['nullable', 'boolean'],
            'features.audit_logging' => ['nullable', 'boolean'],
            'features.webhooks' => ['nullable', 'boolean'],
            'features.exports' => ['nullable', 'boolean'],
        ]);

        $updated = [];

        // Note: In production, these should be stored in a database-backed settings table
        // For now, we'll log the changes and return success
        if (isset($validated['application'])) {
            $updated['application'] = $validated['application'];
            Log::info('System settings updated: application', $validated['application']);
        }

        if (isset($validated['cache'])) {
            $updated['cache'] = $validated['cache'];
            Log::info('System settings updated: cache', $validated['cache']);

            // Clear cache if cache settings changed
            if (isset($validated['cache']['default']) || isset($validated['cache']['prefix'])) {
                Cache::flush();
                $updated['cache_cleared'] = true;
            }
        }

        if (isset($validated['queue'])) {
            $updated['queue'] = $validated['queue'];
            Log::info('System settings updated: queue', $validated['queue']);
        }

        if (isset($validated['features'])) {
            $updated['features'] = $validated['features'];
            Log::info('System settings updated: features', $validated['features']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'updated_settings' => $updated,
            ],
            'message' => 'System settings updated successfully. Note: Changes are logged but require configuration file updates for persistence.',
        ], 200);
    }

    /**
     * Clear system cache.
     */
    public function clearCache(): JsonResponse
    {
        try {
            Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'System cache cleared successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CACHE_CLEAR_FAILED',
                    'message' => 'Failed to clear cache: ' . $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get system health status.
     */
    public function health(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'storage' => $this->checkStorage(),
                'logs' => $this->checkLogs(),
            ],
        ];

        // Determine overall status
        $hasCritical = false;
        $hasWarning = false;

        foreach ($health['checks'] as $check) {
            if ($check['status'] === 'critical') {
                $hasCritical = true;
            } elseif ($check['status'] === 'warning') {
                $hasWarning = true;
            }
        }

        if ($hasCritical) {
            $health['status'] = 'critical';
        } elseif ($hasWarning) {
            $health['status'] = 'warning';
        }

        return response()->json([
            'success' => true,
            'data' => $health,
            'message' => 'System health check completed',
        ], 200);
    }

    /**
     * Check database connectivity.
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            $result = \DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'connection' => Config::get('database.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache connectivity.
     */
    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            Cache::put('health_check', 'ok', 10);
            $value = Cache::get('health_check');
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            if ($value !== 'ok') {
                return [
                    'status' => 'warning',
                    'message' => 'Cache write/read mismatch',
                ];
            }

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'driver' => Config::get('cache.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check storage permissions.
     */
    private function checkStorage(): array
    {
        try {
            $storagePath = storage_path();
            $writable = is_writable($storagePath);
            $logsPath = storage_path('logs');
            $logsWritable = is_writable($logsPath);

            if (! $writable || ! $logsWritable) {
                return [
                    'status' => 'critical',
                    'message' => 'Storage directory not writable',
                    'storage_writable' => $writable,
                    'logs_writable' => $logsWritable,
                ];
            }

            return [
                'status' => 'healthy',
                'storage_writable' => true,
                'logs_writable' => true,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check log system.
     */
    private function checkLogs(): array
    {
        try {
            $logFile = storage_path('logs/laravel.log');
            $logExists = file_exists($logFile);
            $logSize = $logExists ? filesize($logFile) : 0;

            // Warn if log file is larger than 100MB
            if ($logSize > 100 * 1024 * 1024) {
                return [
                    'status' => 'warning',
                    'message' => 'Log file is larger than 100MB',
                    'size_mb' => round($logSize / 1024 / 1024, 2),
                ];
            }

            return [
                'status' => 'healthy',
                'log_file_exists' => $logExists,
                'size_mb' => round($logSize / 1024 / 1024, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Run artisan command (restricted).
     */
    public function runCommand(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command' => ['required', 'string'],
        ]);

        $command = $validated['command'];

        // Only allow safe commands
        $allowedCommands = [
            'cache:clear',
            'config:clear',
            'route:clear',
            'view:clear',
            'optimize',
            'optimize:clear',
        ];

        if (! in_array($command, $allowedCommands)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'COMMAND_NOT_ALLOWED',
                    'message' => 'Command not in allowed list: ' . implode(', ', $allowedCommands),
                ],
            ], 403);
        }

        try {
            $start = microtime(true);
            Artisan::call($command);
            $duration = round((microtime(true) - $start) * 1000, 2);
            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'data' => [
                    'command' => $command,
                    'output' => trim($output) ?: 'No output',
                    'duration_ms' => $duration,
                ],
                'message' => 'Command executed successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'COMMAND_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}
