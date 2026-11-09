<?php

declare (strict_types=1);
namespace Doctrine\Common;

/**
 * An Event_Subscriber knows what events it is interested in.
 * If an Event_Subscriber is added to an Event_Manager, the manager invokes
 * {@link get_subscribed_events} and registers the subscriber as a listener for all
 * returned events.
 */
interface Event_Subscriber
{
    /**
     * Returns an array of events this subscriber wants to listen to.
     *
     * @return string[]
     */
    public function get_subscribed_events();
}