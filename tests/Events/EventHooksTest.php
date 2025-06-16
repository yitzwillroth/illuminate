<?php

namespace Illuminate\Tests\Events;

use Illuminate\Events\EventHooks;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EventHooksTest extends TestCase
{
    use EventHooks;

    private const EVENT = 'event';

    private const INVALID_HOOK = 'invalid';

    private const VALID_CALLBACK = 'callback';

    private const INVALID_CALLBACK = 'invalid';

    public function test_register_before_callback(): void
    {
        $this->before(fn () => static::VALID_CALLBACK, static::EVENT);

        $callbacks = $this->callbacks(static::HOOK_BEFORE, static::EVENT);

        $this->assertCount(1, $callbacks[static::EVENT]);
        $this->assertIsCallable(Arr::first($callbacks[static::EVENT]));
    }

    public function test_register_after_callback(): void
    {
        $this->after(fn () => static::VALID_CALLBACK, static::EVENT);

        $callbacks = $this->callbacks(static::HOOK_AFTER, static::EVENT);

        $this->assertCount(1, $callbacks[static::EVENT]);
        $this->assertIsCallable(Arr::first($callbacks[static::EVENT]));
    }

    public function test_register_failure_callback(): void
    {
        $this->failure(fn () => static::VALID_CALLBACK, static::EVENT);

        $callbacks = $this->callbacks(static::HOOK_FAILURE, static::EVENT);

        $this->assertCount(1, $callbacks[static::EVENT]);
        $this->assertIsCallable(Arr::first($callbacks[static::EVENT]));
    }

    public function test_invalid_hook_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->callbacks(static::INVALID_HOOK);
    }

    public function test_invalid_callback_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->before(static::INVALID_CALLBACK, static::EVENT);
    }

    public function test_wildcard_callbacks(): void
    {
        $this->before(fn () => static::VALID_CALLBACK, static::EVENT_WILDCARD);

        $callbacks = $this->callbacks(static::HOOK_BEFORE);

        $this->assertCount(1, $callbacks[static::EVENT_WILDCARD]);
        $this->assertIsCallable(Arr::first($callbacks[static::EVENT_WILDCARD]));
    }
}
