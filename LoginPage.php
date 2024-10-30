<div class="login-box" id="login-box-oauth" style="min-width: 260px; width: auto; opacity: 0.8; display: block">

	<div class="login-logo">
		<b style="color:white; font-size: 20px; "><?php echo get_bloginfo('name') ?></b>
	</div>

	<div class="row">
		<div class="col-xs-12" style="margin-bottom: 10px;">
			<input type="hidden" name="_token" value="7oxdiMV8SApw5eglxTagyQOxH3gYbQPdGma2sj3M">
			<a href="<?php echo home_url().'/wp-login.php?action=jump&redirect_to='.$redirect_to?>"
				class="btn btn-primary btn-block btn-flat"><?php echo __('Login With StoneID','hillstone-wp-sso-lang') ?></a>
		</div>
	</div>
</div>