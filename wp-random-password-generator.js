/**
 * Scripting for the "Generate Password" button
 * @package WordPress
 * @subpackage WP Random Password Generator
 * @author Robert Paprocki
 */

jQuery( function ( $ ) {
	"use strict";

	// Generate Password button
	$( '#pass-strength-result' ).before( '<br /><button id="password_generator" class="button-primary" value=" Generate Password " role="button">Generate Password</button><br />' );
	// spinning ajax loader gif
	$( '#password_generator' ).after( '<img src="' + wpRandomPasswordGenerator.plugin_dir + '/inc/ajax-loader.gif" id="loader" style="display: none;"" />' );
	// Show Password link
	$( '#loader' ).after( '<span id="password_generator_toggle" style="margin-left:.25em;"><a href="#" role="button">Show Password</a></span>' );

	// bind the click event to the 'Show Password' div
	$( '#password_generator_toggle' ).on( 'click', 'a', function() {
		if ( $('#pass1').val() != "" ) {
			$( '#password_generator_toggle' ).fadeOut( 175, function() {
				$( this ).html( '<kbd style="font-size:1.2em;">' + $('#pass1').val() + '</kbd>' );
			}).fadeIn( 175 );
		}
		return false;
	});

	// bind the click event on the 'Generate Password' button
	$( '#password_generator' ).on( 'click', function () {
		$( '#password_generator' ).attr( 'disabled', true );
		$( '#password_generator_toggle' ).fadeOut( 175, function() {
			$( '#loader' ).fadeIn( 175 );
		});
		var start = new Date(); // debug timer
		$.post(
			ajaxurl, 
			{
				action: 'generate_password' 
			},
			function( p ) {
				var res = jQuery.parseJSON( p );
				res.time.execution = new Date() - start;
				if ( res.debug ) {
					console.log( res );
				}
			if ( res.status == -1 ) { // checks for an error from a Random.org PHP API exception. 
				alert( "An error has occured while using the Random.org API: " + res.result + "\n\nIf you continue to see this alert, try disabling the use of the Random.org API from the plugin settings page." );
				$( '#pass1, #pass2' ).val( '' );
			} else {
				$( '#pass1, #pass2' ).val( res.result ).trigger( 'keyup' );
				$( '#password_generator_toggle' ).html( '<a href="#" role="button">Show Password</a>' );
				$( '#send_password' ).prop( 'checked', 'checked' );
			}
			$('#loader').fadeOut(175, function() { 
				$( '#password_generator_toggle' ).fadeIn( 175 );
				$( '#password_generator' ).removeAttr( 'disabled' );
			});
		});
		return false;
	});
});
