<?php

class sp_booking {

	var $build = 1;

	var $db;

	var $booking;

	var $showbookingnumber = 25;

	var $bookingoptions;

	function __construct() {

		global $wpdb;

		$this->db =& $wpdb;

		$installed_build = SPBCommon::get_option('staypress_booking_build', false);

		if($installed_build === false) {
			$installed_build = $this->build;
			// Create the property class and force table creation
			$this->booking =& new booking_model($wpdb, 0);
			SPBCommon::update_option('staypress_booking_build', $installed_build);
		} else {
			// Create the property class and send through installed build version
			$this->booking =& new booking_model($wpdb, $installed_build);
		}

		$tz = get_option('gmt_offset');
		$this->booking->set_timezone($tz);

		add_action( 'plugins_loaded', array(&$this, 'load_textdomain'));

		add_action( 'init', array( &$this, 'initialise_booking' ) );
		add_action( 'init', array( &$this, 'initialise_bookingadmin_ajax' ) );

		add_action( 'admin_bar_menu', array(&$this, 'add_wp_admin_menu_actions'), 46 );

		do_action ( 'staypress_modify_booking_plugin_actions' );


	}

	function sp_bookingadmin() {
		$this->__construct();
	}

	function load_textdomain() {

		$locale = apply_filters( 'staypress_locale', get_locale() );
		$mofile = SPBCommon::booking_dir( "includes/lang/booking-$locale.mo" );

		if ( file_exists( $mofile ) )
			load_textdomain( 'booking', $mofile );

	}

	function initialise_booking() {

		// Assign the user id to the property model
		$user = wp_get_current_user();
		$this->booking->set_userid($user->ID);

		// Set permissions if they haven't already been set
		$role = get_role( 'author' );
		if( !$role->has_cap( 'confirm_booking' ) ) {
			$role->add_cap( 'read_booking' );
			$role->add_cap( 'edit_booking' );
			$role->add_cap( 'delete_booking' );
			// Needed so they can upload files - well duh
			$role->add_cap( 'upload_files' );
			// Author
			$role = get_role( 'author' );
			$role->add_cap( 'read_booking' );
			$role->add_cap( 'edit_booking' );
			$role->add_cap( 'delete_booking' );
			$role->add_cap( 'confirm_booking' );
			// Editor
			$role = get_role( 'editor' );
			$role->add_cap( 'read_booking' );
			$role->add_cap( 'edit_booking' );
			$role->add_cap( 'delete_booking' );
			$role->add_cap( 'edit_bookings' );
			$role->add_cap( 'edit_others_bookings' );
			$role->add_cap( 'confirm_booking' );
			// Administrator
			$role = get_role( 'administrator' );
			$role->add_cap( 'read_booking' );
			$role->add_cap( 'edit_booking' );
			$role->add_cap( 'delete_booking' );
			$role->add_cap( 'edit_bookings' );
			$role->add_cap( 'edit_others_bookings' );
			$role->add_cap( 'confirm_booking' );
		}

		if(!post_type_exists( STAYPRESS_CONTACT_POST_TYPE )) {
			register_post_type( STAYPRESS_CONTACT_POST_TYPE , array(	'singular_label' => __('Contact','property'),
																		'label' => __('Contacts', 'property'),
																		'public' => true,
																		'show_ui' => false,
																		'publicly_queryable' => false,
																		'exclude_from_search' => true,
																		'hierarchical' => true,
																		'capability_type' => 'contact',
																		'edit_cap' => 'edit_contact',
																		'edit_type_cap' => 'edit_contacts',
																		'edit_others_cap' => 'edit_others_contacts',
																		'publish_others_cap' => 'publish_contacts',
																		'read_cap' => 'read_contact',
																		'delete_cap' => 'delete_contact'
																	)
												);
		}

		$defaultoptions = array(	'checkintext'	=>	'Check in',
									'checkouttext'	=>	'Check out'
								);

		$this->bookingoptions = SPBCommon::get_option('sp_booking_options', $defaultoptions);

		// Register shortcodes
		$this->register_shortcodes();

		// restrict search to only the available properties
		add_filter('staypress_process_search_negative', array(&$this,'get_fullsearch_results_negative'), 10, 2);
		add_filter('staypress_process_list_negative', array(&$this,'get_fullsearch_results_negative'), 10, 2);
		add_filter('staypress_process_tag_negative', array(&$this,'get_fullsearch_results_negative'), 10, 2);
		add_filter('staypress_process_dest_negative', array(&$this,'get_fullsearch_results_negative'), 10, 2);
		add_filter('staypress_process_near_negative', array(&$this,'get_fullsearch_results_negative'), 10, 2);

		add_filter( 'staypress_process_unavailable_properties', array(&$this, 'process_unavailability_search') );

		add_filter('staypress_search_redirect_url', array(&$this, 'keep_availability_args'));
		add_filter('staypress_extend_short_search_form', array(&$this, 'append_availability_fields'), 10, 2);
		add_filter('staypress_extend_widget_search_form', array(&$this, 'append_availability_fields'), 10, 2);
		add_filter('staypress_extend_widget_advsearch_form', array(&$this, 'append_availability_fields'), 10, 2);
		add_filter('staypress_extend_shortadv_search_form', array(&$this, 'append_availability_fields'), 10, 3);

		// Headings which I may remove or move at a later date
		if(has_action('staypress_override_picker_cssjs')) {
			do_action('staypress_override_picker_cssjs');
		} else {
			if(!current_theme_supports( 'staypress_booking_script' )) {
				wp_enqueue_script('jquery-widgetjs', SPBCommon::booking_url('js/jquery-ui-dates.min.js'), array( 'jquery' ), $this->build);
				wp_enqueue_script('bookingpublicjs', SPBCommon::booking_url('js/public.js'), array('jquery'), $this->build);
				wp_localize_script( 'bookingpublicjs', 'booking', array( 'calendarimage' => SPBCommon::booking_url('images/calendar.png')
																		) );
			}
			if(!current_theme_supports( 'staypress_booking_style' )) {
				wp_enqueue_style('jquery-datepickercss', SPBCommon::booking_url('css/jquery.ui.datepicker.css'), array(), $this->build);
				wp_enqueue_style('jquery-smoothnesscss', SPBCommon::booking_url('css/smoothness/datepicker.smoothness.css'), array(), $this->build);
			}
		}

	}

	function add_wp_admin_menu_actions() {
		global $wp_admin_bar;

		if(current_user_can('edit_booking')) {
			$url = admin_url('admin.php?page=booking-add');
			if(defined('STAYPRESS_ON_PROPERTY_PAGE')) {
				$url .= '&amp;property=' . STAYPRESS_ON_PROPERTY_PAGE;
			}
			$wp_admin_bar->add_menu( array( 'parent' => 'new-content', 'id' => 'booking', 'title' => __('Booking','booking'), 'href' => $url  ) );
		}

	}

	function register_shortcodes() {
		add_shortcode('availabilitycalendar', array(&$this, 'do_availability_shortcode') );

		add_filter('the_posts', array(&$this, 'check_for_shortcodes'));
	}

	function check_for_shortcodes($posts) {

		foreach( (array) $posts as $post) {
			if(strpos($post->post_content, '[availabilitycalendar') !== false) {
				if(!current_theme_supports( 'staypress_booking_style' )) {
					// We have a calendar shortcode in here
					wp_enqueue_style('bookingwidgetcss', SPBCommon::booking_url('css/booking.calendarwidget.css'), array());
					//wp_enqueue_script('googlemaps', "http://maps.google.com/maps/api/js?sensor=true", array(), $this->build, true);
					//wp_enqueue_script('propertymapshortcode', SPPCommon::property_url('js/property.mapshortcode.js'), array('googlemaps', 'jquery'), $this->build, true);
				}
			}
		}

		return $posts;

	}

	function initialise_bookingadmin_ajax() {

	}

	function return_json($results) {

		// Check for callback
		if(isset($_GET['callback'])) {
			// Add the relevant header
			header('Content-type: text/javascript');
			echo addslashes($_GET['callback']) . " (";
		} else {
			if(isset($_GET['pretty'])) {
				// Will output pretty version
				header('Content-type: text/html');
			} else {
				//header('Content-type: application/json');
				//header('Content-type: text/javascript');
			}
		}

		if(function_exists('json_encode')) {
			echo json_encode($results);
		} else {
			// PHP4 version
			require_once(ABSPATH."wp-includes/js/tinymce/plugins/spellchecker/classes/utils/JSON.php");
			$json_obj = new Moxiecode_JSON();
			echo $json_obj->encode($results);
		}

		if(isset($_GET['callback'])) {
			echo ")";
		}

	}

	function show_calendar($startyear, $startmonth, $property_id, $excludestatus = array('draft'), $bookedclass = 'booked', $holdingdiv = true) {

		$start = strtotime("$startyear-$startmonth-1");
		$dom = 1;
		//$year, $month, $prepostpack = false, $property_id = false, $excludestatus = array('draft')
		$bookings = $this->booking->get_montharray($startyear, $startmonth, true, $property_id, $excludestatus );

		if($holdingdiv) {
			echo "<div class='month'>\n";
		}

		echo "<h4 class='monthname'>";
		echo "<span class='name'>" . strftime('%B %Y', $start) . "</span>";
		echo "</h4>";

		$startingday = (int) strftime('%w', $start);

		echo "<ul class='monthdetails'>\n";
		// Day of week headings
		$wbeg = strtotime('-' . $startingday . ' days', $start);
		echo "<li class='weekheadings'>";
			echo "<ul class='dow'>";
			for($n = 0; $n < 7; $n++) {
				$wday = strtotime('+' . $n . ' days', $wbeg);
				echo "<li>" . substr(strftime('%a', $wday),0,1) . "</li>";
			}
			echo "</ul>";
		echo "</li>";

		echo "<li class='weekrow'>";
			echo "<ul>";
			for($n=0; $n < $startingday; $n++) {
				echo "<li class='middle'>&nbsp;</li>";
			}
			for($n=$startingday; $n < 7; $n++) {

				if($n != 6) {
					$class = "middle";
				} else {
					$class = '';
				}

				if(isset($bookings[$startyear . STR_PAD($startmonth, 2 , "0", STR_PAD_LEFT) . STR_PAD($dom, 2 , "0", STR_PAD_LEFT)])) {
					//echo $startyear . STR_PAD($startmonth, 2 , "0", STR_PAD_LEFT) . STR_PAD($dom, 2 , "0", STR_PAD_LEFT);
					$class .= " " . $bookedclass;

					$yesterday = strtotime('-1 day', strtotime($startyear . '-' . $startmonth . '-' . $dom));
					if(!isset($bookings[date("Ymd", $yesterday)])) {
						$class .= " startday";
					}
					$tomorrow = strtotime('+1 day', strtotime($startyear . '-' . $startmonth . '-' . $dom));
					if(!isset($bookings[date("Ymd", $tomorrow)])) {
						$class .= " endday";
					}
				}

				echo "<li class='$class' style='$style'>";
				echo $dom++;
				echo "</li>";
			}
			echo "</ul>";
		echo "</li> <!-- weekrow -->\n";

		$dow = 0;
		for($n=$dom; $n <= date('t', $start); $n++) {
			if($dow == 0) {
				echo "<li class='weekrow'>\n";
				echo "<ul>\n";
			}

			if($dow != 6) {
				$class = "middle";
			} else {
				$class = '';
			}

			if(isset($bookings[$startyear . STR_PAD($startmonth, 2 , "0", STR_PAD_LEFT) . STR_PAD($n, 2 , "0", STR_PAD_LEFT)])) {
				//echo $startyear . STR_PAD($startmonth, 2 , "0", STR_PAD_LEFT) . STR_PAD($dom, 2 , "0", STR_PAD_LEFT);
				$class .= " " . $bookedclass;

				$yesterday = strtotime('-1 day', strtotime($startyear . '-' . $startmonth . '-' . $n));
				if(!isset($bookings[date("Ymd", $yesterday)])) {
					$class .= " startday";
				}
				$tomorrow = strtotime('+1 day', strtotime($startyear . '-' . $startmonth . '-' . $n));
				if(!isset($bookings[date("Ymd", $tomorrow)])) {
					$class .= " endday";
				}
			}

			$dow++;
			echo "<li class='$class' style='$style'>";
			echo $n;
			echo "</li>";

			if($dow == 7 && $n != date('t', $start)) {
				echo "</ul>\n";
				echo "</li> <!-- weekrow -->\n";
				$dow = 0;
			}
		}

		// Finish off the last week with blank days
		// find the day we are on to co
		for($n = $dow; $n < 7; $n++) {
			if($n != 6) {
				echo "<li class='middle'>&nbsp;</li>";
			} else {
				echo "<li>&nbsp;</li>";
			}

		}

		echo "</ul> <!-- month details -->\n";

		if($holdingdiv) {
			echo "</div> <!-- month -->\n";
		}

	}

	/**********************************************************
	* Shortcodes
	**********************************************************/

	function do_availability_shortcode($atts, $content = null, $code = "") {

		$defaults = array(	"property"		=>	"define",
							"startyear"		=>	"now",
							"startmonth"	=>	"now",
							"showmonths"	=>	"1",
							"ignore"		=>	"draft",
							"bookedclass"	=>	"booked",
							"prefix"		=>	"0"
						);

		extract(shortcode_atts($defaults, $atts));

		if($startyear == 'now') $startyear = date("Y");
		if($startmonth == 'now') $startmonth = date("n");

		if($property == 'define' && defined('STAYPRESS_ON_PROPERTY_PAGE')) $property = (int) STAYPRESS_ON_PROPERTY_PAGE;

		$ignore = array_map('trim' ,explode(',', $ignore));

		ob_start();
		for($n = 0; $n < (int) $showmonths; $n++) {
			// create the date
			$showmonth = strtotime("+" . ($n + $prefix) . " months", strtotime($startyear . "-" . $startmonth . "-15"));
			$showproperty = 1;
			$excludeclass = '';

			$this->show_calendar(date("Y", $showmonth), date("n", $showmonth), $property, $ignore, $bookedclass, true);
		}
		$content = ob_get_clean();

		return $content;

	}

	/**********************************************************
	* Calendar functions
	**********************************************************/

	function get_month_bookings($property_id, $year, $month, $status = array('confirmed') ) {

	}

	function get_month($property_id, $year, $month) {

	}

	function is_booked_for( $startdate, $enddate ) {

	}

	function is_available_for( $startdate, $enddate ) {

	}

	function process_unavailability_search( $wp_query ) {
		// find the properties that are not available for this query (thereby limiting the results to only available)
		$fromstamp = $wp_query->query_vars['fromstamp'];
		$tostamp = $wp_query->query_vars['tostamp'];

		if(!empty($fromstamp) && !empty($tostamp) && $fromstamp <= $tostamp) {
			return $this->booking->get_booked_properties( $fromstamp, $tostamp );
		}
	}

	// Global Searching functions
	function append_availability_fields( $html, $idstart = 'sp_avail_', $repopulate = false ) {

		$html .= "<div class='{$idstart}avail_availfields'>";
		$html .= "<div class='availrow'>";
		$html .= "<label for='{$idstart}avail_availfrom'>" . __($this->bookingoptions['checkintext'],'booking') . "</label><input type='text' name='availfrom' class='{$idstart}avail_availfrom availfrom' value='";
		if($repopulate) {
			if(isset($_REQUEST['availfrom'])) {
				$html .= date("Y-n-j", strtotime($_REQUEST['availfrom']));
			}
		}
		$html .= "' />";
		$html .= "</div>";
		$html .= "<div class='availrow'>";
		$html .= "<label for='{$idstart}avail_availto'>" . __($this->bookingoptions['checkouttext'],'booking') . "</label><input type='text' name='availto' class='{$idstart}avail_availto availto' value='";
		if($repopulate) {
			if(isset($_REQUEST['availto'])) {
				$html .= date("Y-n-j", strtotime($_REQUEST['availto']));
			}
		}
		$html .= "' />";
		$html .= "</div>";
		$html .= "</div>";

		return $html;
	}

	function get_fullsearch_results_negative( $negative_ids, $wp_query ) {
		//print_r($wp_query);

		if(!empty($_GET['availfrom'])) {
			$fromstamp = strtotime($_GET['availfrom']);
		}

		if(!empty($_GET['availto'])) {
			$tostamp = strtotime($_GET['availto']);
		}

		if(!empty($fromstamp) && !empty($tostamp) && $fromstamp <= $tostamp) {
			$ids = $this->booking->get_booked_properties( $fromstamp, $tostamp );
			$negative_ids = array_merge($negative_ids, $ids);
		}

		return $negative_ids;
	}

	function keep_availability_args( $url ) {

		if(!empty($_REQUEST['availfrom'])) {
			$url = add_query_arg( array("availfrom" => $_REQUEST['availfrom']), $url );
		}

		if(!empty($_REQUEST['availto'])) {
			$url = add_query_arg( array("availto" => $_REQUEST['availto']), $url );
		}

		return $url;

	}


}

// Helper functions

function sp_booking_show_calendar($startyear, $startmonth, $property_id, $excludestatus = array('draft'), $holdingdiv = true) {
	global $sp_booking;

	$sp_booking->show_calendar($startyear, $startmonth, $property_id, $excludestatus, $holdingdiv);
}

?>