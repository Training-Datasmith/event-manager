# Architecture: event-manager

## Purpose

Doctrine's EventManager is the central event-listener system used across Doctrine ORM, DBAL, and related packages. It provides observer-pattern wiring: listeners register interest in named events, and callers dispatch those events to trigger all registered handlers.

## Directory Structure

```
src/
  Event_Args.php                    — Base value object passed to listeners; carries event context
  Event_Dispatcher.php              — Symfony EventDispatcher bridge (implements Symfony\Contracts)
  Event_Listener_Introspector.php   — Interface: read registered listeners
  Event_Listener_Registry.php       — Interface: add/remove listeners
  Event_Manager.php                 — Primary implementation combining all interfaces
  Event_Manager_Interface.php       — Composite interface (Introspector + Registry + Subscriber Registry)
  Event_Subscriber.php              — Interface for self-describing subscribers
  Event_Subscriber_Registry.php     — Interface: add/remove subscribers

tests/
  Event_Manager_Test.php            — Unit tests covering dispatch, deduplication, magic-method guards
```

## Key Design Decisions

- **Identity-keyed listeners** — `spl_object_id()` prevents the same instance from registering twice for the same event, avoiding duplicate invocations.
- **Magic-method guard** — event names beginning with `__` are rejected to prevent accidental invocation of `__destruct`, `__clone`, etc.
- **Method-name dispatch** — the event name doubles as the method name called on each listener (`$listener->{$eventName}($args)`), keeping registration friction low.
- **EventArgs immutable singleton** — `Event_Args::get_empty_instance()` avoids allocations for fire-and-forget events that carry no data.

## Extension Points

- Implement `Event_Subscriber` to declare subscribed events from within the listener class itself.
- Replace `Event_Args` with a domain-specific subclass to pass typed context to listeners.
- Use `Event_Dispatcher` to bridge Doctrine events into Symfony's event system.

## Dependency Flow

```
Caller
  └── Event_Manager::dispatch_event(name, args?)
        └── listener->{name}(Event_Args)
```
