<?php
/**
 * Plugin Name: Jev Rules
 * Description: Queue versioned semantic decisions for WordPress posts and expose them to other plugins.
 * Version: 0.1.1
 * Requires at least: 6.8
 * Requires PHP: 8.1
 * License: MIT
 * Purpose: Register an extensible DecisionPacks-powered rule engine, admin actions and durable cron jobs.
 */
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/includes/decisionpacks.php';
require_once __DIR__ . '/includes/runtime.php';
require_once __DIR__ . '/includes/admin.php';
add_action('init', function () {
    register_post_type('jev_decision', ['label'=>'Jev decisions','public'=>false,'show_ui'=>false,'supports'=>[]]);
});
add_action('jev_rules_process', 'jev_rules_process');
add_action('admin_menu', 'jev_rules_admin_menu');
add_action('admin_post_jev_rules_enqueue', 'jev_rules_admin_enqueue');
add_action('admin_post_jev_rules_save', 'jev_rules_admin_save');
