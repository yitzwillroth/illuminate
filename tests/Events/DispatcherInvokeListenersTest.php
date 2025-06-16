<?php

namespace Illuminate\Tests\Events;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\TestCase;

class DispatcherInvokeListenersTest extends TestCase
{
    protected $dispatcher;

    protected $callbackOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = new Dispatcher(new Container);
        $this->callbackOrder = [];
    }

    public function test_before_callbacks_are_called_before_listeners()
    {
        // Register a before callback
        $this->dispatcher->before(function () {
            $this->callbackOrder[] = 'before';
        }, 'foo');

        // Register a listener
        $this->dispatcher->listen('foo', function () {
            $this->callbackOrder[] = 'listener';
        });

        // Dispatch the event
        $this->dispatcher->dispatch('foo');

        // Assert that the before callback was called before the listener
        $this->assertEquals(['before', 'listener'], $this->callbackOrder);
    }

    public function test_after_callbacks_are_called_after_listeners()
    {
        // Register a listener
        $this->dispatcher->listen('foo', function () {
            $this->callbackOrder[] = 'listener';
        });

        // Register an after callback
        $this->dispatcher->after(function () {
            $this->callbackOrder[] = 'after';
        }, 'foo');

        // Dispatch the event
        $this->dispatcher->dispatch('foo');

        // Assert that the after callback was called after the listener
        $this->assertEquals(['listener', 'after'], $this->callbackOrder);
    }

    public function test_failure_callbacks_are_called_when_listener_returns_false()
    {
        // Register a listener that returns false
        $this->dispatcher->listen('foo', function () {
            $this->callbackOrder[] = 'listener_failure';

            return false;
        });

        // Register a listener that should not be called
        $this->dispatcher->listen('foo', function () {
            $this->callbackOrder[] = 'listener_after_failure';
        });

        // Register a failure callback
        $this->dispatcher->failure(function () {
            $this->callbackOrder[] = 'failure';
        }, 'foo');

        // Register an after callback
        $this->dispatcher->after(function () {
            $this->callbackOrder[] = 'after';
        }, 'foo');

        // Dispatch the event
        $this->dispatcher->dispatch('foo');

        // Assert that the failure callback was called and the after callback was not
        $this->assertEquals(['listener_failure', 'failure'], $this->callbackOrder);
        $this->assertNotContains('after', $this->callbackOrder);
        $this->assertNotContains('listener_after_failure', $this->callbackOrder);
    }

    public function test_multiple_listeners_with_before_and_after_callbacks()
    {
        // Register before callbacks
        $this->dispatcher->before(function () {
            $this->callbackOrder[] = 'before1';
        }, 'foo');
        $this->dispatcher->before(function () {
            $this->callbackOrder[] = 'before2';
        }, 'foo');

        // Register listeners
        $this->dispatcher->listen('foo', function () {
            $this->callbackOrder[] = 'listener1';
        });
        $this->dispatcher->listen('foo', function () {
            $this->callbackOrder[] = 'listener2';
        });

        // Register after callbacks
        $this->dispatcher->after(function () {
            $this->callbackOrder[] = 'after1';
        }, 'foo');
        $this->dispatcher->after(function () {
            $this->callbackOrder[] = 'after2';
        }, 'foo');

        // Dispatch the event
        $this->dispatcher->dispatch('foo');

        // Assert the callback order
        $this->assertEquals(
            ['before1', 'before2', 'listener1', 'listener2', 'after2', 'after1'],
            $this->callbackOrder
        );
    }
}
