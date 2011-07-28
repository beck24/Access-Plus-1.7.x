******************************************
 *                                      *
  *             Access Plus            *
 *                                      *
******************************************

Designed and Coded by: Matt Beckett

Scope:
This plugin enables a user to select multiple access collections for a single object,
much like google+.  This has been an often requested feature even before the advent of google+.

Method:
This plugin creates metacollections containing each of the users within the selected
access collections.  This means the default elgg access framework stays in tact!

Features:
Admin can enable/disable the multi-access interface on an individual basis.  This means
that if there is one instance where it is buggy, the admin can disable it for that instance
only and still use the plugin.
Note that this does not work (well) for widgets, but widgets work well with this plugin anyway.

Limitations:
Due to the necessity of maintaining the metacollections, if the plugin is disabled the
metacollections will remain in the state they are at the time of disabling.
One potential unintended scenario that could happen is as follows:

1. Plugin is enabled
2. A user creates a blog post, sets multiple collections for access
3. Plugin is disabled
4. The user removes a friend from one of the access collections
5. The removed friend still has access to the post because the
metacollection wasn't updated with the removal change

Also the reverse is possible

1. Plugin is enabled
2. User creates a blog post, sets multiple collections for access
3. Plugin is disabled
4. The user adds a new friend to one of the access collections
5. The added friend does not have access to the post because the metacollection
wasn't updated with the new friend


There is a sync function that is called when a user logs in, so these problems will correct
themselves if the plugin is re-enabled.
Generally, just don't toggle it off and on for no good reason.