<?php
/**
 * Created by PhpStorm.
 * User: shramee
 * Date: 30/4/15
 * Time: 10:43 PM
 */

// fixing template to loop.php and post type to post
$instance['template'] = 'loop.php';
$instance['post_type'] = 'post';

if ( empty( $instance['template'] ) ) return;

if ( is_admin() ) return;

$template = $instance['template'];
$query_args = $instance;
unset( $query_args['template'] );
unset( $query_args['additional'] );
unset( $query_args['sticky'] );
unset( $query_args['title'] );

$query_args = wp_parse_args( $instance['additional'], $query_args );

global $wp_rewrite;

if ( $wp_rewrite->using_permalinks() ) {

	if ( get_query_var( 'paged' ) ) {
		// When the widget appears on a sub page.
		$query_args['paged'] = get_query_var( 'paged' );
	}
	elseif ( strpos( $_SERVER['REQUEST_URI'], '/page/' ) !== false ) {
		// When the widget appears on the home page.
		preg_match( '/\/page\/( [0-9]+ )\//', $_SERVER['REQUEST_URI'], $matches );
		if ( ! empty( $matches[1] ) ) $query_args['paged'] = intval( $matches[1] );
		else $query_args['paged'] = 1;
	}
	else $query_args['paged'] = 1;
}
else {
	// Get current page number when we're not using permalinks
	$query_args['paged'] = isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1;
}

switch( $instance['sticky'] ) {
	case 'ignore' :
		$query_args['ignore_sticky_posts'] = 1;
		break;
	case 'only' :
		$query_args['post__in'] = get_option( 'sticky_posts' );
		break;
	case 'exclude' :
		$query_args['post__not_in'] = get_option( 'sticky_posts' );
		break;
}

// Exclude the current post to prevent possible infinite loop

global $siteorigin_panels_current_post;

if ( ! empty( $siteorigin_panels_current_post ) ) {
	if ( ! empty( $query_args['post__not_in'] ) ) {
		$query_args['post__not_in'][] = $siteorigin_panels_current_post;
	}
	else {
		$query_args['post__not_in'] = array( $siteorigin_panels_current_post );
	}
}

if ( ! empty( $query_args['post__in'] ) && ! is_array( $query_args['post__in'] ) ) {
	$query_args['post__in'] = explode( ',', $query_args['post__in'] );
	$query_args['post__in'] = array_map( 'intval', $query_args['post__in'] );
}

// Create the query
query_posts( $query_args );
echo $args['before_widget'];

// Filter the title
$instance['title'] = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );
if ( ! empty( $instance['title'] ) ) {
	echo $args['before_title'] . $instance['title'] . $args['after_title'];
}

$displayPostMore = isset( $instance['continue_reading_enable'] ) && $instance['continue_reading_enable'] == true;
$isInitialPostMore = has_action( 'woo_post_inside_after', 'woo_post_more' );

// hack to temporary add/remove post more
if ( $displayPostMore ) {
	if ( ! $isInitialPostMore ) {
		add_action( 'woo_post_inside_after', 'woo_post_more' );
	}
} else {
	if ( $isInitialPostMore ) {
		remove_action( 'woo_post_inside_after', 'woo_post_more' );
	}
}

$thumbnailEnable = isset( $instance['thumbnail_enable'] ) && $instance['thumbnail_enable'] == true;
$initialThumbnailEnable = get_option( 'pp_pb_post_loop_thumbnail_enable', '1' ) == true;
if ( $thumbnailEnable != $initialThumbnailEnable ) {
	update_option( 'pp_pb_post_loop_thumbnail_enable', $thumbnailEnable ? '1' : '0' );
}

$this->instance = $instance;
// add filter to further set other parameters
add_filter( 'woo_get_dynamic_values', array( $this, 'temporary_set_woo_settings' ) );

add_filter( 'excerpt_length', array( $this, 'filter_excerpt_length' ) );
//		apply_filters( 'excerpt_length', 55 );

add_filter( 'the_title', array( $this, 'filter_title' ), 10, 2 );

// hook for enable/disable pagination
add_action ( 'woo_loop_before', array( $this, 'loop_before' ) );

// hook for column count
//		add_action( 'get_header', array( $this, 'option_css' ) );

if ( strpos( '/'.$instance['template'], '/content' ) !== false ) {
	while( have_posts() ) {
		the_post();
		locate_template( $instance['template'], true, false );
	}
}
else {
	locate_template( $instance['template'], true, false );
}

remove_action ( 'woo_loop_before', array( $this, 'loop_before' ) );

remove_filter( 'the_title', array( $this, 'filter_title' ), 10, 2 );

remove_filter( 'excerpt_length', array( $this, 'filter_excerpt_length' ) );

remove_filter( 'woo_get_dynamic_values', array( $this, 'temporary_set_woo_settings' ) );

if ( $thumbnailEnable != $initialThumbnailEnable ) {
	update_option( 'pp_pb_post_loop_thumbnail_enable', $initialThumbnailEnable ? '1' : '0' );
}

if ( $displayPostMore ) {
	if ( ! $isInitialPostMore ) {
		remove_action( 'woo_post_inside_after', 'woo_post_more' );
	}
} else {
	if ( $isInitialPostMore ) {
		add_action( 'woo_post_inside_after', 'woo_post_more' );
	}
}

echo $args['after_widget'];

// Reset everything
wp_reset_query();