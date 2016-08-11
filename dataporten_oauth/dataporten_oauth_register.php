<?php

class Dataporten_oAuth_register {

	private $username;
	private $password;
	private $email;
	private $nickname;
	private $dataporten_main;
	private $oauth_identity;
	private $role;
	private $firstname;
	private $lastname;

	//
	//
	//	Construct function for register class. Tells the user if the registration
	//	is disabled at the current account, and redirecting the user. Sets all
	//	variables, and generates password. Creates nickname from email, since the
	//	username is set to be id of user. This can be changed later.
	//
	//

	public function __construct($dataporten_main, $oauth_identity) {
		if(!get_option("users_can_register") && get_option("dataporten_only") != 1) {

			header("Location: " . wp_login_url() . "?errors=6");
			exit;
		}
		$this->dataporten_main = $dataporten_main;
		$this->oauth_identity  = $oauth_identity;
		if($oauth_identity['id'] != ""){
			$this->username = $oauth_identity['id'];
			$this->password = wp_generate_password();
			$this->email    = $oauth_identity['email'];
			$this->nickname = explode('@', $this->email)[0];
			$this->firstname = $oauth_identity['firstname'];
			$this->lastname = $oauth_identity['lastname'];
			$this->groups 	= $oauth_identity["groups"];
		} else {
			$this->username = $_POST['identity'];
			$this->password = $_POST['password'];
			$this->nickname = $this->username;
		}
	}

	//
	//
	//	Takes in the groups priority from dataporten_rolesets and creates user.
	//	Sets username, display name and all required fields of an account. Sets
	//	the role-name to default_role. If dataporten_default_role_enabled is enabled,
	//	it checks the intended role of the user. Then links the dataporten account
	//	with the wordpress account.
	//
	//

	public function dataporten_create_user() {
		global $wpdb;
		$groups_priority = json_decode(get_option("dataporten_rolesets"), true);

		$user_id = wp_create_user($this->username, $this->password, $this->email);

		if (is_wp_error($user_id)) {

			//if(defined('WP_DEBUG') && WP_DEBUG == true) {
				error_log("Username " . $this->username);
				error_log($user_id->get_error_message());
			//}

			header("Location: " . site_url() . "?errors=2");
			exit;
		}

		$update_username_result = $wpdb->update($wpdb->users, array(
			'user_login' 	=> $this->username,
			'user_nicename' => $this->username,
			'display_name' 	=> $this->nickname,
		), array('ID' => $user_id));

		$update_nickname_result = update_user_meta($user_id, "nickname", $this->nickname);
		$update_firstname_result = update_user_meta($user_id, "first_name", $this->firstname);
		$update_lastname_result = update_user_meta($user_id, "last_name", $this->lastname);

		//$user_email = wp_update_user( array('ID' => $user_id, 'user_email' => $this->email ) );

		$name 		 = get_option('default_role');
		if(get_option('dataporten_default_role_enabled')) {
			$tmp_results = json_decode(get_option("dataporten_rolesets"), true);
			$highest 	 = -1;

			foreach($this->groups as $tmp) {
				if(!is_array($tmp)) break;
				$curr_index = array_search($tmp["id"], array_keys($tmp_results));
				if($curr_index > $highest) {
					$highest = $curr_index;
					$name    = $tmp_results[$tmp["id"]];
				}
			}
			$this->role = $name;
		}
		$update_role_result = wp_update_user(array('ID' => $user_id, 'role' => $this->role));

		if ($update_username_result == false || $update_nickname_result == false || $update_role_result == false || $update_firstname_result == false || $update_lastname_result == false) {
			
			header("Location: " . wp_login_url() . "?errors=5"); exit;
		} else {
			$this->dataporten_main->dataporten_link_account($user_id, $this->oauth_identity);

			$credentials = array(
				'user_login'  	=> $this->username,
				'user_password' => $this->password,
				'remember' 		=> true,
			);

			$user = wp_signon($credentials, false);
			$this->dataporten_main->dataporten_end_login("", 0, site_url());
		}
	}
}

?>
