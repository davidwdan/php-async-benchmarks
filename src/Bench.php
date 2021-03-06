<?php

namespace app;

use Amp\Loop;
use app\clients\Amphp;
use app\clients\Guzzle;
use app\clients\Reactphp;
use app\clients\Rxphp;
use BadMethodCallException;
use function rmdir;
use function unlink;
use const PHP_EOL;

class Bench
{
    private const URL_PATH = __DIR__ . '/urls.csv';
    private const TEMP_DIR = __DIR__ . '/temp/';

    private string $clientName;

    private int $concurrency;

    private int $batchSize;

    private int $iterations;

    private string $tempDir;

    private $startTime;
    private $finishTime;
    private array $deltas = [];

    public function __construct(string $clientName, int $iterations, int $concurrency, int $batchSize)
    {

        $this->clientName = $clientName;
        $this->concurrency = $concurrency;
        $this->batchSize = $batchSize;
        $this->iterations = $iterations;
        $this->tempDir = self::TEMP_DIR . $this->clientName;
    }

    public function run()
    {
        for ($i = 0; $i < $this->iterations; $i++) {
            $this->clearTempData();
            $start = microtime(true);
            $this->runClient();
            $delta = microtime(true) - $start;
            echo $i. ' - '. $delta.PHP_EOL;
            $this->deltas[] = $delta;
        }
        $this->printResult();
    }

    private function runClient()
    {
        switch ($this->clientName){
            case 'amp':
                Loop::run(
                    fn() => (new Amphp($this->concurrency, $this->batchSize, self::URL_PATH, $this->tempDir))->run()
                );
                break;
            case 'react':
                $loop = \React\EventLoop\Factory::create();
                $client = new Reactphp($this->concurrency, $this->batchSize, self::URL_PATH, $this->tempDir, $loop);
                $client->run();
                $loop->run();
                $loop->stop();
                unset($client, $loop);
                break;
            case 'rx':
                $client = new Rxphp($this->concurrency, $this->batchSize, self::URL_PATH, $this->tempDir);
                $client->run();
                unset($client);
                break;
            case 'guzzle':
                $client = new Guzzle($this->concurrency, $this->batchSize, self::URL_PATH, $this->tempDir);
                $client->run();
                unset($client);
                break;
            default:
                throw new BadMethodCallException('Not supported client');
        }
    }

    private function prepareTempDir()
    {
        if (\file_exists($this->tempDir)) {
            rmdir($this->tempDir);
        }
        mkdir($this->tempDir);
    }

    private function clearTempData()
    {
        @unlink($this->tempDir . '/ok.txt');
        @unlink($this->tempDir . '/bad.txt');
        $this->prepareTempDir();
    }

    private function printResult()
    {
        $min = round(min($this->deltas), 4);
        $max = round(max($this->deltas), 4);
        $avg = round(array_sum($this->deltas)/$this->iterations, 4);
        echo "Batch size: {$this->batchSize}, Iterations: {$this->iterations}".PHP_EOL;
        echo \str_repeat('-', 25).PHP_EOL;
        echo "| {$this->clientName}| {$min} | {$max} | $avg |".PHP_EOL;
    }
}
