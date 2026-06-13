<?php
/**
 * Frontend, WooCommerce account, and public validation.
 *
 * @package Certificados
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles customer and public views.
 */
final class Certificados_Frontend {
	const ACCOUNT_ENDPOINT = 'certificados';
	const QUERY_VAR        = 'certificado_codigo';

	/**
	 * Singleton instance.
	 *
	 * @var Certificados_Frontend|null
	 */
	private static $instance = null;

	/**
	 * Returns the module instance.
	 *
	 * @return Certificados_Frontend
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_public_routes' ) );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_woocommerce_query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_account_menu_item' ) );
		add_action( 'woocommerce_account_' . self::ACCOUNT_ENDPOINT . '_endpoint', array( $this, 'render_account_certificates' ) );
		add_filter( 'the_title', array( $this, 'account_endpoint_title' ) );
		add_shortcode( 'certificados_validacion', array( $this, 'validation_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Adds rewrite rules.
	 */
	public static function add_rewrite_rules() {
		add_rewrite_endpoint( self::ACCOUNT_ENDPOINT, EP_ROOT | EP_PAGES );
		add_rewrite_rule( '^validar-certificado/([^/]+)/?$', 'index.php?pagename=validar-certificado&' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Adds query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::ACCOUNT_ENDPOINT;

		return $vars;
	}

	/**
	 * Registers WooCommerce endpoint query vars.
	 *
	 * @param array $vars WooCommerce query vars.
	 * @return array
	 */
	public function add_woocommerce_query_vars( $vars ) {
		$vars[ self::ACCOUNT_ENDPOINT ] = self::ACCOUNT_ENDPOINT;

		return $vars;
	}

	/**
	 * Sets the account endpoint page title.
	 *
	 * @param string $title Current title.
	 * @return string
	 */
	public function account_endpoint_title( $title ) {
		if (
			is_admin()
			|| ! in_the_loop()
			|| ! is_main_query()
			|| ! function_exists( 'is_wc_endpoint_url' )
			|| ! is_wc_endpoint_url( self::ACCOUNT_ENDPOINT )
		) {
			return $title;
		}

		return __( 'Certificados', 'certificados' );
	}

	/**
	 * Adds account menu item.
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public function add_account_menu_item( $items ) {
		$logout = array();
		if ( isset( $items['customer-logout'] ) ) {
			$logout = array( 'customer-logout' => $items['customer-logout'] );
			unset( $items['customer-logout'] );
		}

		$items[ self::ACCOUNT_ENDPOINT ] = __( 'Certificados', 'certificados' );

		return array_merge( $items, $logout );
	}

	/**
	 * Handles PDF download and public verification routes.
	 */
	public function handle_public_routes() {
		if ( isset( $_GET['certificados_pdf'] ) ) {
			$this->handle_pdf_download();
		}

		$code = get_query_var( self::QUERY_VAR );
		if ( $code && is_404() ) {
			$this->render_public_verification( sanitize_text_field( $code ) );
		}
	}

	/**
	 * Enqueues frontend styles.
	 */
	public function enqueue_frontend_assets() {
		$code = get_query_var( self::QUERY_VAR );
		if ( $code || ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( self::ACCOUNT_ENDPOINT ) ) ) {
			wp_register_style( 'certificados-frontend', false, array(), CERTIFICADOS_VERSION );
			wp_enqueue_style( 'certificados-frontend' );
			wp_add_inline_style( 'certificados-frontend', $this->get_frontend_styles() );
		}
	}

	/**
	 * Returns frontend CSS styles.
	 *
	 * @return string
	 */
	private function get_frontend_styles() {
		return <<<'CSS'
.certificados-validation {
	display: flex;
	justify-content: center;
	align-items: center;
	padding: 40px 10px;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
	width: 100%;
}
.certificados-validation-card {
	background: #ffffff;
	border-radius: 16px;
	box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.02);
	border: 1px solid rgba(0, 0, 0, 0.06);
	width: 100%;
	max-width: 500px;
	padding: 35px;
	text-align: center;
	position: relative;
	overflow: hidden;
	box-sizing: border-box;
	margin: 0 auto;
}
.certificados-validation-header {
	margin-bottom: 24px;
}
.certificados-validation-icon {
	width: 64px;
	height: 64px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	margin: 0 auto 16px auto;
	font-size: 28px;
	line-height: 1;
}
.certificados-validation-icon.success {
	background: #e6f7ed;
	color: #00a854;
	box-shadow: 0 0 0 8px #f0fbf5;
	animation: pulse-success-cert 2s infinite;
}
.certificados-validation-icon.error {
	background: #fdf2f2;
	color: #de350b;
	box-shadow: 0 0 0 8px #fef7f7;
	animation: pulse-error-cert 2s infinite;
}
@keyframes pulse-success-cert {
	0% { box-shadow: 0 0 0 0 rgba(0, 168, 84, 0.2); }
	70% { box-shadow: 0 0 0 12px rgba(0, 168, 84, 0); }
	100% { box-shadow: 0 0 0 0 rgba(0, 168, 84, 0); }
}
@keyframes pulse-error-cert {
	0% { box-shadow: 0 0 0 0 rgba(222, 53, 11, 0.2); }
	70% { box-shadow: 0 0 0 12px rgba(222, 53, 11, 0); }
	100% { box-shadow: 0 0 0 0 rgba(222, 53, 11, 0); }
}
.certificados-validation-card h1 {
	font-size: 22px;
	font-weight: 700;
	color: #1a1a1a;
	margin: 0 0 8px 0;
	line-height: 1.2;
}
.certificados-validation-card p.subtitle {
	font-size: 13px;
	color: #666666;
	margin: 0;
	line-height: 1.5;
}
.certificados-validation-details {
	background: #f9f9fb;
	border-radius: 12px;
	padding: 20px;
	margin: 24px 0;
	text-align: left;
	border: 1px solid rgba(0, 0, 0, 0.03);
}
.certificados-validation-details dl {
	margin: 0;
	padding: 0;
}
.certificados-validation-details dt {
	font-size: 11px;
	text-transform: uppercase;
	letter-spacing: 0.8px;
	color: #888888;
	margin: 0 0 4px 0;
	font-weight: 600;
}
.certificados-validation-details dd {
	font-size: 15px;
	font-weight: 600;
	color: #2c3e50;
	margin: 0 0 16px 0;
	line-height: 1.4;
}
.certificados-validation-details dd:last-child {
	margin-bottom: 0;
}
.certificados-validation-details code {
	font-family: Menlo, Monaco, Consolas, "Courier New", monospace;
	background: #eaeaea;
	padding: 2px 5px;
	border-radius: 4px;
	font-size: 13px;
	color: #333333;
}
.certificados-validation-qr {
	margin-top: 24px;
}
.certificados-validation-qr img {
	max-width: 140px;
	height: auto;
	padding: 8px;
	background: #ffffff;
	border: 1px solid #eaeaea;
	border-radius: 8px;
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
	display: inline-block;
}
.certificados-validation-form {
	margin-top: 24px;
	text-align: left;
}
.certificados-validation-form label {
	font-size: 13px;
	font-weight: 600;
	color: #444444;
	display: block;
	margin-bottom: 8px;
}
.certificados-validation-form-group {
	display: flex;
	gap: 10px;
}
.certificados-validation-form input[type="text"] {
	flex: 1;
	padding: 10px 14px;
	border: 1.5px solid #dddddd;
	border-radius: 8px;
	font-size: 14px;
	transition: border-color 0.2s ease, box-shadow 0.2s ease;
	outline: none;
	background: #fff;
	color: #333;
}
.certificados-validation-form input[type="text"]:focus {
	border-color: #feb20b;
	box-shadow: 0 0 0 3px rgba(254, 178, 11, 0.15);
}
.certificados-validation-form button {
	background: #feb20b;
	color: #1f1f1f;
	border: none;
	padding: 10px 20px;
	border-radius: 8px;
	font-size: 14px;
	font-weight: 700;
	cursor: pointer;
	transition: background-color 0.2s ease, transform 0.1s ease;
}
.certificados-validation-form button:hover {
	background: #e5a00a;
}
.certificados-validation-form button:active {
	transform: scale(0.98);
}
CSS;
	}

	/**
	 * Renders customer's certificates in WooCommerce My Account.
	 */
	public function render_account_certificates() {
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Inicia sesión para ver tus certificados.', 'certificados' ) . '</p>';
			return;
		}

		$certificates = self::get_user_certificates( get_current_user_id() );

		echo '<h2>' . esc_html__( 'Mis certificados', 'certificados' ) . '</h2>';

		if ( empty( $certificates ) ) {
			echo '<p>' . esc_html__( 'Aún no tienes certificados disponibles.', 'certificados' ) . '</p>';
			return;
		}

		echo '<table class="woocommerce-orders-table shop_table shop_table_responsive">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Curso', 'certificados' ) . '</th>';
		echo '<th>' . esc_html__( 'Fecha', 'certificados' ) . '</th>';
		echo '<th>' . esc_html__( 'Código', 'certificados' ) . '</th>';
		echo '<th>' . esc_html__( 'QR', 'certificados' ) . '</th>';
		echo '<th>' . esc_html__( 'Acciones', 'certificados' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $certificates as $certificate ) {
			$data         = Certificados_PDF::get_certificate_data( $certificate->ID );
			$download_url = wp_nonce_url(
				add_query_arg(
					array(
						'certificados_pdf' => $certificate->ID,
					),
					wc_get_account_endpoint_url( self::ACCOUNT_ENDPOINT )
				),
				'certificados_pdf_' . $certificate->ID
			);

			echo '<tr>';
			echo '<td>' . esc_html( $data['course'] ) . '</td>';
			echo '<td>' . esc_html( $data['issue_date'] ) . '</td>';
			echo '<td><code>' . esc_html( $data['code'] ) . '</code></td>';
			echo '<td><img src="' . esc_url( self::get_qr_url( $data['verification_url'], 96 ) ) . '" width="96" height="96" alt="' . esc_attr__( 'Código QR de validación', 'certificados' ) . '"></td>';
			echo '<td>';
			echo '<a class="button" href="' . esc_url( $download_url ) . '">' . esc_html__( 'Descargar PDF', 'certificados' ) . '</a> ';
			echo '<a class="button" href="' . esc_url( $data['verification_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Validar', 'certificados' ) . '</a>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Returns certificates assigned to a user.
	 *
	 * @param int $user_id User ID.
	 * @return WP_Post[]
	 */
	public static function get_user_certificates( $user_id ) {
		return get_posts(
			array(
				'post_type'      => Certificados_Post_Types::CERTIFICATE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => '_certificados_user_id',
				'meta_value'     => absint( $user_id ),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * Returns public verification URL.
	 *
	 * @param string $code Validation code.
	 * @return string
	 */
	public static function get_verification_url( $code ) {
		return home_url( user_trailingslashit( 'validar-certificado/' . rawurlencode( $code ) ) );
	}

	/**
	 * Returns a QR image URL for a verification link.
	 *
	 * @param string $verification_url Public verification URL.
	 * @param int    $size Image size in pixels.
	 * @return string
	 */
	public static function get_qr_url( $verification_url, $size = 220 ) {
		return add_query_arg(
			array(
				'size'   => absint( $size ),
				'format' => 'png',
				'text'   => $verification_url,
			),
			'https://quickchart.io/qr'
		);
	}

	/**
	 * Handles secure PDF downloads.
	 */
	private function handle_pdf_download() {
		$certificate_id = absint( wp_unslash( $_GET['certificados_pdf'] ) );

		if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'certificados_pdf_' . $certificate_id ) ) {
			wp_die(
				esc_html__( 'Enlace de descarga inválido.', 'certificados' ),
				esc_html__( 'Descarga inválida', 'certificados' ),
				array( 'response' => 403 )
			);
		}

		$user_id = absint( get_post_meta( $certificate_id, '_certificados_user_id', true ) );
		if ( get_current_user_id() !== $user_id && ! current_user_can( 'edit_post', $certificate_id ) ) {
			wp_die(
				esc_html__( 'No tienes permiso para descargar este certificado.', 'certificados' ),
				esc_html__( 'Permiso denegado', 'certificados' ),
				array( 'response' => 403 )
			);
		}

		Certificados_PDF::stream( $certificate_id );
	}

	/**
	 * Renders the public verification page.
	 *
	 * @param string $code Validation code.
	 */
	private function render_public_verification( $code ) {
		status_header( self::find_certificate_by_code( $code ) ? 200 : 404 );
		nocache_headers();

		get_header();

		echo '<main class="certificados-validation" style="max-width: 760px; margin: 40px auto; padding: 0 20px;">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->render_validation_content( $code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</main>';

		get_footer();
		exit;
	}

	/**
	 * Renders validation content through a shortcode for Elementor pages.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function validation_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'codigo' => '',
				'code'   => '',
			),
			$atts,
			'certificados_validacion'
		);

		$code = $atts['codigo'] ? $atts['codigo'] : $atts['code'];
		if ( ! $code && function_exists( 'get_query_var' ) ) {
			$code = get_query_var( self::QUERY_VAR );
		}
		if ( ! $code && isset( $_GET['certificado'] ) ) {
			$code = sanitize_text_field( wp_unslash( $_GET['certificado'] ) );
		}

		return '<div class="certificados-validation">' . $this->render_validation_content( $code ) . '</div>';
	}

	/**
	 * Builds the certificate validation HTML.
	 *
	 * @param string $code Validation code.
	 * @return string
	 */
	private function render_validation_content( $code ) {
		$code        = sanitize_text_field( $code );
		$certificate = $code ? self::find_certificate_by_code( $code ) : null;

		ob_start();

		if ( ! $certificate ) {
			echo '<div class="certificados-validation-card">';
			echo '  <div class="certificados-validation-header">';
			echo '    <div class="certificados-validation-icon error">✕</div>';
			echo '    <h1>' . esc_html__( 'Certificado no encontrado', 'certificados' ) . '</h1>';
			echo '    <p class="subtitle">' . esc_html__( 'El código ingresado no corresponde a un certificado válido o emitido.', 'certificados' ) . '</p>';
			echo '  </div>';
			echo '  <form method="get" class="certificados-validation-form">';
			echo '    <label for="certificados-validacion-codigo">' . esc_html__( 'Código de validación', 'certificados' ) . '</label>';
			echo '    <div class="certificados-validation-form-group">';
			echo '      <input type="text" id="certificados-validacion-codigo" name="certificado" value="' . esc_attr( $code ) . '" placeholder="Ej. CERT-XXXXXX">';
			echo '      <button type="submit">' . esc_html__( 'Validar', 'certificados' ) . '</button>';
			echo '    </div>';
			echo '  </form>';
			echo '</div>';
			return ob_get_clean();
		}

		$data   = Certificados_PDF::get_certificate_data( $certificate->ID );
		$qr_url = self::get_qr_url( $data['verification_url'], 220 );

		echo '<div class="certificados-validation-card">';
		echo '  <div class="certificados-validation-header">';
		echo '    <div class="certificados-validation-icon success">✓</div>';
		echo '    <h1>' . esc_html__( 'Certificado válido', 'certificados' ) . '</h1>';
		echo '    <p class="subtitle">' . esc_html__( 'Este certificado fue emitido por el sitio y se encuentra registrado oficialmente.', 'certificados' ) . '</p>';
		echo '  </div>';
		echo '  <div class="certificados-validation-details">';
		echo '    <dl>';
		echo '      <dt>' . esc_html__( 'Participante', 'certificados' ) . '</dt>';
		echo '      <dd>' . esc_html( $data['participant'] ) . '</dd>';
		echo '      <dt>' . esc_html__( 'Curso o taller', 'certificados' ) . '</dt>';
		echo '      <dd>' . esc_html( $data['course'] ) . '</dd>';
		echo '      <dt>' . esc_html__( 'Fecha de emisión', 'certificados' ) . '</dt>';
		echo '      <dd>' . esc_html( $data['issue_date'] ) . '</dd>';
		echo '      <dt>' . esc_html__( 'Código de validación', 'certificados' ) . '</dt>';
		echo '      <dd><code>' . esc_html( $data['code'] ) . '</code></dd>';
		echo '    </dl>';
		echo '  </div>';
		echo '  <div class="certificados-validation-qr">';
		echo '    <img src="' . esc_url( $qr_url ) . '" width="140" height="140" alt="' . esc_attr__( 'Código QR de validación', 'certificados' ) . '">';
		echo '    <p style="font-size: 11px; color: #888; margin-top: 8px;">' . esc_html__( 'Escanea para volver a validar', 'certificados' ) . '</p>';
		echo '  </div>';
		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * Finds a certificate by public validation code.
	 *
	 * @param string $code Validation code.
	 * @return WP_Post|null
	 */
	private static function find_certificate_by_code( $code ) {
		$certificates = get_posts(
			array(
				'post_type'      => Certificados_Post_Types::CERTIFICATE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => '_certificados_code',
				'meta_value'     => sanitize_text_field( $code ),
			)
		);

		foreach ( $certificates as $certificate ) {
			if ( self::is_certificate_publicly_validatable( $certificate->ID ) ) {
				return $certificate;
			}
		}

		return null;
	}

	/**
	 * Checks whether a certificate can be publicly validated.
	 *
	 * @param int $certificate_id Certificate post ID.
	 * @return bool
	 */
	public static function is_certificate_publicly_validatable( $certificate_id ) {
		$course_id = absint( get_post_meta( $certificate_id, '_certificados_course_id', true ) );
		$user_id   = absint( get_post_meta( $certificate_id, '_certificados_user_id', true ) );

		return (
			$course_id
			&& Certificados_Post_Types::COURSE_POST_TYPE === get_post_type( $course_id )
			&& $user_id
			&& get_userdata( $user_id )
		);
	}
}
