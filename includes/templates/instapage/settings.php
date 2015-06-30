<script type="text/javascript">
	var instapage_post_type_area = true;
</script>
<div class="bootstrap-wpadmin">
	<div class="wrap">
		<div id="icon-options-general" class="icon32"><br></div>
		<h2>Instapage Settings</h2>

		<div class="login-form instapage-form">

			<div class="login-box">
				<?php if( $error ): ?>
					<div class="error"><?php echo $error; ?></div>
				<?php endif; ?>

				<form method="post" action="<?php echo admin_url( 'options-general.php?page='. $plugin_file ); ?>">
					<?php if( !$user_id ): ?>
						<h3>Please log into your Instapage account.</h3>
						<input type="hidden" name="instapage_meta_box_nonce" value="<?php echo wp_create_nonce(basename(__FILE__)) ?>" />
						<div class="row-fluid form-horizontal">
							<div>
								<input type="text" name="email" placeholder="Instapage User Email" />
							</div>
							<div>
								<input type="password" name="password" placeholder="Instapage Password" />
							</div>

							<div>
								<input type="submit" class="button button-primary" value="Login" />
							</div>
						</div>
					<?php else: ?>
						<?php if( $user ): ?>
							<h3>You are logged in as <?php echo $user; ?></h3>
						<?php else: ?>
							<h3>You are not properly connected</h3>
							<p>Please click 'disconnect' and connect again. It is safe process, your pages will keep working.</p>
						<?php endif; ?>
						<input type="hidden" name="action" value="disconnect" />
						<input type="submit" class="button button-primary" value="Disconnect" />
					<?php endif; ?>
				</form>
			</div>

			<?php if( !$user_id ): ?>
				<div class="facebook-twitter-message">
					For accounts created using Facebook or Twitter you'll first need to add a password to your account before using
					the WordPress plugin: <a href="https://app.instapage.com/account" target="_blank">https://app.instapage.com/account</a>
				</div>
			<?php endif; ?>

			<div style="clear: both"></div>
		</div>

		<?php if( $user ): ?>
			<div class="instapage-form">
				<div style="clear: both"></div><p><hr></p>
				<h3>Cross-origin proxy services <?php echo $cross_origin_proxy_services; ?></h3>
				<form method="post" action="<?php echo admin_url( 'options-general.php?page='. $plugin_file ); ?>">
					<p>
						<input type="hidden" name="action" value="cross_origin_proxy_services" />
						<input type="checkbox" name="cross_origin_proxy_services" value="1" id="cross_origin_proxy_services" <?php if ( $cross_origin_proxy_services ): ?>checked="checked"<?php endif; ?> />
						<span>Uncheck this if you have problems with sending submissions from landing page</span>
					</p>
					<p>
						<input type="submit" class="button button-primary" value="Save" />
					</p>
				</form>
			</div>
		<?php endif; ?>
	</div>
</div>

<style type="text/css">
.instapage-form
{
	padding: 20px 0;
}

.instapage-form input
{
	margin-bottom: 5px;
}

.login-box
{
	width: 300px;
	padding-right: 10px;
	float: left;
}

.facebook-twitter-message
{
	padding: 10px;
	width: 400px;
	float: left;
}

</style>

<div>
	<hr />
	<a href="http://app.instapage.com/dashboard" target="_blank">Manage your Instapage account</a>
</div>
