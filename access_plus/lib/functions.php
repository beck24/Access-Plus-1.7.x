<?php
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
			$tmp_members = get_members_of_access_collection($access[$i], true);
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
// called on creation of object, sets permissions
function access_plus_object_create($event, $object_type, $object){
	global $CONFIG;
	
	//get our access collections for this object
	$access = $_POST['access_plus'];
	//after first use unset the post variable to prevent infinite loop!
	unset($_POST['access_plus']);
	
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
	 *	(as friends are required to make the collections)
	 *
	 *	If private and something else is set, then private is pointless - strip it out
	 *	After these filters, we can merge collections if necessary
	 */
	
	//make sure it's an array
	if(is_array($access) && count($access) > 0){
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
				$new_access_id = ACCESS_FRIENDS;
			}
			else{
				//private was selected with something else, which makes private unneccesary
				//remove private from the access array
				if(in_array(ACCESS_PRIVATE, $access)){
					$access = access_plus_remove_from_array(ACCESS_PRIVATE, $access);
				}
				
				// now we should have an array of collections that should be merged if necessary
				// first lets check to see if the collection already exists
				
				//collection id is stored in plugin settings
				//stored with unique name in the form of <collection1>:<collection2>:...
				
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
			}
		}
		// perform our query
		// using a direct call to the database so an infinite loop of update events isn't triggered
		// also there is an as-yet unidentified bug that resets the access_id to the original on an update
		// Hopefully I can find a more Elgg-ish way to do this, but until then...
		
		if($event == "create"){
			update_data("UPDATE {$CONFIG->dbprefix}entities set access_id='$new_access_id' WHERE guid=$object->guid");
		}
		elseif($event == "update"){
			// for some reason we can't update here, gets reset to original value
			// save the new access_id in a plugin setting for later - will be updated on next pageload
			$pending = $object->guid . "," . $new_access_id;
			set_plugin_usersetting('pending_actions', $pending, get_loggedin_userid(), 'access_plus');	
		}
	} // if $access is array
}

//
// this function is called on the system init event, it updates any pending permission changes
function access_plus_pending_process(){
	global $CONFIG;
	
	$change = get_plugin_usersetting('pending_actions', get_loggedin_userid(), 'access_plus');
	
	if(!empty($change)){
		// we have a change to update
		$changearray = explode(",", $change);
		$guid = $changearray[0];
		$access_id = $changearray[1];
		
		// make sure we have stuff to update
		if(is_numeric($guid) && is_numeric($access_id)){
			$success = update_data("UPDATE {$CONFIG->dbprefix}entities set access_id='$access_id' WHERE guid=$guid");
			
			if($success){
				set_plugin_usersetting('pending_actions', '', get_loggedin_userid(), 'access_plus');
			}
		}
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
function access_plus_remove_user($hook, $type, $returnvalue, $params){
	
}