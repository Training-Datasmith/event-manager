<?php

declare(strict_types=1);

namespace Doctrine\Tests\Common;

use function array_keys;

use Doctrine\Common\Event_Args;
use Doctrine\Common\Event_Manager;
use Doctrine\Common\Event_Subscriber;
use PHPUnit\Framework\TestCase;

use ReflectionProperty;

class Event_Manager_Test extends TestCase
{
    /* Some pseudo events */
    private const PRE_FOO  = 'preFoo';
    private const POST_FOO = 'postFoo';
    private const PRE_BAR  = 'preBar';

    private bool $preFooInvoked  = false;
    private bool $postFooInvoked = false;
    private Event_Manager $event_manager;

    protected function setUp(): void
    {
        $this->event_manager   = new Event_Manager();
        $this->preFooInvoked  = false;
        $this->postFooInvoked = false;
    }

    public function testInitialState(): void
    {
        self::assertEquals([], $this->event_manager->get_all_listeners());
        self::assertFalse($this->event_manager->has_listeners(self::PRE_FOO));
        self::assertFalse($this->event_manager->has_listeners(self::POST_FOO));
    }

    public function testAddEventListener(): void
    {
        $this->event_manager->add_event_listener(['preFoo', 'postFoo'], $this);
        self::assertTrue($this->event_manager->has_listeners(self::PRE_FOO));
        self::assertTrue($this->event_manager->has_listeners(self::POST_FOO));
        self::assertCount(1, $this->event_manager->get_listeners(self::PRE_FOO));
        self::assertCount(1, $this->event_manager->get_listeners(self::POST_FOO));
        self::assertCount(2, $this->event_manager->get_all_listeners());
        self::assertSame(['preFoo', 'postFoo'], array_keys($this->event_manager->get_all_listeners()));
    }

    public function testDispatchEvent(): void
    {
        $this->event_manager->add_event_listener(['preFoo', 'postFoo'], $this);
        $this->event_manager->dispatch_event(self::PRE_FOO);
        self::assertTrue($this->preFooInvoked);
        self::assertFalse($this->postFooInvoked);
    }

    public function testRemoveEventListener(): void
    {
        $this->event_manager->add_event_listener(['preBar'], $this);
        self::assertTrue($this->event_manager->has_listeners(self::PRE_BAR));
        $this->event_manager->remove_event_listener(['preBar'], $this);
        self::assertFalse($this->event_manager->has_listeners(self::PRE_BAR));
    }

    public function testAddEventSubscriber(): void
    {
        $event_subscriber = new Test_Event_Subscriber();
        $this->event_manager->add_event_subscriber($event_subscriber);
        self::assertTrue($this->event_manager->has_listeners(self::PRE_FOO));
        self::assertTrue($this->event_manager->has_listeners(self::POST_FOO));
    }

    public function testRemoveEventSubscriber(): void
    {
        $event_subscriber = new Test_Event_Subscriber();
        $this->event_manager->add_event_subscriber($event_subscriber);
        $this->event_manager->remove_event_subscriber($event_subscriber);
        self::assertFalse($this->event_manager->has_listeners(self::PRE_FOO));
        self::assertFalse($this->event_manager->has_listeners(self::POST_FOO));
    }

    public function testNoDispatchingForUnregisteredEvent(): void
    {
        $reflection = new ReflectionProperty(Event_Args::class, 'empty_event_args_instance');
        $reflection->setValue(null, null);

        $this->event_manager->dispatch_event('unknown');

        self::assertNull($reflection->getValue(null));
    }

    public function testEmptyListenersListForUnregisteredEvent(): void
    {
        self::assertSame([], $this->event_manager->get_listeners('unknown'));
    }

    /* Listener methods */

    public function preFoo(Event_Args $e): void
    {
        $this->preFooInvoked = true;
    }

    public function postFoo(Event_Args $e): void
    {
        $this->postFooInvoked = true;
    }
}

class Test_Event_Subscriber implements Event_Subscriber
{
    /** @return string[] */
    public function get_subscribed_events(): array
    {
        return ['preFoo', 'postFoo'];
    }
}
