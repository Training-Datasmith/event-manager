<?php

declare (strict_types=1);
namespace Doctrine\Common;

use function spl_object_id;
/**
 * The EventManager is the central point of Doctrine's event listener system.
 * Listeners are registered on the manager and events are dispatched through the
 * manager.
 */
class Event_Manager implements Event_Manager_Interface
{
    /**
     * Map of registered listeners.
     * <event> => <listeners>
     *
     * @var array<string, object[]>
     */
    private array $listeners = [];
    /** {@inheritDoc} */
    public function dispatch_event(string $event_name, Event_Args|null $event_args = null): void
    {
        if (!isset($this->listeners[$event_name])) {
            return;
        }
        $event_args ??= Event_Args::get_empty_instance();
        foreach ($this->listeners[$event_name] as $listener) {
            $listener->{$event_name}($event_args);
        }
    }
    /** {@inheritDoc} */
    public function get_listeners(string $event): array
    {
        return $this->listeners[$event] ?? [];
    }
    /** {@inheritDoc} */
    public function get_all_listeners(): array
    {
        return $this->listeners;
    }
    /** {@inheritDoc} */
    public function has_listeners(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }
    /** {@inheritDoc} */
    public function add_event_listener(string|array $events, object $listener): void
    {
        // Picks the hash code related to that listener
        $oid = spl_object_id($listener);
        foreach ((array) $events as $event) {
            // Reject magic method names to prevent accidental invocation of __destruct, __toString, etc.
            if (str_starts_with($event, '__')) {
                throw new \InvalidArgumentException(sprintf('Event name "%s" is not allowed: magic method names cannot be used as event names.', $event));
            }
            // Overrides listener if a previous one was associated already
            // Prevents duplicate listeners on same event (same instance only)
            $this->listeners[$event][$oid] = $listener;
        }
    }
    /** {@inheritDoc} */
    public function remove_event_listener(string|array $events, object $listener): void
    {
        // Picks the hash code related to that listener
        $oid = spl_object_id($listener);
        foreach ((array) $events as $event) {
            unset($this->listeners[$event][$oid]);
        }
    }
    /** {@inheritDoc} */
    public function add_event_subscriber(Event_Subscriber $subscriber): void
    {
        $this->add_event_listener($subscriber->get_subscribed_events(), $subscriber);
    }
    /** {@inheritDoc} */
    public function remove_event_subscriber(Event_Subscriber $subscriber): void
    {
        $this->remove_event_listener($subscriber->get_subscribed_events(), $subscriber);
    }
}