<?php
/**
 * Plugin Name: WDS Instagram Widget
 * Description: Display your latest Instagrams in a sidebar widget.
 * Version: 1.2
 * Author: WebDevStudios
 * Author URI: http://webdevstudios.com
 * License: GPLv2
*/

/**
 * The WDS Instagram Widget Class.
 */
class WDS_Instagram_Widget extends WP_Widget {

	/**
	 * Plugin class
	 *
	 * @var   WDS_Instagram_Widget
	 * @since 0.1.2
	 */
	protected static $single_instance = null;

	/**
	 * Initialize the widget.
	 *
	 * @since 0.1.2
	 */
	public static function get_instance() {

		if ( null === self::$single_instance ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;
	}

	/**
	 * Contruct widget.
	 */
	public function __construct() {

		parent::__construct(
			'wds_instagram_widget', // Base ID.
			esc_html__( 'Instagram Widget', 'wds-instagram' ), // Widget Name.
			array( 'description' => esc_html__( 'Display your latest Instagrams in a sidebar widget.', 'wds-instagram' ) ) // The args.
		);
	}

	/**
	 * The hooks.
	 *
	 * @since 0.1.2
	 */
	public function hooks() {

		register_widget( 'WDS_Instagram_Widget' );

		// Authorization hooks.
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'authorize_instagram_account' ) );

		add_rewrite_endpoint( 'authorize_instagram', EP_PERMALINK );
	}

	/**
	 * Permit the authorization variables to be processed from Instagram.
	 *
	 * @since 1.2.0
	 *
	 * @param array $vars The registered query vars.
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'code';
		return $vars;
	}

	/**
	 * Authorize the Instgram account.
	 *
	 * @since 1.2.0
	 */
	public function authorize_instagram_account() {

		$pagename           = get_query_var( 'pagename' );
		$authorization_code = get_query_var( 'code' );

		if ( 'authorize_instagram' !== $pagename || ! $authorization_code ) {
			return;
		}

		$widget_settings = get_option( $this->option_name, array() );
		$widget_settings = ! empty( $widget_settings ) ? array_shift( $widget_settings ) : array();

		// Authenticate the authorization.
		$response = wp_remote_post( 'https://api.instagram.com/oauth/access_token', array(
			'timeout' => 30,
			'body'    => array(
				'client_id'     => isset( $widget_settings['client_id'] ) ? $widget_settings['client_id'] : '',
				'client_secret' => isset( $widget_settings['client_secret'] ) ? $widget_settings['client_secret'] : '',
				'grant_type'    => 'authorization_code',
				'code'          => $authorization_code,
				'redirect_uri'  => home_url( 'authorize_instagram' ),
			),
		) );

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! isset( $data->access_token ) ) {
			return;
		}

		update_option( 'wds_instagram_widget_access_token', sanitize_text_field( $data->access_token ) );

		wp_redirect( admin_url( 'widgets.php' ) );
		die();
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {

		// Get widget options.
		$title    = ( ! empty( $instance['title'] ) ) ? apply_filters( 'widget_title', $instance['title'] ) : '';
		$username = ( ! empty( $instance['username'] ) ) ? $instance['username'] : '';
		$hashtag  = ( ! empty( $instance['hashtag'] ) ) ? $instance['hashtag'] : '';

		// Get instagrams.
		$images = $this->get_instagrams( array(
			'count'       => $instance['count'],
			'hashtag'     => $hashtag,
			'flush_cache' => $instance['flush_cache'],
		) );

		if (
			( WP_DEBUG === defined( 'WP_DEBUG' ) && true ) &&
			( WP_DEBUG_DISPLAY !== defined( 'WP_DEBUG_DISPLAY' ) && false ) &&
			empty( $images )
		) {
			?>
			<div id="message" class="error"><p><?php esc_html_e( 'Error: We were unable to fetch your instagram feed.', 'wds-instagram' ); ?></p></div>
			<?php
			return;
		}

		if ( empty( $images ) ) {
			return;
		}

		// Display the instagram pics.
		// Allow the image resolution to be filtered to use any available image resolutions from Instagram.
		// low_resolution, thumbnail, standard_resolution.
		$image_res = apply_filters( 'wds_instagram_widget_image_resolution', 'standard_resolution' );

		echo $args['before_widget'];
		echo $args['before_title'] . esc_html( $title ) . $args['after_title'];

		?>
			<ul class="instagram-widget">
				<?php
					foreach ( $images as $key => $image ) {
						echo apply_filters( 'wds_instagram_widget_image_html', sprintf( '<li><a href="%1$s"><img class="instagram-image" src="%2$s" alt="%3$s" title="%3$s" /></a></li>',
							esc_attr( $image['link'] ),
							esc_url( $image['images'][ $image_res ]['url'] ),
							esc_attr( $image['caption'] )
						), $image );
					}
				?>
				<a href="<?php echo esc_url( 'https://instagram.com/' . $username ); ?>"><?php printf( esc_html__( 'Follow %1$s on Instagram', 'wds-instagram' ), esc_html( $username ) ); ?></a>
			</ul>
		<?php
		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form with defaults.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {

		// Get options or set defaults.
		$title         = ( ! empty( $instance['title'] ) ) ? $instance['title'] : '';
		$client_id     = ( ! empty( $instance['client_id'] ) ) ? $instance['client_id'] : '';
		$client_secret = ( ! empty( $instance['client_secret'] ) ) ? $instance['client_secret'] : '';
		$count         = ( ! empty( $instance['count'] ) ) ? $instance['count'] : '';
		$hashtag       = ( ! empty( $instance['hashtag'] ) ) ? $instance['hashtag'] : '';
		$flush_cache   = ( ! empty( $instance['flush_cache'] ) ) ? $instance['flush_cache'] : '';

		?>
			<h4><?php esc_html_e( 'Widget Setup', 'wds-instagram' ); ?>:</h4>
			<ol>
				<li><?php esc_html_e( 'Register a', 'wds-instagram' ); ?> <a href="http://instagram.com/developer/clients/manage/" target="_blank"><?php esc_html_e( 'New Instagram Client', 'wds-instagram' ); ?></a>. <?php esc_html_e( 'Be sure to provide', 'wds-instagram' ); ?> <strong><?php echo home_url( 'authorize_instagram' ); ?></strong> <?php esc_html_e( 'as the "Valid Redirect URI"', 'wds-instagram' ); ?>.</li>
				<li><?php esc_html_e( 'Enter the Client ID and Client Secret below and save.', 'wds-instagram' ); ?></li>
				<li><?php esc_html_e( 'Press the "Connect To Instagram" button to give your site access to your Instagram account.', 'wds-instagram' ); ?></li>
			</ol>
			<hr />
			<p><?php esc_html_e( 'Status', 'wds-instagram' ); ?>: <strong><?php echo $this->get_access_token() ? 'Connected' : 'Not Connected'; ?></strong></p>
			<?php
				// Check to see if we have an access_token.
				if ( $client_id && $client_secret ) {

					$authorization_url = add_query_arg( array(
						'client_id'     => esc_attr( $client_id ),
						'redirect_uri'  => home_url( 'authorize_instagram' ),
						'response_type' => 'code',
						'scope'         => 'basic+public_content',
					), 'https://api.instagram.com/oauth/authorize/' );
					?>
						<p><a class="button" href="<?php echo esc_url( $authorization_url ); ?>"><?php esc_html_e( 'Connect Your Instagram Account', 'wds-instagram' ); ?></a></p>
					<?php
				}
			?>
			<hr />
		<?php

		$this->form_input(
			array(
				'label'       => esc_html__( 'Widget Title:', 'wds-instagram' ),
				'name'        => $this->get_field_name( 'title' ),
				'id'          => $this->get_field_id( 'title' ),
				'type'        => 'text',
				'value'       => $title,
				'placeholder' => 'Instagram',
			)
		);

		$this->form_input(
			array(
				'label'       => esc_html__( 'Client ID:', 'wds-instagram' ),
				'name'        => $this->get_field_name( 'client_id' ),
				'id'          => $this->get_field_id( 'client_id' ),
				'type'        => 'text',
				'value'       => $client_id,
				'placeholder' => '',
			)
		);

		$this->form_input(
			array(
				'label'       => esc_html__( 'Client Secret:', 'wds-instagram' ),
				'name'        => $this->get_field_name( 'client_secret' ),
				'id'          => $this->get_field_id( 'client_secret' ),
				'type'        => 'text',
				'value'       => $client_secret,
				'placeholder' => '',
			)
		);

		$this->form_input(
			array(
				'label'       => esc_html__( 'Display a hashtag instead?', 'wds-instagram' ),
				'name'        => $this->get_field_name( 'hashtag' ),
				'id'          => $this->get_field_id( 'hashtag' ),
				'type'        => 'text',
				'value'       => $hashtag,
				'placeholder' => 'optional',
				'desc'        => 'One #hashtag only',
			)
		);

		$this->form_input(
			array(
				'label'       => esc_html__( 'Photo Count:', 'wds-instagram' ),
				'name'        => $this->get_field_name( 'count' ),
				'id'          => $this->get_field_id( 'count' ),
				'type'        => 'text',
				'value'       => $count,
				'placeholder' => '5',
			)
		);

		?>
			<p><input type="checkbox" name="widget-wds_instagram_widget[2][flush_cache]" value="1" <?php checked( $flush_cache, 1 ); ?> /> Flush Cache?</p>
		<?php
	}

	/**
	 * Update form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {

		$instance = $old_instance;

			foreach ( array( 'title', 'username', 'user_id', 'client_id', 'client_secret', 'count', 'hashtag', 'flush_cache' ) as $key => $value ) {
				$instance[ $value ] = sanitize_text_field( $new_instance[ $value ] );
			}

			delete_transient( $this->id );

		return $instance;

	}


	/**
	 * Build each form input.
	 *
	 * @param array $args The array of args for each input.
	 */
	public function form_input( $args = array() ) {

		$args = wp_parse_args( $args, array(
			'label'       => '',
			'name'        => '',
			'id'          => '',
			'type'        => 'text',
			'value'       => '',
			'placeholder' => '',
			'desc'        => '',
		) );

		$label       = esc_html( $args['label'] );
		$name        = esc_html( $args['name'] );
		$id          = esc_html( $args['id'] );
		$type        = esc_html( $args['type'] );
		$value       = esc_html( $args['value'] );
		$placeholder = esc_html( $args['placeholder'] );
		$desc        = ! empty( $args['desc'] ) ? sprintf( '<span class="description">%1$s</span>', $args['desc'] ) : '';

		printf(
			'<p><label for="%1$s">%2$s</label><input type="%3$s" class="widefat" name="%4$s" id="%1$s" value="%5$s" placeholder="%6$s" />%7$s</p>',
			$id,
			$label,
			$type,
			$name,
			$value,
			$placeholder,
			$desc
		);
	}
	/**
	 * Retrieve the Instagram access token.
	 *
	 * @since 1.2.0
	 */
	public function get_access_token() {
		return get_option( 'wds_instagram_widget_access_token' );
	}

	/**
	 * Get data from Instagram API.
	 *
	 * @param array $args Defaults arguments to pass to Instagram API.
	 *
	 * @return array $instagrams An array of Instagram data
	 */
	public function get_instagrams( $args = array() ) {

		// Get args.
		$count         = ( ! empty( $args['count'] ) ) ? $args['count'] : 5;
		$hashtag       = ( ! empty( $args['hashtag'] ) ) ? $args['hashtag'] : '';
		$flush         = ( ! empty( $args['flush_cache'] ) ) ? $args['flush_cache'] : 0;
		$api_url       = $hashtag ? 'https://api.instagram.com/v1/tags/' . esc_html( ltrim( $hashtag, '#' ) ) . '/media/recent/' : 'https://api.instagram.com/v1/users/self/media/recent/';

		if ( ! $access_token = $this->get_access_token() ) {
			return;
		}

		if ( ! $flush && $instagrams = get_transient( 'wds_instagram_widget_images' ) ) {
			return $instagrams;
		}

		// Ping Instragram's API.
		$response = wp_remote_get( add_query_arg( array(
			'access_token' => esc_html( $access_token ),
			'count'        => absint( $count ),
		), $api_url ) );

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $instagrams;
		}

		$response   = json_decode( wp_remote_retrieve_body( $response ), 1 );
		$instagrams = isset( $response['data'] ) ? $this->sanitize_instagrams( $response['data'] ) : array();

		// Store Instagrams in a transient, and expire every hour.
		set_transient( 'wds_instagram_widget_images', $instagrams, apply_filters( 'wds_instagram_widget_cache_lifetime', 1 * HOUR_IN_SECONDS ) );

		return $instagrams;
	}

	/**
	 * Sanitize the response from Instagram for the images.
	 *
	 * @since 0.1.2
	 *
	 * @param array $instagrams The images from Instagram.
	 *
	 * @return array $images
	 */
	public function sanitize_instagrams( $instagrams = array() ) {

		if ( empty( $instagrams ) ) {
			return $instagrams;
		}

		$images = array();

		foreach ( $instagrams as $image ) {

			$images[] = array(
				'link'    => isset( $image['link'] ) ? esc_url( $image['link'] ) : '',
				'images'  => isset( $image['images'] ) ? $image['images'] : array(),
				'caption' => isset( $image['caption']['text'] ) ? esc_attr( $image['caption']['text'] ) : '',
			);
		}

		return $images;
	}

} // WDS_Instagram_Widget

add_action( 'widgets_init', array( WDS_Instagram_Widget::get_instance(), 'hooks' ) );
