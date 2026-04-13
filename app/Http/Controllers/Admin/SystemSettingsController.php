<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class SystemSettingsController extends Controller
{
    public function __construct(
        protected SettingsService $settingsService
    ) {}

    /**
     * Display system settings.
     */
    public function show(): JsonResponse
    {
        // Get database settings with config fallbacks
        $settings = $this->settingsService->getAll();

        // Add runtime config that can't be stored in database
        $settings['application'] = array_merge($settings['application'], [
            'env' => Config::get('app.env'),
            'fallback_locale' => Config::get('app.fallback_locale'),
        ]);

        $settings['database'] = [
            'default' => Config::get('database.default'),
            'connections' => array_keys(Config::get('database.connections')),
        ];

        $settings['services'] = [
            'sanctum' => [
                'enabled' => true,
                'expiration' => Config::get('sanctum.expiration'),
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
            'application.fallback_locale' => ['nullable', 'string', 'max:10'],
            'application.debug' => ['nullable', 'boolean'],
            'application.default_currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],

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

        // Update settings in database
        $updated = $this->settingsService->update($validated, $request->user()->id);

        // Clear cache if cache settings changed
        if (isset($validated['cache'])) {
            Cache::flush();
            $updated['cache']['cache_cleared'] = true;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'updated_settings' => $updated,
            ],
            'message' => 'System settings updated successfully. Changes have been persisted to the database.',
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
     *
     * This method allows execution of a limited set of safe Artisan commands.
     * Multiple layers of validation are applied to prevent command injection:
     * 1. Strict regex pattern validation
     * 2. Whitelist of allowed commands
     * 3. Blocked patterns check for dangerous keywords
     * 4. Security logging for monitoring
     */
    public function runCommand(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command' => ['required', 'string', 'max:100', 'regex:/^[a-z:.\-]+$/'],
        ]);

        $command = trim($validated['command']);

        // Block dangerous patterns (defense in depth)
        if ($this->containsDangerousPattern($command)) {
            Log::warning('Blocked command execution attempt - dangerous pattern detected', [
                'command' => $command,
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'COMMAND_BLOCKED',
                    'message' => 'Command contains blocked patterns',
                ],
            ], 403);
        }

        // Only allow safe commands (whitelist)
        $allowedCommands = [
            'cache:clear',
            'config:clear',
            'route:clear',
            'view:clear',
            'optimize',
            'optimize:clear',
        ];

        if (! in_array($command, $allowedCommands, true)) {
            Log::info('Command execution denied - not in whitelist', [
                'command' => $command,
                'allowed_commands' => $allowedCommands,
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
            ]);

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

            Log::info('Artisan command executed successfully', [
                'command' => $command,
                'duration_ms' => $duration,
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);

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
            Log::error('Artisan command execution failed', [
                'command' => $command,
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'COMMAND_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Check if command contains dangerous patterns.
     *
     * @param  string  $command  The command to check
     * @return bool True if dangerous pattern found
     */
    private function containsDangerousPattern(string $command): bool
    {
        $dangerousPatterns = [
            ';', '|', '&', '$', '`', '(', ')', '{', '}', '[', ']',
            '<', '>', '!', '\\', '\n', '\r', '\t', '%', '*', '?',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (str_contains($command, $pattern)) {
                return true;
            }
        }

        // Check for dangerous keywords
        $dangerousKeywords = [
            'exec', 'system', 'shell', 'eval', 'passthru', 'popen',
            'proc_open', 'curl', 'wget', 'nc', 'netcat', 'bash',
            'sh', 'zsh', 'python', 'php', 'ruby', 'perl',
        ];

        $lowerCommand = strtolower($command);
        foreach ($dangerousKeywords as $keyword) {
            if (str_contains($lowerCommand, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
