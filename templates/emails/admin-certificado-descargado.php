<?php
/**
 * Certificado descargado admin email template (HTML).
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

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php esc_html_e( 'Hola,', 'certificados' ); ?></p>
<p><?php printf( esc_html__( 'Te informamos que el cliente %1$s (%2$s) ha descargado por primera vez su certificado de participación.', 'certificados' ), '<strong>' . esc_html( $customer_name ) . '</strong>', '<code>' . esc_html( $customer_email ) . '</code>' ); ?></p>

<div style="background-color: #faf7ef; border: 1px solid rgba(254, 178, 11, 0.4); border-radius: 6px; padding: 20px; margin: 25px 0; font-family: sans-serif;">
	<table style="width: 100%; border-collapse: collapse;">
		<tr>
			<td style="padding: 6px 0; font-weight: bold; color: #8f6500; width: 150px; font-size: 14px;"><?php esc_html_e( 'Cliente:', 'certificados' ); ?></td>
			<td style="padding: 6px 0; color: #111111; font-size: 14px;"><strong><?php echo esc_html( $customer_name ); ?></strong> (<?php echo esc_html( $customer_email ); ?>)</td>
		</tr>
		<tr>
			<td style="padding: 6px 0; font-weight: bold; color: #8f6500; font-size: 14px;"><?php esc_html_e( 'Curso o Taller:', 'certificados' ); ?></td>
			<td style="padding: 6px 0; color: #111111; font-size: 14px;"><strong><?php echo esc_html( $course_name ); ?></strong></td>
		</tr>
		<tr>
			<td style="padding: 6px 0; font-weight: bold; color: #8f6500; font-size: 14px;"><?php esc_html_e( 'Código de Validación:', 'certificados' ); ?></td>
			<td style="padding: 6px 0; color: #111111; font-size: 14px;"><code style="background-color: #111111; color: #feb20b; padding: 2px 5px; border-radius: 4px; font-family: monospace; font-size: 13px;"><?php echo esc_html( $code ); ?></code></td>
		</tr>
		<tr>
			<td style="padding: 6px 0; font-weight: bold; color: #8f6500; font-size: 14px;"><?php esc_html_e( 'Fecha de Descarga:', 'certificados' ); ?></td>
			<td style="padding: 6px 0; color: #111111; font-size: 14px;"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $download_date ) ) ); ?></td>
		</tr>
	</table>
</div>

<p><?php esc_html_e( 'Puedes ver y gestionar el certificado en el panel de administración de WordPress.', 'certificados' ); ?></p>

<p style="text-align: center; margin: 30px 0;">
	<a href="<?php echo esc_url( get_edit_post_link( $certificate_id ) ); ?>" style="display: inline-block; background-color: #111111; color: #feb20b; text-decoration: none; padding: 14px 28px; font-weight: bold; border-radius: 6px; font-size: 16px; border: 1.5px solid #feb20b;"><?php esc_html_e( 'Ver certificado en WordPress', 'certificados' ); ?></a>
</p>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
