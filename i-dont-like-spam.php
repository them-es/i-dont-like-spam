<?php
/**
 * Plugin Name: I don't like Spam!
 * Plugin URI: https://them.es/plugins/i-dont-like-spam
 * Description: Block contact form submissions containing bad words from the WordPress Comment Blocklist. Compatible with the Gutenberg form block (experimental), Ninja Forms, WPForms and Meow Contact Form Block.
 * Version: 1.3.0
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
			'Gutenberg',
			'Ninja Forms',
			'WPForms Lite',
			'Contact Form Block',
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
		// [Experimental] Gutenberg Form Block.
		if ( function_exists( 'gutenberg_is_experiment_enabled' ) && gutenberg_is_experiment_enabled( 'gutenberg-form-blocks' ) ) {
			$required_plugin_installed = true;

			add_filter( 'render_block_core_form_email_content', array( $this, 'core_form_data' ), 10, 2 );
		}

		// Ninja Forms.
		if ( class_exists( 'Ninja_Forms' ) ) {
			$required_plugin_installed = true;

			add_filter( 'ninja_forms_submit_data', array( $this, 'nf_submit_data' ) );
		}

		// WPForms.
		if ( function_exists( 'wpforms' ) ) {
			$required_plugin_installed = true;

			add_filter( 'wpforms_process_honeypot', array( $this, 'wpf_process_honeypot' ), 10, 4 );
		}

		// Meow Contact Form Block.
		if ( class_exists( 'Meow_Contact_Form_Core' ) ) {
			$required_plugin_installed = true;

			add_filter( 'mcfb_validate', array( $this, 'mcfb_validate' ), 10, 2 );
		}

		// Warning: Required plugin is not installed.
		if ( ! isset( $required_plugin_installed ) ) {
			add_action( 'admin_notices', array( $this, 'pluginmissing_admin_notice' ) );
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
			function ( $a ) use ( &$arr_new ) {
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
	 * Gutenberg Forms (experimental!)
	 * https://github.com/WordPress/gutenberg/blob/trunk/packages/block-library/src/form/index.php
	 * https://github.com/WordPress/gutenberg/pull/44214
	 * https://gist.github.com/aristath/7f5ed7185a35e58c8ea65d1154b3d86d
	 *
	 * @param array $content Content.
	 * @param array $params  Form data array.
	 *
	 * @return string
	 */
	public function core_form_data( $content, $params ) {
		foreach ( $params as $row => $field ) {
			// Skip referer row.
			if ( '_wp_http_referer' === $row ) {
				continue;
			}

			foreach ( self::$bad_words as $bad_word ) {
				$bad_word = trim( $bad_word );

				// Skip empty lines.
				if ( empty( $bad_word ) ) {
					continue;
				}

				if ( str_contains( $field, $bad_word ) ) {
					error_log( ( empty( self::$error_message ) ? sprintf( __( 'This %s contains a word that has been blocked.', 'i-dont-like-spam' ), __( 'form', 'i-dont-like-spam' ) ) : esc_attr( self::$error_message ) ) );

					wp_safe_redirect( get_site_url( null, $params['_wp_http_referer'] ) );
					exit();
				}
			}
		}

		return $content;
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

				if ( str_contains( $field_value, $bad_word ) ) {
					$form_data['errors']['fields'][ $field_id ] = ( empty( self::$error_message ) ? sprintf( __( 'This %s contains a word that has been blocked.', 'i-dont-like-spam' ), __( 'field', 'i-dont-like-spam' ) ) : esc_attr( self::$error_message ) );
				}
			}
		}

		return $form_data;
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

				if ( str_contains( $field_value, $bad_word ) ) {
					wpforms()->process->errors[ $form_data['id'] ]['header'] = ( empty( self::$error_message ) ? sprintf( __( 'This %s contains a word that has been blocked.', 'i-dont-like-spam' ), __( 'form', 'i-dont-like-spam' ) ) : esc_attr( self::$error_message ) );

					$honeypot = '[Blocklist] ' . $bad_word;
				}
			}
		}

		return $honeypot;
	}

	/**
	 * Contact Form Block
	 * https://wordpress.org/plugins/contact-form-block
	 *
	 * @param string $error Error message.
	 * @param array  $form  Form fields.
	 *
	 * @return string|null
	 */
	public function mcfb_validate( $error, $form ) {
		foreach ( $form as $field ) {
			foreach ( self::$bad_words as $bad_word ) {
				$bad_word = trim( $bad_word );

				// Skip empty lines.
				if ( empty( $bad_word ) ) {
					continue;
				}

				if ( str_contains( $field, $bad_word ) ) {
					return ( empty( self::$error_message ) ? sprintf( __( 'This %s contains a word that has been blocked.', 'i-dont-like-spam' ), __( 'form', 'i-dont-like-spam' ) ) : esc_attr( self::$error_message ) );
				}
			}
		}

		return null;
	}
}

$i_dont_like_spam = new I_Dont_Like_Spam();
