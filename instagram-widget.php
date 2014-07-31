<?php
/*
Plugin Name: Instagram Widget
Description: Display your latest Instagrams in a sidebar widget.
Version: 0.1-alpha
Author: WebDevStudios
Author URI: http://webdevstudios.com
License: GPLv2
*/

// Helpful dev links:
// http://jelled.com/instagram/lookup-user-id
// http://instagram.com/developer/clients/manage/

class WDS_Instagram_Widget extends WP_Widget {


	/**
	 * Contruct widget
	 */
	public function __construct() {

		parent::__construct(
			'wds_instagram_widget', // Base ID
			__( 'Instagram Widget', 'textdomain' ), // Name
			array( 'description' => __( 'Display your latest Instagrams in a sidebar widget.', 'textdomain' ) ) // Args
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
		$instagram = maybe_unserialize( $this->get_instagrams( array(
				'user_id'   => $instance['user_id'],
				'client_id' => $instance['client_id'],
			)
		) );


		echo $args['before_widget'];
		?>

		<div class="instagram-widget">
			<?php echo $args['before_title'] . esc_html( $title ) . $args['after_title'];

			wp_die( '<pre>'. htmlentities( print_r( $instagram, true ) ) .'</pre>' );

			foreach ($instagram['data'] as $key => $value) {
				wp_die( '<pre>'. htmlentities( print_r( $instagram, true ) ) .'</pre>' );
			}


			?>

			<a href="https://instagram.com/<?php echo esc_html( $username ); ?>"><?php echo esc_html( $username ); ?></a>
		</div>
		<?php

		echo $args['after_widget'];
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
				'label'       => __( 'Widget Title:', 'textdomain'),
				'name'        => $this->get_field_name( 'title' ),
				'id'          => $this->get_field_id( 'title' ),
				'type'        => 'text',
				'value'       => $title,
				'placeholder' => 'Instagram'
			)
		);

		$this->form_input(
			array(
				'label'       => __( 'Username:', 'textdomain'),
				'name'        => $this->get_field_name( 'username' ),
				'id'          => $this->get_field_id( 'username' ),
				'type'        => 'text',
				'value'       => $username,
				'placeholder' => 'gregoryrickaby'
			)
		);

		$this->form_input(
			array(
				'label'       => __( 'User ID:', 'textdomain'),
				'name'        => $this->get_field_name( 'user_id' ),
				'id'          => $this->get_field_id( 'user_id' ),
				'type'        => 'text',
				'value'       => $user_id,
				'placeholder' => '476420644'
			)
		);

		$this->form_input(
			array(
				'label'       => __( 'Client ID:', 'textdomain'),
				'name'        => $this->get_field_name( 'client_id' ),
				'id'          => $this->get_field_id( 'client_id' ),
				'type'        => 'text',
				'value'       => $client_id,
				'placeholder' => '943c899bab2a47e6ae341d3d1943e73f'
			)
		);

		$this->form_input(
			array(
				'label'       => __( 'Photo Count:', 'textdomain'),
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

			delete_transient( 'wds_instagram_widget_' . $this->id );

		return $instance;

	}


	/**
	 * Build each form input
	 * @param  array  $args [description]
	 * @return [type]       [description]
	 */
	public function form_input( $args = array() ) {

		$label       = esc_html( $args['label'] );
		$name        = esc_html( $args['name'] );
		$id          = esc_html( $args['id'] );
		$type        = esc_html( $args['type'] );
		$value       = esc_html( $args['value'] );
		$placeholder = esc_html( $args['placeholder'] );

		printf(
			'<p><label for="%s">%s</label><input type="%s" class="widefat" name="%s" id="%s" value="%s" placeholder="%s" /></p>',
			$id,
			$label,
			$type,
			$name,
			$id,
			$value,
			$placeholder
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

		// If no client id or user id, bail
		if ( empty( $client_id ) || empty( $user_id ) ) {
			return $instagrams;
		}

		// Check for transient
		if ( false === ( $instagrams = get_transient( 'wds_instagram_widget_' . $this->id ) ) ) {

			// Ping Instragram's API
			$response = wp_remote_get( 'https://api.instagram.com/v1/users/' . esc_html( $user_id ) . '/media/recent/?client_id=' . esc_html( $client_id ) );

				// Is the API up?
				if ( ! 200 == wp_remote_retrieve_response_code( $response ) ) {
					return '<div id="message" class="error"><p>' . __( 'You\'ve entered invalid credentials or the Instagram API down.', 'textdomain' ) . '</p></div>';
				}

			// Parse the API data and place into an array
			$instagrams = json_decode( wp_remote_retrieve_body( $response ), true );

				// Are the results in an array?
				if ( ! is_array( $instagrams ) ) {
					return '<div id="message" class="error"><p>' . __( 'The results from the Instagram API were invalid. Please try again later.', 'textdomain' ) . '</p></div>';
				}

			// Store Instagrams in a transient, and expire every hour
			set_transient( 'wds_instagram_widget_' . $this->id, $instagrams, 60 * MINUTE_IN_SECONDS );
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
