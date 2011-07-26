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
 */

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
	
	$elgg_access = array(
		ACCESS_PRIVATE => elgg_echo('PRIVATE'),
		ACCESS_FRIENDS => elgg_echo('access:friends:label'),
		ACCESS_LOGGED_IN => elgg_echo('LOGGED_IN'),
		ACCESS_PUBLIC => elgg_echo('PUBLIC'),
	);
	?>
	<div class="access_plus_wrapper">
	<?php 
	/*
	 * Set as private for now, the plugin will figure out the access on the create/update hook
	 */
	?>
	<input type="hidden" <?php if (isset($vars['internalid'])) echo "id=\"{$vars['internalid']}\""; ?> name="<?php echo $vars['internalname']; ?>" value="<?php echo $vars['value']; ?>">
	<?php
	echo "<div class=\"access_plus_site-wide-options\">";
	foreach($elgg_access as $key => $option) {
		if(in_array($key, $collectionarray)){
			echo "<input name=\"access_plus[]\" id=\"access_plus{$key}\" class=\"access_plustoggle\" type=\"checkbox\" value=\"{$key}\" checked=\"checked\"><label for=\"access_plus{$key}\">". htmlentities($option, ENT_QUOTES, 'UTF-8') ."</label><br>";
		} else {
			echo "<input name=\"access_plus[]\" id=\"access_plus{$key}\" class=\"access_plustoggle\" type=\"checkbox\" value=\"{$key}\"><label for=\"access_plus{$key}\">". htmlentities($option, ENT_QUOTES, 'UTF-8') ."</label><br>";
		}
	}
	echo "</div>"; // access_plus_site-wide-options
		
	asort($vars['options']);
	foreach($vars['options'] as $key => $option) {
		if(!in_array($option, $elgg_access)){
			if(in_array($key, $collectionarray)){
				echo "<input name=\"access_plus[]\" id=\"access_plus{$key}\" class=\"access_plustoggle\" type=\"checkbox\" value=\"{$key}\" checked=\"checked\"><label for=\"access_plus{$key}\">". htmlentities($option, ENT_QUOTES, 'UTF-8') ."</label><br>";
			} else {
				echo "<input name=\"access_plus[]\" id=\"access_plus{$key}\" class=\"access_plustoggle\" type=\"checkbox\" value=\"{$key}\"><label for=\"access_plus{$key}\">". htmlentities($option, ENT_QUOTES, 'UTF-8') ."</label><br>";
			}
		}
	}

	?>
	</div>
	<?php
}