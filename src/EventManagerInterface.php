<?php

declare (strict_types=1);
namespace Doctrine\Common;

/** Provided for convenience, but consider using the individual interfaces directly. */
interface Event_Manager_Interface extends Event_Listener_Introspector, Event_Listener_Registry, Event_Subscriber_Registry
{
}