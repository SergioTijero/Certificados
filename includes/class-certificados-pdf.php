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

		$qr_image = self::get_qr_pdf_image( $data['verification_url'] );
		$content = "BT\n/F1 24 Tf\n72 760 Td\n(CERTIFICADO) Tj\n";
		$content .= "/F1 12 Tf\n0 -50 Td\n";
		foreach ( array_slice( $lines, 2 ) as $line ) {
			$content .= '(' . self::escape_pdf_text( $line ) . ") Tj\n0 -24 Td\n";
		}
		$content .= 'ET';
		if ( $qr_image ) {
			$content .= "\nq\n130 0 0 130 410 120 cm\n/QR1 Do\nQ\n";
			$content .= "BT\n/F1 10 Tf\n410 100 Td\n(Escanea para validar) Tj\nET";
		}

		$objects   = array();
		$objects[] = '<< /Type /Catalog /Pages 2 0 R >>'; // 1.
		$objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>'; // 2.
		$resources = '<< /Font << /F1 4 0 R >>';
		if ( $qr_image ) {
			$resources .= ' /XObject << /QR1 6 0 R >>';
		}
		$resources .= ' >>';
		$objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources ' . $resources . ' /Contents 5 0 R >>'; // 3.
		$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'; // 4.
		$objects[] = '<< /Length ' . strlen( $content ) . " >>\nstream\n" . $content . "\nendstream"; // 5.
		if ( $qr_image ) {
			$objects[] = sprintf(
				"<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length %d /SMask 7 0 R >>\nstream\n%s\nendstream",
				$qr_image['width'],
				$qr_image['height'],
				strlen( $qr_image['rgb'] ),
				$qr_image['rgb']
			); // 6.
			$objects[] = sprintf(
				"<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length %d >>\nstream\n%s\nendstream",
				$qr_image['width'],
				$qr_image['height'],
				strlen( $qr_image['alpha'] ),
				$qr_image['alpha']
			); // 7.
		}

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

	/**
	 * Fetches and converts a QR PNG into PDF image streams.
	 *
	 * @param string $verification_url Public verification URL.
	 * @return array|null
	 */
	private static function get_qr_pdf_image( $verification_url ) {
		$response = wp_remote_get(
			Certificados_Frontend::get_qr_url( $verification_url, 180 ),
			array(
				'timeout' => 8,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		return self::png_rgba_to_pdf_streams( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Converts a non-interlaced 8-bit RGBA PNG to compressed PDF image streams.
	 *
	 * @param string $png PNG bytes.
	 * @return array|null
	 */
	private static function png_rgba_to_pdf_streams( $png ) {
		if ( substr( $png, 0, 8 ) !== "\x89PNG\r\n\x1a\n" ) {
			return null;
		}

		$offset = 8;
		$width  = 0;
		$height = 0;
		$idat   = '';

		while ( $offset + 8 <= strlen( $png ) ) {
			$length = unpack( 'N', substr( $png, $offset, 4 ) )[1];
			$type   = substr( $png, $offset + 4, 4 );
			$data   = substr( $png, $offset + 8, $length );
			$offset = $offset + 12 + $length;

			if ( 'IHDR' === $type ) {
				$header = unpack( 'Nwidth/Nheight/Cdepth/Ccolor/Ccompression/Cfilter/Cinterlace', $data );
				if ( 8 !== $header['depth'] || 6 !== $header['color'] || 0 !== $header['interlace'] ) {
					return null;
				}
				$width  = (int) $header['width'];
				$height = (int) $header['height'];
			} elseif ( 'IDAT' === $type ) {
				$idat .= $data;
			} elseif ( 'IEND' === $type ) {
				break;
			}
		}

		if ( ! $width || ! $height || ! $idat ) {
			return null;
		}

		$raw = gzuncompress( $idat );
		if ( false === $raw ) {
			return null;
		}

		$bytes_per_pixel = 4;
		$stride          = $width * $bytes_per_pixel;
		$position        = 0;
		$previous        = array_fill( 0, $stride, 0 );
		$rgb             = '';
		$alpha           = '';

		for ( $row = 0; $row < $height; $row++ ) {
			$filter   = ord( $raw[ $position ] );
			$position++;
			$scanline = array_values( unpack( 'C*', substr( $raw, $position, $stride ) ) );
			$position += $stride;
			$current  = self::unfilter_png_scanline( $scanline, $previous, $bytes_per_pixel, $filter );

			for ( $i = 0; $i < $stride; $i += 4 ) {
				$rgb   .= chr( $current[ $i ] ) . chr( $current[ $i + 1 ] ) . chr( $current[ $i + 2 ] );
				$alpha .= chr( $current[ $i + 3 ] );
			}

			$previous = $current;
		}

		return array(
			'width'  => $width,
			'height' => $height,
			'rgb'    => gzcompress( $rgb ),
			'alpha'  => gzcompress( $alpha ),
		);
	}

	/**
	 * Applies PNG scanline filters.
	 *
	 * @param array $scanline Current filtered scanline.
	 * @param array $previous Previous unfiltered scanline.
	 * @param int   $bpp Bytes per pixel.
	 * @param int   $filter Filter type.
	 * @return array
	 */
	private static function unfilter_png_scanline( array $scanline, array $previous, $bpp, $filter ) {
		$current = array();
		$count   = count( $scanline );

		for ( $i = 0; $i < $count; $i++ ) {
			$left  = $i >= $bpp ? $current[ $i - $bpp ] : 0;
			$up    = $previous[ $i ];
			$upper = $i >= $bpp ? $previous[ $i - $bpp ] : 0;

			switch ( $filter ) {
				case 1:
					$value = $scanline[ $i ] + $left;
					break;
				case 2:
					$value = $scanline[ $i ] + $up;
					break;
				case 3:
					$value = $scanline[ $i ] + floor( ( $left + $up ) / 2 );
					break;
				case 4:
					$value = $scanline[ $i ] + self::paeth_predictor( $left, $up, $upper );
					break;
				default:
					$value = $scanline[ $i ];
					break;
			}

			$current[ $i ] = $value & 0xff;
		}

		return $current;
	}

	/**
	 * PNG Paeth predictor.
	 *
	 * @param int $left Left value.
	 * @param int $up Upper value.
	 * @param int $upper_left Upper-left value.
	 * @return int
	 */
	private static function paeth_predictor( $left, $up, $upper_left ) {
		$p  = $left + $up - $upper_left;
		$pa = abs( $p - $left );
		$pb = abs( $p - $up );
		$pc = abs( $p - $upper_left );

		if ( $pa <= $pb && $pa <= $pc ) {
			return $left;
		}

		return $pb <= $pc ? $up : $upper_left;
	}
}
