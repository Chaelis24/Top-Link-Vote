<?php

namespace Tests\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PerformanceHelper
{
    private static array $metrics = [];
    private static array $timings = [];
    private static array $reportData = [];
    private static string $currentCategory = '';

    public function __destruct()
    {
        self::flush();
    }

    public static function setCategory(string $category): void
    {
        self::$currentCategory = $category;
    }

    public static function startMeasurement(string $name): void
    {
        self::$timings[$name] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true),
        ];
    }

    public static function endMeasurement(string $name, array $context = []): array
    {
        $end = microtime(true);
        $memoryEnd = memory_get_usage(true);
        $timing = self::$timings[$name] ?? null;

        if (!$timing) {
            return [];
        }

        $duration = ($end - $timing['start']) * 1000;
        $memoryUsed = ($memoryEnd - $timing['memory_start']) / 1024 / 1024;

        $result = [
            'name' => $name,
            'duration_ms' => round($duration, 2),
            'memory_mb' => round($memoryUsed, 2),
            'time' => now()->toDateTimeString(),
            ...$context,
        ];

        self::$metrics[$name][] = $result;
        self::$reportData[] = $result;

        return $result;
    }

    public static function recordMetric(string $category, array $data): void
    {
        self::$metrics[$category][] = $data;
        self::$reportData[] = $data;
    }

    public static function getMetrics(?string $name = null): array
    {
        return $name ? (self::$metrics[$name] ?? []) : self::$metrics;
    }    public static function reset(): void
    {
        self::flush();
        self::$metrics = [];
        self::$timings = [];
        self::$reportData = [];
    }

    public static function flush(): void
    {
        if (empty(self::$metrics)) {
            return;
        }
        try {
            $filename = self::$currentCategory ?: 'performance-report';
            self::saveReport($filename);
        } catch (\Throwable $e) {
        }
    }

    public static function computeStats(array $measurements): array
    {
        if (empty($measurements)) {
            return [
                'avg' => 0,
                'min' => 0,
                'max' => 0,
                'p95' => 0,
                'count' => 0,
            ];
        }

        $durations = array_column($measurements, 'duration_ms');
        sort($durations);
        $count = count($durations);

        return [
            'avg' => round(array_sum($durations) / $count, 2),
            'min' => round(min($durations), 2),
            'max' => round(max($durations), 2),
            'p95' => round($durations[(int) ceil(0.95 * $count) - 1] ?? 0, 2),
            'count' => $count,
        ];
    }

    public static function measureResponseTime(callable $request, string $label, array $context = []): array
    {
        self::startMeasurement($label);
        $response = $request();
        $result = self::endMeasurement($label, $context);

        return [
            'response' => $response,
            'duration_ms' => $result['duration_ms'],
            'memory_mb' => $result['memory_mb'],
        ];
    }

    public static function generateReport(): array
    {
        $report = [];

        foreach (self::$metrics as $name => $measurements) {
            $stats = self::computeStats($measurements);
            $report[$name] = [
                'stats' => $stats,
                'measurements' => $measurements,
                'threshold_breached' => $stats['avg'] > (config('performance.thresholds.response_time', 2.0) * 1000),
            ];
        }

        return $report;
    }

    public static function saveReport(string $filename = 'performance-report'): string
    {
        $report = self::generateReport();
        $storagePath = storage_path('app/performance-reports');

        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $jsonPath = "{$storagePath}/{$filename}_{$timestamp}.json";

        $thresholds = config('performance.thresholds');

        $summary = [
            'generated_at' => date('Y-m-d H:i:s'),
            'environment' => 'testing',
            'app_version' => 'unknown',
            'thresholds' => $thresholds,
            'results' => $report,
            'overall_status' => self::getOverallStatus($report, $thresholds),
        ];

        file_put_contents($jsonPath, json_encode($summary, JSON_PRETTY_PRINT));

        $htmlPath = "{$storagePath}/{$filename}_{$timestamp}.html";
        file_put_contents($htmlPath, self::generateHtmlReport($summary));

        return $jsonPath;
    }

    private static function getOverallStatus(array $report, array $thresholds): string
    {
        $maxRt = $thresholds['response_time'] * 1000;

        foreach ($report as $name => $data) {
            if (($data['stats']['avg'] ?? 0) > $maxRt) {
                return 'FAILED';
            }
        }

        return 'PASSED';
    }

    private static function generateHtmlReport(array $summary): string
    {
        $rows = '';
        foreach ($summary['results'] as $name => $data) {
            $s = $data['stats'];
            $status = $data['threshold_breached'] ? '❌ FAIL' : '✅ PASS';
            $rows .= <<<HTML
            <tr>
                <td>{$name}</td>
                <td>{$s['avg']} ms</td>
                <td>{$s['min']} ms</td>
                <td>{$s['max']} ms</td>
                <td>{$s['p95']} ms</td>
                <td>{$s['count']}</td>
                <td>{$status}</td>
            </tr>
HTML;
        }

        $overallStatus = $summary['overall_status'];
        $statusClass = $overallStatus === 'PASSED' ? 'status-pass' : 'status-fail';
        $thresholds = $summary['thresholds'];
        $thresholdRows = '';
        foreach ($thresholds as $key => $value) {
            $thresholdRows .= "<tr><td>{$key}</td><td>{$value}</td></tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Performance Report - {$summary['generated_at']}</title>
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
    </style>
</head>
<body>
    <div class="card">
        <h1>📊 Performance Test Report</h1>
        <p class="meta">Generated: {$summary['generated_at']} | Environment: {$summary['environment']} | Laravel: {$summary['app_version']}</p>
        <p class="meta">Overall Status: <span class="status-badge {$statusClass}">{$overallStatus}</span></p>
    </div>

    <div class="card">
        <h2>Results</h2>
        <table>
            <thead>
                <tr>
                    <th>Test</th>
                    <th>Avg</th>
                    <th>Min</th>
                    <th>Max</th>
                    <th>P95</th>
                    <th>Count</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                {$rows}
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Thresholds</h2>
        <table>
            <thead><tr><th>Threshold</th><th>Value</th></tr></thead>
            <tbody>{$thresholdRows}</tbody>
        </table>
    </div>
</body>
</html>
HTML;
    }
}
