<?php
/**
 * Basic PDF generation.
 *
 * @package Certificados
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates a lightweight PDF without external dependencies.
 */
final class Certificados_PDF {
	/**
	 * Sends a certificate PDF to the browser.
	 *
	 * @param int $certificate_id Certificate post ID.
	 */
	public static function stream( $certificate_id ) {
		$certificate = get_post( $certificate_id );
		if ( ! $certificate || Certificados_Post_Types::CERTIFICATE_POST_TYPE !== $certificate->post_type ) {
			wp_die( esc_html__( 'Certificado no encontrado.', 'certificados' ), 404 );
		}

		$data = self::get_certificate_data( $certificate_id );
		$pdf  = self::build_pdf( $data );

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="certificado-' . sanitize_file_name( $data['code'] ) . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Builds data for a certificate.
	 *
	 * @param int $certificate_id Certificate post ID.
	 * @return array
	 */
	public static function get_certificate_data( $certificate_id ) {
		$course_id  = absint( get_post_meta( $certificate_id, '_certificados_course_id', true ) );
		$user_id    = absint( get_post_meta( $certificate_id, '_certificados_user_id', true ) );
		$user       = get_userdata( $user_id );
		$issue_date = get_post_meta( $certificate_id, '_certificados_issue_date', true );
		$code       = get_post_meta( $certificate_id, '_certificados_code', true );
		$mode       = get_post_meta( $course_id, '_certificados_mode', true );

		return array(
			'participant'      => $user ? $user->display_name : __( 'Participante', 'certificados' ),
			'course'           => $course_id ? get_the_title( $course_id ) : __( 'Curso o taller', 'certificados' ),
			'mode'             => $mode ? $mode : __( 'virtual', 'certificados' ),
			'issue_date'       => $issue_date ? $issue_date : current_time( 'Y-m-d' ),
			'code'             => $code,
			'verification_url' => Certificados_Frontend::get_verification_url( $code ),
		);
	}

	/**
	 * Builds the PDF document body.
	 *
	 * @param array $data Certificate data.
	 * @return string
	 */
	private static function build_pdf( array $data ) {
		$lines = array(
			'CERTIFICADO',
			'',
			'Se certifica que:',
			$data['participant'],
			'',
			'participo satisfactoriamente en:',
			$data['course'],
			'',
			'Modalidad: ' . ucfirst( $data['mode'] ),
			'Fecha de emision: ' . $data['issue_date'],
			'Codigo de validacion: ' . $data['code'],
			'Verificacion: ' . $data['verification_url'],
		);

		$content = "BT\n/F1 24 Tf\n72 760 Td\n(CERTIFICADO) Tj\n";
		$content .= "/F1 12 Tf\n0 -50 Td\n";
		foreach ( array_slice( $lines, 2 ) as $line ) {
			$content .= '(' . self::escape_pdf_text( $line ) . ") Tj\n0 -24 Td\n";
		}
		$content .= 'ET';

		$objects   = array();
		$objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
		$objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>';
		$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
		$objects[] = '<< /Length ' . strlen( $content ) . " >>\nstream\n" . $content . "\nendstream";

		$pdf     = "%PDF-1.4\n";
		$offsets = array( 0 );
		foreach ( $objects as $index => $object ) {
			$offsets[] = strlen( $pdf );
			$pdf     .= ( $index + 1 ) . " 0 obj\n" . $object . "\nendobj\n";
		}

		$xref_position = strlen( $pdf );
		$pdf          .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n";
		$pdf          .= "0000000000 65535 f \n";
		foreach ( array_slice( $offsets, 1 ) as $offset ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offset );
		}
		$pdf .= "trailer\n<< /Size " . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\n";
		$pdf .= "startxref\n" . $xref_position . "\n%%EOF";

		return $pdf;
	}

	/**
	 * Escapes text for PDF content streams.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function escape_pdf_text( $text ) {
		$text = remove_accents( wp_strip_all_tags( (string) $text ) );

		return str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $text );
	}
}
