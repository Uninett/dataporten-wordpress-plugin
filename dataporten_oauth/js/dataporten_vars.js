(function(){	//Self invoking function
	window.addEventListener("load", function() {
		if(dataporten_variables.hide_login_form == 1 && window.location.pathname.indexOf("wp-login.php") > 0) {
			try{
				document.getElementById("loginform").style.display = "none";	//Hides login form if defined in the database. Should be a better way of doing this.
				window.location.href=document.getElementById('dataporten-login-button').href;
			} catch(e){}
		}

		//
		//	Shows and removes the result container for messages from the backend.
		//

		if(document.getElementById("dataporten_outer") && document.getElementById("dataporten_result").innerHTML.length > 0) {
			var message 		  = document.getElementById("dataporten_outer");
			message.style.display = "block";
			message.style.opacity = 1;
			setTimeout(function() {
				message.style.opacity = 0;
				setTimeout(function() {
					message.remove();
				},500);
			}, 5000);
		}
	});
}());