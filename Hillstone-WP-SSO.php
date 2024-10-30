<?php
/*
Plugin Name: Hillstone WP SSO
Plugin URI: https://passport.hillstonenet.com/
Description: This plugin provides single sign-on capabilities for Hillstone web sites using Hillstone user center authentication.
Version: 1.2.10
Author: Hillstone
Author URI: http://www.hillstonenet.com
Text Domain: hillstone-wp-sso-lang
Domain Path: /lang
License: GPL2
*/
/*  Copyright 2015  tac  (email : tac@hillstonenet.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
if ( ! defined( 'ABSPATH' ) ) exit;
class HillstoneSSO {
    static $instance = false;
    var $prefix = 'hillstone_sso_';
    var $settings = array();

    /**
     * @var string $pluginurl The url to this plugin
     */
    var $pluginurl = '';

    /**
     * @var string $pluginpath The path to this plugin
     */
    var $pluginpath = '';

    /**
     * @var string The plugin version
     */
    var $version = '1.2.8';

    /**
     * @var string simplemodal version
     */
    var $simplemodalVersion = '1.4.3';

    var $Hillstone_APPID;
    var $Hillstone_APPSECRET;
    var $allowed_usertypes;
    var $is_single_logout;
    var $temp_access_token;
    var $cert_path;

    /**
     * HillstoneSSO constructor.
     */
    public function __construct () {
        $name = dirname(plugin_basename(__FILE__));
        $this->settings = $this->get_settings_obj( $this->prefix );
        /**加载语言包*/
        add_action('plugins_loaded', array($this,'load_textdomain') );
        add_action('admin_init', array($this, 'save_settings') );
        /** 将函数注册到钩子中 */
        add_action('admin_menu', array($this, 'menu') );

        //"Constants" setup
        $this->pluginurl = WP_PLUGIN_URL . "/$name/";
        $this->pluginpath = WP_PLUGIN_DIR . "/$name/";

        if ( str_true( $this->get_setting( 'enabled' ) ) ) {
            $this->cert_path           = plugin_dir_path( __FILE__ ) . 'cert/ca.crt';
            $this->Hillstone_APPID     = $this->get_setting( 'client_id' );
            $this->Hillstone_APPSECRET = $this->get_setting( 'client_secret' );
            $this->allowed_usertypes   = (array) $this->get_setting( 'allowed_usertypes' );
            $this->is_single_logout    = $this->get_setting( 'is_single-logout' );

            //add_filter( 'login_enqueue_scripts', array( $this, 'login_hillstone_sso' ), 10, 0 );
            add_filter('login_init', array($this, 'login_hillstone_sso'),10,0);
            add_action('init', array($this, 'init'));
            ///如果开启了单点退出功能
            if ( str_true( $this->is_single_logout ) ) {
                ///重定向到认证中心的统一退出地址
                if ( ! isset( $_GET['token'] ) ) {
                    add_filter( 'logout_url', array( $this, 'get_single_logout_url' ), 1, 3 );
                }
            }
        }
        register_activation_hook( __FILE__, array($this, 'activate') );
    }
    public static function getInstance () {
        if ( !self::$instance ) {
            self::$instance = new self;
        }
        return self::$instance;
    }
    /** 定义添加菜单选项的函数 */
    function menu () {
        add_options_page("Hillstone SSO", "Hillstone SSO", 'manage_options', "hillstone-sso", array($this, 'admin_page') );
    }
    function init() {
        if ( ! is_user_logged_in() ) {
            /** Modal Login*/
            
            add_action('login_footer',array($this, 'custom_login_html'));
            add_action('login_enqueue_scripts', array($this, 'login_css'));
            //add_filter( 'loginout', array( &$this, 'login_loginout' ) );
            //add_action( 'wp_footer', array( $this, 'login_footer' ) );
            // add_action( 'wp_print_scripts', array( $this, 'login_js' ) );
            //add_action( 'wp_print_styles', array( $this, 'hillstone_sso_style' ) );
        }
    }
    /** 定义选项被点击时打开的页面 */
    function admin_page () {
        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.','hillstone-wp-sso-lang') );
        }
        include 'Admin.php';
    }

    function activate () {
        // Default settings
        $this->add_setting('usercenter_base_url', "https://passport.hillstonenet.com");
        $this->add_setting('client_id', "");
        $this->add_setting('allowed_usertypes', array("0") );
        $this->add_setting('client_secret', "");
        $this->add_setting('is_single-logout', "false");
        $this->add_setting('enabled', "false");
    }
    function load_textdomain() {
        load_plugin_textdomain( 'hillstone-wp-sso-lang', false , dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
    }
    function login_hillstone_sso(){
        if($_SERVER['REQUEST_METHOD']=='GET')
        {
            if (!isset($_GET['loggedout']))
            {
                $action='login';
                if(!empty($_GET['action']))
                {
                    $action=$_GET['action'];
                }

                switch ($action)
                {
                    case 'register':
                        header('Location: '. $this->get_setting('usercenter_base_url').'/Account/Register?returnUrl='.urlencode (home_url()));
                        break;
                    case 'lostpassword':
                        header('Location: '. $this->get_setting('usercenter_base_url').'/Account/ForgotPassword?returnUrl='.urlencode (home_url()));
                        break;
                    case 'logout':

                        break;
                    case 'jump':
                        header('Location: '. $this->GenerateSSOUrl());
                        break;
                    case 'single_logout':
                        if(!isset($_GET['token'])) {
                            $logout_token = $this->get_logout_token();
                            if ( isset( $logout_token ) ) {
                                error_log('[Hillstone-WP-SSO]wp_redirect');
                                wp_redirect( $this->get_setting( 'usercenter_base_url' ) . '/Account/SignOut?token=' . $logout_token . '&returnUrl=' . urlencode( home_url() ));

                                //return;
                                //return header( 'Location: ' . $this->get_setting( 'usercenter_base_url' ) . '/Account/SignOut?token=' . $logout_token . '&returnUrl=' . urlencode( home_url() ) );

                                error_log('[Hillstone-WP-SSO]wp_redirect end');
                                exit;
                            }
                            else
                            {
                                error_log('[Hillstone-WP-SSO]logout no token');
                                wp_logout();
                                wp_redirect(home_url());
                            }
                            exit ;
                        }
                        else
                        {
                            error_log('[Hillstone-WP-SSO]logout');
                            wp_logout();
                            wp_redirect(home_url());
                            exit ;
                        }
                            //if (str_true($this->check_logout_token($_GET['token'])))
                            //{
                            //}
                        break;
                    case 'login':
                        if (!is_user_logged_in()) {
                            if ( ! isset( $_GET['admin'] ) or $_GET['admin'] != '1' ) {
                                if ( isset( $_GET['sso'] )) {
                                    // if ( isset( $_GET['display'] ) and $_GET['display'] == 'full' ){                          
                                    //     $redirect_url=$this->GenerateSSOUrl();  
                                    //     echo '<script type=\'text/javascript\'>window.parent.location.href=\''.$redirect_url.'\'</script>';
                                    //     return ;
                                    // }
                                    // else
                                    // {
                                    $this->hillstone_oauth();
                                    return;
                                    // }
                                } 
                                // else {
                                //     //$this->hillstone_oauth_redirect();
                                // }
                            }
                        }
                        else
                        {
                            if(isset($_GET['redirect_to']))
                            {
                                return header('Location: '.$_GET['redirect_to']);
                            }
                            else
                            {
                                return header('Location: '.home_url());
                            }
                        }
                        break;
                    default:
                        break;
                }
            }
        }
    }
    function get_hillstone_accesstoken()
    {
        global $current_user;
        get_currentuserinfo();
        $expire_time=get_user_meta($current_user->ID,'hillstone_expires_time',true);
        $access_token=get_user_meta($current_user->ID,'hillstone_access_token',true);
        $refresh_token=get_user_meta($current_user->ID,'hillstone_refresh_token',true);

        error_log('Cache : $access_token:'.$access_token.',:$refresh_token'.$refresh_token.',$expire_time:'.$expire_time);
        if (strtotime($expire_time)<strtotime(time()))
        {
            return  $access_token;
        }
        else
        {
            return $this->refresh_hillstone_accesstoken($current_user->ID,$refresh_token);
        }
    }
    function refresh_hillstone_accesstoken($userid,$refresh_token)
    {

        $url = $this->get_setting('usercenter_base_url').'/OAuth/Token';
        $post_data='refresh_token='. $refresh_token .'&grant_type=refresh_token';

        $headers = array ( 'Content-Type'=>'application/x-www-form-urlencoded', 'Authorization'=>'Basic '. base64_encode($this->Hillstone_APPID . ':' . $this->Hillstone_APPSECRET));
        $request=new WP_Http();
        $result=$request->request($url,array('method'=>'POST','timeout'=>60,'body' => $post_data, 'headers' => $headers,'sslverify' => true,'user-agent'=>'Hillstone-WP-SSO '.$this->version));
        $ss = json_decode($result['body'],true);

        if(!isset($ss['access_token']))
        {
            return ;
        }
        update_user_meta($userid ,"hillstone_access_token",$ss['access_token']);
        update_user_meta($userid ,"hillstone_expires_time",time() + ($ss['expires_in']-5));
        update_user_meta($userid ,"hillstone_refresh_token",$ss['refresh_token']);
        return $ss['access_token'];

    }
    function get_logout_token()
    {
        $access_token=$this->get_hillstone_accesstoken();
        $url=$this->get_setting('usercenter_base_url').'/OAuth/SignOutToken';
        $headers = array ('Authorization'=>'Bearer '. $access_token);
        error_log('get_logout_token access_token:'.$access_token);
        $request=new WP_Http();
        $result=$request->request($url,array('method'=>'GET','timeout'=>60, 'headers' => $headers,'sslverify' => true,'user-agent'=>'Hillstone-WP-SSO '.$this->version));

        $ss = json_decode($result['body'],true);
        if (!isset($ss['code']))
        {
            return ;
        }
        if ($ss['code']!='0')
        {
            return ;
        }
        return  $ss['message'];
    }
    function get_single_logout_url($logouturl)
    {
        return home_url() . '/wp-login.php?action=single_logout';
    }
    // function get_sso_url()
    // {
    //     return 'javascript()';
    // }
    function remove_hillstone_auth($access_token)
    {
        $url=$this->get_setting('usercenter_base_url').'/OAuth/Remove';
        $headers = array ( 'Authorization'=>'Bearer '. $access_token);
        $request=new WP_Http();
        $result=$request->request($url,array('method'=>'GET','timeout'=>60, 'headers' => $headers,'sslverify' => true,'user-agent'=>'Hillstone-WP-SSO '.$this->version));
    }
    function custom_login_html() {
        $redirect_to=urlencode('/');
        if(isset($_GET['redirect_to'])){
            $redirect_to=$_GET['redirect_to'];
        }
        include 'LoginPage.php';
    }
    function hillstone_oauth_redirect(){
        $url=$this->GenerateSSOUrl();
        @header( 'Location: ' . $url );
        // if(isset($_GET['display']) and $_GET['display'] == 'dialog') {
        //     $url .= '&display=dialog';		
        //     $site_url=rtrim(site_url(),'/');
        //     if(strlen($site_url)<strlen('hillstonenet.com') or substr($site_url,0-strlen('hillstonenet.com'),strlen('hillstonenet.com'))<>'hillstonenet.com')
        //     {
        //         $url .= '&cross_domain=true';
        //     }	
        //     @header( 'Location: ' . $url );
        // }
        // else
        // {
        // add_action('login_footer',array($this, 'custom_login_html'));
        // add_action('login_enqueue_scripts', array($this, 'login_css'));
        // }
        return ;
    }
    function GenerateSSOUrl()
    {
        $_cookie_state      = uniqid( rand(), true );
        $_SESSION ['state'] = md5( $_cookie_state );
        $scope = 'full_info remove_authorization check_logout_token';
        if ( str_true( $this->is_single_logout ) ) {
            $scope .= ' logout_token';
        }
        $redirect_to=urlencode('/');
        if(isset($_GET['redirect_to'])){
            $redirect_to=$_GET['redirect_to'];
        }
        $url = $this->get_setting( 'usercenter_base_url' ) . '/OAuth/Authorize?client_id=' . $this->Hillstone_APPID . '&redirect_uri=' . urlencode( home_url() . '/wp-login.php?sso=1&redirect_to='.$redirect_to ) . '&response_type=code&scope=' . $scope . '&state=' . $_SESSION ['state'] . '';
        return $url;
    }
    function hillstone_oauth()
    {
        $redirect_to=urlencode('/');
        if(isset($_GET['redirect_to'])){
            $redirect_to=$_GET['redirect_to'];
        }
        $code = $_GET['code'];
        $url = $this->get_setting('usercenter_base_url').'/OAuth/Token';
        $post_data='code='. $code .'&redirect_uri='. urlencode (home_url().'/wp-login.php?sso=1&redirect_to='.$redirect_to) .'&grant_type=authorization_code';

        $headers = array ( 'Content-Type'=>'application/x-www-form-urlencoded', 'Authorization'=>'Basic '. base64_encode($this->Hillstone_APPID . ':' . $this->Hillstone_APPSECRET));
        $request=new WP_Http();
        $result=$request->request($url,array('method'=>'POST','timeout'=>60, 'body' => $post_data, 'headers' => $headers,'sslverify' => true,'user-agent'=>'Hillstone-WP-SSO '.$this->version,'user-agent'=>'Hillstone-WP-SSO '.$this->version));
        //print_r($result);,'sslcertificates' => $this->cert_path
        if(is_wp_error($result))
        {
            $this->logFile('get token http request -> get_error_message:\r\n'.$result->get_error_message());
            $this->hillstone_oauth_redirect();
            exit;
        }
        $ss = json_decode($result['body'],true);
        if(!isset($ss['access_token']))
        {
            $this->logFile('No access_token para:\r\n'.json_encode($result));
            //wp_die('$result:'.$result);
            $this->hillstone_oauth_redirect();
            exit;
            //add_action('login_form', 'hillstone_failed_login_message');
        }
        $user_info = json_decode($this->hillstone_get_userinfo($ss['access_token']),true);
        $hillstone_usertype=$user_info["UserType"];
        if(count($this->allowed_usertypes)>0 && !in_array($hillstone_usertype, $this->allowed_usertypes))
        {
            //$this->logFile('hillstone_no_permision_login_message');
            $this->remove_hillstone_auth($ss['access_token']);
            wp_die($this->hillstone_no_permision_login_message());
            //add_action('login_form', array($this, 'hillstone_no_permision_login_message'));
        }
        else
        {
            $hillstone_userid = $user_info["UserID"];
            $user_id=0;
            $oauth_user = get_users(array("meta_key"=>"hillstone_userid","meta_value"=>$hillstone_userid));
            if(is_wp_error($oauth_user) || !count($oauth_user)){
                $oauth_user = get_user_by( 'email',$user_info["Email"]);
                if(!$oauth_user) {
                    $display_name    = $user_info["DisplayName"];
                    $nick_name       = $user_info["DisplayName"];
                    $login_name      = $user_info["UserName"] . '@sso';
                    $random_password = wp_generate_password( $length = 12, $include_standard_special_chars = false );
                    $userdata        = array(
                        'user_login'   => $login_name,
                        'display_name' => $display_name,
                        'user_pass'    => $random_password,
                        'nickname'     => $user_info["DisplayName"],
                        'user_email' => $user_info["Email"]
                    );
                    $user_id = wp_insert_user( $userdata );
                    $user = new WP_User($user_id);
                    $user_role = 'subscriber';
                    if($user_info["UserType"]===0){
                        $user_role='contributor';
                    }
	                $user->set_role( $user_role );
                    update_user_meta($user_id ,"company_name",$user_info['CompanyName']);
                    update_user_meta($user_id ,"first_name",$user_info['FirstName']);
                    update_user_meta($user_id ,"last_name",$user_info['LastName']);
                    update_user_meta( $user_id, "hillstone_userid", $hillstone_userid );
                    wp_signon( array( "user_login" => $login_name, "user_password" => $random_password ), false );
                }
                else
                {
                    $user_id=$oauth_user->ID;
                    update_user_meta( $user_id, "hillstone_userid", $hillstone_userid );
                }
            }else{
                $user_id=$oauth_user[0]->ID;
                //$this->logFile('$user_id:'.$user_id);
            }
            if($user_id!=0)
            {
                update_user_meta($user_id ,"hillstone_access_token",$ss['access_token']);
                update_user_meta($user_id ,"hillstone_expires_time",time() + ($ss['expires_in']-5));
                update_user_meta($user_id ,"hillstone_refresh_token",$ss['refresh_token']);
                wp_set_auth_cookie($user_id);                
                $redirect_to=home_url();
                if(isset($_GET['redirect_to'])){
                    $redirect_to=urldecode( $_GET['redirect_to']);
                }
                @header( 'Location: ' . $redirect_to );
            }
        }
        exit;
        //return $result;
    }
	

    function hillstone_get_userinfo($access_token)
    {
        $url=$this->get_setting('usercenter_base_url').'/API/Resource/UserInfo';

        $headers = array ('Authorization'=>'Bearer '. $access_token);
        $request=new WP_Http();
        $result=$request->request($url,array('method'=>'GET','timeout'=>60, 'headers' => $headers,'sslverify' => true,'user-agent'=>'Hillstone-WP-SSO '.$this->version));
        //$this->logFile('hillstone_get_userinfo:'.json_encode($result));
        return $result['body'];
    }
    function check_logout_token($logout_token)
    {
        $url=$this->get_setting('usercenter_base_url').'/OAuth/CheckToken?token='.$logout_token;

        $headers = array ( 'Authorization'=>'Bearer '. $this->get_hillstone_accesstoken());
        $request=new WP_Http();
        $result=$request->request($url,array('method'=>'GET','timeout'=>60, 'headers' => $headers,'sslverify' => true,'user-agent'=>'Hillstone-WP-SSO '.$this->version));
        $ss = json_decode($result['body'],true);
        if (!isset($ss['code']))
        {
            return 'false';
        }
        if ($ss['code']!='0')
        {
            return 'false';
        }
        return  'true';
    }
    function hillstone_no_permision_login_message() {
        //echo $this->remove_hillstone_auth($this->temp_access_token);
        echo '<p id="error_msg"><span style="color:#E53333;"><strong>'.__('I\'m sorry, you have no right to sign in.','hillstone-wp-sso-lang').'</strong></span></p>';
    }
    function hillstone_failed_login_message() {
        echo '<p id="error_msg"><span style="color:#E53333;"><strong>'.__('Logon failure.','hillstone-wp-sso-lang').'</strong></span></p>';
    }
    
    /**
     * @desc Enqueue's the CSS for the specified theme.
     */
    function login_css() {
        wp_enqueue_style('hillstone-sso-modal-login-form', $this->pluginurl . "css/login.css", false, $this->version, 'screen');
        wp_enqueue_style('bootstrap', "https://cdn.jsdelivr.net/npm/bootstrap@3.3.6/dist/css/bootstrap.min.css");
        wp_enqueue_style('AdminLTE', "https://cdn.jsdelivr.net/npm/adminlte@2.4.1/dist/css/AdminLTE.min.css");
    }
    // function hillstone_sso_style(){
    //     wp_enqueue_style('hillstone-sso-style', $this->pluginurl . "css/style.css", false, $this->version, 'screen');
    // }
    /**
     * @desc loginout filter that adds the simplemodal-login class to the "Log In" link
     * @return string
     */
    function login_loginout($link) {
        if (!is_user_logged_in()) {
            $link = str_replace('href=', 'class="hillstone-sso-modal-login" href=', $link);
        }
        return $link;
    }
    /**
     * @desc Builds the login, registration, and password reset form HTML.
     * Calls filters for each form, then echo's the output.
     */
    // function login_footer() {
    //     include 'ModalLogin.php';
    // }

    function logFile($msg){
        $filename=$this->pluginpath.date("Ymd",time()).'.log';
        //打开文件
        $fd = fopen($filename,"a");
        //增加文件
        $str = "[".date("Y/m/d h:i:s",time())."]".$msg;
        //写入字符串
        fwrite($fd, $str."\n");
        //关闭文件
        fclose($fd);
    }
    function get_settings_obj () {
        return get_option("{$this->prefix}settings", false);
    }
    function set_settings_obj ( $newobj ) {
        return update_option("{$this->prefix}settings", $newobj);
    }
    function set_setting ( $option = false, $newvalue ) {
        if( $option === false ) return false;

        $this->settings = $this->get_settings_obj($this->prefix);
        $this->settings[$option] = $newvalue;
        return $this->set_settings_obj($this->settings);
    }
    function get_setting ( $option = false ) {
        if($option === false || ! isset($this->settings[$option]) ) return false;

        return apply_filters($this->prefix . 'get_setting', $this->settings[$option], $option);
    }
    function add_setting ( $option = false, $newvalue ) {
        if($option === false ) return false;

        if ( ! isset($this->settings[$option]) ) {
            return $this->set_setting($option, $newvalue);
        } else return false;
    }
    function get_field_name($setting, $type = 'string') {
        return "{$this->prefix}setting[$setting][$type]";
    }
    function save_settings()
    {
        if( isset($_REQUEST["{$this->prefix}setting"]) && check_admin_referer('save_hillstone_sso_settings','save_the_hillstone_sso') ) {
            $new_settings = $_REQUEST["{$this->prefix}setting"];

            foreach( $new_settings as $setting_name => $setting_value  ) {
                foreach( $setting_value as $type => $value ) {
                    if( $type == "array" ) {
                        $this->set_setting($setting_name, explode(";", $value));
                    } else {
                        $this->set_setting($setting_name, $value);
                    }
                }
            }

            add_action('admin_notices', array($this, 'saved_admin_notice') );
        }
    }
    function saved_admin_notice(){
        echo '<div class="updated">
	       <p>Hillstone SSO settings have been saved.</p>
	    </div>';

        if( ! str_true($this->get_setting('enabled')) ) {
            echo '<div class="error">
				<p>Hillstone SSO is disabled.</p>
			</div>';
        }
    }
}
if ( ! function_exists('str_true') ) {
    function str_true ( $string, $istrue = array('yes', 'y', 'true','1','on','open') ) {
        if (is_array($string)) return false;
        if (is_bool($string)) return $string;
        return in_array(strtolower($string),$istrue);
    }
}
$HillstoneSSO = HillstoneSSO::getInstance();