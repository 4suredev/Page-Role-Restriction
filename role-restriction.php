<?php
/**
 * Plugin Name: Access Manager - Restrict Pages/Posts by User Role
 * Plugin URI: https://4sure.com.au
 * Description: Enable user role restriction per page or post. Requires ACF Pro
 * Version: 2.0.2
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
// Handle plugin activation
register_activation_hook( __FILE__, function() {
    if ( ! is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) and current_user_can( 'activate_plugins' ) ) {
        // Stop activation redirect and show error
        wp_die('Sorry, but this plugin requires ACF pro to be installed and active. <br><a href="' . admin_url( 'plugins.php' ) . '">&laquo; Return to Plugins</a>');
    }
});
add_action('acf/init', 'acc_import_acf_fields');
function acc_import_acf_fields(){
    if ( function_exists( 'acf_add_options_page' ) ) {
        acf_add_options_page(
            array(
                'page_title' => 'Access Manager Page',
                'menu_title' => 'Access Manager',
                'menu_slug'  => 'access-manager',
                'redirect'   => false,
                'capability' => 'administrator',
                'position'   => 20
            )
        );
    }
    include 'import/role-restriction-custom-fields-import.php'; //import acf fieldgroups
}

/* ================== EXPERIMENTAL ================== */
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
}
function acc_default_settings_options(){
    if(get_current_screen()->base == 'toplevel_page_acc-default-settings'){ ?>
    <h1>Access Manager Default Settings</h1>
    <form method="post" action="options.php"> 
        <?php settings_fields( 'acc-default-settings' ); ?>
        <?php do_settings_sections( 'acc-default-settings' ); ?>
        <table class="form-table">
            <tr>
                <p>
                    <label for="restriction_method">Restriction Method</label>
                    <!--  echo esc_attr( get_option('restriction_method') ); -->
                    <select id="restriction_method" name="restriction_method">
                        <option value="redirect" <?php if(esc_attr( get_option('restriction_method') ) == 'redirect') echo 'selected'; ?>>Redirect</option>
                        <option value="stay" <?php if(esc_attr( get_option('restriction_method') ) == 'stay') echo 'selected'; ?>>Stay on current page</option>
                    </select>
                </p>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
    <?php
    }
} 
/* ================== EXPERIMENTAL ================== */

// Populate user role field with all user roles
function acc_get_all_user_roles( $field ) {
    global $wp_roles;
    $roles = $wp_roles->roles;
    if($roles){        
        foreach( $roles as $key=>$role ) {      
            $field['choices'][$key] = $role['name'];
        }
    }
    return $field;
}
add_filter('acf/load_field/name=user_role', 'acc_get_all_user_roles');
// Populate page list field with all page slugs
function acc_get_page_list( $field ) {
    $args = array(
        'post_status' => 'publish',
        'posts_per_page' => -1
    );
    $pages = get_pages($args);
    if($pages){        
        foreach( $pages as $key=>$page ) {      
            $title = $page->post_title;
            $slug = $page->post_name;
            $field['choices'][$slug] = $title;
        }
    }
    return $field;
}
add_filter('acf/load_field/name=redirect_slug', 'acc_get_page_list');
// Redirection content filter 
add_filter('the_content', 'acc_role_restriction_filter_content');
function acc_role_restriction_filter_content($content){
    if (in_the_loop()){ //only affeect the body content
        $post_id = get_the_id();
        $restrict_method = get_field( 'restriction_method', 'options');
        $show_error_message = get_field( 'show_error_message', 'options');
        $error_message_background_color = get_field( 'error_message_background_color', 'options');
        $error_message_text_color = get_field( 'error_message_text_color', 'options');
        $error_message = get_field( 'pagepost_access_denied', 'options' );
        $redirect_slug = get_field( 'redirect_slug', 'options' );
        $additional_content = get_field( 'additional_content', 'options' , false);
        $role_restrictions = (array) get_field('user_role');
        $role_restrictions_meta = get_post_meta( $post_id, 'acc_page_options', true ); //experimental
        $user = wp_get_current_user();
        $user_roles = (array) $user->roles;
        $custom_css = get_field('custom_css', 'options');
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
                if ($restrict_method == 'redirect'){
                    wp_safe_redirect(home_url().'/'.$redirect_slug.'?redirected=true'); //set the redirect path, add redirected variable to make error message appear on the page
                }else if ($restrict_method == 'stay'){
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
        if ($restrict_method == 'redirect' && is_page($redirect_slug) && $_GET['redirected'] && !wp_get_referer()){    
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