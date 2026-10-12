<?php
/**
 * Chargeur central des classes de hooks du plugin ainatrix live.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

final class HookLoader {

    /** @var list<HookSubscriberInterface> */
    private array $subscribers;

    public function __construct(HookSubscriberInterface ...$subscribers) {
        $this->subscribers = $subscribers;
    }

    /**
     * Enregistre tous les subscribers auprès de WordPress.
     */
    public function register(): void {
        foreach ($this->subscribers as $subscriber) {
            $subscriber->register();
        }
    }
}
