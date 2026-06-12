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
			wp_die(
				esc_html__( 'Certificado no encontrado.', 'certificados' ),
				esc_html__( 'Certificado no encontrado', 'certificados' ),
				array( 'response' => 404 )
			);
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
			'site_name'        => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : __( 'Certificado', 'certificados' ),
			'logo_url'         => self::get_site_logo_url(),
		);
	}

	/**
	 * Builds the PDF document body.
	 *
	 * @param array $data Certificate data.
	 * @return string
	 */
	private static function build_pdf( array $data ) {
		$images = array();
		$logo   = ! empty( $data['logo_url'] ) ? self::get_pdf_image_from_url( $data['logo_url'] ) : null;
		if ( $logo ) {
			$logo_box = self::fit_image_box( $logo['width'], $logo['height'], 120, 58 );
			$images[] = array(
				'name'   => 'LOGO1',
				'image'  => $logo,
				'x'      => 246 + ( 120 - $logo_box['width'] ) / 2,
				'y'      => 696,
				'width'  => $logo_box['width'],
				'height' => $logo_box['height'],
			);
		}

		$qr_image = self::get_qr_pdf_image( $data['verification_url'] );
		if ( $qr_image ) {
			$images[] = array(
				'name'   => 'QR1',
				'image'  => $qr_image,
				'x'      => 410,
				'y'      => 118,
				'width'  => 128,
				'height' => 128,
			);
		}

		$content = self::build_page_content( $data, $images );
		$objects = array(
			'<< /Type /Catalog /Pages 2 0 R >>',
			'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
			'', // Page object is filled after image object numbers are known.
			'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
			'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
			'<< /Length ' . strlen( $content ) . " >>\nstream\n" . $content . "\nendstream",
		);

		$xobjects = '';
		foreach ( $images as $image_data ) {
			$image_object_number = count( $objects ) + 1;
			$mask_object_number  = ! empty( $image_data['image']['alpha'] ) ? $image_object_number + 1 : 0;
			$xobjects           .= ' /' . $image_data['name'] . ' ' . $image_object_number . ' 0 R';
			$objects[]           = self::build_image_object( $image_data['image'], $mask_object_number );
			if ( $mask_object_number ) {
				$objects[] = self::build_alpha_object( $image_data['image'] );
			}
		}

		$resources = '<< /Font << /F1 4 0 R /F2 5 0 R >>';
		if ( $xobjects ) {
			$resources .= ' /XObject <<' . $xobjects . ' >>';
		}
		$resources .= ' >>';
		$objects[2] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources ' . $resources . ' /Contents 6 0 R >>';

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
	 * Builds the styled certificate page content stream.
	 *
	 * @param array $data Certificate data.
	 * @param array $images PDF image placement data.
	 * @return string
	 */
	private static function build_page_content( array $data, array $images ) {
		$content  = "q\n0.996 0.698 0.043 RG\n4 w\n36 36 540 720 re S\nQ\n";
		$content .= "q\n0.996 0.698 0.043 rg\n36 700 540 56 re f\nQ\n";
		$content .= "q\n0.15 0.15 0.15 RG\n1 w\n50 54 512 628 re S\nQ\n";
		$content .= "q\n0.996 0.698 0.043 rg\n72 608 468 3 re f\nQ\n";

		foreach ( $images as $image ) {
			$content .= sprintf(
				"q\n%.2F 0 0 %.2F %.2F %.2F cm\n/%s Do\nQ\n",
				$image['width'],
				$image['height'],
				$image['x'],
				$image['y'],
				$image['name']
			);
		}

		$content .= self::pdf_text( 'F2', 28, 208, 650, 'CERTIFICADO' );
		$content .= self::pdf_text( 'F1', 12, 72, 724, ! empty( $data['site_name'] ) ? $data['site_name'] : ' ' );
		$content .= self::pdf_text( 'F1', 14, 236, 580, 'Se certifica que:' );
		$content .= self::pdf_wrapped_centered_text( 'F2', 26, 72, 540, 468, $data['participant'], 35, 30, 2 );
		$content .= self::pdf_text( 'F1', 14, 198, 500, 'participo satisfactoriamente en:' );
		$content .= self::pdf_wrapped_centered_text( 'F2', 18, 84, 466, 444, $data['course'], 48, 24, 3 );
		$content .= self::pdf_text( 'F1', 12, 104, 406, 'Modalidad: ' . ucfirst( $data['mode'] ) );
		$content .= self::pdf_text( 'F1', 12, 104, 382, 'Fecha de emision: ' . $data['issue_date'] );
		$content .= self::pdf_text( 'F1', 12, 104, 358, 'Codigo de validacion: ' . $data['code'] );
		$content .= self::pdf_wrapped_text( 'F1', 9, 104, 332, 'Verificacion: ' . $data['verification_url'], 92, 12, 2 );
		if ( self::has_pdf_image( $images, 'QR1' ) ) {
			$content .= self::pdf_text( 'F1', 10, 408, 96, 'Escanea para validar' );
		}

		return $content;
	}

	/**
	 * Builds one PDF text operation.
	 *
	 * @param string $font Font resource name.
	 * @param int    $size Font size.
	 * @param int    $x X coordinate.
	 * @param int    $y Y coordinate.
	 * @param string $text Text.
	 * @return string
	 */
	private static function pdf_text( $font, $size, $x, $y, $text ) {
		return sprintf(
			"BT\n/%s %d Tf\n%d %d Td\n(%s) Tj\nET\n",
			$font,
			$size,
			$x,
			$y,
			self::escape_pdf_text( $text )
		);
	}

	/**
	 * Builds wrapped PDF text operations.
	 *
	 * @param string $font Font resource name.
	 * @param int    $size Font size.
	 * @param int    $x X coordinate.
	 * @param int    $y Y coordinate.
	 * @param string $text Text.
	 * @param int    $max_chars Max characters per line.
	 * @param int    $line_height Distance between lines.
	 * @param int    $max_lines Max number of lines.
	 * @return string
	 */
	private static function pdf_wrapped_text( $font, $size, $x, $y, $text, $max_chars, $line_height, $max_lines ) {
		$content = '';
		foreach ( self::wrap_pdf_text( $text, $max_chars, $max_lines ) as $index => $line ) {
			$content .= self::pdf_text( $font, $size, $x, $y - ( $index * $line_height ), $line );
		}

		return $content;
	}

	/**
	 * Builds centered wrapped PDF text operations.
	 *
	 * @param string $font Font resource name.
	 * @param int    $size Font size.
	 * @param int    $x X coordinate for the text box.
	 * @param int    $y Y coordinate.
	 * @param int    $width Width of the text box.
	 * @param string $text Text.
	 * @param int    $max_chars Max characters per line.
	 * @param int    $line_height Distance between lines.
	 * @param int    $max_lines Max number of lines.
	 * @return string
	 */
	private static function pdf_wrapped_centered_text( $font, $size, $x, $y, $width, $text, $max_chars, $line_height, $max_lines ) {
		$content = '';
		foreach ( self::wrap_pdf_text( $text, $max_chars, $max_lines ) as $index => $line ) {
			$estimated_width = strlen( self::escape_pdf_text( $line ) ) * $size * 0.48;
			$line_x          = $x + max( 0, ( $width - $estimated_width ) / 2 );
			$content        .= self::pdf_text( $font, $size, (int) $line_x, $y - ( $index * $line_height ), $line );
		}

		return $content;
	}

	/**
	 * Wraps text into a limited number of PDF-safe lines.
	 *
	 * @param string $text Text.
	 * @param int    $max_chars Max characters per line.
	 * @param int    $max_lines Max number of lines.
	 * @return array
	 */
	private static function wrap_pdf_text( $text, $max_chars, $max_lines ) {
		$text  = trim( preg_replace( '/\s+/', ' ', remove_accents( wp_strip_all_tags( (string) $text ) ) ) );
		$words = preg_split( '/\s+/', $text );
		$lines = array();
		$line  = '';

		foreach ( $words as $word ) {
			$test = $line ? $line . ' ' . $word : $word;
			if ( strlen( $test ) > $max_chars && $line ) {
				$lines[] = $line;
				$line    = $word;
				if ( count( $lines ) >= $max_lines ) {
					break;
				}
			} else {
				$line = $test;
			}
		}

		if ( $line && count( $lines ) < $max_lines ) {
			$lines[] = $line;
		}

		if ( count( $lines ) === $max_lines && ! empty( $words ) && implode( ' ', $lines ) !== $text ) {
			$last_index          = count( $lines ) - 1;
			$lines[ $last_index ] = rtrim( substr( $lines[ $last_index ], 0, max( 0, $max_chars - 3 ) ) ) . '...';
		}

		return $lines ? $lines : array( '' );
	}

	/**
	 * Checks if an image placement list contains a named image.
	 *
	 * @param array  $images Image placements.
	 * @param string $name Image resource name.
	 * @return bool
	 */
	private static function has_pdf_image( array $images, $name ) {
		foreach ( $images as $image ) {
			if ( $name === $image['name'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Fetches and converts a QR PNG into PDF image streams.
	 *
	 * @param string $verification_url Public verification URL.
	 * @return array|null
	 */
	private static function get_qr_pdf_image( $verification_url ) {
		return self::get_pdf_image_from_url( Certificados_Frontend::get_qr_url( $verification_url, 180 ) );
	}

	/**
	 * Fetches and converts a PNG into PDF image streams.
	 *
	 * @param string $url Image URL.
	 * @return array|null
	 */
	private static function get_pdf_image_from_url( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 8,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		return self::image_to_pdf_streams( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Converts a supported image into PDF image stream data.
	 *
	 * @param string $bytes Image bytes.
	 * @return array|null
	 */
	private static function image_to_pdf_streams( $bytes ) {
		if ( substr( $bytes, 0, 8 ) === "\x89PNG\r\n\x1a\n" ) {
			return self::png_to_pdf_streams( $bytes );
		}

		if ( substr( $bytes, 0, 2 ) === "\xff\xd8" ) {
			return self::jpeg_to_pdf_streams( $bytes );
		}

		return null;
	}

	/**
	 * Converts a non-interlaced 8-bit RGB/RGBA PNG to compressed PDF image streams.
	 *
	 * @param string $png PNG bytes.
	 * @return array|null
	 */
	private static function png_to_pdf_streams( $png ) {
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
				if ( 8 !== $header['depth'] || ! in_array( $header['color'], array( 2, 6 ), true ) || 0 !== $header['interlace'] ) {
					return null;
				}
				$width  = (int) $header['width'];
				$height = (int) $header['height'];
				$color  = (int) $header['color'];
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

		$bytes_per_pixel = 6 === $color ? 4 : 3;
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

			for ( $i = 0; $i < $stride; $i += $bytes_per_pixel ) {
				$rgb   .= chr( $current[ $i ] ) . chr( $current[ $i + 1 ] ) . chr( $current[ $i + 2 ] );
				if ( 4 === $bytes_per_pixel ) {
					$alpha .= chr( $current[ $i + 3 ] );
				}
			}

			$previous = $current;
		}

		return array(
			'width'  => $width,
			'height' => $height,
			'rgb'    => gzcompress( $rgb ),
			'alpha'  => $alpha ? gzcompress( $alpha ) : '',
			'filter' => 'FlateDecode',
		);
	}

	/**
	 * Converts a JPEG to PDF image stream data.
	 *
	 * @param string $jpeg JPEG bytes.
	 * @return array|null
	 */
	private static function jpeg_to_pdf_streams( $jpeg ) {
		$size = function_exists( 'getimagesizefromstring' ) ? getimagesizefromstring( $jpeg ) : false;
		if ( ! $size || empty( $size[0] ) || empty( $size[1] ) ) {
			return null;
		}

		return array(
			'width'  => (int) $size[0],
			'height' => (int) $size[1],
			'rgb'    => $jpeg,
			'alpha'  => '',
			'filter' => 'DCTDecode',
		);
	}

	/**
	 * Builds a PDF image object.
	 *
	 * @param array $image Image stream data.
	 * @param int   $mask_object_number Optional soft mask object number.
	 * @return string
	 */
	private static function build_image_object( array $image, $mask_object_number = 0 ) {
		$mask = $mask_object_number ? ' /SMask ' . absint( $mask_object_number ) . ' 0 R' : '';

		return sprintf(
			"<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /%s /Length %d%s >>\nstream\n%s\nendstream",
			$image['width'],
			$image['height'],
			isset( $image['filter'] ) ? $image['filter'] : 'FlateDecode',
			strlen( $image['rgb'] ),
			$mask,
			$image['rgb']
		);
	}

	/**
	 * Builds a PDF alpha mask object.
	 *
	 * @param array $image Image stream data.
	 * @return string
	 */
	private static function build_alpha_object( array $image ) {
		return sprintf(
			"<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length %d >>\nstream\n%s\nendstream",
			$image['width'],
			$image['height'],
			strlen( $image['alpha'] ),
			$image['alpha']
		);
	}

	/**
	 * Fits an image inside a target box.
	 *
	 * @param int $width Source width.
	 * @param int $height Source height.
	 * @param int $max_width Max width.
	 * @param int $max_height Max height.
	 * @return array
	 */
	private static function fit_image_box( $width, $height, $max_width, $max_height ) {
		$ratio = min( $max_width / max( 1, $width ), $max_height / max( 1, $height ) );

		return array(
			'width'  => $width * $ratio,
			'height' => $height * $ratio,
		);
	}

	/**
	 * Returns the current site logo URL.
	 *
	 * @return string
	 */
	private static function get_site_logo_url() {
		if ( ! function_exists( 'get_theme_mod' ) || ! function_exists( 'wp_get_attachment_image_src' ) ) {
			return '';
		}

		$logo_id = absint( get_theme_mod( 'custom_logo' ) );
		if ( ! $logo_id ) {
			return '';
		}

		$logo = wp_get_attachment_image_src( $logo_id, 'full' );

		return is_array( $logo ) && ! empty( $logo[0] ) ? $logo[0] : '';
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
