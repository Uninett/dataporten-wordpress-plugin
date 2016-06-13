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
	const   URL_AUTH  = 'https://auth.dataporten.no/oauth/authorization?';
	const   URL_TOKEN = 'https://auth.dataporten.no/oauth/token?';
	const   URL_USER  = 'https://auth.dataporten.no/userinfo?';
	const 	URL_GROUP = 'https://groups-api.dataporten.no/groups/me/groups?';

	public function __construct($dataporten_main){

		$_SESSION['dataporten']['provider'] = 'Dataporten';

		$this->dataporten_main = $dataporten_main;
		$this->http_util       = get_option('dataporten_http_util');
		$this->client_enabled  = get_option('dataporten_oauth_enabled');
		$this->client_id 	   = get_option('dataporten_oauth_clientid');
		$this->client_secret   = get_option('dataporten_oauth_clientsecret');
		$this->redirect_uri    = get_option('dataporten_oauth_redirect_uri');
		$this->scope 		   = get_option('dataporten_oauth_clientscopes');
		
		if(!$_SESSION["dataporten"]["last_url"]) {
			
			$this->redirect_url = esc_url($_GET['redirect_to']);

			if(empty($this->redirect_url)) {
				$this->redirect_url = strtok($_SERVER['HTTP_REFERER'], '?');
			}
			if($this->redirect_url != "" && strpos($this->redirect_url, 'wp-login.php') !== false) $_SESSION['dataporten']['last_url'] = $this->redirect_url;
		}
	}

	public function pre_auth() {

		if((empty($_SESSION['dataporten']['expires_at'])) || (time() > $_SESSION['dataporten']['expires_at'])) {
			$this->dataporten_main->dataporten_clear_login_state();
		}
		$this->get_auth_code();
	}

	public function post_auth() {
		if ($_SESSION['dataporten']['state'] == $_GET['state']) {
			$this->get_oauth_token($this->dataporten_main);
			$this->dataporten_main->dataporten_login_user($this->get_oauth_identity($this));
		}
	}

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

			$_SESSION["dataporten"]["result"] = "Access-token wasn't found. Please contact an admin or try again later.";
			header("Location: " . $_SESSION["dataporten"]["last_url"]); exit;

		} else {
			$this->access_token = $access_token;
			$this->expires_in   = $expires_in;
			$this->expires_at 	= $expires_at;
		}
	}

	private function get_oauth_identity() {
		$params = array(
			'access_token' => $this->access_token,
		);
		$url_params = http_build_query($params);
		$url 		= Dataporten_oAuth_login::URL_USER . $url_params;
		$result 	= curl_exec($this->create_curl($url, array('Authorization: Bearer ' . $this->access_token), false));
		$result_obj = json_decode($result, true);

		if(get_option('dataporten_default_role_enabled')) {
			$url_params = http_build_query($params);
			$url 		= Dataporten_oAuth_login::URL_GROUP . $url_params;
			$result 	= curl_exec($this->create_curl($url, array('Authorization: Bearer ' . $this->access_token),  false));
			$result_grp = json_decode($result, true);
		}

		$oauth_identity = array(
			'provider' => 'dataporten',
			'id' 	   => $result_obj['user']['userid'],
			'email'    => $result_obj['user']['email'],
			'groups'    => $result_grp,
		);

		if (!$oauth_identity['id']) {

			$_SESSION["dataporten"]["result"] = "Could not complete the login. Please contact an admin or try again later.";
			header("Location: " . $_SESSION["dataporten"]["last_url"]); exit;

		} return $oauth_identity;
	}

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

	private function get_auth_code() {
		$params = array(
			'client_id' => $this->client_id,
			'response_type' => 'code',
			'redirect_uri'  => $this->redirect_uri,
			'state' 		=> uniqid('', true),
		);

		$_SESSION['dataporten']['state'] = $params['state'];
		$url = Dataporten_oAuth_login::URL_AUTH . http_build_query($params);
		
		header("Location: $url");
		exit;
	}
}

?>