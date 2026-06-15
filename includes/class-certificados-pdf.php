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
	const DEFAULT_LOGO_URL = 'https://thehomebrewerperu.com/wp-content/uploads/2019/12/Logo_thbp.png';
	const PAGE_WIDTH       = 792;
	const PAGE_HEIGHT      = 569;

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
		$message    = get_post_meta( $certificate_id, '_certificados_message', true );
		if ( '' === $message ) {
			$message = self::get_default_message();
		}

		return array(
			'participant'      => $user ? $user->display_name : __( 'Participante', 'certificados' ),
			'course'           => $course_id ? get_the_title( $course_id ) : __( 'Curso o taller', 'certificados' ),
			'mode'             => $mode ? $mode : __( 'virtual', 'certificados' ),
			'issue_date'       => $issue_date ? $issue_date : current_time( 'Y-m-d' ),
			'formatted_date'   => self::format_spanish_date( $issue_date ? $issue_date : current_time( 'Y-m-d' ) ),
			'message'          => $message,
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
		$background = self::get_pdf_image_from_file( CERTIFICADOS_PLUGIN_DIR . 'assets/certificate-background.png' );
		if ( $background ) {
			$images[] = array(
				'name'   => 'BG1',
				'image'  => $background,
				'x'      => 0,
				'y'      => 0,
				'width'  => self::PAGE_WIDTH,
				'height' => self::PAGE_HEIGHT,
			);
		}

		$title = self::get_pdf_image_from_file( CERTIFICADOS_PLUGIN_DIR . 'assets/certificate-title.png' );
		if ( $title ) {
			$title_box = self::fit_image_box( $title['width'], $title['height'], 468, 154 );
			$images[]  = array(
				'name'   => 'TITLE1',
				'image'  => $title,
				'x'      => ( self::PAGE_WIDTH - $title_box['width'] ) / 2,
				'y'      => 286,
				'width'  => $title_box['width'],
				'height' => $title_box['height'],
			);
		}

		$qr_image = self::get_qr_pdf_image( $data['verification_url'] );
		if ( $qr_image ) {
			$images[] = array(
				'name'   => 'QR1',
				'image'  => $qr_image,
				'x'      => 660,
				'y'      => 390,
				'width'  => 50,
				'height' => 50,
			);
		}

		$content = self::build_page_content( $data, $images );
		$objects = array(
			'<< /Type /Catalog /Pages 2 0 R >>',
			'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
			'', // Page object is filled after image object numbers are known.
			'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
			'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
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
		$objects[2] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . '] /Resources ' . $resources . ' /Contents 6 0 R >>';

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
		$text = wp_strip_all_tags( (string) $text );
		if ( function_exists( 'iconv' ) ) {
			$text = @iconv( 'UTF-8', 'windows-1252//IGNORE', $text );
		} else {
			$text = remove_accents( $text );
		}

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
		$content = '';

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

		$content .= self::pdf_wrapped_centered_text_color( 'F2', 29, 130, 238, 532, strtoupper( $data['participant'] ), 34, 32, 2, 0.996, 0.698, 0.043 );
		$content .= self::pdf_diamond_rule( 218, 214, 356 );
		$content .= self::pdf_wrapped_centered_text_color( 'F2', 12, 186, 184, 420, $data['message'], 56, 15, 4, 0.08, 0.08, 0.08 );
		$content .= self::pdf_reference_date_line( isset( $data['issue_date'] ) ? $data['issue_date'] : '', isset( $data['formatted_date'] ) ? $data['formatted_date'] : '', 396, 116 );

		$content .= self::pdf_text_color( 'F1', 7, 650, 378, 'Código:', 0.08, 0.08, 0.08 );
		$content .= self::pdf_wrapped_text_color( 'F1', 7, 650, 368, $data['code'], 16, 8, 2, 0.08, 0.08, 0.08 );

		return $content;
	}

	/**
	 * Draws a gold separator rule with diamond endpoints.
	 *
	 * @param int $x Rule X coordinate.
	 * @param int $y Rule Y coordinate.
	 * @param int $width Rule width.
	 * @return string
	 */
	private static function pdf_diamond_rule( $x, $y, $width ) {
		$end_x = $x + $width;

		return sprintf(
			"q\n0.996 0.698 0.043 rg\n%.2F %.2F %.2F 1.5 re f\n%.2F %.2F m %.2F %.2F l %.2F %.2F l %.2F %.2F l h f\n%.2F %.2F m %.2F %.2F l %.2F %.2F l %.2F %.2F l h f\nQ\n",
			$x + 5,
			$y - 0.75,
			$width - 10,
			$x,
			$y,
			$x + 4,
			$y + 4,
			$x + 8,
			$y,
			$x + 4,
			$y - 4,
			$end_x,
			$y,
			$end_x - 4,
			$y + 4,
			$end_x - 8,
			$y,
			$end_x - 4,
			$y - 4
		);
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
		return self::pdf_text_color( $font, $size, $x, $y, $text, 0, 0, 0 );
	}

	/**
	 * Builds one colored PDF text operation.
	 *
	 * @param string $font Font resource name.
	 * @param int    $size Font size.
	 * @param int    $x X coordinate.
	 * @param int    $y Y coordinate.
	 * @param string $text Text.
	 * @param float  $r Red channel.
	 * @param float  $g Green channel.
	 * @param float  $b Blue channel.
	 * @return string
	 */
	private static function pdf_text_color( $font, $size, $x, $y, $text, $r, $g, $b ) {
		return sprintf(
			"q\n%.3F %.3F %.3F rg\nBT\n/%s %d Tf\n%d %d Td\n(%s) Tj\nET\nQ\n",
			$r,
			$g,
			$b,
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
		return self::pdf_wrapped_text_color( $font, $size, $x, $y, $text, $max_chars, $line_height, $max_lines, 0, 0, 0 );
	}

	/**
	 * Builds colored wrapped PDF text operations.
	 *
	 * @param string $font Font resource name.
	 * @param int    $size Font size.
	 * @param int    $x X coordinate.
	 * @param int    $y Y coordinate.
	 * @param string $text Text.
	 * @param int    $max_chars Max characters per line.
	 * @param int    $line_height Distance between lines.
	 * @param int    $max_lines Max number of lines.
	 * @param float  $r Red channel.
	 * @param float  $g Green channel.
	 * @param float  $b Blue channel.
	 * @return string
	 */
	private static function pdf_wrapped_text_color( $font, $size, $x, $y, $text, $max_chars, $line_height, $max_lines, $r, $g, $b ) {
		$content = '';
		foreach ( self::wrap_pdf_text( $text, $max_chars, $max_lines ) as $index => $line ) {
			$content .= self::pdf_text_color( $font, $size, $x, $y - ( $index * $line_height ), $line, $r, $g, $b );
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
		return self::pdf_wrapped_centered_text_color( $font, $size, $x, $y, $width, $text, $max_chars, $line_height, $max_lines, 0, 0, 0 );
	}

	/**
	 * Builds colored centered wrapped PDF text operations.
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
	 * @param float  $r Red channel.
	 * @param float  $g Green channel.
	 * @param float  $b Blue channel.
	 * @return string
	 */
	private static function pdf_wrapped_centered_text_color( $font, $size, $x, $y, $width, $text, $max_chars, $line_height, $max_lines, $r, $g, $b ) {
		$content = '';

		foreach ( self::wrap_pdf_text( $text, $max_chars, $max_lines ) as $index => $line ) {
			$estimated_width = self::estimate_pdf_text_width( $font, $size, $line );
			$line_x          = $x + max( 0, ( $width - $estimated_width ) / 2 );
			$content        .= self::pdf_text_color( $font, $size, (int) $line_x, $y - ( $index * $line_height ), $line, $r, $g, $b );
		}

		return $content;
	}

	/**
	 * Builds the certificate date line with emphasis matching the reference design.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @param string $fallback Fallback formatted date.
	 * @param int    $center_x Center X coordinate.
	 * @param int    $y Y coordinate.
	 * @return string
	 */
	private static function pdf_reference_date_line( $date, $fallback, $center_x, $y ) {
		$parts = self::get_spanish_date_parts( $date );
		if ( ! $parts ) {
			return self::pdf_wrapped_centered_text_color( 'F1', 12, $center_x - 180, $y, 360, 'Lima ' . $fallback, 48, 15, 1, 0.1, 0.1, 0.1 );
		}

		$segments = array(
			array( 'F1', 13, 'Lima', 0.1, 0.1, 0.1 ),
			array( 'F2', 16, $parts['day'], 0.95, 0.53, 0.02 ),
			array( 'F1', 13, 'de', 0.1, 0.1, 0.1 ),
			array( 'F2', 16, $parts['month'], 0.95, 0.53, 0.02 ),
			array( 'F1', 13, 'del', 0.1, 0.1, 0.1 ),
			array( 'F2', 16, $parts['year'], 0.95, 0.53, 0.02 ),
		);

		$segment_gap = 14;
		$total_width = $segment_gap * ( count( $segments ) - 1 );
		foreach ( $segments as $segment ) {
			$total_width += self::estimate_pdf_text_width( $segment[0], $segment[1], $segment[2] );
		}

		$content = '';
		$x       = $center_x - ( $total_width / 2 );
		foreach ( $segments as $segment ) {
			$content .= self::pdf_text_color( $segment[0], $segment[1], (int) $x, $y, $segment[2], $segment[3], $segment[4], $segment[5] );
			$x       += self::estimate_pdf_text_width( $segment[0], $segment[1], $segment[2] ) + $segment_gap;
		}

		return $content;
	}

	/**
	 * Estimates text width for simple Helvetica PDF placement.
	 *
	 * @param string $font Font resource name.
	 * @param int    $size Font size.
	 * @param string $text Text.
	 * @return float
	 */
	private static function estimate_pdf_text_width( $font, $size, $text ) {
		if ( 'F2' === $font ) {
			$factor = ( strtoupper( $text ) === $text ) ? 0.62 : 0.52;
		} else {
			$factor = 0.48;
		}

		return strlen( self::escape_pdf_text( $text ) ) * $size * $factor;
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
		$text  = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
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
	 * Reads and converts a local image into PDF image streams.
	 *
	 * @param string $path Local image path.
	 * @return array|null
	 */
	private static function get_pdf_image_from_file( $path ) {
		if ( ! is_readable( $path ) ) {
			return null;
		}

		$bytes = file_get_contents( $path );
		if ( false === $bytes ) {
			return null;
		}

		return self::image_to_pdf_streams( $bytes );
	}

	/**
	 * Converts a supported image into PDF image stream data.
	 *
	 * @param string $bytes Image bytes.
	 * @return array|null
	 */
	private static function image_to_pdf_streams( $bytes ) {
		$bytes = self::convert_to_compatible_png( $bytes );

		if ( substr( $bytes, 0, 8 ) === "\x89PNG\r\n\x1a\n" ) {
			return self::png_to_pdf_streams( $bytes );
		}

		if ( substr( $bytes, 0, 2 ) === "\xff\xd8" ) {
			return self::jpeg_to_pdf_streams( $bytes );
		}

		return null;
	}

	/**
	 * Converts an image to a compatible PNG format (truecolor, non-interlaced) if needed.
	 *
	 * @param string $bytes Image bytes.
	 * @return string Image bytes (original or converted).
	 */
	private static function convert_to_compatible_png( $bytes ) {
		if ( substr( $bytes, 0, 8 ) === "\x89PNG\r\n\x1a\n" ) {
			$offset = 8;
			while ( $offset + 8 <= strlen( $bytes ) ) {
				$length = unpack( 'N', substr( $bytes, $offset, 4 ) )[1];
				$type   = substr( $bytes, $offset + 4, 4 );
				$data   = substr( $bytes, $offset + 8, $length );
				if ( 'IHDR' === $type ) {
					$header    = unpack( 'Nwidth/Nheight/Cdepth/Ccolor/Ccompression/Cfilter/Cinterlace', $data );
					$depth     = (int) $header['depth'];
					$color     = (int) $header['color'];
					$interlace = (int) $header['interlace'];

					// If it is 8-bit depth, RGB or RGBA, and non-interlaced, it is compatible
					if ( 8 === $depth && in_array( $color, array( 2, 6 ), true ) && 0 === $interlace ) {
						return $bytes;
					}
					break;
				}
				$offset = $offset + 12 + $length;
			}
		}

		if ( substr( $bytes, 0, 2 ) === "\xff\xd8" ) {
			return $bytes;
		}

		if ( function_exists( 'imagecreatefromstring' ) && function_exists( 'imagecreatetruecolor' ) ) {
			$im = @imagecreatefromstring( $bytes );
			if ( $im ) {
				$width     = imagesx( $im );
				$height    = imagesy( $im );
				$truecolor = imagecreatetruecolor( $width, $height );
				if ( $truecolor ) {
					imagealphablending( $truecolor, false );
					imagesavealpha( $truecolor, true );

					// Fill with transparent background
					$transparent = imagecolorallocatealpha( $truecolor, 0, 0, 0, 127 );
					imagefill( $truecolor, 0, 0, $transparent );

					imagecopy( $truecolor, $im, 0, 0, 0, 0, $width, $height );

					ob_start();
					imagepng( $truecolor );
					$converted_bytes = ob_get_clean();

					imagedestroy( $truecolor );
					imagedestroy( $im );

					if ( $converted_bytes ) {
						return $converted_bytes;
					}
				} else {
					imagedestroy( $im );
				}
			}
		}

		return $bytes;
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
		$logo_url = '';

		if ( function_exists( 'get_theme_mod' ) && function_exists( 'wp_get_attachment_image_src' ) ) {
			$logo_id = absint( get_theme_mod( 'custom_logo' ) );
			if ( $logo_id ) {
				$logo = wp_get_attachment_image_src( $logo_id, 'full' );
				if ( is_array( $logo ) && ! empty( $logo[0] ) ) {
					$logo_url = $logo[0];
				}
			}
		}

		if ( empty( $logo_url ) ) {
			$logo_url = self::DEFAULT_LOGO_URL;
		}

		return $logo_url;
	}

	/**
	 * Returns the default certificate message.
	 *
	 * @return string
	 */
	private static function get_default_message() {
		return __( 'Quien culminó exitosamente el curso presencial de elaboración de CERVEZA ARTESANAL, teórico y práctico por 8 horas en la sede central de THE HOMEBREWER PERU.', 'certificados' );
	}

	/**
	 * Formats a date like the reference certificate.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return string
	 */
	private static function format_spanish_date( $date ) {
		$parts = self::get_spanish_date_parts( $date );
		if ( ! $parts ) {
			return $date;
		}

		return $parts['day'] . ' de ' . $parts['month'] . ' del ' . $parts['year'];
	}

	/**
	 * Splits a date into Spanish certificate parts.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return array|null
	 */
	private static function get_spanish_date_parts( $date ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $date, $matches ) ) {
			return null;
		}

		$months = array(
			'01' => 'ENERO',
			'02' => 'FEBRERO',
			'03' => 'MARZO',
			'04' => 'ABRIL',
			'05' => 'MAYO',
			'06' => 'JUNIO',
			'07' => 'JULIO',
			'08' => 'AGOSTO',
			'09' => 'SETIEMBRE',
			'10' => 'OCTUBRE',
			'11' => 'NOVIEMBRE',
			'12' => 'DICIEMBRE',
		);

		$day   = (string) absint( $matches[3] );
		$month = isset( $months[ $matches[2] ] ) ? $months[ $matches[2] ] : $matches[2];

		return array(
			'day'   => $day,
			'month' => $month,
			'year'  => $matches[1],
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
