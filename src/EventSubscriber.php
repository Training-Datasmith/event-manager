<?php

declare (strict_types=1);
namespace Doctrine\Common;

/**
 * An EventSubscriber knows what events it is interested in.
 * If an EventSubscriber is added to an EventManager, the manager invokes
 * {@link getSubscribedEvents} and registers the subscriber as a listener for all
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