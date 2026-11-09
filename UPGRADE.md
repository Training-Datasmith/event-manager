# Upgrade to 2.0

## Made the `$event` parameter of `Event_Manager::get_listeners()` mandatory

When calling `Event_Manager::get_listeners()` you need to specify the event that
you want to fetch the listeners for. Call `get_all_listeners()` instead if you
want to access the listeners of all events.

# Upgrade to 1.2

## Deprecated calling `Event_Manager::get_listeners()` without an event name

When calling `Event_Manager::get_listeners()` without an event name, all
listeners were returned, keyed by event name. A new method `get_all_listeners()`
has been added to provide this functionality. It should be used instead.
