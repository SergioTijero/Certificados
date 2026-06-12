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

		add_action( 'admin_init', array( __CLASS__, 'maybe_update_role_capabilities' ) );
	}

	/**
	 * Activation tasks.
	 */
	public static function activate() {
		Certificados_Post_Types::register();
		self::add_role_capabilities();
		update_option( 'certificados_capabilities_version', CERTIFICADOS_VERSION );
		Certificados_Frontend::add_rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation tasks.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Ensures capabilities are present after plugin updates.
	 */
	public static function maybe_update_role_capabilities() {
		if (
			CERTIFICADOS_VERSION === get_option( 'certificados_capabilities_version' )
			&& self::roles_have_capabilities()
		) {
			return;
		}

		self::add_role_capabilities();
		update_option( 'certificados_capabilities_version', CERTIFICADOS_VERSION );
	}

	/**
	 * Gives admins and WooCommerce shop managers access to certificates.
	 */
	private static function add_role_capabilities() {
		foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}

			foreach ( Certificados_Post_Types::get_all_capabilities() as $capability ) {
				$role->add_cap( $capability );
			}
		}
	}

	/**
	 * Checks whether available target roles already have plugin capabilities.
	 *
	 * @return bool
	 */
	private static function roles_have_capabilities() {
		foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}

			foreach ( Certificados_Post_Types::get_all_capabilities() as $capability ) {
				if ( ! $role->has_cap( $capability ) ) {
					return false;
				}
			}
		}

		return true;
	}
}
