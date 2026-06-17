<?php
/**
 * Certificado listo email template (HTML).
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

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php printf( esc_html__( 'Hola %s,', 'certificados' ), esc_html( $customer_name ) ); ?></p>
<p><?php esc_html_e( 'Nos complace informarte que tu certificado de participación ya está listo y disponible para descargar.', 'certificados' ); ?></p>

<div style="background-color: #faf7ef; border: 1px solid rgba(254, 178, 11, 0.4); border-radius: 6px; padding: 20px; margin: 25px 0; font-family: sans-serif;">
	<table style="width: 100%; border-collapse: collapse;">
		<tr>
			<td style="padding: 6px 0; font-weight: bold; color: #8f6500; width: 120px; font-size: 14px;"><?php esc_html_e( 'Curso:', 'certificados' ); ?></td>
			<td style="padding: 6px 0; color: #111111; font-size: 14px;"><strong><?php echo esc_html( $course_name ); ?></strong></td>
		</tr>
		<tr>
			<td style="padding: 6px 0; font-weight: bold; color: #8f6500; font-size: 14px;"><?php esc_html_e( 'Fecha de Emisión:', 'certificados' ); ?></td>
			<td style="padding: 6px 0; color: #111111; font-size: 14px;"><?php echo esc_html( $issue_date ); ?></td>
		</tr>
		<tr>
			<td style="padding: 6px 0; font-weight: bold; color: #8f6500; font-size: 14px;"><?php esc_html_e( 'Código:', 'certificados' ); ?></td>
			<td style="padding: 6px 0; color: #111111; font-size: 14px;"><code style="background-color: #111111; color: #feb20b; padding: 2px 5px; border-radius: 4px; font-family: monospace; font-size: 13px;"><?php echo esc_html( $code ); ?></code></td>
		</tr>
	</table>
</div>

<p><?php esc_html_e( 'Puedes acceder a tu certificado e imprimirlo o guardarlo en formato PDF ingresando a tu cuenta en nuestra plataforma.', 'certificados' ); ?></p>

<p style="text-align: center; margin: 30px 0;">
	<a href="<?php echo esc_url( $my_account_url ); ?>" style="display: inline-block; background-color: #feb20b; color: #111111; text-decoration: none; padding: 14px 28px; font-weight: bold; border-radius: 6px; font-size: 16px; box-shadow: 0 4px 10px rgba(254, 178, 11, 0.3);"><?php esc_html_e( 'Ver mi certificado', 'certificados' ); ?></a>
</p>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
