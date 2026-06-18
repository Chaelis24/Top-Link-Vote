<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class LoadTestCommand extends Command
{
    protected $signature = 'test:load
        {--users= : Number of concurrent users (overrides config levels)}
        {--requests= : Requests per user (default: config load.requests_per_user)}
        {--endpoint=/ : Endpoint to load test}
        {--method=GET : HTTP method}
        {--report : Generate HTML report}
        {--app-url= : Application URL (default: APP_URL from .env)}';

    protected $description = 'Run concurrent HTTP load tests against the application at multiple concurrency levels';

    private Client $client;
    private array $allResults = [];
    private array $allErrors = [];

    public function handle(): int
    {
        $baseUrl = rtrim($this->option('app-url') ?: config('app.url'), '/');
        $requestsPerUser = (int) ($this->option('requests') ?: config('performance.load.requests_per_user', 5));
        $endpoint = $this->option('endpoint');
        $method = strtoupper($this->option('method'));

        $isLocal = in_array($baseUrl, ['http://localhost', 'http://127.0.0.1', 'http://localhost:8000', 'http://127.0.0.1:8000'], true);

        if (!$baseUrl || $isLocal) {
            $this->warn('APP_URL is not set or is localhost. Starting built-in server...');
            $baseUrl = $this->startDevServer();
        }

        $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout' => config('performance.load.timeout_seconds', 30),
            'connect_timeout' => 10,
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'TopLinkVote-LoadTest/1.0',
            ],
        ]);

        $concurrencyLevels = $this->option('users')
            ? [(int) $this->option('users')]
            : config('performance.load.concurrency_levels', [10, 50, 100, 250, 500, 1000, 5000]);

        $rampUpDelay = config('performance.load.ramp_up_delay_ms', 100);

        $this->info("=== TopLinkVote Load Test ===");
        $this->info("Target: {$baseUrl}{$endpoint}");
        $this->info("Concurrency Levels: " . implode(', ', $concurrencyLevels));
        $this->info("Requests per User: {$requestsPerUser}");
        $this->newLine();

        foreach ($concurrencyLevels as $level) {
            $numUsers = (int) $level;
            $totalRequests = $numUsers * $requestsPerUser;

            $this->info("--- Level: {$numUsers} concurrent users ({$totalRequests} requests) ---");

            $durations = [];
            $errors = [];
            $bar = $this->output->createProgressBar($totalRequests);
            $bar->start();

            $requests = function () use ($numUsers, $requestsPerUser, $endpoint, $method) {
                for ($i = 0; $i < $numUsers; $i++) {
                    for ($j = 0; $j < $requestsPerUser; $j++) {
                        yield new Request($method, $endpoint);
                    }
                }
            };

            $pool = new Pool($this->client, $requests(), [
                'concurrency' => min($numUsers, 100),
                'fulfilled' => function ($response, $index) use (&$durations, &$errors, $bar) {
                    $statusCode = $response->getStatusCode();
                    $duration = 0;
                    $headers = $response->getHeaders();
                    if (isset($headers['X-Response-Time'][0])) {
                        $duration = (float) $headers['X-Response-Time'][0] * 1000;
                    }
                    $durations[] = $duration;
                    if ($statusCode >= 400) {
                        $errors[] = ['status' => $statusCode, 'body' => (string) $response->getBody()];
                    }
                    $bar->advance();
                },
                'rejected' => function ($reason, $index) use (&$durations, &$errors, $bar) {
                    $errors[] = ['error' => $reason->getMessage()];
                    $durations[] = 0;
                    $bar->advance();
                },
            ]);

            $promise = $pool->promise();
            $promise->wait();

            $bar->finish();
            $this->newLine();

            $this->displayLevelResults($numUsers, $totalRequests, $durations, $errors);

            $this->allResults["level_{$numUsers}"] = compact('numUsers', 'totalRequests', 'durations', 'errors');

            usleep($rampUpDelay * 1000);
        }

        $this->displaySummary();
        $this->checkThresholds();

        if ($this->option('report')) {
            $this->generateReport($baseUrl, $endpoint);
        }

        return Command::SUCCESS;
    }

    private function displayLevelResults(int $numUsers, int $totalRequests, array $durations, array $errors): void
    {
        $validDurations = array_filter($durations, fn ($d) => $d > 0);
        $successful = $totalRequests - count($errors);
        $errorRate = $totalRequests > 0 ? (count($errors) / $totalRequests) * 100 : 0;
        $avg = count($validDurations) > 0 ? array_sum($validDurations) / count($validDurations) : 0;
        $max = count($validDurations) > 0 ? max($validDurations) : 0;
        $min = count($validDurations) > 0 ? min($validDurations) : 0;

        $sorted = array_values($validDurations);
        sort($sorted);
        $p95 = $sorted[(int) ceil(0.95 * count($sorted)) - 1] ?? 0;

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Requests', $totalRequests],
                ['Successful', $successful],
                ['Failed', count($errors)],
                ['Error Rate', round($errorRate, 2) . '%'],
                ['Avg Response Time', round($avg, 2) . ' ms'],
                ['Min Response Time', round($min, 2) . ' ms'],
                ['Max Response Time', round($max, 2) . ' ms'],
                ['P95 Response Time', round($p95, 2) . ' ms'],
            ]
        );
    }

    private function displaySummary(): void
    {
        $this->newLine();
        $this->info("=== Summary ===");

        $rows = [];
        foreach ($this->allResults as $key => $result) {
            $valid = array_filter($result['durations'], fn ($d) => $d > 0);
            $avg = count($valid) > 0 ? round(array_sum($valid) / count($valid), 2) : 0;
            $errorRate = $result['totalRequests'] > 0
                ? round((count($result['errors']) / $result['totalRequests']) * 100, 2)
                : 0;
            $rows[] = [
                $result['numUsers'],
                $result['totalRequests'],
                count($result['errors']),
                "{$errorRate}%",
                "{$avg} ms",
            ];
        }

        $this->table(
            ['Users', 'Requests', 'Failed', 'Error Rate', 'Avg Response'],
            $rows
        );
    }

    private function checkThresholds(): void
    {
        $thresholds = config('performance.thresholds');
        $hasFailure = false;

        foreach ($this->allResults as $result) {
            $total = $result['totalRequests'];
            $errors = count($result['errors']);
            $errorRate = $total > 0 ? ($errors / $total) * 100 : 0;
            $valid = array_filter($result['durations'], fn ($d) => $d > 0);
            $avg = count($valid) > 0 ? array_sum($valid) / count($valid) : 0;

            if ($avg > ($thresholds['response_time'] * 1000)) {
                $this->error("❌ [{$result['numUsers']} users] Response time {$avg} ms > " . ($thresholds['response_time'] * 1000) . " ms");
                $hasFailure = true;
            }
            if ($errorRate > $thresholds['error_rate']) {
                $this->error("❌ [{$result['numUsers']} users] Error rate {$errorRate}% > {$thresholds['error_rate']}%");
                $hasFailure = true;
            }
        }

        if (!$hasFailure) {
            $this->info("✅ All thresholds passed across all levels");
        }
    }

    private function generateReport(string $baseUrl, string $endpoint): void
    {
        $storagePath = config('performance.reporting.storage_path', storage_path('app/performance-reports'));
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $jsonPath = "{$storagePath}/load-test_{$timestamp}.json";

        $levels = [];
        foreach ($this->allResults as $key => $result) {
            $valid = array_filter($result['durations'], fn ($d) => $d > 0);
            $sorted = array_values($valid);
            sort($sorted);
            $levels[$key] = [
                'concurrent_users' => $result['numUsers'],
                'total_requests' => $result['totalRequests'],
                'errors' => count($result['errors']),
                'error_rate' => $result['totalRequests'] > 0
                    ? round((count($result['errors']) / $result['totalRequests']) * 100, 2) : 0,
                'avg_response_ms' => count($valid) > 0 ? round(array_sum($valid) / count($valid), 2) : 0,
                'min_response_ms' => count($valid) > 0 ? round(min($valid), 2) : 0,
                'max_response_ms' => count($valid) > 0 ? round(max($valid), 2) : 0,
                'p95_response_ms' => round($sorted[(int) ceil(0.95 * count($sorted)) - 1] ?? 0, 2),
            ];
        }

        $data = [
            'generated_at' => now()->toDateTimeString(),
            'target_url' => $baseUrl,
            'endpoint' => $endpoint,
            'levels' => $levels,
            'thresholds' => config('performance.thresholds'),
            'errors' => $this->allErrors,
        ];

        file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT));
        $this->info("Report saved to: {$jsonPath}");
    }

    private function startDevServer(): string
    {
        $port = 9101;
        $host = '127.0.0.1';

        $process = new Process([
            PHP_BINARY,
            base_path('artisan'),
            'serve',
            "--host={$host}",
            "--port={$port}",
        ], null, ['APP_ENV' => 'testing', 'APP_DEBUG' => 'true']);

        $process->setTimeout(null);
        $process->start();

        usleep(500000);

        $url = "http://{$host}:{$port}";
        $this->warn("Dev server started at {$url}");

        $this->registerShutdown(function () use ($process) {
            $process->stop();
        });

        return $url;
    }

    private function registerShutdown(callable $callback): void
    {
        register_shutdown_function(function () use ($callback) {
            $callback();
        });
    }
}
