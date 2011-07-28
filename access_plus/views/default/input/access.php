<?php
/**
 * Elgg access level input
 * Displays a pulldown input field
 *
 * @package Elgg
 * @subpackage Core


 *
 * @uses $vars['value'] The current value, if any
 * @uses $vars['js'] Any Javascript to enter into the input tag
 * @uses $vars['internalname'] The name of the input field
 *
 *	//use context to select when to replace the default view
 *	blog - blog
 *	dashboard - widgets
 *	profile - widgets
 */
global $access_view_count;
//count the number of times the view has been called - keeps ids unique
//and used to generate the token
if(!is_numeric($access_view_count)){
	$access_view_count = 0;
}
else{
	$access_view_count++;
}

// get our token and see if this view has been blacklisted
$token = access_plus_generate_token($vars['internalname']);
//var_dump($token);

// check to see if our token has been blacklisted
if(access_plus_is_blacklisted($token)){
	// it's been blacklisted, show the regular access control
	include "access_original.php";
}
else{
	// not blacklisted - show the cool controls
	
	$class = "input-access";
	if (isset($vars['class'])) {
		$class = $vars['class'];
	}

	$disabled = false;
	if (isset($vars['disabled'])) {
		$disabled = $vars['disabled'];
	}

	if (!array_key_exists('value', $vars) || $vars['value'] == ACCESS_DEFAULT) {
		$vars['value'] = get_default_access();
	}

	// check to see if the value is a metacollection
	$metacollection_id = get_plugin_usersetting($vars['value'], get_loggedin_userid(), 'access_plus');

	$collectionarray = array($vars['value']);
	if(!empty($metacollection_id)){
		$collectionarray = explode(":", $metacollection_id);
	}


	if ((!isset($vars['options'])) || (!is_array($vars['options']))) {
		$vars['options'] = array();
		$vars['options'] = get_write_access_array();
	}

	if (is_array($vars['options']) && sizeof($vars['options']) > 0) {

		echo "<br>";

		// sort $vars['options'], if there are elgg default site-wide options put them first
		// everything else do alphabetical afterwards
		$tmpoptions = array();

		if(array_key_exists(ACCESS_PRIVATE, $vars['options'])){
			$tmpoptions[][ACCESS_PRIVATE] = $vars['options'][ACCESS_PRIVATE];
			unset($vars['options'][ACCESS_PRIVATE]);
		}

		if(array_key_exists(ACCESS_FRIENDS, $vars['options'])){
			$tmpoptions[][ACCESS_FRIENDS] = $vars['options'][ACCESS_FRIENDS];
			unset($vars['options'][ACCESS_FRIENDS]);
		}

		if(array_key_exists(ACCESS_LOGGED_IN, $vars['options'])){
			$tmpoptions[][ACCESS_LOGGED_IN] = $vars['options'][ACCESS_LOGGED_IN];
			unset($vars['options'][ACCESS_LOGGED_IN]);
		}

		if(array_key_exists(ACCESS_PUBLIC, $vars['options'])){
			$tmpoptions[][ACCESS_PUBLIC] = $vars['options'][ACCESS_PUBLIC];
			unset($vars['options'][ACCESS_PUBLIC]);
		}

		// sort the remaining options alphabetically
		if(is_array($vars['options']) && count($vars['options']) > 0){
			asort($vars['options']);
		}

		foreach($vars['options'] as $key => $value){
			$tmpoptions[][$key] = $value;
		}

		$elgg_access = array(
		ACCESS_PRIVATE,
		ACCESS_FRIENDS,
		ACCESS_LOGGED_IN,
		ACCESS_PUBLIC,
		);
		?>
<div class="access_plus_wrapper">
<?php
/*
 * Set as private for now, the plugin will figure out the access on the create/update hook
 */
?>
	<input type="hidden"
	<?php if (isset($vars['internalid'])) echo "id=\"{$vars['internalid']}\""; ?>
		name="<?php echo $vars['internalname']; ?>"
		value="<?php echo $vars['value']; ?>">
		<?php

		$name = $vars['internalname'];
		$oddeven = 0;
		for($i=0; $i<count($tmpoptions); $i++){
			$keys = array_keys($tmpoptions[$i]);
			$key = $keys[0];
			// set up odd/even class name for zebra striping css
			$oddeven++;
			if($oddeven % 2){ $zebra = "zebra_odd"; }else{ $zebra = "zebra_even"; }
			if(in_array($key, $elgg_access)){ $zebra = "site-wide-options"; }
			echo "<div class=\"access_plus_$zebra\">";
			if(in_array($key, $collectionarray)){
				echo "<input name=\"access_plus[]\" id=\"access_plus_{$access_view_count}_{$key}\" class=\"{$vars['class']}\" type=\"checkbox\" value=\"{$key}\" checked=\"checked\"><label for=\"access_plus_{$access_view_count}_{$key}\">". htmlentities($tmpoptions[$i][$key], ENT_QUOTES, 'UTF-8') ."</label>";
			} else {
				echo "<input name=\"access_plus[]\" id=\"access_plus_{$access_view_count}_{$key}\" class=\"{$vars['class']}\" type=\"checkbox\" value=\"{$key}\"><label for=\"access_plus_{$access_view_count}_{$key}\">". htmlentities($tmpoptions[$i][$key], ENT_QUOTES, 'UTF-8') ."</label>";
			}

			echo "</div>"; // access_plus_$zebra
	}

	?>
</div>
<?php
	} //is_array
} // end of cool controls


//
// add the link to toggle the access view if admin
if(isadminloggedin ()){
	$url = $CONFIG->url . "action/access_plus/toggle?token=" . $token;
	$url = elgg_add_action_tokens_to_url($url);
	
	$linktext = elgg_echo('access_plus:toggle:off');
	if(access_plus_is_blacklisted($token)){
		$linktext = elgg_echo('access_plus:toggle:on');
	}
	
	echo "<div><a href=\"$url\">" . $linktext . "</a></div>";
}