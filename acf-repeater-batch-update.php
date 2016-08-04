<?php
/*
Plugin Name: ACF Repeater Batch Update
Plugin URI: https://github.com/mchestnut/acf-repeater-batch-update
Description: Modifies the ACF Repeater addon to batch update queries for performance improvement
Version: 1.0.1
Author: Matt Chestnut
Author URI: http://www.mattchestnut.com
License: GNU GPLv2
Copyright: Matt Chestnut
*/

// Only include once
if ( ! function_exists( 'acf_modify_repeater_field' ) ) {

	function acf_modify_repeater_field() {
		include_once( 'repeater-batch-update.php' );
	}

	// Low priority to be called after acf finishes loading
	add_action( 'acf/register_fields', 'acf_modify_repeater_field', 15 );
}

?>