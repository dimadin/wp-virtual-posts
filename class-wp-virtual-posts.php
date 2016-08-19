<?php
/**
 * Simple and standardized way of creating virtual, on-the-fly posts.
 *
 * @package   WP_Virtual_Posts
 * @version   1.0
 * @author    Milan Dinić <blog.milandinic.com>
 * @copyright Copyright (c) 2016, Milan Dinić
 * @license   http://opensource.org/licenses/gpl-2.0.php GPL v2 or later
 * @link      https://github.com/dimadin/wp-virtual-posts
 */

/* Exit if accessed directly */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_Virtual_Posts' ) ) :
/**
 * Simple and standardized way of creating virtual, on-the-fly posts.
 *
 * This modifies database response when querying for posts is finished. Intented
 * use is when there are no posts in the database so it prevents it from giving
 * 404 error, while still using existing theme templates for content.
 *
 * Inspired by http://davejesch.com/2012/12/creating-virtual-pages-in-wordpress/
 */
class WP_Virtual_Posts {
	/**
	 * An array of post objects.
	 *
	 * This should be objects that can be used in get_post().
	 *
	 * @var array
	 */
	public $posts = array();

	/**
	 * An array of query flags and its values.
	 *
	 * WP_Query sets boolean values for each query flag to show what type of
	 * request this is. Items in this array will modify any WP_Query property.
	 * See WP_Query::init_query_flags() for a list of properties.
	 *
	 * @var array
	 */
	public $query_flags = array();

	/**
	 * Constructor.
	 *
	 * @param array $posts       Optional. An array arrays of post elements.
	 * @param array $query_flags Optional. An array of query flags and its values.
	 */
	public function __construct( $posts = array(), $query_flags = array() ) {
		// Prepare and add post objects if post arguments are passed
		foreach ( $posts as $post ) {
			$this->add_post( $post );
		}

		// Query flags are always initialy set to passed values
		$this->query_flags = $query_flags;

		// Register hook that modifies result of the query
		add_filter( 'the_posts', array( $this, 'the_posts' ), 10, 2 );
	}

	/**
	 * Add post object.
	 *
	 * @param array $args {
	 *     An array of elements that make up a post.
	 *
	 *     @type int    $ID                    The post ID. Default is negative value of sum
	 *                                         of current time and existing number of posts.
	 *     @type int    $post_author           The ID of the user who added the post. Default is 0.
	 *     @type string $post_date             The date of the post. Default is the current time.
	 *     @type string $post_date_gmt         The date of the post in the GMT timezone. Default is
	 *                                         the value of `$post_date` in GMT.
	 *     @type mixed  $post_content          The post content. Default empty.
	 *     @type string $post_content_filtered The filtered post content. Default empty.
	 *     @type string $post_title            The post title. Default empty.
	 *     @type string $post_excerpt          The post excerpt. Default empty.
	 *     @type string $post_status           The post status. Default 'publish'.
	 *     @type string $post_type             The post type. Default 'page'.
	 *     @type string $comment_status        Whether the post can accept comments. Accepts 'open' or 'closed'.
	 *                                         Default is 'closed'.
	 *     @type string $ping_status           Whether the post can accept pings. Accepts 'open' or 'closed'.
	 *                                         Default is 'closed'.
	 *     @type string $post_password         The password to access the post. Default empty.
	 *     @type string $post_name             The post name. Default empty.
	 *     @type string $to_ping               Space or carriage return-separated list of URLs to ping.
	 *                                         Default empty.
	 *     @type string $pinged                Space or carriage return-separated list of URLs that have
	 *                                         been pinged. Default empty.
	 *     @type string $post_modified         The date when the post was last modified. Default is
	 *                                         the value of `$post_date`.
	 *     @type string $post_modified_gmt     The date when the post was last modified in the GMT
	 *                                         timezone. Default is the value of `$post_date_gmt`.
	 *     @type int    $post_parent           Set this for the post it belongs to, if any. Default 0.
	 *     @type int    $menu_order            The order the post should be displayed in. Default 0.
	 *     @type string $post_mime_type        The mime type of the post. Default empty.
	 *     @type string $guid                  Global Unique ID for referencing the post. Default is
	 *                                         value of `$post_name` appended to the value of get_home_url().
	 * }
	 */
	public function add_post( $args ) {
		// Setup default post arguments
		$defaults = array(
			'post_name'             => '',
			'post_type'             => 'page',
			'post_title'            => '',
			'post_author'	          => 0,
			'post_date'             => current_time('mysql'),
			'post_excerpt'          => '',
			'post_status'           => 'publish',
			'comment_status'        => 'closed',
			'ping_status'           => 'closed',
			'post_password'         => '',
			'to_ping'               => '',
			'pinged'                => '',
			'post_content_filtered' => '',
			'post_mime_type'        => '',
			'post_parent'           => 0,
			'menu_order'            => 0,
		);

		$r = wp_parse_args( $args, $defaults );

		// Setup post ID if not passed
		if ( ! array_key_exists( 'ID', $r ) ) {
			// ID is negative value of sum of current time and existing number of posts
			$r['ID'] = time() + count( $this->posts );
		}

		// Setup post GMT date if not passed
		if ( ! array_key_exists( 'post_date_gmt', $r ) ) {
			// GMT date is converted from post date
			$r['post_date_gmt'] = get_gmt_from_date( $r['post_date'] );
		}

		// Setup post modified date if not passed
		if ( ! array_key_exists( 'post_modified', $r ) ) {
			// Modified date is the same as date of creation
			$r['post_modified'] = $r['post_date'];
		}

		// Setup post modified GMT date if not passed
		if ( ! array_key_exists( 'post_modified_gmt', $r ) ) {
			$r['post_modified_gmt'] = $r['post_date_gmt'];
		}

		// Setup post GUID if not passed
		if ( ! array_key_exists( 'guid', $r ) ) {
			// GUID is made when post slug is appended to site's URL
			$r['guid'] = get_home_url( '/', $r['post_name'] );
		}

		// Make an object from post arguments
		$post = (object) $r;

		// Append post object to existing array of posts
		$this->posts[] = $post;
	}

	/**
	 * Add query flag value.
	 *
	 * @param string $name  Query flag name.
	 * @param mixed  $value Query flag value.
	 */
	public function add_query_flag( $name, $value ) {
		$this->query_flags[ $name ] = $value;
	}

	/**
	 * Set each query flag to WP_Query object.
	 *
	 * @param WP_Query $wp_query WP_Query object whose properties should be set.
	 */
	public function fill_query_flags( $wp_query ) {
		foreach ( $this->query_flags as $key => $value ) {
			$wp_query->$key = $value;
		}
	}

	/**
	* Filter the array of retrieved posts after they've been fetched.
	*	*
	* @param array    $posts    The array of retrieved posts.
	* @param WP_Query $wp_query The WP_Query instance.
	* @return array $posts The array of posts.
	*/
	public function the_posts( $posts, $wp_query ) {
		// Add query flags that are set
		$this->fill_query_flags( $wp_query );

		return $this->posts;
	}
}
endif;
