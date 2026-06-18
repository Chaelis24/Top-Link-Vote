<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Performance Thresholds
    |--------------------------------------------------------------------------
    |
    | Tests will fail if any of these thresholds are exceeded.
    | Adjust based on your infrastructure capabilities.
    |
    */
    'thresholds' => [
        'response_time' => env('PERF_RESPONSE_TIME', 2.0),
        'error_rate' => env('PERF_ERROR_RATE', 1.0),
        'success_rate' => env('PERF_SUCCESS_RATE', 99.0),
        'memory_limit_mb' => env('PERF_MEMORY_LIMIT', 256),
        'cpu_limit_percent' => env('PERF_CPU_LIMIT', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Load Test Configuration
    |--------------------------------------------------------------------------
    */
    'load' => [
        'concurrency_levels' => [10, 50, 100, 250, 500, 1000, 5000],
        'requests_per_user' => env('PERF_REQUESTS_PER_USER', 5),
        'ramp_up_delay_ms' => env('PERF_RAMP_UP_DELAY', 100),
        'timeout_seconds' => env('PERF_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stress Test Configuration
    |--------------------------------------------------------------------------
    */
    'stress' => [
        'start_users' => env('PERF_STRESS_START', 10),
        'max_users' => env('PERF_STRESS_MAX', 10000),
        'increment' => env('PERF_STRESS_INCREMENT', 50),
        'stabilization_wait_ms' => env('PERF_STABILIZE_WAIT', 1000),
        'unacceptable_response_time' => env('PERF_UNACCEPTABLE_RT', 5.0),
        'error_threshold_percent' => env('PERF_STRESS_ERROR_THRESHOLD', 5.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting Configuration
    |--------------------------------------------------------------------------
    */
    'reporting' => [
        'storage_path' => storage_path('app/performance-reports'),
        'retention_days' => env('PERF_REPORT_RETENTION', 90),
    ],
];
