<?php
/**
 * Plugin Name: Centrix Integration
 * Plugin URI: https://wordpress.org/plugins/centrix_integration/
 * Description: Integration with centrix service for electronic identification verification. Only for New Zealand and Australia
 * Version: 1.0.0
 * Author: Roundkick.Studio, eurohlam
 * Author URI: https://roundkick.studio
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * License: GPLv2 or later

Centrix Integration is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Centrix Integration is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Centrix Integration. If not, see http://www.gnu.org/licenses/gpl-2.0.txt.
 */

if (!defined('ABSPATH')) exit;

include_once 'class-centrix-integration.php';
include_once 'centrix-shortcodes.php';

define('CENTRIX_INT_VERSION', '1.0.0');

if (!class_exists('WP_Centrix_Int')) {
	class WP_Centrix_Int {
		/**
		* Plugin's options
		*/
	 	private $options_group = 'centrix_int';
	 	private $url_option = 'centrix_url';
		private $httpUser_option = 'centrix_http_user';
		private $httpPassword_option = 'centrix_http_password';
		private $subscriberId_option = 'centrix_subscriber_id';
		private $userId_option = 'centrix_user_id';
		private $userKey_option = 'centrix_user_key';




		private $db_table_name = 'centrix_message_log';
		private $pdf_folder_name = 'centrix_int';

		static function activate() {
		   	global $wpdb;

 			$table_name = $wpdb->prefix . 'centrix_message_log';
			$sql = "CREATE TABLE IF NOT EXISTS ".$table_name." (
			  id int(11) NOT NULL AUTO_INCREMENT,
			  time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			  endpoint varchar(20) NOT NULL,
			  request longtext CHARACTER SET utf8 NOT NULL,
			  response longtext CHARACTER SET utf8 NOT NULL,
			  filepath varchar(50),
			  PRIMARY KEY (id)
			) DEFAULT CHARSET=utf8;";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );

			$dir_path = wp_upload_dir()['basedir'] . '/centrix_int';
			if(!file_exists($dir_path)) wp_mkdir_p($dir_path);
        }

		static function deactivate() {
			//nothing so far
		}

		static function uninstall() {
		   	global $wpdb;
			delete_option( 'centrix_url' );
			delete_option( 'centrix_http_user' );
			delete_option( 'centrix_http_password' );
			delete_option( 'centrix_subscriber_id' );
			delete_option( 'centrix_user_id' );
			delete_option( 'centrix_user_key' );


 			$table_name = $wpdb->prefix . 'centrix_message_log';
			$sql = "DROP TABLE IF EXISTS $table_name";

			$wpdb->query( $sql );
        }

		function __construct() {
			add_action('admin_menu', array( $this, 'centrix_menu'));
			add_action('wp_ajax_centrix_send_request', array( $this,'centrix_send_request'));
			add_action('wp_ajax_centrix_send_email', array( $this,'send_pdf_by_email'));
			add_action('init', 'centrix_shortcodes_init');
		}

		/**
		* Send request to centrix
		*/
		function centrix_send_request() {
		   	global $wpdb;

			$httpUser = get_option($this->httpUser_option);
			$httpPassword = get_option($this->httpPassword_option);
			$url = get_option($this->url_option);
			$subscriberId = get_option($this->subscriberId_option);
			$userId = get_option($this->userId_option);
			$userKey = get_option($this->userKey_option);
			$requestData = stripcslashes($_POST['requestData']);
			$soapAction = $_POST['soapAction'];
			error_log('Centrix URL: '. $url);
			error_log('Centrix SOAPAction: ' . $soapAction);
			error_log('Centrix Request: '. $requestData);
			error_log('User: ' . $httpUser . " Pwd: " . $httpPassword);


			if (!empty($httpUser) && !empty($httpPassword) && !empty($url) && !empty($soapAction) && !empty($subscriberId) && !empty($userId) && !empty($userKey)) {
				$centrixInt = new Centrix_Integration();
				$centrixRequest = $centrixInt->prepare_centrix_parameters($subscriberId, $userId, $userKey, json_decode($requestData, true));
				error_log('Send request to Centrix: ' . $centrixRequest);
				$getCreditReportProductsResponse = $centrixInt->send_request($url, $httpUser, $httpPassword, $soapAction, $centrixRequest);
				error_log('Centrix Response: ' . $getCreditReportProductsResponse);
				/*$wpdb->insert(
		 			$wpdb->prefix . $this->db_table_name,
					array(
					'time' => current_time( 'mysql' ),
					'endpoint' => $soapAction,
					'request' => $centrixRequest,
					'response' => $result,
					)
				);*/
				$enquiryNumber = $centrixInt->get_enquiry_number($getCreditReportProductsResponse);
				error_log('Getting Centrix PDF for enquiryNumer: ' . $enquiryNumber);
				$result = $centrixInt->get_pdf($url, $subscriberId, $userId, $userKey, $enquiryNumber);
				echo $result;
			} else {
				error_log('Centrix Integration plugin error: empty one or several required parameters - accessKey, secret, url or path. Please check settings of centrix Integration plugin');
				echo '{"Centrix Integration plugin error": "empty one or several required parameters - accessKey, secret, url or path"}';
			}
			wp_die();
		}

		function send_pdf_by_email() {
	        $subject = 'Electronic Verification Identification Report';
	        $body = 'Please refer to the attached PDF for more details';
	        $headers = array('Content-Type: text/html; charset=UTF-8');
			$filepath = $_POST['filepath'];
			$emailList = $_POST['emaillist'];

	        wp_mail( $emailList, $subject, $body, $headers, $filepath );

			echo '{ "result" : "success" }';
			wp_die();
	    }

		function centrix_settings() {
			register_setting( $this->options_group, $this->url_option );
			register_setting( $this->options_group, $this->httpUser_option );
			register_setting( $this->options_group, $this->httpPassword_option );
			register_setting( $this->options_group, $this->subscriberId_option );
			register_setting( $this->options_group, $this->userId_option );
			register_setting( $this->options_group, $this->userKey_option );
		}

		function centrix_menu() {
		  	add_action('admin_init', array( $this,'centrix_settings'));
			add_options_page('Centrix Integration', 'Centrix Integration', 'manage_options', 'centrix-int', array( $this,'centrix_options_page'));
		}


		/**
		* Admin options page
		*/
		function centrix_options_page() {
			?>
		    <div class="wrap">
		        <h2>Centrix Integration</h2>
		        <p>Centrix is an electronic identification verification (EV) tool that allows you to verify the identity of your customer using biometric checks, Australian and New Zealand data sources and global watchlists in one easy step. More details about
		            <a href="http://centrix.co.nz" target = "_blank">centrix</a></p>
		        <p>Version: <?php echo CENTRIX_INT_VERSION ?></p>
		        <div>
		            <form method="post" action="options.php">
		            <?php
						settings_fields($this->options_group);
						do_settings_sections($this->options_group);
					?>
						<table class="form-table">
			            	<tr valign="top">
								<th scope="row">Centrix URL</th>
								<td>
									<input type="url" class="regular-text" name="centrix_url" value="<?php echo get_option($this->url_option) ?>" />
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">Centrix HTTP User</th>
								<td>
									<input type="text" class="regular-text" name="centrix_http_user" value="<?php echo get_option($this->httpUser_option) ?>" />
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">Centrix HTTP Password</th>
								<td>
									<input type="text" class="regular-text" name="centrix_http_password" value="<?php echo get_option($this->httpPassword_option) ?>" />
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">Centrix Subscriber ID</th>
								<td>
									<input type="text" class="regular-text" name="centrix_subscriber_id" value="<?php echo get_option($this->subscriberId_option) ?>" />
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">Centrix User ID</th>
								<td>
									<input type="text" class="regular-text" name="centrix_user_id" value="<?php echo get_option($this->userId_option) ?>" />
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">Centrix User Key</th>
								<td>
									<input type="text" class="regular-text" name="centrix_user_key" value="<?php echo get_option($this->userKey_option) ?>" />
								</td>
							</tr>

						</table>
						<input type="hidden" name="page_options" value="centrix_url,centrix_http_user,centrix_http_password,centrix_subscriber_id,centrix_user_id,centrix_user_key" />
						<p class="submit">
							<input class="button-primary" type="submit" value="Save Changes" />
						</p>
					</form>
				</div>
			</div>
			<?php
		}

	} //end class WP_centrix_Int
}


if (class_exists('WP_centrix_Int')) {
	// Installation and uninstallation hooks
	register_activation_hook(__FILE__, array('WP_Centrix_Int', 'activate'));
	register_deactivation_hook(__FILE__, array('WP_Centrix_Int', 'deactivate'));
	register_uninstall_hook(__FILE__, array('WP_Centrix_Int', 'uninstall'));
	// instantiate the plugin class
	$wp_plugin = new WP_Centrix_Int();
}
?>
