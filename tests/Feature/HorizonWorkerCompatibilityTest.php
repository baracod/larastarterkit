<?php

namespace Tests\Feature;

use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Laravel\Horizon\Console\WorkCommand;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class HorizonWorkerCompatibilityTest extends TestCase
{
    #[DataProvider('idleTimeouts')]
    public function test_horizon_can_start_processing_with_laravel_worker_options(?int $seconds): void
    {
        $worker = Mockery::mock(Worker::class);
        $worker->shouldReceive('setName')->once()->with('default')->andReturnSelf();
        $worker->shouldReceive('setCache')->once()->andReturnSelf();
        $worker->shouldReceive('runNextJob')->once()->with(
            'redis',
            'compatibility-test',
            Mockery::on(fn (WorkerOptions $options) => (int) $options->stopWhenEmptyFor === ($seconds ?? 0)),
        );
        $this->app->instance('queue.worker', $worker);
        $command = $this->app->make(WorkCommand::class);
        $command->setLaravel($this->app);
        $arguments = ['connection' => 'redis', '--queue' => 'compatibility-test', '--once' => true];
        if ($seconds !== null) {
            $arguments['--stop-when-empty-for'] = $seconds;
        }

        $this->assertSame(0, $command->run(new ArrayInput($arguments), new BufferedOutput));
    }

    public static function idleTimeouts(): array
    {
        return ['default' => [null], 'explicit' => [5]];
    }
}
