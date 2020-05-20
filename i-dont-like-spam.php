<?php
/**
 * @wordpress-plugin
 * Plugin Name: I don't like Spam!
 * Plugin URI: https://them.es/plugins/i-dont-like-spam
 * Description: Block contact form submissions containing bad words from the WordPress Comment Blocklist. Compatible with Ninja Forms.
 * Version: 1.0.0
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
		add_action( 'plugins_loaded', array( $this, 'on_load' ) );

		add_action( 'customize_register', array( $this, 'customizer_settings' ) );

		add_filter( 'ninja_forms_submit_data', array( $this, 'nf_submit_data' ) );
	}

	/**
	 * Test compatibility.
	 */
	public function pluginmissing_admin_notice() {
		printf( '<div class="%1$s"><p>%2$s</p></div>', 'notice notice-error notice-billy', sprintf( __( '<strong>I don\'t like Spam!</strong> is an Anti-Spam add-on for contact forms. Please install and activate the following %s.', 'i-dont-like-spam' ), sprintf( '<a href="' . esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=%1$s' ) ) . '">%2$s</a>', 'Ninja Forms', __( 'Plugin', 'i-dont-like-spam' ) ) ) );

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}

	public function on_load() {
		if ( ! class_exists( 'Ninja_Forms' ) ) {
			// Warning: Required plugin is not installed.
			add_action( 'admin_notices', array( $this, 'pluginmissing_admin_notice' ) );

			return false;
		}
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
				'description' => __( 'Form fields that contain a word from the Comments Blocklist will show this error message.', 'i-dont-like-spam' ),
				'section'     => 'i_dont_like_spam_section',
				'settings'    => 'i_dont_like_spam_errormessage',
				'priority'    => 0,
			)
		);
	}


	/**
	 * Ninja Forms: Server side spam protection using WordPress comment blacklist keys
	 *
	 * https://developer.ninjaforms.com/codex/custom-server-side-validation
	 *
	 * Enter your blacklisted words here: Settings > Discussion > Comment Blocklist
	 * https://developer.wordpress.org/reference/functions/wp_blacklist_check
	 * https://raw.githubusercontent.com/splorp/wordpress-comment-blacklist/master/blacklist.txt
	 */
	public function nf_submit_data( $form_data ) {
		$bad_words     = explode( "\n", strtolower( trim( get_option( 'blacklist_keys' ) ) ) );
		$error_message = get_theme_mod( 'i_dont_like_spam_errormessage' );

		foreach ( $form_data['fields'] as $field ) {
			// Field settings, including the field key and value.
			$field_value = wp_strip_all_tags( strtolower( $field['value'] ) );
			$field_id    = esc_attr( $field['id'] );

			foreach ( $bad_words as $bad_word ) {
				$bad_word = trim( $bad_word );

				// Skip empty lines.
				if ( empty( $bad_word ) ) {
					continue;
				}

				if ( false !== strpos( $field_value, $bad_word ) ) {
					$form_data['errors']['fields'][ $field_id ] = ( empty( $error_message ) ? __( 'This field contains a word that has been blocked.', 'i-dont-like-spam' ) : esc_attr( $error_message ) );
				}
			}
		}

		return $form_data;
	}

}

$i_dont_like_spam = new I_Dont_Like_Spam();
