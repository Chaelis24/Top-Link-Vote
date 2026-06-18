<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class StressTestCommand extends Command
{
    protected $signature = 'test:stress
        {--users=100 : Number of concurrent users to simulate}
        {--requests=5 : Number of requests per user}
        {--endpoint=/ : Endpoint to stress test}
        {--method=GET : HTTP method}
        {--report : Generate HTML report}
        {--app-url= : Application URL (default: APP_URL from .env)}';

    protected $description = 'Run concurrent HTTP stress tests against the application';

    private Client $client;
    private array $results = [];
    private array $errors = [];
    private array $durations = [];

    public function handle(): int
    {
        $baseUrl = rtrim($this->option('app-url') ?: config('app.url'), '/');
        $numUsers = (int) $this->option('users');
        $requestsPerUser = (int) $this->option('requests');
        $endpoint = $this->option('endpoint');
        $method = strtoupper($this->option('method'));

        if (!$baseUrl || $baseUrl === 'http://localhost') {
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
                'User-Agent' => 'TopLinkVote-StressTest/1.0',
            ],
        ]);

        $this->info("=== TopLinkVote Stress Test ===");
        $this->info("Target: {$baseUrl}{$endpoint}");
        $this->info("Concurrent Users: {$numUsers}");
        $this->info("Requests per User: {$requestsPerUser}");
        $this->info("Total Requests: " . ($numUsers * $requestsPerUser));
        $this->newLine();

        $totalRequests = $numUsers * $requestsPerUser;
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
            'fulfilled' => function ($response, $index) use ($bar) {
                $this->processResponse($response, $index);
                $bar->advance();
            },
            'rejected' => function ($reason, $index) use ($bar) {
                $this->errors[] = [
                    'index' => $index,
                    'error' => $reason->getMessage(),
                ];
                $this->durations[] = 0;
                $bar->advance();
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();

        $bar->finish();
        $this->newLine(2);

        $this->displayResults();
        $this->checkThresholds();

        if ($this->option('report')) {
            $this->generateReport($baseUrl, $endpoint, $numUsers, $requestsPerUser);
        }

        return Command::SUCCESS;
    }

    private function processResponse($response, int $index): void
    {
        $statusCode = $response->getStatusCode();
        $duration = 0;
        $headers = $response->getHeaders();

        if (isset($headers['X-Response-Time'][0])) {
            $duration = (float) $headers['X-Response-Time'][0] * 1000;
        }

        $this->durations[] = $duration;

        $this->results[] = [
            'index' => $index,
            'status' => $statusCode,
            'duration_ms' => round($duration, 2),
            'headers' => $headers,
        ];

        if ($statusCode >= 400) {
            $this->errors[] = [
                'index' => $index,
                'status' => $statusCode,
                'body' => (string) $response->getBody(),
            ];
        }
    }

    private function displayResults(): void
    {
        $totalRequests = count($this->results) + count($this->errors);
        $successfulRequests = count(array_filter($this->results, fn ($r) => $r['status'] < 400));
        $failedRequests = $totalRequests - $successfulRequests;
        $errorRate = $totalRequests > 0 ? ($failedRequests / $totalRequests) * 100 : 0;

        $validDurations = array_filter($this->durations, fn ($d) => $d > 0);
        $avgDuration = count($validDurations) > 0
            ? array_sum($validDurations) / count($validDurations)
            : 0;
        $maxDuration = count($validDurations) > 0 ? max($validDurations) : 0;
        $minDuration = count($validDurations) > 0 ? min($validDurations) : 0;

        sort($validDurations);
        $p95Index = (int) ceil(0.95 * count($validDurations)) - 1;
        $p95 = $validDurations[$p95Index] ?? 0;

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Requests', $totalRequests],
                ['Successful', $successfulRequests],
                ['Failed', $failedRequests],
                ['Error Rate', round($errorRate, 2) . '%'],
                ['Avg Response Time', round($avgDuration, 2) . ' ms'],
                ['Min Response Time', round($minDuration, 2) . ' ms'],
                ['Max Response Time', round($maxDuration, 2) . ' ms'],
                ['P95 Response Time', round($p95, 2) . ' ms'],
                ['Requests/sec (approx)', round($totalRequests / max(($avgDuration / 1000) * $totalRequests, 0.1), 2)],
            ]
        );
    }

    private function checkThresholds(): void
    {
        $thresholds = config('performance.thresholds');
        $hasFailure = false;

        $validDurations = array_filter($this->durations, fn ($d) => $d > 0);
        $avgDuration = count($validDurations) > 0
            ? array_sum($validDurations) / count($validDurations)
            : 0;

        $total = count($this->results) + count($this->errors);
        $successful = count(array_filter($this->results, fn ($r) => $r['status'] < 400));
        $errorRate = $total > 0 ? (($total - $successful) / $total) * 100 : 0;

        if ($avgDuration > ($thresholds['response_time'] * 1000)) {
            $this->error("❌ Response time threshold breached: {$avgDuration} ms > " . ($thresholds['response_time'] * 1000) . " ms");
            $hasFailure = true;
        }

        if ($errorRate > $thresholds['error_rate']) {
            $this->error("❌ Error rate threshold breached: {$errorRate}% > {$thresholds['error_rate']}%");
            $hasFailure = true;
        }

        if (!$hasFailure) {
            $this->info("✅ All thresholds passed");
        }
    }

    private function generateReport(string $baseUrl, string $endpoint, int $numUsers, int $requestsPerUser): void
    {
        $storagePath = config('performance.reporting.storage_path', storage_path('app/performance-reports'));

        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $jsonPath = "{$storagePath}/stress-test_{$timestamp}.json";

        $validDurations = array_filter($this->durations, fn ($d) => $d > 0);
        $avgDuration = count($validDurations) > 0 ? array_sum($validDurations) / count($validDurations) : 0;

        $total = count($this->results) + count($this->errors);
        $successful = count(array_filter($this->results, fn ($r) => $r['status'] < 400));

        $data = [
            'generated_at' => now()->toDateTimeString(),
            'target_url' => $baseUrl,
            'endpoint' => $endpoint,
            'concurrent_users' => $numUsers,
            'requests_per_user' => $requestsPerUser,
            'total_requests' => $total,
            'results' => [
                'successful' => $successful,
                'failed' => $total - $successful,
                'error_rate' => $total > 0 ? round((($total - $successful) / $total) * 100, 2) : 0,
                'avg_response_ms' => round($avgDuration, 2),
                'max_response_ms' => round(count($validDurations) > 0 ? max($validDurations) : 0, 2),
                'min_response_ms' => round(count($validDurations) > 0 ? min($validDurations) : 0, 2),
                'p95_response_ms' => 0,
            ],
            'thresholds' => config('performance.thresholds'),
            'errors' => $this->errors,
        ];

        $validDurArr = array_values($validDurations);
        sort($validDurArr);
        $data['results']['p95_response_ms'] = round($validDurArr[(int) ceil(0.95 * count($validDurArr)) - 1] ?? 0, 2);

        file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT));

        $htmlPath = str_replace('.json', '.html', $jsonPath);
        $this->generateHtmlReport($data, $htmlPath);

        $this->info("Report saved to: {$jsonPath}");
        $this->info("HTML report: {$htmlPath}");
    }

    private function generateHtmlReport(array $data, string $path): void
    {
        $statusColor = $data['results']['error_rate'] > 1 ? 'status-fail' : 'status-pass';
        $statusText = $data['results']['error_rate'] > 1 ? 'FAILED' : 'PASSED';

        $errorRows = '';
        foreach (array_slice($data['errors'], 0, 20) as $error) {
            $errorRows .= "<tr><td>{$error['index']}</td><td>{$error['status']}</td><td>" . ($error['error'] ?? '') . "</td></tr>";
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stress Test Report - {$data['generated_at']}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 1200px; margin: 0 auto; padding: 2rem; background: #f5f5f5; }
        h1, h2, h3 { color: #333; }
        .card { background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 1.5rem; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { background: #f8f9fa; font-weight: 600; }
        .status-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 12px; font-weight: 600; font-size: 0.875rem; }
        .status-pass { background: #d4edda; color: #155724; }
        .status-fail { background: #f8d7da; color: #721c24; }
        .meta { color: #666; font-size: 0.875rem; }
        .metric-value { font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔬 Stress Test Report</h1>
        <p class="meta">Generated: {$data['generated_at']} | Target: {$data['target_url']}{$data['endpoint']}</p>
        <p class="meta">Overall: <span class="status-badge {$statusColor}">{$statusText}</span></p>
    </div>

    <div class="card">
        <h2>Configuration</h2>
        <table>
            <tr><td>Concurrent Users</td><td class="metric-value">{$data['concurrent_users']}</td></tr>
            <tr><td>Requests per User</td><td class="metric-value">{$data['requests_per_user']}</td></tr>
            <tr><td>Total Requests</td><td class="metric-value">{$data['total_requests']}</td></tr>
        </table>
    </div>

    <div class="card">
        <h2>Results</h2>
        <table>
            <tr><td>Successful Requests</td><td class="metric-value">{$data['results']['successful']}</td></tr>
            <tr><td>Failed Requests</td><td class="metric-value">{$data['results']['failed']}</td></tr>
            <tr><td>Error Rate</td><td class="metric-value">{$data['results']['error_rate']}%</td></tr>
            <tr><td>Avg Response Time</td><td class="metric-value">{$data['results']['avg_response_ms']} ms</td></tr>
            <tr><td>Min Response Time</td><td class="metric-value">{$data['results']['min_response_ms']} ms</td></tr>
            <tr><td>Max Response Time</td><td class="metric-value">{$data['results']['max_response_ms']} ms</td></tr>
            <tr><td>P95 Response Time</td><td class="metric-value">{$data['results']['p95_response_ms']} ms</td></tr>
        </table>
    </div>

    <div class="card">
        <h2>Errors (first 20)</h2>
        <table>
            <thead><tr><th>Index</th><th>Status</th><th>Error</th></tr></thead>
            <tbody>{$errorRows}</tbody>
        </table>
    </div>
</body>
</html>
HTML;

        file_put_contents($path, $html);
    }

    private function startDevServer(): string
    {
        $port = 9100;
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
