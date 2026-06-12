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
				'capabilities' => self::get_course_capabilities(),
				'map_meta_cap' => true,
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
				'capabilities' => self::get_certificate_capabilities(),
				'map_meta_cap' => true,
			)
		);
	}

	/**
	 * Returns course capabilities.
	 *
	 * @return array
	 */
	public static function get_course_capabilities() {
		return self::build_capabilities( 'cert_course', 'cert_courses' );
	}

	/**
	 * Returns certificate capabilities.
	 *
	 * @return array
	 */
	public static function get_certificate_capabilities() {
		return self::build_capabilities( 'cert_certificate', 'cert_certificates' );
	}

	/**
	 * Returns all primitive capabilities used by this plugin.
	 *
	 * @return array
	 */
	public static function get_all_capabilities() {
		$capabilities = array_merge( self::get_course_capabilities(), self::get_certificate_capabilities() );

		return array_values( array_unique( $capabilities ) );
	}

	/**
	 * Builds a WordPress post type capabilities map.
	 *
	 * @param string $singular Singular capability suffix.
	 * @param string $plural Plural capability suffix.
	 * @return array
	 */
	private static function build_capabilities( $singular, $plural ) {
		return array(
			'edit_post'              => 'edit_' . $singular,
			'read_post'              => 'read_' . $singular,
			'delete_post'            => 'delete_' . $singular,
			'edit_posts'             => 'edit_' . $plural,
			'edit_others_posts'      => 'edit_others_' . $plural,
			'publish_posts'          => 'publish_' . $plural,
			'read_private_posts'     => 'read_private_' . $plural,
			'delete_posts'           => 'delete_' . $plural,
			'delete_private_posts'   => 'delete_private_' . $plural,
			'delete_published_posts' => 'delete_published_' . $plural,
			'delete_others_posts'    => 'delete_others_' . $plural,
			'edit_private_posts'     => 'edit_private_' . $plural,
			'edit_published_posts'   => 'edit_published_' . $plural,
		);
	}
}
