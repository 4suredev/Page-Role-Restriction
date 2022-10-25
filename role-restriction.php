<?php
/**
 * Plugin Name: Access Manager - Restrict Pages/Posts by User Role
 * Plugin URI: https://4sure.com.au
 * Description: Enable user role restriction per page or post. Requires ACF Pro
 * Version: 3.0.2
 * Author: 4sure
 * Author URI: https://4sure.com.au
 */
// Constants
define('ACC_PLUGIN_PATH', plugin_dir_url( __FILE__ ));
define('ACC_PLUGIN_FILE', ACC_PLUGIN_PATH.'role-restriction.php');
include_once( plugin_dir_path( __FILE__ ) . 'updater.php');
$updater = new Page_role_restriction_updater( __FILE__ ); 
$updater->set_username( '4suredev' ); 
$updater->set_repository( 'Page-Role-Restriction' ); 
$updater->initialize(); 
if( ! class_exists( 'Page_role_restriction_updater' ) ){
	include_once( plugin_dir_path( __FILE__ ) . 'updater.php' );
}
add_action( 'admin_enqueue_scripts', 'acc_admin_enqueue_scripts');
function acc_admin_enqueue_scripts($hook) {
    wp_enqueue_media();
    wp_enqueue_style( 'wp-color-picker');
    wp_enqueue_script( 'wp-color-picker');
    wp_enqueue_script('acc-custom-scripts', ACC_PLUGIN_PATH.'js/admin-scripts.js', array('jquery'));
    wp_enqueue_style('acc-custom-styles', ACC_PLUGIN_PATH.'css/admin-styles.css');
}
/* Edit page/post hooks */
add_action( 'load-post.php', 'acc_meta_init' );
add_action( 'load-post-new.php', 'acc_meta_init' );
add_action( 'load-page.php', 'acc_meta_init' );
add_action( 'load-page-new.php', 'acc_meta_init' );
function acc_meta_init(){
    add_action( 'add_meta_boxes', 'acc_add_post_meta_boxes' );
    add_action( 'save_post', 'acc_save_post_meta', 10, 2 );
}
/* Create one or more meta boxes to be displayed on the post editor screen. */
function acc_add_post_meta_boxes() {
    add_meta_box(
        'acc-page-options',
        esc_html__( 'Page Access', 'acc' ), 
        'acc_page_options_meta_box',
        NULL,     
        'advanced', 
        'default' 
    );
}
/* Display the post meta box. */
function acc_page_options_meta_box( $post ) { 
   wp_nonce_field( basename( __FILE__ ), 'acc_page_options_nonce' );
   $postmeta = maybe_unserialize( get_post_meta( $post->ID, 'acc_page_options', true ) );
   ?>   <p><b>Users that can access this page</b></p>
        <p style="font-size: 0.8em; color: ccc;">leave blank to allow all</p>
        <ul class="user-roles-list">
            <?php
                global $wp_roles;
                $roles = $wp_roles->roles;
                if($roles){        
                    foreach( $roles as $key=>$role ) {   
                        if ( is_array( $postmeta ) && in_array( $key, $postmeta ) ) { $checked = 'checked="checked"'; } 
                        else { $checked = null; }   
                        ?><li>
                            <label for="<?php echo $key; ?>"><input type="checkbox" name="allowedroles[]" id="<?php echo $key; ?>" value="<?php echo $key; ?>" <?php echo $checked; ?>/> <?php echo $role['name']; ?></label>
                        </li><?php
                    }
                }
            ?>
        </ul>
    <?php 
}
function acc_save_post_meta( $post_id, $post ) {
    /* Verify the nonce before proceeding. */
    if ( !isset( $_POST['acc_page_options_nonce'] ) || !wp_verify_nonce( $_POST['acc_page_options_nonce'], basename( __FILE__ ) ) ){
        return $post_id;
    }
    $post_type = get_post_type_object( $post->post_type );
     /* Verify user capabilities. */
    if ( !current_user_can( $post_type->cap->edit_post, $post_id ) ){
        return $post_id;
    }
    $new_meta_value = $_POST['allowedroles'];
    $meta_key = 'acc_page_options';
    if ( !empty($_POST['allowedroles']) ){
        update_post_meta( $post_id, $meta_key, $new_meta_value );
    }else{
        delete_post_meta( $post_id, $meta_key );
    }
  
}
add_action('admin_menu', 'acc_default_settings_menu');
function acc_default_settings_menu() {
    global $submenu;
    $menu_slug = "acc-default-settings"; // used as "key" in menus
    $menu_pos = 20; // whatever position you want your menu to appear
	$menu_icon = 'dashicons-admin-generic';
    add_menu_page( 'Access Manager Page', 'Access Manager Default Settings', 'manage_options', $menu_slug, 'acc_default_settings_options', $menu_icon, $menu_pos);
    add_action( 'admin_init', 'acc_default_options_init' );
}
function acc_default_options_init(){
    register_setting( 'acc-default-settings', 'restriction_method' );
    register_setting( 'acc-default-settings', 'redirect_slug' );
    register_setting( 'acc-default-settings', 'show_error_message' );
    register_setting( 'acc-default-settings', 'error_message_background_color' );
    register_setting( 'acc-default-settings', 'error_message_text_color' );
    register_setting( 'acc-default-settings', 'pagepost_access_denied' );
    register_setting( 'acc-default-settings', 'additional_content' );
    register_setting( 'acc-default-settings', 'custom_css' );
}
function acc_default_settings_options(){
    if(get_current_screen()->base == 'toplevel_page_acc-default-settings'){ ?>
    <h1>Access Manager Default Settings</h1>
    <form method="post" action="options.php"> 
        <?php settings_fields( 'acc-default-settings' ); ?>
        <?php do_settings_sections( 'acc-default-settings' ); ?>
        <?php 
            $restriction_method = get_option('restriction_method');
            $show_error_message = get_option('show_error_message');
            $redirect_slug = get_option('redirect_slug');
            $error_message_background_color = get_option('error_message_background_color');
            $error_message_text_color = get_option('error_message_text_color');
            $pagepost_access_denied = get_option('pagepost_access_denied');
            $additional_content = get_option('additional_content');
            $custom_css = get_option('custom_css');
        ?>
        <table class="form-table">
            <tr><th colspan=3>Access Manager</th></tr>
            <tr>
                <td>
                    <label for="restriction_method">Restriction Method</label>
                    <select id="restriction_method" name="restriction_method" conditional-formatting="true" data-condition="restriction_method" class="field">
                        <option value="redirect" <?php if($restriction_method == 'redirect') echo 'selected'; ?>>Redirect</option>
                        <option value="stay" <?php if($restriction_method == 'stay') echo 'selected'; ?>>Stay on current page</option>
                    </select>
                </td>
                <td condition="restriction_method" condition-value="redirect" show="<?php if($restriction_method == 'redirect') echo 'true'; else echo 'false'; ?>">
                    <label for="redirect_slug">Redirect Slug</label>
                    <select id="redirect_slug" name="redirect_slug">
                        <?php echo acc_get_page_list(); ?>
                    </select>
                    <p style="font-size: 0.8em; color: ccc;">Select the page redirect destination.</p>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="show_error_message" class="inline-check"><input type="checkbox" name="show_error_message" <?php if($show_error_message) echo 'checked'; ?> conditional-formatting="true" data-condition="show_error_message" id="show_error_message"  class="field"/> Show Error Message</label>
                </td>
                <td condition="show_error_message" <?php if($show_error_message) echo 'show="true"'; else echo 'show="false"'; ?>>
                    <label for="error_message_background_color">Error message background color</label>
                    <input type="text" class="color-picker" name="error_message_background_color" id="error_message_background_color" value="<?php echo $error_message_background_color; ?>"/>
                </td>
                <td condition="show_error_message" <?php if($show_error_message) echo 'show="true"'; else echo 'show="false"'; ?>>
                    <label for="error_message_text_color">Error message text color</label>
                    <input type="text" class="color-picker" name="error_message_text_color" id="error_message_text_color" value="<?php echo $error_message_text_color; ?>"/>
                </td>
            </tr>
            <tr>
                <td condition="show_error_message" <?php if($show_error_message) echo 'show="true"'; else echo 'show="false"'; ?> colspan=3>
                    <label for="pagepost_access_denied">Error message</label>
                    <textarea name="pagepost_access_denied" id="pagepost_access_denied"><?php echo $pagepost_access_denied; ?></textarea>
                </td>
            </tr>
            <tr>
                <td colspan=3>
                    <label for="additional_content">Additional content below error message</label>
                    <?php 
                    wp_editor( $additional_content, 'additional_content', array() );
                     ?>
                </td>
            </tr>
            <tr>
                <td colspan=3>
                    <label for="custom_css">Custom CSS</label>
                    <textarea name="custom_css" id="custom_css"><?php echo $custom_css; ?></textarea>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
    <?php
    }
} 
// Populate page list field with all page slugs
function acc_get_page_list() {
    $args = array(
        'post_status' => 'publish',
        'posts_per_page' => -1
    );
    $pages = get_pages($args);
    $field_options = '';
    if(get_option('restriction_method') == 'redirect')
        $selected_val = get_option('redirect_slug');
    
    if($pages){        
        foreach( $pages as $key=>$page ) {      
            $title = $page->post_title;
            $slug = $page->post_name;
            if($selected_val == $slug){
                $selected = 'selected';
            }else{
                $selected = '';
            }
            $field_options .= '<option value="'.$slug.'" '.$selected.'>'.$title.'</option>';
        }
    }
    return $field_options;
}
// Redirection content filter 
add_filter('the_content', 'acc_role_restriction_filter_content');
function acc_role_restriction_filter_content($content){
    if (in_the_loop()){ //only affeect the body content
        $post_id = get_the_id();

        $restriction_method = get_option('restriction_method');
        $show_error_message = get_option('show_error_message');
        $redirect_slug = get_option('redirect_slug');
        $error_message_background_color = get_option('error_message_background_color');
        $error_message_text_color = get_option('error_message_text_color');
        $error_message = get_option('pagepost_access_denied');
        $additional_content = get_option('additional_content');
        $custom_css = get_option('custom_css');

        $role_restrictions = (array) get_post_meta( $post_id, 'acc_page_options', true ); 
        $user = wp_get_current_user();
        $user_roles = (array) $user->roles;
        if($additional_content != ''){$margin = '100px';}
        else{$margin = '0';}
        echo  '<style>
                .access-error-message{
                    color: '.$error_message_text_color.';
                    background:  '.$error_message_background_color.';
                    padding: 30px;
                    text-align: center;
                    margin-bottom: '.$margin.';
                }                   
            </style>';
        if($custom_css != ""){echo "<style>".$custom_css."</style>";}   
        if (count($role_restrictions) >= 1){ // check if current page has restrictions set
            $matched_roles = array_intersect($role_restrictions, $user_roles); //compare page restrictions with user role
            if (count($matched_roles) == 0){ //if user role is not within allowed roles ($role_restrictions), execute restriction
                if ($restriction_method == 'redirect'){
                    if (!is_page($redirect_slug)){
                        if($redirect_slug == 'home') {$redirect_slug = '';}
                        wp_safe_redirect(home_url().'/'.$redirect_slug.'?redirected=true'); //set the redirect path, add redirected variable to make error message appear on the page
                    }
                }else if ($restriction_method == 'stay'){
                    if ($show_error_message){
                        $content = '<div class="access-error-message">'.$error_message.'</div>';
                        if ($additional_content != ""){
                            $content .= '<div class="additional-content">'.$additional_content.'</div>';
                        }
                    }else{
                        $content = ''; //if no error message, just hide content
                    }
                }  
            }else{ //if user role is in the allowed array
                foreach($matched_roles as $role){
                    //per role validation here
                    // if ($role == "role1"){}
                    // else if ($role == "role2"){}
                    // else{}

                    // Potential feature that could be added here: maybe have the option to let the user select custom redirects per 
                    // user role. This option will be available per page. You can work on this on a separate "development" branch
                }
            }
        }
        //Executes if the page had just redirected by checking the redirect_slug and checking for the ?redirected=true variable
        if ($restriction_method == 'redirect' && is_page($redirect_slug) && $_GET['redirected'] && !wp_get_referer()){    
            if ($show_error_message){  //show error message for a few seconds then animate to remove
                $content = '<div class="access-error-message">'.$error_message.'</div>'.$content;
                echo '<style>.access-error-message{margin-top: 100px;}</style>';
                echo "<script type='text/javascript'>
                    document.addEventListener('DOMContentLoaded', function(event) { 
                        jQuery(document).ready(function(){
                            timeout = setTimeout(hideMessage, 3000);
                        });
                        function hideMessage(){
                            jQuery('.access-error-message').animate({
                                'opacity'   : 0, 
                                'height'    : 0, 
                                'padding'   : 0, 
                                'margin'    : 0
                            }, 1000, updateUrl);
                        }
                        function updateUrl(){
                            jQuery('.access-error-message').remove();
                            var url = window.location.href;
                            url = url.split('?')[0];
                            window.history.replaceState({}, null, url);
                        }
                    });
                    </script>";
            }
        }
    }return $content;
}