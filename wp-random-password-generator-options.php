<?php

if ( ! current_user_can( 'manage_options' ) )  {
	wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
}

wp_register_style( 'wp-random-password-generator-style', plugins_url( 'css/wp-random-password-generator-options.css' , __FILE__ ) );
wp_enqueue_style( 'wp-random-password-generator-style' );

?>

<div class="wrap">
<?php screen_icon(); ?>
<h2>WP Random Password Generator</h2>
<div style="overflow: hidden">
	<iframe src="http://random.org/quota?ip=<?php echo( $_SERVER['SERVER_ADDR'] ) ?>"></iframe>
</div>
</div>
