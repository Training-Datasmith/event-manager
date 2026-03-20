<?php

declare (strict_types=1);
namespace Doctrine\Common;

/**
 * Interface for introspecting event listeners.
 *
 * Provides methods to query registered listeners without modifying them.
 */
interface Event_Listener_Introspector extends Event_Dispatcher
{
    /**
     * Gets the listeners of a specific event.
     *
     * @param string $event The name of the event.
     *
     * @return object[]
     */
    public function get_listeners(string $event): array;
    /**
     * Gets all listeners keyed by event name.
     *
     * @return array<string, object[]>
     */
    public function get_all_listeners(): array;
    /**
     * Checks whether an event has any registered listeners.
     */
    public function has_listeners(string $event): bool;
}