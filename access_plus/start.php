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
	
	// override permissions for the access_plus_permissions context
	register_plugin_hook('permissions_check', 'all', 'access_plus_permissions_check');
	
	// watch for changes in collection membership
	register_plugin_hook('access:collections:add_user', 'collection', 'access_plus_add_user');
	register_plugin_hook('access:collections:remove_user', 'collection', 'access_plus_remove_user');
}

// call function on object creation to set permissions
register_elgg_event_handler('create','object','access_plus_object_create', 1000);

// call function on object update to set permissions
register_elgg_event_handler('update','object','access_plus_object_create', 1000);

// call function on page load to update any permissions that are pending
register_elgg_event_handler('init', 'system', 'access_plus_pending_process', 1000);

//call function on user logout to synchronize the metacollections with current collections
register_elgg_event_handler('logout', 'user', 'access_plus_sync_metacollections', 1000);

register_elgg_event_handler('init','system','access_plus_init');
?>