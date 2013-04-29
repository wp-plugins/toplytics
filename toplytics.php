<?php  
/* 
 * Plugin Name: Toplytics
 * Plugin URI: http://wordpress.org/extend/plugins/toplytics/ 
 * Description: Plugin for displaying most viewed content using data from a Google Analytics account. Relieves the DB from writing every click.
 * Author: PressLabs 
 * Version: 1.1
 * Author URI: http://www.presslabs.com/ 
 */

include 'config.php'; // configuration file
include 'widget-toplytics.php'; // Widget code integration

//--------------------------------------------------------------------
// Add cron job if all options are set
$options = get_option('toplytics_options');
if ( !empty($options['text_username']) && !empty($options['text_account']) && !empty($options['text_pass']) ) {
  if ( !wp_next_scheduled( 'toplytics_hourly_event' ) )
		wp_schedule_event( time(), 'hourly', 'toplytics_hourly_event');
} else {
	wp_clear_scheduled_hook('toplytics_hourly_event');
}

//--------------------------------------------------------------------
register_activation_hook(__FILE__,'toplytics_activate');
add_action('toplytics_hourly_event', 'toplytics_do_this_hourly');
function toplytics_activate() {
	add_option('toplytics_options', array(null) );
}

//--------------------------------------------------------------------
register_deactivation_hook(__FILE__,'toplytics_deactivate');
function toplytics_deactivate() {
	delete_option('toplytics_options');
	wp_clear_scheduled_hook('toplytics_hourly_event');
}

//--------------------------------------------------------------------
function toplytics_do_this_hourly() { // do something every hour
	require_once 'toplytics.class.php';
  
	$results = Toplytics::ga_statistics();
	set_transient('gapi.cache', $results, 48 * 1800);
}

//------------------------------------------------------------------
add_action('widgets_init','toplytics_widgets_init');
function toplytics_widgets_init() {
	$options = get_option('toplytics_options');
	if ( !empty($options['text_username']) && !empty($options['text_account']) && !empty($options['text_pass']) )
		register_widget('Toplytics_WP_Widget_Most_Visited_Posts');
} 

//------------------------------------------------------------------
//
// Add settings link on plugin page.
//
$plugin = plugin_basename(__FILE__); 
add_filter("plugin_action_links_$plugin", 'toplytics_settings_link' );
function toplytics_settings_link($links) {
	$settings_link = "<a href='".toplytics_return_settings_link()."'>". __("Settings")."</a>";
	array_unshift($links, $settings_link);

	return $links; 
}

//------------------------------------------------------------------
function toplytics_return_settings_link() {
	$plugin_page = plugin_basename(__FILE__);

	return admin_url('tools.php?page='.$plugin_page);
}

//------------------------------------------------------------------
//
// Displays all messages registered to 'your-settings-error-slug'
//
function toplytics_admin_notices_action() {
    settings_errors();
}
add_action( 'admin_notices', 'toplytics_admin_notices_action' );

//------------------------------------------------------------------
add_action('wp_enqueue_scripts', 'toplytics_add_wp_stylesheet');
function toplytics_add_wp_stylesheet() {
	wp_register_style( 'toplytics-style', plugins_url('/toplytics.css', 
	  __FILE__), false, filemtime( dirname(__FILE__) . '/toplytics.css'));
	wp_enqueue_style( 'toplytics-style');
}

//------------------------------------------------------------------
//
// Dashboard integration
//
add_action('admin_menu', 'toplytics_menu');
function toplytics_menu() {
	add_management_page('Toplytics Options Page', 'Toplytics', 
	  'manage_options', __FILE__, 'toplytics_options_page');
}

//------------------------------------------------------------------
function toplytics_options_page() {
	if (!current_user_can('manage_options'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}?>
<div class="wrap">
<div id="icon-tools" class="icon32">&nbsp;</div>

<h2>Toplytics</h2>
Please configure your Google Analytics Account to be used for this site:
<form action="options.php" method="post">
  <p>
	<?php settings_fields('toplytics_options'); ?>
	<?php do_settings_sections('toplytics'); ?>
  </p>
	<input name="Submit" type="submit" class="button button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
</form></div><?php
}

//------------------------------------------------------------------
add_action('admin_init', 'toplytics_admin_init');
function toplytics_admin_init(){
	$plugin_page = plugin_basename(__FILE__);
	$plugin_link = toplytics_return_settings_link();
	$options = get_option('toplytics_options');
	if ( empty($options['text_username']) || empty($options['text_account']) || empty($options['text_pass']) )
		add_action('admin_notices', create_function( '', "echo '<div class=\"error\"><p>"
			.sprintf(__('Toplytics needs configuration information on its <a href="%s">'.__('Settings').'</a> page.', $plugin_page), 
  					 admin_url('tools.php?page='.$plugin_page))."</p></div>';" ) );

	register_setting( 'toplytics_options', 'toplytics_options', 'toplytics_options_validate' );
	add_settings_section('toplytics_main', 'Google Analytics account', 'toplytics_section_text', 'toplytics');
	add_settings_field('toplytics_text_username', 'User name (your email):', 'toplytics_setting_username', 'toplytics', 'toplytics_main');
	add_settings_field('toplytics_text_account', 'Account (your site ID):', 'toplytics_setting_account', 'toplytics', 'toplytics_main');
	add_settings_field('toplytics_text_pass', 'Password:', 'toplytics_setting_pass', 'toplytics', 'toplytics_main');
}

//------------------------------------------------------------------
function toplytics_section_text() {
	echo '<p>Enter here the details of your account:</p>';
}

//------------------------------------------------------------------
function toplytics_setting_username() {
	$options = get_option('toplytics_options');
	echo "<input id='toplytics_text_username' name='toplytics_options[text_username]' size='40' type='text' value='{$options['text_username']}' />";
}

//------------------------------------------------------------------
function toplytics_setting_account() {
        $options = get_option('toplytics_options');
        echo "<input id='toplytics_text_account' name='toplytics_options[text_account]' size='40' type='text' value='{$options['text_account']}' />";
}

//------------------------------------------------------------------
function toplytics_setting_pass() {
        $options = get_option('toplytics_options');
        echo "<input id='toplytics_text_pass' name='toplytics_options[text_pass]' size='40' type='password' value='{$options['text_pass']}' />";
}

//------------------------------------------------------------------
function toplytics_options_validate($input) {
	$options = get_option('plugin_options');
	$options['text_username'] = trim($input['text_username']);
	$options['text_account'] = trim($input['text_account']);
	$options['text_pass'] = trim($input['text_pass']);
	if(!preg_match('/^[a-zA-Z0-9\.@-]{2,}$/i', $options['text_username']))
	{
		$options['text_username'] = '';
	}
	if(!preg_match('/^[0-9]{2,}$/i', $options['text_account']))
	{
		$options['text_account'] = '';
	}
	return $options;
}


