<?php
/**
 * Admin screens and metadata.
 *
 * @package Certificados
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles admin fields for courses and certificates.
 */
final class Certificados_Admin {
	const NONCE_ACTION = 'certificados_save_meta';
	const NONCE_NAME   = 'certificados_meta_nonce';

	/**
	 * Singleton instance.
	 *
	 * @var Certificados_Admin|null
	 */
	private static $instance = null;

	/**
	 * Returns the module instance.
	 *
	 * @return Certificados_Admin
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
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Certificados_Post_Types::COURSE_POST_TYPE, array( $this, 'save_course' ) );
		add_action( 'save_post_' . Certificados_Post_Types::CERTIFICATE_POST_TYPE, array( $this, 'save_certificate' ) );
		add_action( 'admin_notices', array( $this, 'woocommerce_notice' ) );
		add_filter( 'manage_' . Certificados_Post_Types::CERTIFICATE_POST_TYPE . '_posts_columns', array( $this, 'certificate_columns' ) );
		add_action( 'manage_' . Certificados_Post_Types::CERTIFICATE_POST_TYPE . '_posts_custom_column', array( $this, 'render_certificate_column' ), 10, 2 );
		add_action( 'admin_post_certificados_download_pdf', array( $this, 'download_certificate_pdf' ) );
	}

	/**
	 * Adds meta boxes.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'certificados_course_details',
			__( 'Detalles del curso', 'certificados' ),
			array( $this, 'render_course_box' ),
			Certificados_Post_Types::COURSE_POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'certificados_certificate_details',
			__( 'Datos del certificado', 'certificados' ),
			array( $this, 'render_certificate_box' ),
			Certificados_Post_Types::CERTIFICATE_POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Renders course fields.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_course_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$mode       = get_post_meta( $post->ID, '_certificados_mode', true );
		$start_date = get_post_meta( $post->ID, '_certificados_start_date', true );
		$end_date   = get_post_meta( $post->ID, '_certificados_end_date', true );

		?>
		<p>
			<label for="certificados_mode"><strong><?php esc_html_e( 'Modalidad', 'certificados' ); ?></strong></label><br>
			<select id="certificados_mode" name="certificados_mode">
				<option value="virtual" <?php selected( $mode, 'virtual' ); ?>><?php esc_html_e( 'Virtual', 'certificados' ); ?></option>
				<option value="presencial" <?php selected( $mode, 'presencial' ); ?>><?php esc_html_e( 'Presencial', 'certificados' ); ?></option>
			</select>
		</p>
		<p>
			<label for="certificados_start_date"><strong><?php esc_html_e( 'Fecha de inicio', 'certificados' ); ?></strong></label><br>
			<input type="date" id="certificados_start_date" name="certificados_start_date" value="<?php echo esc_attr( $start_date ); ?>">
		</p>
		<p>
			<label for="certificados_end_date"><strong><?php esc_html_e( 'Fecha de finalización', 'certificados' ); ?></strong></label><br>
			<input type="date" id="certificados_end_date" name="certificados_end_date" value="<?php echo esc_attr( $end_date ); ?>">
		</p>
		<?php
	}

	/**
	 * Renders certificate fields.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_certificate_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$course_id  = absint( get_post_meta( $post->ID, '_certificados_course_id', true ) );
		$user_id    = absint( get_post_meta( $post->ID, '_certificados_user_id', true ) );
		$issue_date = get_post_meta( $post->ID, '_certificados_issue_date', true );
		$code       = get_post_meta( $post->ID, '_certificados_code', true );
		$courses    = get_posts(
			array(
				'post_type'      => Certificados_Post_Types::COURSE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$users      = $this->get_assignable_customers( $user_id );

		?>
		<p>
			<label for="certificados_course_id"><strong><?php esc_html_e( 'Curso o taller', 'certificados' ); ?></strong></label><br>
			<select id="certificados_course_id" name="certificados_course_id" required>
				<option value=""><?php esc_html_e( 'Seleccionar curso', 'certificados' ); ?></option>
				<?php foreach ( $courses as $course ) : ?>
					<option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $course_id, $course->ID ); ?>>
						<?php echo esc_html( get_the_title( $course ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="certificados_user_id"><strong><?php esc_html_e( 'Cliente', 'certificados' ); ?></strong></label><br>
			<select id="certificados_user_id" name="certificados_user_id" required>
				<option value=""><?php esc_html_e( 'Seleccionar cliente', 'certificados' ); ?></option>
				<?php foreach ( $users as $user ) : ?>
					<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $user_id, $user->ID ); ?>>
						<?php echo esc_html( sprintf( '%1$s (%2$s)', $user->display_name, $user->user_email ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="certificados_issue_date"><strong><?php esc_html_e( 'Fecha de emisión', 'certificados' ); ?></strong></label><br>
			<input type="date" id="certificados_issue_date" name="certificados_issue_date" value="<?php echo esc_attr( $issue_date ); ?>">
		</p>
		<?php if ( $code ) : ?>
			<?php $verification_url = Certificados_Frontend::get_verification_url( $code ); ?>
			<p>
				<strong><?php esc_html_e( 'Código de validación:', 'certificados' ); ?></strong>
				<code><?php echo esc_html( $code ); ?></code>
			</p>
			<p>
				<img src="<?php echo esc_url( Certificados_Frontend::get_qr_url( $verification_url, 160 ) ); ?>" width="160" height="160" alt="<?php esc_attr_e( 'Código QR de validación', 'certificados' ); ?>">
			</p>
			<p>
				<a href="<?php echo esc_url( $verification_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Ver página pública de validación', 'certificados' ); ?>
				</a>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $this->get_admin_pdf_url( $post->ID ) ); ?>">
					<?php esc_html_e( 'Descargar PDF', 'certificados' ); ?>
				</a>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Saves course metadata.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_course( $post_id ) {
		if ( ! $this->can_save( $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, '_certificados_mode', $this->sanitize_choice( 'certificados_mode', array( 'virtual', 'presencial' ), 'virtual' ) );
		update_post_meta( $post_id, '_certificados_start_date', $this->sanitize_date( 'certificados_start_date' ) );
		update_post_meta( $post_id, '_certificados_end_date', $this->sanitize_date( 'certificados_end_date' ) );
	}

	/**
	 * Saves certificate metadata.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_certificate( $post_id ) {
		if ( ! $this->can_save( $post_id ) ) {
			return;
		}

		$course_id  = isset( $_POST['certificados_course_id'] ) ? absint( wp_unslash( $_POST['certificados_course_id'] ) ) : 0;
		$user_id    = isset( $_POST['certificados_user_id'] ) ? absint( wp_unslash( $_POST['certificados_user_id'] ) ) : 0;
		$issue_date = $this->sanitize_date( 'certificados_issue_date' );

		if ( Certificados_Post_Types::COURSE_POST_TYPE !== get_post_type( $course_id ) ) {
			$course_id = 0;
		}

		if ( ! get_userdata( $user_id ) ) {
			$user_id = 0;
		}

		update_post_meta( $post_id, '_certificados_course_id', $course_id );
		update_post_meta( $post_id, '_certificados_user_id', $user_id );
		update_post_meta( $post_id, '_certificados_issue_date', $issue_date );

		if ( ! get_post_meta( $post_id, '_certificados_code', true ) ) {
			update_post_meta( $post_id, '_certificados_code', $this->generate_unique_code() );
		}
	}

	/**
	 * Shows a WooCommerce dependency notice when needed.
	 */
	public function woocommerce_notice() {
		if ( class_exists( 'WooCommerce' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, Certificados_Post_Types::COURSE_POST_TYPE ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Certificados funciona mejor con WooCommerce activo para mostrar certificados en Mi cuenta.', 'certificados' ) . '</p></div>';
	}

	/**
	 * Adds useful columns to the certificate list.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function certificate_columns( $columns ) {
		$date = isset( $columns['date'] ) ? $columns['date'] : __( 'Fecha', 'certificados' );
		unset( $columns['date'] );

		$columns['certificados_course']     = __( 'Curso', 'certificados' );
		$columns['certificados_customer']   = __( 'Cliente', 'certificados' );
		$columns['certificados_issue_date'] = __( 'Emisión', 'certificados' );
		$columns['certificados_code']       = __( 'Código', 'certificados' );
		$columns['certificados_validate']   = __( 'Validación', 'certificados' );
		$columns['certificados_pdf']        = __( 'PDF', 'certificados' );
		$columns['date']                    = $date;

		return $columns;
	}

	/**
	 * Renders certificate list column values.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Certificate post ID.
	 */
	public function render_certificate_column( $column, $post_id ) {
		switch ( $column ) {
			case 'certificados_course':
				$course_id = absint( get_post_meta( $post_id, '_certificados_course_id', true ) );
				echo $course_id ? esc_html( get_the_title( $course_id ) ) : '&mdash;';
				break;

			case 'certificados_customer':
				$user_id = absint( get_post_meta( $post_id, '_certificados_user_id', true ) );
				$user    = $user_id ? get_userdata( $user_id ) : false;
				echo $user ? esc_html( sprintf( '%1$s (%2$s)', $user->display_name, $user->user_email ) ) : '&mdash;';
				break;

			case 'certificados_issue_date':
				$issue_date = get_post_meta( $post_id, '_certificados_issue_date', true );
				echo $issue_date ? esc_html( $issue_date ) : '&mdash;';
				break;

			case 'certificados_code':
				$code = get_post_meta( $post_id, '_certificados_code', true );
				echo $code ? '<code>' . esc_html( $code ) . '</code>' : '&mdash;';
				break;

			case 'certificados_validate':
				$code = get_post_meta( $post_id, '_certificados_code', true );
				if ( ! $code ) {
					echo '&mdash;';
					break;
				}

				echo '<a href="' . esc_url( Certificados_Frontend::get_verification_url( $code ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Validar', 'certificados' ) . '</a>';
				break;

			case 'certificados_pdf':
				echo '<a href="' . esc_url( $this->get_admin_pdf_url( $post_id ) ) . '">' . esc_html__( 'Descargar', 'certificados' ) . '</a>';
				break;
		}
	}

	/**
	 * Handles administrator PDF downloads.
	 */
	public function download_certificate_pdf() {
		$certificate_id = isset( $_GET['certificate_id'] ) ? absint( wp_unslash( $_GET['certificate_id'] ) ) : 0;

		if ( ! $certificate_id || ! current_user_can( 'edit_post', $certificate_id ) ) {
			wp_die(
				esc_html__( 'No tienes permiso para descargar este certificado.', 'certificados' ),
				esc_html__( 'Permiso denegado', 'certificados' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( 'certificados_admin_pdf_' . $certificate_id );

		Certificados_PDF::stream( $certificate_id );
	}

	/**
	 * Checks save permissions and nonce.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function can_save( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return false;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Sanitizes a date field.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private function sanitize_date( $key ) {
		$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';

		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}

	/**
	 * Sanitizes an allowed choice.
	 *
	 * @param string $key Field key.
	 * @param array  $allowed Allowed values.
	 * @param string $fallback Fallback value.
	 * @return string
	 */
	private function sanitize_choice( $key, array $allowed, $fallback ) {
		$value = isset( $_POST[ $key ] ) ? sanitize_key( wp_unslash( $_POST[ $key ] ) ) : $fallback;

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Returns WooCommerce customers for certificate assignment.
	 *
	 * @param int $selected_user_id Current assigned user ID.
	 * @return WP_User[]
	 */
	private function get_assignable_customers( $selected_user_id ) {
		$args = array(
			'fields'  => array( 'ID', 'display_name', 'user_email' ),
			'orderby' => 'display_name',
		);

		if ( get_role( 'customer' ) ) {
			$args['role__in'] = array( 'customer' );
		}

		$users = get_users( $args );

		if ( $selected_user_id && ! $this->user_exists_in_list( $selected_user_id, $users ) ) {
			$selected_user = get_userdata( $selected_user_id );
			if ( $selected_user ) {
				$users[] = $selected_user;
			}
		}

		return $users;
	}

	/**
	 * Checks if a user ID exists in a user list.
	 *
	 * @param int   $user_id User ID.
	 * @param array $users User list.
	 * @return bool
	 */
	private function user_exists_in_list( $user_id, array $users ) {
		foreach ( $users as $user ) {
			if ( absint( $user->ID ) === absint( $user_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Builds a secure admin PDF download URL.
	 *
	 * @param int $certificate_id Certificate post ID.
	 * @return string
	 */
	private function get_admin_pdf_url( $certificate_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'         => 'certificados_download_pdf',
					'certificate_id' => absint( $certificate_id ),
				),
				admin_url( 'admin-post.php' )
			),
			'certificados_admin_pdf_' . absint( $certificate_id )
		);
	}

	/**
	 * Generates a unique public validation code.
	 *
	 * @return string
	 */
	private function generate_unique_code() {
		do {
			$code  = 'CERT-';
			$code .= strtoupper( wp_generate_password( 10, false, false ) );
			$found = get_posts(
				array(
					'post_type'      => Certificados_Post_Types::CERTIFICATE_POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'meta_key'       => '_certificados_code',
					'meta_value'     => $code,
					'fields'         => 'ids',
				)
			);
		} while ( ! empty( $found ) );

		return $code;
	}
}
