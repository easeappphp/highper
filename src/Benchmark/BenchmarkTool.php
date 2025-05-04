<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Benchmark;

use Symfony\Component\Process\Process;
use Symfony\Component\Console\Output\OutputInterface;

class BenchmarkTool
{
    /**
     * @var array The benchmark configuration
     */
    protected array $config;
    
    /**
     * @var OutputInterface|null The output interface
     */
    protected ?OutputInterface $output;
    
    /**
     * @var array The benchmark results
     */
    protected array $results = [];

    /**
     * Create a new benchmark tool
     *
     * @param array $config
     * @param OutputInterface|null $output
     */
    public function __construct(array $config, ?OutputInterface $output = null)
    {
        $this->config = $config;
        $this->output = $output;
    }

    /**
     * Run the benchmarks
     *
     * @return array
     */
    public function run(): array
    {
        $this->log('Starting benchmarks...');
        
        // Loop through each framework to benchmark
        foreach ($this->config['frameworks'] as $name => $framework) {
            $this->log("Benchmarking {$name}...");
            
            // Run the benchmark for this framework
            $result = $this->benchmarkFramework($name, $framework);
            
            // Store the results
            $this->results[$name] = $result;
            
            $this->log("Completed benchmark for {$name}.");
            $this->log("Results: {$result['requests_per_second']} req/sec, {$result['avg_latency']} ms avg latency");
        }
        
        // Calculate comparative results
        $this->calculateComparativeResults();
        
        $this->log('Benchmark completed.');
        
        return $this->results;
    }

    /**
     * Benchmark a specific framework
     *
     * @param string $name
     * @param array $framework
     * @return array
     */
    protected function benchmarkFramework(string $name, array $framework): array
    {
        // Get benchmark settings
        $url = $framework['url'] ?? 'http://localhost:8080';
        $concurrency = $this->config['concurrency'] ?? 100;
        $duration = $this->config['duration'] ?? 30;
        $warmup = $this->config['warmup'] ?? 5;
        
        // Start the server if needed
        $serverProcess = null;
        
        if (isset($framework['server_command'])) {
            $this->log("Starting server for {$name}...");
            
            $serverProcess = Process::fromShellCommandline($framework['server_command']);
            $serverProcess->start();
            
            // Wait for the server to start
            sleep(2);
        }
        
        try {
            // Warm up
            $this->log("Warming up {$name}...");
            $this->runWrkBenchmark($url, $concurrency, $warmup);
            
            // Run the actual benchmark
            $this->log("Running benchmark for {$name}...");
            $output = $this->runWrkBenchmark($url, $concurrency, $duration);
            
            // Parse the results
            $result = $this->parseWrkOutput($output);
            
            // Add memory usage if available
            if (isset($framework['memory_command'])) {
                $memoryProcess = Process::fromShellCommandline($framework['memory_command']);
                $memoryProcess->run();
                
                $result['memory_usage'] = trim($memoryProcess->getOutput());
            }
            
            return $result;
        } finally {
            // Stop the server if we started it
            if ($serverProcess !== null) {
                $this->log("Stopping server for {$name}...");
                $serverProcess->stop();
            }
        }
    }

    /**
     * Run wrk benchmark
     *
     * @param string $url
     * @param int $concurrency
     * @param int $duration
     * @return string
     */
    protected function runWrkBenchmark(string $url, int $concurrency, int $duration): string
    {
        $threads = min($concurrency, 8); // Maximum 8 threads
        
        $process = Process::fromShellCommandline(
            "wrk -t{$threads} -c{$concurrency} -d{$duration}s --latency {$url}"
        );
        
        $process->run();
        
        return $process->getOutput();
    }

    /**
     * Parse wrk output
     *
     * @param string $output
     * @return array
     */
    protected function parseWrkOutput(string $output): array
    {
        $result = [
            'requests_per_second' => 0,
            'total_requests' => 0,
            'avg_latency' => 0,
            'max_latency' => 0,
            'transfer_rate' => 0,
        ];
        
        // Parse requests per second
        if (preg_match('/Requests\/sec:\s+(\d+\.\d+)/', $output, $matches)) {
            $result['requests_per_second'] = (float) $matches[1];
        }
        
        // Parse total requests
        if (preg_match('/(\d+)\s+requests in/', $output, $matches)) {
            $result['total_requests'] = (int) $matches[1];
        }
        
        // Parse latency
        if (preg_match('/Latency\s+(\d+\.\d+\w+)\s+(\d+\.\d+\w+)\s+(\d+\.\d+\w+)/', $output, $matches)) {
            $result['avg_latency'] = $this->parseTime($matches[1]);
            $result['max_latency'] = $this->parseTime($matches[3]);
        }
        
        // Parse transfer rate
        if (preg_match('/Transfer\/sec:\s+(\d+\.\d+\w+)/', $output, $matches)) {
            $result['transfer_rate'] = $matches[1];
        }
        
        return $result;
    }

    /**
     * Parse time string to milliseconds
     *
     * @param string $time
     * @return float
     */
    protected function parseTime(string $time): float
    {
        $value = (float) $time;
        
        if (str_contains($time, 'us')) {
            return $value / 1000; // Convert microseconds to milliseconds
        } elseif (str_contains($time, 's')) {
            return $value * 1000; // Convert seconds to milliseconds
        }
        
        return $value; // Already in milliseconds
    }

    /**
     * Calculate comparative results
     *
     * @return void
     */
    protected function calculateComparativeResults(): void
    {
        if (count($this->results) <= 1) {
            return;
        }
        
        // Find the fastest framework (highest requests per second)
        $fastest = '';
        $highestRps = 0;
        
        foreach ($this->results as $name => $result) {
            if ($result['requests_per_second'] > $highestRps) {
                $highestRps = $result['requests_per_second'];
                $fastest = $name;
            }
        }
        
        // Calculate relative performance
        foreach ($this->results as $name => $result) {
            if ($name !== $fastest) {
                $relativePerformance = ($result['requests_per_second'] / $highestRps) * 100;
                $this->results[$name]['relative_performance'] = round($relativePerformance, 2);
            } else {
                $this->results[$name]['relative_performance'] = 100;
            }
        }
        
        // Add a summary
        $this->results['summary'] = [
            'fastest_framework' => $fastest,
            'highest_rps' => $highestRps,
        ];
    }

    /**
     * Generate an HTML report
     *
     * @param string $outputPath
     * @return void
     */
    public function generateReport(string $outputPath): void
    {
        $this->log('Generating benchmark report...');
        
        // Create the HTML
        $html = $this->createReportHtml();
        
        // Write to file
        file_put_contents($outputPath, $html);
        
        $this->log("Report saved to {$outputPath}");
    }

    /**
     * Create the HTML report
     *
     * @return string
     */
    protected function createReportHtml(): string
    {
        $title = $this->config['title'] ?? 'PHP Framework Benchmark';
        $date = date('Y-m-d H:i:s');
        
        $html = "<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>{$title}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        tr:hover { background-color: #f5f5f5; }
        .chart { width: 100%; height: 400px; margin-bottom: 20px; }
        .summary { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
    <script src=\"https://cdn.jsdelivr.net/npm/chart.js\"></script>
</head>
<body>
    <h1>{$title}</h1>
    <p>Generated on {$date}</p>
    
    <div class=\"summary\">
        <h2>Summary</h2>";
        
        if (isset($this->results['summary'])) {
            $fastest = $this->results['summary']['fastest_framework'];
            $highestRps = number_format($this->results['summary']['highest_rps'], 2);
            
            $html .= "
        <p><strong>Fastest Framework:</strong> {$fastest}</p>
        <p><strong>Highest Requests/sec:</strong> {$highestRps}</p>";
        }
        
        $html .= "
    </div>
    
    <h2>Requests Per Second</h2>
    <div class=\"chart\">
        <canvas id=\"rpsChart\"></canvas>
    </div>
    
    <h2>Average Latency</h2>
    <div class=\"chart\">
        <canvas id=\"latencyChart\"></canvas>
    </div>
    
    <h2>Detailed Results</h2>
    <table>
        <tr>
            <th>Framework</th>
            <th>Requests/sec</th>
            <th>Avg Latency (ms)</th>
            <th>Max Latency (ms)</th>
            <th>Total Requests</th>
            <th>Transfer Rate</th>
            <th>Memory Usage</th>
            <th>Relative Performance</th>
        </tr>";
        
        foreach ($this->results as $name => $result) {
            if ($name === 'summary') {
                continue;
            }
            
            $rps = number_format($result['requests_per_second'], 2);
            $avgLatency = number_format($result['avg_latency'], 2);
            $maxLatency = number_format($result['max_latency'], 2);
            $totalRequests = number_format($result['total_requests']);
            $transferRate = $result['transfer_rate'] ?? 'N/A';
            $memoryUsage = $result['memory_usage'] ?? 'N/A';
            $relativePerformance = $result['relative_performance'] ?? 'N/A';
            
            if ($relativePerformance !== 'N/A') {
                $relativePerformance = number_format($relativePerformance, 2) . '%';
            }
            
            $html .= "
        <tr>
            <td>{$name}</td>
            <td>{$rps}</td>
            <td>{$avgLatency}</td>
            <td>{$maxLatency}</td>
            <td>{$totalRequests}</td>
            <td>{$transferRate}</td>
            <td>{$memoryUsage}</td>
            <td>{$relativePerformance}</td>
        </tr>";
        }
        
        $html .= "
    </table>
    
    <script>
        // Prepare data for charts
        const frameworks = " . json_encode(array_keys(array_filter($this->results, function($key) {
            return $key !== 'summary';
        }, ARRAY_FILTER_USE_KEY))) . ";
        
        const rpsData = " . json_encode(array_map(function($result) {
            return $result['requests_per_second'];
        }, array_filter($this->results, function($key) {
            return $key !== 'summary';
        }, ARRAY_FILTER_USE_KEY))) . ";
        
        const latencyData = " . json_encode(array_map(function($result) {
            return $result['avg_latency'];
        }, array_filter($this->results, function($key) {
            return $key !== 'summary';
        }, ARRAY_FILTER_USE_KEY))) . ";
        
        // Create RPS chart
        const rpsChart = new Chart(document.getElementById('rpsChart'), {
            type: 'bar',
            data: {
                labels: frameworks,
                datasets: [{
                    label: 'Requests Per Second',
                    data: rpsData,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Create latency chart
        const latencyChart = new Chart(document.getElementById('latencyChart'), {
            type: 'bar',
            data: {
                labels: frameworks,
                datasets: [{
                    label: 'Average Latency (ms)',
                    data: latencyData,
                    backgroundColor: 'rgba(255, 99, 132, 0.6)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>";
        
        return $html;
    }

    /**
     * Log a message
     *
     * @param string $message
     * @return void
     */
    protected function log(string $message): void
    {
        if ($this->output !== null) {
            $this->output->writeln($message);
        }
    }
}
            