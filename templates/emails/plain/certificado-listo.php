<?php
/**
 * Certificado listo email template (Plain).
 *
 * @package Certificados
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$certificate_id = $certificate->ID;
$user_id        = absint( get_post_meta( $certificate_id, '_certificados_user_id', true ) );
$user           = get_userdata( $user_id );
$customer_name  = $user ? $user->display_name : '';

$course_id    = absint( get_post_meta( $certificate_id, '_certificados_course_id', true ) );
$course_name  = $course_id ? get_the_title( $course_id ) : '';
$issue_date   = get_post_meta( $certificate_id, '_certificados_issue_date', true );
$code         = get_post_meta( $certificate_id, '_certificados_code', true );

$my_account_url = function_exists( 'wc_get_account_endpoint_url' ) 
	? wc_get_account_endpoint_url( 'certificados' ) 
	: home_url( '/mi-cuenta/certificados/' );

echo "= " . esc_html( $email_heading ) . " =\n\n";

printf( esc_html__( 'Hola %s,', 'certificados' ), $customer_name ) . "\n\n";
esc_html_e( 'Nos complace informarte que tu certificado de participación ya está listo y disponible para descargar.', 'certificados' ) . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
printf( esc_html__( 'Curso: %s', 'certificados' ), $course_name ) . "\n";
printf( esc_html__( 'Fecha de Emisión: %s', 'certificados' ), $issue_date ) . "\n";
printf( esc_html__( 'Código: %s', 'certificados' ), $code ) . "\n\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

esc_html_e( 'Puedes acceder a tu certificado e imprimirlo o guardarlo en formato PDF ingresando a tu cuenta en nuestra plataforma.', 'certificados' ) . "\n\n";
echo esc_url( $my_account_url ) . "\n\n";

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
