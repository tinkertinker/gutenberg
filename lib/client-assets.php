<?php
/**
 * Functions to register client-side assets (scripts and stylesheets) for the
 * Gutenberg editor plugin.
 *
 * @package gutenberg
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Silence is golden.' );
}

/**
 * Retrieves the root plugin path.
 *
 * @return string Root path to the gutenberg plugin.
 *
 * @since 0.1.0
 */
function gutenberg_dir_path() {
	return plugin_dir_path( dirname( __FILE__ ) );
}

/**
 * Retrieves a URL to a file in the gutenberg plugin.
 *
 * @param  string $path Relative path of the desired file.
 *
 * @return string       Fully qualified URL pointing to the desired file.
 *
 * @since 0.1.0
 */
function gutenberg_url( $path ) {
	return plugins_url( $path, dirname( __FILE__ ) );
}

/**
 * Returns contents of an inline script used in appending polyfill scripts for
 * browsers which fail the provided tests. The provided array is a mapping from
 * a condition to verify feature support to its polyfill script handle.
 *
 * @param array $tests Features to detect.
 * @return string Conditional polyfill inline script.
 */
function gutenberg_get_script_polyfill( $tests ) {
	global $wp_scripts;

	$polyfill = '';
	foreach ( $tests as $test => $handle ) {
		if ( ! array_key_exists( $handle, $wp_scripts->registered ) ) {
			continue;
		}

		$polyfill .= (
			// Test presence of feature...
			'( ' . $test . ' ) || ' .
			// ...appending polyfill on any failures. Cautious viewers may balk
			// at the `document.write`. Its caveat of synchronous mid-stream
			// blocking write is exactly the behavior we need though.
			'document.write( \'<script src="' .
			esc_url( $wp_scripts->registered[ $handle ]->src ) .
			'"></scr\' + \'ipt>\' );'
		);
	}

	return $polyfill;
}

/**
 * Registers common scripts and styles to be used as dependencies of the editor
 * and plugins.
 *
 * @since 0.1.0
 */
function gutenberg_register_scripts_and_styles() {
	gutenberg_register_vendor_scripts();

	// Editor Scripts.
	wp_register_script(
		'wp-utils',
		gutenberg_url( 'utils/build/index.js' ),
		array(),
		filemtime( gutenberg_dir_path() . 'utils/build/index.js' )
	);
	wp_register_script(
		'wp-date',
		gutenberg_url( 'date/build/index.js' ),
		array( 'moment' ),
		filemtime( gutenberg_dir_path() . 'date/build/index.js' )
	);
	global $wp_locale;
	wp_add_inline_script( 'wp-date', 'window._wpDateSettings = ' . wp_json_encode( array(
		'l10n'     => array(
			'locale'        => get_locale(),
			'months'        => array_values( $wp_locale->month ),
			'monthsShort'   => array_values( $wp_locale->month_abbrev ),
			'weekdays'      => array_values( $wp_locale->weekday ),
			'weekdaysShort' => array_values( $wp_locale->weekday_abbrev ),
			'meridiem'      => (object) $wp_locale->meridiem,
			'relative'      => array(
				/* translators: %s: duration */
				'future' => __( '%s from now', 'default' ),
				/* translators: %s: duration */
				'past'   => __( '%s ago', 'default' ),
			),
		),
		'formats'  => array(
			'time'     => get_option( 'time_format', __( 'g:i a', 'default' ) ),
			'date'     => get_option( 'date_format', __( 'F j, Y', 'default' ) ),
			'datetime' => __( 'F j, Y g:i a', 'default' ),
		),
		'timezone' => array(
			'offset' => get_option( 'gmt_offset', 0 ),
			'string' => get_option( 'timezone_string', 'UTC' ),
		),
	) ), 'before' );
	wp_register_script(
		'wp-i18n',
		gutenberg_url( 'i18n/build/index.js' ),
		array(),
		filemtime( gutenberg_dir_path() . 'i18n/build/index.js' )
	);
	wp_register_script(
		'wp-element',
		gutenberg_url( 'element/build/index.js' ),
		array( 'react', 'react-dom', 'react-dom-server' ),
		filemtime( gutenberg_dir_path() . 'element/build/index.js' )
	);
	wp_register_script(
		'wp-components',
		gutenberg_url( 'components/build/index.js' ),
		array( 'wp-element', 'wp-i18n', 'wp-utils', 'wp-api-request' ),
		filemtime( gutenberg_dir_path() . 'components/build/index.js' )
	);
	wp_register_script(
		'wp-blocks',
		gutenberg_url( 'blocks/build/index.js' ),
		array( 'wp-element', 'wp-components', 'wp-utils', 'wp-i18n', 'tinymce-latest', 'tinymce-latest-lists', 'tinymce-latest-paste', 'tinymce-latest-table', 'media-views', 'media-models' ),
		filemtime( gutenberg_dir_path() . 'blocks/build/index.js' )
	);
	wp_add_inline_script(
		'wp-blocks',
		gutenberg_get_script_polyfill( array(
			'\'Promise\' in window' => 'promise',
			'\'fetch\' in window'   => 'fetch',
		) ),
		'before'
	);

	// Editor Styles.
	wp_register_style(
		'wp-components',
		gutenberg_url( 'components/build/style.css' ),
		array(),
		filemtime( gutenberg_dir_path() . 'components/build/style.css' )
	);
	wp_register_style(
		'wp-blocks',
		gutenberg_url( 'blocks/build/style.css' ),
		array(),
		filemtime( gutenberg_dir_path() . 'blocks/build/style.css' )
	);
	wp_register_style(
		'wp-edit-blocks',
		gutenberg_url( 'blocks/build/edit-blocks.css' ),
		array(),
		filemtime( gutenberg_dir_path() . 'blocks/build/edit-blocks.css' )
	);
}
add_action( 'init', 'gutenberg_register_scripts_and_styles' );

/**
 * Append result of internal request to REST API for purpose of preloading
 * data to be attached to the page. Expected to be called in the context of
 * `array_reduce`.
 *
 * @param  array  $memo Reduce accumulator.
 * @param  string $path REST API path to preload.
 * @return array        Modified reduce accumulator.
 */
function gutenberg_preload_api_request( $memo, $path ) {
	if ( empty( $path ) ) {
		return $memo;
	}

	$path_parts = parse_url( $path );
	if ( false === $path_parts ) {
		return $memo;
	}

	$request = new WP_REST_Request( 'GET', $path_parts['path'] );
	if ( ! empty( $path_parts['query'] ) ) {
		parse_str( $path_parts['query'], $query_params );
		$request->set_query_params( $query_params );
	}

	$response = rest_do_request( $request );
	if ( 200 === $response->status ) {
		$memo[ $path ] = array(
			'body'    => $response->data,
			'headers' => $response->headers,
		);
	}

	return $memo;
}

/**
 * Registers vendor JavaScript files to be used as dependencies of the editor
 * and plugins.
 *
 * This function is called from a script during the plugin build process, so it
 * should not call any WordPress PHP functions.
 *
 * @since 0.1.0
 */
function gutenberg_register_vendor_scripts() {
	$suffix = SCRIPT_DEBUG ? '' : '.min';

	// Vendor Scripts.
	$react_suffix = ( SCRIPT_DEBUG ? '.development' : '.production' ) . $suffix;

	gutenberg_register_vendor_script(
		'react',
		'https://unpkg.com/react@16.0.0/umd/react' . $react_suffix . '.js'
	);
	gutenberg_register_vendor_script(
		'react-dom',
		'https://unpkg.com/react-dom@16.0.0/umd/react-dom' . $react_suffix . '.js',
		array( 'react' )
	);
	gutenberg_register_vendor_script(
		'react-dom-server',
		'https://unpkg.com/react-dom@16.0.0/umd/react-dom-server.browser' . $react_suffix . '.js',
		array( 'react' )
	);
	$moment_script = SCRIPT_DEBUG ? 'moment.js' : 'min/moment.min.js';
	gutenberg_register_vendor_script(
		'moment',
		'https://unpkg.com/moment@2.18.1/' . $moment_script,
		array( 'react' )
	);
	$tinymce_version = '4.7.1';
	gutenberg_register_vendor_script(
		'tinymce-latest',
		'https://fiddle.azurewebsites.net/tinymce/' . $tinymce_version . '/tinymce' . $suffix . '.js'
	);
	gutenberg_register_vendor_script(
		'tinymce-latest-lists',
		'https://fiddle.azurewebsites.net/tinymce/' . $tinymce_version . '/plugins/lists/plugin' . $suffix . '.js',
		array( 'tinymce-latest' )
	);
	gutenberg_register_vendor_script(
		'tinymce-latest-paste',
		'https://fiddle.azurewebsites.net/tinymce/' . $tinymce_version . '/plugins/paste/plugin' . $suffix . '.js',
		array( 'tinymce-latest' )
	);
	gutenberg_register_vendor_script(
		'tinymce-latest-table',
		'https://fiddle.azurewebsites.net/tinymce/' . $tinymce_version . '/plugins/table/plugin' . $suffix . '.js',
		array( 'tinymce-latest' )
	);
	gutenberg_register_vendor_script(
		'fetch',
		'https://unpkg.com/whatwg-fetch/fetch.js'
	);
	gutenberg_register_vendor_script(
		'promise',
		'https://unpkg.com/promise-polyfill/promise' . $suffix . '.js'
	);

	// TODO: This is only necessary so long as WordPress 4.9 is not yet stable,
	// since we depend on the newly-introduced wp-api-request script handle.
	//
	// See: gutenberg_ensure_wp_api_request (compat.php).
	gutenberg_register_vendor_script(
		'wp-api-request-shim',
		'https://raw.githubusercontent.com/WordPress/wordpress-develop/master/src/wp-includes/js/api-request.js'
	);
}

/**
 * Retrieves a unique and reasonably short and human-friendly filename for a
 * vendor script based on a URL.
 *
 * @param  string $src Full URL of the external script.
 *
 * @return string      Script filename suitable for local caching.
 *
 * @since 0.1.0
 */
function gutenberg_vendor_script_filename( $src ) {
	$filename = basename( $src );
	$hash     = substr( md5( $src ), 0, 8 );

	$match = preg_match(
		'/^'
		. '(?P<prefix>.*?)'
		. '(?P<ignore>\.development|\.production)?'
		. '(?P<suffix>\.min)?'
		. '(?P<extension>\.js)'
		. '(?P<extra>.*)'
		. '$/',
		$filename,
		$filename_pieces
	);

	if ( ! $match ) {
		return "$filename.$hash.js";
	}

	$match = preg_match(
		'@tinymce.*/plugins/([^/]+)/plugin(\.min)?\.js$@',
		$src,
		$tinymce_plugin_pieces
	);
	if ( $match ) {
		$filename_pieces['prefix'] = 'tinymce-plugin-' . $tinymce_plugin_pieces[1];
	}

	return $filename_pieces['prefix'] . $filename_pieces['suffix']
		. '.' . $hash
		. $filename_pieces['extension'];
}

/**
 * Given a REST data response with links, returns the href value of a specified
 * link relation with optional context.
 *
 * @since 0.10.0
 *
 * @param  array  $data    REST response data.
 * @param  string $link    Link relation.
 * @param  string $context Optional context to append.
 * @return string          Link relation URI.
 */
function gutenberg_get_rest_link( $data, $link, $context = null ) {
	// Check whether a link entry with href exists.
	if ( empty( $data['_links'] ) || empty( $data['_links'][ $link ] ) ||
			! isset( $data['_links'][ $link ][0]['href'] ) ) {
		return;
	}

	$href = $data['_links'][ $link ][0]['href'];

	// Strip API root prefix.
	$api_root = untrailingslashit( get_rest_url() );
	if ( 0 === strpos( $href, $api_root ) ) {
		$href = substr( $href, strlen( $api_root ) );
	}

	// Add optional context.
	if ( ! is_null( $context ) ) {
		$href = add_query_arg( 'context', $context, $href );
	}

	return $href;
}

/**
 * Registers a vendor script from a URL, preferring a locally cached version if
 * possible, or downloading it if the cached version is unavailable or
 * outdated.
 *
 * @param  string $handle Name of the script.
 * @param  string $src    Full URL of the external script.
 * @param  array  $deps   Optional. An array of registered script handles this
 *                        script depends on.
 *
 * @since 0.1.0
 */
function gutenberg_register_vendor_script( $handle, $src, $deps = array() ) {
	if ( defined( 'GUTENBERG_LOAD_VENDOR_SCRIPTS' ) && ! GUTENBERG_LOAD_VENDOR_SCRIPTS ) {
		return;
	}

	$filename = gutenberg_vendor_script_filename( $src );

	if ( defined( 'GUTENBERG_LIST_VENDOR_ASSETS' ) && GUTENBERG_LIST_VENDOR_ASSETS ) {
		echo "$src|$filename\n";
		return;
	}

	$full_path = gutenberg_dir_path() . 'vendor/' . $filename;

	$needs_fetch = (
		defined( 'GUTENBERG_DEVELOPMENT_MODE' ) && GUTENBERG_DEVELOPMENT_MODE && (
			! file_exists( $full_path ) ||
			time() - filemtime( $full_path ) >= DAY_IN_SECONDS
		)
	);

	if ( $needs_fetch ) {
		// Determine whether we can write to this file.  If not, don't waste
		// time doing a network request.
		// @codingStandardsIgnoreStart
		$f = @fopen( $full_path, 'a' );
		// @codingStandardsIgnoreEnd
		if ( ! $f ) {
			// Failed to open the file for writing, probably due to server
			// permissions.  Enqueue the script directly from the URL instead.
			wp_register_script( $handle, $src, $deps, null );
			return;
		}
		fclose( $f );
		$response = wp_remote_get( $src );
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			// The request failed; just enqueue the script directly from the
			// URL.  This will probably fail too, but surfacing the error to
			// the browser is probably the best we can do.
			wp_register_script( $handle, $src, $deps, null );
			// If our file was newly created above, it will have a size of
			// zero, and we need to delete it so that we don't think it's
			// already cached on the next request.
			if ( ! filesize( $full_path ) ) {
				unlink( $full_path );
			}
			return;
		}
		$f = fopen( $full_path, 'w' );
		fwrite( $f, wp_remote_retrieve_body( $response ) );
		fclose( $f );
	}

	wp_register_script(
		$handle,
		gutenberg_url( 'vendor/' . $filename ),
		$deps,
		null
	);
}

/**
 * Extend wp-api Backbone client with methods to look up the REST API endpoints for all post types.
 *
 * This is temporary while waiting for #41111 in core.
 *
 * @link https://core.trac.wordpress.org/ticket/41111
 */
function gutenberg_extend_wp_api_backbone_client() {
	// Post Types Mapping.
	$post_type_rest_base_mapping = array();
	foreach ( get_post_types( array(), 'objects' ) as $post_type_object ) {
		$rest_base = ! empty( $post_type_object->rest_base ) ? $post_type_object->rest_base : $post_type_object->name;
		$post_type_rest_base_mapping[ $post_type_object->name ] = $rest_base;
	}

	// Taxonomies Mapping.
	$taxonomy_rest_base_mapping = array();
	foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy_object ) {
		$rest_base = ! empty( $taxonomy_object->rest_base ) ? $taxonomy_object->rest_base : $taxonomy_object->name;
		$taxonomy_rest_base_mapping[ $taxonomy_object->name ] = $rest_base;
	}

	$script  = sprintf( 'wp.api.postTypeRestBaseMapping = %s;', wp_json_encode( $post_type_rest_base_mapping ) );
	$script .= sprintf( 'wp.api.taxonomyRestBaseMapping = %s;', wp_json_encode( $taxonomy_rest_base_mapping ) );
	$script .= <<<JS
		wp.api.getPostTypeModel = function( postType ) {
			var route = '/' + wpApiSettings.versionString + this.postTypeRestBaseMapping[ postType ] + '/(?P<id>[\\\\d]+)';
			return _.find( wp.api.models, function( model ) {
				return model.prototype.route && route === model.prototype.route.index;
			} );
		};
		wp.api.getPostTypeRevisionsCollection = function( postType ) {
			var route = '/' + wpApiSettings.versionString + this.postTypeRestBaseMapping[ postType ] + '/(?P<parent>[\\\\d]+)/revisions';
			return _.find( wp.api.collections, function( model ) {
				return model.prototype.route && route === model.prototype.route.index;
			} );
		};
		wp.api.getTaxonomyModel = function( taxonomy ) {
			var route = '/' + wpApiSettings.versionString + this.taxonomyRestBaseMapping[ taxonomy ] + '/(?P<id>[\\\\d]+)';
			return _.find( wp.api.models, function( model ) {
				return model.prototype.route && route === model.prototype.route.index;
			} );
		};
		wp.api.getTaxonomyCollection = function( taxonomy ) {
			var route = '/' + wpApiSettings.versionString + this.taxonomyRestBaseMapping[ taxonomy ];
			return _.find( wp.api.collections, function( model ) {
				return model.prototype.route && route === model.prototype.route.index;
			} );
		};
JS;
	wp_add_inline_script( 'wp-api', $script );

	// Localize the wp-api settings and schema.
	// $request = new WP_REST_Request( '/wp/v2/sites/48514416/' );
	// $request->
	// $request->set_header( 'Host', 'public-api.wordpress.com');
	// $schema_response = rest_do_request( $request );
	// if ( ! $schema_response->is_error() ) {
		$schema_data =<<< EOF
{"namespace":"wp\/v2/sites/48514416","routes":{"\/wp\/v2/sites/48514416":{"namespace":"wp\/v2/sites/48514416","methods":["GET"],"endpoints":[{"methods":["GET"],"args":{"namespace":{"required":false,"default":"wp\/v2/sites/48514416"},"context":{"required":false,"default":"view"}}}],"_links":{"self":"https:\/\/public-api.wordpress.com\/wp\/v2"}},"\/wp\/v2\/sites\/48514416":{"namespace":"wp\/v2/sites/48514416","methods":["GET"],"endpoints":[{"methods":["GET"],"args":{"namespace":{"required":false,"default":"wp\/v2/sites/48514416"},"context":{"required":false,"default":"view"}}}],"_links":{"self":"https:\/\/public-api.wordpress.com\/wp\/v2\/sites\/48514416"}},"\/wp\/v2\/sites\/48514416\/posts":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST"],"endpoints":[{"methods":["GET"],"args":{"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"},"page":{"required":false,"default":1,"description":"Current page of the collection.","type":"integer"},"per_page":{"required":false,"default":10,"description":"Maximum number of items to be returned in result set.","type":"integer"},"search":{"required":false,"description":"Limit results to those matching a string.","type":"string"},"after":{"required":false,"description":"Limit response to posts published after a given ISO8601 compliant date.","type":"string"},"author":{"required":false,"default":[],"description":"Limit result set to posts assigned to specific authors.","type":"array","items":{"type":"integer"}},"author_exclude":{"required":false,"default":[],"description":"Ensure result set excludes posts assigned to specific authors.","type":"array","items":{"type":"integer"}},"before":{"required":false,"description":"Limit response to posts published before a given ISO8601 compliant date.","type":"string"},"exclude":{"required":false,"default":[],"description":"Ensure result set excludes specific IDs.","type":"array","items":{"type":"integer"}},"include":{"required":false,"default":[],"description":"Limit result set to specific IDs.","type":"array","items":{"type":"integer"}},"offset":{"required":false,"description":"Offset the result set by a specific number of items.","type":"integer"},"order":{"required":false,"default":"desc","enum":["asc","desc"],"description":"Order sort attribute ascending or descending.","type":"string"},"orderby":{"required":false,"default":"date","enum":["author","date","id","include","modified","parent","relevance","slug","title"],"description":"Sort collection by object attribute.","type":"string"},"slug":{"required":false,"description":"Limit result set to posts with one or more specific slugs.","type":"array","items":{"type":"string"}},"status":{"required":false,"default":"publish","description":"Limit result set to posts assigned one or more statuses.","type":"array","items":{"enum":["publish","future","draft","pending","private","trash","auto-draft","inherit","spam","any"],"type":"string"}},"categories":{"required":false,"default":[],"description":"Limit result set to all items that have the specified term assigned in the categories taxonomy.","type":"array","items":{"type":"integer"}},"categories_exclude":{"required":false,"default":[],"description":"Limit result set to all items except those that have the specified term assigned in the categories taxonomy.","type":"array","items":{"type":"integer"}},"tags":{"required":false,"default":[],"description":"Limit result set to all items that have the specified term assigned in the tags taxonomy.","type":"array","items":{"type":"integer"}},"tags_exclude":{"required":false,"default":[],"description":"Limit result set to all items except those that have the specified term assigned in the tags taxonomy.","type":"array","items":{"type":"integer"}},"sticky":{"required":false,"description":"Limit result set to items that are sticky.","type":"boolean"}}},{"methods":["POST"],"args":{"date":{"required":false,"description":"The date the object was published, in the site's timezone.","type":"string"},"date_gmt":{"required":false,"description":"The date the object was published, as GMT.","type":"string"},"slug":{"required":false,"description":"An alphanumeric identifier for the object unique to its type.","type":"string"},"status":{"required":false,"enum":["publish","future","draft","pending","private","spam"],"description":"A named status for the object.","type":"string"},"password":{"required":false,"description":"A password to protect access to the content and excerpt.","type":"string"},"title":{"required":false,"description":"The title for the object.","type":"object"},"content":{"required":false,"description":"The content for the object.","type":"object"},"author":{"required":false,"description":"The ID for the author of the object.","type":"integer"},"excerpt":{"required":false,"description":"The excerpt for the object.","type":"object"},"featured_media":{"required":false,"description":"The ID of the featured media for the object.","type":"integer"},"comment_status":{"required":false,"enum":["open","closed"],"description":"Whether or not comments are open on the object.","type":"string"},"ping_status":{"required":false,"enum":["open","closed"],"description":"Whether or not the object can be pinged.","type":"string"},"format":{"required":false,"enum":["standard","aside","chat","gallery","link","image","quote","status","video","audio"],"description":"The format for the object.","type":"string"},"meta":{"required":false,"description":"Meta fields.","type":"object"},"sticky":{"required":false,"description":"Whether or not the object should be treated as sticky.","type":"boolean"},"template":{"required":false,"enum":[""],"description":"The theme file to use to display the object.","type":"string"},"categories":{"required":false,"description":"The terms assigned to the object in the category taxonomy.","type":"array","items":{"type":"integer"}},"tags":{"required":false,"description":"The terms assigned to the object in the post_tag taxonomy.","type":"array","items":{"type":"integer"}}}}],"_links":{"self":"https:\/\/public-api.wordpress.com\/wp\/v2\/sites\/48514416\/posts"}},"\/wp\/v2\/sites\/48514416\/posts\/(?P<id>[\\d]+)":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST","PUT","PATCH","DELETE"],"endpoints":[{"methods":["GET"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"},"password":{"required":false,"description":"The password for the post if it is password protected.","type":"string"}}},{"methods":["POST","PUT","PATCH"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"date":{"required":false,"description":"The date the object was published, in the site's timezone.","type":"string"},"date_gmt":{"required":false,"description":"The date the object was published, as GMT.","type":"string"},"slug":{"required":false,"description":"An alphanumeric identifier for the object unique to its type.","type":"string"},"status":{"required":false,"enum":["publish","future","draft","pending","private","spam"],"description":"A named status for the object.","type":"string"},"password":{"required":false,"description":"A password to protect access to the content and excerpt.","type":"string"},"title":{"required":false,"description":"The title for the object.","type":"object"},"content":{"required":false,"description":"The content for the object.","type":"object"},"author":{"required":false,"description":"The ID for the author of the object.","type":"integer"},"excerpt":{"required":false,"description":"The excerpt for the object.","type":"object"},"featured_media":{"required":false,"description":"The ID of the featured media for the object.","type":"integer"},"comment_status":{"required":false,"enum":["open","closed"],"description":"Whether or not comments are open on the object.","type":"string"},"ping_status":{"required":false,"enum":["open","closed"],"description":"Whether or not the object can be pinged.","type":"string"},"format":{"required":false,"enum":["standard","aside","chat","gallery","link","image","quote","status","video","audio"],"description":"The format for the object.","type":"string"},"meta":{"required":false,"description":"Meta fields.","type":"object"},"sticky":{"required":false,"description":"Whether or not the object should be treated as sticky.","type":"boolean"},"template":{"required":false,"enum":[""],"description":"The theme file to use to display the object.","type":"string"},"categories":{"required":false,"description":"The terms assigned to the object in the category taxonomy.","type":"array","items":{"type":"integer"}},"tags":{"required":false,"description":"The terms assigned to the object in the post_tag taxonomy.","type":"array","items":{"type":"integer"}}}},{"methods":["DELETE"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"force":{"required":false,"default":false,"description":"Whether to bypass trash and force deletion.","type":"boolean"}}}]},"\/wp\/v2\/sites\/48514416\/posts\/(?P<parent>[\\d]+)\/revisions":{"namespace":"wp\/v2/sites/48514416","methods":["GET"],"endpoints":[{"methods":["GET"],"args":{"parent":{"required":false,"description":"The ID for the parent of the object.","type":"integer"},"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"}}}]},"\/wp\/v2\/sites\/48514416\/posts\/(?P<parent>[\\d]+)\/revisions\/(?P<id>[\\d]+)":{"namespace":"wp\/v2/sites/48514416","methods":["GET","DELETE"],"endpoints":[{"methods":["GET"],"args":{"parent":{"required":false,"description":"The ID for the parent of the object.","type":"integer"},"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"}}},{"methods":["DELETE"],"args":{"parent":{"required":false,"description":"The ID for the parent of the object.","type":"integer"},"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"force":{"required":false,"default":false,"description":"Required to be true, as revisions do not support trashing.","type":"boolean"}}}]},"\/wp\/v2\/sites\/48514416\/pages":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST"],"endpoints":[{"methods":["GET"],"args":{"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"},"page":{"required":false,"default":1,"description":"Current page of the collection.","type":"integer"},"per_page":{"required":false,"default":10,"description":"Maximum number of items to be returned in result set.","type":"integer"},"search":{"required":false,"description":"Limit results to those matching a string.","type":"string"},"after":{"required":false,"description":"Limit response to posts published after a given ISO8601 compliant date.","type":"string"},"author":{"required":false,"default":[],"description":"Limit result set to posts assigned to specific authors.","type":"array","items":{"type":"integer"}},"author_exclude":{"required":false,"default":[],"description":"Ensure result set excludes posts assigned to specific authors.","type":"array","items":{"type":"integer"}},"before":{"required":false,"description":"Limit response to posts published before a given ISO8601 compliant date.","type":"string"},"exclude":{"required":false,"default":[],"description":"Ensure result set excludes specific IDs.","type":"array","items":{"type":"integer"}},"include":{"required":false,"default":[],"description":"Limit result set to specific IDs.","type":"array","items":{"type":"integer"}},"menu_order":{"required":false,"description":"Limit result set to posts with a specific menu_order value.","type":"integer"},"offset":{"required":false,"description":"Offset the result set by a specific number of items.","type":"integer"},"order":{"required":false,"default":"desc","enum":["asc","desc"],"description":"Order sort attribute ascending or descending.","type":"string"},"orderby":{"required":false,"default":"date","enum":["author","date","id","include","modified","parent","relevance","slug","title","menu_order"],"description":"Sort collection by object attribute.","type":"string"},"parent":{"required":false,"default":[],"description":"Limit result set to items with particular parent IDs.","type":"array","items":{"type":"integer"}},"parent_exclude":{"required":false,"default":[],"description":"Limit result set to all items except those of a particular parent ID.","type":"array","items":{"type":"integer"}},"slug":{"required":false,"description":"Limit result set to posts with one or more specific slugs.","type":"array","items":{"type":"string"}},"status":{"required":false,"default":"publish","description":"Limit result set to posts assigned one or more statuses.","type":"array","items":{"enum":["publish","future","draft","pending","private","trash","auto-draft","inherit","spam","any"],"type":"string"}}}},{"methods":["POST"],"args":{"date":{"required":false,"description":"The date the object was published, in the site's timezone.","type":"string"},"date_gmt":{"required":false,"description":"The date the object was published, as GMT.","type":"string"},"slug":{"required":false,"description":"An alphanumeric identifier for the object unique to its type.","type":"string"},"status":{"required":false,"enum":["publish","future","draft","pending","private","spam"],"description":"A named status for the object.","type":"string"},"password":{"required":false,"description":"A password to protect access to the content and excerpt.","type":"string"},"parent":{"required":false,"description":"The ID for the parent of the object.","type":"integer"},"title":{"required":false,"description":"The title for the object.","type":"object"},"content":{"required":false,"description":"The content for the object.","type":"object"},"author":{"required":false,"description":"The ID for the author of the object.","type":"integer"},"excerpt":{"required":false,"description":"The excerpt for the object.","type":"object"},"featured_media":{"required":false,"description":"The ID of the featured media for the object.","type":"integer"},"comment_status":{"required":false,"enum":["open","closed"],"description":"Whether or not comments are open on the object.","type":"string"},"ping_status":{"required":false,"enum":["open","closed"],"description":"Whether or not the object can be pinged.","type":"string"},"menu_order":{"required":false,"description":"The order of the object in relation to other object of its type.","type":"integer"},"meta":{"required":false,"description":"Meta fields.","type":"object"},"template":{"required":false,"enum":[""],"description":"The theme file to use to display the object.","type":"string"}}}],"_links":{"self":"https:\/\/public-api.wordpress.com\/wp\/v2\/sites\/48514416\/pages"}},"\/wp\/v2\/sites\/48514416\/pages\/(?P<id>[\\d]+)":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST","PUT","PATCH","DELETE"],"endpoints":[{"methods":["GET"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"},"password":{"required":false,"description":"The password for the post if it is password protected.","type":"string"}}},{"methods":["POST","PUT","PATCH"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"date":{"required":false,"description":"The date the object was published, in the site's timezone.","type":"string"},"date_gmt":{"required":false,"description":"The date the object was published, as GMT.","type":"string"},"slug":{"required":false,"description":"An alphanumeric identifier for the object unique to its type.","type":"string"},"status":{"required":false,"enum":["publish","future","draft","pending","private","spam"],"description":"A named status for the object.","type":"string"},"password":{"required":false,"description":"A password to protect access to the content and excerpt.","type":"string"},"parent":{"required":false,"description":"The ID for the parent of the object.","type":"integer"},"title":{"required":false,"description":"The title for the object.","type":"object"},"content":{"required":false,"description":"The content for the object.","type":"object"},"author":{"required":false,"description":"The ID for the author of the object.","type":"integer"},"excerpt":{"required":false,"description":"The excerpt for the object.","type":"object"},"featured_media":{"required":false,"description":"The ID of the featured media for the object.","type":"integer"},"comment_status":{"required":false,"enum":["open","closed"],"description":"Whether or not comments are open on the object.","type":"string"},"ping_status":{"required":false,"enum":["open","closed"],"description":"Whether or not the object can be pinged.","type":"string"},"menu_order":{"required":false,"description":"The order of the object in relation to other object of its type.","type":"integer"},"meta":{"required":false,"description":"Meta fields.","type":"object"},"template":{"required":false,"enum":[""],"description":"The theme file to use to display the object.","type":"string"}}},{"methods":["DELETE"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"force":{"required":false,"default":false,"description":"Whether to bypass trash and force deletion.","type":"boolean"}}}]},"\/wp\/v2\/sites\/48514416\/pages\/(?P<parent>[\\d]+)\/revisions":{"namespace":"wp\/v2/sites/48514416","methods":["GET"],"endpoints":[{"methods":["GET"],"args":{"parent":{"required":false,"description":"The ID for the parent of the object.","type":"integer"},"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"}}}]},"\/wp\/v2\/sites\/48514416\/pages\/(?P<parent>[\\d]+)\/revisions\/(?P<id>[\\d]+)":{"namespace":"wp\/v2/sites/48514416","methods":["GET","DELETE"],"endpoints":[{"methods":["GET"],"args":{"parent":{"required":false,"description":"The ID for the parent of the object.","type":"integer"},"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"}}},{"methods":["DELETE"],"args":{"parent":{"required":false,"description":"The ID for the parent of the object.","type":"integer"},"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"force":{"required":false,"default":false,"description":"Required to be true, as revisions do not support trashing.","type":"boolean"}}}]},"\/wp\/v2\/sites\/48514416\/media":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST"],"endpoints":[{"methods":["GET"],"args":{"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"},"page":{"required":false,"default":1,"description":"Current page of the collection.","type":"integer"},"per_page":{"required":false,"default":10,"description":"Maximum number of items to be returned in result set.","type":"integer"},"search":{"required":false,"description":"Limit results to those matching a string.","type":"string"},"after":{"required":false,"description":"Limit response to posts published after a given ISO8601 compliant date.","type":"string"},"author":{"required":false,"default":[],"description":"Limit result set to posts assigned to specific authors.","type":"array","items":{"type":"integer"}},"author_exclude":{"required":false,"default":[],"description":"Ensure result set excludes posts assigned to specific authors.","type":"array","items":{"type":"integer"}},"before":{"required":false,"description":"Limit response to posts published before a given ISO8601 compliant date.","type":"string"},"exclude":{"required":false,"default":[],"description":"Ensure result set excludes specific IDs.","type":"array","items":{"type":"integer"}},"include":{"required":false,"default":[],"description":"Limit result set to specific IDs.","type":"array","items":{"type":"integer"}},"offset":{"required":false,"description":"Offset the result set by a specific number of items.","type":"integer"},"order":{"required":false,"default":"desc","enum":["asc","desc"],"description":"Order sort attribute ascending or descending.","type":"string"},"orderby":{"required":false,"default":"date","enum":["author","date","id","include","modified","parent","relevance","slug","title"],"description":"Sort collection by object attribute.","type":"string"},"parent":{"required":false,"default":[],"description":"Limit result set to items with particular parent IDs.","type":"array","items":{"type":"integer"}},"parent_exclude":{"required":false,"default":[],"description":"Limit result set to all items except those of a particular parent ID.","type":"array","items":{"type":"integer"}},"slug":{"required":false,"description":"Limit result set to posts with one or more specific slugs.","type":"array","items":{"type":"string"}},"status":{"required":false,"default":"inherit","description":"Limit result set to posts assigned one or more statuses.","type":"array","items":{"enum":["inherit","private","trash"],"type":"string"}},"media_type":{"required":false,"enum":["image","application"],"description":"Limit result set to attachments of a particular media type.","type":"string"},"mime_type":{"required":false,"description":"Limit result set to attachments of a particular MIME type.","type":"string"}}},{"methods":["POST"],"args":{"date":{"required":false,"description":"The date the object was published, in the site's timezone.","type":"string"},"date_gmt":{"required":false,"description":"The date the object was published, as GMT.","type":"string"},"slug":{"required":false,"description":"An alphanumeric identifier for the object unique to its type.","type":"string"},"status":{"required":false,"enum":["publish","future","draft","pending","private","spam"],"description":"A named status for the object.","type":"string"},"title":{"required":false,"description":"The title for the object.","type":"object"},"author":{"required":false,"description":"The ID for the author of the object.","type":"integer"},"comment_status":{"required":false,"enum":["open","closed"],"description":"Whether or not comments are open on the object.","type":"string"},"ping_status":{"required":false,"enum":["open","closed"],"description":"Whether or not the object can be pinged.","type":"string"},"meta":{"required":false,"description":"Meta fields.","type":"object"},"template":{"required":false,"enum":[""],"description":"The theme file to use to display the object.","type":"string"},"alt_text":{"required":false,"description":"Alternative text to display when attachment is not displayed.","type":"string"},"caption":{"required":false,"description":"The attachment caption.","type":"object"},"description":{"required":false,"description":"The attachment description.","type":"object"},"post":{"required":false,"description":"The ID for the associated post of the attachment.","type":"integer"}}}],"_links":{"self":"https:\/\/public-api.wordpress.com\/wp\/v2\/sites\/48514416\/media"}},"\/wp\/v2\/sites\/48514416\/media\/(?P<id>[\\d]+)":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST","PUT","PATCH","DELETE"],"endpoints":[{"methods":["GET"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"}}},{"methods":["POST","PUT","PATCH"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"date":{"required":false,"description":"The date the object was published, in the site's timezone.","type":"string"},"date_gmt":{"required":false,"description":"The date the object was published, as GMT.","type":"string"},"slug":{"required":false,"description":"An alphanumeric identifier for the object unique to its type.","type":"string"},"status":{"required":false,"enum":["publish","future","draft","pending","private","spam"],"description":"A named status for the object.","type":"string"},"title":{"required":false,"description":"The title for the object.","type":"object"},"author":{"required":false,"description":"The ID for the author of the object.","type":"integer"},"comment_status":{"required":false,"enum":["open","closed"],"description":"Whether or not comments are open on the object.","type":"string"},"ping_status":{"required":false,"enum":["open","closed"],"description":"Whether or not the object can be pinged.","type":"string"},"meta":{"required":false,"description":"Meta fields.","type":"object"},"template":{"required":false,"enum":[""],"description":"The theme file to use to display the object.","type":"string"},"alt_text":{"required":false,"description":"Alternative text to display when attachment is not displayed.","type":"string"},"caption":{"required":false,"description":"The attachment caption.","type":"object"},"description":{"required":false,"description":"The attachment description.","type":"object"},"post":{"required":false,"description":"The ID for the associated post of the attachment.","type":"integer"}}},{"methods":["DELETE"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"force":{"required":false,"default":false,"description":"Whether to bypass trash and force deletion.","type":"boolean"}}}]},"\/wp\/v2\/sites\/48514416\/jp_pay_order":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST"],"endpoints":[{"methods":["GET"],"args":{"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"},"page":{"required":false,"default":1,"description":"Current page of the collection.","type":"integer"},"per_page":{"required":false,"default":10,"description":"Maximum number of items to be returned in result set.","type":"integer"},"search":{"required":false,"description":"Limit results to those matching a string.","type":"string"},"after":{"required":false,"description":"Limit response to posts published after a given ISO8601 compliant date.","type":"string"},"before":{"required":false,"description":"Limit response to posts published before a given ISO8601 compliant date.","type":"string"},"exclude":{"required":false,"default":[],"description":"Ensure result set excludes specific IDs.","type":"array","items":{"type":"integer"}},"include":{"required":false,"default":[],"description":"Limit result set to specific IDs.","type":"array","items":{"type":"integer"}},"offset":{"required":false,"description":"Offset the result set by a specific number of items.","type":"integer"},"order":{"required":false,"default":"desc","enum":["asc","desc"],"description":"Order sort attribute ascending or descending.","type":"string"},"orderby":{"required":false,"default":"date","enum":["author","date","id","include","modified","parent","relevance","slug","title"],"description":"Sort collection by object attribute.","type":"string"},"slug":{"required":false,"description":"Limit result set to posts with one or more specific slugs.","type":"array","items":{"type":"string"}},"status":{"required":false,"default":"publish","description":"Limit result set to posts assigned one or more statuses.","type":"array","items":{"enum":["publish","future","draft","pending","private","trash","auto-draft","inherit","spam","any"],"type":"string"}}}},{"methods":["POST"],"args":{"date":{"required":false,"description":"The date the object was published, in the site's timezone.","type":"string"},"date_gmt":{"required":false,"description":"The date the object was published, as GMT.","type":"string"},"slug":{"required":false,"description":"An alphanumeric identifier for the object unique to its type.","type":"string"},"status":{"required":false,"enum":["publish","future","draft","pending","private","spam"],"description":"A named status for the object.","type":"string"},"password":{"required":false,"description":"A password to protect access to the content and excerpt.","type":"string"},"excerpt":{"required":false,"description":"The excerpt for the object.","type":"object"},"meta":{"required":false,"description":"Meta fields.","type":"object"},"template":{"required":false,"enum":[""],"description":"The theme file to use to display the object.","type":"string"}}}],"_links":{"self":"https:\/\/public-api.wordpress.com\/wp\/v2\/sites\/48514416\/jp_pay_order"}},"\/wp\/v2\/sites\/48514416\/jp_pay_order\/(?P<id>[\\d]+)":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST","PUT","PATCH","DELETE"],"endpoints":[{"methods":["GET"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"},"password":{"required":false,"description":"The password for the post if it is password protected.","type":"string"}}},{"methods":["POST","PUT","PATCH"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"date":{"required":false,"description":"The date the object was published, in the site's timezone.","type":"string"},"date_gmt":{"required":false,"description":"The date the object was published, as GMT.","type":"string"},"slug":{"required":false,"description":"An alphanumeric identifier for the object unique to its type.","type":"string"},"status":{"required":false,"enum":["publish","future","draft","pending","private","spam"],"description":"A named status for the object.","type":"string"},"password":{"required":false,"description":"A password to protect access to the content and excerpt.","type":"string"},"excerpt":{"required":false,"description":"The excerpt for the object.","type":"object"},"meta":{"required":false,"description":"Meta fields.","type":"object"},"template":{"required":false,"enum":[""],"description":"The theme file to use to display the object.","type":"string"}}},{"methods":["DELETE"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"force":{"required":false,"default":false,"description":"Whether to bypass trash and force deletion.","type":"boolean"}}}]},"\/wp\/v2\/sites\/48514416\/jp_pay_product":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST"],"endpoints":[{"methods":["GET"],"args":{"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"},"page":{"required":false,"default":1,"description":"Current page of the collection.","type":"integer"},"per_page":{"required":false,"default":10,"description":"Maximum number of items to be returned in result set.","type":"integer"},"search":{"required":false,"description":"Limit results to those matching a string.","type":"string"},"after":{"required":false,"description":"Limit response to posts published after a given ISO8601 compliant date.","type":"string"},"before":{"required":false,"description":"Limit response to posts published before a given ISO8601 compliant date.","type":"string"},"exclude":{"required":false,"default":[],"description":"Ensure result set excludes specific IDs.","type":"array","items":{"type":"integer"}},"include":{"required":false,"default":[],"description":"Limit result set to specific IDs.","type":"array","items":{"type":"integer"}},"offset":{"required":false,"description":"Offset the result set by a specific number of items.","type":"integer"},"order":{"required":false,"default":"desc","enum":["asc","desc"],"description":"Order sort attribute ascending or descending.","type":"string"},"orderby":{"required":false,"default":"date","enum":["author","date","id","include","modified","parent","relevance","slug","title"],"description":"Sort collection by object attribute.","type":"string"},"slug":{"required":false,"description":"Limit result set to posts with one or more specific slugs.","type":"array","items":{"type":"string"}},"status":{"required":false,"default":"publish","description":"Limit result set to posts assigned one or more statuses.","type":"array","items":{"enum":["publish","future","draft","pending","private","trash","auto-draft","inherit","spam","any"],"type":"string"}}}},{"methods":["POST"],"args":{"date":{"required":false,"description":"The date the object was published, in the site's timezone.","type":"string"},"date_gmt":{"required":false,"description":"The date the object was published, as GMT.","type":"string"},"slug":{"required":false,"description":"An alphanumeric identifier for the object unique to its type.","type":"string"},"status":{"required":false,"enum":["publish","future","draft","pending","private","spam"],"description":"A named status for the object.","type":"string"},"password":{"required":false,"description":"A password to protect access to the content and excerpt.","type":"string"},"title":{"required":false,"description":"The title for the object.","type":"object"},"content":{"required":false,"description":"The content for the object.","type":"object"},"featured_media":{"required":false,"description":"The ID of the featured media for the object.","type":"integer"},"meta":{"required":false,"description":"Meta fields.","type":"object"},"template":{"required":false,"enum":[""],"description":"The theme file to use to display the object.","type":"string"}}}],"_links":{"self":"https:\/\/public-api.wordpress.com\/wp\/v2\/sites\/48514416\/jp_pay_product"}},"\/wp\/v2\/sites\/48514416\/jp_pay_product\/(?P<id>[\\d]+)":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST","PUT","PATCH","DELETE"],"endpoints":[{"methods":["GET"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"},"password":{"required":false,"description":"The password for the post if it is password protected.","type":"string"}}},{"methods":["POST","PUT","PATCH"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"date":{"required":false,"description":"The date the object was published, in the site's timezone.","type":"string"},"date_gmt":{"required":false,"description":"The date the object was published, as GMT.","type":"string"},"slug":{"required":false,"description":"An alphanumeric identifier for the object unique to its type.","type":"string"},"status":{"required":false,"enum":["publish","future","draft","pending","private","spam"],"description":"A named status for the object.","type":"string"},"password":{"required":false,"description":"A password to protect access to the content and excerpt.","type":"string"},"title":{"required":false,"description":"The title for the object.","type":"object"},"content":{"required":false,"description":"The content for the object.","type":"object"},"featured_media":{"required":false,"description":"The ID of the featured media for the object.","type":"integer"},"meta":{"required":false,"description":"Meta fields.","type":"object"},"template":{"required":false,"enum":[""],"description":"The theme file to use to display the object.","type":"string"}}},{"methods":["DELETE"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"force":{"required":false,"default":false,"description":"Whether to bypass trash and force deletion.","type":"boolean"}}}]},"\/wp\/v2\/sites\/48514416\/feedback":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST"],"endpoints":[{"methods":["GET"],"args":{"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"},"page":{"required":false,"default":1,"description":"Current page of the collection.","type":"integer"},"per_page":{"required":false,"default":10,"description":"Maximum number of items to be returned in result set.","type":"integer"},"search":{"required":false,"description":"Limit results to those matching a string.","type":"string"},"after":{"required":false,"description":"Limit response to posts published after a given ISO8601 compliant date.","type":"string"},"before":{"required":false,"description":"Limit response to posts published before a given ISO8601 compliant date.","type":"string"},"exclude":{"required":false,"default":[],"description":"Ensure result set excludes specific IDs.","type":"array","items":{"type":"integer"}},"include":{"required":false,"default":[],"description":"Limit result set to specific IDs.","type":"array","items":{"type":"integer"}},"offset":{"required":false,"description":"Offset the result set by a specific number of items.","type":"integer"},"order":{"required":false,"default":"desc","enum":["asc","desc"],"description":"Order sort attribute ascending or descending.","type":"string"},"orderby":{"required":false,"default":"date","enum":["author","date","id","include","modified","parent","relevance","slug","title"],"description":"Sort collection by object attribute.","type":"string"},"slug":{"required":false,"description":"Limit result set to posts with one or more specific slugs.","type":"array","items":{"type":"string"}},"status":{"required":false,"default":"publish","description":"Limit result set to posts assigned one or more statuses.","type":"array","items":{"enum":["publish","future","draft","pending","private","trash","auto-draft","inherit","spam","any"],"type":"string"}}}},{"methods":["POST"],"args":{"date":{"required":false,"description":"The date the object was published, in the site's timezone.","type":"string"},"date_gmt":{"required":false,"description":"The date the object was published, as GMT.","type":"string"},"slug":{"required":false,"description":"An alphanumeric identifier for the object unique to its type.","type":"string"},"status":{"required":false,"enum":["publish","future","draft","pending","private","spam"],"description":"A named status for the object.","type":"string"},"password":{"required":false,"description":"A password to protect access to the content and excerpt.","type":"string"},"title":{"required":false,"description":"The title for the object.","type":"object"},"content":{"required":false,"description":"The content for the object.","type":"object"},"template":{"required":false,"enum":[""],"description":"The theme file to use to display the object.","type":"string"}}}],"_links":{"self":"https:\/\/public-api.wordpress.com\/wp\/v2\/sites\/48514416\/feedback"}},"\/wp\/v2\/sites\/48514416\/feedback\/(?P<id>[\\d]+)":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST","PUT","PATCH","DELETE"],"endpoints":[{"methods":["GET"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"},"password":{"required":false,"description":"The password for the post if it is password protected.","type":"string"}}},{"methods":["POST","PUT","PATCH"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"date":{"required":false,"description":"The date the object was published, in the site's timezone.","type":"string"},"date_gmt":{"required":false,"description":"The date the object was published, as GMT.","type":"string"},"slug":{"required":false,"description":"An alphanumeric identifier for the object unique to its type.","type":"string"},"status":{"required":false,"enum":["publish","future","draft","pending","private","spam"],"description":"A named status for the object.","type":"string"},"password":{"required":false,"description":"A password to protect access to the content and excerpt.","type":"string"},"title":{"required":false,"description":"The title for the object.","type":"object"},"content":{"required":false,"description":"The content for the object.","type":"object"},"template":{"required":false,"enum":[""],"description":"The theme file to use to display the object.","type":"string"}}},{"methods":["DELETE"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"force":{"required":false,"default":false,"description":"Whether to bypass trash and force deletion.","type":"boolean"}}}]},"\/wp\/v2\/sites\/48514416\/types":{"namespace":"wp\/v2/sites/48514416","methods":["GET"],"endpoints":[{"methods":["GET"],"args":{"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"}}}],"_links":{"self":"https:\/\/public-api.wordpress.com\/wp\/v2\/sites\/48514416\/types"}},"\/wp\/v2\/sites\/48514416\/types\/(?P<type>[\\w-]+)":{"namespace":"wp\/v2/sites/48514416","methods":["GET"],"endpoints":[{"methods":["GET"],"args":{"type":{"required":false,"description":"An alphanumeric identifier for the post type.","type":"string"},"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"}}}]},"\/wp\/v2\/sites\/48514416\/statuses":{"namespace":"wp\/v2/sites/48514416","methods":["GET"],"endpoints":[{"methods":["GET"],"args":{"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"}}}],"_links":{"self":"https:\/\/public-api.wordpress.com\/wp\/v2\/sites\/48514416\/statuses"}},"\/wp\/v2\/sites\/48514416\/statuses\/(?P<status>[\\w-]+)":{"namespace":"wp\/v2/sites/48514416","methods":["GET"],"endpoints":[{"methods":["GET"],"args":{"status":{"required":false,"description":"An alphanumeric identifier for the status.","type":"string"},"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"}}}]},"\/wp\/v2\/sites\/48514416\/taxonomies":{"namespace":"wp\/v2/sites/48514416","methods":["GET"],"endpoints":[{"methods":["GET"],"args":{"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"},"type":{"required":false,"description":"Limit results to taxonomies associated with a specific post type.","type":"string"}}}],"_links":{"self":"https:\/\/public-api.wordpress.com\/wp\/v2\/sites\/48514416\/taxonomies"}},"\/wp\/v2\/sites\/48514416\/taxonomies\/(?P<taxonomy>[\\w-]+)":{"namespace":"wp\/v2/sites/48514416","methods":["GET"],"endpoints":[{"methods":["GET"],"args":{"taxonomy":{"required":false,"description":"An alphanumeric identifier for the taxonomy.","type":"string"},"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"}}}]},"\/wp\/v2\/sites\/48514416\/categories":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST"],"endpoints":[{"methods":["GET"],"args":{"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"},"page":{"required":false,"default":1,"description":"Current page of the collection.","type":"integer"},"per_page":{"required":false,"default":10,"description":"Maximum number of items to be returned in result set.","type":"integer"},"search":{"required":false,"description":"Limit results to those matching a string.","type":"string"},"exclude":{"required":false,"default":[],"description":"Ensure result set excludes specific IDs.","type":"array","items":{"type":"integer"}},"include":{"required":false,"default":[],"description":"Limit result set to specific IDs.","type":"array","items":{"type":"integer"}},"order":{"required":false,"default":"asc","enum":["asc","desc"],"description":"Order sort attribute ascending or descending.","type":"string"},"orderby":{"required":false,"default":"name","enum":["id","include","name","slug","term_group","description","count"],"description":"Sort collection by term attribute.","type":"string"},"hide_empty":{"required":false,"default":false,"description":"Whether to hide terms not assigned to any posts.","type":"boolean"},"parent":{"required":false,"description":"Limit result set to terms assigned to a specific parent.","type":"integer"},"post":{"required":false,"description":"Limit result set to terms assigned to a specific post.","type":"integer"},"slug":{"required":false,"description":"Limit result set to terms with one or more specific slugs.","type":"array","items":{"type":"string"}}}},{"methods":["POST"],"args":{"description":{"required":false,"description":"HTML description of the term.","type":"string"},"name":{"required":true,"description":"HTML title for the term.","type":"string"},"slug":{"required":false,"description":"An alphanumeric identifier for the term unique to its type.","type":"string"},"parent":{"required":false,"description":"The parent term ID.","type":"integer"},"meta":{"required":false,"description":"Meta fields.","type":"object"}}}],"_links":{"self":"https:\/\/public-api.wordpress.com\/wp\/v2\/sites\/48514416\/categories"}},"\/wp\/v2\/sites\/48514416\/categories\/(?P<id>[\\d]+)":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST","PUT","PATCH","DELETE"],"endpoints":[{"methods":["GET"],"args":{"id":{"required":false,"description":"Unique identifier for the term.","type":"integer"},"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"}}},{"methods":["POST","PUT","PATCH"],"args":{"id":{"required":false,"description":"Unique identifier for the term.","type":"integer"},"description":{"required":false,"description":"HTML description of the term.","type":"string"},"name":{"required":false,"description":"HTML title for the term.","type":"string"},"slug":{"required":false,"description":"An alphanumeric identifier for the term unique to its type.","type":"string"},"parent":{"required":false,"description":"The parent term ID.","type":"integer"},"meta":{"required":false,"description":"Meta fields.","type":"object"}}},{"methods":["DELETE"],"args":{"id":{"required":false,"description":"Unique identifier for the term.","type":"integer"},"force":{"required":false,"default":false,"description":"Required to be true, as terms do not support trashing.","type":"boolean"}}}]},"\/wp\/v2\/sites\/48514416\/tags":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST"],"endpoints":[{"methods":["GET"],"args":{"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"},"page":{"required":false,"default":1,"description":"Current page of the collection.","type":"integer"},"per_page":{"required":false,"default":10,"description":"Maximum number of items to be returned in result set.","type":"integer"},"search":{"required":false,"description":"Limit results to those matching a string.","type":"string"},"exclude":{"required":false,"default":[],"description":"Ensure result set excludes specific IDs.","type":"array","items":{"type":"integer"}},"include":{"required":false,"default":[],"description":"Limit result set to specific IDs.","type":"array","items":{"type":"integer"}},"offset":{"required":false,"description":"Offset the result set by a specific number of items.","type":"integer"},"order":{"required":false,"default":"asc","enum":["asc","desc"],"description":"Order sort attribute ascending or descending.","type":"string"},"orderby":{"required":false,"default":"name","enum":["id","include","name","slug","term_group","description","count"],"description":"Sort collection by term attribute.","type":"string"},"hide_empty":{"required":false,"default":false,"description":"Whether to hide terms not assigned to any posts.","type":"boolean"},"post":{"required":false,"description":"Limit result set to terms assigned to a specific post.","type":"integer"},"slug":{"required":false,"description":"Limit result set to terms with one or more specific slugs.","type":"array","items":{"type":"string"}}}},{"methods":["POST"],"args":{"description":{"required":false,"description":"HTML description of the term.","type":"string"},"name":{"required":true,"description":"HTML title for the term.","type":"string"},"slug":{"required":false,"description":"An alphanumeric identifier for the term unique to its type.","type":"string"},"meta":{"required":false,"description":"Meta fields.","type":"object"}}}],"_links":{"self":"https:\/\/public-api.wordpress.com\/wp\/v2\/sites\/48514416\/tags"}},"\/wp\/v2\/sites\/48514416\/tags\/(?P<id>[\\d]+)":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST","PUT","PATCH","DELETE"],"endpoints":[{"methods":["GET"],"args":{"id":{"required":false,"description":"Unique identifier for the term.","type":"integer"},"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"}}},{"methods":["POST","PUT","PATCH"],"args":{"id":{"required":false,"description":"Unique identifier for the term.","type":"integer"},"description":{"required":false,"description":"HTML description of the term.","type":"string"},"name":{"required":false,"description":"HTML title for the term.","type":"string"},"slug":{"required":false,"description":"An alphanumeric identifier for the term unique to its type.","type":"string"},"meta":{"required":false,"description":"Meta fields.","type":"object"}}},{"methods":["DELETE"],"args":{"id":{"required":false,"description":"Unique identifier for the term.","type":"integer"},"force":{"required":false,"default":false,"description":"Required to be true, as terms do not support trashing.","type":"boolean"}}}]},"\/wp\/v2\/sites\/48514416\/users":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST"],"endpoints":[{"methods":["GET"],"args":{"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"},"page":{"required":false,"default":1,"description":"Current page of the collection.","type":"integer"},"per_page":{"required":false,"default":10,"description":"Maximum number of items to be returned in result set.","type":"integer"},"search":{"required":false,"description":"Limit results to those matching a string.","type":"string"},"exclude":{"required":false,"default":[],"description":"Ensure result set excludes specific IDs.","type":"array","items":{"type":"integer"}},"include":{"required":false,"default":[],"description":"Limit result set to specific IDs.","type":"array","items":{"type":"integer"}},"offset":{"required":false,"description":"Offset the result set by a specific number of items.","type":"integer"},"order":{"required":false,"default":"asc","enum":["asc","desc"],"description":"Order sort attribute ascending or descending.","type":"string"},"orderby":{"required":false,"default":"name","enum":["id","include","name","registered_date","slug","email","url"],"description":"Sort collection by object attribute.","type":"string"},"slug":{"required":false,"description":"Limit result set to users with one or more specific slugs.","type":"array","items":{"type":"string"}},"roles":{"required":false,"description":"Limit result set to users matching at least one specific role provided. Accepts csv list or single role.","type":"array","items":{"type":"string"}}}},{"methods":["POST"],"args":{"username":{"required":true,"description":"Login name for the user.","type":"string"},"name":{"required":false,"description":"Display name for the user.","type":"string"},"first_name":{"required":false,"description":"First name for the user.","type":"string"},"last_name":{"required":false,"description":"Last name for the user.","type":"string"},"email":{"required":true,"description":"The email address for the user.","type":"string"},"url":{"required":false,"description":"URL of the user.","type":"string"},"description":{"required":false,"description":"Description of the user.","type":"string"},"locale":{"required":false,"enum":["","en_US","af","am","an","ar","as","ast","az","bal","bel","bg","bn","bo","br","bs","ca","ckb","cs","cy","da","de","dv","el-po","el","en-gb","eo","es-cl","es-mx","es-pr","es","et","eu","fa","fi","fo","fr-be","fr-ca","fr-ch","fr","fy","ga","gd","gl","gu","he","hi","hr","hu","hy","id","is","it","ja","ka","kir","kk","km","kn","ko","ku","la","lo","lt","lv","me","mhr","mk","ml","mm","mn","mr","mrj","ms","mt","mwl","my","mya","ne","nl","nn","no","oci","pa","pl","ps","pt-br-dev","pt-br","pt","ro","ru","rue","rup","sah","si","sk","sl","snd","so","sq","sr","su","sv","sw","ta","te","th","tir","tl","tlh","tr","ug","uk","ur","uz","vi","xmf","yi","yo","yor","zh-cn","zh-hk","zh-sg","zh-tw","zh","en","sr_latin"],"description":"Locale for the user.","type":"string"},"nickname":{"required":false,"description":"The nickname for the user.","type":"string"},"slug":{"required":false,"description":"An alphanumeric identifier for the user.","type":"string"},"roles":{"required":false,"description":"Roles assigned to the user.","type":"array","items":{"type":"string"}},"password":{"required":true,"description":"Password for the user (never included).","type":"string"},"meta":{"required":false,"description":"Meta fields.","type":"object"}}}],"_links":{"self":"https:\/\/public-api.wordpress.com\/wp\/v2\/sites\/48514416\/users"}},"\/wp\/v2\/sites\/48514416\/users\/(?P<id>[\\d]+)":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST","PUT","PATCH","DELETE"],"endpoints":[{"methods":["GET"],"args":{"id":{"required":false,"description":"Unique identifier for the user.","type":"integer"},"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"}}},{"methods":["POST","PUT","PATCH"],"args":{"id":{"required":false,"description":"Unique identifier for the user.","type":"integer"},"username":{"required":false,"description":"Login name for the user.","type":"string"},"name":{"required":false,"description":"Display name for the user.","type":"string"},"first_name":{"required":false,"description":"First name for the user.","type":"string"},"last_name":{"required":false,"description":"Last name for the user.","type":"string"},"email":{"required":false,"description":"The email address for the user.","type":"string"},"url":{"required":false,"description":"URL of the user.","type":"string"},"description":{"required":false,"description":"Description of the user.","type":"string"},"locale":{"required":false,"enum":["","en_US","af","am","an","ar","as","ast","az","bal","bel","bg","bn","bo","br","bs","ca","ckb","cs","cy","da","de","dv","el-po","el","en-gb","eo","es-cl","es-mx","es-pr","es","et","eu","fa","fi","fo","fr-be","fr-ca","fr-ch","fr","fy","ga","gd","gl","gu","he","hi","hr","hu","hy","id","is","it","ja","ka","kir","kk","km","kn","ko","ku","la","lo","lt","lv","me","mhr","mk","ml","mm","mn","mr","mrj","ms","mt","mwl","my","mya","ne","nl","nn","no","oci","pa","pl","ps","pt-br-dev","pt-br","pt","ro","ru","rue","rup","sah","si","sk","sl","snd","so","sq","sr","su","sv","sw","ta","te","th","tir","tl","tlh","tr","ug","uk","ur","uz","vi","xmf","yi","yo","yor","zh-cn","zh-hk","zh-sg","zh-tw","zh","en","sr_latin"],"description":"Locale for the user.","type":"string"},"nickname":{"required":false,"description":"The nickname for the user.","type":"string"},"slug":{"required":false,"description":"An alphanumeric identifier for the user.","type":"string"},"roles":{"required":false,"description":"Roles assigned to the user.","type":"array","items":{"type":"string"}},"password":{"required":false,"description":"Password for the user (never included).","type":"string"},"meta":{"required":false,"description":"Meta fields.","type":"object"}}},{"methods":["DELETE"],"args":{"id":{"required":false,"description":"Unique identifier for the user.","type":"integer"},"force":{"required":false,"default":false,"description":"Required to be true, as users do not support trashing.","type":"boolean"},"reassign":{"required":true,"description":"Reassign the deleted user's posts and links to this user ID.","type":"integer"}}}]},"\/wp\/v2\/sites\/48514416\/users\/me":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST","PUT","PATCH","DELETE"],"endpoints":[{"methods":["GET"],"args":{"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"}}},{"methods":["POST","PUT","PATCH"],"args":{"username":{"required":false,"description":"Login name for the user.","type":"string"},"name":{"required":false,"description":"Display name for the user.","type":"string"},"first_name":{"required":false,"description":"First name for the user.","type":"string"},"last_name":{"required":false,"description":"Last name for the user.","type":"string"},"email":{"required":false,"description":"The email address for the user.","type":"string"},"url":{"required":false,"description":"URL of the user.","type":"string"},"description":{"required":false,"description":"Description of the user.","type":"string"},"locale":{"required":false,"enum":["","en_US","af","am","an","ar","as","ast","az","bal","bel","bg","bn","bo","br","bs","ca","ckb","cs","cy","da","de","dv","el-po","el","en-gb","eo","es-cl","es-mx","es-pr","es","et","eu","fa","fi","fo","fr-be","fr-ca","fr-ch","fr","fy","ga","gd","gl","gu","he","hi","hr","hu","hy","id","is","it","ja","ka","kir","kk","km","kn","ko","ku","la","lo","lt","lv","me","mhr","mk","ml","mm","mn","mr","mrj","ms","mt","mwl","my","mya","ne","nl","nn","no","oci","pa","pl","ps","pt-br-dev","pt-br","pt","ro","ru","rue","rup","sah","si","sk","sl","snd","so","sq","sr","su","sv","sw","ta","te","th","tir","tl","tlh","tr","ug","uk","ur","uz","vi","xmf","yi","yo","yor","zh-cn","zh-hk","zh-sg","zh-tw","zh","en","sr_latin"],"description":"Locale for the user.","type":"string"},"nickname":{"required":false,"description":"The nickname for the user.","type":"string"},"slug":{"required":false,"description":"An alphanumeric identifier for the user.","type":"string"},"roles":{"required":false,"description":"Roles assigned to the user.","type":"array","items":{"type":"string"}},"password":{"required":false,"description":"Password for the user (never included).","type":"string"},"meta":{"required":false,"description":"Meta fields.","type":"object"}}},{"methods":["DELETE"],"args":{"force":{"required":false,"default":false,"description":"Required to be true, as users do not support trashing.","type":"boolean"},"reassign":{"required":true,"description":"Reassign the deleted user's posts and links to this user ID.","type":"integer"}}}],"_links":{"self":"https:\/\/public-api.wordpress.com\/wp\/v2\/sites\/48514416\/users\/me"}},"\/wp\/v2\/sites\/48514416\/comments":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST"],"endpoints":[{"methods":["GET"],"args":{"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"},"page":{"required":false,"default":1,"description":"Current page of the collection.","type":"integer"},"per_page":{"required":false,"default":10,"description":"Maximum number of items to be returned in result set.","type":"integer"},"search":{"required":false,"description":"Limit results to those matching a string.","type":"string"},"after":{"required":false,"description":"Limit response to comments published after a given ISO8601 compliant date.","type":"string"},"author":{"required":false,"description":"Limit result set to comments assigned to specific user IDs. Requires authorization.","type":"array","items":{"type":"integer"}},"author_exclude":{"required":false,"description":"Ensure result set excludes comments assigned to specific user IDs. Requires authorization.","type":"array","items":{"type":"integer"}},"author_email":{"required":false,"description":"Limit result set to that from a specific author email. Requires authorization.","type":"string"},"before":{"required":false,"description":"Limit response to comments published before a given ISO8601 compliant date.","type":"string"},"exclude":{"required":false,"default":[],"description":"Ensure result set excludes specific IDs.","type":"array","items":{"type":"integer"}},"include":{"required":false,"default":[],"description":"Limit result set to specific IDs.","type":"array","items":{"type":"integer"}},"offset":{"required":false,"description":"Offset the result set by a specific number of items.","type":"integer"},"order":{"required":false,"default":"desc","enum":["asc","desc"],"description":"Order sort attribute ascending or descending.","type":"string"},"orderby":{"required":false,"default":"date_gmt","enum":["date","date_gmt","id","include","post","parent","type"],"description":"Sort collection by object attribute.","type":"string"},"parent":{"required":false,"default":[],"description":"Limit result set to comments of specific parent IDs.","type":"array","items":{"type":"integer"}},"parent_exclude":{"required":false,"default":[],"description":"Ensure result set excludes specific parent IDs.","type":"array","items":{"type":"integer"}},"post":{"required":false,"default":[],"description":"Limit result set to comments assigned to specific post IDs.","type":"array","items":{"type":"integer"}},"status":{"required":false,"default":"approve","description":"Limit result set to comments assigned a specific status. Requires authorization.","type":"string"},"type":{"required":false,"default":"comment","description":"Limit result set to comments assigned a specific type. Requires authorization.","type":"string"},"password":{"required":false,"description":"The password for the post if it is password protected.","type":"string"}}},{"methods":["POST"],"args":{"author":{"required":false,"description":"The ID of the user object, if author was a user.","type":"integer"},"author_email":{"required":false,"description":"Email address for the object author.","type":"string"},"author_ip":{"required":false,"description":"IP address for the object author.","type":"string"},"author_name":{"required":false,"description":"Display name for the object author.","type":"string"},"author_url":{"required":false,"description":"URL for the object author.","type":"string"},"author_user_agent":{"required":false,"description":"User agent for the object author.","type":"string"},"content":{"required":false,"description":"The content for the object.","type":"object"},"date":{"required":false,"description":"The date the object was published, in the site's timezone.","type":"string"},"date_gmt":{"required":false,"description":"The date the object was published, as GMT.","type":"string"},"parent":{"required":false,"default":0,"description":"The ID for the parent of the object.","type":"integer"},"post":{"required":false,"default":0,"description":"The ID of the associated post object.","type":"integer"},"status":{"required":false,"description":"State of the object.","type":"string"},"meta":{"required":false,"description":"Meta fields.","type":"object"}}}],"_links":{"self":"https:\/\/public-api.wordpress.com\/wp\/v2\/sites\/48514416\/comments"}},"\/wp\/v2\/sites\/48514416\/comments\/(?P<id>[\\d]+)":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST","PUT","PATCH","DELETE"],"endpoints":[{"methods":["GET"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"context":{"required":false,"default":"view","enum":["view","embed","edit"],"description":"Scope under which the request is made; determines fields present in response.","type":"string"},"password":{"required":false,"description":"The password for the parent post of the comment (if the post is password protected).","type":"string"}}},{"methods":["POST","PUT","PATCH"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"author":{"required":false,"description":"The ID of the user object, if author was a user.","type":"integer"},"author_email":{"required":false,"description":"Email address for the object author.","type":"string"},"author_ip":{"required":false,"description":"IP address for the object author.","type":"string"},"author_name":{"required":false,"description":"Display name for the object author.","type":"string"},"author_url":{"required":false,"description":"URL for the object author.","type":"string"},"author_user_agent":{"required":false,"description":"User agent for the object author.","type":"string"},"content":{"required":false,"description":"The content for the object.","type":"object"},"date":{"required":false,"description":"The date the object was published, in the site's timezone.","type":"string"},"date_gmt":{"required":false,"description":"The date the object was published, as GMT.","type":"string"},"parent":{"required":false,"description":"The ID for the parent of the object.","type":"integer"},"post":{"required":false,"description":"The ID of the associated post object.","type":"integer"},"status":{"required":false,"description":"State of the object.","type":"string"},"meta":{"required":false,"description":"Meta fields.","type":"object"}}},{"methods":["DELETE"],"args":{"id":{"required":false,"description":"Unique identifier for the object.","type":"integer"},"force":{"required":false,"default":false,"description":"Whether to bypass trash and force deletion.","type":"boolean"},"password":{"required":false,"description":"The password for the parent post of the comment (if the post is password protected).","type":"string"}}}]},"\/wp\/v2\/sites\/48514416\/settings":{"namespace":"wp\/v2/sites/48514416","methods":["GET","POST","PUT","PATCH"],"endpoints":[{"methods":["GET"],"args":[]},{"methods":["POST","PUT","PATCH"],"args":{"title":{"required":false,"description":"Site title.","type":"string"},"description":{"required":false,"description":"Site tagline.","type":"string"},"timezone":{"required":false,"description":"A city in the same timezone as you.","type":"string"},"date_format":{"required":false,"description":"A date format for all date strings.","type":"string"},"time_format":{"required":false,"description":"A time format for all time strings.","type":"string"},"start_of_week":{"required":false,"description":"A day number of the week that the week should start on.","type":"integer"},"language":{"required":false,"description":"WordPress locale code.","type":"string"},"use_smilies":{"required":false,"description":"Convert emoticons like :-) and :-P to graphics on display.","type":"boolean"},"default_category":{"required":false,"description":"Default post category.","type":"integer"},"default_post_format":{"required":false,"description":"Default post format.","type":"string"},"posts_per_page":{"required":false,"description":"Blog pages show at most.","type":"integer"},"default_ping_status":{"required":false,"enum":["open","closed"],"description":"Allow link notifications from other blogs (pingbacks and trackbacks) on new articles.","type":"string"},"default_comment_status":{"required":false,"enum":["open","closed"],"description":"Allow people to post comments on new articles.","type":"string"}}}],"_links":{"self":"https:\/\/public-api.wordpress.com\/wp\/v2\/sites\/48514416\/settings"}}}}
EOF;
		wp_add_inline_script( 'wp-api', sprintf(
			'wpApiSettings.cacheSchema = true; wpApiSettings.schema = %s;',
			// wp_json_encode( $schema_response->get_data() )
			$schema_data
		), 'before' );
	// } else {
	// 	print_r( $schema_response );
	// 	die();
	// }
}


/**
 * Get post to edit.
 *
 * @param int $post_id Post ID to edit.
 * @return array|WP_Error The post resource data or a WP_Error on failure.
 */
function gutenberg_get_post_to_edit( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return new WP_Error( 'post_not_found', __( 'Post not found.', 'gutenberg' ) );
	}

	$post_type_object = get_post_type_object( $post->post_type );
	if ( ! $post_type_object ) {
		return new WP_Error( 'unrecognized_post_type', __( 'Unrecognized post type.', 'gutenberg' ) );
	}

	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return new WP_Error( 'unauthorized_post_type', __( 'Unauthorized post type.', 'gutenberg' ) );
	}

	return gutenberg_hack_post_into_rest_response( $post );

	$request = new WP_REST_Request(
		'GET',
		sprintf( '/wp/v2/%s/%d', ! empty( $post_type_object->rest_base ) ? $post_type_object->rest_base : $post_type_object->name, $post->ID )
	);
	$request->set_param( 'context', 'edit' );
	$response = rest_do_request( $request );
	if ( $response->is_error() ) {
		return $response->as_error();
	}
	return rest_get_server()->response_to_data( $response, false );
}

/**
 * Handles the enqueueing of block scripts and styles that are common to both
 * the editor and the front-end.
 *
 * Note: This function must remain *before*
 * `gutenberg_editor_scripts_and_styles` so that editor-specific stylesheets
 * are loaded last.
 *
 * @since 0.4.0
 */
function gutenberg_common_scripts_and_styles() {
	// Enqueue basic styles built out of Gutenberg through `npm build`.
	wp_enqueue_style( 'wp-blocks' );

	/*
	 * Enqueue block styles built through plugins.  This lives in a separate
	 * action for a couple of reasons: (1) we want to load these assets
	 * (usually stylesheets) in *both* frontend and editor contexts, and (2)
	 * one day we may need to be smarter about whether assets are included
	 * based on whether blocks are actually present on the page.
	 */

	/**
	 * Fires after enqueuing block assets for both editor and front-end.
	 *
	 * Call `add_action` on any hook before 'wp_enqueue_scripts'.
	 *
	 * In the function call you supply, simply use `wp_enqueue_script` and
	 * `wp_enqueue_style` to add your functionality to the Gutenberg editor.
	 *
	 * @since 0.4.0
	 */
	do_action( 'enqueue_block_assets' );
}
add_action( 'wp_enqueue_scripts', 'gutenberg_common_scripts_and_styles' );
add_action( 'admin_enqueue_scripts', 'gutenberg_common_scripts_and_styles' );

/**
 * Returns a default color palette.
 *
 * @return array Color strings in hex format.
 *
 * @since 0.7.0
 */
function gutenberg_color_palette() {
	return array(
		'#f78da7',
		'#cf2e2e',
		'#ff6900',
		'#fcb900',
		'#7bdcb5',
		'#00d084',
		'#8ed1fc',
		'#0693e3',
		'#eee',
		'#abb8c3',
		'#313131',
	);
}

/**
 * Scripts & Styles.
 *
 * Enqueues the needed scripts and styles when visiting the top-level page of
 * the Gutenberg editor.
 *
 * @since 0.1.0
 *
 * @param string $hook Screen name.
 */
function gutenberg_editor_scripts_and_styles( $hook ) {
	if ( ! empty( $hook ) && ! preg_match( '/(toplevel|gutenberg)_page_gutenberg(-demo)?/', $hook, $page_match ) ) {
		return;
	}

	$is_demo = isset( $page_match[2] );

	wp_add_inline_script(
		'editor', 'window.wp.oldEditor = window.wp.editor;', 'after'
	);

	gutenberg_extend_wp_api_backbone_client();

	// The editor code itself.
	wp_enqueue_script(
		'wp-editor',
		gutenberg_url( 'editor/build/index.js' ),
		array( 'wp-api', 'wp-date', 'wp-i18n', 'wp-blocks', 'wp-element', 'wp-components', 'wp-utils', 'word-count', 'editor', 'heartbeat' ),
		filemtime( gutenberg_dir_path() . 'editor/build/index.js' ),
		true // enqueue in the footer.
	);

	gutenberg_fix_jetpack_freeform_block_conflict();
	wp_localize_script( 'wp-editor', 'wpEditorL10n', array(
		'tinymce' => array(
			'baseURL'  => includes_url( 'js/tinymce' ),
			'suffix'   => SCRIPT_DEBUG ? '' : '.min',
			'settings' => apply_filters( 'tiny_mce_before_init', array(
				'external_plugins' => apply_filters( 'mce_external_plugins', array() ),
				'plugins'          => array_unique( apply_filters( 'tiny_mce_plugins', array(
					'charmap',
					'colorpicker',
					'hr',
					'lists',
					'media',
					'paste',
					'tabfocus',
					'textcolor',
					'fullscreen',
					'wordpress',
					'wpautoresize',
					'wpeditimage',
					'wpemoji',
					'wpgallery',
					'wplink',
					'wpdialogs',
					'wptextpattern',
					'wpview',
				) ) ),
				'toolbar1'         => implode( ',', array_merge( apply_filters( 'mce_buttons', array(
					'formatselect',
					'bold',
					'italic',
					'bullist',
					'numlist',
					'blockquote',
					'alignleft',
					'aligncenter',
					'alignright',
					'link',
					'unlink',
					'wp_more',
					'spellchecker',
				), 'editor' ), array( 'kitchensink' ) ) ),
				'toolbar2'         => implode( ',', apply_filters( 'mce_buttons_2', array(
					'strikethrough',
					'hr',
					'forecolor',
					'pastetext',
					'removeformat',
					'charmap',
					'outdent',
					'indent',
					'undo',
					'redo',
					'wp_help',
				), 'editor' ) ),
				'toolbar3'         => implode( ',', apply_filters( 'mce_buttons_3', array(), 'editor' ) ),
				'toolbar4'         => implode( ',', apply_filters( 'mce_buttons_4', array(), 'editor' ) ),
			), 'editor' ),
		),
	) );

	// Register `wp-utils` as a dependency of `word-count` to ensure that
	// `wp-utils` doesn't clobbber `word-count`.  See WordPress/gutenberg#1569.
	$word_count_script = wp_scripts()->query( 'word-count' );
	array_push( $word_count_script->deps, 'wp-utils' );

	// Parse post type from parameters.
	$post_type = null;
	if ( ! isset( $_GET['post_type'] ) ) {
		$post_type = 'post';
	} else {
		$post_types = get_post_types( array(
			'show_ui' => true,
		) );

		if ( in_array( $_GET['post_type'], $post_types ) ) {
			$post_type = $_GET['post_type'];
		} else {
			wp_die( __( 'Invalid post type.', 'gutenberg' ) );
		}
	}

	// Parse post ID from parameters.
	$post_id = 1396; // TODO: hack
	if ( isset( $_GET['post_id'] ) && (int) $_GET['post_id'] > 0 ) {
		$post_id = (int) $_GET['post_id'];
	}

	// Create an auto-draft if new post.
	if ( ! $post_id ) {
		$default_post_to_edit = get_default_post_to_edit( $post_type, true );
		$post_id              = $default_post_to_edit->ID;
	}

	// Generate API-prepared post from post ID.
	$post_to_edit = gutenberg_get_post_to_edit( $post_id );
	if ( is_wp_error( $post_to_edit ) ) {
		wp_die( $post_to_edit->get_error_message() );
	}

	// Set initial title to empty string for auto draft for duration of edit.
	$is_new_post = 'auto-draft' === $post_to_edit['status'];
	if ( $is_new_post ) {
		$default_title         = apply_filters( 'default_title', '' );
		$post_to_edit['title'] = array(
			'raw'      => $default_title,
			'rendered' => apply_filters( 'the_title', $default_title, $post_id ),
		);
	}

	// Preload common data.
	$preload_paths = array(
		'/wp/v2/users/me?context=edit',
		'/wp/v2/taxonomies?context=edit',
		gutenberg_get_rest_link( $post_to_edit, 'about', 'edit' ),
	);
	if ( ! $is_new_post ) {
		$preload_paths[] = gutenberg_get_rest_link( $post_to_edit, 'version-history' );
	}
	$preload_data = array_reduce(
		$preload_paths,
		'gutenberg_preload_api_request',
		array()
	);
	wp_add_inline_script(
		'wp-components',
		sprintf( 'window._wpAPIDataPreload = %s', wp_json_encode( $preload_data ) ),
		'before'
	);

	// Initialize the post data.
	wp_add_inline_script(
		'wp-editor',
		'window._wpGutenbergPost = ' . wp_json_encode( $post_to_edit ) . ';'
	);

	// Prepopulate with some test content in demo.
	if ( $is_new_post && $is_demo ) {
		wp_add_inline_script(
			'wp-editor',
			file_get_contents( gutenberg_dir_path() . 'post-content.js' )
		);
	}

	// Prepare Jed locale data.
	$locale_data = gutenberg_get_jed_locale_data( 'gutenberg' );
	wp_add_inline_script(
		'wp-editor',
		'wp.i18n.setLocaleData( ' . json_encode( $locale_data ) . ' );',
		'before'
	);

	// Preload server-registered block schemas.
	$block_registry = WP_Block_Type_Registry::get_instance();
	$schemas        = array();
	foreach ( $block_registry->get_all_registered() as $block_name => $block_type ) {
		if ( isset( $block_type->attributes ) ) {
			$schemas[ $block_name ] = $block_type->attributes;
		}
	}
	wp_localize_script( 'wp-blocks', '_wpBlocksAttributes', $schemas );

	// Initialize the editor.
	$gutenberg_theme_support = get_theme_support( 'gutenberg' );
	$color_palette           = gutenberg_color_palette();

	if ( ! empty( $gutenberg_theme_support[0]['colors'] ) ) {
		$color_palette = $gutenberg_theme_support[0]['colors'];
	}

	$editor_settings = array(
		'wideImages' => ! empty( $gutenberg_theme_support[0]['wide-images'] ),
		'colors'     => $color_palette,
	);

	wp_add_inline_script( 'wp-editor', 'wp.api.init().done( function() {'
		. 'wp.editor.createEditorInstance( \'editor\', window._wpGutenbergPost, ' . json_encode( $editor_settings ) . ' ); '
		. '} );'
	);

	/**
	 * Scripts
	 */
	wp_enqueue_media( array(
		'post' => $post_to_edit['id'],
	) );
	wp_enqueue_editor();

	/**
	 * Styles
	 */

	wp_enqueue_style(
		'wp-editor-font',
		'https://fonts.googleapis.com/css?family=Noto+Serif:400,400i,700,700i'
	);
	wp_enqueue_style(
		'wp-editor',
		gutenberg_url( 'editor/build/style.css' ),
		array( 'wp-components', 'wp-blocks', 'wp-edit-blocks' ),
		filemtime( gutenberg_dir_path() . 'editor/build/style.css' )
	);

	/**
	 * Fires after block assets have been enqueued for the editing interface.
	 *
	 * Call `add_action` on any hook before 'admin_enqueue_scripts'.
	 *
	 * In the function call you supply, simply use `wp_enqueue_script` and
	 * `wp_enqueue_style` to add your functionality to the Gutenberg editor.
	 *
	 * @since 0.4.0
	 */
	do_action( 'enqueue_block_editor_assets' );
}
add_action( 'admin_enqueue_scripts', 'gutenberg_editor_scripts_and_styles' );
