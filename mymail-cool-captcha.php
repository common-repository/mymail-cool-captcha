<?php
/*
Plugin Name: MyMail Cool Captcha for Forms
Plugin URI: https://evp.to/mymail?utm_campaign=wporg&utm_source=MyMail+Cool+Captcha+for+Forms
Description: Adds a Cool Captcha to your MyMail subscription forms
Version: 0.4.4
Author: EverPress
Author URI: https://everpress.co

License: GPLv2 or later
*/


class MyMailCoolCaptcha {

	private $plugin_path;
	private $plugin_url;

	public function __construct() {

		$this->plugin_path = plugin_dir_path( __FILE__ );
		$this->plugin_url  = plugin_dir_url( __FILE__ );

		register_activation_hook( __FILE__, array( &$this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( &$this, 'deactivate' ) );

		load_plugin_textdomain( 'mymail_coolcaptcha', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );

		add_action( 'init', array( &$this, 'init' ) );
	}

	public function activate( $network_wide ) {

		if ( function_exists( 'mymail' ) ) {

			$defaults = array(
				'coolcaptcha_error_msg' => __( 'Enter the text of the captcha', 'mymail_coolcaptcha' ),
				'coolcaptcha_formlabel' => __( 'Enter the text of the captcha', 'mymail_coolcaptcha' ),
				'coolcaptcha_forms'     => array(),
				'coolcaptcha_format'    => 'jpeg',
				'coolcaptcha_quality'   => 2,
				'coolcaptcha_width'     => 200,
				'coolcaptcha_height'    => 70,
				'coolcaptcha_blur'      => true,
				'coolcaptcha_min'       => 5,
				'coolcaptcha_max'       => 8,
				'coolcaptcha_yp'        => 12,
				'coolcaptcha_ya'        => 14,
				'coolcaptcha_xp'        => 11,
				'coolcaptcha_xa'        => 5,
				'coolcaptcha_rot'       => 8,
				'coolcaptcha_language'  => 'en',
			);

			$mymail_options = mymail_options();

			foreach ( $defaults as $key => $value ) {
				if ( ! isset( $mymail_options[ $key ] ) ) {
					mymail_update_option( $key, $value );
				}
			}
		}

	}

	public function deactivate( $network_wide ) {

	}

	public function init() {

		if ( is_admin() ) {

			add_filter( 'mymail_setting_sections', array( &$this, 'settings_tab' ) );

			add_action( 'mymail_section_tab_coolcaptcha', array( &$this, 'settings' ) );

		}
		add_filter( 'mymail_form_fields', array( &$this, 'form_fields' ), 10, 3 );
		add_filter( 'mymail_profile_fields', array( &$this, 'form_fields' ), 10, 3 );

		add_filter( 'mymail_submit_errors', array( &$this, 'check_captcha_v1' ), 10, 1 );
		add_filter( 'mymail_submit', array( &$this, 'check_captcha' ), 10, 1 );
		add_action( 'wp_ajax_mymail_coolcaptcha_img', array( &$this, 'coolcaptcha_img' ) );
		add_action( 'wp_ajax_nopriv_mymail_coolcaptcha_img', array( &$this, 'coolcaptcha_img' ) );

		if ( function_exists( 'mailster' ) ) {

			add_action(
				'admin_notices',
				function() {

					$name = 'MyMail Cool Captcha for Forms';
					$slug = 'mailster-cool-captcha/mailster-cool-captcha.php';

					$install_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . dirname( $slug ) ), 'install-plugin_' . dirname( $slug ) );

					$search_url = add_query_arg(
						array(
							's'    => $slug,
							'tab'  => 'search',
							'type' => 'term',
						),
						admin_url( 'plugin-install.php' )
					);

					?>
			<div class="error">
				<p>
				<strong><?php echo esc_html( $name ); ?></strong> is deprecated in Mailster and no longer maintained! Please switch to the <a href="<?php echo esc_url( $search_url ); ?>">new version</a> as soon as possible or <a href="<?php echo esc_url( $install_url ); ?>">install it now!</a>
				</p>
			</div>
					<?php

				}
			);
		}

	}

	public function settings_tab( $settings ) {

		$position = 4;
		$settings = array_slice( $settings, 0, $position, true ) +
					array( 'coolcaptcha' => 'Cool Captcha' ) +
					array_slice( $settings, $position, null, true );

		return $settings;
	}

	public function settings() {

		?>
	<table class="form-table">
		<tr valign="top">
			<th scope="row"><?php _e( 'Error Message', 'mymail_coolcaptcha' ); ?></th>
			<td><p><input type="text" name="mymail_options[coolcaptcha_error_msg]" value="<?php echo esc_attr( mymail_option( 'coolcaptcha_error_msg' ) ); ?>" class="large-text"></p></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'Form Label', 'mymail_coolcaptcha' ); ?></th>
			<td><p><input type="text" name="mymail_options[coolcaptcha_formlabel]" value="<?php echo esc_attr( mymail_option( 'coolcaptcha_formlabel' ) ); ?>" class="large-text"></p></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'Disable for logged in users', 'mymail_coolcaptcha' ); ?></th>
			<td><label><input type="hidden" name="mymail_options[coolcaptcha_loggedin]" value=""><input type="checkbox" name="mymail_options[coolcaptcha_loggedin]" value="1" <?php checked( mymail_option( 'coolcaptcha_loggedin' ) ); ?>> <?php _e( 'disable the captcha for logged in users', 'mymail_coolcaptcha' ); ?></label></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'Forms', 'mymail_coolcaptcha' ); ?><p class="description"><?php _e( 'select forms which require a captcha', 'mymail_coolcaptcha' ); ?></p></th>
			<td>
				<ul>
				<?php
				$forms       = mymail( 'form' )->get_all();
					$enabled = mymail_option( 'coolcaptcha_forms', array() );
				foreach ( $forms as $form ) {
					$form = (object) $form;
					$id   = isset( $form->ID ) ? $form->ID : $form->id;
					echo '<li><label><input name="mymail_options[coolcaptcha_forms][]" type="checkbox" value="' . $id . '" ' . ( checked( in_array( $id, $enabled ), true, false ) ) . '>' . $form->name . '</label></li>';
				}

				?>
				</ul>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'Preview', 'mymail_coolcaptcha' ); ?>
			<p class="description"><?php _e( 'you have to save the settings to update the preview!', 'mymail_coolcaptcha' ); ?></p></th>
			<td>
				<?php
				printf(
					'<br><img src="%s" width="' . mymail_option( 'coolcaptcha_width' ) . '" height="' . mymail_option( 'coolcaptcha_height', 70 ) . '" style="border:1px solid #ccc">',
					add_query_arg(
						array(
							'action'  => 'mymail_coolcaptcha_img',
							'nocache' => time(),
						),
						admin_url( 'admin-ajax.php' )
					)
				);
				?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'Image Format', 'mymail_coolcaptcha' ); ?></th>
			<td><select name="mymail_options[coolcaptcha_format]">
				<?php
				$themes      = array(
					'jpeg' => 'JPG',
					'png'  => 'PNG',
				);
					$current = mymail_option( 'coolcaptcha_format' );
				foreach ( $themes as $key => $name ) {
					echo '<option value="' . $key . '" ' . ( selected( $key, $current, false ) ) . '>' . $name . '</option>';
				}

				?>
			</select></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'Quality', 'mymail_coolcaptcha' ); ?></th>
			<td><select name="mymail_options[coolcaptcha_quality]">
				<?php
				$themes      = array(
					1 => 'low',
					2 => 'medium',
					3 => 'high',
				);
					$current = mymail_option( 'coolcaptcha_quality' );
				foreach ( $themes as $key => $name ) {
					echo '<option value="' . $key . '" ' . ( selected( $key, $current, false ) ) . '>' . $name . '</option>';
				}

				?>
			</select></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'Dimensions', 'mymail_coolcaptcha' ); ?></th>
			<td><p><input type="text" name="mymail_options[coolcaptcha_width]" value="<?php echo esc_attr( mymail_option( 'coolcaptcha_width', 200 ) ); ?>" class="small-text"> &times; <input type="text" name="mymail_options[coolcaptcha_height]" value="<?php echo esc_attr( mymail_option( 'coolcaptcha_height' ) ); ?>" class="small-text"> px</p></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'Blur', 'mymail_coolcaptcha' ); ?></th>
			<td><label><input type="hidden" name="mymail_options[coolcaptcha_blur]" value=""><input type="checkbox" name="mymail_options[coolcaptcha_blur]" value="1" <?php checked( mymail_option( 'coolcaptcha_blur' ) ); ?>> <?php _e( 'use blur', 'mymail_coolcaptcha' ); ?></label></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'Line', 'mymail_coolcaptcha' ); ?></th>
			<td><label><input type="hidden" name="mymail_options[coolcaptcha_line]" value=""><input type="checkbox" name="mymail_options[coolcaptcha_line]" value="1" <?php checked( mymail_option( 'coolcaptcha_line' ) ); ?>> <?php _e( 'strike out the text', 'mymail_coolcaptcha' ); ?></label></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'Word length', 'mymail_coolcaptcha' ); ?></th>
			<td><p><?php _e( 'use at least', 'mymail_coolcaptcha' ); ?> <input type="text" name="mymail_options[coolcaptcha_min]" value="<?php echo esc_attr( mymail_option( 'coolcaptcha_min' ) ); ?>" class="small-text"> <?php _e( 'letters per word but max', 'mymail_coolcaptcha' ); ?> <input type="text" name="mymail_options[coolcaptcha_max]" value="<?php echo esc_attr( mymail_option( 'coolcaptcha_max' ) ); ?>" class="small-text"></p></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'Wave configuration', 'mymail_coolcaptcha' ); ?></th>
			<td><p>Y-period: <input type="text" name="mymail_options[coolcaptcha_yp]" value="<?php echo esc_attr( mymail_option( 'coolcaptcha_yp' ) ); ?>" class="small-text">
				Y-amplitude: <input type="text" name="mymail_options[coolcaptcha_ya]" value="<?php echo esc_attr( mymail_option( 'coolcaptcha_ya' ) ); ?>" class="small-text">
				X-period: <input type="text" name="mymail_options[coolcaptcha_xp]" value="<?php echo esc_attr( mymail_option( 'coolcaptcha_xp' ) ); ?>" class="small-text">
				X-amplitude: <input type="text" name="mymail_options[coolcaptcha_xa]" value="<?php echo esc_attr( mymail_option( 'coolcaptcha_xa' ) ); ?>" class="small-text"> </p></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'Max. rotation', 'mymail_coolcaptcha' ); ?></th>
			<td><p><input type="text" name="mymail_options[coolcaptcha_rot]" value="<?php echo esc_attr( mymail_option( 'coolcaptcha_rot' ) ); ?>" class="small-text"></p></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'Language', 'mymail_coolcaptcha' ); ?></th>
			<td><select name="mymail_options[coolcaptcha_language]">
				<?php
				$languages   = array(
					'en' => 'English',
					'es' => 'Spanish',
				);
					$current = mymail_option( 'coolcaptcha_language' );
				foreach ( $languages as $key => $name ) {
					echo '<option value="' . $key . '" ' . ( selected( $key, $current, false ) ) . '>' . $name . '</option>';
				}

				?>
			</select></td>
		</tr>
	</table>

		<?php
	}

	public function form_fields( $fields, $formid, $form ) {

		if ( is_user_logged_in() && mymail_option( 'coolcaptcha_loggedin' ) ) {
			return $fields;
		}

		if ( ! in_array( $formid, mymail_option( 'coolcaptcha_forms', array() ) ) ) {
			return $fields;
		}

		$position = count( $fields ) - 1;
		$fields   = array_slice( $fields, 0, $position, true ) +
					array( '_coolcaptcha' => $this->get_field( $form, $formid, $form ) ) +
					array_slice( $fields, $position, null, true );

		return $fields;

	}

	public function coolcaptcha_img() {

		if ( ! session_id() ) {
			session_start();
		}

		$formid = ( isset( $_GET['formid'] ) ) ? intval( $_GET['formid'] ) : 0;

		require_once $this->plugin_path . 'captcha/captcha.php';
		$captcha = new SimpleCaptcha();

		$captcha->resourcesPath = $this->plugin_path . 'captcha';
		$captcha->wordsFile     = 'words/' . mymail_option( 'coolcaptcha_language' ) . '.php';
		$captcha->session_var   = 'mymail_coolcaptcha_' . $formid;

		$captcha->width  = intval( mymail_option( 'coolcaptcha_width' ) );
		$captcha->height = intval( mymail_option( 'coolcaptcha_height' ) );

		$captcha->imageFormat = mymail_option( 'coolcaptcha_format', 'jpg' );

		$captcha->lineWidth = mymail_option( 'coolcaptcha_line' ) ? 3 : 0;
		$captcha->scale     = intval( mymail_option( 'coolcaptcha_quality' ) );

		$captcha->minWordLength = intval( mymail_option( 'coolcaptcha_min' ) );
		$captcha->maxWordLength = intval( mymail_option( 'coolcaptcha_max' ) );

		$captcha->maxRotation = intval( mymail_option( 'coolcaptcha_rot' ) );

		$captcha->blur       = (bool) mymail_option( 'coolcaptcha_blur' );
		$captcha->Yperiod    = intval( mymail_option( 'coolcaptcha_yp' ) );
		$captcha->Yamplitude = intval( mymail_option( 'coolcaptcha_ya' ) );
		$captcha->Xperiod    = intval( mymail_option( 'coolcaptcha_xp' ) );
		$captcha->Xamplitude = intval( mymail_option( 'coolcaptcha_xa' ) );

		$captcha->CreateImage();
		exit;

	}

	public function get_field( $html, $formid, $form ) {

		$form = (object) $form;

		$width  = intval( mymail_option( 'coolcaptcha_width' ) );
		$height = intval( mymail_option( 'coolcaptcha_height' ) );

		$label = mymail_option( 'coolcaptcha_formlabel' );

		$html = '<div class="mymail-wrapper mymail-_coolcaptcha-wrapper">';
		if ( empty( $form->inline ) ) {
			$html .= '<label for="mymail-_coolcaptcha-' . $formid . '">' . $label . '</label>';
		}
		$img = sprintf(
			'<div><img title="' . __( 'click to reload', 'mymail_coolcaptcha' ) . '" onclick="var s=this.src;this.src=s.replace(/nocache=\d+/, \'nocache=\'+(+new Date()))" src="%s" style="cursor:pointer;width:%dpx;height:%dpx"></div>',
			add_query_arg(
				array(
					'action'  => 'mymail_coolcaptcha_img',
					'nocache' => time(),
					'formid'  => $formid,
				),
				admin_url( 'admin-ajax.php' )
			),
			$width,
			$height
		);

		$input = '<input id="mymail-_coolcaptcha-' . $formid . '" name="mymail__coolcaptcha" type="text" value="" class="input mymail-coolcaptcha" placeholder="' . ( ! empty( $form->inline ) ? $label : '' ) . '">';

		$html = $html . $img . $input;

		$html .= '</div>';

		return $html;

	}

	public function check_captcha( $object ) {

		if ( is_user_logged_in() && mymail_option( 'coolcaptcha_loggedin' ) ) {
			return $object;
		}

		$formid = ( isset( $_POST['formid'] ) ) ? intval( $_POST['formid'] ) : 0;

		if ( ! in_array( $formid, mymail_option( 'coolcaptcha_forms', array() ) ) ) {
			return $object;
		}

		if ( ! session_id() ) {
			session_start();
		}

		$session_var = 'mymail_coolcaptcha_' . $formid;

		if ( empty( $_SESSION[ $session_var ] ) || strtolower( trim( $_REQUEST['mymail__coolcaptcha'] ) ) != $_SESSION[ $session_var ] ) {
			$object['errors']['_coolcaptcha'] = mymail_option( 'coolcaptcha_error_msg' );
		} else {

		}

		return $object;

	}

	public function check_captcha_v1( $errors ) {

		if ( is_user_logged_in() && mymail_option( 'coolcaptcha_loggedin' ) ) {
			return $errors;
		}

		$formid = ( isset( $_POST['formid'] ) ) ? intval( $_POST['formid'] ) : 0;

		if ( ! in_array( $formid, mymail_option( 'coolcaptcha_forms', array() ) ) ) {
			return $errors;
		}

		if ( ! session_id() ) {
			session_start();
		}

		$session_var = 'mymail_coolcaptcha_' . $formid;

		if ( empty( $_SESSION[ $session_var ] ) || strtolower( trim( $_REQUEST['mymail__coolcaptcha'] ) ) != $_SESSION[ $session_var ] ) {
			$errors['_coolcaptcha'] = mymail_option( 'coolcaptcha_error_msg' );
		} else {

		}

		return $errors;

	}


}
new MyMailCoolCaptcha();
