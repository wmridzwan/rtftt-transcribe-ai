<?php

use App\Jobs\ProcessTranscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\artisan;

/*
 * P7-003: real-Redis queue integration. Proves dispatch → work → complete,
 * failure → failed_jobs visibility, and duplicate-delivery safety for real
 * job classes on the Redis driver. Skips when no Redis is reachable so CI
 * without Redis stays green; the P7-003 evidence run executes with Redis.
 */

function redisAvailableForQueueTest(): bool
{
    try {
        $pong = app('redis')->connection('default')->ping();

        return $pong === true || $pong === 'PONG';
    } catch (Throwable) {
        return false;
    }
}

function skipUnlessRedisForQueueTest(): void
{
    if (! redisAvailableForQueueTest()) {
        test()->markTestSkipped('Redis unreachable at 127.0.0.1:6379; P7-003 evidence requires Redis.');
    }
}

class RedisProbeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $marker) {}

    public function handle(): void
    {
        Cache::store('array')->forever('p7003-probe-'.$this->marker, 'done');
    }
}

class RedisFailingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $marker) {}

    public function handle(): void
    {
        throw new RuntimeException('P7-003 intentional failure '.$this->marker);
    }
}

beforeEach(function (): void {
    // Pin loopback-without-auth for the evidence run (TD-004 loopback
    // exception): local `.env` files may carry a REDIS_PASSWORD placeholder
    // that loopback Redis does not accept.
    config()->set('database.redis.default.host', '127.0.0.1');
    config()->set('database.redis.default.port', 6379);
    config()->set('database.redis.default.password', null);

    skipUnlessRedisForQueueTest();
    Queue::connection('redis')->clear('p7003-test');
});

it('dispatches, works, and completes a job on the Redis driver', function (): void {
    Queue::connection('redis')->push(new RedisProbeJob('complete'), queue: 'p7003-test');

    artisan('queue:work', [
        'connection' => 'redis',
        '--queue' => 'p7003-test',
        '--once' => true,
        '--tries' => 1,
    ])->assertSuccessful();

    expect(Cache::store('array')->get('p7003-probe-complete'))->toBe('done');
});

it('records a failing Redis job in failed_jobs with no silent loss', function (): void {
    Queue::connection('redis')->push(new RedisFailingJob('visible'), queue: 'p7003-test');

    artisan('queue:work', [
        'connection' => 'redis',
        '--queue' => 'p7003-test',
        '--once' => true,
        '--tries' => 1,
    ])->assertSuccessful();

    expect(DB::table('failed_jobs')->count())->toBeGreaterThanOrEqual(1);
});

it('delivers duplicate real job-class messages safely (fencing no-op)', function (): void {
    // Unknown identifiers exercise the skip path twice; neither delivery
    // may throw or change domain state.
    $job = new ProcessTranscription(transcriptionId: 999001, processingAttemptId: 999001);

    Queue::connection('redis')->push(clone $job, queue: 'p7003-test');
    Queue::connection('redis')->push(clone $job, queue: 'p7003-test');

    foreach ([1, 2] as $_) {
        artisan('queue:work', [
            'connection' => 'redis',
            '--queue' => 'p7003-test',
            '--once' => true,
            '--tries' => 1,
        ])->assertSuccessful();
    }

    expect(DB::table('failed_jobs')->count())->toBe(0);
});
