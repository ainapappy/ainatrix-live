<?php
/**
 * Point d'entrée objet du plugin : déclare et enregistre les classes de hooks.
 *
 * @package ainatrix
 */

declare(strict_types=1);

namespace Ainatrix;

use Ainatrix\Hooks\HookLoader;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin {

    /**
     * Amorce le plugin : à brancher sur plugins_loaded (voir ainatrix-live.php).
     */
    public static function boot(): void {
        // Enregistrer ici la classe de hooks de chaque fonctionnalité (src/{Feature}/).
        (new HookLoader())->register();
    }
}
