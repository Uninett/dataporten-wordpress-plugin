<?php

class Dataporten_oAuth_register {
	
	private $username;
	private $password;
	private $email;
	private $nickname;
	private $dataporten_main;
	private $oauth_identity;
	private $role;

	public function __construct($dataporten_main, $oauth_identity) {
		if(!get_option("users_can_register")) {
			$_SESSION['dataporten']['result'] = "Sorry, user registration is disabled at this time. Your account could not be registered";

			header("Location: " . $_SESSION['dataporten']['last_url']);
			exit;
		}
		$this->dataporten_main = $dataporten_main;
		$this->oauth_identity  = $oauth_identity;
		if($oauth_identity['id'] != ""){
			$this->username = $oauth_identity['id'];
			$this->password = wp_generate_password();
			$this->email    = $oauth_identity['email'];
			$this->nickname = explode('@', $this->email)[0];
			$this->groups 	= $oauth_identity["groups"];
		} else {
			$this->username = $_POST['identity'];
			$this->password = $_POST['password'];
			$this->nickname = $this->username;
		}
	}

	public function dataporten_create_user() {
		global $wpdb;
		$groups_priority = json_decode(get_option("dataporten_rolesets"), true);

		$user_id = wp_create_user($this->username, $this->password, $this->username);

		if (is_wp_error($user_id)) {
			$_SESSION['dataporten']['result'] = $user_id->get_error_message();
			header("Location: " . site_url());
			exit;
		}

		$update_username_result = $wpdb->update($wpdb->users, array(
			'user_login' 	=> $this->username, 
			'user_nicename' => $this->username,
			'display_name' 	=> $this->nickname, 
			'user_email'    => $this->email,
		), array('ID' => $user_id));

		$update_nickname_result = update_user_meta($user_id, "nickname", $this->nickname);
		
		$tmp_results = json_decode(get_option("dataporten_rolesets"), true);
		$highest 	 = -1;
		$name 		 = get_option('default_role');

		foreach($this->groups as $tmp) {
			$curr_index = array_search($tmp["id"], array_keys($tmp_results));
			if($curr_index > $highest) {
				$highest = $curr_index;
				$name    = $tmp_results[$tmp["id"]];
			}
		}

		$this->role = $name;

		$update_role_result = wp_update_user(array('ID' => $user_id, 'role' => $this->role));

		if ($update_username_result == false || $update_nickname_result == false) {
			// there was an error during registration, redirect and notify the user:
			$_SESSION["dataporten"]["result"] = "Could not rename the username during registration. Please contact an admin or try again later.";
			header("Location: " . $_SESSION["dataporten"]["last_url"]); exit;
		} elseif ($update_role_result == false) {
			// there was an error during registration, redirect and notify the user:
			$_SESSION["dataporten"]["result"] = "Could not assign default user role during registration. Please contact an admin or try again later.";
			header("Location: " . $_SESSION["dataporten"]["last_url"]); exit;
		} else {
			$this->dataporten_main->dataporten_link_account($user_id);

			$credentials = array(
				'user_login'  	=> $this->username,
				'user_password' => $this->password,
				'remember' 		=> true,
			);

			$user = wp_signon($credentials, false);
			$this->dataporten_main->dataporten_end_login("You have been registered successfully!", 0);
		}
	}
}

?>