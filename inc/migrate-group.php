<?php
/**
 * Migrate groups from WordPress to Hugo data as YAML
 */
namespace HH_Hugo;

class Migrate_Group {

	/**
	 * @var WP_Post WordPress post object to migrate
	 */
	protected $page = null;

	/**
	 * @var string Geocoding API with placeholder for city name
	 */
	protected $geo_api_url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/%s.json?access_token=%s';

	/**
	 * Instantiate and migrate
	 *
	 * @param string|int|WP_Post $group WordPress post ID or slug or WP_Post
	 * @param int $parent_id ID of WordPress parent page
	 * @param bool $dry_run If true, mock migration only. Defaults to false.
	 */
	function __construct( $group, $parent_id = 0, $dry_run = false ) {
		$content_dir = defined( 'HH_HUGO_UNIT_TESTS_RUNNING' ) ? 'hugo-groups-test' : 'hugo-groups';
		$this->dest_dir = trailingslashit( HH_HUGO_COMMAND_DIR ) . $content_dir;

		// just return the class for unit testing
		if ( empty( $group ) && defined( 'HH_HUGO_UNIT_TESTS_RUNNING' ) ) {
			return;
		}

		$is_wp_post = is_object( $group ) && 'WP_Post' === get_class( $group );
		$this->page = $is_wp_post ? $group : $this->get_page( $group, $parent_id );

		$this->data = $this->setup_data( $this->page->ID );
		$this->filename = sanitize_title( $this->page->post_title ) . '.yml';

		if ( ! $dry_run ) {
			$written = $this->write_file( $this->filename, $this->data );
			if ( ! $written ) {
				$this->result = array( 'error' => 'Error writing file: ' . $this->filename );
				return;
			}
		}
		$this->result = array( 'success' => 'Migrated ' . $this->filename );
	}

	/**
	 * Instantiate and migrate
	 *
	 * @param string|int $group WordPress post ID or slug
	 * @param int $parent_id ID of WordPress parent page
	 * @return WP_Post
	 */
	public function get_page( $group, $parent_id ) {
		$args = array(
			'numberposts' => 1,
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_parent' => $parent_id,
		);

		if ( is_numeric( $group ) ) {
			$args['page_id'] = intval( $group );
		} else {
			$args['name'] = $group;
		}

		$pages = get_posts( $args );
		if ( empty( $pages ) ) {
			$this->result = array( 'error' => 'Could not find group page for: ' . $group );
			return null;
		}

		return $pages[0];
	}

	/**
	 * Setup data for conversion to YAML
	 *
	 * @param int $page Page ID to migrate
	 * @return array
	 */
	public function setup_data( $page_id ) {
		$title = get_the_title( $page_id );
		$data = array(
			'label' => $title,
		);

		if ( $coordinates = $this->get_coordinates( $title ) ) {
			$data['coordinates'] = $coordinates;
		}

		if ( $external_url = $this->get_external_url( $page_id ) ) {
			$data['externalUrl'] = $external_url;
		}

		return $data;
	}

	/**
	 * Turn city / post title into search query
	 *
	 * @param string $city
	 * @return string
	 */
	public function city_search( $city ) {
		$city = preg_replace( '/[^\w]+/', '+', strtolower( $city ) );
		return trim( $city, '+' );
	}

	/**
	 * Get lat,lon for city name
	 *
	 * @param string $city
	 * @return null|array Lat,Lon array or null if failure
	 */
	public function get_coordinates( $city ) {
		$token_path = trailingslashit( HH_HUGO_COMMAND_DIR ) . 'geo-api-token.txt';
		if ( ! file_exists( $token_path ) ) {
			return null;
		}
		$token = file_get_contents( $token_path );
		if ( empty( $token ) ) {
			return null;
		}

		$request_url = sprintf( $this->geo_api_url, $this->city_search( $city ), $token );
		$response = wp_remote_get( $request_url );

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['features'][0]['center'] ) ) {
			return null;
		}

		// Carmen GeoJSON provides [lon, lat] but Leaflet.js wants [lat, lon]
		return array_reverse( $body['features'][0]['center'] );
	}

	/**
	 * Get external URL from options stored in Simple 301 Redirects plugin
	 *
	 * @param int $page_id
	 * @return string|null URL or null if not found
	 */
	public function get_external_url( $page_id ) {
		$redirects = get_option( '301_redirects', array() );

		// search in redirects with and without leading slash
		$uri = get_page_uri( $page_id );
		if ( ! empty( $redirects[ $uri ] ) ) {
			return $redirects[ $uri ];
		}

		if ( ! empty( $redirects[ '/' . $uri ] ) ) {
			return $redirects[ '/' . $uri ];
		}

		return null;
	}

	/**
	 * Emit yaml with a little bit of processing for Hugo's sake
	 *
	 * @param array $data Group data
	 * @return string YAML data
	 */
	public function get_yaml( $data ) {
		$yaml = yaml_emit( $data, YAML_UTF8_ENCODING );
		return trim( preg_replace( array( '/^-+\n/', '/\.+$/'), '', $yaml ) );
	}

	/**
	 * Write group data file
	 *
	 * @param string $filename
	 * @param array $data
	 * @return bool
	 */
	public function write_file( $filename, $data ) {
		if ( ! is_dir( $this->dest_dir ) ) {
			mkdir( $this->dest_dir, 0755, true );
		}

		return file_put_contents(
			trailingslashit( $this->dest_dir ) . $filename,
			$this->get_yaml( $data )
		);
	}

	/**
	 * Getter for migration results
	 *
	 * @return array
	 */
	public function result() {
		return $this->result;
	}
}
