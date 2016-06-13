(function(){
	window.addEventListener("load", function() {
		if(dataporten_variables.hide_login_form == 1 && window.location.pathname.indexOf("wp-login.php") > 0) {
			try{
				document.getElementById("loginform").style.display = "none";
			} catch(e){}
		}

		if(document.getElementById("dataporten_outer")) {
			setTimeout(function() {
				var message = document.getElementById("dataporten_outer");
				message.style.opacity = 0;
				setTimeout(function() {
					message.remove();
				},500);
			}, 5000);
		}
		//if(window.location.pathname.indexOf("wp-admin/options-general.php") > 0) prettyPrint();
	});

	function prettyPrint() {
	    var ugly = document.getElementById('dataporten_default_role').value;
	    var obj = JSON.parse(ugly);
	    var pretty = JSON.stringify(obj, undefined, 4);
	    document.getElementById('dataporten_default_role').value = pretty;
	}

}());