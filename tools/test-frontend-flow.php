<?php
/**
 * Frontend flow test with WordPress/WooCommerce stubs.
 *
 * @package Certificados
 */

define( 'ABSPATH', __DIR__ );
define( 'EP_ROOT', 1 );
define( 'EP_PAGES', 2 );

$GLOBALS['certificados_frontend_test'] = array(
	'current_user_id' => 7,
	'last_get_posts'  => null,
	'posts'           => array(
		101 => array(
			'ID'        => 101,
			'post_type' => 'cert_course',
			'title'     => 'Taller de Marketing Digital',
			'meta'      => array(
				'_certificados_mode' => 'virtual',
			),
		),
		202 => array(
			'ID'        => 202,
			'post_type' => 'cert_certificate',
			'title'     => 'Certificado Demo',
			'meta'      => array(
				'_certificados_course_id'  => 101,
				'_certificados_user_id'    => 7,
				'_certificados_issue_date' => '2026-06-12',
				'_certificados_code'       => 'CERT-DEMO123',
			),
		),
		303 => array(
			'ID'        => 303,
			'post_type' => 'cert_certificate',
			'title'     => 'Certificado Incompleto',
			'meta'      => array(
				'_certificados_course_id'  => 999,
				'_certificados_user_id'    => 404,
				'_certificados_issue_date' => '2026-06-12',
				'_certificados_code'       => 'CERT-INCOMPLETE',
			),
		),
	),
	'users'           => array(
		7 => array(
			'ID'           => 7,
			'display_name' => 'Ana Cliente',
			'user_email'   => 'ana@example.test',
		),
	),
);

function add_action() {}
function add_filter() {}
function add_rewrite_endpoint() {}
function add_rewrite_rule() {}
function add_shortcode() {}

function __( $text ) {
	return $text;
}

function esc_html__( $text ) {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr__( $text ) {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $url ) {
	return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
}

function absint( $value ) {
	return abs( (int) $value );
}

function is_user_logged_in() {
	return true;
}

function get_current_user_id() {
	return $GLOBALS['certificados_frontend_test']['current_user_id'];
}

function get_posts( $args ) {
	$GLOBALS['certificados_frontend_test']['last_get_posts'] = $args;

	$results = array();
	foreach ( $GLOBALS['certificados_frontend_test']['posts'] as $post ) {
		if ( isset( $args['post_type'] ) && $post['post_type'] !== $args['post_type'] ) {
			continue;
		}

		if ( isset( $args['meta_key'], $args['meta_value'] ) ) {
			$value = isset( $post['meta'][ $args['meta_key'] ] ) ? $post['meta'][ $args['meta_key'] ] : null;
			if ( (string) $value !== (string) $args['meta_value'] ) {
				continue;
			}
		}

		$results[] = (object) array(
			'ID'        => $post['ID'],
			'post_type' => $post['post_type'],
			'post_title' => $post['title'],
		);

		if ( isset( $args['posts_per_page'] ) && 1 === (int) $args['posts_per_page'] ) {
			break;
		}
	}

	return $results;
}

function get_post_meta( $post_id, $key ) {
	return isset( $GLOBALS['certificados_frontend_test']['posts'][ $post_id ]['meta'][ $key ] )
		? $GLOBALS['certificados_frontend_test']['posts'][ $post_id ]['meta'][ $key ]
		: '';
}

function get_userdata( $user_id ) {
	return isset( $GLOBALS['certificados_frontend_test']['users'][ $user_id ] )
		? (object) $GLOBALS['certificados_frontend_test']['users'][ $user_id ]
		: false;
}

function get_the_title( $post_id ) {
	return isset( $GLOBALS['certificados_frontend_test']['posts'][ $post_id ] )
		? $GLOBALS['certificados_frontend_test']['posts'][ $post_id ]['title']
		: '';
}

function get_post_type( $post_id ) {
	return isset( $GLOBALS['certificados_frontend_test']['posts'][ $post_id ] )
		? $GLOBALS['certificados_frontend_test']['posts'][ $post_id ]['post_type']
		: false;
}

function current_time() {
	return '2026-06-12';
}

function home_url( $path = '' ) {
	return 'https://example.test/' . ltrim( $path, '/' );
}

function user_trailingslashit( $path ) {
	return rtrim( $path, '/' ) . '/';
}

function add_query_arg( $args, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
}

function wp_nonce_url( $url, $action ) {
	return add_query_arg( array( '_wpnonce' => 'nonce-' . $action ), $url );
}

function wc_get_account_endpoint_url( $endpoint ) {
	return 'https://example.test/my-account/' . $endpoint . '/';
}

function sanitize_text_field( $value ) {
	return trim( (string) $value );
}

function shortcode_atts( $pairs, $atts ) {
	return array_merge( $pairs, $atts );
}

function certificados_frontend_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "[FAIL] {$message}\n" );
		exit( 1 );
	}

	echo "[OK] {$message}\n";
}

require dirname( __DIR__ ) . '/includes/class-certificados-post-types.php';
require dirname( __DIR__ ) . '/includes/class-certificados-frontend.php';
require dirname( __DIR__ ) . '/includes/class-certificados-pdf.php';

$frontend = Certificados_Frontend::instance();

ob_start();
$frontend->render_account_certificates();
$html = ob_get_clean();

certificados_frontend_test_assert( false !== strpos( $html, 'Mis certificados' ), 'account section title is rendered' );
certificados_frontend_test_assert( false !== strpos( $html, 'Taller de Marketing Digital' ), 'assigned course is rendered' );
certificados_frontend_test_assert( false !== strpos( $html, 'CERT-DEMO123' ), 'validation code is rendered' );
certificados_frontend_test_assert( false !== strpos( $html, 'Descargar PDF' ), 'PDF download action is rendered' );
certificados_frontend_test_assert( false !== strpos( $html, 'certificados_pdf=202' ), 'PDF download targets the certificate' );
certificados_frontend_test_assert( false !== strpos( $html, '_wpnonce=nonce-certificados_pdf_202' ), 'PDF download includes nonce' );
certificados_frontend_test_assert( false !== strpos( $html, 'validar-certificado%2FCERT-DEMO123' ), 'QR points to public validation URL' );
certificados_frontend_test_assert( false !== strpos( $html, 'quickchart.io%2Fqr' ) || false !== strpos( $html, 'quickchart.io/qr' ), 'QR image is rendered' );
certificados_frontend_test_assert( false !== strpos( $html, 'https://example.test/validar-certificado/CERT-DEMO123/' ), 'public validation link is rendered' );

$reflection = new ReflectionClass( 'Certificados_Frontend' );
$method     = $reflection->getMethod( 'find_certificate_by_code' );
$method->setAccessible( true );
$found = $method->invoke( null, 'CERT-DEMO123' );

certificados_frontend_test_assert( $found && 202 === $found->ID, 'public validation lookup finds certificate by code' );
certificados_frontend_test_assert( '_certificados_code' === $GLOBALS['certificados_frontend_test']['last_get_posts']['meta_key'], 'public lookup uses validation code meta key' );

$missing_data = $method->invoke( null, 'CERT-INCOMPLETE' );
certificados_frontend_test_assert( null === $missing_data, 'public validation rejects certificates without valid course and customer' );

$shortcode_html = $frontend->validation_shortcode( array( 'codigo' => 'CERT-DEMO123' ) );
certificados_frontend_test_assert( false !== strpos( $shortcode_html, 'Certificado válido' ), 'validation shortcode renders valid certificate content' );
certificados_frontend_test_assert( false !== strpos( $shortcode_html, 'Ana Cliente' ), 'validation shortcode renders participant name' );

$empty_shortcode_html = $frontend->validation_shortcode( array() );
certificados_frontend_test_assert( false !== strpos( $empty_shortcode_html, 'certificados-validation-form' ), 'validation shortcode renders search form without a code' );
