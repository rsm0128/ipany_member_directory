<?php
/**
 * Plugin Name: IPA Core Engine Plugin
 * Plugin URI: http://webexpert1102.wordpress.com
 * Description: IPA Core Engine Plugin
 * Version: 1.0.0
 * Author: rsm0128
 * Author URI: http://webexpert1102.wordpress.com
 * Stable tag: 1.1
*/
function enqueue_parent_styles() {

	wp_enqueue_style( 'font-raleway', 'https://fonts.googleapis.com/css?family=Raleway:100,100i,200,200i,300,300i,400,400i,500,500i,600,600i,700,700i,800,800i,900,900i' );
	wp_enqueue_style( 'font-roboto', 'https://fonts.googleapis.com/css?family=Roboto' );
	wp_enqueue_style( 'select2_css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.7/css/select2.min.css' );
	wp_enqueue_script('select2_js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.7/js/select2.min.js', array( 'jquery' ));
	wp_enqueue_style( 'ipa-engine', plugins_url('style.css', __FILE__) );
}
add_action( 'wp_enqueue_scripts', 'enqueue_parent_styles', 99 );

// Our custom post type function
function create_members_cpt() {
    register_post_type( 'members',
    // CPT Options
        array(
            'labels' => array(
                'name' => __( 'Members' ),
                'singular_name' => __( 'Member' )
            ),
            'public' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'members'),
        )
    );
}
add_action( 'init', 'create_members_cpt' );

function members_ajax_search() {

	// init variables
	$zip = intval($_REQUEST['zip']); if ( strlen( $zip ) > 5 ) $zip = substr( $zip, 0, 5 );
	$radius = isset($_REQUEST['radius']) ? intval($_REQUEST['radius']) : 0;

	$spec = sanitize_text_field(trim($_REQUEST['spec']));
	$keyword = sanitize_text_field($_REQUEST['keyword']);
	$posts_per_page = isset($_REQUEST['per_page']) ? intval($_REQUEST['per_page']) : 10;
	$page_num = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;

	// get zip codes in radius
	$api_key = "###";
	$unit = "mile";
	$api_url = sprintf("https://www.zipcodeapi.com/rest/%s/radius.json/%d/%d/%s", $api_key, $zip, $radius, $unit);
	$request = wp_remote_get($api_url);

	$zip_code_list = array($zip);
	// echo $api_url;
	// var_dump($request);
	// exit;
	if ( is_wp_error( $request ) ) {
		// return false;
	} else {
		$body = wp_remote_retrieve_body( $request );
		$json_zip_list = json_decode( $body );
		foreach ( $json_zip_list->zip_codes as $json_zip) {
			if ( !in_array( $json_zip->zip_code, $zip_code_list ) ){
		        array_push( $zip_code_list, $json_zip->zip_code );
		    }
		}
	}

	$meta_query = array();

	// zip search
	if ( !empty($zip) ) {
		$meta_query[] = array('key' => 'zip_code', 'value' => $zip_code_list, 'compare' => 'IN');
	}

	// spec search
	if ( !empty($spec) ) {
		$meta_query[] = array('key' => 'specialty', 'value' => $spec, 'compare' => '=');
	}

	// serach box
	if( !empty($keyword) ){
		$meta_query[] = array(
			'relation' => 'OR',
			array( 'key' => 'first_name', 'value' => $keyword, 'compare' => 'LIKE' ),
			array( 'key' => 'last_name', 'value' => $keyword, 'compare' => 'LIKE' ),
			array( 'key' => 'state', 'value' => $keyword, 'compare' => 'LIKE' ),
			array( 'key' => 'city_island', 'value' => $keyword, 'compare' => 'LIKE' ),
		);
	}

	$args = array(
		'meta_key'   => 'first_name',
		'orderby'    => 'meta_value',
		'order'      => 'ASC',
		'posts_per_page'   => $posts_per_page,
		'post_type' => 'members',
		'post_status' => 'publish',
		'paged' => $page_num
	);

	if ( !empty($meta_query) ) {
		$args['meta_query'] = $meta_query;
	}


	$query = new WP_Query($args);
	$max_num_pages = $query->max_num_pages;
	$found_posts = $query->found_posts;

	ob_start();

	while ( $query->have_posts() ) : $query->the_post();
		$post_id = get_the_ID();
		$first_name = get_post_meta($post_id, 'first_name', true );
		$last_name = get_post_meta($post_id, 'last_name', true );
		$member_title = get_post_meta($post_id, 'member_title', true );
		$specialty = get_post_meta($post_id, 'specialty', true );
		$practice_name = get_post_meta($post_id, 'practice_name', true );
		$street_address = get_post_meta($post_id, 'street_address', true );
		$city_island = get_post_meta($post_id, 'city_island', true );
		$state = get_post_meta($post_id, 'state', true );
		$zip_code = get_post_meta($post_id, 'zip_code', true );
		$phone = get_post_meta($post_id, 'phone', true );
		$fax = get_post_meta($post_id, 'fax', true );

		$full_title = $first_name . " " . $last_name . ", " . $member_title;
		$full_address = $street_address . " " . $city_island . ", " . $state . " " . $zip_code;
		?>


		<div class="member_block">
			<h2>
				<a href="<?php the_permalink() ?>" title='Read'><?php echo $full_title ?></a>
			</h2>
			<h3><?php echo $practice_name?></h3>
			<p><span>Speciality:</span><?php echo $specialty ?></p>
			<p><span>Address:</span><?php echo $full_address?></p>
			<p><span>Phone:</span><?php echo $phone ?></p>
			<p><span>Fax:</span><?php echo $fax ?></p>
		</div>

		<?php
	endwhile;
	$html = ob_get_clean();

	if ( $found_posts == 0 ) $html = "Nothing found";

	$return = array(
		'found_posts' => $found_posts,
		'max_num_pages' => $max_num_pages,
		'page_num' => $page_num,
		'content' => $html
	);
	wp_send_json_success($return);
}

add_action( 'wp_ajax_members_ajax_search', 'members_ajax_search' );
add_action( 'wp_ajax_nopriv_members_ajax_search', 'members_ajax_search' );

function members_form( $atts ){
	//get all values for search
	function get_meta_values( $key ) {
		global $wpdb;
		if( empty( $key ) ) return;

		$result = $wpdb->get_col( $wpdb->prepare( "
			SELECT pm.meta_value FROM {$wpdb->postmeta} pm
			LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = '%s'
			AND p.post_status = 'publish'
			AND p.post_type = 'members'
			GROUP BY pm.meta_value
			ORDER BY pm.meta_value
		", $key ));

		return $result;
	}

	ob_start(); ?>

<div id="member_directory">
	<script>
	var current_page = 1;
	// var per_page = 3;
	jQuery(document).ready( function() {

		jQuery('#resetme').click( function(ev) {
			jQuery("#members_from_search select").val('').trigger('change');
		});

		jQuery('#ajaxme').click( function(ev) {
			ev.preventDefault();
			current_page = 1;
			perform_ajax_request(current_page);
		});

		jQuery('#pagination').on('click', 'a.page', function(){
			current_page = jQuery(this).data('page');
			perform_ajax_request(current_page);
		});

		jQuery('#pagination').on('click', 'a.prev', function(){
			current_page -= 1;
			perform_ajax_request(current_page);
		});

		jQuery('#pagination').on('click', 'a.next', function(){
			current_page += 1;
			perform_ajax_request(current_page);
		});

		jQuery('#loader').bind('ajaxStart', function(){
		    jQuery(this).show();
		}).bind('ajaxStop', function(){
		    jQuery(this).hide();
		});

		initSelect2();

	});

	function initSelect2() {
		setTimeout(function(){
			if(window.innerWidth <= 800 && window.innerHeight <= 600) {
				jQuery('#specialty').select2({
					placeholder: "Select a specialty",
					minimumResultsForSearch: -1,
				    // allowClear: true
				});
				jQuery('#location').select2({
					placeholder: "Select a location",
					minimumResultsForSearch: -1,
				    // allowClear: true
				});
			} else {
				jQuery('#specialty').select2({
					placeholder: "Select a specialty",
				    // allowClear: true
				});
				jQuery('#location').select2({
					placeholder: "Select a location",
				    // allowClear: true
				});
			}
			jQuery('#radius').select2({
				placeholder: "Radius",
				minimumResultsForSearch: -1,
			    // allowClear: true
			});
		}, 100);
	}

	window.onresize = function(event) {
		initSelect2();
	};

	function perform_ajax_request(current_page) {
		jQuery.ajax({
			url: "<?php echo admin_url('admin-ajax.php')?>",
			data: {
				'action': 'members_ajax_search',
				'zip' : jQuery('#location').val(),
				'spec' : jQuery('#specialty').val(),
				'keyword' : jQuery('#keyword').val(),
				'radius' : jQuery('#radius').val(),
				'page' : current_page,
			},
			success:function(result) {
				if ( result.success ) {
					var data = result.data;
					// data.found_posts
					// data.max_num_pages
					// data.page_num
					// data.content
					jQuery('#search_results').html(data.content);
					updatePagination(data.max_num_pages, data.page_num, 4);
				} else {
					//
				}
			},
			error: function(errorThrown){
				alert('Sorry, some error found, please contact the support.');
			},
		});
	}

	function updatePagination(max_num_pages, page_num, btn_count_half) {
		var pagination_html = '';
		if ( max_num_pages > 0 ) {
			var btn_count_total = btn_count_half * 2 + 1;
			var min_num = Math.max(1, Math.min(page_num - btn_count_half, max_num_pages - btn_count_total + 1));
			var max_num = Math.min(max_num_pages, Math.max(page_num + btn_count_half, btn_count_total));

			// prev button
			if ( page_num > 1 ) {
				pagination_html += '<a class="nav prev" data-next="false">«</a>';
			} else {
				pagination_html += '<a class="nav prev disabled" data-next="false">«</a>';
			}

			for ( var i = min_num; i <= max_num; i++ ) {
				let _class = 'page';
				if (page_num == i) _class += " current"
				pagination_html += '<a class="' + _class + '" data-page="' + i + '">' + i + '</a>';
			}

			// next button
			if ( page_num < max_num_pages ) {
				pagination_html += '<a class="nav next" data-next="true">»</a>';
			} else {
				pagination_html += '<a class="nav next disabled" data-next="true">»</a>';
			}
		}

		jQuery('#pagination').html(pagination_html);
	}

	</script>

	<div id='form_holder'>
		<p>Please choose a speciality, a zip code and, optionally, you can enter a keyword such as first name, last name or name of a town.</p>
		<form id='members_from_search' action='/action_page.php'>
			<div class='f_left'>
				<select id='specialty' name='specialty'>
					<option></option>
					<option value=" ">All</option>
					<?php
					foreach (get_meta_values('specialty') as $spec){
						if(!empty($spec)){ ?>
							<option value="<?php echo $spec ?>"><?php echo $spec ?></option>
						<?php }
					} ?>
				</select>

				<div class="rsm-row">
					<div class="location-wrapper">
						<select id='location' name='location'>
							<option></option>
							<option value=" ">All</option>
							<?php foreach (get_meta_values( 'zip_code', 'members','publish' ) as $zip_code){
								if(!empty($zip_code)){ ?>
									<option value="<?php echo $zip_code ?>"><?php echo $zip_code ?></option>
								<?php }
							} ?>
						</select>
					</div>
					<div class="radius-wrapper">
						<select id="radius" name="radius">
							<option></option>
							<option value='5'>5 miles</option>
							<option value='10'>10 miles</option>
							<option value='15'>15 miles</option>
							<option value='20'>20 miles</option>
						</select>
					</div>
				</div>
				<input type='text' id='keyword' placeholder='keyword'>
			</div>
			<div class='f_right'>
				<input type='reset' class="ipa-button" id='resetme' value='Clear'>
				<input type='submit' class="ipa-button" id='ajaxme' value='Search'>
			</div>
			<div style='clear: both;'></div>
		</form>
	</div>
	<div id='search_results'></div>
	<div id='pagination'></div>
	<div id="loader">
		<div class="loader-wrapper">
			<div class="loader-animation"></div>
		</div>
	</div>
</div>

	<?php
	return ob_get_clean();
}

add_shortcode('members_form', 'members_form');
session_start();

function members_import( $atts ){

	if (!file_exists( dirname(__FILE__) ."/example.xls" )) {
		exit('file not exists');
	}	
	if(!is_readable( dirname(__FILE__) ."/example.xls" )) {
		exit('not readable');
	}	
	
	require_once 'excel_reader2.php';
	$xls = new Spreadsheet_Excel_Reader( dirname(__FILE__) ."/example.xls" );

	$prev_last_row = isset($_SESSION['prev_last_row']) ? $_SESSION['prev_last_row'] : 1;
	$cur_row = 0;
	$count_per_request = 10;
	if ( count($xls->sheets[0]["cells"]) > $prev_last_row ) {
		foreach($xls->sheets[0]["cells"] as $member){
			if($cur_row < $prev_last_row){//start from line in EXELE file.
				$cur_row++;
				continue;
			} else {
				$first_name = $member[1];
				$last_name = $member[2];
				$member_title = $member[3];
				$specialty = $member[4];
				$practice_name = $member[5];
				$street_address = $member[6];
				$city_island = $member[7];
				$zip_code = $member[8];
				$phone = $member[9];
				$fax = $member[10];

				if ($cur_row == $prev_last_row && ($_SESSION['status'] == 'added_post')){
					$post_id = $_SESSION['post_id'];
				} else {
					$post_data = array(
						'post_type' => 'members',
						'post_title'    => wp_strip_all_tags( $member_title . " " . $first_name . " " . $last_name . " " . $zip_code ),
						'post_content'  => '',
						'post_status'   => 'publish',
						'post_author'   => 1,
					);
					$post_id = wp_insert_post( $post_data );
				}

				$_SESSION['status'] = 'added_post';
				$_SESSION['post_id'] = $post_id;
				
				if ($post_id) {
					update_post_meta($post_id, 'first_name', $first_name );
					update_post_meta($post_id, 'last_name', $last_name );
					update_post_meta($post_id, 'member_title', $member_title );
					update_post_meta($post_id, 'specialty', $specialty );
					update_post_meta($post_id, 'practice_name', $practice_name );
					update_post_meta($post_id, 'street_address', $street_address );		
					update_post_meta($post_id, 'city_island', $city_island );
					update_post_meta($post_id, 'zip_code', $zip_code );
					update_post_meta($post_id, 'phone', $phone );
					update_post_meta($post_id, 'fax', $fax );
					// update_post_meta($post_id, 'state', $member[8] );
				}

				$_SESSION['status'] = 'added_post_meta';
				$cur_row++;
				$_SESSION['prev_last_row'] = $cur_row;

				echo $cur_row." - added! <br>";
				if ( ($cur_row - $prev_last_row) >= $count_per_request ) break;
			}
		}

		$html = sprintf('successfully added %d members', $cur_row) . "<script>location.reload();</script>";
		return $html;
	} else {
		return "Already imported all members " . $prev_last_row . "/" . count($xls->sheets[0]["cells"]);
	}
}
add_shortcode('members_import', 'members_import');
