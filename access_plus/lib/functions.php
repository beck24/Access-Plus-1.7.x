<?php
//
// called on creation of object, sets permissions
function access_plus_access_process($event, $object_type, $object){
	global $CONFIG;
	
	$flag = $object->access_id;
	
	//find which $_POST variable we need
	// will be an array in the form of $_POST['access_plus#'] where # is the view count
	$id = get_plugin_setting('get_field_count'.$flag, 'access_plus');
	
	//get our access collections for this object
	$access = $_POST['access_plus'.$id];
	
	//make sure it's an array
	if(is_array($access) && count($access) > 0){
		$new_access_id = access_plus_parse_access($access);
		
		if($object instanceof ElggEntity){
			$guid = $object->guid;
		}
		else{
			$guid = $object->id;
		}
		
		// for some reason we can't update here, gets reset to original value
		// save the new access_id in a plugin setting for later - will be updated on next pageload
		$pending = $object_type . ":" . $guid . ":" . $new_access_id;
		access_plus_add_to_pending_actions($pending);	
	} // if $access is array
}

//
// this function adds a string of pending operations to the existing list
function access_plus_add_to_pending_actions($pending){
	$currentpending = get_plugin_usersetting('pending_actions', get_loggedin_userid(), 'access_plus');
	
	if(!empty($currentpending)){
		$newpending = $currentpending . "," . $pending;
	}
	else{
		$newpending = $pending;
	}
	
	set_plugin_usersetting('pending_actions', $newpending, get_loggedin_userid(), 'access_plus');	
}

//
// adds the user to the list to sync their collections
// list is stored as a plugin setting, then processed on hourly cron
function access_plus_add_to_sync_list($event, $object_type, $object){
	$synclist = get_plugin_setting('synclist', 'access_plus');
	$syncarray = explode(",", $synclist);
	
	if(!is_array($syncarray)){
		$syncarray = array($object->guid);
	}
	else{
		if(!in_array($object->guid, $syncarray)){
			$syncarray[] = $object->guid;
		}
	}
	
	$synclist = implode(",", $syncarray);
	
	//save the modified list
	set_plugin_setting('synclist', $synclist, 'access_plus');
}


//
// adds a new id to the list of user collections
function access_plus_add_to_user_collection_keys($new_acl_id){
	$currentlist = get_plugin_usersetting('acls', get_loggedin_userid(), 'access_plus');
	
	// turn list into an array
	$currentarray = explode(",", $currentlist);
	
	// add our new collection id to the array
	if(!in_array($new_acl_id, $currentarray)){
		$currentarray[] = $new_acl_id;
	}
	
	//order it nicely and return back to a list
	$currentarray = array_unique($currentarray);
	$currentarray = array_values($currentarray);
	
	$newlist = implode(",", $currentarray);
	set_plugin_usersetting('acls', $newlist, get_loggedin_userid(), 'access_plus');
}

//
// called by the hook when a user is added to an access collection
// checks to see if that collection is used in a metacollection
// if so, checks to see if the user is already there, if not adds them
// $params['collection_id'], $params['collection'], $params['user_guid']
function access_plus_add_user($hook, $type, $returnvalue, $params){
	// set a custom context to overwrite permissions temporarily
	$context = get_context();
	set_context('access_plus_permission');
	
	//get an array of all of the users metacollections
	$currentlist = get_plugin_usersetting('acls', get_loggedin_userid(), 'access_plus');
	$metacollection_array = explode(",", $currentlist);
	
	// iterate though the metacollections
	foreach($metacollection_array as $id){
		$componentlist = get_plugin_usersetting($id, get_loggedin_userid(), 'access_plus');
		$components = explode(":", $componentlist);
		
		if(in_array($params['collection_id'], $components)){
			// the collection is being used in this metacollection
			// see if the user is already in this collection, otherwise add them
			$members = get_members_of_access_collection($id, true);
			
			if(is_array($members) && !in_array($params['user_guid'], $members)){
				// user is not in the metacollection already, so we need to add them
				add_user_to_access_collection($params['user_guid'], $id);
			}
		}
	}
	
	set_context($context);
}

function access_plus_blacklist($token){
	$blacklist = get_plugin_setting('blacklist', 'access_plus');
	$blackarray = explode(",", $blacklist);
	
	if(!in_array($token, $blackarray)){
		$blackarray[] = $token;
	}
	
	$blackarray = array_values($blackarray);
	
	$blacklist = implode(",", $blackarray);
	set_plugin_setting('blacklist', $blacklist, 'access_plus');
}

//
// creates a new flag for the access view count
// returns the id of the empty placeholder access collection
function access_plus_create_field_count_flag($access_view_count){
	if(!is_numeric($access_view_count)){
		return false;
	}
	
	// if guid is set to 0, then it defaults to logged in user
	// use -9999 so that it defaults to 0, and the users collections aren't cluttered
	$key = "access_plus_flag" . $access_view_count;
	$id = create_access_collection($key, -9999);
	
	// save our flag in plugin settings so we don't have to recreate it next time
	set_plugin_setting('field_count'.$access_view_count, $id, 'access_plus');
	// save a reverse setting so we can retrieve the field count from the id
	set_plugin_setting('get_field_count'.$id, $access_view_count, 'access_plus');
	
	return $id;
}

//
// creates a metacollection from an array of collection IDs
function access_plus_create_metacollection($access){
	if(!is_array($access)){
		return false;
	}
	
	for($i=0; $i<count($access); $i++){
		if($i == 0){
			$key = get_loggedin_userid() . ":" . $access[0];
		}
		else{
			$key .= ":" . $access[$i];
		}
	}
	
	// set a custom context to overwrite permissions temporarily
	$context = get_context();
	set_context('access_plus_permission');

	// if guid is set to 0, then it defaults to logged in user
	// use -9999 so that it defaults to 0, and the users collections aren't cluttered
	$id = create_access_collection($key, -9999);
	
	if(!$id){
		set_context($context);
		return false;
	}
	else{
		// we've created the metacollection, populate it from the component collections
		$members = array();
		for($i=0; $i<count($access); $i++){
			
			if($access[$i] == ACCESS_FRIENDS){
				// we're adding every friend we have in this special case
				$user = get_loggedin_user();
				$friends = $user->getFriends("", 0, 0);
				$tmp_members = array();
				foreach($friends as $friend){
					$tmp_members[] = $friend->guid;
				}
			}
			else{
				$tmp_members = get_members_of_access_collection($access[$i], true);
			}
			
			if(is_array($tmp_members) && count($tmp_members) > 0){
				$members = array_merge($members, $tmp_members);
			}
		}
		
		$members = array_unique($members);
		$members = array_values($members);
		
		// add each member to the metacollection
		foreach($members as $member){
			$done = add_user_to_access_collection($member, $id);
			if(!$done){
				register_error(elgg_echo('access_plus:add_user_to_metacollection:error'));
			}
		}
		
		set_context($context);
		return $id;
	}
	
	// return context to it's previous setting so we're not allowing unwarranted access
	// to anything else
	set_context($context);
}

//
// this function generates a token that is unique to a specific instance of an access view
// tokens can be used to enable or disable multi-use on a per-instance basis
// uses the name of the input field, as well as context and view count
function access_plus_generate_token($name){
	global $access_view_count;

	$context = get_context();
	
	return md5($context . $access_view_count . $name);
}

//
// returns true if the access instance is blacklisted
function access_plus_is_blacklisted($token){
	// get our blacklist from settings
	$blacklist = get_plugin_setting('blacklist', 'access_plus');
	$blackarray = explode(",", $blacklist);

	if(!is_array($blackarray)){ $blackarray = array(); }
	
	if(in_array($token, $blackarray)){
		// it's been blacklisted
		return true;
	}
	
	return false;
}

//
// this function tades accesses and merges the collections
function access_plus_merge_collections($access){

	// now we should have an array of collections that should be merged if necessary
	// first lets check to see if the collection already exists
			
	//collection id is stored in plugin settings
	//	stored with unique name in the form of <collection1>:<collection2>:...
				
	for($i=0; $i<count($access); $i++){
		if($i == 0){
			$key = $access[$i];
		}
		else{
			$key .= ":" . $access[$i];
		}
	}

	// get our saved access collection id, if it exists
	$acl_id = get_plugin_usersetting($key, get_loggedin_userid(), 'access_plus');
				
	if(!$acl_id){
		//we don't have an existing collection for this combination
		//have to create a new one
		$new_acl_id = access_plus_create_metacollection($access);
					
		if(is_numeric($new_acl_id)){
			//save our new collection ID
			// we save plugin settings with both the key and value reversed so we can
			// calculate what collections are merged later on
			set_plugin_usersetting($key, $new_acl_id, get_loggedin_userid(), 'access_plus');
			set_plugin_usersetting($new_acl_id, $key, get_loggedin_userid(), 'access_plus');
			access_plus_add_to_user_collection_keys($new_acl_id);
			$new_access_id = $new_acl_id;
		}
		else{
			//there was a problem, make it private instead and throw an error
			$new_access_id = ACCESS_PRIVATE;
			register_error(elgg_echo('access_plus:metacollection:creation:error'));
		}
	}
	else{
		//we have an existing collection for this so we'll use it
		$new_access_id = $acl_id;
	}

	return $new_access_id;
}

//
// this function takes an array of accesses, sorts out what the final value should be
function access_plus_parse_access($access){
	/*
	 *	if $access is not an array we should do nothing
	 *	if $access has only one item, we should set that as the object access_id
	 *
	 *	if $access has more than one item we need to do some processing then check for an existing
	 *	access collection.  If one exists for the combination given then we'll use it, otherwise
	 *	we'll create a new collection. 
	 *
	 *	There is a heirarchy that should be checked for:
	 *	eg. if public and something else is selected
	 *	then the something else is pointless, just set it as public.
	 *	If not public, but logged in users and something else, set as logged in
	 *	If not public or logged in, but friends, then set as friends
	 *	BUT we need to make sure there's no group collections because they might contain non-friends
	 *	in that case we make a metacollection for all friends plus that group...
	 *
	 *	If private and something else is set, then private is pointless - strip it out
	 *	After these filters, we can merge collections if necessary
	 */
	
	if(count($access) == 1){
		//only one option was selected, so set it and we're done
		$new_access_id = $access[0];
	}
	else{
		//there are multiple options selected
		sort($access);
		if(in_array(ACCESS_PUBLIC, $access)){
			//public was one of the selections, nothing else matters
			$new_access_id = ACCESS_PUBLIC;
		}
		elseif(in_array(ACCESS_LOGGED_IN, $access)){
			//logged in users was selected, trumps anything but public
			$new_access_id = ACCESS_LOGGED_IN;
		}
		elseif(in_array(ACCESS_FRIENDS, $access)){
			//friends selected, trumps anything but public and logged in
			// except if there are group access collections
			
			//check if there are group collections
			$groups = array();
			foreach($access as $access_id){
				$grouptest = false;
				if($access_id != ACCESS_FRIENDS){
					$collection = get_access_collection($access_id);
					$grouptest = get_entity($collection->owner_guid);
				}
				if($grouptest instanceof ElggGroup){
					$groups[] = $access_id;
				} 
			}
			
			if(count($groups) != 0){
				// there are groups, so we need to merge collections
				$groups[] = ACCESS_FRIENDS;
				sort($groups);
				$new_access_id = access_plus_merge_collections($groups);
			}
			else{
				// no groups, so we can just set it to friends
				$new_access_id = ACCESS_FRIENDS;
			}
		}
		else{
			//private was selected with something else, which makes private unneccesary
			//remove private from the access array
			if(in_array(ACCESS_PRIVATE, $access)){
				$access = access_plus_remove_from_array(ACCESS_PRIVATE, $access);
			}
			
			$new_access_id = access_plus_merge_collections($access);
		}
	}
	return $new_access_id;
}

//
// this function is called on the system init event, it updates any pending permission changes
function access_plus_pending_process(){
	global $CONFIG;
	
	$pending = get_plugin_usersetting('pending_actions', get_loggedin_userid(), 'access_plus');
	
	if(!empty($pending)){
		// we have a change to update
		$pendingarray = explode(",", $pending);
		
		// each $changearray is a string in the form of <object type>:<guid>:<access_id>
		foreach($pendingarray as $changestring){
			$change = explode(":", $changestring);
			
			// $change[0] = object type, $change[1] = guid, $change[2] = access_id
			$datatype = $change[0];
			$guid = intval($change[1]);
			$access_id = intval($change[2]);
			
			if($datatype == "object"){
				// make sure we have stuff to update
				if(is_numeric($guid) && is_numeric($access_id)){
					// direct call to database to prevent infinite loop of events
					update_data("UPDATE {$CONFIG->dbprefix}entities set access_id='$access_id' WHERE guid=$guid");
				}		
			}
			elseif($datatype == "metadata"){
				// make sure we have stuff to update
				if(is_numeric($guid) && is_numeric($access_id)){
					// direct call to database to prevent infinite loop of events
					update_data("UPDATE {$CONFIG->dbprefix}metadata set access_id=$access_id WHERE id=$guid");
				}
			}
			elseif($datatype == "annotation"){
				// make sure we have stuff to update
				if(is_numeric($guid) && is_numeric($access_id)){
					// direct call to database to prevent infinite loop of events
					update_data("UPDATE {$CONFIG->dbprefix}annotations set access_id=$access_id WHERE id=$guid");
				}
			}
			// as we add new datatypes this will be extended
		}
		
		// finished processing the necessary updates, remove pending actions
		set_plugin_usersetting('pending_actions', '', get_loggedin_userid(), 'access_plus');
	}
}

function access_plus_permissions_check(){
	$context = get_context();
	if($context == "access_plus_permissions"){
		return true;
	}
	
	return NULL;
}


//	removes a single item from an array
//	resets keys
//
function access_plus_remove_from_array($value, $array){
	if(!is_array($array)){ return $array; }
	if(!in_array($value, $array)){ return $array; }
	
	for($i=0; $i<count($array); $i++){
		if($value == $array[$i]){
			unset($array[$i]);
			$array = array_values($array);
		}
	}
	
	return $array;
}

//
// called by the hook when a user is removed from an access collection
// checks to see if that collection is used in a metacollection
// if so, checks to see if the user needs to be removed, if so removes them
// $params['user_guid'], $params['collection_id']
function access_plus_remove_user($hook, $type, $returnvalue, $params){
		// set a custom context to overwrite permissions temporarily
	$context = get_context();
	set_context('access_plus_permission');
	
	//get an array of all of the users metacollections
	$currentlist = get_plugin_usersetting('acls', get_loggedin_userid(), 'access_plus');
	$metacollection_array = explode(",", $currentlist);
	
	// iterate though the metacollections
	foreach($metacollection_array as $id){
		$componentlist = get_plugin_usersetting($id, get_loggedin_userid(), 'access_plus');
		$components = explode(":", $componentlist);
		
		if(in_array($params['collection_id'], $components)){
			// the collection is being used in this metacollection
			// count how many other component collections the removed user is a member of
			$count = 0;
			for($i=0; $i<count($components); $i++){
				$members = get_members_of_access_collection($components[$i], true);
				if(is_array($members) && in_array($params['user_guid'], $members)){
					// user is in this collection, count the collection
					$count++;
				}
			}
			
			if($count == 1){
				// the user is only in one component collection - the one being removed
				// must remove from the metacollection then
				remove_user_from_access_collection($params['user_guid'], $id);
			}
		}
	}
	
	set_context($context);
}

//
// function called on cron
// this will empty all of the metacollections and repopulate them properly
// this will restore proper permissions in case collections were edited while the
// plugin was disabled
function access_plus_sync_metacollections($hook, $entity_type, $returnvalue, $params){
	global $CONFIG;
	
	$synclist = get_plugin_setting('synclist', 'access_plus');
	$syncarray = explode(",", $synclist);

	//set a custom context to overwrite permissions temporarily
	$context = get_context();
	set_context('access_plus_permission');
	
	foreach($syncarray as $guid){
		$user = get_user($guid);
		if($user instanceof ElggUser){
	
			//get an array of all of the users metacollections
			$currentlist = get_plugin_usersetting('acls', $user->guid, 'access_plus');
			$metacollection_array = explode(",", $currentlist);
	
			// iterate though the metacollections
			foreach($metacollection_array as $id){
				if(is_numeric($id)){
					// first we empty the collection
					// using direct call for performance reasons and brevity
					$success = delete_data("DELETE FROM {$CONFIG->dbprefix}access_collection_membership WHERE access_collection_id=$id");
		
					$componentlist = get_plugin_usersetting($id, get_loggedin_userid(), 'access_plus');
					$components = explode(":", $componentlist);
		
					$members = array();
					for($i=0; $i<count($components); $i++){
						
						if($components[$i] == ACCESS_FRIENDS){
							$tmpmembers = array();
							$friends = $user->getFriends("", 0, 0);
							
							foreach($friends as $friend){
								$tmpmembers[] = $friend->guid;
							}
						}
						else{
							$tmpmembers = get_members_of_access_collection($components[$i], true);
						}
			
						if(is_array($tmpmembers)){
							$members = array_merge($members, $tmpmembers);
						}
					}
			
					// we now have an array of all the user guids that should be in the metacollection
					// make sure there's no duplicates, and we'll add them all back in
					$members = array_unique($members);
					$members = array_values($members);
		
					foreach($members as $member){
						add_user_to_access_collection($member, $id);
					}
				} 	// if id is numeric
			}	//iterating through metacollections
		}	// if user is instance of ElggUser
	} // foreach $synclist
	set_context($context);
	set_plugin_setting('synclist', '', 'access_plus');
}

//
// removes a token from the blacklist
function access_plus_unblacklist($token){
	$blacklist = get_plugin_setting('blacklist', 'access_plus');
	$blackarray = explode(",", $blacklist);
	
	$blackarray = access_plus_remove_from_array($token, $blackarray);
	
	$blacklist = implode(",", $blackarray);
	
	set_plugin_setting('blacklist', $blacklist, 'access_plus');
}