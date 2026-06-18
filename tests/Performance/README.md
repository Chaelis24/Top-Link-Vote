# Performance, Load, and Stress Testing

## Overview

This suite measures how many concurrent users the TopLinkVote system can handle before performance degrades or failures occur. It includes performance benchmarks, load simulations, and stress testing.

## Test Structure

```
tests/
├── Performance/          # Endpoint response time benchmarks
│   ├── HomepageTest.php
│   ├── LoginTest.php
│   ├── DashboardTest.php
│   ├── CandidateListingTest.php
│   ├── VotingPageTest.php
│   ├── ApiTest.php
│   └── DatabaseQueryTest.php
├── Load/                 # Concurrent user simulations
│   └── ConcurrentUsersTest.php
├── Stress/               # Breaking point and recovery tests
│   ├── ElectionWorkflowTest.php
│   ├── GradualLoadTest.php
│   └── DatabaseStressTest.php
├── Helpers/
│   └── PerformanceHelper.php   # Metrics tracking & reporting
└── PerformanceReport.php       # Report generation
```

## How to Run Tests

### Run all performance tests:
```bash
php artisan test --group=performance
```

### Run load tests:
```bash
php artisan test --group=load
```

### Run stress tests:
```bash
php artisan test --group=stress
```

### Run a specific test suite:
```bash
php artisan test tests/Performance
php artisan test tests/Load
php artisan test tests/Stress
```

### Run real concurrent HTTP stress test (Artisan command):
```bash
# Basic usage (simulates 100 users, 5 requests each)
php artisan test:stress --users=100 --requests=5

# Test a specific endpoint
php artisan test:stress --users=50 --requests=10 --endpoint=/login --report

# Test with custom application URL
php artisan test:stress --users=500 --requests=3 --app-url=https://staging.toplincvote.edu

# Generate HTML report
php artisan test:stress --users=250 --requests=5 --report
```

## Test Scenarios

### Performance Tests (single-user benchmarks)
- **Homepage**: Measures login page load time (10 iterations)
- **Login**: Measures login page render and submission time (5 iterations)
- **Dashboard**: Measures student and admin dashboard load time (5 iterations)
- **Candidate Listing**: Measures candidate list page with 3 and 20 candidates
- **Voting Page**: Measures ballot page render and vote submission (5 iterations)
- **API Endpoints**: Measures force-logout and secure-logout endpoints
- **Database Queries**: Measures bulk reads, vote inserts, and complex joins

### Load Tests (simulated concurrency)
Simulates sequential requests for:
- 10, 50, 100, 250, 500, 1000 concurrent users
- Each user makes 3 requests
- Records: avg/min/max response time, error rate, success rate

### Stress Tests (pushing limits)
- **Gradual Load**: Increases from 5 to 50 users, identifies breaking point
- **Election Workflow**: Tests login → browse → vote cycle for 20 users
- **Database Stress**: Bulk inserts (100 votes), concurrent reads, lock contention

## Thresholds (configured in `config/performance.php`)

| Threshold | Default | Description |
|-----------|---------|-------------|
| Response Time | 2.0s | Max acceptable response time |
| Error Rate | 1.0% | Max acceptable error rate |
| Success Rate | 99.0% | Min acceptable success rate |
| Memory Limit | 256 MB | Per-request memory limit |
| CPU Limit | 90% | Max CPU usage |

## Interpreting Results

### Performance Report (JSON/HTML)
After running tests, reports are saved to `storage/app/performance-reports/`:
- `performance-report_<timestamp>.json` - Machine-readable
- `performance-report_<timestamp>.html` - Human-readable

### Key Metrics
- **Avg Response Time**: Average time to serve a request
- **P95 Response Time**: 95% of requests complete within this time
- **Error Rate**: Percentage of failed requests
- **Requests per Second**: Throughput metric

### Bottleneck Indicators
- High P95 but low avg → intermittent slow queries
- High error rate → application instability under load
- Increasing response times with more users → scalability issue
- Database query time dominance → need query optimization or caching

## Maximum Supported Concurrent Users

The system's capacity depends on your infrastructure. Based on the test results:

1. **With SQLite (testing)**: ~100-250 concurrent users before degradation
2. **With MySQL (production)**: Expected 500-1000+ concurrent users
3. **With MySQL + Redis caching**: Expected 2000-5000+ concurrent users

Run the stress tests on your production-like environment for accurate numbers.

## Recommended Server Specifications

For supporting 1000+ concurrent users during peak election voting:

### Minimum
- CPU: 4 vCPUs
- RAM: 8 GB
- Database: MySQL 8.0 with dedicated server
- PHP: 8.2+ with FPM (pm.max_children = 50+)
- Queue: Laravel Horizon with Redis

### Recommended
- CPU: 8+ vCPUs
- RAM: 16+ GB
- Database: MySQL 8.0 with query cache and proper indexing
- PHP: 8.2+ with FPM (pm.max_children = 100+)
- Cache: Redis for sessions, cache, and queues
- Web Server: Nginx with FastCGI cache

## CI/CD Integration

```bash
# In your CI pipeline:

# Lightweight - run on every commit
php artisan test --group=performance

# Heavier - run on staging deployment
php artisan test --group=load

# Full stress test - run before production releases
php artisan test --group=stress
php artisan test:stress --users=500 --report
```

## Optimizations Identified

Common bottlenecks found by these tests:
1. **N+1 queries**: Use eager loading (`with()`) for relationships
2. **Missing indexes**: Ensure foreign key columns are indexed
3. **Heavy Livewire components**: Defer non-critical data loading
4. **Session contention**: Use Redis/cookie sessions instead of file/database
5. **Queue processing**: Offload vote counting and result tabulation to queues
