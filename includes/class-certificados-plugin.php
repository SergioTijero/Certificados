<?php
/**
 * Main plugin bootstrap.
 *
 * @package Certificados
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CERTIFICADOS_PLUGIN_DIR . 'includes/class-certificados-post-types.php';
require_once CERTIFICADOS_PLUGIN_DIR . 'includes/class-certificados-admin.php';
require_once CERTIFICADOS_PLUGIN_DIR . 'includes/class-certificados-pdf.php';
require_once CERTIFICADOS_PLUGIN_DIR . 'includes/class-certificados-frontend.php';

/**
 * Coordinates plugin modules.
 */
final class Certificados_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Certificados_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns the plugin instance.
	 *
	 * @return Certificados_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		Certificados_Post_Types::instance();
		Certificados_Admin::instance();
		Certificados_Frontend::instance();
	}

	/**
	 * Activation tasks.
	 */
	public static function activate() {
		Certificados_Post_Types::register();
		Certificados_Frontend::add_rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation tasks.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
