<?php
/*
Plugin Name: Import external attachments
Plugin URI:  https://github.com/ryanpcmcquen/import-external-attachments
Version: 1.5.12
Description: Examines the text of a post and makes local copies of all the images & documents, adding them as gallery attachments on the post itself.
Author: Ryan P.C. McQuen
Author URI: https://ryanpcmcquen.org
License: GPLv2 or later
*/

/*

based on Import External Images v1.4 by Marty Thornley
https://github.com/MartyThornley/import-external-images

based on Add Linked Images To Gallery v1.4 by Randy Hunt
http://www.bbqiguana.com/wordpress-plugins/add-linked-images-to-gallery/

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

	$external_image_count = 0;

	define( 'EXTERNAL_IMAGES_MAX_POSTS_COUNT' , 50 );
	define( 'EXTERNAL_IMAGES_MAX_COUNT' , 20 );
	define( 'EXTERNAL_IMAGES_DIR' , plugin_dir_path( __FILE__ ) );
	define( 'EXTERNAL_IMAGES_URL' , plugins_url( basename( dirname( __FILE__ ) ) ) );

	define( 'EXTERNAL_IMAGES_ALLOW_BULK_MESSAGE' , false );

	require_once( ABSPATH . 'wp-admin/includes/file.php' );
	require_once( ABSPATH . 'wp-admin/includes/media.php' );

	include_once( plugin_dir_path( __FILE__ ) . 'ajax.php');

	//register_activation_hook( __FILE__ , 'external_image_install' );

	add_action( 'admin_menu', 		'external_image_menu' );
	add_action( 'admin_init', 		'external_image_admin_init' );
	add_action( 'admin_head' , 		'external_images_bulk_resize_admin_javascript' );
	add_action( 'admin_notices',	'external_images_bulk_resize_message' , 90 );

	function external_image_admin_init () {
		global $pagenow;

		register_setting( 'external_image' , 'external_image_whichimgs' );

		if ( $pagenow == 'post.php' ) {
			add_action( 'post_submitbox_misc_actions' ,		'import_external_images_per_post' );
			add_action( 'save_post', 						'external_image_import_images' );
		}

		add_filter( 'attachment_link' , 'force_attachment_links_to_link_to_image' , 9 , 3 );

	}

	function external_images_bulk_resize_message(){
		global $pagenow;

		if ( EXTERNAL_IMAGES_ALLOW_BULK_MESSAGE ) {
			$message = '<h4>Please Resize Your Images</h4>';
			$message .= '<p>You may want to resize large images on you previous site before importing images. It will help save bandwidth during the import and prevent the import from crashing.';
			$message .= '<p>You can <a href="http://photographyblogsites.com/file-folder/import-tools/bulk-resize-media.zip">download the "Bulk Image Resizer" here.</a></p>';

			if ( $pagenow == 'upload.php' && isset( $_GET['page'] ) && $_GET['page'] == 'external_image' ) {
				echo '<div class="updated fade">';
				echo $message;
				echo '</div>';
			}
		}
	}

	function force_attachment_links_to_link_to_image( $link , $id ) {

		$object = get_post( $id );

		$mime_types = array(
			'image/png',
			'image/jpeg',
			'image/jpg',
			'image/gif',
			'image/bmp'
		);

		// if this post does not exists on this site, return empty string
		if ( ! $object )
			return '';

		if ( $object && in_array( $object->post_mime_type , $mime_types ) && $object->guid != '' )
			$link = $object->guid;

		return $link;

	}

	function external_image_menu() {
		add_media_page( 'Import attachments', 'Import attachments', 'edit_theme_options', 'external_image', 'external_image_options' );
	}

	/*
	 * Meta Boxes for hiding pages from main menu
	 */
	function import_external_images_per_post() {

		$post_id = (int) $_GET['post'];
		$external_images = external_image_get_img_tags( $post_id );

		$html = '';
		$documents = '';

		if ( is_array( $external_images ) && count( $external_images ) > 0 ) {

		$html = 	'<div class="misc-pub-section " id="external-images" style="background-color: #FFFFE0; border-color: #E6DB55;">';
		$html .= 	'<h4>You have ('.count( $external_images ).')  files that can be imported!</h4>';

		foreach ( $external_images as $external_image ) {

			if( in_array( strtolower(pathinfo($external_image, PATHINFO_EXTENSION)), array( 'pdf', 'doc', 'docx' ) ) ) {
				$cutlen = strlen( $external_image ) < 40  ? strlen( $external_image ) : -40;

				$documents .= '<li><small>...' . substr( $external_image, $cutlen) . '</small></li>';
			}
			else {
				$html .= '<img style="margin: 3px; max-width:50px;" src="'.$external_image.'" />';
			}

		}

		if( strlen( $documents ) ) {
			$html .= '<strong>PDFs to Import:</strong>';
			$html .= '<ul class="pdf-list">' . $documents . '</ul>';
		}

		$html .= 	'<input type="hidden" name="import_external_images_nonce" id="import_external_images_nonce" value="'.wp_create_nonce( 'import_external_images_nonce' ).'" />';
		$html .= 	'<p><input type="checkbox" name="import_external_images" id="import_external_images" value="import-'.$post_id.'" /> Import external media?</p>';
		$html .= 	'<p class="howto">Only '.EXTERNAL_IMAGES_MAX_COUNT.' will be imported at a time to keep things from taking too long.</p>';

		$html .= 	'</div>';
		}
		echo $html;

	}

	function is_external_file( $file ) {

		$allowed = array( 'jpeg' , 'png', 'bmp' , 'gif',  'pdf', 'jpg', 'doc', 'docx' );

		$ext = pathinfo($file, PATHINFO_EXTENSION);

		if ( in_array( strtolower($ext) , $allowed ) ) {
			return true;
		}

		return false;

	}

	function external_image_import_images( $post_id , $force = false ) {

		global $pagenow;

		if ( get_transient( 'saving_imported_images_' .$post_id ) )
			return;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;

		if ( $force == false && !wp_verify_nonce( $_REQUEST['import_external_images_nonce'] , 'import_external_images_nonce' ) )
			return;

		if ( $force == false && $pagenow != 'post.php' )
			return;

		if ( $force == false && $pagenow == 'post.php' && !isset( $_POST['import_external_images'] ) )
			return;

		if (wp_is_post_revision($post_id))
			return;

		$post = get_post($post_id);
		$replaced = false;
		$content = $post->post_content;
		$imgs = external_image_get_img_tags($post_id);

		$count = 0;
		for ( $i=0; $i<EXTERNAL_IMAGES_MAX_COUNT; $i++ ) {
			if (isset($imgs[$i]) && is_external_file($imgs[$i]) ) {
				$new_img = external_image_sideload( $imgs[$i] , $post_id );
				if ($new_img && is_external_file($new_img) ) {
					$content = str_replace( $imgs[$i] , $new_img , $content);
					$replaced = true;
					$count++;
				}
			}
		}
		if ( $replaced ) {
			set_transient( 'saving_imported_images_'.$post_id , 'true' , 20 );
			$update_post = array();
			$update_post['ID'] = $post_id;
			$update_post['post_content'] = $content;
			wp_update_post($update_post);
			_fix_attachment_links( $post_id );
			$response = $count;
		} else {
			$response = false;
		}
		return $response;
	}

	/*
	 * Handle importing of external image
	 * Most of this taken from WordPress function 'media_sideload_image'
 	 * @param string $file The URL of the image to download
 	 * @param int $post_id The post ID the media is to be associated with
 	 * @param string $desc Optional. Description of the image
 	 * @return string - just the image url on success, false on failure
	 */
	function external_image_sideload( $file , $post_id , $desc = '' ) {

		if ( ! empty($file) && is_external_file( $file ) ) {
			// Download file to temp location
			$tmp = download_url( $file );

			// Set variables for storage
			// fix file filename for query strings
			preg_match('/[^\?]+\.(jpg|jpeg|gif|png|pdf|doc|docx)/i', $file, $matches);
			$file_array['name'] = basename($matches[0]);
			$file_array['tmp_name'] = $tmp;

			// If error storing temporarily, unlink
			if ( is_wp_error( $tmp ) ) {
				@unlink($file_array['tmp_name']);
				$file_array['tmp_name'] = '';
				return false;
			}
			$desc = $file_array['name'];
			// do the validation and storage stuff
			$id = media_handle_sideload( $file_array, $post_id, $desc );
			// If error storing permanently, unlink
			if ( is_wp_error($id) ) {
				@unlink($file_array['tmp_name']);
				return false;
			} else {
				$src = wp_get_attachment_url( $id );
			}

		}

		if ( !empty( $src ) && is_external_file( $src ) )
			return $src;
		else
			return false;
	}

	function external_image_getext( $file ) {

		if ( function_exists( 'mime_content_type' ) ) {

			$mime = strtolower(mime_content_type($file));
			switch($mime) {
				case 'image/jpg':
					return '.jpg';
					break;
				case 'image/jpeg':
					return '.jpeg';
					break;
				case 'image/gif':
					return '.gif';
					break;
				case 'image/png':
					return '.png';
					break;
				case 'application/pdf':
					return '.pdf';
					break;
				case 'application/msword':
					return '.doc';
					break;
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
					return '.docx';
					break;
			}

			return '';

		} else {
			return '';
		}
	}

	function external_image_get_img_tags ( $post_id ) {
		$post = get_post( $post_id );
		$w = get_option( 'external_image_whichimgs' );
		$s = get_option( 'siteurl' );
		// need to also check for multisitesubdomain and mapped domain...
		//$mapped = mapped_domain;

		$excludes = get_option( 'external_image_excludes' );
		$excludes = explode( ',' , $excludes );


		$result = array();
		preg_match_all( '/<img[^>]* src=[\'"]?([^>\'"]+)/' , $post->post_content , $matches );
		preg_match_all( '/<a[^>]* href=[\'"]?([^>\'"]+)/' , $post->post_content , $matches2 );

		$matches[0] = array_merge( $matches[0] , $matches2[0] );
		$matches[1] = array_merge( $matches[1] , $matches2[1] );

		for ( $i=0; $i<count($matches[0]); $i++ ) {
			$uri = $matches[1][$i];
			$path_parts = pathinfo($uri);

			// check all excluded urls
			if ( is_array( $excludes ) ) {
				foreach( $excludes as $exclude ) {
					$trim = trim( $exclude );
					if ( $trim !='' && strpos( $uri , $trim ) != false )
						$uri = '';
				}
			}

			//only check FQDNs
			if ( $uri != '' && preg_match( '/^https?:\/\//' , $uri ) ) {
				//make sure it's external
				if ( $s != substr( $uri , 0 , strlen( $s ) ) && ( !isset( $mapped ) || $mapped != substr( $uri , 0 , strlen( $mapped ) ) ) ) {
					$path_parts['extension'] = (isset($path_parts['extension'])) ? strtolower($path_parts['extension']) : false;
					if ( in_array( $path_parts['extension'], array( 'gif', 'jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx' ) ) )
						$result[] = $uri;
				}
			}
		}
		//print_r( $matches );
		$result = array_unique($result);
		return $result;
	}

	function external_image_backcatalog () {

		$posts = get_posts( array( 'numberposts'=>-1, 'post_type' => array( 'post', 'page' ) ) );
		echo '<h4>Processing Posts...</h4>';

		set_time_limit(300);

		$count = 0;

		$before = '<form style="padding: 0 10px; margin: 20px 20px 0 0; float: left;" action="" method="post" name="external_image-backcatalog">';
		$resubmit = '<input type="hidden" value="backcatalog" name="action">
			<input class="button-primary" type="submit" value="Process More Posts">';
		$after = '</form>';

		foreach( $posts as $post ) {

			try {
				$imgs = external_image_get_img_tags($post->ID);
				if ( is_array( $imgs ) && count( $imgs ) > 0 ) {

					$count += count( $imgs );

					echo '<p>Post titled: "<strong>'.$post->post_title . '</strong>" - ';
					external_image_import_images( $post->ID , true );
					echo count( $imgs ) . ' Images processed</p>';

				}
			} catch (Exception $e) {
				echo '<em>an error occurred</em>.</p>';
			}
		}

		if($done_message) {
			echo $before;
			echo $done_message;
			echo $resubmit;
			echo $after;
		} else {
			echo '<p>Finished processing past posts!</p>';
		}
	}

	function external_image_get_backcatalog () {

		$posts = get_posts( array( 'numberposts' => -1, 'post_type' => array( 'post', 'page' ) ) );

		$count_posts = 0;
		$posts_to_import = array();
		foreach( $posts as $post ) {
			$count_images = 0;

			try {
				$imgs = external_image_get_img_tags($post->ID);

				if ( is_array( $imgs ) && count( $imgs ) > 0 ) {
					$count_images += count( $imgs );
					$posts_to_import[] = $post->ID;
					$count_posts ++;
				}
			} catch (Exception $e) {
				echo '<em>an error occurred</em>.</p>';
			}
		}

		return $posts_to_import;
	}

	function external_image_options () {
		$_cats  = '';
		$_auths = '';
		?>

<style type="text/css">
	#import_posts #processing { background: url( <?php echo EXTERNAL_IMAGES_URL; ?>/images/ajax-loader.gif ) top left transparent no-repeat; padding: 0 0 0 23px; }
</style>

<div class="wrap" style="overflow:hidden;">
	<div class="icon32" id="icon-upload"><br></div>
	<h2>Import external attachments</h2>

	<?php
		if ( isset( $_POST['action'] ) && $_POST['action'] == 'backcatalog' ) {

			echo '<div id="message" class="updated fade" style="background-color:rgb(255,251,204); overflow: hidden; margin: 0 0 10px 0">';
			external_image_backcatalog();
			echo '</div>';

		} elseif ( isset( $_POST['action'] ) && $_POST['action'] == 'update' ) {
			update_option('external_image_whichimgs',   esc_html( $_POST['external_image_whichimgs'] ) );
			update_option('external_image_excludes',   	esc_html( $_POST['external_image_excludes'] ) );

			echo '<div id="message" class="updated fade" style="background-color:rgb(255,251,204);"><p>Settings updated.</p></div>';
		}
	?>


	<form name="external_image-options" method="post" action="" style="width:300px; padding: 0 20px; margin: 20px 20px 0 0 ; float: left; background: #f6f6f6; border: 1px solid #e5e5e5; ">
	<h2 style="margin-top: 0px;">Options</h2>
		<?php settings_fields('external_image'); ?>
		<h3>Which external links to process:</h3>
		<p>By default, all external images and documents are processed.  This can be set to ignore certain domains.</p>
		<p>
		<label for="myradio1">
			<input id="myradio1" type="radio" name="external_image_whichimgs" value="all" <?php echo (get_option('external_image_whichimgs')!='exclude'?'checked="checked"':''); ?> /> All attachments
		</label>
		</p>
		<p>
		<label for="myradio2">
			<input id="myradio2" type="radio" name="external_image_whichimgs" value="exclude" <?php echo (get_option('external_image_whichimgs')=='exclude'?'checked="checked"':''); ?> /> Exclude by domain
		</label>
		</p>
		<p><label for="myradio2">Domains to exclude (comma separated):</label></p>
		<p class="howto">Example: smugmug.com, flickr.com, picasa.com, photobucket.com, facebook.com</p>
		<p><textarea style="height:90px; width: 294px;"id="external_image_excludes" name="external_image_excludes"><?php echo ( get_option('external_image_excludes') != '' ? get_option('external_image_excludes') : '' ); ?></textarea></p>

		<div class="submit">
			<input type="hidden" name="external_image_update" value="action" />
			<input type="submit" name="submit" class="button-primary" value="Save Changes" />
		</div>
	</form>

	<div id="import_all_images" style="float:left; margin:0px; display:inline; width:500px; ">

	<h2 style="margin-top: 0px;">Process all posts</h2>

		<?php

			$posts = get_posts( array( 'numberposts'=>-1, 'post_type' => array( 'post', 'page' ) ) );
			$count = 0;
			foreach( $posts as $this_post ) {
				$images = external_image_get_img_tags ($this_post->ID);
				if( !empty( $images ) ) {
					$posts_to_fix[$count]['title'] = $this_post->post_title;
					$posts_to_fix[$count]['images'] = $images;
					$posts_to_fix[$count]['id'] = $this_post->ID;
				}
			$count++;
			}

			$import = '<div style="float:left; margin: 0 10px;">';
			$import .= '<p class="submit" id="bulk-resize-examine-button">';
			$import .= '<button class="button-primary" onclick="external_images_import_images();">Import attachments now</button>';
			$import .= '</p>';

			$import .= '<div id="import_posts" style="display:none padding:25px 10px 10px 80px;"></div>';
			$import .= '<div id="import_results" style="display:none"></div>';

      $import .= '</div>';

			$html = '';

			if ( is_array( $posts_to_fix ) ) {
				$html .= '<p class="howto">Please note that this can take a long time for sites with a lot of posts. You can also edit each post and import one post at a time.</p>';
				$html .= '<p class="howto">We will process up to 50 posts at a time. You should <a class="button-secondary" href="'.admin_url('upload.php?page=external_image').'">refresh the page</a> when done to check if you have more than 50 posts.</p>';
				$html .= '<p class="howto">Only '.EXTERNAL_IMAGES_MAX_COUNT.' per post will be imported at a time to keep things from taking too long. For posts with more than that, they will get added back into the list when you refresh or come back and try again.</p>';

				$html .= $import;
				$html .= '<div id="posts_list" style="padding: 0 5px; margin: 0px; clear:both; ">';
				$html .= '<h4>Here is a look at posts that contain external attachments:</h4>';

				$html .= '<ul style="padding: 0 0 0 5px;">';
				foreach( $posts_to_fix as $post_to_fix ) {
					$html .= '<li>"<strong>'.$post_to_fix['title'].'</strong>" - ' .count($post_to_fix['images']). ' images. <a href="'.admin_url('post.php?post='.$post_to_fix['id'].'&action=edit').'">Edit Post</a>.</li>';
				}
				$html .= '</ul>';
				$html .= '</div>';



			} else {
				$html .= "<p>We didn't find any external attachments to import. You're all set!</p>";

			}
			$html .= '</div>';

			echo $html;

		?>

	</div>
</div>
	<?php
	}
