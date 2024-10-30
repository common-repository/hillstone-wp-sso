<?php
if ( ! defined( 'ABSPATH' ) ) exit;
global $HillstoneSSO;

if( isset( $_GET[ 'tab' ] ) ) {
    $active_tab = $_GET[ 'tab' ];
} else {
	$active_tab = 'setting';
}
?>
<div class="wrap">

    <div id="icon-themes" class="icon32"></div>
    <h2>Hillstone SSO Settings</h2>

    <h2 class="nav-tab-wrapper">
        <a href="<?php echo add_query_arg( array('tab' => 'setting'), $_SERVER['REQUEST_URI'] ); ?>" class="nav-tab <?php echo $active_tab == 'setting' ? 'nav-tab-active' : ''; ?>">Settings</a>
        <a href="<?php echo add_query_arg( array('tab' => 'help'), $_SERVER['REQUEST_URI'] ); ?>" class="nav-tab <?php echo $active_tab == 'help' ? 'nav-tab-active' : ''; ?>">Help</a>
    </h2>

    <form method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
    	<?php wp_nonce_field( 'save_hillstone_sso_settings','save_the_hillstone_sso' ); ?>

    	<?php if( $active_tab == "setting" ): ?>
    	<h3>Required</h3>
    	<p>These are the most basic settings you must configure. Without these, you won't be able to use Hillstone SSO.</p>
    	<table class="form-table">
			<tbody>
				<tr>
					<th scope="row" valign="top">Enable Hillstone SSO</th>
					<td>
						<input type="hidden" name="<?php echo $this->get_field_name('enabled'); ?>" value="false" />
						<label><input type="checkbox" name="<?php echo $this->get_field_name('enabled'); ?>" value="true" <?php if( str_true($this->get_setting('enabled')) ) echo "checked"; ?> /> Enable Hillstone SSO for WordPress. (this one is kind of important) <br/>The admin login url is : <?php echo  home_url('/wp-login.php'); ?>?admin=1</label><br/>
					</td>
	    		<tr>
	    		<!--<tr>
					<th scope="row" valign="top">User Center Base URL</th>
					<td>
						<input type="text" name="<?php echo $this->get_field_name('usercenter_base_url'); ?>" value="<?php echo $HillstoneSSO->get_setting('usercenter_base_url'); ?>" /><br/>
						Hillstone User Center home page address. Example: https://passport.hillstonenet.com
					</td>
				</tr>-->
				<tr>
					<th scope="row" valign="top">CLIENT ID</th>
					<td>
						<input type="text" style="width:350px;" name="<?php echo $this->get_field_name('client_id'); ?>" value="<?php echo $HillstoneSSO->get_setting('client_id'); ?>" />
						<br/>
						If you don't have the client id, please contact tac@hillstonenet.com 
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">CLIENT SECRET</th>
					<td>
						<input type="text" style="width:350px;" name="<?php echo $this->get_field_name('client_secret'); ?>" value="<?php echo $HillstoneSSO->get_setting('client_secret'); ?>" />
						<br/>
						If you don't have the client secret, please contact tac@hillstonenet.com
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">Allow Login User Type(s)</th>
					<td>
						<input type="text" name="<?php echo $this->get_field_name('allowed_usertypes', 'array'); ?>" value="<?php echo join(';', (array)$HillstoneSSO->get_setting('allowed_usertypes')); ?>" />
						<br/>Separate with semi-colons.
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">Enable Single Logout</th>
					<td>
						<input type="hidden" name="<?php echo $this->get_field_name('is_single-logout'); ?>" value="false" />
						<label><input type="checkbox" name="<?php echo $this->get_field_name('is_single-logout'); ?>" value="true" <?php if( str_true($this->get_setting('is_single-logout')) ) echo "checked"; ?> /> Enable Hillstone Single Logout for WordPress.<br/>The Single Logout URL is : <?php echo  home_url('/wp-login.php'); ?>?action=single_logout</label><br/>
					</td>
	    		<tr> 
			</tbody>
    	</table>
    	<p><input class="button-primary" type="submit" value="Save Settings" /></p>    	
    	<?php else: ?>
		<h3>Help</h3>
		<p>This plugin provides single sign-on capabilities for Hillstone web sites using Hillstone user center authentication(OAuth 2.0).</p>
		<h4>Testing</h4>
		<p>The most effective way to test logins is to use two browsers. In other words, keep WordPress Admin open in Chrome, and use Firefox to try logging in. This will give you real time feedback on your settings and prevent you from inadvertently locking yourself out.</p>
		<h4>Which raises the question, what happens if I get locked out?</h4>
		<p>If you accidentally lock yourself out, the easiest way to get back in is to rename <strong><?php echo plugin_dir_path(__FILE__); ?></strong> to something else and then refresh. WordPress will detect the change and disable Hillstone WP SSO. You can then rename the folder back to its previous name.</p>
    	<?php endif; ?>
    </form>
</div>