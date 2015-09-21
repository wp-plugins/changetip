<?php

/*
 * Plugin Name: ChangeTip
 * Plugin URI: http://wordpress.org/plugins/changetip/
 * Description: <a href="https://www.changetip.com/">ChangeTip</a> is a way to send and receive tips online with Bitcoin. We call ourselves a Love Button for the Internet. We’ve been told we’re revolutionizing appreciation and giving. Anytime you want to reward someone, all you have to do is mention @changetip and an amount and we’ll take care of the transaction. It’s that simple.
 * Author: ChangeTip
 * Version: 0.0.8
 * Author URI: https://www.changetip.com/
 * Text Domain: changetip
 * Contributors: Evan Nagle and Jim Lyndon
 */

include_once dirname( __FILE__ ) . '/pezplug.php';
include_once dirname( __FILE__ ) . '/inc/autoreply.php';
include_once dirname( __FILE__ ) . '/inc/comments-screen.php';
include_once dirname( __FILE__ ) . '/inc/helpers.php';
include_once dirname( __FILE__ ) . '/inc/updates.php';
include_once dirname( __FILE__ ) . '/inc/uuid.php';

global $changetip;

if ( !$changetip ) :
class changetip extends pezplug {
    public function __construct() {
        parent::__construct( 'changetip' );

        $this->add_action( 'admin_init' );
        $this->add_action( 'admin_menu' );
        $this->add_action( 'admin_notices' );
        $this->add_style( 'css/changetip.css' );
        $this->add_script( 'js/pez.js', array( 'jquery' ) );
        $this->add_script( 'js/changetip.js', array( 'js/pez.js' ) );
        $this->add_script( 'js/changetip-uuid.js' );
        $this->add_script( 'js/changetip-client.js', array( 'js/changetip.js' ) );
        $this->add_admin_style( 'css/changetip.css' );
        $this->add_admin_script( 'js/pez.js', array( 'jquery' ) );
        $this->add_admin_script( 'js/changetip.js', array( 'js/pez.js' ) );
        $this->add_admin_script( 'js/changetip-admin.js', array( 'js/changetip.js' ) );
        $this->add_filter( 'the_content' );
        $this->add_filter( 'comment_post_redirect', NULL, 99, 2 );
        $this->add_action( 'show_user_profile' );
        $this->add_action( 'edit_user_profile', 'show_user_profile' );
        $this->add_action( 'personal_options_update' );
        $this->add_action( 'edit_user_profile_update', 'personal_options_update' );
        $this->add_action( 'wp_head' );
    }

    public function deactivate() {
        delete_option( 'changetip' );
    }

    public function get_changetip_users() {
        $usernames = array();
        $usernames_json = $this->get_option( 'changetip_username' );

        if( !$usernames_json && isset( $_GET['changetip_register'] ) ) {
            $usernames = array( $_GET['changetip_register'] );
        } else {
            $usernames = json_decode( $usernames_json );
            if( !is_array( $usernames ) && is_string( $usernames_json ) ) {
                //gracefully handle old value, not stored as json
                $usernames = array( $usernames_json );
            }
        }

        if( empty( $usernames ) ) {
            $usernames = array();
        } else {
            foreach( $usernames as &$username ) {
                if( $username ) {
                    if( !isset( $username->name ) ) $username = NULL;
                    if( !isset( $username->uuid ) ) $username = NULL;   
                }
            }
            $usernames = array_filter( $usernames );
        }
        
        return array_values( $usernames );
    }

    public function get_changetip_users_encoded() {
        $users = $this->get_changetip_users();    
        foreach( $users as &$user ) {
            if( $user && isset ( $user->name) && isset( $user->uuid ) ) {
                $user->name = htmlspecialchars( $user->name, ENT_QUOTES );
                $user->uuid = htmlspecialchars( $user->uuid, ENT_QUOTES );
            } else {
                $user = NULL;
            }
        }

        $users = array_filter( $users );
        return array_values( $users );
    }

    public function is_registered() {
        return count( $this->get_changetip_users() ) > 0;
    }

    public function register_new_user( $username, $uuid ) {
        $users = $this->get_changetip_users();
        foreach( $users as $user ) {
            if( $user->name == $username || $user->uuid == $uuid ) {
                return; //already registered
            }
        }
        $users[] = array(
            'name' => $username,
            'uuid' => $uuid
        );
        $this->update_option( 'changetip_username', json_encode( $users ) );
    }

    public function get_mapped_changetip_user() {
        global $post;
        $user_map = NULL;
        $users = $this->get_changetip_users();

        if( is_array( $users ) && $post && $post->post_author ) {
            $author_id = $post->post_author;
            $user_name_mapped = get_user_meta( $author_id, 'changetip_user_map', TRUE );
            foreach( $users as $user ) {
                if ( $user->name == $user_name_mapped ) {
                    $user_map = $user;
                }
            }
        }
        if( !$user_map && count( $users ) > 0 ) {
            $user_map = $users[0];
        }
        return $user_map;
    }

    public function get_version() {
        $version = $this->get_option( 'changetip_version' );
        return is_numeric( $version ) ? $version : -1;
    }

    public function update_version( $version ) {
        if( is_numeric( $version ) ) {
            $this->update_option( 'changetip_version', $version );
        }
    }

    public function autoreply_is_on() {
        return $this->get_option( 'changetip_autoreply' );
    }

    public function admin_init() {
	    if ( !current_user_can( 'manage_options' ) ) {
		    wp_die( 'You are not allowed.' );
	    }

        if( function_exists( 'changetip_check_for_updates' ) ) {
            changetip_check_for_updates( $this ); //updates.php
        }

        if( isset( $_GET['changetip_register'] ) ) {
            $new_username = $_GET['changetip_register'];
            $new_uuid = $_GET['changetip_uuid'];
            $new_currency = isset( $_GET['changetip_currency'] ) ? $_GET['changetip_currency'] : 'USD';
            $default_tips = isset( $_GET['changetip_tip_suggestions'] ) ? $_GET['changetip_tip_suggestions'] : '.25 USD';
            $default_tips = explode( '||', $default_tips );
            $connect_user = isset( $_GET['changetip_connect'] );

            if( !count( $default_tips ) || !$default_tips[0] ) {
                $default_tips = array( '.25 USD' );
            }

            $this->register_new_user( $new_username, $new_uuid );
            $this->update_some_options( array(
                'changetip_autoreply' => TRUE,
                'changetip_version' => 1.1
            ));

            if($connect_user) {
                update_user_meta( get_current_user_id(), 'changetip_user_map', $new_username );
            }

            wp_redirect( admin_url( 'admin.php?page=changetip&changetip_register_result=1') );
            die();
        }

        if( isset( $_GET['changetip_register_result'] ) && $_GET['changetip_register_result'] == 1 ) { ?>
            <div class='updated'>
                <p>You've successfully tied your ChangeTip account to Wordpress!</p>
            </div>
        <?php }

        add_settings_section(
            'changetip_api', // ID
            'ChangeTip API', // Title
            NULL, // Callback
            $this->slug // Page
        );

        add_settings_field(
            'changetip_username', // ID
            'ChangeTip User(s)', // Title
            $this->ref( '_field_changetip_username' ), // Callback
            $this->slug, // Page
            'changetip_api' // Section
        );

        add_settings_field(
            'changetip_autoreply',
            'Enable Autoreply',
            $this->ref( '_field_changetip_autoreply' ),
            $this->slug,
            'changetip_api'
        );

        add_settings_field(
            'changetip_button_position',
            'Button Position',
            $this->ref( '_field_changetip_button_position' ),
            $this->slug,
            'changetip_api'
        );
    }

    public function admin_menu() {
        add_menu_page(
            'Changetip',
            'Changetip',
            'edit_posts',
            $this->slug,
            NULL,
            'dashicons-megaphone',
            100
        );

	    //add_menu_page( 'custom menu title', 'custom menu', 'manage_options', 'custompage',  $this->ref( '_admin_topmenu_render' ), plugins_url( 'myplugin/images/icon.png' ), 6 ); 

        add_submenu_page(
            'changetip', //parent slug
            'Connect', //page title
            'Connect', //menu title
            'edit_posts', //cap,
            $this->slug, //slug
            $this->ref( '_connect_menu_render' )
        );

        add_submenu_page(
            'changetip', //parent slug
            'Settings', //page title
            'Settings', //menu title
            'manage_options', //cap,
            $this->slug . '-admin', //slug
            $this->ref( '_admin_menu_render' )
        );
    }

    public function admin_notices() {
        if( !$this->is_registered() ) { ?>
            <div class='update-nag'>
                <p>The ChangeTip Plugin is activated! <strong>Please <a id="changetip-register" href='#changetip-register'>connect your ChangeTip account</a>.</strong>
            </div>
        <?php }
    }

    function _admin_menu_render() {
        $this->render_options_page( 'ChangeTip' );
    }

    function _connect_menu_render() {
        echo '<div class="wrap">';
        echo "<h2>Connect to Changetip</h2>";

        $user_map = get_user_meta( get_current_user_id(), 'changetip_user_map', TRUE );
        $user_map = htmlspecialchars( $user_map, ENT_QUOTES );

        if( $user_map ) {
            echo "<p>You've successfully connected to your changetip account, <strong><a href='https://www.changetip.com/tipme/$user_map'>$user_map</a></strong>.</p>";
            echo "<p><a id='changetip-register' href='#changetip-connect' class='button'>Connect to a different ChangeTip Account</a></p>";    
        } else {
            echo "<p><a id='changetip-register' href='#changetip-connect' class='button'>Connect ChangeTip Account</a></p>";
        }
        echo '</div>';
    }

    function _field_changetip_username() {
        $users = $this->get_changetip_users_encoded();
        if( count( $users ) > 0 ) {
            foreach( $users as $user ) { ?>
                <div class='changetip-username-field'>
                    <input type='text' readonly value='<?php echo $user->name; ?>' data-uuid='<?php echo $user->uuid; ?>' />
                    <a href="#" class="changetip-delete-account button-secondary">Delete</a>
                    <hr/>
                </div>
            <?php }
        }
        echo "<p><a id='changetip-register' href='#changetip-register' class='button'>Connect ChangeTip Account</a></p>";
        $this->render_hidden_textbox( 'changetip_username' );
    }

    function _field_changetip_autoreply() {
        $this->render_field_checkbox( 'changetip_autoreply', "<small>ChangeTip will autoreply to all tips (though you'll have to approve the autoreply). This encourages others to tip as well.</small>" );
    }

    function _field_changetip_button_position() {
        $this->render_field_select( 'changetip_button_position', array(
            'top-and-bottom' => 'Top and Bottom',
            'top' => 'Top',
            'bottom' => 'Bottom'
        ) );
    }

    public function the_content( $content ) {
        global $post;

        if( is_single() && $post->post_type == 'post' ) {
            $username = $this->get_mapped_changetip_user();
            if( $username && $username->uuid ) {
                $uuid = $username->uuid;
                $uuid_esc = htmlspecialchars( $uuid, ENT_QUOTES );
                $uid_factory = new CTUUID();
                $bid = $uid_factory->v5( $uuid, $post->ID );

                $position = $this->get_option( 'changetip_button_position' );
                if( !$position || $position == 'top' || $position == 'top-and-bottom' ) {
                    $button = "<div class='changetip_tipme_button pos-top' data-uid='$uuid_esc' data-bid='$bid'></div>";
                    $content = $button . $content;
                }
                if( !$position || $position == 'bottom' || $position == 'top-and-bottom' ) {
                    $button = "<div class='changetip_tipme_button pos-bottom' data-uid='$uuid_esc' data-bid='$bid'></div>";
                    $content .= $button;
                }
                $content = apply_filters( 'changetip_the_content', $content );
            }
        }
        return $content;
    }

    public function wp_head() {
        $username = $this->get_mapped_changetip_user();

        if( $username ) {
            $username_json = htmlspecialchars( json_encode( $username->name ), ENT_QUOTES );
            $uuid_json = htmlspecialchars( json_encode( $username->uuid ), ENT_QUOTES );
            echo "<meta property='changetip:username' content=\"$username_json\" />\n";
            echo "<meta property='changetip:uuid' content=\"$uuid_json\" />\n";
        }
        if( isset( $_GET['changetip_approve'] ) && $_GET['changetip_approve'] && $this->is_registered() ) {
            $comment = get_comment( $_GET['changetip_approve'] );
            $comment->changetip_key = get_comment_meta( $comment->comment_ID, 'changetip_comment_id', TRUE );
            $comment->context_url = get_comment_link( $comment->comment_ID );

            if( $comment ) {
                $comment_json = htmlspecialchars( json_encode( $comment ), ENT_QUOTES );
                echo "<meta property='changetip:approve' content=\"$comment_json\" />\n";
            }
        }
        if( is_single() ) {
            $post_id = get_the_ID();
            echo "<meta property='changetip:postId' content=\"$post_id\" />\n";
        }
        $app_id = 'q5TL7owpVHBSHFXAoX8hPm';
        $app_id_json = htmlspecialchars( json_encode( $app_id ), ENT_QUOTES );
        echo "<meta property='changetip:appid' content=\"$app_id_json\" />\n";

        $ajaxurl = admin_url('admin-ajax.php');
        echo "<script>var ajaxurl = '$ajaxurl';</script>";
    }

    public function comment_post_redirect( $location, $comment_object ) {
        if( !$this->is_registered() ) {
            return $location;
        }
        if( changetip_contains_callout( $comment_object->comment_content ) ) {
            changetip_mark_comment_as_tip( $comment_object );
            $location = add_query_arg( 'changetip_approve', $comment_object->comment_ID, $location );
        }
        return $location;
    }

    public function show_user_profile( $user ) {
        if( !user_can( $user, 'edit_posts' ) || !$this->is_registered() ) {
            return;
        } ?>
            <h3>ChangeTip Account</h3>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th>
                            <label for="changetip_user_map">ChangeTip Account</label>
                        </th>
                        <td>
                            <select id="changetip_user_map" name="changetip_user_map">
                                <?php
                                    $selected_username = get_user_meta( $user->ID, 'changetip_user_map', TRUE );
                                    $usernames =  $this->get_changetip_users();
                                    foreach( $usernames as $username ) {
                                        $name = $username->name;
                                        $checked = $name == $selected_username ? ' selected' : '';
                                        echo "<option value='$name'$checked>$name</option>";
                                    }
                                ?>
                            </select><br/>
                            <span class="description">
                                Select the ChangeTip account that you want associated with this Wordpress user.
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php
    }

    public function personal_options_update( $user_id ) {
        if ( !current_user_can( 'edit_user', $user_id ) ) {
            return FALSE;
        }
        if( !isset( $_POST['changetip_user_map'] ) ) {
            return TRUE;
        }
        update_user_meta( $user_id, 'changetip_user_map', $_POST['changetip_user_map'] );
    }
}

$changetip = new changetip();
register_deactivation_hook( __FILE__, array( $changetip, 'deactivate' ) );

endif;