<?php
/**
 * Custom post types.
 *
 * @package Certificados
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers courses and certificates.
 */
final class Certificados_Post_Types {
	const COURSE_POST_TYPE      = 'cert_course';
	const CERTIFICATE_POST_TYPE = 'cert_certificate';

	/**
	 * Singleton instance.
	 *
	 * @var Certificados_Post_Types|null
	 */
	private static $instance = null;

	/**
	 * Returns the module instance.
	 *
	 * @return Certificados_Post_Types
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
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Registers post types.
	 */
	public static function register() {
		register_post_type(
			self::COURSE_POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Cursos y talleres', 'certificados' ),
					'singular_name' => __( 'Curso o taller', 'certificados' ),
					'add_new_item'  => __( 'Agregar curso o taller', 'certificados' ),
					'edit_item'     => __( 'Editar curso o taller', 'certificados' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'menu_icon'    => 'dashicons-welcome-learn-more',
				'supports'     => array( 'title', 'editor' ),
				'capability_type' => 'post',
			)
		);

		register_post_type(
			self::CERTIFICATE_POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Certificados', 'certificados' ),
					'singular_name' => __( 'Certificado', 'certificados' ),
					'add_new_item'  => __( 'Agregar certificado', 'certificados' ),
					'edit_item'     => __( 'Editar certificado', 'certificados' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=' . self::COURSE_POST_TYPE,
				'supports'     => array( 'title' ),
				'capability_type' => 'post',
			)
		);
	}
}
