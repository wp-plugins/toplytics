<?php  
/* 
 * Plugin Name: Toplytics
 * Plugin URI: http://wordpress.org/extend/plugins/toplytics/ 
 * Description: Plugin for displaying most viewed content using data from a Google Analytics account. Relieves the DB from writing every click.
 * Author: PressLabs 
 * Version: 1.2.2
 * Author URI: http://www.presslabs.com/ 
 */

include 'config.php'; // configuration file
include 'widget-toplytics.php'; // Widget code integration
include 'toplytics.class.php'; // the main class

//--------------------------------------------------------------------
//
// Return the string between 'start' and 'end' from 'conent'.
//
function toplytics_str_between( $start, $end, $content ) {
	$r = explode($start, $content);

	if (isset($r[1])) {
		$r = explode($end, $r[1]);
		return $r[0];
	}

	return '';
}

//--------------------------------------------------------------------
function toplytics_get_templates_list_path() {
	$plugin_templates_list = glob( plugin_dir_path(__FILE__) . '/templates/t_*.php' );
	$theme_templates_list = glob( get_stylesheet_directory() . '/t_*.php' );
	return array_merge($theme_templates_list, $plugin_templates_list);
}

//--------------------------------------------------------------------
function toplytics_get_template_path( $slug ) {
	$files = toplytics_get_templates_list_path();

	foreach($files as $filename) {
		$template_slug = substr( substr( basename($filename), 2), 0, -4 );
		if ( $slug == $template_slug ) {
			$content = file_get_contents($filename);
			$template_name = trim( toplytics_str_between("Toplytics Template:", "\n", $content) );
			if ( $template_name > "" ) return $filename;
		}
	}
	return $slug;
}

//--------------------------------------------------------------------
function toplytics_get_template_name( $slug ) {
	$files = toplytics_get_templates_list_path();

	foreach($files as $filename) {
		$template_slug = substr( substr( basename($filename), 2), 0, -4 );
		if ( $slug == $template_slug ) {
			$content = file_get_contents($filename);
			$template_name = trim( toplytics_str_between("Toplytics Template:", "\n", $content) );
			if ( $template_name > "" ) return $template_name;
		}
	}
	return $slug;
}

//--------------------------------------------------------------------
function toplytics_get_templates() {
	$files = toplytics_get_templates_list_path();

	foreach($files as $filename) {
		$content = file_get_contents($filename);
		$template_name = trim( toplytics_str_between("Toplytics Template:", "\n", $content) );
		if ( $template_name > "" ) {
			$out[]['template_slug'] = substr( substr( basename($filename), 2), 0, -4 );
			$out[]['template_name'] = $template_name;
			$out[]['template_filename'] = $filename;
		}
	}
	return $out;
}

//--------------------------------------------------------------------
function toplytics_get_templates_list() {
	$files = toplytics_get_templates_list_path();

	// remove t_ prefix and .php extension from template filename
	foreach($files as $filename) {
		$content = file_get_contents($filename);
		$template_name = trim( toplytics_str_between("Toplytics Template:", "\n", $content) );
		if ( $template_name > "" ) {
			$filename_with_no_ext[] = substr( substr( basename($filename), 2), 0, -4 );
		}
	}
	return $filename_with_no_ext;
}

//--------------------------------------------------------------------
// Add cron job if all options are set
$options = get_option('toplytics_options');
if ( !empty($options['text_username']) && !empty($options['text_account']) && !empty($options['text_token']) ) {
	if ( !wp_next_scheduled( 'toplytics_hourly_event' ) )
		wp_schedule_event( time(), 'hourly', 'toplytics_hourly_event');
} else {
	wp_clear_scheduled_hook('toplytics_hourly_event');
}

//--------------------------------------------------------------------
function toplytics_activate() {
	add_option('toplytics_options', array(null) );
}
register_activation_hook(__FILE__,'toplytics_activate');
add_action('toplytics_hourly_event', 'toplytics_do_this_hourly');

//--------------------------------------------------------------------
function toplytics_deactivate() {
	//error_log('delete_transient:'.delete_transient('gapi.cache'));
	delete_option('toplytics_options');
	wp_clear_scheduled_hook('toplytics_hourly_event');
}
register_deactivation_hook(__FILE__,'toplytics_deactivate');

//--------------------------------------------------------------------
function toplytics_do_this_hourly() { // scan Google Analytics statistics every hour
	delete_transient('gapi.cache');
	Toplytics::ga_statistics();
}

//------------------------------------------------------------------
function toplytics_widgets_init() {
	$options = get_option('toplytics_options');
	$transient = get_transient('gapi.cache');
	//error_log(print_r($transient['today'],true));
	if ( !empty($options['text_username']) && !empty($options['text_account']) 
		&& !empty($options['text_token']) && !empty($transient['today']) )
			register_widget('Toplytics_WP_Widget_Most_Visited_Posts');
} 
add_action('widgets_init','toplytics_widgets_init');

//------------------------------------------------------------------
//
// Add settings link on plugin page.
//
function toplytics_settings_link($links) {
	$settings_link = "<a href='".toplytics_return_settings_link()."'>". __("Settings")."</a>";
	array_unshift($links, $settings_link);

	return $links; 
}
add_filter("plugin_action_links_".plugin_basename(__FILE__), 'toplytics_settings_link' );

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
//
//  Add CSS file to Front End
//
function toplytics_add_wp_stylesheet() {
	wp_register_style( 'toplytics-style', plugins_url('/toplytics.css', 
		__FILE__), false, filemtime( dirname(__FILE__) . '/toplytics.css'));
	wp_enqueue_style( 'toplytics-style');
}
add_action('wp_enqueue_scripts', 'toplytics_add_wp_stylesheet');

//------------------------------------------------------------------
//
// Dashboard integration (Tools)
//
function toplytics_menu() {
	add_management_page('Toplytics Options Page', 'Toplytics', 
		'manage_options', __FILE__, 'toplytics_options_page');
}
add_action('admin_menu', 'toplytics_menu');

//------------------------------------------------------------------
function toplytics_options_page() {
	if (!current_user_can('manage_options'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}

	// if settings are not empty then run the function called every hour (scan the GA statistics)
	// this case is useful when you change the GA account settings
	$options = get_option('toplytics_options');
	if ( !empty($options['text_username']) && !empty($options['text_account']) && !empty($options['text_token']) )
		toplytics_do_this_hourly();
?>
<div class="wrap">
<div id="icon-tools" class="icon32">&nbsp;</div>

<h2>Toplytics</h2>
Please configure your Google Analytics Account to be used for this site:
<form action="options.php" method="post">
  <p>
	<?php settings_fields('toplytics_options'); ?>
	<?php do_settings_sections('toplytics'); ?>

<?php if ( $options['text_token'] == '' || $options['text_token'] == null ) { ?>
<table class="form-table">
<tbody>
	<tr valign="top">
<th scope="row">Password:</th>
<td>
<input id="text_password" value="" size="40" name="text_password" type="password"><br />
Please enter the password to connect to Google Analytics Account!<br />
The password is not keeped in database, it is used only one time, to generate the token then it is used the token instead of password.
</td>
</tr>
	</tbody>
	</table>
<?php } ?>

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
	if ( empty($options['text_username']) || empty($options['text_account']) || empty($options['text_token']) || $options['text_token']==null )
		add_action('admin_notices', create_function( '', "echo '<div class=\"error\"><p>"
			.sprintf(__('Toplytics needs configuration information on its <a href="%s">'.__('Settings').'</a> page.', $plugin_page), 
  					 admin_url('tools.php?page='.$plugin_page))."</p></div>';" ) );

	register_setting( 'toplytics_options', 'toplytics_options', 'toplytics_options_validate' );
	add_settings_section('toplytics_main', 'Google Analytics account', 'toplytics_section_text', 'toplytics');
	add_settings_field('toplytics_text_username', 'User name (your email):', 'toplytics_setting_username', 'toplytics', 'toplytics_main');
	add_settings_field('toplytics_text_account', 'Account (your site ID):', 'toplytics_setting_account', 'toplytics', 'toplytics_main');
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
function toplytics_options_validate($input) {
	$options = get_option('plugin_options');
	$options['text_username'] = trim($input['text_username']);
	$options['text_account'] = trim($input['text_account']);

	if ( isset( $_POST['text_password'] ) ) {
		$options['text_token'] = '';
		require_once 'gapi.class.php'; // The code
	  	try {
			$ga = new gapi($options['text_username'], $_POST['text_password']);
			if ( $ga->getAuthToken() )
				$options['text_token'] = $ga->getAuthToken();
		} catch (Exception $e) {
			wp_die ('GAPI: Failed to authenticate user.');
		}
	}
	
	$results = get_transient('gapi.cache');

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

