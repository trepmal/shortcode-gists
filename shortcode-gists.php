<?php
/*
 * Plugin Name: Shortcode Gists
 * Plugin URI: trepmal.com
 * Description: Auto-create gists with [gist] shortcode
 * Version:
 * Author: Kailey Lampert
 * Author URI: kaileylampert.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * TextDomain: shortcode-gists
 * DomainPath:
 * Network:
 */


$shortcode_gists = new Shortcode_Gists;

class Shortcode_Gists {

	var $cpt_slug = 'sc_gist';

	function __construct() {

		add_filter( 'init',                     array( $this, 'init' ) );

		add_filter( 'admin_init',               array( $this, 'admin_init' ) );

		// add a field to the profile page
		add_action( 'personal_options',         array( $this, 'add_profile_options' ) );

		// save
		// add_action( 'personal_options_update',  array( $this, 'save_custom_profile_fields'  ) );
		// add_action( 'edit_user_profile_update', array( $this, 'save_custom_profile_fields'  ) );


		add_filter('the_content',               array( $this, 'the_content' ), 5 );
	}

	/*
	 * CPT for storage
	 *
	 *
	 */


	function init() {
		$labels = array(
			'name'               => _x( 'Gists', 'post type general name', 'shortcode-gists' ),
			'singular_name'      => _x( 'Gist', 'post type singular name', 'shortcode-gists' ),
			'menu_name'          => _x( 'Gists', 'admin menu', 'shortcode-gists' ),
			'name_admin_bar'     => _x( 'Gist', 'add new on admin bar', 'shortcode-gists' ),
			'add_new'            => _x( 'Add New', 'gist', 'shortcode-gists' ),
			'add_new_item'       => __( 'Add New Gist', 'shortcode-gists' ),
			'new_item'           => __( 'New Gist', 'shortcode-gists' ),
			'edit_item'          => __( 'Edit Gist', 'shortcode-gists' ),
			'view_item'          => __( 'View Gist', 'shortcode-gists' ),
			'all_items'          => __( 'All Gists', 'shortcode-gists' ),
			'search_items'       => __( 'Search Gists', 'shortcode-gists' ),
			'parent_item_colon'  => __( 'Parent Gists:', 'shortcode-gists' ),
			'not_found'          => __( 'No books found.', 'shortcode-gists' ),
			'not_found_in_trash' => __( 'No books found in Trash.', 'shortcode-gists' )
		);

		register_post_type( $this->cpt_slug, array(
			'labels'       => $labels,
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'options-general.php'
		) );
	}

	/*
	 * General Settings fields
	 *
	 *
	 */


	function admin_init() {
		register_setting( 'general', 'gistclient', array( $this, 'gist_client_sanitize' ) );
		add_settings_field('gistclient', __( 'Gist Client', 'shortcode-gists' ) . '<br /><a href="https://github.com/settings/applications/new">' . __('Register', 'shortcode-gists') . '</a>', array( &$this, 'fields'), 'general' );
	}

	function fields( ) {
		$client = get_option( 'gistclient', array( 'id' => '', 'secret' => '' ) );
		?><a name="gist"></a>
		<p><label><?php _e( 'Authorization callback URL:', 'shortcode-gists' ); ?><br />
			<input type='text' readonly='readonly' value='<?php echo esc_attr( admin_url() ); ?>' class='regular-text code' /></label></p>
		<p><label><?php _e( 'Client ID:', 'shortcode-gists' ); ?><br />
			<input type='text' name='gistclient[id]' value='<?php echo esc_attr( $client['id'] ); ?>' class='regular-text code' /></label></p>
		<p><label><?php _e( 'Client Secret:', 'shortcode-gists' ); ?><br />
			<input type='text' name='gistclient[secret]' value='<?php echo esc_attr( $client['secret'] ); ?>' class='regular-text code' /></label></p>
		<?php
	}

	function gist_client_sanitize( $input ) {
		$input['id']           = strip_tags( $input['id'] );
		$input['secret']       = strip_tags( $input['secret'] );

		return $input;
	}




	/*
	 * Profile field
	 *
	 *
	 */


	function add_profile_options( $profileuser ) {
		?>
		<tr>
			<th scope="row"><?php _e( 'Gist Authorization', 'shortcode-gists' ); ?></th>
			<td>

			<?php
			$client = get_option( 'gistclient', array( 'id' => '', 'secret' => '' ) );
			if ( empty( $client['id'] ) ) {
				?>
					<a class="button disabled" href="#"><?php _e( 'Authorize', 'shortcode-gists' ); ?></a><br />
					<em><a href="<?php echo admin_url( 'options-general.php#gist' ); ?>"><?php _e( 'Please configure a Github Client', 'shortcode-gists' ); ?></a></em>
				<?php
			} else {

				// get saved token
				$access_token = get_user_meta( $profileuser->ID, 'gist_access_token', true );

				// redirect uri
				$this_page = 'http'. (is_ssl()?'s':''). '://'. $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
				$this_page = remove_query_arg( 'code', $this_page );

				?><a class="button" href="https://github.com/login/oauth/authorize?client_id=<?php echo $client['id']; ?>&amp;redirect_uri=<?php echo $this_page; ?>&amp;scope=gist"><?php echo empty( $access_token ) ? __( 'Authorize', 'shortcode-gists' ) : __( 'Reauthorize', 'shortcode-gists' ); ?></a><?php

				// code is set when github redirect back here
				if ( isset( $_GET['code'] ) ) {

					$args = array(
						'body' => array(
							'client_id'     => $client['id'],
							'client_secret' => $client['secret'],
							'code'          => $_GET['code'],
						),
					);
					$response = wp_remote_post( 'https://github.com/login/oauth/access_token', $args );
					$body = wp_remote_retrieve_body( $response );

					$body_args = wp_parse_args( $body );

					if ( isset( $body_args['access_token'] ) ) {
						$access_token = $body_args['access_token'];
						update_user_meta( $profileuser->ID, 'gist_access_token', $access_token );
					}

				}

				// if token is set, try to get user info
				if ( ! empty( $access_token ) ) {
					$args = array(
						'headers' => array(
							'Authorization' => "token $access_token",
							'Accept' => 'application/json'
						)
					);
					$response = wp_remote_get( 'https://api.github.com/user', $args );
					$body = wp_remote_retrieve_body( $response );
					$body_args = json_decode( $body );

					// if message is set, there was a failure
					if ( isset( $body_args->message ) ) {
						echo "<p>{$body_args->message}</p>";
					} else {
						echo "<p><img width='100' src='{$body_args->avatar_url}' /></p>";
					}
				}

			}
			?>
			</td>
		</tr>
		<?php
	}




	/*
	 * Content filter/shortcode
	 *
	 *
	 */



	function the_content( $c ) {
		global $shortcode_tags;
		// backup shortcodes
		$shortcode_tags_copy = $shortcode_tags;
		// remove exising shortcodes
		$shortcode_tags = array();

		// add ours
		add_shortcode( 'gist', array( $this, 'gist' ) );
		$c = do_shortcode( $c );
		remove_shortcode( 'gist', array( $this, 'gist' ) );

		// restore
		$shortcode_tags = $shortcode_tags_copy;
		return $c;
	}

	function gist( $attr, $content ) {
		$attr = shortcode_atts( array(
			'filename'    => 'file.txt',
			'description' => '',
			'gisturl'     => '',
		), $attr );

		global $post;
		$post_author_id = $post->post_author;
		$access_token = get_user_meta( $post_author_id, 'gist_access_token', true );

		// if post author doesn't have token, bail
		if ( ! $access_token ) {
			$content = trim( $content );
			return "<pre>{$content}</pre>";
		}

		$reduced_content = preg_replace( '/\s/', '', $content );
		$content_hash = md5( $reduced_content );

		if ( null !== ( $gist = $this->get_gist( $content_hash ) ) ) {
			// echo '//fetch//';

			$embed_url_base = $gist->post_content;
		} else {
			// echo '//make//';

			$args = array(
				'body' => json_encode( array(
					'description' => strip_tags( $attr['description'] ),
					'public' => true,
					'files'  => array(
						$attr['filename'] => array(
							'content' => trim( html_entity_decode( $content ) )
						)
					) )
				),
				'headers' => array(
					'Authorization' => "token $access_token",
					'Accept'        => 'application/json'
				)
			);

			$response  = wp_remote_post( 'https://api.github.com/gists', $args );
			$body      = wp_remote_retrieve_body( $response );
			$body_args = json_decode( $body );

			wp_insert_post( array(
				'post_title'   => $content_hash,
				'post_type'    => $this->cpt_slug,
				'post_status'  => 'publish',
				'post_content' => $body_args->html_url
			) );

			$embed_url_base = $body_args->html_url;
		}

		$embed_url_base = esc_url( $embed_url_base );
		return "<script src='{$embed_url_base}.js'></script>";

	}

	public function get_gist( $hash ) {
		return get_page_by_title( $hash, OBJECT, $this->cpt_slug );
	}

}

