<?php

// WPCOM specific file for enabling Gutenber on P2
// various settings and overrides are needed to work
// with WPCOM API and front-end editing

/**
 * Gutenberg only attaches itself on admin enqueue, so need to call
 * same enqueue scripts for front-end editing.
 *
 * TODO: probably only want to call on page with editor
 */
add_action( 'wp_enqueue_scripts', 'gutenberg_editor_scripts_and_styles' );


/**
 * Style for P2 which does not have default white page,
 * maybe should be included in Gutenberg ?
 */
function wpcom_gutenberg_style() {
	echo '<style> .gutenberg { background-color: white; } </style>';
}
add_action( 'wp_head', 'wpcom_gutenberg_style' );


/**
 * Set REST API URL, since WPCOM blogs call out to single
 * location and not to relative URLs ie. /wp-json/
 */
function wpcom_set_rest_url( $url, $a, $b, $c ) {
    print_r( func_get_args() );
    die();
	return 'https://public-api.wordpress.com/wp/v2/sites/';
}
add_filter( 'rest_url', 'wpcom_set_rest_url', 10, 4 );


/**
 * Set REST API URL prefix
 * This should probably include something like /sites/48514416
 */
function wpcom_set_rest_url_prefix( $prefix ) {
	return '';
}
add_filter( 'rest_url_prefix', 'wpcom_set_rest_url_prefix' );

/**
 * HACK to get post data instead of calling API
 */
function gutenberg_hack_post_into_rest_response( $post ) {
    return array (
        'id' => $post->ID,
         'date' => $post->post_date,
        'date_gmt' => $post->post_date_gmt,
        'guid' => array (
            'rendered' => $post->guid,
            'raw' => $post->guid,
        ),
        'modified' => $post->post_modified,
        'modified_gmt' => $post->post_modified_gmt,
        'password' => $post->post_password,
        'slug' => '',
        'status' => $post->post_status,
        'type' => $post->post_type,
        'link' => $post->guid,
        'title' => array (
            'raw' => $post->post_title,
            'rendered' => $post->post_title,
        ),
        'content' => array (
            'raw' => $post->post_content,
            'rendered' => $post->post_content,
            'protected' => false,
        ),
        'excerpt' => array (
            'raw' => $post->post_excerpt,
            'rendered' => $post->post_excerpt,
            'protected' => false,
        ),
        'author' => $post->post_author,
        //'featured_media' => 0,
        'comment_status' => $post->comment_status,
        'ping_status' => $post->ping_status,
        'sticky' => false,
        'template' => '',
        'format' => 'aside',
        'meta' => array (),
        'categories' =>  array (),
        'tags' =>  array (
        ),
    );
}

