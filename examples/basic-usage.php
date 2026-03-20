<?php

declare(strict_types=1);

/**
 * Example: Registering and dispatching events with Doctrine EventManager.
 */

use Doctrine\Common\Event_Args;
use Doctrine\Common\Event_Manager;

require_once __DIR__ . '/../vendor/autoload.php';

// --- 1. Simple listener class ---

class User_Created_Args extends Event_Args
{
    public function __construct(
        public readonly string $username,
        public readonly string $email,
    ) {}
}

class Audit_Listener
{
    /** @var list<string> */
    public array $log = [];

    public function user_created(User_Created_Args $args): void
    {
        $this->log[] = "User created: {$args->username} <{$args->email}>";
    }
}

// --- 2. Wire up ---

$em = new Event_Manager();
$audit = new Audit_Listener();
$em->add_event_listener('user_created', $audit);

// --- 3. Dispatch ---

$em->dispatch_event('user_created', new User_Created_Args('alice', 'alice@example.com'));
$em->dispatch_event('user_created', new User_Created_Args('bob', 'bob@example.com'));

foreach ($audit->log as $entry) {
    echo $entry . PHP_EOL;
}
// User created: alice <alice@example.com>
// User created: bob <bob@example.com>

// --- 4. Removing a listener ---

$em->remove_event_listener('user_created', $audit);
$em->dispatch_event('user_created', new User_Created_Args('charlie', 'charlie@example.com'));
echo 'Entries after removal: ' . count($audit->log) . PHP_EOL; // 2
