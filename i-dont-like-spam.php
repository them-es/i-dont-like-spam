<?php
/**
 * Plugin Name: I don't like Spam!
 * Plugin URI: https://them.es/plugins/i-dont-like-spam
 * Description: Block contact form submissions containing bad words from the WordPress Comment Blocklist. Compatible with Ninja Forms, Caldera Forms and WPForms.
 * Version: 1.2.6
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
	 * @var array
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
		// [WP 5.5+] https://make.wordpress.org/core/2020/07/23/codebase-language-improvements-in-5-5
		$disallowed_keys     = ( false === get_option( 'disallowed_keys' ) ? get_option( 'blacklist_keys' ) : get_option( 'disallowed_keys' ) );
		self::$bad_words     = explode( "\n", strtolower( esc_textarea( $disallowed_keys ) ) );
		self::$error_message = get_theme_mod( 'i_dont_like_spam_errormessage' );

		add_action( 'plugins_loaded', array( $this, 'on_load' ) );

		add_action( 'customize_register', array( $this, 'customizer_settings' ) );
	}

	/**
	 * Test compatibility.
	 *
	 * @return void
	 */
	public function pluginmissing_admin_notice() {
		$plugins = array(
			'Ninja Forms',
			'Caldera Forms',
			'WPForms Lite',
		);

		$plugins_missing_links = '';
		foreach ( $plugins as $plugin ) {
			$plugins_missing_links .= '<a href="' . esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . esc_attr( $plugin ) ) ) . '">' . esc_attr( $plugin ) . '</a>' . ( next( $plugins ) ? ', ' : '' );
		}

		printf( '<div class="%1$s"><p>%2$s</p></div>', 'notice notice-error', sprintf( __( '<strong>I don\'t like Spam!</strong> is an Anti-Spam add-on for contact forms. One of the following Plugins needs to be installed and activated: %s.', 'i-dont-like-spam' ), $plugins_missing_links ) );

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}

	/**
	 * On load.
	 *
	 * @return false|void
	 */
	public function on_load() {
		if ( ! class_exists( 'Ninja_Forms' ) && ! class_exists( 'Caldera_Forms' ) && ! function_exists( 'wpforms' ) ) {
			// Warning: Required plugin is not installed.
			add_action( 'admin_notices', array( $this, 'pluginmissing_admin_notice' ) );

			return false;
		}

		// Ninja Forms.
		if ( class_exists( 'Ninja_Forms' ) ) {
			add_filter( 'ninja_forms_submit_data', array( $this, 'nf_submit_data' ) );
		}

		// Caldera Forms.
		if ( class_exists( 'Caldera_Forms' ) ) {
			$cf_field_types = array( 'text', 'paragraph', 'email', 'number', 'phone', 'url' ); // Caldera_Forms_Fields::get_all().
			foreach ( $cf_field_types as $cf_field_type ) {
				add_filter( 'caldera_forms_validate_field_' . $cf_field_type, array( $this, 'cf_submit_data' ), 25, 3 );
			}
		}

		// WPForms.
		if ( function_exists( 'wpforms' ) ) {
			add_filter( 'wpforms_process_honeypot', array( $this, 'wpf_process_honeypot' ), 10, 4 );
		}
	}

	/**
	 * Flatten an array (e.g. "Name" field in WPForms).
	 * https://stackoverflow.com/questions/8611313/turning-multidimensional-array-into-one-dimensional-array
	 *
	 * @param array $arr Make a multidimensional array an one-dimensional array.
	 *
	 * @return array New flattened fields array.
	 */
	public function flatten_fields_array( $arr ) {
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
	 *
	 * @param WP_Customize_Manager $wp_customize Theme Customizer object.
	 *
	 * @return void
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
	 * https://developer.ninjaforms.com/codex/custom-server-side-validation
	 *
	 * @param array $form_data Form data array.
	 *
	 * @return array $form_data Spam checked form data array.
	 */
	public function nf_submit_data( $form_data ) {
		foreach ( $form_data['fields'] as $field ) {
			// Skip array values.
			if ( is_array( $field['value'] ) ) {
				continue;
			}

			// Field settings, including the field key and value.
			$field_value = wp_strip_all_tags( strtolower( (string) $field['value'] ) );
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
	 * Caldera Forms: Server side spam protection using WordPress comment blocklist keys
	 * https://calderaforms.com/doc/caldera_forms_validate_field_field_type
	 *
	 * @param string $value Form field value.
	 * @param array  $field Form field array.
	 * @param array  $form  Form array.
	 *
	 * @return string|WP_Error $value Spam checked form field value.
	 */
	public function cf_submit_data( $value, $field, $form ) {
		$field_value = wp_strip_all_tags( strtolower( (string) $value ) );

		foreach ( self::$bad_words as $bad_word ) {
			$bad_word = trim( $bad_word );

			// Skip empty lines.
			if ( empty( $bad_word ) ) {
				continue;
			}

			if ( false !== strpos( $field_value, $bad_word ) ) {
				return new WP_Error( $field['ID'], ( empty( self::$error_message ) ? sprintf( __( 'This %s contains a word that has been blocked.', 'i-dont-like-spam' ), __( 'field', 'i-dont-like-spam' ) ) : esc_attr( self::$error_message ) ) );
			}
		}

		return $value;
	}

	/**
	 * WPForms: Server side spam protection using WordPress comment blocklist keys
	 * https://wpforms.com/developers/how-to-block-email-addresses-from-your-forms
	 *
	 * @param string $honeypot  Honeypot field.
	 * @param array  $fields    Form fields.
	 * @param array  $entry     Entry data.
	 * @param string $form_data Form data.
	 *
	 * @return string $honeypot Spam checked honeypot field.
	 */
	public function wpf_process_honeypot( $honeypot, $fields, $entry, $form_data ) {
		// Flatten fields array (e.g. "Name" field).
		$entry_fields = $this->flatten_fields_array( $entry['fields'] );

		foreach ( $entry_fields as $field ) {
			// Field value.
			$field_value = wp_strip_all_tags( strtolower( (string) $field ) );

			foreach ( self::$bad_words as $bad_word ) {
				$bad_word = trim( $bad_word );

				// Skip empty lines.
				if ( empty( $bad_word ) ) {
					continue;
				}

				if ( false !== strpos( $field_value, $bad_word ) ) {
					wpforms()->process->errors[ $form_data['id'] ]['header'] = ( empty( self::$error_message ) ? sprintf( __( 'This %s contains a word that has been blocked.', 'i-dont-like-spam' ), __( 'form', 'i-dont-like-spam' ) ) : esc_attr( self::$error_message ) );

					$honeypot = '[Blocklist] ' . $bad_word;
				}
			}
		}

		return $honeypot;
	}

}

$i_dont_like_spam = new I_Dont_Like_Spam();
