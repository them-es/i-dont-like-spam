<?php
/**
 * @wordpress-plugin
 * Plugin Name: I don't like Spam!
 * Plugin URI: https://them.es/plugins/i-dont-like-spam
 * Description: Block contact form submissions containing bad words from the WordPress Comment Blocklist. Compatible with Ninja Forms and WPForms.
 * Version: 1.1.0
 * Author: them.es
 * Author URI: https://them.es
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: i-dont-like-spam
 * Domain Path: /languages
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;


class I_Dont_Like_Spam {

	/**
	 * Enter your blocklist here: Settings > Discussion > Comment Blocklist
	 *
	 * https://developer.wordpress.org/reference/functions/wp_blacklist_check
	 * https://raw.githubusercontent.com/splorp/wordpress-comment-blacklist/master/blacklist.txt
	 *
	 * @access public
	 * @var string
	 */
	public static $bad_words;


	/**
	 * Error message.
	 *
	 * @access public
	 * @var string
	 */
	public static $error_message;


	/**
	 * On load.
	 */
	public function __construct() {
		$this->init();
	}


	/**
	 * Plugin initiation.
	 *
	 * A helper function to initiate actions, hooks and other features needed.
	 */
	public function init() {
		self::$bad_words     = explode( "\n", strtolower( trim( get_option( 'blacklist_keys' ) ) ) );
		self::$error_message = get_theme_mod( 'i_dont_like_spam_errormessage' );

		add_action( 'plugins_loaded', array( $this, 'on_load' ) );

		add_action( 'customize_register', array( $this, 'customizer_settings' ) );

		// Ninja Forms.
		add_filter( 'ninja_forms_submit_data', array( $this, 'nf_submit_data' ) );

		// WPForms.
		add_filter( 'wpforms_process_honeypot', array( $this, 'wpf_process_honeypot' ), 10, 4 );
	}

	/**
	 * Test compatibility.
	 */
	public function pluginmissing_admin_notice() {
		printf( '<div class="%1$s"><p>%2$s</p></div>', 'notice notice-error', sprintf( __( '<strong>I don\'t like Spam!</strong> is an Anti-Spam add-on for contact forms. One of the following Plugins needs to be installed and activated: %s.', 'i-dont-like-spam' ), sprintf( '<a href="' . esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=%1$s' ) ) . '">%1$s</a>, <a href="' . esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=%2$s' ) ) . '">%2$s</a>', 'Ninja Forms', 'WPForms Lite' ) ) );

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}

	public function on_load() {
		if ( ! class_exists( 'Ninja_Forms' ) && ! function_exists( 'wpforms' ) ) {
			// Warning: Required plugin is not installed.
			add_action( 'admin_notices', array( $this, 'pluginmissing_admin_notice' ) );

			return false;
		}
	}


	/**
	 * Flatten an array (e.g. "Name" field in WPForms: )
	 *
	 * https://stackoverflow.com/questions/8611313/turning-multidimensional-array-into-one-dimensional-array
	 */
	public function flatten_fields_array( array $arr ) {
		$arr_new = array();
		array_walk_recursive(
			$arr,
			function( $a ) use ( &$arr_new ) {
				$arr_new[] = $a;
			}
		);

		return $arr_new;
	}


	/**
	 * Theme Customizer additions and adjustments.
	 */
	public function customizer_settings( $wp_customize ) {
		// Section.
		$wp_customize->add_section(
			'i_dont_like_spam_section',
			array(
				'title'       => __( 'I don\'t like Spam!', 'i-dont-like-spam' ),
				'description' => '',
				'priority'    => 99,
			)
		);

		// Custom error message.
		$wp_customize->add_setting(
			'i_dont_like_spam_errormessage',
			array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		$wp_customize->add_control(
			'i_dont_like_spam_errormessage',
			array(
				'type'        => 'text',
				'label'       => __( 'Error message', 'i-dont-like-spam' ),
				'description' => __( 'Forms that contain a word from the Comments Blocklist will show this error message.', 'i-dont-like-spam' ),
				'section'     => 'i_dont_like_spam_section',
				'settings'    => 'i_dont_like_spam_errormessage',
				'priority'    => 0,
			)
		);
	}


	/**
	 * Ninja Forms: Server side spam protection using WordPress comment blocklist
	 *
	 * https://developer.ninjaforms.com/codex/custom-server-side-validation
	 *
	 * Enter your blocklist here: Settings > Discussion > Comment Blocklist
	 * https://developer.wordpress.org/reference/functions/wp_blacklist_check
	 * https://raw.githubusercontent.com/splorp/wordpress-comment-blacklist/master/blacklist.txt
	 */
	public function nf_submit_data( $form_data ) {
		foreach ( $form_data['fields'] as $field ) {
			// Field settings, including the field key and value.
			$field_value = wp_strip_all_tags( strtolower( $field['value'] ) );
			$field_id    = esc_attr( $field['id'] );

			foreach ( self::$bad_words as $bad_word ) {
				$bad_word = trim( $bad_word );

				// Skip empty lines.
				if ( empty( $bad_word ) ) {
					continue;
				}

				if ( false !== strpos( $field_value, $bad_word ) ) {
					$form_data['errors']['fields'][ $field_id ] = ( empty( self::$error_message ) ? sprintf( __( 'This %s contains a word that has been blocked.', 'i-dont-like-spam' ), __( 'field', 'i-dont-like-spam' ) ) : esc_attr( self::$error_message ) );
				}
			}
		}

		return $form_data;
	}


	/**
	 * WPForms: Server side spam protection using WordPress comment blocklist keys
	 *
	 * https://wpforms.com/developers/how-to-block-email-addresses-from-your-forms
	 */
	public function wpf_process_honeypot( $honeypot, $fields, $entry, $form_data ) {
		// Flatten fields array (e.g. "Name" field).
		$entry_fields = $this->flatten_fields_array( $entry['fields'] );

		foreach ( $entry_fields as $field ) {
			// Field value.
			$field_value = wp_strip_all_tags( strtolower( $field ) );

			foreach ( self::$bad_words as $bad_word ) {
				$bad_word = trim( $bad_word );

				// Skip empty lines.
				if ( empty( $bad_word ) ) {
					continue;
				}

				if ( false !== strpos( $field_value, $bad_word ) ) {
					wpforms()->process->errors[ $form_data['id'] ]['header'] = ( empty( self::$error_message ) ? sprintf( __( 'This %s contains a word that has been blocked.', 'i-dont-like-spam' ), __( 'form', 'i-dont-like-spam' ) ) : esc_attr( self::$error_message ) );

					return '[Blocklist] ' . $bad_word;
				}
			}
		}

		return $honeypot;
	}

}

$i_dont_like_spam = new I_Dont_Like_Spam();
