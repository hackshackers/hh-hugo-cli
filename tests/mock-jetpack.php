<?php
/**
 * Some Jetpack functions to make the test run more smoothly
 */
// Requires some Jetpack libs
require_once( HH_PLUGINS_DIR . '/jetpack/class.jetpack-post-images.php' );
require_once( HH_PLUGINS_DIR . '/jetpack/class.media-extractor.php' );
require_once( HH_PLUGINS_DIR . '/jetpack/functions.compat.php' );

function jetpack_shortcode_get_vimeo_id( $atts ) {
	if ( isset( $atts[0] ) ) {
		$atts[0] = trim( $atts[0], '=' );
		if ( is_numeric( $atts[0] ) )
			$id = (int) $atts[0];
		elseif ( preg_match( '|vimeo\.com/(\d+)/?$|i', $atts[0], $match ) )
			$id = (int) $match[1];
		elseif ( preg_match( '|player\.vimeo\.com/video/(\d+)/?$|i', $atts[0], $match ) )
			$id = (int) $match[1];
		return $id;
	}
	return 0;
}
