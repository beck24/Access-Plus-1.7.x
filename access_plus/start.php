<?php
/**
 *Access Plus
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License version 2
 * @author Matt Beckett
 * @copyright Matt Beckett 2011
 */

/**
 *
 */
include_once 'lib/functions.php';

function access_plus_init() {

	// Load system configuration
	global $CONFIG;
	
	// Extend system CSS with our own styles
	elgg_extend_view('css','access_plus/css', 1000);

	// Load the language file
	register_translations($CONFIG->pluginspath . "access_plus/languages/");
	
	//set_plugin_setting('firstrun', '', 'access_plus');
	// Check if this is the first time the plugin is run
	$firstrun = get_plugin_setting('firstrun', 'access_plus');
	
	if(empty($firstrun)){
		// this is the first time, set the plugin setting so the code below won't be set ever again
		set_plugin_setting('firstrun', 1, 'access_plus');
		// set default blacklist
		// these should be common to all elgg installations
		// they represent access selections that are buggy at this time
		// may not work depending on other plugins, but it can't hurt
		$blackarray = array();
		// group open/closed
		$blackarray[] = "1354927dabe566ba9b5e02082e3c260a";
		// page write access
		$blackarray[] = "7daf5c485c0a582297bec6a6dab25e4f";
		$blacklist = implode(",", $blackarray);
		set_plugin_setting('blacklist', $blacklist, 'access_plus');
	}
	
	// override permissions for the access_plus_permissions context
	register_plugin_hook('permissions_check', 'all', 'access_plus_permissions_check');
	
	// watch for changes in collection membership
	register_plugin_hook('access:collections:add_user', 'collection', 'access_plus_add_user');
	register_plugin_hook('access:collections:remove_user', 'collection', 'access_plus_remove_user');
}

// call function on object creation and update to set permissions
register_elgg_event_handler('create','object','access_plus_access_process', 0);
register_elgg_event_handler('update','object','access_plus_access_process', 0);

// call function on metadata creation and update to set permissions
register_elgg_event_handler('create','metadata','access_plus_access_process', 0);
register_elgg_event_handler('update','metadata','access_plus_access_process', 0);

// call function on annotation creation and update to set permissions
register_elgg_event_handler('create','annotation','access_plus_access_process', 0);
register_elgg_event_handler('update','annotation','access_plus_access_process', 0);

// call function on page load to update any permissions that are pending
register_elgg_event_handler('init', 'system', 'access_plus_pending_process');

//call function on user login to synchronize the metacollections with current collections
register_elgg_event_handler('login', 'user', 'access_plus_sync_metacollections');

register_elgg_event_handler('init','system','access_plus_init');

//register action to toggle our access view
register_action("access_plus/toggle", false, $CONFIG->pluginspath . "access_plus/actions/access_plus/toggle.php", true);
?>