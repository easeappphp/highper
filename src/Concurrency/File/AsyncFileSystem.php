<?php

declare(strict_types=1);

namespace EaseAppPHP\HighPer\Framework\Concurrency\File;

use Amp\ByteStream\ReadableFile;
use Amp\ByteStream\WritableFile;
use Amp\Future;
use Amp\NullCancellation;
use Amp\Parallel\Worker\Task;
use EaseAppPHP\HighPer\Framework\Concurrency\Pool\WorkerPool;
use Psr\Log\LoggerInterface;

class AsyncFileSystem
{
    /**
     * Create a new async file system
     *
     * @param WorkerPool $workerPool
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected WorkerPool $workerPool,
        protected LoggerInterface $logger
    ) {
    }

    /**
     * Read a file asynchronously
     *
     * @param string $path
     * @return Future<string>
     */
    public function read(string $path): Future
    {
        $this->logger->debug('Reading file asynchronously', [
            'path' => $path,
        ]);
        
        return Future::async(function () use ($path) {
            if (!file_exists($path)) {
                throw new \RuntimeException("File {$path} not found");
            }
            
            $file = new ReadableFile($path);
            $contents = $file->buffer();
            $file->close();
            
            return $contents;
        });
    }

    /**
     * Write to a file asynchronously
     *
     * @param string $path
     * @param string $contents
     * @return Future<bool>
     */
    public function write(string $path, string $contents): Future
    {
        $this->logger->debug('Writing file asynchronously', [
            'path' => $path,
            'size' => strlen($contents),
        ]);
        
        return Future::async(function () use ($path, $contents) {
            $directory = dirname($path);
            
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0755, true)) {
                    throw new \RuntimeException("Failed to create directory {$directory}");
                }
            }
            
            $file = new WritableFile($path);
            $file->write($contents);
            $file->close();
            
            return true;
        });
    }

    /**
     * Append to a file asynchronously
     *
     * @param string $path
     * @param string $contents
     * @return Future<bool>
     */
    public function append(string $path, string $contents): Future
    {
        $this->logger->debug('Appending to file asynchronously', [
            'path' => $path,
            'size' => strlen($contents),
        ]);
        
        return Future::async(function () use ($path, $contents) {
            $file = new WritableFile($path, 'a');
            $file->write($contents);
            $file->close();
            
            return true;
        });
    }

    /**
     * Delete a file asynchronously
     *
     * @param string $path
     * @return Future<bool>
     */
    public function delete(string $path): Future
    {
        $this->logger->debug('Deleting file asynchronously', [
            'path' => $path,
        ]);
        
        return $this->workerPool->submit(new class($path) implements Task {
            public function __construct(private string $path) {}
            
            public function run(): bool
            {
                if (!file_exists($this->path)) {
                    return false;
                }
                
                return unlink($this->path);
            }
        });
    }

    /**
     * Check if a file exists asynchronously
     *
     * @param string $path
     * @return Future<bool>
     */
    public function exists(string $path): Future
    {
        return $this->workerPool->submit(new class($path) implements Task {
            public function __construct(private string $path) {}
            
            public function run(): bool
            {
                return file_exists($this->path);
            }
        });
    }

    /**
     * Get file information asynchronously
     *
     * @param string $path
     * @return Future<array>
     */
    public function stat(string $path): Future
    {
        return $this->workerPool->submit(new class($path) implements Task {
            public function __construct(private string $path) {}
            
            public function run(): array
            {
                if (!file_exists($this->path)) {
                    throw new \RuntimeException("File {$this->path} not found");
                }
                
                $stat = stat($this->path);
                
                return [
                    'size' => $stat['size'],
                    'atime' => $stat['atime'],
                    'mtime' => $stat['mtime'],
                    'ctime' => $stat['ctime'],
                    'type' => filetype($this->path),
                    'readable' => is_readable($this->path),
                    'writable' => is_writable($this->path),
                ];
            }
        });
    }

    /**
     * List directory contents asynchronously
     *
     * @param string $path
     * @return Future<array>
     */
    public function list(string $path): Future
    {
        return $this->workerPool->submit(new class($path) implements Task {
            public function __construct(private string $path) {}
            
            public function run(): array
            {
                if (!is_dir($this->path)) {
                    throw new \RuntimeException("Directory {$this->path} not found");
                }
                
                $files = scandir($this->path);
                
                // Remove . and ..
                $files = array_diff($files, ['.', '..']);
                
                return array_values($files);
            }
        });
    }
}
