<?php
/**
 * Lightweight bootstrap test with WordPress function stubs.
 *
 * This runs the plugin entrypoint and verifies that the expected post types,
 * hooks, rewrite routes, and role capabilities are registered. It is intended
 * for local regression checks when a full WordPress test suite is unavailable.
 *
 * @package Certificados
 */

define( 'ABSPATH', __DIR__ );
define( 'EP_ROOT', 1 );
define( 'EP_PAGES', 2 );

$GLOBALS['certificados_test'] = array(
	'actions'            => array(),
	'filters'            => array(),
	'post_types'         => array(),
	'rewrite_endpoints'  => array(),
	'rewrite_rules'      => array(),
	'activation_hooks'   => array(),
	'deactivation_hooks' => array(),
	'options'            => array(),
	'roles'              => array(),
);

class Certificados_Test_Role {
	/**
	 * Role capabilities.
	 *
	 * @var array
	 */
	public $caps = array();

	/**
	 * Adds a capability.
	 *
	 * @param string $capability Capability name.
	 */
	public function add_cap( $capability ) {
		$this->caps[ $capability ] = true;
	}

	/**
	 * Checks a capability.
	 *
	 * @param string $capability Capability name.
	 * @return bool
	 */
	public function has_cap( $capability ) {
		return ! empty( $this->caps[ $capability ] );
	}
}

$GLOBALS['certificados_test']['roles']['administrator'] = new Certificados_Test_Role();
$GLOBALS['certificados_test']['roles']['shop_manager']  = new Certificados_Test_Role();

function plugin_dir_path( $file ) {
	return trailingslashit( dirname( $file ) );
}

function plugin_dir_url( $file ) {
	return 'https://example.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
}

function trailingslashit( $value ) {
	return rtrim( $value, '/\\' ) . '/';
}

function register_activation_hook( $file, $callback ) {
	$GLOBALS['certificados_test']['activation_hooks'][ $file ] = $callback;
}

function register_deactivation_hook( $file, $callback ) {
	$GLOBALS['certificados_test']['deactivation_hooks'][ $file ] = $callback;
}

function add_action( $hook, $callback ) {
	$GLOBALS['certificados_test']['actions'][ $hook ][] = $callback;
}

function add_filter( $hook, $callback ) {
	$GLOBALS['certificados_test']['filters'][ $hook ][] = $callback;
}

function register_post_type( $post_type, $args ) {
	$GLOBALS['certificados_test']['post_types'][ $post_type ] = $args;
}

function add_rewrite_endpoint( $endpoint, $places ) {
	$GLOBALS['certificados_test']['rewrite_endpoints'][ $endpoint ] = $places;
}

function add_rewrite_rule( $regex, $query, $position ) {
	$GLOBALS['certificados_test']['rewrite_rules'][ $regex ] = array(
		'query'    => $query,
		'position' => $position,
	);
}

function flush_rewrite_rules() {}

function get_role( $role_name ) {
	return isset( $GLOBALS['certificados_test']['roles'][ $role_name ] ) ? $GLOBALS['certificados_test']['roles'][ $role_name ] : null;
}

function update_option( $name, $value ) {
	$GLOBALS['certificados_test']['options'][ $name ] = $value;
}

function get_option( $name ) {
	return isset( $GLOBALS['certificados_test']['options'][ $name ] ) ? $GLOBALS['certificados_test']['options'][ $name ] : null;
}

function __( $text ) {
	return $text;
}

function certificados_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "[FAIL] {$message}\n" );
		exit( 1 );
	}

	echo "[OK] {$message}\n";
}

require dirname( __DIR__ ) . '/certificados.php';

Certificados_Plugin::activate();

$state = $GLOBALS['certificados_test'];

certificados_test_assert( isset( $state['post_types']['cert_course'] ), 'course post type is registered on activation' );
certificados_test_assert( isset( $state['post_types']['cert_certificate'] ), 'certificate post type is registered on activation' );
certificados_test_assert( isset( $state['rewrite_endpoints']['certificados'] ), 'WooCommerce account endpoint is registered' );
certificados_test_assert( isset( $state['rewrite_rules']['^validar-certificado/([^/]+)/?$'] ), 'public validation rewrite rule is registered' );
certificados_test_assert( isset( $state['filters']['woocommerce_account_menu_items'] ), 'WooCommerce account menu filter is registered' );
certificados_test_assert( isset( $state['actions']['woocommerce_account_certificados_endpoint'] ), 'WooCommerce account endpoint renderer is registered' );
certificados_test_assert( isset( $state['actions']['template_redirect'] ), 'public route handler is registered' );
certificados_test_assert( isset( $state['actions']['add_meta_boxes'] ), 'admin meta boxes are registered' );
certificados_test_assert( get_option( 'certificados_capabilities_version' ) === CERTIFICADOS_VERSION, 'capabilities version is stored' );

foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
	$role = get_role( $role_name );
	certificados_test_assert( $role->has_cap( 'edit_cert_courses' ), "{$role_name} has course management capabilities" );
	certificados_test_assert( $role->has_cap( 'edit_cert_certificates' ), "{$role_name} has certificate management capabilities" );
	foreach ( Certificados_Post_Types::get_all_capabilities() as $capability ) {
		certificados_test_assert( $role->has_cap( $capability ), "{$role_name} has {$capability}" );
	}
}
