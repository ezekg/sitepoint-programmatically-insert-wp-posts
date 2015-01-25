<?php

// Remove all tape posts and location taxonomies
// ----
function remove_tapes() {
	$remove_tape_posts = get_posts( array(
		'post_type' => 'tape',
		'numberposts' => -1
	));

	foreach( $remove_tape_posts as $tape_post ) {

		wp_delete_post( $tape_post->ID );

	}
}

function remove_terms() {
	$terms = get_terms( "location", array( 'hide_empty' => false ) );

	foreach ( $terms as $term ) {

		wp_delete_term( $term->term_id, "location" );

	}
}

// Create posts for Tapes custom post type
// ----
function insert_tapes() {
	if( current_user_can( 'manage_options' ) ) :
		global $user_ID;
		
		// No repeat inserts, please
		if( true === DOING_CRON || true === DOING_AJAX ) return;

		// Make sure it doesn't time out
		// set_time_limit( 2400 );
		ini_set( 'max_execution_time', 2400 );
		ini_set( 'request_terminate_timeout', 2400 );

		// Directory where mp3 files are stored
		$mp3_dir = content_url() . "/uploads/mp3";
		// Directory containing transcript .doc files
		$transcripts_dir = get_template_directory() . '/tapes/transcripts-txt/';
		// Change to dir
		chdir( $transcripts_dir );
		// Glob .doc files
		$transcripts = glob('*.txt');
		// Get locations CSV
		$csv =  get_template_directory() . "/tapes/locations/locations.csv";
		// Cast into array
		$transcript_csv_contents = array_map( 'str_getcsv', file( $csv ) );
		// Keep count
		$i = 0;

		// Parse each transcript, and create post
		// ----
		foreach( $transcripts as $transcript ) :
			// Get contents of file
			$transcript_contents = file( $transcript );
			// Locations
			$transcript_locations_array = $transcript_csv_contents[$i];
			// Get length of transcript
			$transcript_length = sizeof( $transcript_contents );
			// Get transcript filename
			$transcript_filename = str_replace( ".txt", "", $transcript );

			// Get tape num and format to match filename
			// ----
			$tape_num = ( $transcript_locations_array[0] < 10 ? '0' : '' ) . ( $transcript_locations_array[0] < 100 ? '0' : '' ) . $transcript_locations_array[0];

			// Get tapes mp3 url
			// ----
			$mp3_url = $mp3_dir . "/" . $transcript_filename . ".mp3";
			// Get absolute path of mp3
			$mp3_absolute_url = "mp3/" . $transcript_filename . ".mp3";

			$output_tape_num = $tape_num;
			$output_clinic = ucwords( strtolower( $transcript_locations_array[1] ) );
			$output_city = $transcript_locations_array[3];
			$output_clean_city = str_replace( " ", "-", strtolower( $output_city ) );
			$output_state_abr = $transcript_locations_array[5];
			$output_state = $transcript_locations_array[4];
			$output_clean_state = str_replace( " ", "-", strtolower( $output_state ) );
			$output_mp3_url = $mp3_url;
			$output_abs_mp3_url = $mp3_absolute_url;
			$output_transcript = implode( "", $transcript_contents );

			// Create taxonomy
			// ----
			if( function_exists( taxonomy_exists ) && taxonomy_exists( 'location' ) ) :

				// Check if the state exists
				$tape_location_state_term = term_exists( $output_state, 'location', 0 );

				// Create state if it doesn't exist
				if ( ! $tape_location_state_term ) {
					$tape_location_state_term = wp_insert_term( $output_state, 'location',
						array(
							'parent' => 0
						)
					);
				}

				// Get term object
				$tape_location_state_term_obj = get_term_by('name', $output_state, 'location');

				// Check if the city exists
				$tape_location_city_term = term_exists( $output_city, 'location', $tape_location_state_term_obj->term_id );

				$slug = $output_clean_city . '-' . $output_clean_state;

				// Create city if it doesn't exist
				if ( ! $tape_location_city_term ) {
					$tape_location_city_term = wp_insert_term( $output_city, 'location',
						array(
							'parent' => $tape_location_state_term_obj->term_id,
							'slug' => $slug
						)
					);
				}

				$tape_location_city_term_obj = get_term_by('slug', $slug, 'location');

				// Create array for taxonomy
				$tape_location_tax = array(
					$tape_location_state_term_obj->term_id,
					$tape_location_city_term_obj->term_id
				);

			endif;

			// Post title
			// ----
			$tape_post_title = '#' . $output_tape_num . " - " . $output_clinic . " in " . $output_city . ", " . $output_state_abr;
			echo $tape_post_title . '<br>';

			// Get all posts
			// ----
			$post_exists = function( $post_title ) {
				global $wpdb;
				// Get all tapes
				$all_posts = $wpdb->get_col( "SELECT post_title FROM $wpdb->posts WHERE post_type = 'tape'" );
				// Check if passed title exists in array
				return in_array( $post_title, $all_posts );
			};

			// Make sure post does not already exist
			// ----
			if( ! $post_exists( $tape_post_title ) ) :

				// Create post object
				// ----
				$post = array(
					'post_title' => $tape_post_title,
					'post_content' => $output_transcript,
					'post_status' => 'publish',
					'post_author' => $user_ID,
					'post_type' => 'tape'
				);

				// Insert the post into the database
				$post_id = wp_insert_post( $post );

				// Create attachment
				// ----

				// Get attachment filetype
				$tape_attachment_filetype = wp_check_filetype( $output_mp3_url, null );

				// Create attachment object
				$tape_attachment = array(
					'guid' => $output_mp3_url,
					'post_mime_type' => $tape_attachment_filetype['type'],
					'post_title' => preg_replace( '/\.[^.]+$/', '', $tape_num[1] ),
					'post_content' => '',
					'post_status' => 'inherit'
				);

				// Insert attachment to post
				$tape_attachment_id = wp_insert_attachment( $tape_attachment, $output_abs_mp3_url, $post_id );

				// Update custom taxonomy
				// ----
				wp_set_post_terms( $post_id, $tape_location_tax, 'location' );

				// Update audio custom field with attachment
				// ----
				update_field( 'field_5396ad95c962f', $tape_attachment_id, $post_id );

			endif;

			// Increase count
			$i++;

		endforeach;

		echo 'Uploaded ' . $i .  ' out of ' . sizeof($transcripts) . ' tapes.';
	endif;
}

function sp_debug( $var ) {
	echo "<pre>" . print_r( $var, true ) . "</pre>";
}

/**
 * Generate posts from CSV
 */
// add_action( "init", function() {
// 	global $current_user;

// 	// Check if the user is an administrator, and if the correct post variable
// 	//  exists. I'd recommend replacing this with your own code to make sure
// 	//  the post creation _only_ happens when you want it to.
// 	if ( ! current_user_can( "manage_options" ) && ! isset( $_POST["sp_create_posts"] ) ) {
// 		exit;
// 	}

	// Get the data from all those CSVs!
	$posts = function() {
		$data = array();
		$errors = array();

		// Get array of CSV files
		$files = glob( __DIR__ . "/data/*.csv" );

		foreach ( $files as $file ) {

			// Attempt to change permissions if not readable
			if ( ! is_readable( $file ) ) {
				chmod( $file, 0744 );
			}

			// Check if file is writable, then open it in 'read only' mode
			if ( is_readable( $file ) && $_file = fopen( $file, "r" ) ) {

				// To sum this part up, all it really does is go row by
				//  row, column by column, saving all the data
				$post = array();

				// Get first row in CSV, which is of course the headers
		    	$header = fgetcsv( $_file );

		        while ( $row = fgetcsv( $_file ) ) {

		            foreach ( $header as $i => $key ) {
	                    $post[$key] = $row[$i];
	                }

	                $data[] = $post;
		        }

				fclose( $_file );

			} else {
				$errors[] = "File '$file' could not be opened. Check the file's permissions to make sure it's readable by your server.";
			}
		}

		if ( ! empty( $errors ) ) {
			// ... do stuff with the errors
		}

		return $data;
	};

	foreach ( $posts() as $post ) {

		sp_debug($post);

		continue;

		// Simple check to see if the current post exists within the
		//  database. This isn't very efficient, but it works.
		$post_exists = function( $title ) {
			global $wpdb;

			// Get an array of all posts within our custom post type
			$posts = $wpdb->get_col( "SELECT post_title FROM $wpdb->posts WHERE post_type = 'sp_tutorial'" );

			// Check if the passed title exists in array
			return in_array( $title, $posts );
		};

		// If the post exists, skip this post and go to the next one
		if ( $post_exists( $post["title"] ) ) {
			continue;
		}

		// Insert the post into the database
		$post["id"] = wp_insert_post( array(
			"post_title" => $post["title"],
			"post_content" => $post["content"],
			"post_type" => "sp_tutorial",
			"post_status" => "publish"
		));

		// Set attachment meta
		$attachment = array();
		$attachment["path"] = realpath( $post["attachment"] );
		$attachment["type"] = wp_check_filetype( $attachment["path"] );
		$attachment["name"] = basename( $attachment["path"], ".{$attachment["type"]}" );

		// Replace post attachment data
		$post["attachment"] = $attachment;

		// Insert attachment into media library
		$post["attachment"]["id"] = wp_insert_attachment( array(
			"guid" => $post["attachment"]["path"],
			"post_mime_type" => $post["attachment"]["type"],
			"post_title" => $post["attachment"]["name"],
			"post_content" => "",
			"post_status" => "inherit"
		));

		// Update post's custom field with attachment
		update_field( "field_5396ad95c962f", $post["attachment"]["id"], $post["id"] );

	}

// });