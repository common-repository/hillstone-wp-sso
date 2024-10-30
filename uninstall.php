<?php
/**
 * Created by PhpStorm.
 * User: TAC
 * Date: 2015/12/18
 * Time: 16:06
 */
// Only run when WP_UNINSTALL_PLUGIN is set
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit ();

// Remove plugin options
delete_option( 'hillstone_sso_settings' );

// Remove user meta
$users = get_users();

if ( $users ) :

	foreach ( $users as $user ) :

		delete_user_meta( $user->ID, 'hillstone_access_token' );
		delete_user_meta( $user->ID, 'hillstone_expires_time' );
		delete_user_meta( $user->ID, 'hillstone_refresh_token' );

	endforeach;

endif;