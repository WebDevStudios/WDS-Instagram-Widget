<?php
/*
Plugin Name: WDS Instagram Widget
Description: Display your latest Instagrams in a sidebar widget.
Version: 1.1
Author: WebDevStudios
Author URI: http://webdevstudios.com
License: GPLv2
*/

class WDS_Instagram_Widget extends WP_Widget {


	/**
	 * Contruct widget
	 */
	public function __construct() {

		parent::__construct(
			'wds_instagram_widget', // Base ID
			__( 'Instagram Widget', 'wds-instagram' ), // Name
			array( 'description' => __( 'Display your latest Instagrams in a sidebar widget.', 'wds-instagram' ) ) // Args
		);

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

		// Get widget options
		$title    = ( ! empty( $instance['title'] ) ) ? apply_filters( 'widget_title', $instance['title'] ) : '';
		$username = ( ! empty( $instance['username'] ) ) ? esc_attr( $instance['username'] ) : '';

		// Get Instagrams
		$instagram = $this->get_instagrams( array(
			'user_id'   => $instance['user_id'],
			'client_id' => $instance['client_id'],
			'count'     => $instance['count'],
		) );

		// If we have Instagrams
		if ( false !== $instagram ) : ?>

			<?php
				// Allow the image resolution to be filtered to use any available image resolutions from Instagram
				// low_resolution, thumbnail, standard_resolution
				$image_res = apply_filters( 'wds_instagram_widget_image_resolution', 'standard_resolution' );

				echo $args['before_widget'];
				echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
			?>

			<ul class="instagram-widget">

			<?php
				foreach( $instagram['data'] as $key => $image ) {
					echo apply_filters( 'wds_instagram_widget_image_html', sprintf( '<li><a href="%1$s"><img class="instagram-image" src="%2$s" alt="%3$s" title="%3$s" /></a></li>',
						$image['link'],
						$image['images'][ $image_res ]['url'],
						$image['caption']['text']
					), $image );
				}
			?>

				<a href="https://instagram.com/<?php echo esc_html( $username ); ?>"><?php printf( __( 'Follow %1$s on Instagram', 'wds-instagram' ), esc_html( $username ) ); ?></a>
			</ul>

			<?php echo $args['after_widget']; ?>

		<?php elseif( ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) && ( defined( 'WP_DEBUG_DISPLAY' ) && false !== WP_DEBUG_DISPLAY ) ): ?>
			<div id="message" class="error"><p><?php _e( 'Error: We were unable to fetch your instagram feed.', 'wds-instagram' ); ?></p></div>
		<?php endif;


	}


	/**
	 * Back-end widget form with defaults
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {

		// Get options or set defaults
		$title       = ( ! empty( $instance['title'] ) ) ? $instance['title'] : '';
		$username    = ( ! empty( $instance['username'] ) ) ? $instance['username'] : '';
		$user_id     = ( ! empty( $instance['user_id'] ) ) ? $instance['user_id'] : '';
		$client_id   = ( ! empty( $instance['client_id'] ) ) ? $instance['client_id'] : '';
		$count       = ( ! empty( $instance['count'] ) ) ? $instance['count'] : '';
		$placeholder = ( ! empty( $instance['placeholder'] ) ) ? $instance['placeholder'] : '';

		$this->form_input(
			array(
				'label'       => __( 'Widget Title:', 'wds-instagram'),
				'name'        => $this->get_field_name( 'title' ),
				'id'          => $this->get_field_id( 'title' ),
				'type'        => 'text',
				'value'       => $title,
				'placeholder' => 'Instagram'
			)
		);

		$this->form_input(
			array(
				'label'       => __( 'Username:', 'wds-instagram'),
				'name'        => $this->get_field_name( 'username' ),
				'id'          => $this->get_field_id( 'username' ),
				'type'        => 'text',
				'value'       => $username,
				'placeholder' => 'myusername'
			)
		);

		$this->form_input(
			array(
				'label'       => __( 'User ID:', 'wds-instagram'),
				'name'        => $this->get_field_name( 'user_id' ),
				'id'          => $this->get_field_id( 'user_id' ),
				'type'        => 'text',
				'value'       => $user_id,
				'placeholder' => '476220644',
				'desc'        => sprintf( __( 'Lookup your User ID <a href="%1$s" target="_blank">here</a>', 'wds-instagram' ), 'http://findmyinstagramid.com/' )
			)
		);

		$this->form_input(
			array(
				'label'       => __( 'Client ID:', 'wds-instagram'),
				'name'        => $this->get_field_name( 'client_id' ),
				'id'          => $this->get_field_id( 'client_id' ),
				'type'        => 'text',
				'value'       => $client_id,
				'placeholder' => '943c89932b2a47e6ae341d3d1943e73f',
				'desc'        => sprintf( __( 'Register a new client <a href="%1$s" target="_blank">here</a>', 'wds-instagram' ), 'http://instagram.com/developer/clients/manage/' )
			)
		);

		$this->form_input(
			array(
				'label'       => __( 'Photo Count:', 'wds-instagram'),
				'name'        => $this->get_field_name( 'count' ),
				'id'          => $this->get_field_id( 'count' ),
				'type'        => 'text',
				'value'       => $count,
				'placeholder' => '5'
			)
		);

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

			foreach ( array( 'title', 'username', 'user_id', 'client_id', 'count' ) as $key => $value ) {
				$instance[$value] = sanitize_text_field( $new_instance[$value] );
			}

			delete_transient( $this->id );

		return $instance;

	}


	/**
	 * Build each form input
	 * @param  array  $args [description]
	 * @return [type]       [description]
	 */
	public function form_input( $args = array() ) {

		$args = wp_parse_args( $args, array(
			'label'       => '',
			'name'        => '',
			'id'          => '',
			'type'        => 'text',
			'value'       => '',
			'placeholder' => '',
			'desc'        => ''
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
	 * Get data from Instagram API
	 *
	 * @param  array  $args  Defaults arguments to pass to Instagram API
	 * @return array  $instagrams  An array of Instagram data
	 */
	public function get_instagrams( $args = array() ) {

		// Get args
		$user_id   = ( ! empty( $args['user_id'] ) ) ? $args['user_id'] : '';
		$client_id = ( ! empty( $args['client_id'] ) ) ? $args['client_id'] : '';
		$count     = ( ! empty( $args['count'] ) ) ? $args['count'] : '';

		// If no client id or user id, bail
		if ( empty( $client_id ) || empty( $user_id ) ) {
			return false;
		}

		// Check for transient
		$key = $this->id;
		if ( false === ( $instagrams = get_transient( $key ) ) ) {

			// Ping Instragram's API
			$api_url = 'https://api.instagram.com/v1/users/' . esc_html( $user_id ) . '/media/recent/';
			$response = wp_remote_get( add_query_arg( array(
				'client_id' => esc_html( $client_id ),
				'count'     => absint( $count )
			), $api_url ) );

			// Is the API up?
			if ( ! 200 == wp_remote_retrieve_response_code( $response ) ) {
				return false;
			}

			// Parse the API data and place into an array
			$instagrams = json_decode( wp_remote_retrieve_body( $response ), true );

			// Are the results in an array?
			if ( ! is_array( $instagrams ) ) {
				return false;
			}

			$instagrams = maybe_unserialize( $instagrams );

			// Store Instagrams in a transient, and expire every hour
			set_transient( $key, $instagrams, apply_filters( 'wds_instagram_widget_cache_lifetime', 1 * HOUR_IN_SECONDS ) );
		}

		return $instagrams;

	}

} // WDS_Instagram_Widget


/**
 * Register Widget with WordPress
 */
function wds_start_instagram() {
	register_widget( 'WDS_Instagram_Widget' );
}
add_action( 'widgets_init', 'wds_start_instagram' );
