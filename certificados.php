<?php
/**
 * Plugin Name: Certificados
 * Plugin URI: https://example.com/certificados
 * Description: Plugin para gestionar y emitir certificados en WordPress.
 * Version: 0.1.0
 * Author: Sergio Tijero
 * Author URI: https://example.com
 * Text Domain: certificados
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package Certificados
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CERTIFICADOS_VERSION', '0.1.0' );
define( 'CERTIFICADOS_PLUGIN_FILE', __FILE__ );
define( 'CERTIFICADOS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CERTIFICADOS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Runs when the plugin is activated.
 */
function certificados_activate() {
	// Activation tasks will go here.
}
register_activation_hook( __FILE__, 'certificados_activate' );

/**
 * Runs when the plugin is deactivated.
 */
function certificados_deactivate() {
	// Deactivation tasks will go here.
}
register_deactivation_hook( __FILE__, 'certificados_deactivate' );
