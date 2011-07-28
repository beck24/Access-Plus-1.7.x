<?php

// make sure only admins are doing this
admin_gatekeeper();

$token = get_input('token');

//sanity check
if(empty($token)){
	register_error(elgg_echo('access_plus:invalid:token'));
	forward(REFERRER);
}

if(access_plus_is_blacklisted($token)){
	// unblacklist
	access_plus_unblacklist($token);
	system_message(elgg_echo('access_plus:toggle:enabled'));
}
else{
	// blacklist
	access_plus_blacklist($token);
	system_message(elgg_echo('access_plus:toggle:disabled'));
}

forward(REFERRER);