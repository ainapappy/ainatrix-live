<?php

/**
 * @package   ainatrix-live
 * @author    Aina Papy
 * @license   GPL-3.0
 * @link      https://www.ainatrix.com
 *
 * Plugin Name: ainatrix - pluging live for ainatrix
 * Plugin URI: https://www.ainatrix.com
 * Description: pluging live v1 for ainatrix
 * Author: Aina Papy, Mevasoa
 * Version: 2.16.0
 * Author URI: https://www.ainatrix.com/papy
 * Text Domain: ainatrix
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AIX_LIVE_URL', plugin_dir_url(__FILE__));

// Code objet PSR-4 : classes du namespace Ainatrix\ dans src/.
require_once __DIR__ . '/vendor/autoload.php';

// Couche procédurale résiduelle : constantes et glue (chargement non récursif).
$dir = __DIR__ . '/inc';
foreach (scandir($dir) as $filename) {
    $path = $dir . '/' . $filename;
    if (is_file($path)) {
        require_once $path;
    }
}

// Amorce la couche objet une fois les plugins chargés.
add_action('plugins_loaded', ['Ainatrix\Plugin', 'boot']);
