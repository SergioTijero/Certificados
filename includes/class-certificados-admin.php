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
		add_action( 'admin_menu', array( $this, 'add_bulk_assignment_page' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'save_post_' . Certificados_Post_Types::COURSE_POST_TYPE, array( $this, 'save_course' ) );
		add_action( 'save_post_' . Certificados_Post_Types::CERTIFICATE_POST_TYPE, array( $this, 'save_certificate' ) );
		add_action( 'save_post_' . Certificados_Post_Types::REQUEST_POST_TYPE, array( $this, 'save_request' ) );
		add_action( 'admin_notices', array( $this, 'woocommerce_notice' ) );
		add_filter( 'manage_' . Certificados_Post_Types::CERTIFICATE_POST_TYPE . '_posts_columns', array( $this, 'certificate_columns' ) );
		add_action( 'manage_' . Certificados_Post_Types::CERTIFICATE_POST_TYPE . '_posts_custom_column', array( $this, 'render_certificate_column' ), 10, 2 );
		add_filter( 'manage_' . Certificados_Post_Types::REQUEST_POST_TYPE . '_posts_columns', array( $this, 'request_columns' ) );
		add_action( 'manage_' . Certificados_Post_Types::REQUEST_POST_TYPE . '_posts_custom_column', array( $this, 'render_request_column' ), 10, 2 );
		add_action( 'admin_post_certificados_download_pdf', array( $this, 'download_certificate_pdf' ) );
		add_action( 'admin_post_certificados_bulk_assign', array( $this, 'handle_bulk_assignment' ) );
		add_action( 'wp_ajax_certificados_search_customers', array( $this, 'ajax_search_customers' ) );
	}

	/**
	 * Adds bulk assignment submenu.
	 */
	public function add_bulk_assignment_page() {
		add_submenu_page(
			'edit.php?post_type=' . Certificados_Post_Types::COURSE_POST_TYPE,
			__( 'Asignar certificados', 'certificados' ),
			__( 'Asignar certificados', 'certificados' ),
			'publish_cert_certificates',
			'certificados-bulk-assign',
			array( $this, 'render_bulk_assignment_page' )
		);
	}

	/**
	 * Loads admin scripts for customer search.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'cert' ) ) {
			return;
		}

		wp_register_script( 'certificados-admin', false, array(), CERTIFICADOS_VERSION, true );
		wp_enqueue_script( 'certificados-admin' );
		wp_localize_script(
			'certificados-admin',
			'CertificadosAdmin',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'certificados_search_customers' ),
				'searchingText' => __( 'Buscando...', 'certificados' ),
				'emptyText'     => __( 'No se encontraron clientes.', 'certificados' ),
				'removeText'    => __( 'Quitar', 'certificados' ),
			)
		);
		wp_add_inline_script( 'certificados-admin', $this->get_customer_search_script() );
		wp_register_style( 'certificados-admin', false, array(), CERTIFICADOS_VERSION );
		wp_enqueue_style( 'certificados-admin' );
		wp_add_inline_style( 'certificados-admin', $this->get_admin_styles() );
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

		add_meta_box(
			'certificados_request_details',
			__( 'Datos de la solicitud', 'certificados' ),
			array( $this, 'render_request_box' ),
			Certificados_Post_Types::REQUEST_POST_TYPE,
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
			<label for="certificados_start_date"><strong><?php esc_html_e( 'Fecha de inicio referencial', 'certificados' ); ?></strong></label><br>
			<input type="date" id="certificados_start_date" name="certificados_start_date" value="<?php echo esc_attr( $start_date ); ?>">
		</p>
		<p>
			<label for="certificados_end_date"><strong><?php esc_html_e( 'Fecha de finalización referencial', 'certificados' ); ?></strong></label><br>
			<input type="date" id="certificados_end_date" name="certificados_end_date" value="<?php echo esc_attr( $end_date ); ?>">
		</p>
		<p class="description"><?php esc_html_e( 'Estas fechas son opcionales. Para cursos recurrentes, usa la fecha de emisión de cada certificado.', 'certificados' ); ?></p>
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
		$message    = get_post_meta( $post->ID, '_certificados_message', true );
		if ( ! $issue_date ) {
			$issue_date = current_time( 'Y-m-d' );
		}
		if ( '' === $message ) {
			$message = $this->get_default_certificate_message();
		}
		$code       = get_post_meta( $post->ID, '_certificados_code', true );
		$courses    = get_posts(
			array(
				'post_type'      => Certificados_Post_Types::COURSE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$user       = $user_id ? get_userdata( $user_id ) : false;

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
			<?php $this->render_customer_search_control( 'certificados_user_id', $user, false ); ?>
		</p>
		<p>
			<label for="certificados_issue_date"><strong><?php esc_html_e( 'Fecha de emisión', 'certificados' ); ?></strong></label><br>
			<input type="date" id="certificados_issue_date" name="certificados_issue_date" value="<?php echo esc_attr( $issue_date ); ?>">
		</p>
		<p>
			<label for="certificados_message"><strong><?php esc_html_e( 'Mensaje del certificado', 'certificados' ); ?></strong></label><br>
			<textarea id="certificados_message" name="certificados_message" class="large-text" rows="4"><?php echo esc_textarea( $message ); ?></textarea>
		</p>
		<p class="description"><?php esc_html_e( 'Este texto se imprime en el PDF debajo del nombre del participante. Puedes ajustarlo por certificado.', 'certificados' ); ?></p>
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
	 * Renders the bulk assignment page.
	 */
	public function render_bulk_assignment_page() {
		if ( ! current_user_can( 'publish_cert_certificates' ) ) {
			wp_die(
				esc_html__( 'No tienes permiso para asignar certificados.', 'certificados' ),
				esc_html__( 'Permiso denegado', 'certificados' ),
				array( 'response' => 403 )
			);
		}

		$courses = get_posts(
			array(
				'post_type'      => Certificados_Post_Types::COURSE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Asignar certificados en bloque', 'certificados' ); ?></h1>
			<p><?php esc_html_e( 'Selecciona un curso, busca clientes por nombre o correo y crea un certificado para cada participante.', 'certificados' ); ?></p>

			<?php if ( isset( $_GET['created'] ) ) : ?>
				<div class="notice notice-success"><p>
					<?php
					printf(
						/* translators: 1: created certificates, 2: skipped certificates. */
						esc_html__( 'Certificados creados: %1$d. Omitidos por duplicado para la misma fecha: %2$d.', 'certificados' ),
						absint( wp_unslash( $_GET['created'] ) ),
						isset( $_GET['skipped'] ) ? absint( wp_unslash( $_GET['skipped'] ) ) : 0
					);
					?>
				</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="certificados_bulk_assign">
				<?php wp_nonce_field( 'certificados_bulk_assign', 'certificados_bulk_assign_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="certificados_bulk_course_id"><?php esc_html_e( 'Curso o taller', 'certificados' ); ?></label></th>
						<td>
							<select id="certificados_bulk_course_id" name="certificados_course_id" required>
								<option value=""><?php esc_html_e( 'Seleccionar curso', 'certificados' ); ?></option>
								<?php foreach ( $courses as $course ) : ?>
									<option value="<?php echo esc_attr( $course->ID ); ?>"><?php echo esc_html( get_the_title( $course ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="certificados_bulk_issue_date"><?php esc_html_e( 'Fecha de emisión', 'certificados' ); ?></label></th>
						<td><input type="date" id="certificados_bulk_issue_date" name="certificados_issue_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="certificados_bulk_message"><?php esc_html_e( 'Mensaje del certificado', 'certificados' ); ?></label></th>
						<td>
							<textarea id="certificados_bulk_message" name="certificados_message" class="large-text" rows="4"><?php echo esc_textarea( $this->get_default_certificate_message() ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Se usará para todos los certificados creados en esta asignación.', 'certificados' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Clientes', 'certificados' ); ?></th>
						<td>
							<?php $this->render_customer_search_control( 'certificados_user_ids', null, true ); ?>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Crear certificados', 'certificados' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders request details and approval action.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_request_box( $post ) {
		$user_id        = absint( get_post_meta( $post->ID, '_certificados_request_user_id', true ) );
		$full_name      = get_post_meta( $post->ID, '_certificados_request_full_name', true );
		$email          = get_post_meta( $post->ID, '_certificados_request_email', true );
		$requested      = get_post_meta( $post->ID, '_certificados_request_course', true );
		$requested_id   = absint( get_post_meta( $post->ID, '_certificados_request_course_id', true ) );
		$status         = get_post_meta( $post->ID, '_certificados_request_status', true );
		$certificate_id = absint( get_post_meta( $post->ID, '_certificados_request_certificate_id', true ) );
		$courses        = get_posts(
			array(
				'post_type'      => Certificados_Post_Types::COURSE_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		echo '<table class="form-table" role="presentation">';
		echo '<tr><th>' . esc_html__( 'Cliente', 'certificados' ) . '</th><td>' . esc_html( $full_name ) . ' <code>' . esc_html( $email ) . '</code></td></tr>';
		echo '<tr><th>' . esc_html__( 'Usuario', 'certificados' ) . '</th><td>' . ( $user_id ? esc_html( '#' . $user_id ) : '&mdash;' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Curso indicado', 'certificados' ) . '</th><td>' . esc_html( $requested ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Estado', 'certificados' ) . '</th><td><strong>' . esc_html( $status ? $status : __( 'pending', 'certificados' ) ) . '</strong></td></tr>';
		echo '</table>';

		if ( $certificate_id ) {
			echo '<p><a class="button button-primary" href="' . esc_url( get_edit_post_link( $certificate_id ) ) . '">' . esc_html__( 'Ver certificado creado', 'certificados' ) . '</a></p>';
			return;
		}

		echo '<hr>';
		echo '<h3>' . esc_html__( 'Aprobar y crear certificado', 'certificados' ) . '</h3>';
		wp_nonce_field( 'certificados_approve_request_' . $post->ID, 'certificados_approve_request_nonce' );
		echo '<p><label for="certificados_request_course_id"><strong>' . esc_html__( 'Curso real', 'certificados' ) . '</strong></label><br>';
		echo '<select id="certificados_request_course_id" name="certificados_course_id" required>';
		echo '<option value="">' . esc_html__( 'Seleccionar curso', 'certificados' ) . '</option>';
		foreach ( $courses as $course ) {
			echo '<option value="' . esc_attr( $course->ID ) . '" ' . selected( $requested_id, $course->ID, false ) . '>' . esc_html( get_the_title( $course ) ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><label for="certificados_request_issue_date"><strong>' . esc_html__( 'Fecha de emisión', 'certificados' ) . '</strong></label><br>';
		echo '<input type="date" id="certificados_request_issue_date" name="certificados_issue_date" value="' . esc_attr( current_time( 'Y-m-d' ) ) . '" required></p>';
		echo '<p><label for="certificados_request_message"><strong>' . esc_html__( 'Mensaje del certificado', 'certificados' ) . '</strong></label><br>';
		echo '<textarea id="certificados_request_message" name="certificados_message" class="large-text" rows="4">' . esc_textarea( $this->get_default_certificate_message() ) . '</textarea></p>';
		submit_button( __( 'Aprobar y crear certificado', 'certificados' ), 'primary', 'certificados_approve_request_submit', false );
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
		$message    = $this->sanitize_message( 'certificados_message' );

		if ( Certificados_Post_Types::COURSE_POST_TYPE !== get_post_type( $course_id ) ) {
			$course_id = 0;
		}

		if ( ! get_userdata( $user_id ) ) {
			$user_id = 0;
		}

		update_post_meta( $post_id, '_certificados_course_id', $course_id );
		update_post_meta( $post_id, '_certificados_user_id', $user_id );
		update_post_meta( $post_id, '_certificados_issue_date', $issue_date );
		update_post_meta( $post_id, '_certificados_message', $message );

		if ( ! get_post_meta( $post_id, '_certificados_code', true ) ) {
			update_post_meta( $post_id, '_certificados_code', $this->generate_unique_code() );
		}
	}

	/**
	 * Saves a request approval from the native WordPress edit form.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_request( $post_id ) {
		if ( empty( $_POST['certificados_approve_request_submit'] ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if (
			! isset( $_POST['certificados_approve_request_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['certificados_approve_request_nonce'] ) ), 'certificados_approve_request_' . $post_id )
		) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( get_post_meta( $post_id, '_certificados_request_certificate_id', true ) ) {
			return;
		}

		$this->approve_request_from_post( $post_id );
	}

	/**
	 * Handles bulk certificate creation.
	 */
	public function handle_bulk_assignment() {
		if ( ! current_user_can( 'publish_cert_certificates' ) ) {
			wp_die(
				esc_html__( 'No tienes permiso para asignar certificados.', 'certificados' ),
				esc_html__( 'Permiso denegado', 'certificados' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( 'certificados_bulk_assign', 'certificados_bulk_assign_nonce' );

		$course_id  = isset( $_POST['certificados_course_id'] ) ? absint( wp_unslash( $_POST['certificados_course_id'] ) ) : 0;
		$issue_date = $this->sanitize_date( 'certificados_issue_date' );
		$message    = $this->sanitize_message( 'certificados_message' );
		$user_ids   = isset( $_POST['certificados_user_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['certificados_user_ids'] ) ) : array();

		if ( Certificados_Post_Types::COURSE_POST_TYPE !== get_post_type( $course_id ) ) {
			$course_id = 0;
		}

		$created = 0;
		$skipped = 0;
		foreach ( array_unique( array_filter( $user_ids ) ) as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $course_id || ! $user ) {
				continue;
			}

			if ( $this->certificate_exists_for_user( $course_id, $user_id, $issue_date ) ) {
				$skipped++;
				continue;
			}

			$certificate_id = wp_insert_post(
				array(
					'post_type'   => Certificados_Post_Types::CERTIFICATE_POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => sprintf(
						/* translators: 1: course title, 2: customer name. */
						__( '%1$s - %2$s', 'certificados' ),
						get_the_title( $course_id ),
						Certificados_PDF::get_user_certificate_name( $user_id )
					),
				)
			);

			if ( is_wp_error( $certificate_id ) || ! $certificate_id ) {
				continue;
			}

			update_post_meta( $certificate_id, '_certificados_course_id', $course_id );
			update_post_meta( $certificate_id, '_certificados_user_id', $user_id );
			update_post_meta( $certificate_id, '_certificados_issue_date', $issue_date );
			update_post_meta( $certificate_id, '_certificados_message', $message );
			update_post_meta( $certificate_id, '_certificados_code', $this->generate_unique_code() );
			$created++;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => Certificados_Post_Types::COURSE_POST_TYPE,
					'page'      => 'certificados-bulk-assign',
					'created'   => $created,
					'skipped'   => $skipped,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Searches customers by name or email.
	 */
	public function ajax_search_customers() {
		if ( ! current_user_can( 'edit_cert_certificates' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permiso denegado.', 'certificados' ) ), 403 );
		}

		check_ajax_referer( 'certificados_search_customers', 'nonce' );

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( array() );
		}

		$args = array(
			'number'         => 20,
			'orderby'        => 'display_name',
			'search'         => '*' . $term . '*',
			'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
			'fields'         => array( 'ID', 'display_name', 'user_email' ),
		);

		if ( get_role( 'customer' ) ) {
			$args['role__in'] = array( 'customer' );
		}

		$results = array();
		foreach ( get_users( $args ) as $user ) {
			$results[] = array(
				'id'    => absint( $user->ID ),
				'label' => sprintf( '%1$s (%2$s)', $user->display_name, $user->user_email ),
			);
		}

		wp_send_json_success( $results );
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
		$columns['certificados_status']     = __( 'Estado', 'certificados' );
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

			case 'certificados_status':
				if ( Certificados_Frontend::is_certificate_publicly_validatable( $post_id ) ) {
					echo '<span style="color:#008a20;font-weight:600;">' . esc_html__( 'Listo', 'certificados' ) . '</span>';
				} else {
					echo '<span style="color:#b32d2e;font-weight:600;">' . esc_html__( 'Incompleto', 'certificados' ) . '</span>';
				}
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
	 * Adds useful columns to the request list.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function request_columns( $columns ) {
		$date = isset( $columns['date'] ) ? $columns['date'] : __( 'Fecha', 'certificados' );
		unset( $columns['date'] );

		$columns['certificados_request_customer'] = __( 'Cliente', 'certificados' );
		$columns['certificados_request_course']   = __( 'Curso indicado', 'certificados' );
		$columns['certificados_request_status']   = __( 'Estado', 'certificados' );
		$columns['certificados_request_result']   = __( 'Resultado', 'certificados' );
		$columns['date']                          = $date;

		return $columns;
	}

	/**
	 * Renders request list column values.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Request post ID.
	 */
	public function render_request_column( $column, $post_id ) {
		switch ( $column ) {
			case 'certificados_request_customer':
				$name  = get_post_meta( $post_id, '_certificados_request_full_name', true );
				$email = get_post_meta( $post_id, '_certificados_request_email', true );
				echo esc_html( $name ) . '<br><code>' . esc_html( $email ) . '</code>';
				break;

			case 'certificados_request_course':
				echo esc_html( get_post_meta( $post_id, '_certificados_request_course', true ) );
				break;

			case 'certificados_request_status':
				$status = get_post_meta( $post_id, '_certificados_request_status', true );
				echo esc_html( $status ? $status : __( 'pending', 'certificados' ) );
				break;

			case 'certificados_request_result':
				$certificate_id = absint( get_post_meta( $post_id, '_certificados_request_certificate_id', true ) );
				echo $certificate_id ? '<a href="' . esc_url( get_edit_post_link( $certificate_id ) ) . '">' . esc_html__( 'Certificado creado', 'certificados' ) . '</a>' : '&mdash;';
				break;
		}
	}

	/**
	 * Approves a customer request and creates the certificate.
	 *
	 * @param int $request_id Request post ID.
	 */
	private function approve_request_from_post( $request_id ) {
		$user_id    = absint( get_post_meta( $request_id, '_certificados_request_user_id', true ) );
		$course_id  = isset( $_POST['certificados_course_id'] ) ? absint( wp_unslash( $_POST['certificados_course_id'] ) ) : 0;
		$issue_date = $this->sanitize_date( 'certificados_issue_date' );
		$message    = $this->sanitize_message( 'certificados_message' );
		$user       = get_userdata( $user_id );

		if ( ! $user || Certificados_Post_Types::COURSE_POST_TYPE !== get_post_type( $course_id ) ) {
			wp_die(
				esc_html__( 'La solicitud no tiene un usuario válido o falta seleccionar un curso válido.', 'certificados' ),
				esc_html__( 'Solicitud incompleta', 'certificados' ),
				array( 'response' => 400 )
			);
		}

		$certificate_id = wp_insert_post(
			array(
				'post_type'   => Certificados_Post_Types::CERTIFICATE_POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf(
					/* translators: 1: course title, 2: customer name. */
					__( '%1$s - %2$s', 'certificados' ),
					get_the_title( $course_id ),
					Certificados_PDF::get_user_certificate_name( $user_id )
				),
			)
		);

		if ( is_wp_error( $certificate_id ) || ! $certificate_id ) {
			wp_die(
				esc_html__( 'No se pudo crear el certificado.', 'certificados' ),
				esc_html__( 'Error al crear certificado', 'certificados' ),
				array( 'response' => 500 )
			);
		}

		update_post_meta( $certificate_id, '_certificados_course_id', $course_id );
		update_post_meta( $certificate_id, '_certificados_user_id', $user_id );
		update_post_meta( $certificate_id, '_certificados_issue_date', $issue_date );
		update_post_meta( $certificate_id, '_certificados_message', $message );
		update_post_meta( $certificate_id, '_certificados_code', $this->generate_unique_code() );

		update_post_meta( $request_id, '_certificados_request_status', 'approved' );
		update_post_meta( $request_id, '_certificados_request_certificate_id', $certificate_id );
		remove_action( 'save_post_' . Certificados_Post_Types::REQUEST_POST_TYPE, array( $this, 'save_request' ) );
		wp_update_post(
			array(
				'ID'          => $request_id,
				'post_status' => 'publish',
			)
		);
		add_action( 'save_post_' . Certificados_Post_Types::REQUEST_POST_TYPE, array( $this, 'save_request' ) );
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
	 * Sanitizes a certificate message field.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private function sanitize_message( $key ) {
		$value = isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '';

		return '' !== trim( $value ) ? $value : $this->get_default_certificate_message();
	}

	/**
	 * Returns the default certificate message.
	 *
	 * @return string
	 */
	private function get_default_certificate_message() {
		return __( 'Quien culminó exitosamente el curso presencial de elaboración de CERVEZA ARTESANAL, teórico y práctico por 8 horas en la sede central de THE HOMEBREWER PERU.', 'certificados' );
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
	 * Checks whether a customer already has a certificate for a course.
	 *
	 * @param int $course_id Course post ID.
	 * @param int    $user_id User ID.
	 * @param string $issue_date Certificate issue date.
	 * @return bool
	 */
	private function certificate_exists_for_user( $course_id, $user_id, $issue_date ) {
		$certificates = get_posts(
			array(
				'post_type'      => Certificados_Post_Types::CERTIFICATE_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_certificados_course_id',
						'value' => absint( $course_id ),
					),
					array(
						'key'   => '_certificados_user_id',
						'value' => absint( $user_id ),
					),
					array(
						'key'   => '_certificados_issue_date',
						'value' => $issue_date,
					),
				),
			)
		);

		return ! empty( $certificates );
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

	/**
	 * Renders a customer AJAX search field.
	 *
	 * @param string       $field_name Field name.
	 * @param WP_User|null $selected_user Selected user.
	 * @param bool         $multiple Whether multiple users can be selected.
	 */
	private function render_customer_search_control( $field_name, $selected_user = null, $multiple = false ) {
		?>
		<div class="certificados-customer-picker" data-multiple="<?php echo $multiple ? '1' : '0'; ?>" data-field-name="<?php echo esc_attr( $field_name ); ?>">
			<input type="search" class="regular-text certificados-customer-search" placeholder="<?php esc_attr_e( 'Buscar por nombre o correo...', 'certificados' ); ?>" autocomplete="off">
			<div class="certificados-customer-results" aria-live="polite"></div>
			<div class="certificados-customer-selected">
				<?php if ( $selected_user ) : ?>
					<span class="certificados-selected-customer">
						<?php echo esc_html( sprintf( '%1$s (%2$s)', $selected_user->display_name, $selected_user->user_email ) ); ?>
						<button type="button" class="button-link certificados-remove-customer"><?php esc_html_e( 'Quitar', 'certificados' ); ?></button>
						<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $selected_user->ID ); ?>">
					</span>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Returns customer search JavaScript.
	 *
	 * @return string
	 */
	private function get_customer_search_script() {
		return <<<'JS'
(function () {
	function debounce(fn, delay) {
		var timer;
		return function () {
			var args = arguments;
			clearTimeout(timer);
			timer = setTimeout(function () { fn.apply(null, args); }, delay);
		};
	}

	document.addEventListener('click', function (event) {
		if (event.target.classList.contains('certificados-customer-result')) {
			var picker = event.target.closest('.certificados-customer-picker');
			var selected = picker.querySelector('.certificados-customer-selected');
			var multiple = picker.dataset.multiple === '1';
			var fieldName = picker.dataset.fieldName + (multiple ? '[]' : '');
			if (!multiple) {
				selected.innerHTML = '';
			}
			if (selected.querySelector('input[value="' + event.target.dataset.id + '"]')) {
				return;
			}
			var item = document.createElement('span');
			item.className = 'certificados-selected-customer';
			item.textContent = event.target.textContent + ' ';
			var remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'button-link certificados-remove-customer';
			remove.textContent = CertificadosAdmin.removeText;
			var input = document.createElement('input');
			input.type = 'hidden';
			input.name = fieldName;
			input.value = event.target.dataset.id;
			item.appendChild(remove);
			item.appendChild(input);
			selected.appendChild(item);
			picker.querySelector('.certificados-customer-results').innerHTML = '';
			picker.querySelector('.certificados-customer-search').value = '';
		}

		if (event.target.classList.contains('certificados-remove-customer')) {
			event.target.closest('.certificados-selected-customer').remove();
		}
	});

	document.querySelectorAll('.certificados-customer-search').forEach(function (input) {
		input.addEventListener('input', debounce(function () {
			var picker = input.closest('.certificados-customer-picker');
			var results = picker.querySelector('.certificados-customer-results');
			var term = input.value.trim();
			if (term.length < 2) {
				results.innerHTML = '';
				return;
			}
			results.innerHTML = '<p>' + CertificadosAdmin.searchingText + '</p>';
			fetch(CertificadosAdmin.ajaxUrl + '?action=certificados_search_customers&nonce=' + encodeURIComponent(CertificadosAdmin.nonce) + '&term=' + encodeURIComponent(term), {
				credentials: 'same-origin'
			})
				.then(function (response) { return response.json(); })
				.then(function (payload) {
					results.innerHTML = '';
					if (!payload.success || !payload.data.length) {
						results.innerHTML = '<p>' + CertificadosAdmin.emptyText + '</p>';
						return;
					}
					payload.data.forEach(function (customer) {
						var button = document.createElement('button');
						button.type = 'button';
						button.className = 'button certificados-customer-result';
						button.dataset.id = customer.id;
						button.textContent = customer.label;
						results.appendChild(button);
					});
				});
		}, 250));
	});
}());
JS;
	}

	/**
	 * Returns admin CSS.
	 *
	 * @return string
	 */
	private function get_admin_styles() {
		return '.certificados-customer-results{margin-top:8px;display:flex;gap:6px;flex-wrap:wrap}.certificados-customer-selected{margin-top:8px}.certificados-selected-customer{display:inline-flex;gap:6px;align-items:center;margin:0 6px 6px 0;padding:4px 8px;background:#f0f0f1;border-radius:3px}';
	}
}
