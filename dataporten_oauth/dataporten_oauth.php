<?php

/*
Plugin Name: Dataporten-oAuth
Plugin URI: http://github.com/uninett/app-dataporten-wordpress
Description: A WordPress plugin that allows users to login or register by authenticating with an existing Dataporten accunt via OAuth 2.0. 
Version: 0.1
Author: UNINETT
Author URI: https://uninett.no
License: GPL2
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
define('WP_DEBUG', true);
session_start();

class Dataporten_oAuth {

	const PLUGIN_VERSION = "0.1";

	private static $instance;
	private $oauth_identity;

	public static function get_instance() {
		if(null === self::$instance)
			self::$instance = new self;
		return self::$instance;
	}

	private $settings = array(
		'dataporten_http_util' 		  		=> 'curl',
		'dataporten_login_redirect' 		=> 'home_page',
		'dataporten_hide_native_wp'   		=> 1,
		'dataporten_oauth_enabled' 			=> 0,
		'dataporten_default_role_enabled' 	=> 0,
		'dataporten_oauth_clientid' 		=> '',
		'dataporten_oauth_clientsecret' 	=> '',
		'dataporten_oauth_redirect_uri' 	=> '',
		'dataporten_oauth_clientscopes' 	=> '',
		//'dataporten_default_role_realm'		=> '',
		'dataporten_rolesets' 				=> array(),
		'dataporten_http_util_verify_ssl'	=> 0,
		//'dataporten_http_util_verify_ssl' => 1,

	);

	function __construct() {
		register_activation_hook(__FILE__, array($this, 'dataporten_activate'));
		register_deactivation_hook(__FILE__, array($this, 'dataporten_deactivate'));

		add_action('plugins_loaded', array($this, 'dataporten_update'));
		add_action('init', array($this, 'dataporten_init'));
	}

	function dataporten_init() {
		//print_r("initialized");
		$this->define_environment();
		$plugin = plugin_basename(__FILE__);

		add_filter('query_vars', array($this, 'dataporten_qvar_triggers'));
		add_action('template_redirect', array($this, 'dataporten_qvar_handlers'));
		add_action('admin_menu', array($this, 'dataporten_settings_page'));
		//add_action('wp_enqueue_scripts', array($this, 'dataporten_style_script'));
		add_action('login_enqueue_scripts', array($this, 'dataporten_style_script'));
		add_action('admin_enqueue_scripts', array($this, 'dataporten_style_script'));
		add_action('admin_init', array($this, 'dataporten_activate'));
		add_filter('plugin_action_links_$plugin', array($this, 'dataporten_settings_link'));
		add_action('show_user_profile', array($this, 'dataporten_linked_accounts'));
		add_action('wp_logout', array($this, 'dataporten_end_logout'));
		add_action('login_message', array($this, 'dataporten_login_screen'));
		add_action('wp_footer', array($this, 'dataporten_push_login_messages'));
		add_filter('admin_footer', array($this, 'dataporten_push_login_messages'));
		add_filter('login_footer', array($this, 'dataporten_push_login_messages'));
	}

	function dataporten_linked_accounts() {
		global $current_user;
		global $wpdb;

		get_currentuserinfo();

		$user_id 		= $current_user->ID;
		$usermeta_table = $wpdb->usermeta;
		$query_string   = "SELECT * FROM $usermeta_table WHERE $user_id = $usermeta_table.user_id AND $usermeta_table.meta_key = 'dataporten_identity'";
		$query_result   = $wpdb->get_results($query_string, ARRAY_A);
		$profile 		= true;

		if(isset($_GET["unlinked"]) && $_GET['unlinked'] == 1) {
			add_action( 'admin_notices', $this->dataporten_unlinked_notice("Success! Your account is now unlinked with dataporten.", "updated") );
		} else if(isset($_GET["unlinked"]) && $_GET['unlinked'] == 0) {
			add_action( 'admin_notices', $this->dataporten_unlinked_notice("Oops.. Something went wrong when trying to unlink your account. Are you sure you're you?", "error") );
		}
		if (count($query_result) == 0){
			$site_url      = get_bloginfo('url');
			$button_params = array(
				'text'  => 'Link Dataporten',
				'class' => 'dataporten-profile-page',
				'href'  => $site_url . '?connect=dataporten'
			);

			include 'login-view.php';
		} else {
			
			$query_results		 	   = array_shift($query_result);
			$dataporten_identity_parts = explode('|', $query_results["meta_value"]);
			$oauth_provider			   = $dataporten_identity_parts[0];
			$oauth_id 				   = $dataporten_identity_parts[1]; // keep this private, don't send to client
			$time_linked 			   = $dataporten_identity_parts[2];
			
			$query = add_query_arg(array(
				'disconnect' => $query_results["umeta_id"],
			), site_url());
			
			$local_time = strtotime("-" . $_COOKIE['gmtoffset'] . ' hours', $time_linked);

			$button_params = array(
				'text' => 'Unlink Dataporten',
				'href' => $query,
			);
			include 'login-view.php';
		}
	}

	function dataporten_end_logout() {
		$_SESSION['dataporten']['result'] = 'Logged out successfully.';
		if (is_user_logged_in()) {
			$last_url = $_SERVER['HTTP_REFERER'];
		} else {
			$last_url = strtok($_SERVER['HTTP_REFERER'], '?');
		}

		unset($_SESSION['dataporten']['last_url']);
		$this->dataporten_clear_login_state();
		$redirect_method = get_option("dataporten_login_redirect");
		$redirect_url    = "";

		switch($redirect_method) {
			case "home_page":
				$redirect_url = site_url();
				break;
			case "last_page":
				$redirect_url = $last_url;
				break;
		}
		wp_safe_redirect($redirect_url);
		die();
	}

	function dataporten_style_script() {
		$dataporten_vars = array(
			'hide_login_form' => get_option('dataporten_hide_native_wp'),
		);
		wp_enqueue_script('dataporten_vars', plugins_url('/js/dataporten_vars.js', __FILE__));
		wp_localize_script('dataporten_vars', 'dataporten_variables', $dataporten_vars);
		wp_enqueue_style('dataporten-stylesheet', plugin_dir_url( __FILE__ ) . '/css/dataporten-oauth.css', array());
		//wp_enqueue_scripts('dataporten-stylesheet');
	}

	function dataporten_unlink_account() {
		global $current_user;
		global $wpdb;

		get_currentuserinfo();
		$dataporten_identity_row = $_GET['disconnect'];
		$user_id = $current_user->ID;
		$usermeta_table = $wpdb->usermeta;
		$query_string = $wpdb->prepare("DELETE FROM $usermeta_table WHERE $usermeta_table.user_id = $user_id AND $usermeta_table.meta_key = 'dataporten_identity' AND $usermeta_table.umeta_id = %d", $dataporten_identity_row);
		$query_result = $wpdb->query($query_string);
		if($query_result) {
			header("Location: " . site_url() . "/wp-admin/profile.php?unlinked=1");
		} else {
			header("Location: " . site_url() . "/wp-admin/profile.php?unlinked=0");
		}	
	}

	function dataporten_login_screen() {
		if (get_option("dataporten_oauth_enabled")) {
			$site_url = get_bloginfo('url');
			if(is_user_logged_in()) {
				$text = "Logout";
				$link = wp_logout_url();
			} else {
				$text = "Login with Dataporten";
				$link = $site_url . "?connect=dataporten";
			}
			$button_params = array(
				'text'  => $text,
				'class' => 'login-page-button',
				'href'  => $link,
				);
			$profile = false;
			include 'login-view.php';
		}
	}

	function dataporten_unlinked_notice($msg, $type) {
		echo '<div class="' . $type . ' notice">';
    	echo '<p>' . $msg . '</p>';
		echo '</div>';
	}

	function dataporten_activate() {
		//print_r("activated");
		$this->define_environment();

		foreach ($this->settings as $setting_name => $default_value) {
			register_setting('dataporten_settings', $setting_name);
		}
	}

	function dataporten_settings_link($links) {
		$settings_link = "<a href='options-general.php?page=Dataporten-oAuth'>Settings</a>"; // CASE SeNsItIvE filename!
		array_unshift($links, $settings_link); 
		return $links; 
	}

	function dataporten_update() {
		$plugin_version    = Dataporten_oAuth::PLUGIN_VERSION;
		$installed_version = get_option("dataporten_plugin_version");
		
		if (!$installed_version || $installed_version <= 0 || $installed_version != $plugin_version) {
			
			$this->dataporten_update_missing_db();
			update_option("dataporten_plugin_version", $plugin_version);
			add_action('admin_notices', array($this, 'dataporten_update_notice'));
		
		}
	}

	function dataporten_update_missing_db() {
		foreach($this->settings as $setting_name => $default_value) {
			update_option($setting_name, $default_value);
		}
	}

	function dataporten_update_notice() {
		$settings_link = "<a href='options-general.php?page=Dataporten-oAuth'>Settings Page</a>";
		echo "<div class='updated'>";
		echo "<p>Dataporten-oAuth has been updated! Please review the " . $settings_link .".</p>";
		echo "</div>";
	}

	function dataporten_deactivate() {
		//print_r("deactivated");
		//$this->define_environment;
	}

	function dataporten_settings_page() {
		add_options_page( 'Dataporten-oAuth Options', 'Dataporten-oAuth', 'manage_options', 'Dataporten-oAuth', array($this, 'dataporten_settings_page_content') );
	}

	function dataporten_settings_page_content() {
		if (!current_user_can( 'manage_options' )) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ));
		}
		$settings_page = new Dataporten_oAuth_settings($this);

		$settings_page->print_settings();
	}

	function dataporten_qvar_triggers($vars) {
		$vars[] = 'connect';
		$vars[] = 'code';
		$vars[] = 'error_description';
		$vars[] = 'error_message';
		$vars[] = 'disconnect';
		return $vars;
	}

	function dataporten_qvar_handlers() {
		if (get_query_var('connect')) {

			$this->dataporten_include_connector('connect');

		} elseif (get_query_var('code')) {

			$this->dataporten_include_connector('code');

		} elseif (get_query_var('error_description') || get_query_var('error_message')) {
			$this->dataporten_include_connector();
		} else if(get_query_var('disconnect')) {
			$this->dataporten_include_connector('disconnect');
		}
	}

	function dataporten_include_connector($type) {
		$login_obj = new Dataporten_oAuth_login($this);
		switch($type){
			case 'connect':
				$login_obj->pre_auth();
				break;
			case 'code':
				$login_obj->post_auth();
				break;
			case 'disconnect':
				$this->dataporten_unlink_account();
				break;
			default:
				break;
		}
	}

	function dataporten_login_user($oauth_identity) {
		//$_SESSION['dataporten']['user_id'] = $oauth_identity['id'];
		//$_SESSION['dataporten']['email']   = $oauth_identity['email'];
		$this->oauth_identity = $oauth_identity;

		$matched_user = $this->dataporten_find_match_users($oauth_identity);

		
		if ($matched_user) {
			$user_id = $matched_user->ID;
			$user_login = $matched_user->user_login;
			wp_set_current_user($user_id, $user_login);
			wp_set_auth_cookie($user_id);
			do_action('wp_login', $user_login, $matched_user);

			$this->dataporten_check_authority($oauth_identity);
			$this->dataporten_end_login("Logged in successfully!", 0);
		} else if (is_user_logged_in()) {
			global $current_user;
			get_currentuserinfo();

			$user_id = $current_user->ID;

			$this->dataporten_link_account($oauth_identity);
			$this->dataporten_check_authority($user_id);
			$this->dataporten_end_login("Your account was linked successfully with dataporten.", 1);
		} else if (!is_user_logged_in() && !$matched_user) {

			$register = new Dataporten_oAuth_register($this, $oauth_identity);

			$register->dataporten_create_user();
		} else {
			$this->dataporten_end_login("Sorry, we couldn't log you in. The login flow terminated in an unexpected way. Please notify the admin or try again later.", -1);
		}

	}

	function dataporten_check_authority($identity) {
		global $current_user, $wpdb;
		$role 		= $wpdb->prefix . 'capabilities';
		$current_user->role = array_keys($current_user->$role);
		$role 		 = $current_user->role[0];

		$tmp_results = json_decode(get_option("dataporten_rolesets"), true);
		$highest 	 = -1;
		$name 		 = $role;

		foreach($identity["groups"] as $tmp) {
			$curr_index = array_search($tmp["id"], array_keys($tmp_results));
			if($curr_index > $highest) {
				$highest = $curr_index;
				$name    = $tmp_results[$tmp["id"]];
			}
		}

		if($role != $name) {
			$update_role_result = wp_update_user(array('ID' => get_current_user_id(), 'role' => $name));
		}
	}

	function dataporten_link_account($user_id) {
		if ($this->oauth_identity['id'] != '') {
			add_user_meta($user_id, 'dataporten_identity', 'dataporten|' . $this->oauth_identity['id'] . '|' . time());
		}
	}

	function dataporten_end_login($message, $state) {

		$last_url = $_SESSION['dataporten']['last_url'];
		unset($_SESSION['dataporten']['last_url']);
		$_SESSION['dataporten']['result'] = $message;
		$this->dataporten_clear_login_state();
		switch($state){
			case -1:
				$redirect_url = wp_login_url();
				break;
			case 1:
				$redirect_url = get_edit_user_link();
				wp_safe_redirect($redirect_url);
				$this->dataporten_unlinked_notice($msg, "updated");
				die();
				break;
			case 0:
				$redirect_url = $last_url == site_url() . "/wp-login.php" ? site_url() : $last_url;
				break;
		}
		/*$redirect_method = strpos($last_url, "wp-admin/profile.php") ? "last_page" : get_option("dataporten_login_redirect");
		$redirect_url    = "";

		switch($redirect_method) {
			case "home_page":
				$redirect_url = site_url();
				break;
			case "last_page":
				$redirect_url = $last_url;
				break;
		}
*/
		wp_safe_redirect($redirect_url);
		die();
	}

	function dataporten_find_match_users($oauth_identity) {
		global $wpdb;

		$usermeta_table = $wpdb->usermeta;
		$query_string   = "SELECT $usermeta_table.user_id FROM $usermeta_table WHERE $usermeta_table.meta_key = 'dataporten_identity' AND $usermeta_table.meta_value LIKE '%" . $oauth_identity['provider'] . "|" . $oauth_identity['id'] . "%'";
		$query_result   = $wpdb->get_var($query_string);

		$user = get_user_by('id', $query_result);
		return $user;
	}

	function dataporten_push_login_messages() {
		$result = $_SESSION['dataporten']['result'];
		unset($_SESSION['dataporten']['result']);
		echo "<div id='dataporten_outer'>";
		echo "<div id='dataporten_result'>" . $result . "</div>";
		echo "</div>";
	}

	function dataporten_clear_login_state() {
		unset($_SESSION['dataporten']['user_id']);
		unset($_SESSION['dataporten']['user_email']);
		unset($_SESSION['dataporten']['access_token']);
		unset($_SESSION['dataporten']['expires_in']);
		unset($_SESSION['dataporten']['expires_at']);
		unset($_SESSION['dataporten']['user_name']);

	}

	private function define_environment() {
		if(	getenv('DATAPORTEN_CLIENTID') && getenv('DATAPORTEN_CLIENTSECRET') && getenv('DATAPORTEN_SCOPES') && getenv('HOST')) {
			define("DATAPORTEN_CLIENTID", getenv('DATAPORTEN_CLIENTID'));
			define("DATAPORTEN_CLIENTSECRET", getenv('DATAPORTEN_CLIENTSECRET'));
			define("DATAPORTEN_SCOPES", getenv('DATAPORTEN_SCOPES'));

			$full_uri = getenv('TLS') == "true" ? 'https://' . getenv('HOST') : 'http://' . getenv('HOST');
			define('DATAPORTEN_REDIRECTURI', $full_uri);

			$this->settings["dataporten_oauth_clientid"] 	 = DATAPORTEN_CLIENTID;
			$this->settings["dataporten_oauth_clientsecret"] = DATAPORTEN_CLIENTSECRET;
			$this->settings["dataporten_oauth_redirect_uri"] = DATAPORTEN_REDIRECTURI;
			$this->settings["dataporten_oauth_clientscopes"] = DATAPORTEN_SCOPES;
			$this->settings["dataporten_oauth_enabled"] 	 = 1;

			if(getenv('DATAPORTEN_CLIENTSECRET') && get_option("dataporten_oauth_clientsecret") != getenv('DATAPORTEN_CLIENTSECRET')) {
				update_option("dataporten_oauth_clientsecret", $this->settings["dataporten_oauth_clientsecret"]);
			}
			if(getenv('DATAPORTEN_CLIENTID') && get_option("dataporten_oauth_clientid") != getenv('DATAPORTEN_CLIENTID')) {
				update_option("dataporten_oauth_clientid", $this->settings["dataporten_oauth_clientid"]);
			}
			if(getenv('DATAPORTEN_SCOPES') && get_option("dataporten_oauth_clientscopes") != getenv('DATAPORTEN_SCOPES')) {
				update_option("dataporten_oauth_clientscopes", $this->settings["dataporten_oauth_clientscopes"]);
			}

			if(getenv('HOST')) {
				if($full_uri != get_option("dataporten_oauth_redirect_uri")) {
					update_option("dataporten_oauth_redirect_uri", $this->settings["dataporten_oauth_redirect_uri"]);

				}
			}
		}
		if( getenv('DATAPORTEN_DEFAULT_ROLE_ENABLED')) {
			define('DATAPORTEN_DEFAULT_ROLE_ENABLED', getenv('DATAPORTEN_DEFAULT_ROLE_ENABLED'));

			$this->settings['dataporten_default_role_enabled'] = DATAPORTEN_DEFAULT_ROLE_ENABLED; 

			if(get_option('dataporten_default_role_enabled') != getenv('DATAPORTEN_DEFAULT_ROLE_ENABLED')){
				update_option('dataporten_default_role_enabled', $this->settings['dataporten_default_role_enabled']);
			}
		}
		/*if(getenv('DATAPORTEN_DEFAULT_ROLE_REALM')) {
			define('DATAPORTEN_DEFAULT_ROLE_REALM' , getenv('DATAPORTEN_DEFAULT_ROLE_REALM'));

			$this->settings['dataporten_default_role_realm'] = DATAPORTEN_DEFAULT_ROLE_REALM;

			if(get_option('dataporten_default_role_realm') != getenv('DATAPORTEN_DEFAULT_ROLE_REALM')){
				update_option('dataporten_default_role_realm', $this->settings['dataporten_default_role_realm']);
			}
		}*/
		//print_r(json_decode(getenv('DATAPORTEN_ROLESETS')));
		if(getenv('DATAPORTEN_ROLESETS')) {
			//define('DATAPORTEN_ROLESETS' , );

			$this->settings['dataporten_rolesets'] = json_decode(getenv('DATAPORTEN_ROLESETS'), true);
			//print_r("foo");
			//print_r($this->settings['dataporten-rolesets']);

			if(get_option('dataporten_rolesets') != getenv('DATAPORTEN_ROLESETS')){
				update_option('dataporten_rolesets', json_encode($this->settings['dataporten_rolesets']));
			}
		}
	}
}

spl_autoload_register(function ($class_name) {
	include strtolower($class_name) . '.php';
});

Dataporten_oAuth::get_instance();