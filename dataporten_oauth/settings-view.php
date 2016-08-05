

<h2>Dataporten-oAuth Settings</h2>
<form method="post" action="options.php">

	<?php

		//
		//
		//  Fills in the settings from the database.
		//
		//

		settings_fields('dataporten_settings');
		do_settings_sections('dataporten_settings');
	?>
	<h3>General Settings</h3>
	<table class="form-table">
		<tr style='display:<?php echo (get_option('users_can_register')) ? "none" : "table-row"; ?>;'>
			<th>
				<label for="dataporten_only">Enable only Dataporten register</label>
			</th>
			<td>
				<input type="checkbox" name="dataporten_only" value="1" id="dataporten_only" <?php checked(get_option("dataporten_only") == 1 && !get_option("users_can_register")); ?>/>
			</td>
		</tr>
		<tr>
			<th>
				<label for="dataporten_hide_native_wp">Hide Native Wordpress Login</label>
			</th>
			<td>
				<input type="checkbox" name="dataporten_hide_native_wp" value="1" id="dataporten_hide_native_wp" <?php checked(get_option("dataporten_hide_native_wp") == 1); ?> />
			</td>
		</tr>
		<tr>
			<th>
				<label for="dataporten_default_redirect">Redirect on login: </label>
			</th>
			<td>
				<select id="dataporten_default_redirect" name="dataporten_login_redirect">
					<option value="home_page" <?php selected(get_option("dataporten_login_redirect"), "home_page"); ?>>Home Page</option>
					<option value="last_page" <?php selected(get_option("dataporten_login_redirect"), "last_page"); ?>>Last Page</option>
				</select>
			</td>
		</tr>
		<tr>
			<th>
				<label for="dataporten_ssl_verify">HTTP Util Verify SSL</label>
			</th>
			<td>
				<input type="checkbox" name="dataporten_http_util_verify_ssl" value="1" id="ssl_verify" <?php checked(get_option('dataporten_http_util_verify_ssl') == 1); ?> />
			</td>
		</tr>
		<tr>
			<th>
				<label for="dataporten_default_role_enabled">New user default role enabled: </label>
			</th>
			<td>
				<input type="checkbox" name="dataporten_default_role_enabled" value="1" id="default_role_enabled" <?php checked(get_option("dataporten_default_role_enabled") == 1); ?> />
			</td>
		</tr>
		<tr>
			<th>
				<label for="dataporten_default_role">New user default role: </label>
			</th>
			<td>
				<input type="input" name="dataporten_rolesets" id="dataporten_default_role" value='<?php echo get_option("dataporten_rolesets"); ?>' <?php disabled(get_option('dataporten_default_role_enabled') == 0); ?> ></input>
			</td>
		</tr>
		<tr>
			<th>
				Info
			</th>
			<td>
			User role-sets should be on the format {"fc:org:uninett.no:unit:SEL-S": "editor", "fc:org:uninett.no":"administrator"} and so on. If a user is a member of more than one group in the defined rules, the last occurence in the above defined array will be the one that is chosen. If group2 is the last one, then the role of group2 will be the one that is added in the database.
			</td>
		</tr>
	</table>
	<h3>Dataporten Settings</h3>
	<table class="form-table">
		<tr>
			<th>
				<label for="dataporten_enabled">Enabled:</label>
			</th>
			<td>
				<input type="checkbox" class="dataporten-settings-input" name="dataporten_oauth_enabled" value="1" id="dataporten_enabled" <?php checked(get_option("dataporten_oauth_enabled") == 1); ?> />
			</td>
		</tr>
		<tr>
			<th>
				<label for="dataporten_clientid">Client ID:</label>
			</th>
			<td>
				<input type="text" class="dataporten-settings-input" name="dataporten_oauth_clientid" value="<?php echo get_option("dataporten_oauth_clientid"); ?>" id="dataporten_clientid" />
			</td>
		</tr>
		<tr>
			<th>
				<label for="dataporten_clientsecret">Client Secret:</label>
			</th>
			<td>
				<input type="text" class="dataporten-settings-input" name="dataporten_oauth_clientsecret" value="<?php echo get_option("dataporten_oauth_clientsecret"); ?>" id="dataporten_clientsecret" />
			</td>
		</tr>
		<tr>
			<th>
				<label for="dataporten_redirect_uri">Redirect Uri:</label>
			</th>
			<td>
				<input type="text" class="dataporten-settings-input" name="dataporten_oauth_redirect_uri" value="<?php echo get_option("dataporten_oauth_redirect_uri"); ?>" id="dataporten_redirect_uri" />
			</td>
		</tr>
		<tr>
			<th>
				<label for="dataporten_clientscopes">Client Scopes:</label>
			</th>
			<td>
				<input type="text" class="dataporten-settings-input" name="dataporten_oauth_clientscopes" value="<?php echo get_option("dataporten_oauth_clientscopes"); ?>" id="dataporten_clientscopes" />
			</td>
		</tr>
	</table>
	<?php submit_button('Save all settings'); ?>
</form>
