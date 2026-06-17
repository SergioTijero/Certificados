<?php
/**
 * Class WC_Email_Certificado_Descargado_Admin.
 *
 * @package Certificados
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WC_Email' ) ) {

	/**
	 * Custom WooCommerce email sent to admin when a certificate is downloaded for the first time.
	 */
	class WC_Email_Certificado_Descargado_Admin extends WC_Email {

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id             = 'certificados_certificado_descargado_admin';
			$this->title          = __( 'Certificado descargado (Admin)', 'certificados' );
			$this->description    = __( 'Este correo electrónico se envía al administrador o gestores cuando un cliente descarga su certificado por primera vez.', 'certificados' );
			$this->template_html  = 'emails/admin-certificado-descargado.php';
			$this->template_plain = 'emails/plain/admin-certificado-descargado.php';
			$this->placeholders   = array(
				'{course_name}'   => '',
				'{customer_name}' => '',
				'{site_title}'    => $this->get_blogname(),
			);

			// Call parent constructor.
			parent::__construct();

			// Default values.
			$this->template_base = CERTIFICADOS_PLUGIN_DIR . 'templates/';
			$this->recipient     = $this->get_option( 'recipient', get_option( 'admin_email' ) );

			$log_file = CERTIFICADOS_PLUGIN_DIR . 'certificados-debug.log';
			file_put_contents( $log_file, date('[Y-m-d H:i:s] ') . "WC_Email_Certificado_Descargado_Admin instantiated\n", FILE_APPEND );

			// Hook action to trigger email.
			add_action( 'certificados_enviar_correo_certificado_descargado_admin_notification', array( $this, 'trigger' ) );
		}

		/**
		 * Triggers the email.
		 *
		 * @param int $certificate_id Certificate ID.
		 */
		public function trigger( $certificate_id ) {
			if ( ! $certificate_id ) {
				return;
			}

			$user_id = absint( get_post_meta( $certificate_id, '_certificados_user_id', true ) );
			$user    = get_userdata( $user_id );
			if ( ! $user ) {
				return;
			}

			$this->object = get_post( $certificate_id );

			$course_id = absint( get_post_meta( $certificate_id, '_certificados_course_id', true ) );
			$course_title = $course_id ? get_the_title( $course_id ) : '';
			$customer_name = $user->display_name;

			$this->placeholders['{course_name}']   = $course_title;
			$this->placeholders['{customer_name}'] = $customer_name;

			if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
				return;
			}

			// Send the email.
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		/**
		 * Returns default subject.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __( '[{site_title}] Primeras descargas: {customer_name} ha descargado su certificado', 'certificados' );
		}

		/**
		 * Returns default heading.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Certificado descargado por primera vez', 'certificados' );
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
					'sent_to_admin' => true,
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
					'sent_to_admin' => true,
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
				'recipient' => array(
					'title'       => __( 'Destinatario(s)', 'woocommerce' ),
					'type'        => 'text',
					'description' => sprintf( __( 'Introduce los destinatarios (separados por comas) para este correo. Por defecto: %s', 'woocommerce' ), '<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>' ),
					'placeholder' => '',
					'default'     => '',
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
