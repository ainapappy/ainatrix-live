<?php
/**
 * Contrat des classes de hooks du plugin ainatrix live.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace AinatrixLive\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Une classe de hooks regroupe l'enregistrement des actions et filtres
 * WordPress d'un domaine fonctionnel du plugin.
 */
interface HookSubscriberInterface {

    /**
     * Enregistre les hooks du domaine auprès de WordPress.
     */
    public function register(): void;
}
