<?php
/*
Plugin Name: myFOSSIL BuddyPress Activity Refresh
Description: This plugin automatically refreshes the BuddyPress Activity stream.
Author: Florian Koenig-Heidinger, forked and updated by Brandon Wood
Tags: buddypress
Version: 0.3.1
*/

class myFOSSILBuddypressActivityRefresh
{

    /**
     * Plugin Folder
     *
     * @access protected
     * @var string
     */
    protected $plugin_folder = null;

    /**
     * Plugin Directory
     *
     * @access protected
     * @var string
     */
    protected $plugin_dir = null;

    /**
     * Plugin Url
     *
     * @access protected
     * @var string
     */
    protected $plugin_url = null;

    /**
     * Plugin File
     *
     * @access protected
     * @var string
     */
    protected $plugin_file = null;

    /**
     * Default Refresh Rate in seconds
     *
     * @access protected
     * @var integer
     */
    protected $defaultRefreshRate = 10;

    /**
     * Default Time Format
     * Valid values are 'since', 'datetime'
     *
     * @access protected
     * @var string
     */
    protected $defaultTimeFormat = 'since';

    /**
     * Allowed Values for Time Format
     *
     * @access protected
     * @var array
     */
    protected $allowedValuesTimeFormat = array( 'since', 'datetime' );

    /**
     * Admin Options Name
     *
     * @access protected
     * @var string
     */
    protected $adminOptionsName = 'myFOSSILBuddypressActivityRefresh';

    /**
     * Options array
     *
     * @access protected
     * @var array
     */
    protected $options = null;

    /**
     * Constructor, does nothing.
     *
     * @access public
     */
    public function __construct()
    {
    } // function construct()

    /**
     * Initialising
     *
     * @access public
     */
    public function init()
    {
        $this->plugin_folder = substr( dirname( __FILE__ ), strrpos( dirname( __FILE__ ), DIRECTORY_SEPARATOR ) + 1 );
        $this->plugin_url = WP_PLUGIN_URL . '/' . $this->plugin_folder;
        $this->plugin_dir = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $this->plugin_folder;
        $this->plugin_file = $this->plugin_folder . '/' . basename( __FILE__ );

        // load Options
        $this->getOptions();

        // only add the JavaScript, if the refreshRate > 0
        if ( $this->options['refreshRate'] > 0 ) {
            // adding the refresh javascript file
            wp_enqueue_script( 'jquery-timeago-js', $this->plugin_url . '/js/jquery.timeago.js', array( 'jquery' ) );
            wp_enqueue_script( 'myfossil-bp-activity-refresh-ajax-js', $this->plugin_url . '/js/refresh.js', array( 'jquery' ) );

            // adding JavaScript Refresh Rate to html head
            add_action( 'wp_head', array( $this, 'addJavaScriptRefreshRate' ) );

            // add method ajaxRefresh to action hook wp_ajax_myfossil_bp_activity_refresh
            add_action( 'wp_ajax_myfossil_bp_activity_refresh', array( $this, 'ajaxRefresh' ) );
        }

        add_action( 'admin_menu', array( $this, 'addAdminMenu' ) );
        add_filter( 'plugin_action_links', array( $this, 'addPluginActionLink' ), 10, 2 );
        add_filter( 'bp_activity_time_since', array( $this, 'replaceActivityTimeSince' ), 10, 2 );
        add_filter( 'bp_activity_get_comments', array( $this, 'getComments' ) );
        add_filter( 'bp_activity_allowed_tags', array( $this, 'updateAllowedTags' ) );
    } // function init()

    /**
     * Updating allowed tags
     *
     * @param unknown $allowedTags
     * @return array
     */
    public function updateAllowedTags( $allowedTags )
    {
        // Adding the title attribute to <span>, so we can add the timestamp
        $allowedTags['span']['title'] = array();
        return $allowedTags;
    }

    /**
     * getOptions()
     * Load and update options
     *
     * @access protected
     * @return unknown
     */
    protected function getOptions()
    {
        // set default options
        $this->options = array(
            'refreshRate' => $this->defaultRefreshRate,
            'timeFormat' => $this->defaultTimeFormat,
        );

        // load options
        $savedOptions = get_option( $this->adminOptionsName );

        if ( !empty( $savedOptions ) ) {
            foreach ( $savedOptions as $key => $option ) {
                $this->options[$key] = $option;
            }
        }

        // save options
        update_option( $this->adminOptionsName, $this->options );

        return $this->options;
    } // getOptions()

    /**
     * validate and update options ()
     * Load and update options
     *
     * @var $options array
     * @access protected
     * @param array   $options
     */
    protected function saveOptions( array $options )
    {

        if ( !empty( $options ) ) {
            foreach ( $options as $key => $option ) {
                switch ( $key ) {
                case 'refreshRate':
                    if ( is_numeric( $option ) ) {
                        $this->options[$key] = (int)$option;
                    }
                    break;

                default:
                    $this->options[$key] = $option;
                    break;
                }
            }
        }

        // save options
        update_option( $this->adminOptionsName, $this->options );
    } // saveOptions()

    /**
     * add JavaScript variable refreshRate
     * action hook: wp_head
     *
     * @access public
     */
    public function addJavaScriptRefreshRate()
    {
        echo '<script type="text/javascript">' . "\n";
        echo 'var myFOSSILBpActivityRefreshRate = ' . $this->options['refreshRate'] . ';' . "\n";
        if ( $this->options['timeFormat'] == 'since' ) {
            echo 'var myFOSSILBpActivityRefreshTimeago = true;' . "\n";
            echo 'jQuery.timeago.settings.refreshMillis = 0;' . "\n";
        }
        else {
            echo 'var myFOSSILBpActivityRefreshTimeago = false;' . "\n";
        }
        echo '</script>' . "\n";
    }

    /**
     * method to answer the ajax request
     * action hook: wp_ajax_myfossil_bp_activity_refresh
     *
     * @access public
     */
    public function ajaxRefresh()
    {
        global $bp, $activities_template;

        $inGroup = ! empty( $bp->groups->current_group );

        // get last id
        if ( isset( $_POST['last_id'] ) && is_numeric( $_POST['last_id'] ) ) {
            $last_id = (int) $_POST['last_id'];

            // start the Loop
            // show new comments in stream format
            if ( bp_has_activities( bp_ajax_querystring( 'activity' ) ) ) {
                $activities = $activities_template->activities;

                foreach ( $activities as $activity ) {
                    $activities_template->in_the_loop = true;
                    $activities_template->current_activity++;
                    $activities_template->activity = $activity;

                    if ( $inGroup ) {
                        if ( bp_get_activity_id() > $last_id ) {
                            // print the entry
                            bp_get_template_part('activity/entry');
                        }
                        else if ( !empty( $activities_template->activity->children ) ) {
                                $activities_template->activity_parents[$activity->id] = $activity;
                                $this->recursiveLoopForGroups( $activity->children );
                            }
                    }
                    else {
                        // if the current id is less than last_id, break the while
                        if ( bp_get_activity_id() > $last_id ) {
                            // print the entry
                            bp_get_template_part('activity/entry');
                        }
                    }
                }
            }
        }
    } // function ajaxRefresh()

    /**
     *
     *
     * @param unknown $comments
     */
    protected function recursiveLoopForGroups( $comments )
    {
        global $activities_template;
        $last_id = (int) $_POST['last_id'];
        foreach ( $comments as $comment ) {
            $activities_template->activity = $comment;
            if ( bp_get_activity_id() > $last_id ) {
                // print the entry
                include locate_template( array( 'activity/entry.php' ), false );
            }
            if ( !empty( $comment->children ) ) {
                $activities_template->activity_parents[$comment->id] = $comment;
                $this->recursiveLoopForGroups( $comment->children );
            }
        }
    }

    /**
     * method to add the admin menu
     * action hook: admin_menu
     *
     * @access public
     */
    public function addAdminMenu()
    {
        add_options_page(
            'BuddyPress Activity Refresh',
            'myFOSSIL BuddyPress Activity Refresh',
            'manage_options',
            basename( __FILE__ ),
            array( $this, 'printAdminPage' )
        );
    } // end function addAdminMenu()

    /**
     * prints out the admin page
     *
     * @access public
     */
    public function printAdminPage()
    {
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }

        if ( isset( $_POST['update_myfossilbpactivityrefresh'] ) ) {
            if ( isset( $_POST['myfossilbpactivityrefresh_refreshrate'] ) ) {
                $options['refreshRate'] = $_POST['myfossilbpactivityrefresh_refreshrate'];
            }
            if ( isset( $_POST['myfossilbpactivityrefresh_timeformat'] ) && in_array( $_POST['myfossilbpactivityrefresh_timeformat'], $this->allowedValuesTimeFormat ) ) {
                $options['timeFormat'] = $_POST['myfossilbpactivityrefresh_timeformat'];
            }
            $this->saveOptions( $options );
?>
				<div class="updated"><p><strong><?php _e( 'Settings Updated.', 'myfossil-buddypress-activity-refresh' );?></strong></p></div>
			<?php
        }

?>
			<div class="wrap">
				<form method="post" action="">
					<h2>myFOSSIL Buddypress Activity Refresh</h2>

					<h3><?php _e( 'Refresh Rate', 'myfossil-buddypress-activity-refresh' ); ?></h3>
					<p><?php printf( __( 'This value is the refresh rate in seconds. Default value is %d. If you set this value to 0, the refresh is disabled.', 'myfossil-buddypress-activity-refresh' ), $this->defaultRefreshRate ); ?>
					<p><label for="myfossilbpactivityrefresh_refreshrate"><input type="text" id="myfossilbpactivityrefresh_refreshrate" name="myfossilbpactivityrefresh_refreshrate" value="<?php echo $this->options['refreshRate']; ?>" /> <?php _e( 'seconds', 'myfossil-buddypress-activity-refresh' ); ?></label></p>

					<h3><?php _e( 'Time Format', 'myfossil-buddypress-activity-refresh' ); ?></h3>
					<p><?php printf( __( 'Choose between the the <em>%s</em>-format: <strong>5 minutes ago</strong> and the <em>%s</em>-format: <strong>%s</strong>', 'myfossil-buddypress-activity-refresh' ), __( 'Since', 'myfossil-buddypress-activity-refresh' ), __( 'DateTime', 'myfossil-buddypress-activity-refresh' ), $this->getFormattedDateTime( date( 'd-m-y H:i:s', time() - 5 * 60 ) ) ); ?>
					<p><label for="myfossilbpactivityrefresh_timeformat"><select id="myfossilbpactivityrefresh_timeformat" name="myfossilbpactivityrefresh_timeformat">
						<option value="since"<?php if ( $this->options['timeFormat'] == 'since' ) echo ' selected="selected"'; ?>><?php _e( 'Since', 'myfossil-buddypress-activity-refresh' ); ?></option>
						<option value="datetime"<?php if ( $this->options['timeFormat'] == 'datetime' ) echo ' selected="selected"'; ?>><?php _e( 'DateTime', 'myfossil-buddypress-activity-refresh' ); ?></option>
					</select></p>

					<div class="submit">
						<button class="button" type="submit" name="update_myfossilbpactivityrefresh"><?php _e( 'Update Settings', 'myfossil-buddypress-activity-refresh' ); ?></button>
					</div>
				</form>
 			</div>
		<?php
    }//End function printAdminPage()

    /**
     * addPluginActionLink
     * Stolen from Welcome Pack - thanks, Paul!
     * filter hook: plugin_action_links
     *
     * @access public
     * @param array   $links
     * @param string  $file
     * @return unknown
     */
    public function addPluginActionLink( $links, $file )
    {
        if ( $this->plugin_file != $file ) {
            return $links;
        }
        $settings_link = '<a href="' . admin_url( 'admin.php?page=' . basename( __FILE__ ) ) . '">' . __( 'Settings', 'myfossil-buddypress-activity-refresh' ) . '</a>';

        array_unshift( $links, $settings_link );
        return $links;
    } // addPluginActionLink()

    /**
     * getFormattedDateTime
     *
     * @param unknown $datetime
     * @return unknown
     */
    protected function getFormattedDateTime( $datetime )
    {
        $return = '<span class="timeago" title="' . date( 'c', strtotime( $datetime ) ) . '">' . date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $datetime ) + get_option( 'gmt_offset' ) * 60 * 60 ) . '</span>';
        return $return;
    }

    /**
     * replaceActivityTimeSince
     *
     * @param unknown $content
     * @param unknown $activity (optional)
     * @return unknown
     */
    public function replaceActivityTimeSince( $content, $activity = false )
    {
        if ( $activity !== false ) {
            $content = '<span class="time-since">';
            $content .=  $this->getFormattedDateTime( $activity->date_recorded );
            $content .= '</span>';
        }
        return $content;
    }

    /**
     * getComments
     * Copy fo the /buddypress/bp-activity/bp-activity-templatetags.php/bp_activity_get_comments() function
     * Modification to use my own recursiveComments-function
     *
     * @access public
     * @param unknown s array $args
     * @return unknown
     */
    public function getComments( $args = '' )
    {
        global $activities_template, $bp;

        if ( !$activities_template->activity->children )
            return false;

        $comments_html = $this->recursiveComments( $activities_template->activity );

        return $comments_html;
    }

    /**
     * recursiveComments
     * Copy of the /buddypress/bp-activity/bp-activity-templatetags.php/bp_activity_recurse_comments() function
     * Modification to change the "time since"
     *
     * @access public
     * @param object  $comment
     * @return unknown
     */
    protected function recursiveComments( $comment )
    {
        global $activities_template, $bp;

        if ( !$comment->children )
            return false;

        $content .= '<ul>';
        foreach ( (array)$comment->children as $comment ) {
            if ( !$comment->user_fullname ) {
                $comment->user_fullname = $comment->display_name;
            }

            $content .= '<li id="acomment-' . $comment->id . '">';
            $content .= '<div class="acomment-avatar"><a href="' . bp_core_get_user_domain( $comment->user_id, $comment->user_nicename, $comment->user_login ) . '">' . bp_core_fetch_avatar( array( 'item_id' => $comment->user_id, 'width' => 20, 'height' => 20, 'email' => $comment->user_email ) ) . '</a></div>';
            $content .= '<div class="acomment-meta"><a href="' . bp_core_get_user_domain( $comment->user_id, $comment->user_nicename, $comment->user_login ) . '">' . apply_filters( 'bp_acomment_name', $comment->user_fullname, $comment ) . '</a> &middot; ' . $this->getFormattedDateTime( $comment->date_recorded );

            /* Reply link - the span is so that threaded reply links can be hidden when JS is off. */
            if ( is_user_logged_in() ) {
                $content .= '<span class="acomment-replylink"> &middot; <a href="#acomment-' . $comment->id . '" class="acomment-reply" id="acomment-reply-' . $activities_template->activity->id . '">' . __( 'Reply', 'buddypress' ) . '</a></span>';
            }

            /* Delete link */
            if ( $bp->loggedin_user->is_super_admin || $bp->loggedin_user->id == $comment->user_id ) {
                $content .= ' &middot; <a href="' . wp_nonce_url( $bp->root_domain . '/' . $bp->activity->slug . '/delete/?cid=' . $comment->id, 'bp_activity_delete_link' ) . '" class="delete acomment-delete">' . __( 'Delete', 'buddypress' ) . '</a>';
            }

            $content .= '</div>';
            $content .= '<div class="acomment-content">' . apply_filters( 'bp_get_activity_content', $comment->content ) . '</div>';

            $content .= $this->recursiveComments( $comment );
            $content .= '</li>';
        }
        $content .= '</ul>';

        return $content;
    } // recursiveComments()

} // class myFOSSILBuddypressActivityRefresh


/**
 * myFOSSILBuddypressActivityRefreshInit()
 *
 * Only load the plugin code if BuddyPress is activated.
 */
function myFOSSILBuddypressActivityRefreshInit()
{
    $myFOSSILBuddypressActivityRefresh = new myFOSSILBuddypressActivityRefresh();
    $myFOSSILBuddypressActivityRefresh->init();
}
add_action( 'init', 'myFOSSILBuddypressActivityRefreshInit' );

?>
