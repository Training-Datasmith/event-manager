<?php

declare (strict_types=1);
namespace Doctrine\Common;

/**
 * Interface for registering and unregistering event listeners.
 */
interface Event_Listener_Registry extends Event_Dispatcher
{
    /**
     * Adds an event listener that listens on the specified events.
     *
     * @param string|string[] $events   The event(s) to listen on.
     * @param object          $listener The listener object.
     */
    public function add_event_listener(string|array $events, object $listener): void;
    /**
     * Removes an event listener from the specified events.
     *
     * @param string|string[] $events
     */
    public function remove_event_listener(string|array $events, object $listener): void;
}