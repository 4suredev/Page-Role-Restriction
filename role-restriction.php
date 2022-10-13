<?php
/**
 * Plugin Name: Access Manager - Restrict Pages/Posts by User Role
 * Plugin URI: https://4sure.com.au
 * Description: Enable user role restriction per page or post. Requires ACF Pro
 * Version: 1.0.0
 * Author: 4sure
 * Author URI: https://4sure.com.au
 */
// Constants
define('ACC_PLUGIN_PATH', plugin_dir_url( __FILE__ ));
define('ACC_PLUGIN_FILE', ACC_PLUGIN_PATH.'role-restriction.php');
// Handle plugin activation
register_activation_hook( __FILE__, function() {
    if ( ! is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) and current_user_can( 'activate_plugins' ) ) {
        // Stop activation redirect and show error
        wp_die('Sorry, but this plugin requires ACF pro to be installed and active. <br><a href="' . admin_url( 'plugins.php' ) . '">&laquo; Return to Plugins</a>');
    }
});
acc_import_acf_fields();
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
// REDIRECTION FUNCTION FOR PAGES IF THEY HAVE ROLE RESTRICTION VALUES
function redirect_by_role(){
    $restrict_method = get_field( 'restriction_method', 'options');
    $show_error_message = get_field( 'show_error_message', 'options');
    $error_message_background_color = get_field( 'error_message_background_color', 'options');
    $error_message_text_color = get_field( 'error_message_text_color', 'options');
    $error_message = get_field( 'pagepost_access_denied', 'options' );
    $redirect_slug = get_field( 'redirect_slug', 'options' );
    $additional_content = get_field( 'additional_content', 'options' , false);
    $target_element = get_field( 'target_element', 'options' );
    $role_restrictions = (array) get_field('user_role');
    $user = wp_get_current_user();
    $user_roles = (array) $user->roles;
    if (count($role_restrictions) >= 1){ // checks every post and page if it has restrictions
        $matched_roles = array_intersect($role_restrictions, $user_roles);
        if (count($matched_roles) == 0){
            if ($restrict_method == 'redirect'){
                wp_safe_redirect(home_url().'/'.$redirect_slug.'?redirected=true'); //set the redirect path here
            }else if ($restrict_method == 'stay'){
                if ($show_error_message){
                    echo  '<style>
                        @keyframes placeHolderShimmer{
                            0%{
                                background-position: -1200px 0
                            }
                            100%{
                                background-position: 1200px 0
                            }
                        }
                        .access-error-message{
                            color: '.$error_message_text_color.';
                            background: '.$error_message_background_color.';
                            padding: 30px;
                            text-align: center;
                            pointer-events: none;
                        }
                        .gform_wrapper{ display: none; }
                        '.$target_element.' > *{
                            opacity: 0;
                            transition: all ease-in .6s;
                        }
                        '.$target_element.'::before{
                            content: "";
                            width: 100%;
                            height: 20px;
                            position: absolute;
                            top: -30px;
                            border-radius: 10px;
                            animation-duration: 3.5s;
                            animation-fill-mode: forwards;
                            animation-iteration-count: infinite;
                            animation-name: placeHolderShimmer;
                            animation-timing-function: linear;
                            background: darkgray;
                            background: linear-gradient(to right, #eeeeee 10%, #dddddd 18%, #eeeeee 33%);
                            transition: opacity ease-out .3s;
                        }
                        '.$target_element.'.loaded::before{opacity: 0;}
                    </style>';
                    if ($additional_content != ""){
                        preg_match_all('#\[(.*?)\]#', $additional_content, $match);
                        $gravityforms = array();
                        foreach($match[0] as $shortcode){
                            $shortcode_type = substr($shortcode, strpos($shortcode, "[") + 1);    
                            $shortcode_type = substr( $shortcode_type, 0, 12 );
                            if ($shortcode_type != 'gravityforms'){
                                $additional_content = str_replace($shortcode, do_shortcode($shortcode), $additional_content);
                            }else{
                                echo '<style>.gform_wrapper{display: none;}</style>';
                                $gform_id_1 = get_string_between($shortcode, 'id=', ']');
                                $gform_id_2 = get_string_between($shortcode, 'id=', ' ');
                                if ($gform_id_1 != false){$gform_id = $gform_id_1;}
                                if ($gform_id_2 != false){$gform_id = $gform_id_2;}
                                gravity_form( $gform_id, $display_title = false, $display_description = false, $display_inactive = false, $field_values = null, $ajax = false, $tabindex, $echo = true );
                                $additional_content = str_replace($shortcode, '<div class="embed-form-'.$gform_id.'"></div>', $additional_content);
                                array_push($gravityforms, $gform_id);
                            }
                        }
                        $additional_content = str_replace(array("\r", "\n"), '', $additional_content);
                    }
                    echo "<script type='text/javascript'>
                    document.addEventListener('DOMContentLoaded', function(event) { 
                        jQuery(document).ready(function($){
                            $('".$target_element."').html('');
                            $('".$target_element."').prepend('<div class=\"access-error-message\">".$error_message."</div><div class=\"additional-content\"></div>');";
                    echo "$('.additional-content').html('".trim($additional_content)."');";
                    foreach($gravityforms as $form){
                        echo "$('#gform_wrapper_".$form."').appendTo($('.embed-form-".$form."'));";
                        echo "$('#gform_wrapper_".$form."').css('display','block');";
                    }
                    echo "$('".$target_element.">*').animate({opacity: 1}, 400);";
                    echo "$('".$target_element."').addClass('loaded');";
                    echo "});});</script>";
                }else{
                    echo "<script type='text/javascript'>
                    document.addEventListener('DOMContentLoaded', function(event) { 
                        jQuery(document).ready(function($){
                            $('".$target_element."').html('');
                        });
                    });
                    </script>";
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
            echo  '<style>
                .access-error-message{
                    color: '.$error_message_text_color.';
                    background:  '.$error_message_background_color.';
                    padding: 30px;
                    text-align: center;
                    pointer-events: none;
                }
                .gform_wrapper{ display: none; }
                '.$target_element.' > *{
                    opacity: 0;
                    transition: all ease-in .6s;
                }
                '.$target_element.'::before{
                    content: "";
                    width: 100%;
                    height: 20px;
                    position: absolute;
                    top: -30px;
                    border-radius: 10px;
                    animation-duration: 3.5s;
                    animation-fill-mode: forwards;
                    animation-iteration-count: infinite;
                    animation-name: placeHolderShimmer;
                    animation-timing-function: linear;
                    background: darkgray;
                    background: linear-gradient(to right, #eeeeee 10%, #dddddd 18%, #eeeeee 33%);
                    transition: opacity ease-out .3s;
                }
                '.$target_element.'.loaded::before{opacity: 0;}
            </style>';
            if ($additional_content != ""){
                preg_match_all('#\[(.*?)\]#', $additional_content, $match);
                $gravityforms = array();
                foreach($match[0] as $shortcode){
                    preg_match_all('#\[(.*?)\]#', $additional_content, $match);
                    foreach($match[0] as $shortcode){
                        $shortcode_type = substr($shortcode, strpos($shortcode, "[") + 1);    
                        $shortcode_type = substr( $shortcode_type, 0, 12 );
                        if ($shortcode_type != 'gravityforms'){
                            $additional_content = str_replace($shortcode, do_shortcode($shortcode), $additional_content);
                        }else{
                            echo '<style>.gform_wrapper{display: none;}</style>';
                            $gform_id_1 = get_string_between($shortcode, 'id=', ']');
                            $gform_id_2 = get_string_between($shortcode, 'id=', ' ');
                            if ($gform_id_1 != false){$gform_id = $gform_id_1;}
                            if ($gform_id_2 != false){$gform_id = $gform_id_2;}
                            gravity_form( $gform_id, $display_title = false, $display_description = false, $display_inactive = false, $field_values = null, $ajax = false, $tabindex, $echo = true );
                            $additional_content = str_replace($shortcode, '<div class="embed-form-'.$gform_id.'"></div>', $additional_content);
                            array_push($gravityforms, $gform_id);
                        }
                    }
                    $additional_content = str_replace(array("\r", "\n"), '', $additional_content);
                }
                $additional_content = str_replace(array("\r", "\n"), '', $additional_content);
            }
            echo "<script type='text/javascript'>
                    document.addEventListener('DOMContentLoaded', function(event) { 
                        jQuery(document).ready(function($){
                            $('".$target_element."').prepend('<div class=\"access-error-message\">".$error_message."</div><div class=\"additional-content\"></div>');";
            echo "$('.additional-content').html('".trim($additional_content)."');";
            foreach($gravityforms as $form){
                echo "$('#gform_wrapper_".$form."').appendTo($('.embed-form-".$form."'));";
                echo "$('#gform_wrapper_".$form."').css('display','block');";
            }
            echo "$('".$target_element." > *').animate({opacity: 1}, 400);";
            echo "$('".$target_element."').addClass('loaded');";
            echo "});});</script>";
        }else{
            echo "<script type='text/javascript'>
            document.addEventListener('DOMContentLoaded', function(event) { 
                jQuery(document).ready(function($){
                    $('".$target_element."').html('');
                });
            });
            </script>";
        }
        
    }
}
add_action('template_redirect', 'redirect_by_role');
function get_string_between($string, $start, $end){
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}