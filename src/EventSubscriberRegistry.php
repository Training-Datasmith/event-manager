<?php

declare (strict_types=1);
namespace Doctrine\Common;

/**
 * Interface for registering and unregistering event subscribers.
 */
interface Event_Subscriber_Registry extends Event_Dispatcher
{
    /**
     * Adds an EventSubscriber.
     *
     * The subscriber is asked for all the events it is interested in and added
     * as a listener for these events.
     */
    public function add_event_subscriber(Event_Subscriber $subscriber): void;
    /**
     * Removes an EventSubscriber.
     *
     * The subscriber is asked for all the events it is interested in and removed
     * as a listener for these events.
     */
    public function remove_event_subscriber(Event_Subscriber $subscriber): void;
}