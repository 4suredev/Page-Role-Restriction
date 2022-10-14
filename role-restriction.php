<?php
/**
 * Plugin Name: Access Manager - Restrict Pages/Posts by User Role
 * Plugin URI: https://4sure.com.au
 * Description: Enable user role restriction per page or post. Requires ACF Pro
 * Version: 2.0.1
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
    include 'import/role-restriction-custom-fields-import.php';
}
// POPULATE CUSTOM FIELD CHECKBOX VALUES WITH USER ROLES
function get_all_user_roles( $field ) {
    global $wp_roles;
    $roles = $wp_roles->roles;
    if($roles){        
        foreach( $roles as $key=>$role ) {      
            $field['choices'][$key] = $role['name'];
        }
    }
    return $field;
}
add_filter('acf/load_field/name=user_role', 'get_all_user_roles');
function get_page_list( $field ) {
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
add_filter('acf/load_field/name=redirect_slug', 'get_page_list');
function get_string_between($string, $start, $end){
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}
// REDIRECTION FILTER FOR PAGES IF THEY HAVE ROLE RESTRICTION VALUES
add_filter('the_content', 'role_restriction_filter_content');
function role_restriction_filter_content($content){
    if (in_the_loop()){ //only affeect the body content
        $restrict_method = get_field( 'restriction_method', 'options');
        $show_error_message = get_field( 'show_error_message', 'options');
        $error_message_background_color = get_field( 'error_message_background_color', 'options');
        $error_message_text_color = get_field( 'error_message_text_color', 'options');
        $error_message = get_field( 'pagepost_access_denied', 'options' );
        $redirect_slug = get_field( 'redirect_slug', 'options' );
        $additional_content = get_field( 'additional_content', 'options' , false);
        $role_restrictions = (array) get_field('user_role');
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
                    pointer-events: none;
                    margin-bottom: '.$margin.';
                }                   
            </style>';
        if($custom_css != ""){echo "<style>".$custom_css."</style>";}   
        if (count($role_restrictions) >= 1){ // checks every post and page if it has restrictions
            $matched_roles = array_intersect($role_restrictions, $user_roles);
            if (count($matched_roles) == 0){
                if ($restrict_method == 'redirect'){
                    wp_safe_redirect(home_url().'/'.$redirect_slug.'?redirected=true'); //set the redirect path here
                }else if ($restrict_method == 'stay'){
                    if ($show_error_message){
                        $content = '<div class="access-error-message">'.$error_message.'</div>';
                        if ($additional_content != ""){
                            $content .= $additional_content;
                        }
                    
                    }else{
                        
                    }
        
                }  
            }else{
                foreach($matched_roles as $role){
                    //per role validation here
                    // if ($role == "role1"){}
                    // else if ($role == "role2"){}
                    // else{}
                }
            }
        }
        if ($restrict_method == 'redirect' && is_page($redirect_slug) && $_GET['redirected'] && !wp_get_referer()){    
            if ($show_error_message){
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