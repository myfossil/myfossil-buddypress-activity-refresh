# myFOSSIL BuddyPress Activity Refresh

This plugin will check automatically for new activities and comments and add
them to the activity stream.

The JavaScript file "refresh.js" will send every 10 seconds an ajax request to
wp-load.php to get the new activities. If there are new activities, they will
be prepend to the activity list. Comments will appear in stream mode.

## Installation

1. Copy to /wp-content/plugins/
2. In the WordPress Admin panel, visit the plugins page and Activate the plugin.

## Formatting new Activities

New activities and comments have the css class "new-update".

Example:
`.new-update { background-color: #ffff00; }`

## History

This is a fork of spitzohr's [RS BuddyPress Activity Refresh plugin](https://wordpress.org/plugins/rs-buddypress-activity-refresh).
