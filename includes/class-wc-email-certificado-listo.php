<?php
/**
 * Class WC_Email_Certificado_Listo.
 *
 * @package Certificados
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WC_Email' ) ) {

	/**
	 * Custom WooCommerce email sent when a certificate is published.
	 */
	class WC_Email_Certificado_Listo extends WC_Email {

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id             = 'certificados_certificado_listo';
			$this->title          = __( 'Certificado listo', 'certificados' );
			$this->description    = __( 'Este correo electrónico se envía al cliente cuando su certificado está listo y disponible.', 'certificados' );
			$this->template_html  = 'emails/certificado-listo.php';
			$this->template_plain = 'emails/plain/certificado-listo.php';
			$this->placeholders   = array(
				'{course_name}' => '',
				'{site_title}'  => $this->get_blogname(),
			);

			// Call parent constructor.
			parent::__construct();

			// Default values.
			$this->template_base = CERTIFICADOS_PLUGIN_DIR . 'templates/';

			$log_file = CERTIFICADOS_PLUGIN_DIR . 'certificados-debug.log';
			file_put_contents( $log_file, date('[Y-m-d H:i:s] ') . "WC_Email_Certificado_Listo instantiated\n", FILE_APPEND );

			// Hook action to trigger email.
			add_action( 'certificados_enviar_correo_certificado_listo_notification', array( $this, 'trigger' ) );
		}

		public function trigger( $certificate_id ) {
			$log_file = CERTIFICADOS_PLUGIN_DIR . 'certificados-debug.log';
			file_put_contents( $log_file, date('[Y-m-d H:i:s] ') . "WC_Email_Certificado_Listo::trigger called for ID: $certificate_id\n", FILE_APPEND );

			if ( ! $certificate_id ) {
				file_put_contents( $log_file, date('[Y-m-d H:i:s] ') . " - trigger Error: ID is empty.\n", FILE_APPEND );
				return;
			}

			$user_id = absint( get_post_meta( $certificate_id, '_certificados_user_id', true ) );
			$user    = get_userdata( $user_id );
			if ( ! $user || ! $user->user_email ) {
				file_put_contents( $log_file, date('[Y-m-d H:i:s] ') . " - trigger Error: User or email empty.\n", FILE_APPEND );
				return;
			}

			$this->object = get_post( $certificate_id );
			$this->recipient = $user->user_email;

			$course_id = absint( get_post_meta( $certificate_id, '_certificados_course_id', true ) );
			$course_title = $course_id ? get_the_title( $course_id ) : '';

			$this->placeholders['{course_name}'] = $course_title;

			file_put_contents( $log_file, date('[Y-m-d H:i:s] ') . " - trigger Info: is_enabled: " . ($this->is_enabled() ? 'yes' : 'no') . ", recipient: " . $this->get_recipient() . "\n", FILE_APPEND );

			if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
				return;
			}

			// Send the email.
			$result = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			file_put_contents( $log_file, date('[Y-m-d H:i:s] ') . " - trigger Result: send output was: " . ($result ? 'success' : 'fail') . "\n", FILE_APPEND );
		}

		/**
		 * Returns default subject.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( '¡Tu certificado de {course_name} está listo!', 'certificados' );
		}

		/**
		 * Returns default heading.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( '¡Felicidades por culminar tu curso!', 'certificados' );
		}

		/**
		 * Returns HTML content.
		 *
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				array(
					'certificate'   => $this->object,
					'email_heading' => $this->get_heading(),
					'sent_to_admin' => false,
					'plain_text'    => false,
					'email'         => $this,
				),
				'',
				$this->template_base
			);
		}

		/**
		 * Returns plain text content.
		 *
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				array(
					'certificate'   => $this->object,
					'email_heading' => $this->get_heading(),
					'sent_to_admin' => false,
					'plain_text'    => true,
					'email'         => $this,
				),
				'',
				$this->template_base
			);
		}

		/**
		 * Initialise settings form fields.
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title'   => __( 'Habilitar/Deshabilitar', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Habilitar esta notificación de correo electrónico', 'certificados' ),
					'default' => 'yes',
				),
				'subject' => array(
					'title'       => __( 'Asunto', 'woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => sprintf( __( 'Asunto del correo electrónico. Por defecto: %s', 'certificados' ), $this->get_default_subject() ),
					'placeholder' => $this->get_default_subject(),
					'default'     => '',
				),
				'heading' => array(
					'title'       => __( 'Encabezado del correo electrónico', 'woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => sprintf( __( 'Encabezado principal del correo electrónico. Por defecto: %s', 'certificados' ), $this->get_default_heading() ),
					'placeholder' => $this->get_default_heading(),
					'default'     => '',
				),
				'email_type' => array(
					'title'       => __( 'Tipo de correo electrónico', 'woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Elige el formato de correo electrónico a enviar.', 'woocommerce' ),
					'default'     => 'html',
					'class'       => 'email_type wc-enhanced-select',
					'options'     => $this->get_email_type_options(),
				),
			);
		}
	}
}
