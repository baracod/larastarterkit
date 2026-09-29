<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ProductionNetworkConfigurationTest extends TestCase
{
    public function test_production_requires_secure_configuration(): void
    {
        $this->assertTrue($this->check([])->isSuccessful());
        foreach ([['APP_DEBUG' => 'true'], ['APP_URL' => 'http://example.org'], ['SESSION_SECURE_COOKIE' => 'false'], ['QUEUE_CONNECTION' => 'sync'], ['REDIS_QUEUE_RETRY_AFTER' => '60']] as $invalid) {
            $this->assertFalse($this->check($invalid)->isSuccessful());
        }
    }

    private function check(array $overrides): Process
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/docker/prod/check-environment.php'], null, array_merge([
            'APP_KEY' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://example.org',
            'DB_PASSWORD' => 'test-password',
            'REDIS_PASSWORD' => 'test-password',
            'QUEUE_CONNECTION' => 'redis',
            'CACHE_STORE' => 'redis',
            'SESSION_DRIVER' => 'redis',
            'SESSION_SECURE_COOKIE' => 'true',
            'REDIS_QUEUE_RETRY_AFTER' => '360',
        ], $overrides));
        $process->run();

        return $process;
    }
}
