<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Console\Commands;

use EaseAppPHP\HighPer\Framework\Benchmark\BenchmarkTool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BenchmarkCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this
            ->setName('benchmark')
            ->setDescription('Run benchmarks to compare framework performance')
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_REQUIRED,
                'Path to the benchmark configuration file',
                'benchmark.php'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Path to save the benchmark report',
                'benchmark-report.html'
            );
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = $input->getOption('config');
        $reportPath = $input->getOption('output');
        
        // Check if the configuration file exists
        if (!file_exists($configPath)) {
            $output->writeln("<error>Configuration file not found: {$configPath}</error>");
            return Command::FAILURE;
        }
        
        // Load the configuration
        $config = require $configPath;
        
        // Check if the configuration is valid
        if (!is_array($config) || !isset($config['frameworks']) || empty($config['frameworks'])) {
            $output->writeln("<error>Invalid benchmark configuration</error>");
            return Command::FAILURE;
        }
        
        $output->writeln("<info>Running benchmarks...</info>");
        
        // Create the benchmark tool
        $benchmarkTool = new BenchmarkTool($config, $output);
        
        try {
            // Run the benchmarks
            $results = $benchmarkTool->run();
            
            // Generate the report
            $benchmarkTool->generateReport($reportPath);
            
            $output->writeln("<info>Benchmark completed successfully!</info>");
            $output->writeln("<info>Report saved to: {$reportPath}</info>");
            
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln("<error>Error running benchmarks: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
}
