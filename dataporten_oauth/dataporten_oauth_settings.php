<?php

class Dataporten_oAuth_settings {

	private $dataporten_main;

	public function __construct($dataporten_main) {
		$this->dataporten_main = $dataporten_main;
	}

	public function print_settings() {
		$dataporten_main = $this->dataporten_main;
		include 'settings-view.php';
	}

}

?>
