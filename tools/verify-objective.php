<?php
/**
 * Static verification for the basic certificate workflow.
 *
 * This script does not replace a WordPress/WooCommerce integration test. It
 * checks that the plugin still declares the core hooks and modules required by
 * the initial objective.
 *
 * @package Certificados
 */

$root = dirname( __DIR__ );

$checks = array(
	'course post type'              => array( 'includes/class-certificados-post-types.php', "const COURSE_POST_TYPE      = 'cert_course';" ),
	'certificate post type'         => array( 'includes/class-certificados-post-types.php', "const CERTIFICATE_POST_TYPE = 'cert_certificate';" ),
	'custom role capabilities'      => array( 'includes/class-certificados-post-types.php', 'get_all_capabilities' ),
	'shop manager capability sync'  => array( 'includes/class-certificados-plugin.php', "'shop_manager'" ),
	'course admin fields'           => array( 'includes/class-certificados-admin.php', 'render_course_box' ),
	'certificate assignment fields' => array( 'includes/class-certificados-admin.php', 'certificados_user_id' ),
	'certificate admin columns'     => array( 'includes/class-certificados-admin.php', 'certificate_columns' ),
	'customer selector preference'  => array( 'includes/class-certificados-admin.php', 'get_assignable_customers' ),
	'unique validation code'        => array( 'includes/class-certificados-admin.php', 'generate_unique_code' ),
	'woocommerce account endpoint'  => array( 'includes/class-certificados-frontend.php', "const ACCOUNT_ENDPOINT = 'certificados';" ),
	'account certificate listing'   => array( 'includes/class-certificados-frontend.php', 'render_account_certificates' ),
	'secure PDF download'           => array( 'includes/class-certificados-frontend.php', 'handle_pdf_download' ),
	'public validation route'       => array( 'includes/class-certificados-frontend.php', 'validar-certificado' ),
	'QR URL helper'                 => array( 'includes/class-certificados-frontend.php', 'get_qr_url' ),
	'PDF generation'                => array( 'includes/class-certificados-pdf.php', 'Content-Type: application/pdf' ),
	'PDF QR image embedding'        => array( 'includes/class-certificados-pdf.php', '/Subtype /Image' ),
);

$failed = false;

foreach ( $checks as $label => $check ) {
	list( $file, $needle ) = $check;
	$path                 = $root . '/' . $file;
	$contents             = is_readable( $path ) ? file_get_contents( $path ) : '';

	if ( false === strpos( $contents, $needle ) ) {
		fwrite( STDERR, "[FAIL] {$label}: missing {$needle} in {$file}\n" );
		$failed = true;
		continue;
	}

	echo "[OK] {$label}\n";
}

exit( $failed ? 1 : 0 );
