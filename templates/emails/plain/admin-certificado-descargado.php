<?php
/**
 * Certificado descargado admin email template (Plain).
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
$customer_email = $user ? $user->user_email : '';

$course_id    = absint( get_post_meta( $certificate_id, '_certificados_course_id', true ) );
$course_name  = $course_id ? get_the_title( $course_id ) : '';
$code         = get_post_meta( $certificate_id, '_certificados_code', true );
$download_date = get_post_meta( $certificate_id, '_certificados_first_download_date', true );
if ( ! $download_date ) {
	$download_date = current_time( 'mysql' );
}

echo "= " . esc_html( $email_heading ) . " =\n\n";

echo esc_html__( 'Hola,', 'certificados' ) . "\n\n";
printf( esc_html__( 'Te informamos que el cliente %1$s (%2$s) ha descargado por primera vez su certificado de participación.', 'certificados' ), $customer_name, $customer_email ) . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
printf( esc_html__( 'Cliente: %1$s (%2$s)', 'certificados' ), $customer_name, $customer_email ) . "\n";
printf( esc_html__( 'Curso o Taller: %s', 'certificados' ), $course_name ) . "\n";
printf( esc_html__( 'Código de Validación: %s', 'certificados' ), $code ) . "\n";
printf( esc_html__( 'Fecha de Descarga: %s', 'certificados' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $download_date ) ) ) . "\n\n";
echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

esc_html_e( 'Puedes ver y gestionar el certificado en el panel de administración de WordPress.', 'certificados' ) . "\n";
echo get_edit_post_link( $certificate_id ) . "\n\n";

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
