Introduction
============

The Doctrine Event Manager is a simple event system used by the various Doctrine projects. It was originally built
for the DBAL and ORM but over time other projects adopted it and now it is available as a standalone library.

Installation
============

The library can easily be installed with composer.

.. code-block:: sh

    $ composer require doctrine/event-manager

Setup
=====

The event system is controlled by the ``Doctrine\Common\Event_Manager`` class.

.. code-block:: php

    use Doctrine\Common\Event_Manager;

    $event_manager = new Event_Manager();

Listeners
=========

Now you are ready to listen for events. Here is an example of a custom event listener named ``TestEvent``.

.. code-block:: php

    use Doctrine\Common\Event_Args;
    use Doctrine\Common\Event_Manager;

    final class TestEvent
    {
        public const preFoo = 'preFoo';
        public const postFoo = 'postFoo';

        /** @var Event_Manager */
        private $event_manager;

        /** @var bool */
        public $pre_foo_invoked = false;

        /** @var bool */
        public $post_foo_invoked = false;

        public function __construct(Event_Manager $event_manager)
        {
            $event_manager->add_event_listener([self::preFoo, self::postFoo], $this);
        }

        public function preFoo(Event_Args $event_args) : void
        {
            $this->pre_foo_invoked = true;
        }

        public function postFoo(Event_Args $event_args) : void
        {
            $this->post_foo_invoked = true;
        }
    }

    // Create a new instance
    $testEvent = new TestEvent($event_manager);

Dispatching Events
==================

Now you can dispatch events with the ``dispatch_event()`` method.

.. code-block:: php

    $event_manager->dispatch_event(TestEvent::preFoo);
    $event_manager->dispatch_event(TestEvent::postFoo);

Removing Event Listeners
========================

You can easily remove a listener with the ``remove_event_listener()`` method.

.. code-block:: php

    $event_manager->remove_event_listener([TestEvent::preFoo, TestEvent::postFoo], $testEvent);

Event Subscribers
=================

The Doctrine event system also has a simple concept of event subscribers. We can define a simple ``TestEventSubscriber`` class which implements the ``Doctrine\Common\Event_Subscriber`` interface with a ``get_subscribed_events()`` method which returns an array of events it should be subscribed to.

.. code-block:: php

    use Doctrine\Common\Event_Subscriber;

    final class TestEventSubscriber implements Event_Subscriber
    {
        /** @var bool */
        public $pre_foo_invoked = false;

        public function preFoo() : void
        {
            $this->pre_foo_invoked = true;
        }

        public function get_subscribed_events() : array
        {
            return [TestEvent::preFoo];
        }
    }

    $event_subscriber = new TestEventSubscriber();
    $event_manager->add_event_subscriber($event_subscriber);

.. note::

    The array returned by the ``get_subscribed_events()`` method is a simple array with the values being the event names. The subscriber must have a method that is named exactly like the event.

Now when you dispatch an event, any event subscribers will be notified of that event.

.. code-block:: php

    $event_manager->dispatch_event(TestEvent::preFoo);

Now you can check the ``pre_foo_invoked`` property to see if the event subscriber was notified of the event:

.. code-block:: php

    if ($event_subscriber->pre_foo_invoked) {
        // the preFoo method was invoked
    }
