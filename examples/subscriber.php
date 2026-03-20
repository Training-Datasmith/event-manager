<?php

declare(strict_types=1);

/**
 * Example: Using Event_Subscriber to self-register multiple events.
 */

use Doctrine\Common\Event_Args;
use Doctrine\Common\Event_Manager;
use Doctrine\Common\Event_Subscriber;

require_once __DIR__ . '/../vendor/autoload.php';

class Lifecycle_Subscriber implements Event_Subscriber
{
    /** @var list<string> */
    public array $trace = [];

    public function get_subscribed_events(): array
    {
        return ['pre_persist', 'post_persist', 'pre_remove'];
    }

    public function pre_persist(Event_Args $args): void
    {
        $this->trace[] = 'pre_persist';
    }

    public function post_persist(Event_Args $args): void
    {
        $this->trace[] = 'post_persist';
    }

    public function pre_remove(Event_Args $args): void
    {
        $this->trace[] = 'pre_remove';
    }
}

$em = new Event_Manager();
$subscriber = new Lifecycle_Subscriber();
$em->add_event_subscriber($subscriber);

$em->dispatch_event('pre_persist');
$em->dispatch_event('post_persist');
$em->dispatch_event('pre_remove');

echo implode(' -> ', $subscriber->trace) . PHP_EOL;
// pre_persist -> post_persist -> pre_remove
