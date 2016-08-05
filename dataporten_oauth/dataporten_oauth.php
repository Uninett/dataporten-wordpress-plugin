<?php

/*
Plugin Name: Dataporten-oAuth
Plugin URI: http://github.com/uninett/dataporten-wordpress-plugin
Description: A WordPress plugin that allows users to login or register by authenticating with an existing Dataporten accunt via OAuth 2.0.
Version: 2.1
Author: UNINETT
Author URI: https://uninett.no
License: GPL2
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Dataporten_oAuth {

	//
	//
	//	Class initialization. Defines plugin version, and a singleton class pattern,
	//  ensuring there is only one instance of the class globally.
	//
	//

	const PLUGIN_VERSION = "2.1";

	private static $instance;
	private $oauth_identity;

	public static function get_instance() {
		if(null === self::$instance)
			self::$instance = new self;
		return self::$instance;
	}

	//
	//
	//	Default settings to be added to the database.
	//
	//

	private $settings = array(
		'dataporten_http_util' 		  		=> 'curl',
		'dataporten_login_redirect' 		=> 'home_page',
		'dataporten_hide_native_wp'   		=> 0,
		'dataporten_oauth_enabled' 			=> 1,
		'dataporten_default_role_enabled' 	=> 1,
		'dataporten_oauth_clientid' 		=> '',
		'dataporten_oauth_clientsecret' 	=> '',
		'dataporten_oauth_redirect_uri' 	=> '',
		'dataporten_oauth_clientscopes' 	=> '',
		'dataporten_rolesets' 				=> array(),
		'dataporten_http_util_verify_ssl'	=> 0,
		'dataporten_only'					=> 0,
	);

	//
	//
	//	Constructor. Adding hook for init, and updating of plugin.
	//
	//

	function __construct() {
		register_activation_hook(__FILE__, array($this, 'dataporten_activate'));
		register_deactivation_hook(__FILE__, array($this, 'dataporten_deactivate'));

		add_action('plugins_loaded', array($this, 'dataporten_update'));
		add_action('init', array($this, 'dataporten_init'));
	}

	//
	//
	//	Hooks for the different menus, filters for views and script injection.
	//
	//

	function dataporten_init() {
		$this->define_environment();
		$plugin = plugin_basename(__FILE__);

		add_filter('query_vars', array($this, 'dataporten_qvar_triggers'));
		add_action('template_redirect', array($this, 'dataporten_qvar_handlers'));
		add_action('admin_menu', array($this, 'dataporten_settings_page'));
		add_action('wp_enqueue_scripts', array($this, 'dataporten_style_script'));
		add_action('login_enqueue_scripts', array($this, 'dataporten_style_script'));
		add_action('admin_enqueue_scripts', array($this, 'dataporten_style_script'));
		add_action('admin_init', array($this, 'dataporten_activate_admin'));
		add_filter('plugin_action_links_$plugin', array($this, 'dataporten_settings_link'));
		add_action('show_user_profile', array($this, 'dataporten_linked_account'));
		add_action('wp_logout', array($this, 'dataporten_end_logout'));
		add_action('login_message', array($this, 'dataporten_login_screen'));
		//add_action('wp_footer', array($this, 'dataporten_push_login_messages'));
		//add_filter('admin_footer', array($this, 'dataporten_push_login_messages'));
		//add_filter('login_footer', array($this, 'dataporten_push_login_messages'));
		add_filter('comment_form_defaults', array($this, 'dataporten_custom_comments'));
		add_action('dataporten_clear_states', array($this, 'dataporten_clear_states_cron'));
	}

	//
	//
	//	Function for populating the profile settings view with whether the account has
	//  been linked with dataporten or not. Spouts a notice if the account is updated.
	//
	//

	function dataporten_linked_account() {
		global $current_user;
		global $wpdb;

		wp_get_current_user();

		$user_id 		= $current_user->ID;
		$usermeta_table = $wpdb->usermeta;
		$query_string   = "SELECT * FROM $usermeta_table WHERE $user_id = $usermeta_table.user_id AND $usermeta_table.meta_key = 'dataporten_identity'";
		$query_result   = $wpdb->get_results($query_string, ARRAY_A);
		$profile 		= true;

		if(isset($_GET['unlinked'])) {
			if($_GET['unlinked'] == 1) {
				$this->dataporten_unlinked_notice("Success! Your account is now unlinked with dataporten.", "updated");
			} else if($_GET['unlinked'] == 0) {
				$this->dataporten_unlinked_notice("Oops.. Something went wrong when trying to unlink your account. Are you sure you're you?", "error");
			}
		} else if(isset($_GET['linked'])) {
			if($_GET['linked'] == 1) {
				$this->dataporten_unlinked_notice("Success! Your account is now linked with dataporten.", "updated");
			} else if($_GET['linked'] == 0) {
				$this->dataporten_unlinked_notice("Oops.. Something went wrong when trying to link your account. Are you sure you're you?", "error");
			} else if($_GET['linked'] == 2) {
				$this->dataporten_unlinked_notice("That account is already linked on this site.", "error");
			}
		}

		//
		//	Checks whether the account has been linked or not. If not, it adds a
		//  button for linking the account. If it has, it adds a button for unlinking the account.
		//

		if (count($query_result) == 0){
			$site_url      = get_bloginfo('url');
			$button_params = array(
				'text'  => 'Link Dataporten',
				'class' => 'dataporten-profile-page',
				'href'  => wp_nonce_url($site_url . '?connect=dataporten', "link_account", "link_nonce"),
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

			//$local_time = strtotime("-" . $_COOKIE['gmtoffset'] . ' hours', $time_linked);

			$button_params = array(
				'text' => 'Unlink Dataporten',
				'href' => wp_nonce_url(site_url() . '?disconnect=' . $query_results["umeta_id"], "link_account", "link_nonce"),
			);
			include 'login-view.php';
		}
	}

	//
	//
	//	Logs the user out of wordpress, and redirects the
	//  user to another page.
	//
	//

	function dataporten_end_logout() {
		if (is_user_logged_in()) {
			$last_url = $_SERVER['HTTP_REFERER'];
		} else {
			$last_url = strtok($_SERVER['HTTP_REFERER'], '?');
		}

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

	//
	//
	//	Injects the stylesheet and JavaScript to the page. Makes hide_login_form available
	//  for read for the JavaScript, and enables the native loginscreen to be hidden.
	//
	//

	function dataporten_style_script() {
		$dataporten_vars = array(
			'hide_login_form' => get_option('dataporten_hide_native_wp'),
		);
		wp_enqueue_script('dataporten_vars', plugins_url('/js/dataporten_vars.js', __FILE__));
		wp_localize_script('dataporten_vars', 'dataporten_variables', $dataporten_vars);
		wp_enqueue_style('dataporten-stylesheet', plugin_dir_url( __FILE__ ) . '/css/dataporten-oauth.css', array());
	}

	//
	//
	//	Unlinks an user-account from Dataporten where the users dataporten id is found, along
	//  with the row of the data for extra security. Redirects the user to another page
	//  depending on it completing or not.
	//
	//

	function dataporten_unlink_account() {
		if(isset($_GET['link_nonce']) && wp_verify_nonce($_GET['link_nonce'], 'link_account')) {
			global $current_user;
			global $wpdb;

			wp_get_current_user();
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
		} else {
			header("Location: " . site_url() . "/wp-admin/profile.php?unlinked=0");
		}
	}

	//
	//
	//	Manipulates the login screen of Wordpress, adding a button if dataporten has been enabled. Includes
	//  login-view.php as the button view. Text of button changes depending on whether the user is logged in or not.
	//
	//

	function dataporten_login_screen() {
		if (get_option("dataporten_oauth_enabled")) {
			$site_url = get_bloginfo('url');
			if(is_user_logged_in()) {
				$text = "Logout";
				$link = wp_logout_url();
			} else {
				$redirect_to = isset($_GET['redirect_to']) ? "&redirect_to=" . $_GET['redirect_to'] : "";
				$text = "Login with Dataporten";
				$link = wp_nonce_url($site_url . "?connect=dataporten" . $redirect_to, "link_account", "link_nonce");
			}
			$button_params = array(
				'text'  => $text,
				'class' => 'login-page-button',
				'href'  => $link,
				);
			$profile = false;
			if(isset($_GET['errors'])) {
				if($_GET['errors'] == 5) $this->dataporten_push_error_message("Could not complete the login. Please contact an admin or try again later.");
				else if($_GET['errors'] == 6) $this->dataporten_push_error_message("Sorry, user registration is disabled at this time. Your account could not be registered");
			}
			include 'login-view.php';
		}
	}

	//
	//
	//	Enables native wordpress feedback to the user in the admin-panel.
	//
	//

	function dataporten_unlinked_notice($msg, $type) {
		echo '<div class="' . $type . ' notice is-dismissible">';
    	echo '<p>' . $msg . '</p>';
		echo '</div>';
	}

	//
	//
	//	Function being run when the plugin is activated for the first time. Populates the database with default
	//  options.
	//
	//

	function dataporten_activate() {
		$this->define_environment();
		
		foreach ($this->settings as $setting_name => $default_value) {
			register_setting('dataporten_settings', $setting_name);
		}

		$this->dataporten_new_table();
		wp_schedule_event(time(), 'daily', 'dataporten_clear_states');
	}

	//
	//
	//	Deletes all previously unused states. Does this once every day.
	//
	//

	private function dataporten_clear_states_cron() {
		global $wpdb;

		$remove_before 	= date("Y-m-d H:i:s", strtotime("-10 minutes"));
		$table_name 	= $wpdb->prefix . 'dataporten_oauth';

		$query_fetch  = $wpdb->get_results("SELECT * FROM $table_name WHERE $table_name.added < '$remove_before'", ARRAY_A);
		$ids = "";
		for($i = 0; $i < count($query_fetch); $i++) {
			$ids = $ids . "id = " . $query_fetch["$i"]["id"];
			if($i < count($query_fetch) - 1) {
				$ids = $ids . " OR ";
			}
		}
		$query_string = $wpdb->prepare("DELETE FROM $table_name WHERE $table_name.added < %s AND ($ids)", $remove_before);
		$query_result = $wpdb->query($query_string);
	}

	//
	//
	//	Creates a new table for the database that's to contain the states and redirect url of that state
	//
	//

	private function dataporten_new_table() {
		global $wpdb;

   		$table_name = $wpdb->prefix.'dataporten_oauth';
   		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id int(11) NULL AUTO_INCREMENT,
			state varchar(190) NOT NULL UNIQUE,
			url varchar(255) DEFAULT '' NOT NULL,
			added TIMESTAMP NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	//
	//
	//	Initiates admin
	//
	//

	function dataporten_activate_admin() {
		$this->define_environment();

		foreach ($this->settings as $setting_name => $default_value) {
			register_setting('dataporten_settings', $setting_name);
		}
	}

	//
	//
	//	Adding a settings button to plugin view for the plugin. (NOT WORKING for some reason).
	//
	//

	function dataporten_settings_link($links) {
		$settings_link = "<a href='options-general.php?page=Dataporten-oAuth'>Settings</a>"; // CASE SeNsItIvE filename!
		array_unshift($links, $settings_link);
		return $links;
	}

	//
	//
	//	Defines what will happen when the plugin is updated. Runs function for adding the data to the database.
	//
	//

	function dataporten_update() {
		$plugin_version    = Dataporten_oAuth::PLUGIN_VERSION;
		$installed_version = get_option("dataporten_plugin_version");

		if (!$installed_version || $installed_version <= 0 || $installed_version != $plugin_version) {

			$this->dataporten_update_missing_db();
			update_option("dataporten_plugin_version", $plugin_version);
			add_action('admin_notices', array($this, 'dataporten_update_notice'));

		}
	}

	//
	//
	//	Adds missing data to database. Currently it removes all settings.
	//
	//

	function dataporten_update_missing_db() {
		foreach($this->settings as $setting_name => $default_value) {
			update_option($setting_name, $default_value);
		}
	}

	//
	//
	//	Notice informing the user that the plugin has been updated.
	//
	//

	function dataporten_update_notice() {
		$settings_link = "<a href='options-general.php?page=Dataporten-oAuth'>Settings Page</a>";
		echo "<div class='updated'>";
		echo "<p>Dataporten-oAuth has been updated! Please review the " . $settings_link .".</p>";
		echo "</div>";
	}

	//
	//
	//	Function not in use. Maybe this should remove the data?
	//
	//

	function dataporten_deactivate() {
		wp_clear_scheduled_hook('dataporten_clear_states');
	}

	//
	//
	//	Adds link to the plugin when hovering over settings in admin menu.
	//
	//

	function dataporten_settings_page() {
		add_options_page( 'Dataporten-oAuth Options', 'Dataporten-oAuth', 'manage_options', 'Dataporten-oAuth', array($this, 'dataporten_settings_page_content') );
	}

	//
	//
	//	Prints settings page.
	//
	//

	function dataporten_settings_page_content() {
		if (!current_user_can( 'manage_options' )) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ));
		}
		include 'dataporten_oauth_settings.php';

		$settings_page = new Dataporten_oAuth_settings($this);

		$settings_page->print_settings();
	}

	//
	//
	//	Defines the qvars to be used.
	//
	//

	function dataporten_qvar_triggers($vars) {
		$vars[] = 'connect';
		$vars[] = 'code';
		$vars[] = 'disconnect';
		$vars[] = 'errors';
		return $vars;
	}

	//
	//
	//	Handlers for the above defined qvars.
	//
	//

	function dataporten_qvar_handlers() {
		if (get_query_var('connect')) {
			if(!isset($_GET['link_nonce']) || !wp_verify_nonce($_GET['link_nonce'], 'link_account')) {
				$this->dataporten_end_login("?errors=1", -1, "foo");
			} else {
				include 'dataporten_oauth_login.php';
				$login_obj = new Dataporten_oAuth_login($this);
				$login_obj->pre_auth();
			}

		} elseif (get_query_var('code')) {
			include 'dataporten_oauth_login.php';
			$login_obj = new Dataporten_oAuth_login($this);
			$login_obj->post_auth();

		} else if(get_query_var('disconnect')) {
			$this->dataporten_unlink_account();
		} else if(get_query_var('errors')) {
			$this->dataporten_push_error($_GET['errors']);
		}
	}

	private function dataporten_push_error($error_number) {
		switch($error_number) {
			case 4: 					//Error with flow
				$this->dataporten_push_error_message("Something went went wrong with the login-flow. Please try again, or contact an administrator.");
				break;
			case 1:						//Nonce not matching
				$this->dataporten_push_error_message("Nonces' doesn't match. Are you sure you clicked a link from this page?");
				break;
			case 2:						//General error
				$this->dataporten_push_error_message("Something went wrong. Please try again, or contact an administrator");
				break;
		}
	}

	//
	//
	//	Function for loggin in the user. Takes in a variable with the dataporten users id, groups and email.
	//  If the user is matched in the database, but not logged in, we login the user. If the user is logged in
	//  (can be matched here also, so we have to check whether or not there is a match later on. This to prevent
	//  two or more accounts be linked with the same account), he/she is then linked with the desired Dataporten
	//  account. If the user isn't logged in, nor there is a match in the database, we create a new user.
	//	Always checks the users role if the dataporten_default_role_enabled is enabled. This to check whether the
	//	environment variables have been changed, and the user is supposed to have a different role.
	//
	//

	function dataporten_login_user($oauth_identity) {
		$this->oauth_identity = $oauth_identity;

		$matched_user = $this->dataporten_find_match_users($oauth_identity);

		if ($matched_user && !is_user_logged_in()) {
			$user_id = $matched_user->ID;
			$user_login = $matched_user->user_login;
			wp_set_current_user($user_id, $user_login);
			wp_set_auth_cookie($user_id);
			do_action('wp_login', $user_login, $matched_user);

			if(get_option('dataporten_default_role_enabled')) {
				$this->dataporten_check_authority($this->oauth_identity);
			}
			$this->dataporten_end_login("Logged in successfully!", 0, $this->oauth_identity["url"]);
		} else if (is_user_logged_in()) {
			global $current_user;
			wp_get_current_user();

			$user_id = $current_user->ID;

			$this->dataporten_link_account($user_id, $oauth_identity);

			if(get_option('dataporten_default_role_enabled')) {
				$this->dataporten_check_authority($this->oauth_identity);
			}

			$this->dataporten_end_login("?linked=1", 1, $this->oauth_identity["url"]);
		} else if (!is_user_logged_in() && !$matched_user) {
			include 'dataporten_oauth_register.php';

			$register = new Dataporten_oAuth_register($this, $oauth_identity);
			$register->dataporten_create_user();
		} else {
			$this->dataporten_end_login("?errors=4", -1, $this->oauth_identity["url"]);
		}

	}

	//
	//
	//	Updates the current users role depending on the rolesets defined in the database.
	//  The occurence with the highest index, is the one that is kept. This means that
	//  you have to have this in mind when defining the environment variables.
	//
	//

	function dataporten_check_authority($identity) {
		global $current_user, $wpdb;
		$role 		= $wpdb->prefix . 'capabilities';
		$current_user->role = array_keys($current_user->$role);
		$role 		 = $current_user->role[0];

		$tmp_results = json_decode(get_option("dataporten_rolesets"), true);
		$highest 	 = -1;
		$name 		 = $role;
		foreach($identity["groups"] as $tmp) {
			if(!is_array($tmp)) return;
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

	//
	//
	//	Tries to link account with dataporten, but first checks if the account already
	//  are linked. If there isn't an occurence of the account in the database, it will
	//	link with the current account. If the account already exists, the user will be
	//	informed.
	//
	//

	function dataporten_link_account($user_id, $oauth_identity) {
		if ($this->oauth_identity['id'] != '') {

			$matched_user = $this->dataporten_find_match_users($oauth_identity);

			if(!$matched_user){
				add_user_meta($user_id, 'dataporten_identity', 'dataporten|' . $oauth_identity['id'] . '|' . time());
			} else {
				wp_safe_redirect(get_edit_user_link() . "?linked=2");
				die();
			}
		}
	}

	//
	//
	//  Redirects the user to a predetermined location, depending on
	//  the $state parameter.
	//
	//

	function dataporten_end_login($message, $state, $url) {
		$last_url = $url;
		switch($state){
			case -1:
				$redirect_url = wp_login_url() . $message;
				break;
			case 1:
				$redirect_url = get_edit_user_link();
				wp_safe_redirect($redirect_url . $message);
				die();
				break;
			case 0:
				$redirect_url = $last_url == site_url() . "/wp-login.php" ? site_url() : $last_url == "" ? site_url() : $last_url;
				break;
		}
		wp_safe_redirect($redirect_url);
		die();
	}

	//
	//
	//	Finds a match of the user, to see if the user already exists in the database.
	//
	//

	function dataporten_find_match_users($oauth_identity) {
		global $wpdb;

		$usermeta_table = $wpdb->usermeta;
		$query_string   = "SELECT $usermeta_table.user_id FROM $usermeta_table WHERE $usermeta_table.meta_key = 'dataporten_identity' AND $usermeta_table.meta_value LIKE '%" . $oauth_identity['provider'] . "|" . $oauth_identity['id'] . "%'";
		$query_result   = $wpdb->get_var($query_string);

		$user = get_user_by('id', $query_result);
		return $user;
	}

	//
	//
	//	Adds a message to the wordpress page if there is a result.
	//
	//

	private function dataporten_push_error_message($message) {

		echo "<div id='dataporten_outer'>";
		echo "<div id='dataporten_result'>" . $message . "</div>";
		echo "</div>";
	}

	//
	//
	//	Defines the environment variables. If there is anything different from the
	//  environment variables defined in env.list, the already existing ones in the
	//  database is replaced. It also checks whether the environment variables are
	//  defined, making it so the plugin can be used standalone, without docker and
	//  environment variables.
	//
	//

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
		if(getenv('DATAPORTEN_ROLESETS')) {

			$this->settings['dataporten_rolesets'] = json_decode(getenv('DATAPORTEN_ROLESETS'), true);

			if(get_option('dataporten_rolesets') != getenv('DATAPORTEN_ROLESETS')){
				update_option('dataporten_rolesets', json_encode($this->settings['dataporten_rolesets']));
			}
		}
	}

	function dataporten_custom_comments() {
		if (get_option("dataporten_oauth_enabled") && comments_open(get_the_ID())) {
			$site_url = get_bloginfo('url');
			if(!is_user_logged_in()) {
				$text = "Login with Dataporten";
				$redirect_to = "&redirect_to=//" . $_SERVER["HTTP_HOST"] . $_SERVER['REQUEST_URI'];
				$link = wp_nonce_url($site_url . "?connect=dataporten" . $redirect_to, "link_account", "link_nonce");

				$button_params = array(
					'text'  => $text,
					'class' => 'login-page-button',
					'href'  => $link,
					);
				$profile = false;
				include 'login-view.php';
			}
		}
	}
}

Dataporten_oAuth::get_instance();
