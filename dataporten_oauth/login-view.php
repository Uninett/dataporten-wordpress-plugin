<?php
	if ($profile) {
?>
	<table class="form-table">
		<tr id="dataporten-link">
			<th>
				Dataporten
			</th>
			<td>
				<a class="button button-secondary" href="<?php echo $button_params['href']; ?>"><?php echo $button_params["text"]; ?></a>
			</td>
		</tr>
	</table>
<?php
	} else {
?>
	<a class="<?php echo $button_params['class']; ?>" href="<?php echo $button_params['href']; ?>"><?php echo $button_params["text"]; ?></a>
<?php } ?>