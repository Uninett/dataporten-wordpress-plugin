<?php

class Dataporten_oAuth_login {

	private $redirect_url;
	private $http_util;
	private $client_enabled;
	private $client_secret;
	private $redirect_uri;
	private $dataporten_main;
	private $scope;
	private $access_token;
	private $expires_in;
	private $expires_at;
	private $url;
	const   URL_AUTH  = 'https://auth.dataporten.no/oauth/authorization?';
	const   URL_TOKEN = 'https://auth.dataporten.no/oauth/token?';
	const   URL_USER  = 'https://auth.dataporten.no/userinfo?';
	const 	URL_GROUP = 'https://groups-api.dataporten.no/groups/me/groups?';

	//
	//
	//	Construct function for login class. Takes in the dataporten main
	//	variables for easier access of its functions.
	//
	//

	public function __construct($dataporten_main){

		$this->dataporten_main = $dataporten_main;
		$this->http_util       = get_option('dataporten_http_util');
		$this->client_enabled  = get_option('dataporten_oauth_enabled');
		$this->client_id 	   = get_option('dataporten_oauth_clientid');
		$this->client_secret   = get_option('dataporten_oauth_clientsecret');
		$this->redirect_uri    = get_option('dataporten_oauth_redirect_uri');
		$this->scope 		   = get_option('dataporten_oauth_clientscopes');


		if(!isset($_GET['redirect_to']) && isset($_SERVER['HTTP_REFERER'])) {
			$this->redirect_url = strtok($_SERVER['HTTP_REFERER'], '?');
		} else if(isset($_GET['redirect_to'])){
			$this->redirect_url = $_GET['redirect_to'];
		}

		if($this->redirect_url != "" && strpos($this->redirect_url, 'wp-login.php') === false) $this->url = $this->redirect_url;

	}

	//
	//
	//	Setup before the authentication. Fetching authentication code.
	//
	//

	public function pre_auth() {
		$this->get_auth_code();
	}

	//
	//
	//	Setup after authentication. Gets authentication token, and logs in the
	//	user depending on the identity of the user. Checks whether the state gotten from
	//	the oAuth2.0 server is the same saved in the database. If it is, it deletes the
	//	entry in the database, fetches the saved url from the state variable, and adds
	//	it to the identity variable that's sent to dataporten_login_user. If the states
	//	doesn't match, the login is terminated.
	//
	//

	public function post_auth() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'dataporten_oauth';

		$str 	= mb_convert_encoding($_GET['state'], 'UTF-8', 'UTF-8');
		$str 	= htmlentities($str, ENT_QUOTES, 'UTF-8');
		$states = $wpdb->get_results( "SELECT * FROM $table_name WHERE state = '$str'", ARRAY_A );
		$added  = strtotime($states["0"]["added"]);

		if(count($states) == 1 && $states["0"]["state"] == $_GET['state']
			&& $added + 600 > time()) {
			$state 		  = $states["0"]["state"];
			$query_string = $wpdb->prepare("DELETE FROM $table_name WHERE $table_name.state = %d", $state);
			$query_result = $wpdb->query($query_string);

			$this->get_oauth_token();
			$identity = $this->get_oauth_identity();
			$identity["url"] = $states["0"]["url"];
			$this->dataporten_main->dataporten_login_user($identity);
		} else {
			$this->dataporten_main->dataporten_end_login("?errors=2", -1, "foo");
		}
	}

	//
	//
	//	Function for getting oAuth-token from dataporten. Calls the create_curl for
	//	easier creation of curl-variable. Defines the accesstoken, expires in and expires at
	//	When token is fetched.
	//
	//

	private function get_oauth_token() {
		$code   = htmlspecialchars($_GET['code']);
		$params = array(
			'grant_type' 	=> 'authorization_code',
			'client_id'  	=> $this->client_id,
			'client_secret' => $this->client_secret,
			'code' 			=> $code,
			'redirect_uri'  => $this->redirect_uri,
		);

		$url_params = http_build_query($params);
		$url  		= Dataporten_oAuth_login::URL_TOKEN . $url_params;

		$result 	  = curl_exec($this->create_curl($url, false, $params));
		$result_obj   = json_decode($result, true);
		$access_token = $result_obj['access_token'];
		$expires_in   = $result_obj['expires_in'];
		$expires_at   = time() + $expires_in;

		if (!$access_token || !$expires_in) {
			header("Location: " . wp_login_url() . "?errors=5"); exit;

		} else {
			$this->access_token = $access_token;
			$this->expires_in   = $expires_in;
			$this->expires_at 	= $expires_at;
		}
	}

	//
	//
	//	Fetches the users identity, and adds them to an array of parameters. If the
	//	dataporten-default_role_enabled is enabled, the users group is fetched
	//	enabling later scripts to assign a role to the user.
	//
	//

	private function get_oauth_identity() {
		$params = array(
			'access_token' => $this->access_token,
		);
		$url_params = http_build_query($params);
		$url 		= Dataporten_oAuth_login::URL_USER . $url_params;
		$result 	= curl_exec($this->create_curl($url, array('Authorization: Bearer ' . $this->access_token), false));
		$result_obj = json_decode($result, true);
		$result_grp = array();
		$userid 	= "";
		$email 		= "";
		$first_name 		= "";
		$last_name 			= "";

		if(array_key_exists("user", $result_obj)) {
			if(array_key_exists("userid", $result_obj["user"])) $userid = $result_obj["user"]["userid"];
			if(array_key_exists("email", $result_obj["user"])) $email = $result_obj["user"]["email"];
			if(array_key_exists("name", $result_obj["user"])){
				$full_name = explode(" ", $result_obj["user"]["name"], 2);
				$first_name = $full_name[0];
				$last_name = $full_name[1];
			}
		} else {
			if(array_key_exists("userid", $result_obj)) $userid = $result_obj["userid"];
			if(array_key_exists("email", $result_obj)) $email = $result_obj["email"];
			if(array_key_exists("name", $result_obj)){
				$full_name = explode(" ", $result_obj["name"], 2);
				$first_name = $full_name[0];
				$last_name = $full_name[1];
			}
		}

		if(get_option('dataporten_default_role_enabled')) {
			$url_params = http_build_query($params);
			$url 		= Dataporten_oAuth_login::URL_GROUP . $url_params;
			$result 	= curl_exec($this->create_curl($url, array('Authorization: Bearer ' . $this->access_token),  false));
			$result_grp = json_decode($result, true);
		}

		$oauth_identity = array(
			'provider' => 'dataporten',
			'id' 	   => $userid,
			'email'    => $email,
			'firstname'=> $first_name,
			'lastname' => $last_name,
			'groups'   => $result_grp,
		);

		if (!$oauth_identity['id']) {
			header("Location: " . wp_login_url() . "?errors=5"); exit;

		} return $oauth_identity;
	}

	//
	//
	//	Function for fetching auth_code. Builds http-query, and redirects the user
	//	to dataporten login.
	//
	//

	private function get_auth_code() {
		$state  = uniqid('', true);
		$params = array(
			'client_id' 	=> $this->client_id,
			'response_type' => 'code',
			'redirect_uri'  => $this->redirect_uri,
			'state' 		=> $state,
		);

		$this->insert_state($state);

		$url = Dataporten_oAuth_login::URL_AUTH . http_build_query($params);

		header("Location: $url");
		exit;
	}

	//
	//
	//	Adds the created state to the database for later references
	//
	//

	private function insert_state($state) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'dataporten_oauth';
		$url 		= $this->url ? $this->url : "";

		$wpdb->insert(
			$table_name,
			array(
				'state' => $state,
				'url' 	=> $url,
				'added' => date("Y-m-d H:i:s"),
			)
		);
	}

	//
	//
	//	Local function for creating curl variables. To minimize the repetition of code.
	//
	//

	private function create_curl($url, $header, $extended) {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		if ($header){
			curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		}
		if ($extended) {
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $extended);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, (get_option('dataporten_http_util_verify_ssl') == 1 ? 1: 0));
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, (get_option('dataporten_http_util_verify_ssl') == 1 ? 2: 0));
		}
		return $curl;
	}
}

?>
