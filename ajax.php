<?php

/**
*  AJAX FUNCTIONS
*/

	// ajax actions
	add_action( 'wp_ajax_external_image_get_backcatalog_ajax', 	'external_image_get_backcatalog_ajax' );
	add_action( 'wp_ajax_external_image_import_all_ajax', 		'external_image_import_all_ajax' );

	/**
	 * Output the javascript needed for making ajax calls into the header
	 */
	function external_images_bulk_resize_admin_javascript() {
		echo "<script type=\"text/javascript\" src=\"".EXTERNAL_IMAGES_URL."/import-external-attachments.js\" ></script>\n";
	}

	/**
	 * Verifies that the current user has administrator permission and, if not,
	 * renders a json warning and dies
	 */
	function external_images_verify_permission() {
		if (!current_user_can('administrator')) {
			$results = array('success'=>false,'message' => 'Administrator permission is required');
			echo json_encode($results);
			die();
		}
	}

	/**
	 *
	 */
	function external_image_get_backcatalog_ajax( $numberposts = -1 ) {
		$posts_to_import = external_image_get_backcatalog($numberposts);

		if(!empty($posts_to_import)) {
			$response['success'] = true;
			$response['posts'] = $posts_to_import;
		} else {
			$response['success'] = false;
			$response['posts'] = array();
		}


		echo json_encode( $response );
		die();
	}

	/**
	 *
	 */
	function external_image_import_all_ajax() {
		//external_images_verify_permission();

		global $wpdb;

		$post_id = intval( $_POST['import_images_post'] );
		$response = array();

		if (!$post_id) {
			$results = array(
				'success'	=> false,
				'message' 	=> 'Missing ID Parameter'
			);

			echo json_encode($results);
			die();
		}

		$post = get_post($post_id);

		// do what we need with image...
		$response = external_image_import_images( $post_id , true );

		$results = ( $response ? '<strong>' . $post->post_title . '</strong> had ('. $response .') attachments successfully imported<br />' : '<strong>' . $post->post_title . ': </strong> No attachments imported - you might want to check whether they still exist!' );

		echo json_encode( $results  );
		die(); // required by wordpress
	}
