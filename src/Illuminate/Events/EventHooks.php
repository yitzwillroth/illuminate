<?php

namespace Illuminate\Events;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

trait EventHooks
{
    /**
     * The "before" event hook name.
     */
    protected const HOOK_BEFORE = 'before';

    /**
     * The "after" event hook name.
     */
    protected const HOOK_AFTER = 'after';

    /**
     * The "failure" event hook name.
     */
    protected const HOOK_FAILURE = 'failure';

    /**
     * The available event hooks.
     */
    protected const HOOKS = [self::HOOK_BEFORE, self::HOOK_AFTER, self::HOOK_FAILURE];

    /**
     * The wildcard event name.
     */
    protected const EVENT_WILDCARD = '*';

    /**
     * The registered event callbacks.
     */
    protected array $callbacks = [];

    /**
     * The IoC container instance.
     *
     * @var Container
     */
    protected $container;

    /**
     * Get registered event callbacks, optionally filtered by a hook and/or event.
     *
     * @throws InvalidArgumentException
     */
    public function callbacks(?string $hook = null, ?string $event = null): array
    {
        if (! is_null($hook)) {
            $this->validateHook($hook);
        }

        return match (true) {
            (! is_null($hook) && ! is_null($event)) => [
                static::EVENT_WILDCARD => $this->callbacks[$hook][static::EVENT_WILDCARD] ?? [],
                $event => $this->callbacks[$hook][$event] ?? [],
            ],
            (! is_null($hook) && is_null($event)) => $this->callbacks[$hook] ?? [],
            (is_null($hook) && ! is_null($event)) => [
                static::HOOK_BEFORE => [
                    static::EVENT_WILDCARD => $this->callbacks[static::HOOK_BEFORE][static::EVENT_WILDCARD] ?? [],
                    $event => $this->callbacks[static::HOOK_BEFORE][$event] ?? [],
                ],
                static::HOOK_AFTER => [
                    static::EVENT_WILDCARD => $this->callbacks[static::HOOK_AFTER][static::EVENT_WILDCARD] ?? [],
                    $event => $this->callbacks[static::HOOK_AFTER][$event] ?? [],
                ],
                static::HOOK_FAILURE => [
                    static::EVENT_WILDCARD => $this->callbacks[static::HOOK_FAILURE][static::EVENT_WILDCARD] ?? [],
                    $event => $this->callbacks[static::HOOK_FAILURE][$event] ?? [],
                ],
            ],
            default => $this->callbacks
        };
    }

    /**
     * Register callback(s) to be executed before event dispatch.
     *
     * @throws InvalidArgumentException
     */
    public function before(callable|string|array $callbacks, string|array|null $events = null): static
    {
        return tap($this, function () use ($callbacks, $events) {
            $this->registerCallbacks(static::HOOK_BEFORE, $callbacks, $events);
        });
    }

    /**
     * Register callback(s) to be executed after event dispatch.
     *
     * @throws InvalidArgumentException
     */
    public function after(callable|string|array $callbacks, string|array|null $events = null): static
    {
        return tap($this, function () use ($callbacks, $events) {
            $this->registerCallbacks(static::HOOK_AFTER, $callbacks, $events);
        });
    }

    /**
     * Register callback(s) to be executed if event dispatch fails.
     *
     * @throws InvalidArgumentException
     */
    public function failure(callable|string|array $callbacks, string|array|null $events = null): static
    {
        return tap($this, function () use ($callbacks, $events) {
            $this->registerCallbacks(static::HOOK_FAILURE, $callbacks, $events);
        });
    }

    /**
     * Register event hook callbacks with the dispatcher.
     *
     * @throws InvalidArgumentException
     */
    protected function registerCallbacks(string $hook, callable|string|array $callbacks, string|array|null $events = null): void
    {
        $this->validateHook($hook);

        is_null($events)
            ? $this->registerGlobalCallbacks($hook, $callbacks)
            : $this->registerEventCallbacks($hook, $callbacks, $events);
    }

    /**
     * Register global event hook callbacks with the dispatcher.
     *
     * @throws InvalidArgumentException
     */
    protected function registerGlobalCallbacks(string $hook, callable|string|array $callbacks): void
    {
        $this->validateHook($hook);

        foreach (Arr::wrap($callbacks) as $callback) {
            $this->registerCallback($hook, static::EVENT_WILDCARD, $callback);
        }
    }

    /**
     * Register event-specific event hook callbacks with the dispatcher.
     *
     * @throws InvalidArgumentException
     */
    protected function registerEventCallbacks(string $hook, callable|string|array $callbacks, string|array $events): void
    {
        foreach (Arr::wrap($events) as $event) {
            if (! is_string($event)) {
                throw new InvalidArgumentException('Event name must be a string, given: '.gettype($event));
            }

            foreach (Arr::wrap($callbacks) as $callback) {
                $this->registerCallback($hook, $event, $callback);
            }
        }
    }

    /**
     * Register an event hook callback.
     *
     * @throws InvalidArgumentException
     */
    protected function registerCallback(string $hook, string $event, callable|string $callback): void
    {
        $this->validateHook($hook);

        is_callable($callback) ?: $this->validateCallbackString($callback);

        $this->callbacks[$hook][$event][] = $callback;
    }

    /**
     * Invoke the registered event callbacks for a specific hook.
     *
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     */
    protected function invokeCallbacks(string $hook, string $event, array $payload): void
    {
        $this->validateHook($hook);

        foreach ($this->prepareCallbacks($hook, $event, $payload) as $callback) {
            $this->invokeCallback($callback, $payload);
        }
    }

    /**
     * Aggregates, formats, and orders callbacks for a specific hook and event.
     *
     * @throws InvalidArgumentException
     */
    protected function prepareCallbacks(string $hook, string $event, array $payload): array
    {
        $this->validateHook($hook);

        return $this->orderCallbacks($hook, $this->aggregateCallbacks($hook, $event, $payload));
    }

    /**
     * Aggregate callbacks for a specific hook and event, including wildcard callbacks.
     *
     * @throws InvalidArgumentException
     */
    protected function aggregateCallbacks(string $hook, string $event, array $payload): array
    {
        $this->validateHook($hook);

        // order: global registered, event registered, declared on the event object
        return array_merge(
            $this->callbacks[$hook][static::EVENT_WILDCARD] ?? [],
            $this->callbacks[$hook][$event] ?? [],
            count($callback = $this->prepareEventObjectCallback($hook, $payload)) ? [$callback] : [],
        );
    }

    /**
     * Prepare the event object callback.
     *
     * @throws InvalidArgumentException
     */
    protected function prepareEventObjectCallback(string $hook, array $payload): array
    {
        return (! empty($payload) && is_object($event = Arr::first($payload)) && method_exists($event, $hook))
            ? [$event, $hook]
            : [];
    }

    /**
     * Order the callbacks based on the hook type.
     *
     * @throws InvalidArgumentException
     */
    protected function orderCallbacks(string $hook, array $callbacks): array
    {
        $this->validateHook($hook);

        // first in, last out ordering
        return in_array($hook, [static::HOOK_AFTER, static::HOOK_FAILURE], true)
            ? array_reverse($callbacks)
            : $callbacks;
    }

    /**
     * Invoke the given callback with the provided payload.
     *
     * @throws BindingResolutionException
     */
    protected function invokeCallback(callable|string $callback, array $payload): void
    {
        is_callable($callback) ?: $this->validateCallbackString($callback);

        // order of the following match expression is integral as we want to make callables that
        // are neither closure nor functions via the container to auto-inject their dependencies

        match (true) {

            // [$object,'method'] -- call the method
            is_array($callback)
                && is_object($object = Arr::first($callback))
                && is_string($method = Arr::last($callback))
                && method_exists($object, $method) =>
                    $object->{$method}(...$payload),

            // ['class', 'method'] -- make the class, call the specified method
            is_array($callback)
                && is_string($class = Arr::first($callback))
                && is_string($method = Arr::last($callback))
                && class_exists($class)
                && method_exists($class, $method) =>
                    $this->getContainer()->make($class)->{$method}(...$payload),

            // classname w/ handle() method -- make the class, call the handle method
            is_string($callback)
                && class_exists($callback)
                && method_exists($callback, 'handle') =>
                    $this->getContainer()->make($callback)->handle(...$payload),

            // classname, invokable -- make and invoke the class
            is_string($callback)
                && class_exists($callback)
                && method_exists($callback, '__invoke') =>
                    $this->getContainer()->make($callback)(...$payload),

            // class@method -- make the class, call the specified method
            is_string($callback)
                && str_contains($callback, '@')
                && class_exists($class = ($parts = Str::of($callback)->explode('@'))->first())
                && method_exists($class, $method = $parts->last()) =>
                    $this->getContainer()->make($class)->{$method}(...$payload),

            // class::method -- make the class, call the specified method
            is_string($callback)
                && str_contains($callback, '::')
                && class_exists($class = ($parts = Str::of($callback)->explode('::'))->first())
                && method_exists($class, $method = $parts->last()) =>
                    $this->getContainer()->make($class)->{$method}(...$payload),

            // closure or function -- invoke the closure or function
            is_callable($callback) => $callback(...$payload),

            default => throw new InvalidArgumentException('Invalid callback provided.'),
        };
    }

    /**
     * Validate the given hook name.
     *
     * @throws InvalidArgumentException
     */
    public function validateHook(string $hook): void
    {
        if (! in_array($hook, static::HOOKS, true)) {
            throw new InvalidArgumentException("Invalid hook: {$hook}");
        }
    }

    /**
     * Validate the given callback.
     *
     * @throws InvalidArgumentException
     */
    protected function validateCallbackString(string $callback): void
    {
        if ((! class_exists(($parts = Str::of($callback)->replace('::', '@')->explode('@'))->last()))
                && ($parts->count() !== 2 || ! class_exists($parts->first()) || ! method_exists($parts, $parts->last()))) {
            throw new InvalidArgumentException('Invalid callback provided.');
        }
    }

    /**
     * Get the container instance.
     **/
    protected function getContainer(): Container
    {
        return $this->container;
    }
}
