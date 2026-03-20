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
    /**
     * Dispatches a named event to all registered listeners.
     *
     * Each listener must expose a public method named $event_name that accepts an Event_Args instance.
     * If no listeners are registered for the event, this is a no-op.
     *
     * @param string         $event_name The name of the event to dispatch (must not start with '__')
     * @param Event_Args|null $event_args Optional context object passed to each listener; defaults to the empty singleton
     */
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
    /**
     * Returns all listeners registered for a specific event.
     *
     * @param string $event The event name to look up
     *
     * @return object[] Indexed array of listener instances, keyed internally by spl_object_id
     */
    public function get_listeners(string $event): array
    {
        return $this->listeners[$event] ?? [];
    }
    /**
     * Returns the full map of registered listeners, grouped by event name.
     *
     * @return array<string, object[]> Map of event name => listener instances
     */
    public function get_all_listeners(): array
    {
        return $this->listeners;
    }
    /**
     * Returns whether any listeners are registered for the given event.
     *
     * @param string $event The event name to check
     *
     * @return bool True if at least one listener is registered
     */
    public function has_listeners(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }
    /**
     * Registers a listener for one or more events.
     *
     * The same listener instance is not duplicated for the same event (identity-keyed via spl_object_id).
     * Event names must not begin with '__' to prevent accidental magic-method invocation.
     *
     * @param string|string[] $events   A single event name or array of event names
     * @param object          $listener The listener object; must expose a public method for each event name
     *
     * @throws \InvalidArgumentException When an event name starts with '__'
     */
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
    /**
     * Removes a previously registered listener from one or more events.
     *
     * Silently does nothing if the listener was not registered for a given event.
     *
     * @param string|string[] $events   A single event name or array of event names
     * @param object          $listener The listener instance to deregister
     */
    public function remove_event_listener(string|array $events, object $listener): void
    {
        // Picks the hash code related to that listener
        $oid = spl_object_id($listener);
        foreach ((array) $events as $event) {
            unset($this->listeners[$event][$oid]);
        }
    }
    /**
     * Registers a self-describing subscriber for all events it declares interest in.
     *
     * Equivalent to calling add_event_listener() for each event returned by $subscriber->get_subscribed_events().
     *
     * @param Event_Subscriber $subscriber The subscriber declaring its own event list
     */
    public function add_event_subscriber(Event_Subscriber $subscriber): void
    {
        $this->add_event_listener($subscriber->get_subscribed_events(), $subscriber);
    }
    /**
     * Removes a previously registered subscriber from all its declared events.
     *
     * @param Event_Subscriber $subscriber The subscriber to deregister
     */
    public function remove_event_subscriber(Event_Subscriber $subscriber): void
    {
        $this->remove_event_listener($subscriber->get_subscribed_events(), $subscriber);
    }
}