<?php

class sp_bookingadmin {

	var $build = 2;

	var $db;

	var $booking;

	var $showbookingnumber = 25;

	function __construct() {

		global $wpdb;

		$this->db =& $wpdb;

		$installed_build = SPBCommon::get_option('staypress_booking_build', false);

		if($installed_build === false) {
			$installed_build = $this->build;
			// Create the property class and force table creation
			$this->booking = new booking_model($wpdb, 0);
			SPBCommon::update_option('staypress_booking_build', $installed_build);
		} else {
			// Create the property class and send through installed build version
			$this->booking = new booking_model($wpdb, $installed_build);
		}

		$tz = get_option('gmt_offset');
		$this->booking->set_timezone($tz);

		add_action( 'plugins_loaded', array(&$this, 'load_textdomain'));

		add_action( 'init', array( &$this, 'initialise_booking' ) );
		add_action( 'init', array( &$this, 'initialise_bookingadmin_ajax' ) );

		// Add admin menus
		add_action( 'admin_menu', array( &$this, 'add_admin_menu' ), 2 );

		// Header actions
		add_action('load-toplevel_page_booking', array(&$this, 'add_admin_header_booking'));
		add_action('load-bookings_page_booking-add', array(&$this, 'add_admin_header_booking_add'));
		add_action('load-bookings_page_booking-options', array(&$this, 'add_admin_header_booking_options'));

		// Favourite actions
		add_filter('favorite_actions', array(&$this, 'add_favourite_actions_standard'));
		add_action( 'admin_bar_menu', array(&$this, 'add_wp_admin_menu_actions'), 46 );

		add_filter( 'staypress_property_links', array(&$this, 'add_property_booking_action'), 10, 2 );

		//add_action( 'staypress_property_filter', array(&$this, 'show_property_availability_calendar') );

		do_action ( 'staypress_modify_booking_plugin_actions' );

		// Queue actions
		add_action( 'staypress_booking_added', array('SPQueue', 'queue_operation'), 10, 3 );
		add_action( 'staypress_booking_updated', array('SPQueue', 'queue_operation'), 10, 3 );
		add_action( 'staypress_booking_deleted', array('SPQueue', 'queue_operation'), 10, 3 );

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

	// Headers
	function remove_header_notices() {
		if(has_action('admin_notices')) {
			remove_all_actions( 'admin_notices' );
		}
	}

	function add_admin_header_core() {

		global $action, $page;

		// Grab any action or page variables
		wp_reset_vars(array('action','page'));

		// Set up the models user
		$user = wp_get_current_user();
		$user_ID = $user->ID;

		$this->booking->set_userid($user_ID);

		// remove admin_notices
		$this->remove_header_notices();

		// default style sheet for the plugin
		wp_enqueue_style('bookingcss', SPBCommon::booking_url('css/defaultbooking.css'), array(), $this->build);

	}

	function add_admin_header_booking() {

		global $action, $page;

		$this->add_admin_header_core();

		// booking panel js
		wp_enqueue_script('jquery-widgetjs', SPBCommon::booking_url('js/jquery-ui-dates.min.js'), array(), $this->build);
		wp_enqueue_style('jquery-datepickercss', SPBCommon::booking_url('css/jquery.ui.datepicker.css'), array(), $this->build);
		wp_enqueue_style('jquery-smoothnesscss', SPBCommon::booking_url('css/smoothness/datepicker.smoothness.css'), array(), $this->build);

		wp_enqueue_script('bookingadminjs', SPBCommon::booking_url('js/booking.js'), array('jquery'), $this->build);

		wp_localize_script( 'bookingadminjs', 'booking', array( 'calendarimage' => SPBCommon::booking_url('images/calendar.png'),
																'deletebooking' => __('Are you sure you want to delete this booking?','booking'),
																'deletebookingnonce' => wp_create_nonce('ajaxdeletebooking')
																) );

		// Reset the favourite_actions filter
		remove_filter( 'favorite_actions', array(&$this, 'add_favourite_actions_standard'));
		if($action == 'edit') {
			add_filter( 'favorite_actions', array(&$this, 'add_favourite_actions_bookinglist'));
		} else {
			add_filter( 'favorite_actions', array(&$this, 'add_favourite_actions_newbooking'));
		}

		$this->handle_booking_panel_updates();
	}

	function add_admin_header_booking_add() {
		$this->add_admin_header_core();

		wp_enqueue_script('jquery-widgetjs', SPBCommon::booking_url('js/jquery-ui-dates.min.js'), array(), $this->build);
		wp_enqueue_style('jquery-datepickercss', SPBCommon::booking_url('css/jquery.ui.datepicker.css'), array(), $this->build);
		wp_enqueue_style('jquery-smoothnesscss', SPBCommon::booking_url('css/smoothness/datepicker.smoothness.css'), array(), $this->build);

		wp_enqueue_script('bookingadminjs', SPBCommon::booking_url('js/booking.js'), array('jquery'), $this->build);

		wp_localize_script( 'bookingadminjs', 'booking', array( 'calendarimage' => SPBCommon::booking_url('images/calendar.png')
																) );

		// Reset the favourite_actions filter
		remove_filter( 'favorite_actions', array(&$this, 'add_favourite_actions_standard'));
		add_filter( 'favorite_actions', array(&$this, 'add_favourite_actions_bookinglist'));

		$this->handle_bookingadd_panel_updates();
	}

	function add_admin_header_booking_options() {
		$this->add_admin_header_core();

		wp_enqueue_script('jquery-widgetjs', SPBCommon::booking_url('js/jquery-ui-dates.min.js'), array(), $this->build);
		wp_enqueue_style('jquery-datepickercss', SPBCommon::booking_url('css/jquery.ui.datepicker.css'), array(), $this->build);
		wp_enqueue_style('jquery-smoothnesscss', SPBCommon::booking_url('css/smoothness/datepicker.smoothness.css'), array(), $this->build);

		wp_enqueue_script('bookingadminjs', SPBCommon::booking_url('js/booking.js'), array('jquery'), $this->build);
		wp_localize_script( 'bookingadminjs', 'booking', array( 'calendarimage' => SPBCommon::booking_url('images/calendar.png')
																) );

		$this->update_booking_options();
	}

	function initialise_booking() {

		// Assign the user id to the property model
		$user = wp_get_current_user();
		$this->booking->set_userid($user->ID);

		// Set permissions if they haven't already been set
		$role = get_role( 'contributor' );
		if( !$role->has_cap( 'read_booking' ) ) {
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

			// Contacts
			$role = get_role( 'contributor' );
			$role->add_cap( 'read_contact' );
			$role->add_cap( 'edit_contact' );
			$role->add_cap( 'delete_contact' );
			// Author
			$role = get_role( 'author' );
			$role->add_cap( 'read_contact' );
			$role->add_cap( 'edit_contact' );
			$role->add_cap( 'delete_contact' );
			$role->add_cap( 'publish_contacts' );
			// Editor
			$role = get_role( 'editor' );
			$role->add_cap( 'read_contact' );
			$role->add_cap( 'edit_contact' );
			$role->add_cap( 'delete_contact' );
			$role->add_cap( 'publish_contacts' );
			$role->add_cap( 'edit_contacts' );
			$role->add_cap( 'edit_others_contacts' );
			// Administrator
			$role = get_role( 'administrator' );
			$role->add_cap( 'read_contact' );
			$role->add_cap( 'edit_contact' );
			$role->add_cap( 'delete_contact' );
			$role->add_cap( 'publish_contacts' );
			$role->add_cap( 'edit_contacts' );
			$role->add_cap( 'edit_others_contacts' );
		}

		if(!post_type_exists( STAYPRESS_CONTACT_POST_TYPE )) {
			register_post_type( STAYPRESS_CONTACT_POST_TYPE, array(	'singular_label' => __('Contact','property'),
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

	}

	function register_shortcodes() {
		add_shortcode('availabilitycalendar', array(&$this, 'do_adminside_shortcode') );
	}

	function do_adminside_shortcode($atts, $content = null, $code = "") {
		// Don't want to actually do anything at the moment on the admin side of things.
		return true;
	}

	function initialise_bookingadmin_ajax() {

		add_action( 'wp_ajax__deletebooking', array(&$this,'ajax__deletebooking') );
		add_action( 'wp_ajax__bookingmovemonth', array(&$this,'ajax__bookingmovemonth') );

	}

	function ajax__bookingmovemonth() {

		$year = (int) $_REQUEST['year'];
		$month = (int) $_REQUEST['month'];

		// Fudge the URI
		$_SERVER['REQUEST_URI'] = wp_get_referer();

		$this->show_calendar($year, $month, false);
		exit;
	}

	function ajax__deletebooking() {

		if(!empty($_GET['id'])) {
			$id = (int) $_GET['id'];

			check_ajax_referer('ajaxdeletebooking');

			$result = $this->booking->delete_booking($id);
			if(!is_wp_error($result)) {
				do_action( 'staypress_booking_deleted', $id, 'delete', 'booking' );
				$result = array('errorcode' => '200', 'message' => __('Deletion completed','booking'), 'id' => $id, 'newnonce' => wp_create_nonce('ajaxdeletebooking'));
			} else {
				$result = array('errorcode' => '500', 'message' => $result->get_error_message(), 'id' => $id, 'newnonce' => wp_create_nonce('ajaxdeletebooking'));
			}
			$this->return_json($result);
		}

		exit; // or bad things happen

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

	function add_admin_menu() {

		global $menu, $_wp_last_object_menu;

		// Add the menu page
		add_menu_page(__('Booking Management','booking'), __('Bookings','booking'), 'edit_booking',  'booking', array(&$this,'handle_booking_panel'), SPBCommon::booking_url('images/calendar.png'));
		// Move things about
		$keys = array_keys($menu);
		$menuaddedat = end( $keys );

		$checkfrom = $_wp_last_object_menu + 1;
		while(isset($menu[$checkfrom])) {
			$checkfrom += 1;
		}
		// If we are here then we have found a slot
		$menu[$checkfrom] = $menu[$menuaddedat];
		$_wp_last_object_menu = $checkfrom;
		// Remove the menu we originally added
		unset($menu[$menuaddedat]);

		// Add the sub menu
		add_submenu_page('booking', __('Add New Booking','booking'), __('Add New','booking'), 'edit_booking', "booking-add", array(&$this,'show_bookingadd_panel'));

		// Add the settings pages if the user has the relevant permissions
		if(current_user_can('manage_categories')) {

		}

		if(current_user_can('manage_options')) {
			add_submenu_page('booking', __('Booking options','booking'), __('Edit Options','booking'), 'manage_options', "booking-options", array(&$this,'show_options_panel'));
		}

	}

	function add_favourite_actions_newbooking($actions) {

		$default_action = array('admin.php?page=booking-add' => array(__('New Booking','booking'), 'edit_booking'));
		// Quick links
		$actions['admin.php?page=booking'] = array(__('Bookings','booking'), 'edit_booking');

		$actions = array_merge($default_action, $actions);

		return $actions;

	}

	function add_favourite_actions_bookinglist($actions) {

		$default_action = array('admin.php?page=booking' => array(__('Bookings','booking'), 'edit_booking'));
		// Quick links
		$actions['admin.php?page=booking-add'] = array(__('New Booking','booking'), 'edit_booking');

		$actions = array_merge($default_action, $actions);

		return $actions;

	}

	function add_favourite_actions_standard($actions) {

		// Quick links
		$actions['admin.php?page=booking'] = array(__('Bookings','booking'), 'edit_booking');
		$actions['admin.php?page=booking-add'] = array(__('New Booking','booking'), 'edit_booking');

		return $actions;

	}

	function add_wp_admin_menu_actions() {
		global $wp_admin_bar;

		if(current_user_can('edit_booking')) {
			$wp_admin_bar->add_menu( array( 'parent' => 'new-content', 'id' => 'booking', 'title' => __('Booking','booking'), 'href' => admin_url('admin.php?page=booking-add') ) );
		}

	}

	// Alerts
	function show_booking_alert($msg) {
		// Message if there needs to be one
		echo "<div class='notfound'>" . $msg . "</div>";
	}

	function show_booking_panel_messages() {

		if(isset($_GET['error'])) {
			$this->show_booking_panel_errors();
		} else {
			// Set up user messages
			$messages = array();
			$messages[1] = __('Your booking details have been saved.','booking');
			$messages[2] = __('Your booking has been added.','booking');
			$messages[3] = __('Your booking has been confirmed.','booking');

			$messages[4] = __('The booking has been deleted.','booking');
			$messages[5] = __('The booking could not be deleted.','booking');

			$messages[6] = __('Your booking details have not been saved.','booking');
			$messages[7] = __('Your booking has not been added.','booking');
			$messages[8] = __('Your booking has not been confirmed.','booking');

			$messages[9] = __('Your note has been added.','booking');
			$messages[10] = __('Your note could not be added.','booking');

			$messages[11] = __('Your payment has been added.','booking');
			$messages[12] = __('Your payment could not be added.','booking');

			$messages[13] = __('Your notes have been deleted.','booking');
			$messages[14] = __('Your notes could not be deleted.','booking');

			// Message if there needs to be one
			if(isset($_GET['msg'])) {
				echo '<div id="upmessage" class="updatedmessage"><p>' . $messages[(int) $_GET['msg']];
				if((int) $_GET['msg'] <= 4 ) {
					// a positive message
					echo "&nbsp;&nbsp;<a href='admin.php?page=booking'>" . __('Return to list','booking') . "</a>";
				}
				echo '<a href="#close" id="closemessage">' . __('close', 'booking') . '</a>';
				echo '</p></div>';
				$_SERVER['REQUEST_URI'] = remove_query_arg(array('msg'), $_SERVER['REQUEST_URI']);
			}
		}

	}

	function show_booking_panel_errors() {

		// Set up user messages
		$errors = array();
		$errors[1] = __('Please ensure you have completed all of the required fields.','booking');
		$errors[2] = __('You are not allowed to confirm bookings, sorry.','booking');
		$errors[3] = __('This booking conflicts with another for this property.','booking');
		$errors[4] = __('The contact details for this booking could not be saved.','booking');
		$errors[5] = __('The booking could not be saved.','booking');
		$errors[6] = __('The booking could not be added.','booking');

		// Message if there needs to be one
		if(!empty($_GET['error'])) {
			echo '<div id="upmessage" class="updatedmessage errormessage"><p><strong>' . __('The following errors occured','booking') . "</strong>";
			echo '<a href="#close" id="closemessage">' . __('close', 'popover') . '</a>';
			echo '</p>';
			echo '<p>';
			foreach( (array) explode(",", $_GET['error']) as $err ) {
				if(!empty($errors[ (int) $err ])) {
					echo $errors[ (int) $err ] . "<br/>";
				}
			}
			echo '</p>';
			echo '</div>';
		}

	}

	// Panels
	function handle_bookingadd_panel_updates() {

		global $page, $action, $wp_query;

		wp_reset_vars( array('action', 'page') );

		switch($action) {

			case 'save':	// build the booking array
							$booking = array();

							check_admin_referer('update-booking-' . (int) $_POST['id']);

							$booking['title'] = $_POST['title'];
							$booking['startdate'] = date("Y-m-d", strtotime($_POST['startdate']));
							$booking['enddate'] = date("Y-m-d", strtotime($_POST['enddate']));

							$booking['starttime'] = date("H:i", mktime((int) $_POST['startdate-hour'], (int) $_POST['startdate-min']));
							$booking['endtime'] = date("H:i", mktime((int) $_POST['enddate-hour'], (int) $_POST['enddate-min']));

							$booking['notes'] = (isset($_POST['notes']) ? $_POST['notes'] : '');

							$booking['property_id'] = $_POST['property_id'];
							// An insert so set the defaults
							$booking['blog_id'] = $this->booking->blog_id;
							$booking['user_id'] = $this->booking->user_id;

							// check the booking status
							if(isset($_POST['publish']) || addslashes($_POST['status']) == 'confirm') {
								// The publish button has been pressed
								$validate = $this->booking->validate_booking($booking['id'], $booking);
								if( current_user_can('confirm_booking') ) {
									if( !is_wp_error($validate) ) {
										$booking['status'] = 'confirm';
									} else {
										$error = array('error' => 1);
										$booking['status'] = 'pending';
									}
								} else {
									$error = array('error' => 2);
									$booking['status'] = 'pending';
								}

							} else {
								// Check for submit button
								if(isset($_POST['submit']) || addslashes($_POST['status']) == 'pending') {
									// The publish button has been pressed
									$validate = $this->booking->validate_booking($booking['id'], $booking);
									if( is_wp_error($validate) ) {
										$error = array('error' => 1);
										$booking['status'] = 'reserved';
									} else {
										$booking['status'] = 'pending';
									}

								} else {
									$booking['status'] = $_POST['status'];
								}
							}

							$booking_id = $this->booking->insert_booking($booking);

							// build the contact array
							if(!is_wp_error($booking_id)) {
								$contact = array();
								$contact['contact_name'] = $_POST['contact_name'];
								$contact['contact_email'] = $_POST['contact_email'];
								$contact['contact_tel'] = $_POST['contact_tel'];
								$contact['contact_address'] = $_POST['contact_address'];

								if(!empty($_POST['contact_id'])) {
									$contact_id = (int) $_POST['contact_id'];
									$contact['booking_id'] = (int) $booking_id;
								} else {
									$contact_id = (int) time() * -1;
									$contact['booking_id'] = (int) $booking_id;
								}

								// save the contact first
								$contact_id = $this->booking->update_contact($contact_id, $contact);

								if(is_wp_error($contact_id)) {
									$error = array('error' => 4);
								}

							}

							if(!is_wp_error($booking_id)) {
								// Update the pricing meta information
								$priceamount = $_POST['priceamount'];
								if(!is_numeric($priceamount)) {
									$priceamount = 0;
								}
								$pricecurrency = $_POST['pricecurrency'];
								$this->booking->update_meta( $booking_id, 'priceamount', $priceamount . ':' . $pricecurrency);

								$chargesamount = $_POST['chargesamount'];
								if(!is_numeric($chargesamount)) {
									$chargesamount = 0;
								}
								$chargescurrency = $_POST['chargescurrency'];
								$this->booking->update_meta( $booking_id, 'chargesamount', $chargesamount . ':' . $chargescurrency);

								$taxesamount = $_POST['taxesamount'];
								if(!is_numeric($taxesamount)) {
									$taxesamount = 0;
								}
								$taxescurrency = $_POST['taxescurrency'];
								$this->booking->update_meta( $booking_id, 'taxesamount', $taxesamount . ':' . $taxescurrency);

								$totalamount = $_POST['totalamount'];
								if(!is_numeric($totalamount)) {
									$totalamount = 0;
								}
								$totalcurrency = $_POST['totalcurrency'];
								$this->booking->update_meta( $booking_id, 'totalamount', $totalamount . ':' . $totalcurrency);
							}

							if(!is_wp_error($booking_id)) {
								do_action( 'staypress_booking_added', $booking_id, 'add_new', 'booking' );
								$return = array('msg' => 2, 'action' => 'edit', 'id' => $booking_id);
								if(!empty($error)) {
									$return = array_merge($return, $error);
								}
								wp_safe_redirect( add_query_arg( $return, 'admin.php?page=booking' ) );
							} else {
								$return = array('msg' => 7);
								$error = array('error' => 6);
								if(!empty($error)) {
									$return = array_merge($return, $error);
								}
								wp_safe_redirect( add_query_arg( $return, 'admin.php?page=booking' ) );
							}

							break;


		}

	}

	function handle_booking_panel_updates() {

		global $page, $action, $filtertype, $filterperiod, $filterstartday, $parent_file, $wp_query;

		wp_reset_vars( array('action', 'page', 'filtertype', 'filterperiod', 'filterstartday') );

		switch($action) {

			case 'save':	// build the booking array
							$booking = array();
							$booking['id'] = (int) $_POST['id'];
							check_admin_referer('update-booking-' . $booking['id']);

							if(!empty($_POST['quicknoteaddbutton']) && !empty($_POST['quicknote'])) {
								// we are adding a quick note
								if($this->booking->add_quick_note( $booking['id'], $_POST['quicknote'])) {
									do_action( 'staypress_booking_updated', $booking['id'], 'update', 'booking' );
									$return = array('msg' => 9); // 9, 10
								} else {
									$return = array('msg' => 10); // 9, 10
								}
								wp_safe_redirect( add_query_arg( $return, wp_get_referer() ) );

							} elseif(!empty($_POST['quickpaymentaddbutton']) && !empty($_POST['quickpaymentamount'])) {
								// we are adding a quick payment
								if($this->booking->add_quick_payment($booking['id'], $_POST['quickpayment'], $_POST['quickpaymentamount'], $_POST['quickpaymentcurrency'])) {
									do_action( 'staypress_booking_updated', $booking['id'], 'update', 'booking' );
									$return = array('msg' => 11); // 9, 10
								} else {
									$return = array('msg' => 12); // 9, 10
								}
								wp_safe_redirect( add_query_arg( $return, wp_get_referer() ) );
							} elseif(!empty($_POST['addfullnotebutton']) && !empty($_POST['fullbookingnotearea'])) {
								// Note type
								switch($_POST['addfullnotetype']) {
									case 'note':	if($this->booking->add_quick_note( $booking['id'], $_POST['fullbookingnotearea'])) {
														do_action( 'staypress_booking_updated', $booking['id'], 'update', 'booking' );
														$return = array('msg' => 9); // 9, 10
													} else {
														$return = array('msg' => 10); // 9, 10
													}
													break;

									case 'payment':	if($this->booking->add_quick_payment($booking['id'], $_POST['fullbookingnotearea'], $_POST['fullpaymentamount'], $_POST['fullpaymentcurrency'])) {
														do_action( 'staypress_booking_updated', $booking['id'], 'update', 'booking' );
														$return = array('msg' => 11); // 9, 10
													} else {
														$return = array('msg' => 12); // 9, 10
													}
													break;
								}
								wp_safe_redirect( add_query_arg( $return, wp_get_referer() ) );
							} elseif( (!empty($_POST['bulknotesubmit']) || !empty($_POST['bulknotesubmit2'])) && (!empty($_POST['bulknoteaction']) || !empty($_POST['bulknoteaction2'])) ) {
								$action = $_POST['bulknoteaction'];
								if(empty($action)) $action = $_POST['bulknoteaction2'];
								$return = array('msg' => 14); // 9, 10
								switch($action) {
									case 'deletenotes':
													foreach( (array) $_POST['bookingnoteid'] as $id) {
														$this->booking->delete_note($id);
														$return = array('msg' => 13); // 9, 10
													}
													break;
								}
								wp_safe_redirect( add_query_arg( $return, wp_get_referer() ) );
							} else {
								// we are updating a booking
								$booking['title'] = $_POST['title'];
								$booking['startdate'] = date("Y-m-d", strtotime($_POST['startdate']));
								$booking['enddate'] = date("Y-m-d", strtotime($_POST['enddate']));

								$booking['starttime'] = date("H:i", mktime((int) $_POST['startdate-hour'], (int) $_POST['startdate-min']));
								$booking['endtime'] = date("H:i", mktime((int) $_POST['enddate-hour'], (int) $_POST['enddate-min']));

								$booking['notes'] = (isset($_POST['notes']) ? $_POST['notes'] : '' );

								$booking['property_id'] = $_POST['property_id'];

								// check the booking status
								if(isset($_POST['publish']) || addslashes($_POST['status']) == 'confirm') {
									// The publish button has been pressed
									$validate = $this->booking->validate_booking($booking['id'], $booking);
									if( current_user_can('confirm_booking') ) {
										if( !is_wp_error($validate) ) {
											$booking['status'] = 'confirm';
										} else {
											$error = array('error' => 1);
											$booking['status'] = 'pending';
										}
									} else {
										$error = array('error' => 2);
										$booking['status'] = 'pending';
									}

								} else {
									// Check for submit button
									if(isset($_POST['submit']) || addslashes($_POST['status']) == 'pending') {
										// The publish button has been pressed
										$validate = $this->booking->validate_booking($booking['id'], $booking);
										if( is_wp_error($validate) ) {
											$error = array('error' => 1);
											$booking['status'] = 'reserved';
										} else {
											$booking['status'] = 'pending';
										}

									} else {
										$booking['status'] = $_POST['status'];
									}
								}

								$booking_id = $this->booking->update_booking($booking);

								if(!is_wp_error($booking_id)) {
									// build the contact array
									$contact = array();
									$contact['contact_name'] = $_POST['contact_name'];
									$contact['contact_email'] = $_POST['contact_email'];
									$contact['contact_tel'] = $_POST['contact_tel'];
									$contact['contact_address'] = $_POST['contact_address'];

									if(!empty($_POST['contact_id'])) {
										$contact_id = (int) $_POST['contact_id'];
										$contact['booking_id'] = (int) $booking_id;
									} else {
										$contact_id = (int) time() * -1;
										$contact['booking_id'] = (int) $booking_id;
									}

									// save the contact first
									$contact_id = $this->booking->update_contact($contact_id, $contact);

									if(is_wp_error($contact_id)) {
										$error = array('error' => 4);
									}
								}

								if(!is_wp_error($booking_id)) {
									// Update the pricing meta information
									$priceamount = $_POST['priceamount'];
									if(!is_numeric($priceamount)) {
										$priceamount = 0;
									}
									$pricecurrency = $_POST['pricecurrency'];
									$this->booking->update_meta( $booking_id, 'priceamount', $priceamount . ':' . $pricecurrency);

									$chargesamount = $_POST['chargesamount'];
									if(!is_numeric($chargesamount)) {
										$chargesamount = 0;
									}
									$chargescurrency = $_POST['chargescurrency'];
									$this->booking->update_meta( $booking_id, 'chargesamount', $chargesamount . ':' . $chargescurrency);

									$taxesamount = $_POST['taxesamount'];
									if(!is_numeric($taxesamount)) {
										$taxesamount = 0;
									}
									$taxescurrency = $_POST['taxescurrency'];
									$this->booking->update_meta( $booking_id, 'taxesamount', $taxesamount . ':' . $taxescurrency);

									$totalamount = $_POST['totalamount'];
									if(!is_numeric($totalamount)) {
										$totalamount = 0;
									}
									$totalcurrency = $_POST['totalcurrency'];
									$this->booking->update_meta( $booking_id, 'totalamount', $totalamount . ':' . $totalcurrency);
								}

								if(!is_wp_error($booking_id)) {
									do_action( 'staypress_booking_updated', $booking_id, 'update', 'booking' );
									$return = array('msg' => 1);
									if(!empty($error)) {
										$return = array_merge($return, $error);
									}
									wp_safe_redirect( add_query_arg( $return, wp_get_referer() ) );
								} else {
									$return = array('msg' => 6);
									$error = array('error' => 4);
									if(!empty($error)) {
										$return = array_merge($return, $error);
									}
									wp_safe_redirect( add_query_arg( $return, wp_get_referer() ) );
								}
							}
							break;


		}

	}

	function handle_booking_panel() {

		global $page, $action, $filtertype, $filterperiod, $filterstartday, $parent_file, $wp_query;

		$insearch = false;

		switch($action) {

			case 'edit':	$this->show_bookingedit_panel();
							return; // so we don't see the rest of this page.
							break;


		}


		if(isset($_GET['paged']) && is_numeric(addslashes($_GET['paged']))) {
			$paged = intval($_GET['paged']);
		} else {
			$paged = 1;
		}

		$startat = (($paged - 1) * $this->showbookingnumber);

		$showfilter = array();

		// Add in startday check.
		if(!empty($filterstartday)) {
			$showfilter['startday'] = date("Y-m-d", (int) $filterstartday);
		}

		// Check for the search values
		if(!empty($_GET['bookingsearchbutton']) && !empty($_GET['bookingsearch'])) {
			$showfilter['search'] = $_GET['bookingsearch'];
			$insearch = true;
		}

		if(!empty($_GET['filteronprop']) && !empty($_GET['property_id'])) {
			$showfilter['property_id'] = (int) $_GET['property_id'];
			$insearch = true;
		}

		if(!empty($_GET['filteroncont']) && !empty($_GET['contactsearch'])) {
			$showfilter['contactsearch'] = $_GET['contactsearch'];
			$insearch = true;
		}

		if(empty($filtertype)) {
			$showfilter['type'] = 'all';
		} else {
			$showfilter['type'] = $filtertype;
		}
		if(empty($filterperiod)) {
			if(!$insearch || !empty($filterstartday)) {
				$showfilter['period'] = 'day';
			}
		} else {
			$showfilter['period'] = $filterperiod;
		}

		echo "<div class='wrap'>\n";

		echo "<div class='innerwrap'>\n";

		// Show booking list
		$results = $this->booking->get_bookings($startat, $this->showbookingnumber, $showfilter);

		$resin = $results['resin'];
		$resout = $results['resout'];
		$resocc = $results['resocc'];

		// Get the totals and the highest of them for pagination
		$resintotal = $results['resintotal'];
		$resouttotal = $results['resouttotal'];
		$resocctotal = $results['resocctotal'];
		$total = max($resintotal, $resouttotal, $resocctotal);

		if($insearch == true) {
			echo "<h2><a href='' class='selected'>" . __('Search Results','booking') . "</a>";
		} else {
			echo "<h2><a href='' class='selected'>" . __('Bookings','booking') . "</a>";
		}

		if(current_user_can('edit_booking')) {
			//echo "<a href='' class='addbutton'>" . "+" . "</a>";
		}

		if($total > $this->showbookingnumber) {
			// Pagination required

			$list_navigation = paginate_links( array(
				'base' => add_query_arg( 'paged', '%#%' ),
				'format' => '',
				'total' => ceil($total / $this->showbookingnumber),
				'current' => $paged,
				'prev_next' => false
			));

			echo "<span id='pagination'>" . $list_navigation . "</span>";
		}

		// Show the end of the header
		echo "</h2>";

		echo "<div id='bookinglist' class='bookinginnercontainer'>";

		// Inner sub menu
		echo "<ul id='innermenu'>";

		echo "<li class='leftmenu'>";

		echo "<ul id='inoutmenu' class='appmenu'>";

			echo "<li class=''>";
			echo "<a href='" . add_query_arg('filtertype', 'all', remove_query_arg('paged')) . "'";
			if($showfilter['type'] == 'all') {
				echo " class='selected'";
			}
			echo " title='" . __('All arrivals and departures' , 'booking') . "'";
			echo ">";
			echo __('All', 'booking');
			echo "</a>";
			echo "</li>";

			echo "<li class=''>";
			echo "<a href='" . add_query_arg('filtertype', 'in', remove_query_arg('paged')) . "'";
			if($showfilter['type'] == 'in') {
				echo " class='selected'";
			}
			echo " title='" . __('Only arrivals' , 'booking') . "'";
			echo ">";
			echo __('Arrive', 'booking');
			echo "</a>";
			echo "</li>";

			echo "<li class=''>";
			echo "<a href='" . add_query_arg('filtertype', 'out', remove_query_arg('paged')) . "'";
			if($showfilter['type'] == 'out') {
				echo " class='selected'";
			}
			echo " title='" . __('Only departures' , 'booking') . "'";
			echo ">";
			echo __('Depart', 'booking');
			echo "</a>";
			echo "</li>";

			echo "<li class=''>";
			echo "<a href='" . add_query_arg('filtertype', 'occupied', remove_query_arg('paged')) . "'";
			if($showfilter['type'] == 'occupied') {
				echo " class='selected'";
			}
			echo " title='" . __('Only currently occupied' , 'booking') . "'";
			echo ">";
			echo __('Occupied', 'booking');
			echo "</a>";
			echo "</li>";

		echo "</ul>\n";

		echo "</li>";

		echo "<li class='rightmenu'>";

		echo "<ul id='periodmenu' class='appmenu'>";

			echo "<li class=''>";
			echo "<a href='" . add_query_arg('filterperiod', 'day', remove_query_arg('paged')) . "'";
			if($showfilter['period'] == 'day') {
				echo " class='selected'";
			}
			if(isset($showfilter['startday'])) {
				echo " title='" . __('Bookings taking place this day' , 'booking') . "'";
				echo ">";
				echo __('Day', 'booking');
			} else {
				echo " title='" . __('Bookings taking place today' , 'booking') . "'";
				echo ">";
				echo __('Today', 'booking');
			}
			echo "</a>";
			echo "</li>";

			echo "<li class=''>";
			echo "<a href='" . add_query_arg('filterperiod', 'nextday', remove_query_arg('paged')) . "'";
			if($showfilter['period'] == 'nextday') {
				echo " class='selected'";
			}
			if(isset($showfilter['startday'])) {
				echo " title='" . __('Bookings taking place the next day' , 'booking') . "'";
				echo ">";
				echo __('Next day', 'booking');
			} else {
				echo " title='" . __('Bookings taking place tomorrow' , 'booking') . "'";
				echo ">";
				echo __('Tomorrow', 'booking');
			}
			echo "</a>";
			echo "</li>";

			echo "<li class=''>";
			echo "<a href='" . add_query_arg('filterperiod', 'week', remove_query_arg('paged')) . "'";
			if($showfilter['period'] == 'week') {
				echo " class='selected'";
			}
			echo " title='" . __('Bookings taking place over the next 7 days' , 'booking') . "'";
			echo ">";
			echo __('Week', 'booking');
			echo "</a>";
			echo "</li>";

			echo "<li class=''>";
			echo "<a href='" . add_query_arg('filterperiod', 'month', remove_query_arg('paged')) . "'";
			if($showfilter['period'] == 'month') {
				echo " class='selected'";
			}
			echo " title='" . __('Bookings taking place over the next 30 days' , 'booking') . "'";
			echo ">";
			echo __('Month', 'booking');
			echo "</a>";
			echo "</li>";

		echo "</ul>\n";

		echo "</li>";

		echo "</ul> <!-- innermneu -->\n";

		$this->show_booking_panel_messages();

		if($total > 0) {
			if(!empty($resin)) {
				$this->show_booking_arrivals($resin);
			}
			if(!empty($resout)) {
				$this->show_booking_departures($resout);
			}
			if(!empty($resocc)) {
				$this->show_booking_occupied($resocc);
			}
		} else {
			if($this->booking->bookings_exist()) {
				$this->show_booking_alert( __('Sorry, there are no bookings that match this criteria.','booking') );
			} else {
				// Empty state
				$this->show_booking_none();
			}
		}

		echo "</div> <!-- bookinglist -->\n";

		echo "</div> <!-- innerwrap -->\n";

		// Start sidebar here
		echo "<div class='rightwrap'>";
		$this->show_booking_rightpanel();
		echo "</div> <!-- rightwrap -->";

		echo "<div class='rightwrap'>";
		$this->show_booking_rightcalendarpanel();
		echo "</div> <!-- rightwrap -->";

		echo "</div> <!-- wrap -->";

	}

	function show_list_date($highlightdate, $class = 'left') {
		echo "<div class='{$class} date'>";
		echo "<div class='topdate'>";
		$day = strftime('%a', $highlightdate);
		if(strlen($day) >= 4) {
			$dayclass = ' smaller';
		} else {
			$dayclass = ' ';
		}
		echo "<span class='dayofweek" . $dayclass . "'>" . $day . "</span>";
		echo "<span class='day'>" . strftime('%d', $highlightdate) . "</span>";
		echo "</div>";

		echo "<div class='bottomdate'>";
		echo "<span class='day'>" . strftime('%d', $highlightdate) . "</span>";
		echo "<span class='month'>" . strftime('%b', $highlightdate) . "</span>";

		if(strftime('%Y', $highlightdate) != strftime('%Y')) {
			echo "<span class='year'>" . strftime('%Y', $highlightdate) . "</span>";
		}

		echo "</div>";
		echo "</div> <!-- date -->";
	}

	function show_list_details($res) {

		global $page;

		$user = wp_get_current_user();

		echo "<p class='bookingtitle'><span>";
		if(current_user_can('edit_booking')) {
			echo "<a href='admin.php?page=" . $page . "&amp;action=edit&amp;id=" . $res->id . "'>";
		}
		if(!empty($res->title)) {
			echo esc_html(stripslashes($res->title));
		} else {
			echo __('(No title)','booking');
		}
		if(current_user_can('edit_booking')) {
			echo "</a>";
		}
		echo "</span>";
		echo "<span class='status " . esc_attr($res->status) . "'>[";
		echo $this->translate_status($res->status);
		echo "]</span> <!-- status -->";

		echo "</p>";

		if(!empty($res->property_id)) {
			$property = apply_filters( 'property_get_details', $res->property_id );
			echo "<p class='propertyinfo'>";
			echo "<span class='propertytitle'>" . __('Property','booking') . " : </span>";	// deliberately set to property for translation
			if(!empty($property) && $property != $res->property_id) {
				if($property->post_author == $user->ID || current_user_can( 'edit_others_properties' )) {
					echo "<a href='" . "admin.php?page=property&propertysearch=" . "id:" . $res->property_id . "&propertysearchbutton=Search" . "'>";
				}
				if(!empty($property->reference)) {
					esc_html_e(stripslashes($property->reference));
				} elseif(!empty($property->post_title)) {
					esc_html_e(stripslashes($property->post_title));
				} else {
					esc_html_e($res->property_id);
				}
				if($property->post_author == $user->ID || current_user_can( 'edit_others_properties' )) {
					echo "</a>";
				}
			} else {
				echo __('(No property details)','booking');
			}

			echo "</p>";
		}

		// New contact grabbing function here
		$contacts = $this->booking->get_bookingcontacts($res->id);

		if(!empty($contacts) && !is_wp_error($contacts)) {
			$contact = array_shift($contacts);

			$contactmetadata = get_post_custom($contact->ID);

			echo "<p class='contactinfo'>";
			echo "<span class='contacttitle'>" . __('Contact','booking') . " : </span>";

			if(array_key_exists('contact_email', $contactmetadata) && is_array($contactmetadata['contact_email'])) {
				$contact->contact_email = array_shift($contactmetadata['contact_email']);
			} else {
				$contact->contact_email = '';
			}
			if(array_key_exists('contact_tel', $contactmetadata) && is_array($contactmetadata['contact_tel'])) {
				$contact->contact_tel = array_shift($contactmetadata['contact_tel']);
			}  else {
				$contact->contact_tel = '';
			}

			if(!empty($contact->contact_email)) {
				echo "<a href='mailto:" . esc_attr(stripslashes($contact->contact_email)) . "'>";
			}
			if(!empty($contact->post_title)) {
				esc_html_e(stripslashes($contact->post_title));
			} else {
				esc_html_e(stripslashes($contact->contact_email));
			}
			if(!empty($contact->contact_email)) {
				echo "</a>";
			}

			if(!empty($contact->contact_tel)) {
				echo "&nbsp;&nbsp;(&nbsp;";
				esc_html_e(stripslashes($contact->contact_tel));
				echo "&nbsp;)";
			}
			echo "</p>";
		}

		// Time in / Time out
		if(!empty($res->starttime) && ($res->starttime != '00:00:00' || $res->endtime != '00:00:00')) {
			echo "<p class='arrivalinfo'>";
			echo "<span class='arrivaltitle'>" . __('Arrives','booking') . " : </span>";
			echo strftime( "%R", strtotime($res->starttime));
			echo __(' on ', 'booking');
			echo strftime( '%b %e, %Y', strtotime($res->startdate));
			echo "</p>";
		} elseif(!empty($res->startdate)) {
			echo "<p class='arrivalinfo'>";
			echo "<span class='arrivaltitle'>" . __('Arrives','booking') . " : </span>";
			echo strftime( '%b %e, %Y', strtotime($res->startdate));
			echo "</p>";
		}
		if(!empty($res->endtime) && ($res->starttime != '00:00:00' || $res->endtime != '00:00:00')) {
			echo "<p class='departureinfo'>";
			echo "<span class='departuretitle'>" . __('Departs','booking') . " : </span>";
			echo strftime( "%R", strtotime($res->endtime));
			echo __(' on ', 'booking');
			echo strftime( '%b %e, %Y', strtotime($res->enddate));
			echo "</p>";
		} elseif(!empty($res->enddate)) {
			echo "<p class='departureinfo'>";
			echo "<span class='departuretitle'>" . __('Departs','booking') . " : </span>";
			echo strftime( '%b %e, %Y', strtotime($res->enddate));
			echo "</p>";
		}

		// edit and delete links
		$actions = array();

		if(current_user_can('edit_booking')) {
			$actions[] = "<a class='edit' href='admin.php?page=" . $page . "&amp;action=edit&amp;id=" . $res->id . "'>" . __('Edit','booking') . "</a>";
		}
		if(current_user_can('delete_booking')) {
			$actions[] = "<a class='delete' id='delete-" . $res->id . "' href='" . wp_nonce_url("admin.php?page=" . $page . "&amp;action=delete&amp;id=" . $res->id, 'delete-booking-' . $res->id ) . "'>" . __('Delete','booking') . "</a>";
		}

		echo "<div class='linkmeta'>";
		echo implode(" | ", $actions);
		echo "</div>";

	}

	function show_list_meta($res) {
		echo "<div class='clear'></div>";

		echo "<div class='bookingmeta'>";

			echo "<div class='bookinguser'>";
			echo __('Made by : ', 'booking');
			if(!empty($res->user_id)) {
				$author = new WP_User( $res->user_id );
				if(current_user_can('edit_users')) {
					echo "<a href='" . admin_url('users.php?usersearch=' . $author->user_login) . "'>";
				}
				echo $author->user_login;
				if(current_user_can('edit_users')) {
					echo "</a>";
				}
			} else {
				echo __('Unknown','booking');
			}

		//echo "&nbsp;&nbsp;&nbsp;&nbsp;";
		//echo __('Updated : ', 'booking');
		//echo mysql2date("Y-m-d", $property->post_modified);
			echo "</div>";

			echo "<div class='bookinglinks'>";
			echo "boo";
			//echo implode(' | ', $actions);
			echo "</div>";

		echo "</div>";
	}

	function show_booking_arrivals($resin) {

		echo "<h3 class='arrivalsheader'>" . __('Arrivals','booking') . "</h3>";
		echo "<div class='bookinglistinner arrivals'>";
		$toplineclass = '';
		foreach($resin as $key => $res) {
			echo "<div class='booking" . $toplineclass . "' id='booking-" . $res->id . "'>";

			// Key date display
			$highlightdate = strtotime($res->startdate);
			$this->show_list_date($highlightdate, 'left arrival');

			echo "<div class='details'>";

			$this->show_list_details($res);

			echo "</div> <!-- details -->";

			//$this->show_list_meta($res);

			echo "</div> <!-- booking -->";

			$toplineclass = ' topline';
		}
		echo "</div> <!-- bookinglistinner -->";

	}

	function show_booking_departures($resout) {

		echo "<h3 class='departuresheader'>" . __('Departures','booking') . "</h3>";
		echo "<div class='bookinglistinner departures'>";
		$toplineclass = '';
		foreach($resout as $key => $res) {
			echo "<div class='booking" . $toplineclass . "' id='booking-" . $res->id . "'>";

			// Key date display
			$highlightdate = strtotime($res->enddate);
			$this->show_list_date($highlightdate, 'left departure');

			echo "<div class='details'>";

			$this->show_list_details($res);

			echo "</div> <!-- details -->";

			//$this->show_list_meta($res);

			echo "</div> <!-- booking -->";

			$toplineclass = ' topline';
		}
		echo "</div> <!-- bookinglistinner -->";

	}

	function show_booking_occupied($resocc) {

		echo "<h3 class='occupiedheader'>" . __('Occupied','booking') . "</h3>";
		echo "<div class='bookinglistinner occupied'>";
		$toplineclass = '';
		foreach($resocc as $key => $res) {
			echo "<div class='booking" . $toplineclass . "' id='booking-" . $res->id . "'>";

			// Key date display
			$highlightdate = strtotime($res->startdate);
			$this->show_list_date($highlightdate, 'left arrival');

			$highlightdate = strtotime($res->enddate);
			$this->show_list_date($highlightdate, 'right departure');

			echo "<div class='details'>";

			$this->show_list_details($res);

			echo "</div> <!-- details -->";

			//$this->show_list_meta($res);

			echo "</div> <!-- booking -->";

			$toplineclass = ' topline';
		}
		echo "</div> <!-- bookinglistinner -->";

	}

	function show_booking_none() {
		// No properties have been entered or found so go through the clean slate check list.

		echo "<p>" . __('Hello, before we get started entering your bookings, there are one or two things we need to complete.','booking') . "</p>";
		echo "<p>" . __('Complete the steps below and hopefully we will have you up and running in no time.','booking') . "</p>";

		$count = 1;
		if(current_user_can('manage_categories')) {

		}

		// Property
		echo "<p class='nodatalist'>";
		echo "<span class='nodatanumber'>" . $count++ . "</span>";
		echo "<span>";
		echo "<a href='?page=booking-add'>" . __("Add a booking.",'booking') . "</a>" . __("<br/>Seriously, that's it, you're ready to add that first booking.",'booking');
		echo "</span>";
		echo "</p>";
	}

	function show_bookingadd_panel() {
		$this->show_bookingedit_panel();
	}

	function show_booking_edit_form($booking = false) {

		// Message if there needs to be one
		$this->show_booking_panel_messages();

		echo "<input type='hidden' id='action' name='action' value='save' />";
		echo "<input type='hidden' id='id' name='id' value='" . $booking->id . "' />";

		wp_nonce_field('update-booking-' . $booking->id);

		echo "<label for='title' class='main'>" . __('Title', 'booking') . "</label>";
		echo "<input type='text' name='title' id='title' value='" . esc_attr(stripslashes( (isset($booking->title) ? $booking->title : '' ))) . "' class='main wide' />";

		// Build property drop down list
		echo "<label for='property_id' class='main'>" . __('Property', 'booking') . "</label>";
		if(has_filter('property_get_list')) {
			$properties = apply_filters( 'property_get_list', 999);
			if($properties != 999) {
				// We have some properties but don't know if the one for the booking is here or not
				$inlist = false;
				echo "<select name='property_id' id='property_id' class='main wide'>";
				echo "<option value=''>" . __('Select a property from the list','booking') . "</option>";
				foreach($properties as $key => $property) {
					echo "<option value='" . $property->ID . "'";
					if(isset($booking->property_id) && $booking->property_id == $property->ID) {
						echo " selected='selected'";
						$inlist = true;
					}
					echo ">";
					echo esc_html($property->reference) . " - " . esc_html($property->post_title);
					echo "</option>";
				}
				if(!$inlist) {
					// The property for this booking isn't in the list of properties we have access to, so let's grab the details and
					// add it in at the end
					$property = apply_filters( 'property_get_details', $booking->property_id);
					if(!empty($property) && $property != $booking->property_id) {
						echo "<option value='" . $property->ID . "'";
						echo " selected='selected'";
						echo ">";
						echo esc_html($property->reference) . " - " . esc_html($property->post_title);
						echo "</option>";
					} elseif(!empty($property)) {
						echo "<option value='" . $booking->property_id . "'";
						echo " selected='selected'";
						echo ">";
						echo __('Property : ','booking') . esc_html($booking->property_id);
						echo "</option>";
					}
				}
				echo "</select>";

			}
		} else {
			// Need to grab the properties list from the options
			$properties = SPBCommon::get_option( 'sp_booking_properties', array() );

			if(!empty($properties)) {
				$arrprops = explode( "\n", $properties );
				foreach($arrprops as $key => $property) {
					$arrprops[$key] = explode(":", $property);
				}
			}

			if(!empty($arrprops)) {
				echo "<select name='property_id' id='property_id' class='main wide'>";
				foreach($arrprops as $key => $property) {
					echo "<option value='" . $property[0] . "'";
					if($booking->property_id == $property[0]) {
						echo " selected='selected'";
					}
					echo ">";
					echo esc_html($property[0]) . " - " . esc_html($property[1]);
					echo "</option>";
				}
				echo "</select>";
			}

		}

		$startdate = strtotime($booking->startdate);

		echo "<label for='startdate' class='main'>" . __('Arrival Date','booking') .  "</label>";
		echo "<select name='startdate-day' id='startdate-day' class='datefield'>";
		for($n=1; $n <=31; $n++) {
			echo "<option value='" . $n . "'";
			if($n == date("j", $startdate)) echo " selected='selected'";
			echo ">" . $n . "</option>";
		}
		echo "</select>";
		echo "<select name='startdate-month' id='startdate-month' class='datefield'>";
		for($n=1; $n <=12; $n++) {
			$date = strtotime(date("Y-" . $n . "-15"));
			echo "<option value='" . $n . "'";
			if($n == date("n", $startdate)) echo " selected='selected'";
			echo ">" . strftime('%b', $date) . "</option>";
		}
		echo "</select>";
		echo "<select name='startdate-year' id='startdate-year' class='datefield'>";
		for($n=-15; $n <=12; $n++) {
			$date = strtotime('+' . $n . ' years');
			echo "<option value='" . date("Y", $date) . "'";
			if(date("Y", $date) == date("Y", $startdate)) echo " selected='selected'";
			echo ">" . date("Y", $date) . "</option>";
		}
		echo "</select>";
		echo "<input type='text' name='startdate' id='startdate' value='" . esc_attr(date("Y-n-j", $startdate)) . "' class='hiddendatefield' />";

		// Times drop downs
		$starttime = strtotime( (isset($booking->starttime) ? $booking->starttime : 'now' ) );
		echo "<select name='startdate-hour' id='startdate-hour' class='lefttimefield timefield'>";
		for($n=0; $n <=23; $n++) {
			echo "<option value='" . str_pad($n, 2, '0', STR_PAD_LEFT) . "'";
			if(str_pad($n, 2, '0', STR_PAD_LEFT) == date("H", $starttime)) echo " selected='selected'";
			echo ">" . str_pad($n, 2, '0', STR_PAD_LEFT) . "</option>";
		}
		echo "</select>";
		echo "<select name='startdate-min' id='startdate-min' class='timefield'>";
		for($n=0; $n <=59; $n++) {
			echo "<option value='" . str_pad($n, 2, '0', STR_PAD_LEFT) . "'";
			if(str_pad($n, 2, '0', STR_PAD_LEFT) == date("i", $starttime)) echo " selected='selected'";
			echo ">" . str_pad($n, 2, '0', STR_PAD_LEFT) . "</option>";
		}
		echo "</select>";

		$enddate = strtotime($booking->enddate);

		echo "<label for='enddate' class='main'>" . __('Departure Date','booking') .  "</label>";
		echo "<select name='enddate-day' id='enddate-day' class='datefield'>";
		for($n=1; $n <=31; $n++) {
			echo "<option value='" . $n . "'";
			if($n == date("j", $enddate)) echo " selected='selected'";
			echo ">" . $n . "</option>";
		}
		echo "</select>";
		echo "<select name='enddate-month' id='enddate-month' class='datefield'>";
		for($n=1; $n <=12; $n++) {
			$date = strtotime(date("Y-" . $n . "-15"));
			echo "<option value='" . $n . "'";
			if($n == date("n", $enddate)) echo " selected='selected'";
			echo ">" . strftime('%b', $date) . "</option>";
		}
		echo "</select>";
		echo "<select name='enddate-year' id='enddate-year' class='datefield'>";
		for($n=-15; $n <=12; $n++) {
			$date = strtotime('+' . $n . ' years');
			echo "<option value='" . date("Y", $date) . "'";
			if(date("Y", $date) == date("Y", $enddate)) echo " selected='selected'";
			echo ">" . date("Y", $date) . "</option>";
		}
		echo "</select>";
		echo "<input type='text' name='enddate' id='enddate' value='" . esc_attr(date("Y-n-j", $enddate)) . "' class='hiddendatefield' />";

		// Times drop downs
		$endtime = strtotime( (isset($booking->endtime) ? $booking->endtime : 'now' ) );
		echo "<select name='enddate-hour' id='enddate-hour' class='lefttimefield timefield'>";
		for($n=0; $n <=23; $n++) {
			echo "<option value='" . str_pad($n, 2, '0', STR_PAD_LEFT) . "'";
			if(str_pad($n, 2, '0', STR_PAD_LEFT) == date("H", $endtime)) echo " selected='selected'";
			echo ">" . str_pad($n, 2, '0', STR_PAD_LEFT) . "</option>";
		}
		echo "</select>";
		echo "<select name='enddate-min' id='enddate-min' class='timefield'>";
		for($n=0; $n <=59; $n++) {
			echo "<option value='" . str_pad($n, 2, '0', STR_PAD_LEFT) . "'";
			if(str_pad($n, 2, '0', STR_PAD_LEFT) == date("i", $endtime)) echo " selected='selected'";
			echo ">" . str_pad($n, 2, '0', STR_PAD_LEFT) . "</option>";
		}
		echo "</select>";

		// New contact grabbing function here
		if(!empty($booking->contact)) {
			$contacts = $booking->contact;
		} else {
			$contacts = $this->booking->get_bookingcontacts($booking->id);
		}

		if(empty($contacts) || is_wp_error($contacts)) {
			$contact = new stdClass();
		} else {
			// We only want the first one in this case - for now anyway
			$contact = array_shift($contacts);
		}

		echo "<h3>" . __('Main Contact Details','booking') . "</h3>";
		echo "<p>" . __('Enter the details of the main contact person for this booking.','booking') . "</p>";

		echo "<input type='hidden' id='contact_id' name='contact_id' value='" . (isset($contact->ID) ? $contact->ID : '') . "' />";

		echo "<label for='contact_name' class='main'>" . __('Name', 'booking') . "</label>";
		echo "<input type='text' name='contact_name' id='contact_name' value='" . esc_attr(stripslashes( (isset($contact->post_title) ? $contact->post_title : '' ))) . "' class='main narrow' />";

		if(!empty($contact->ID)) {
			$contactmetadata = get_post_custom($contact->ID);
		} else {
			if(!empty($contact->metadata)) {
				$contactmetadata = $contact->metadata;
			} else {
				$contactmetadata = array();
			}
		}

		if(array_key_exists('contact_email', $contactmetadata) && is_array($contactmetadata['contact_email'])) {
			$contact->contact_email = array_shift($contactmetadata['contact_email']);
		} else {
			$contact->contact_email = '';
		}

		if(array_key_exists('contact_tel', $contactmetadata) && is_array($contactmetadata['contact_tel'])) {
			$contact->contact_tel = array_shift($contactmetadata['contact_tel']);
		}  else {
			$contact->contact_tel = '';
		}

		if(array_key_exists('contact_address', $contactmetadata) && is_array($contactmetadata['contact_address'])) {
			$contact->contact_address = array_shift($contactmetadata['contact_address']);
		}  else {
			$contact->contact_address = '';
		}

		echo "<label for='contact_email' class='main'>" . __('Email', 'booking') . "</label>";
		echo "<input type='text' name='contact_email' id='contact_email' value='" . esc_attr(stripslashes($contact->contact_email)) . "' class='main narrow' />";

		echo "<label for='contact_tel' class='main'>" . __('Telephone', 'booking') . "</label>";
		echo "<input type='text' name='contact_tel' id='contact_tel' value='" . esc_attr(stripslashes($contact->contact_tel)) . "' class='main narrow' />";

		echo "<label for='contact_address' class='main'>" . __('Address', 'booking') . "</label>";
		echo "<textarea name='contact_address' id='contact_address' class='main wide short'>" . esc_html(stripslashes($contact->contact_address)) . "</textarea>";

	}

	function show_bookingedit_panel() {

		global $page, $action;

		if(isset($_GET['id'])) {
			$id = (int) $_GET['id'];

			$booking = $this->booking->get_booking($id);

			if(is_wp_error($booking)) {
				$this->show_booking_alert( $booking->get_error_message() );
				echo "</div>";
				echo "</form>";
				echo "</div>";
				return;
			}

		} else {
			$booking = new stdClass;
			$booking->status = 'draft';
			$booking->id = time() * -1;

			// set date to today
			$booking->startdate = date("Y-n-j");
			$booking->enddate = date("Y-n-j");

			if(isset($_GET['property'])) {
				$property_id = (int) $_GET['property'];
				$booking->property_id = $property_id;
			}

			if(isset($_GET['lead'])) {
				// a lead from a contact form plugin has been passed in
				$booking = apply_filters('staypress_booking_prepopulate', $booking, (int) $_GET['lead']);
			}
		}

		echo "<div class='wrap'>\n";

		echo "<form action='?page=" . $page . "' method='post' name='editaddbookingform' id='editaddbookingform' >";

		echo "<div class='innerwrap'>\n";

		echo "<h2><a href='' class='selected'>";
		if($booking->id > 0) {
			echo __('Edit Booking','booking');
		} else {
			echo __('Add Booking','booking');
		}
		echo "</a>";

		echo "</h2>";

		echo "<div id='bookingforminner' class='bookinginnercontainer'>";

		$this->show_booking_edit_form($booking);

		echo "</div> <!-- bookingforminner -->\n";

		// Booking notes interface
		if($booking->id > 0) {

			$showfilter = array();

			if(isset($_GET['filtertype'])) {
				$showfilter['type'] = addslashes($_GET['filtertype']);
			} else {
				$showfilter['type'] = 'all';
			}

			echo "<h2><a href='' class='selected'>";
			echo __('Booking Notes','booking');
			echo "</a>";
			echo "</h2>";

			echo "<div id='bookingnotesinner' class='bookinginnercontainer'>";

			// Inner sub menu
			echo "<ul id='innermenu'>";
			echo "<li class='leftmenu'>";
			echo "<ul id='inoutmenu' class='appmenu'>";

				echo "<li class=''>";
				echo "<a href='" . add_query_arg('filtertype', 'all', remove_query_arg('msg')) . "'";
				echo " class='bookingnotefilter";
				if($showfilter['type'] == 'all') {
					echo " selected";
				}
				echo "' title='" . __('All information' , 'booking') . "'";
				echo " id='filterallbookingnotes' ";
				echo ">";
				echo __('All', 'booking');
				echo "</a>";
				echo "</li>";

				echo "<li class=''>";
				echo "<a href='" . add_query_arg('filtertype', 'note', remove_query_arg('msg')) . "'";
				echo " class='bookingnotefilter";
				if($showfilter['type'] == 'note') {
					echo " selected";
				}
				echo "' title='" . __('Only notes' , 'booking') . "'";
				echo " id='filternotebookingnotes' ";
				echo ">";
				echo __('Notes', 'booking');
				echo "</a>";
				echo "</li>";

				/*
				echo "<li class=''>";
				echo "<a href='" . add_query_arg('filtertype', 'reminder', remove_query_arg('msg')) . "'";
				if($showfilter['type'] == 'reminder') {
					echo " class='selected'";
				}
				echo " title='" . __('Only reminders' , 'booking') . "'";
				echo ">";
				echo __('Reminders', 'booking');
				echo "</a>";
				echo "</li>";
				*/

				echo "<li class=''>";
				echo "<a href='" . add_query_arg('filtertype', 'payment', remove_query_arg('msg')) . "'";
				echo " class='bookingnotefilter";
				if($showfilter['type'] == 'payment') {
					echo " selected";
				}
				echo "' title='" . __('Only payments' , 'booking') . "'";
				echo " id='filterpaymentbookingnotes' ";
				echo ">";
				echo __('Payments', 'booking');
				echo "</a>";
				echo "</li>";

			echo "</ul>\n";
			echo "</li>";

			echo "<li class='rightmenu'>";
			echo "</li>";
			echo "</ul> <!-- innermneu -->\n";

			$notes = $this->booking->get_notes($booking->id, $showfilter['type']);
			if(!empty($notes)) {

				echo "<div class='alignleft actions'>";
				echo "<select name='bulknoteaction' id='bulknoteaction'>";
				echo "<option value=''>" . __('Bulk Actions','booking') . "</option>";
				echo "<option value='deletenotes'>" . __('Delete','booking') . "</option>";
				echo "</select>&nbsp;";
				echo "<input type='submit' name='bulknotesubmit' value='" . __('Apply','booking') . "' class='button' />";
				echo "</div>";
			}

			echo "<table class='widefat'>";
			if(!empty($notes)) {
				foreach( (array) $notes as $key => $note) {
					echo "<tr class='bookingnote bookingnote" . $note->note_type . "'>";
					switch($note->note_type) {
							case 'payment':
												echo "<th>";
												echo "<input type='checkbox' name='bookingnoteid[]' value='" . $note->id . "' />";
												echo "</th>";
												echo "<td colspan='2'>";
													echo "<h6>" . __('Payment made of : ', 'booking');
													$meta = unserialize($note->note_meta);
													echo strtoupper($meta['currency']) . " " . number_format($meta['amount'], 2);
													echo "<span>" . __(' on ', 'booking') . date("jS M Y", strtotime( $note->created_date )) . __(' at ', 'booking') . date("H:i", strtotime( $note->created_date )) . "</span>";
													echo "</h6>";

													echo "<p>" . $note->note . "</p>";
												echo "</td>";
												break;

							case 'reminder':
												echo "<th>";
												echo "<input type='checkbox' name='bookingnoteid[]' value='" . $note->id . "' />";
												echo "</th>";
												echo "<td colspan='2'>";
													echo "<h6>" . __('Reminder made by : ', 'booking');
													$user = get_userdata( $note->user_id );
													echo $user->user_nicename;
													echo "<span>" . __(' on ', 'booking') . date("jS M Y", strtotime( $note->created_date )) . __(' at ', 'booking') . date("H:i", strtotime( $note->created_date )) . "</span>";
													echo "</h6>";

												echo "</td>";
												break;

							case 'note':
												echo "<th>";
												echo "<input type='checkbox' name='bookingnoteid[]' value='" . $note->id . "' />";
												echo "</th>";
												echo "<td colspan='2'>";
													echo "<h6>" . __('Note made by : ', 'booking');
													$user = get_userdata( $note->user_id );
													echo $user->user_nicename;
													echo "<span>" . __(' on ', 'booking') . date("jS M Y", strtotime( $note->created_date )) . __(' at ', 'booking') . date("H:i", strtotime( $note->created_date )) . "</span>";
													echo "</h6>";

													echo "<p>" . $note->note . "</p>";
												echo "</td>";
												break;
					}
					echo "</tr>";
				}
			}

			// The add notes / payments panel
			echo "<tr>";
			echo "<td colspan='3' class='noteboxrow'>";
			echo "<textarea id='fullbookingnotearea' name='fullbookingnotearea'></textarea>";

			echo "<div class='alignleft'>";
			echo "<input type='submit' class='button' name='addfullnotebutton' id='addfullnotebutton' value='" . __('Add','booking') . "' />";
			echo "&nbsp;";
			echo "<select name='addfullnotetype' id='addfullnotetype'>";
			echo "<option value='note'>" . __('a note', 'booking') . "</option>";
			echo "<option value='payment'>" . __('a payment', 'booking') . "</option>";
			echo "</select>";
			echo "</div>";

			echo "<div class='alignright additionalnoteinfo noteinfo'>";
			echo "</div>";

			echo "<div class='alignright additionalnoteinfo paymentinfo' style='display: none;'>";
			echo __('Amount','booking') . "&nbsp;";
			echo "<input type='text' name='fullpaymentamount' id='fullpaymentamount' value='' />&nbsp;";
			$currencies = $this->booking->get_currencies();
			echo "<select name='fullpaymentcurrency' id='fullpaymentcurrency'>";
			foreach( (array) $currencies as $key => $value) {
				echo "<option value='" . $key . "'>" . $value . "</option>";
			}
			echo "</select>";
			echo "</div>";

			echo "</td>";
			echo "</tr>";

			echo "</table>";

			if(!empty($notes)) {
				echo "<div class='alignleft actions'>";
				echo "<select name='bulknoteaction2' id='bulknoteaction2'>";
				echo "<option value=''>" . __('Bulk Actions','booking') . "</option>";
				echo "<option value='deletenotes'>" . __('Delete','booking') . "</option>";
				echo "</select>&nbsp;";
				echo "<input type='submit' name='bulknotesubmit2' value='" . __('Apply','booking') . "' class='button' />";
				echo "</div>";
			}

			echo "</div> <!-- bookingnotesinner -->\n";
		}
		echo "</div> <!-- innerwrap -->\n";

		// Start sidebar here
		echo "<div class='rightwrap'>";
		$this->show_bookingaddedit_rightpanel($booking);
		echo "</div> <!-- rightwrap -->";

		echo "</form> <!-- editaddbookingform -->";

		echo "</div> <!-- wrap -->";

	}

	// right panels
	function show_bookingaddedit_rightpanel($booking = false) {

		global $page, $action;

		// Search
		echo "<div class='sidebarbox'>";
		echo '<div title="Click to toggle" class="handlediv" id="handlestatus"><br></div>';
		echo "<h2 class='searchformheading rightbarheading'>" . __('Booking Status','booking') . "</h2>";
		echo "<div class='innersidebarbox'>";

		echo "<div class='statuslabel'>" . __('Status','booking');

		echo "<input type='hidden' name='oldstatus' id='oldstatus' value='" . esc_attr($booking->status) . "' />\n";

		$statusoptions = $this->booking->get_statuslist();
		echo "<select name='status' id='savestatus'>";
		if($booking->status == 'trash') {
			echo "<option value='trash' selected='selected'>" . __('Trash') . "</option>";
		}
		foreach($statusoptions as $key => $value) {

			if($key == 'publish' && !current_user_can( 'publish_properties' )) {
				continue;
			}

			echo "<option value='" . $key . "'";
			if($booking->status == $key) {
				echo " selected='selected'";
			}
			echo ">" . $value . "</option>";
		}
		echo "</select>";
		echo "</div>";

		echo "<div class='statusbuttons'>";

		//current_user_can( 'publish_properties' )
		if( in_array($booking->status, array('confirm')) ) {

			echo "<input type='submit' name='save' value='" . __('Save','booking') . "' class='button-primary' />";

		} elseif( in_array($booking->status, array('pending', 'draft', 'trash', 'cancelled', 'reserved', 'deposit', '')) ) {

			if(current_user_can( 'publish_properties' )) {
				echo "<input type='submit' name='publish' value='" . __('Confirm','booking') . "' class='button-primary' />";
				echo "<input type='submit' name='save' value='" . __('Save','booking') . "' class='button' />";
			} else {
				echo "<input type='submit' name='submit' value='" . __('Submit','booking') . "' class='button-primary' />";
				echo "<input type='submit' name='save' value='" . __('Save','booking') . "' class='button' />";
			}
		}

		echo "<a href='" . wp_get_referer() . "' class='cancellink' title='Cancel editing and return to booking list'>Cancel</a>";
		echo "</div>";

		echo "</div> <!-- innersidebarbox -->";
		echo "</div> <!-- sidebarbox -->";

		// Pricing information box
		$this->show_quick_pricing_form( $booking );

		// Quick note functionality
		if($booking->id > 0) {
			$this->show_quick_note_form( $booking );
			$this->show_quick_payment_form( $booking );
		}

	}

	function show_quick_pricing_form( $booking ) {
		echo "<div class='sidebarbox pricingbox'>";
		echo '<div title="Click to toggle" class="handlediv" id="handlepricing"><br></div>';
		echo "<h2 class='rightbarheading'>" . __('Price Details','booking') . "</h2>";
		echo "<div class='innersidebarbox'>";

		$price = $this->booking->get_meta( $booking->id, 'priceamount', '0:USD');
		$price = array_map('trim',explode(":", $price));
		echo "<div class='statuslabel pricesrow'><span>" . __('Price','booking') . "</span>";
		$currencies = $this->booking->get_currencies();
		echo "<select name='pricecurrency' id='pricecurrency' class='pricingcurrency'>";
		foreach( (array) $currencies as $key => $value) {
			echo "<option value='" . $key . "' " . selected($key, $price[1]) . ">" . $value . "</option>";
		}
		echo "</select>";
		echo "<input type='text' name='priceamount' id='priceamount' value='" . number_format($price[0], 2) . "' class='pricingamount' />";
		echo "</div>";

		$charges = $this->booking->get_meta( $booking->id, 'chargesamount', '0:USD');
		$charges = array_map('trim',explode(":", $charges));
		echo "<div class='statuslabel chargesrow'><span>" . __('Charges','booking') . "</span>";
		$currencies = $this->booking->get_currencies();
		echo "<select name='chargescurrency' id='chargescurrency' class='pricingcurrency'>";
		foreach( (array) $currencies as $key => $value) {
			echo "<option value='" . $key . "' " . selected($key, $charges[1]) . ">" . $value . "</option>";
		}
		echo "</select>";
		echo "<input type='text' name='chargesamount' id='chargesamount' value='" . number_format($charges[0], 2) . "' class='pricingamount' />";
		echo "</div>";

		$taxes = $this->booking->get_meta( $booking->id, 'taxesamount', '0:USD');
		$taxes = array_map('trim',explode(":", $taxes));
		echo "<div class='statuslabel taxesrow'><span>" . __('Taxes','booking') . "</span>";
		$currencies = $this->booking->get_currencies();
		echo "<select name='taxescurrency' id='taxescurrency' class='pricingcurrency'>";
		foreach( (array) $currencies as $key => $value) {
			echo "<option value='" . $key . "' " . selected($key, $taxes[1]) . ">" . $value . "</option>";
		}
		echo "</select>";
		echo "<input type='text' name='taxesamount' id='taxesamount' value='" . number_format($taxes[0], 2) . "' class='pricingamount' />";
		echo "</div>";

		$total = $this->booking->get_meta( $booking->id, 'totalamount', '0:USD');
		$total = array_map('trim',explode(":", $total));
		echo "<div class='statuslabel totalrow'><span>" . __('Total','booking') . "</span>";
		$currencies = $this->booking->get_currencies();
		echo "<select name='totalcurrency' id='totalcurrency' class='pricingcurrency'>";
		foreach( (array) $currencies as $key => $value) {
			echo "<option value='" . $key . "' " . selected($key, $total[1]) . ">" . $value . "</option>";
		}
		echo "</select>";
		echo "<input type='text' name='totalamount' id='totalamount' value='" . number_format($total[0], 2) . "' class='pricingamount' />";
		echo "</div>";


		echo "</div> <!-- innersidebarbox -->";
		echo "</div> <!-- sidebarbox -->";
	}

	function show_quick_note_form( $booking ) {
		echo "<div class='sidebarbox'>";
		echo '<div title="Click to toggle" class="handlediv" id="handlequicknote"><br></div>';
		echo "<h2 class='rightbarheading'>" . __('Quick Note','booking') . "</h2>";
		echo "<div class='innersidebarbox'>";
		echo "<textarea name='quicknote' id='quicknotearea'>";
		echo "</textarea>";

		echo "<input type='submit' name='quicknoteaddbutton' id='quicknoteaddbutton' value='" . __('Add Note','booking') . "' class='button' />";
		echo "</div> <!-- innersidebarbox -->";
		echo "</div> <!-- sidebarbox -->";
	}

	function show_quick_payment_form( $booking ) {

		echo "<div class='sidebarbox'>";
		echo '<div title="Click to toggle" class="handlediv" id="handlequickpayment"><br></div>';
		echo "<h2 class='rightbarheading'>" . __('Quick Payment','booking') . "</h2>";
		echo "<div class='innersidebarbox'>";

		echo "<textarea name='quickpayment' id='quickpaymentarea'>";
		echo "</textarea>";

		echo "<div class='statuslabel'>" . __('Amount','booking');
		echo "<input type='text' name='quickpaymentamount' id='quickpaymentamount' value='' />";
		echo "</div>";
		echo "<div class='statuslabel'>" . __('Currency','booking');
		$currencies = $this->booking->get_currencies();
		echo "<select name='quickpaymentcurrency' id='quickpaymentcurrency'>";
		foreach( (array) $currencies as $key => $value) {
			echo "<option value='" . $key . "'>" . $value . "</option>";
		}
		echo "</select>";
		echo "</div>";

		echo "<input type='submit' name='quickpaymentaddbutton' id='quickpaymentaddbutton' value='" . __('Add Payment','booking') . "' class='button' />";


		echo "</div> <!-- innersidebarbox -->";
		echo "</div> <!-- sidebarbox -->";
	}

	function show_booking_rightcalendarpanel() {

		global $page, $action;

		// Start the filter form
		echo "<div class='sidebarbox'>";
		echo '<div title="Click to toggle" class="handlediv" id="handlecal"><br></div>';
		echo "<h2 class='filterformheading rightbarheading'>" . __('Filter by date','booking') . "</h2>";
		echo "<div class='innersidebarbox'>";
			// Calendar find
		$this->show_calendar_sidebar();
		echo "</div> <!-- innersidebarbox -->";
		echo "</div> <!-- sidebarbox -->";

	}

	function show_booking_rightpanel() {

		global $page, $action;

		// Search
		echo "<div class='sidebarbox'>";
		echo '<div title="Click to toggle" class="handlediv" id="handlesearch"><br></div>';
		echo "<h2 class='searchformheading rightbarheading'>" . __('Find bookings','booking') . "</h2>";
		echo "<div class='innersidebarbox'>";
		echo "<form action='' method='get' name='searchform' id='searchform'>";
		echo "<input type='hidden' name='page' value='" . $page . "' />";
		echo "<input type='text' name='bookingsearch' id='bookingsearch' value='";
		if(!empty($_GET['bookingsearch'])) esc_html_e(stripslashes($_GET['bookingsearch']));
		echo "' class='bookingsearch' />";
		echo "<br/>";
		echo "<input type='submit' id='bookingsearchbutton' name='bookingsearchbutton' value='" . __('Search', 'booking') . "' class='button' />";
		echo "</form>";
		echo "</div> <!-- innersidebarbox -->";
		echo "</div> <!-- sidebarbox -->";

		echo "<div class='sidebarbox'>";
		echo '<div title="Click to toggle" class="handlediv" id="handleprop"><br></div>';
		echo "<h2 class='filterformheading rightbarheading'>" . __('Find for property','booking') . "</h2>";
		echo "<div class='innersidebarbox'>";
		echo "<form action='' method='get' name='filterpropform' id='filterpropform'>";
		echo "<input type='hidden' name='page' value='" . $page . "' />";
		// Property find

		echo "<label for='property_id' class='main'>" . __('Property','booking') . "</label>";
		// grab the property list
		$properties = apply_filters( 'property_get_list', 999);
		echo "<select name='property_id' class='wide'>";
		if($properties != 999) {
			// We have some properties but don't know if the one for the booking is here or not
			echo "<option value=''>" . __('Select a property from the list','booking') . "</option>";
			foreach($properties as $key => $property) {
				echo "<option value='" . $property->ID . "'";
				if( isset($_GET['property_id']) && (int) $_GET['property_id'] == $property->ID) {
					echo " selected='selected'";
				}
				echo ">";
				if(!empty($property->reference)) {
					echo esc_html($property->reference) . " - ";
				}
				echo esc_html($property->post_title);
				echo "</option>";
			}
		}
		echo "</select>";

		echo "<input type='submit' id='filteronprop' name='filteronprop' value='" . __('Search', 'booking') . "' class='button' />";
		echo "</form>";
		echo "</div> <!-- innersidebarbox -->";
		echo "</div> <!-- sidebarbox -->";

		echo "<div class='sidebarbox'>";
		echo '<div title="Click to toggle" class="handlediv" id="handlecont"><br></div>';
		echo "<h2 class='filterformheading rightbarheading'>" . __('Find for contact','booking') . "</h2>";
		echo "<div class='innersidebarbox'>";
		echo "<form action='' method='get' name='filtercontactform' id='filtercontactform'>";
		echo "<input type='hidden' name='page' value='" . $page . "' />";
		// Contact find
		echo "<label for='contactsearch' class='main'>" . __('Contact','booking') . "</label>";
		echo "<input type='text' name='contactsearch' class='wide' value='";
		if(!empty($_GET['contactsearch'])) esc_html_e(stripslashes($_GET['contactsearch']));
		echo "' />";
		echo "<input type='submit' id='filteroncont' name='filteroncont' value='" . __('Search', 'booking') . "' class='button' />";
		echo "</form>";
		echo "</div> <!-- innersidebarbox -->";
		echo "</div> <!-- sidebarbox -->";

	}

	function show_options_rightpanel() {

		if(!defined('STAYPRESS_HIDE_DONATIONS')) {
			echo "<div class='sidebarbox'>";
			echo '<div title="Click to toggle" class="handlediv" id="handlestatus"><br></div>';
			echo "<h2 class='rightbarheading'>" . __('Show Your Support','property') . "</h2>";
			echo "<div class='innersidebarbox'>";


			echo "<div class='highlightbox blue'>";
			echo "<p>";
			echo __('We don\'t take donations here. Instead, we pick a charity every month and ask you to donate directly to them if you feel the urge to give.','property');
			echo "</p>";
			echo "</div>";

			echo "<div class='highlightbox'>";
			echo "<p>";
			echo __('<strong>Support Bite-Back</strong><br/><br/>Bite-Back is a pioneering shark and marine conservation charity which is running successful campaigns to end the sale of shark fin soup in Britain.<br/><br/>','property');
			echo '<img src="' . SPPCommon::property_url('images/biteback.jpg') . '" alt="bite-back" style="margin-left: 30px;" />';
			echo "</p>";

			echo "<p>";
			echo __('To find out more about Bite-Back visit their website <a href="http://www.bite-back.com/">here</a>.<br/><br/><strong>To make a direct donation please go <a href="https://uk.virginmoneygiving.com/fundraiser-web/donate/makeDonationForCharityDisplay.action?charityId=1002357&frequencyType=S">here</a></strong>.','property');
			echo "</p>";

			echo "<p>";
			echo __('If you make a donation, then please let us know and we\'ll make sure we put out a big thank you on our site.','property');
			echo "</p>";
			echo "</div>";

			echo "<br/>";

			echo "</div> <!-- innersidebarbox -->";
			echo "</div> <!-- sidebarbox -->";
			echo "<br/>";

		}

	}

	function show_calendar($startyear, $startmonth, $holdingdiv = true) {

		global $filterstartday;

		if(!empty($filterstartday)) {
			if( $startyear == date( 'Y', (int) $filterstartday) && $startmonth == date( 'n', (int) $filterstartday) ) {
				$selday = date( 'j', (int) $filterstartday );
			} else {
				$selday = 0;
			}

		} else {
			$selday = 0;
		}

		if($startmonth == date('n') && $startyear == date('Y')) {
			$today = date('j');
			if($selday == 0) {
				$selday = $today;
			}
		} else {
			$today = 0;
		}

		$start = strtotime("$startyear-$startmonth-1");
		$dom = 1;

		$bookings = $this->booking->get_montharray($startyear, $startmonth, false, false, false);

		if($holdingdiv) {
			echo "<div class='month'>\n";
		}

		echo "<h4 class='monthname'>";
		echo "<a href='#prevmonth' class='previousmonth' id='" . date("Y-n", strtotime('-1 month', $start)) . "'>&nbsp;</a>";
		echo "<span class='name'>" . strftime('%B %Y', $start) . "</span>";
		echo "<a href='#nextmonth' class='nextmonth' id='" . date("Y-n", strtotime('+1 month', $start)) . "'>&nbsp;</a>";
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

				if($selday == $dom) {
					$class .= " selday";
				}

				if($today == $dom) {
					$class .= " today";
				}

				if(isset($bookings[$startyear . STR_PAD($startmonth, 2 , "0", STR_PAD_LEFT) . STR_PAD($dom, 2 , "0", STR_PAD_LEFT)])) {
					//echo $startyear . STR_PAD($startmonth, 2 , "0", STR_PAD_LEFT) . STR_PAD($dom, 2 , "0", STR_PAD_LEFT);
					switch($bookings[$startyear . STR_PAD($startmonth, 2 , "0", STR_PAD_LEFT) . STR_PAD($dom, 2 , "0", STR_PAD_LEFT)]) {
						case 1:	$mark = 1;
								break;
						case 2: $mark = 2;
								break;
						case 3: $mark = 3;
								break;
						case 4: $mark = 4;
								break;
						default:
								$mark = 4;
					}
					$mark = "<div class='booked booked-" . $mark . "'></div>";
				} else {
					$mark = '';
				}

				echo "<li class='$class'>";
				echo "<a href='" . add_query_arg( array("filterstartday" => strtotime("$startyear-$startmonth-$dom")), remove_query_arg('paged') ) . "'>";
				echo $dom++;
				echo "</a>";
				echo $mark;
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

			if($selday == $n) {
				$class .= " selday";
			}

			if($today == $n) {
				$class .= " today";
			}

			if(isset($bookings[$startyear . STR_PAD($startmonth, 2 , "0", STR_PAD_LEFT) . STR_PAD($n, 2 , "0", STR_PAD_LEFT)])) {
				//echo $startyear . STR_PAD($startmonth, 2 , "0", STR_PAD_LEFT) . STR_PAD($dom, 2 , "0", STR_PAD_LEFT);
				switch($bookings[$startyear . STR_PAD($startmonth, 2 , "0", STR_PAD_LEFT) . STR_PAD($n, 2 , "0", STR_PAD_LEFT)]) {
					case 1:	$mark = 1;
							break;
					case 2: $mark = 2;
							break;
					case 3: $mark = 3;
							break;
					case 4: $mark = 4;
							break;
					default:
							$mark = 4;
				}
				$mark = "<div class='booked booked-" . $mark . "'></div>";
			} else {
				$mark = '';
			}

			$dow++;
			echo "<li class='$class'>";
			echo "<a href='" . add_query_arg( array("filterstartday" => strtotime("$startyear-$startmonth-$n")), remove_query_arg('paged') ) . "'>";
			echo $n;
			echo "</a>";
			echo $mark;
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

	function show_calendar_sidebar() {

		global $filterstartday;

		if(!empty($filterstartday)) {
			$this->show_calendar(date("Y", (int) $filterstartday), date("n", (int) $filterstartday));
		} else {
			$this->show_calendar(date("Y"), date("n"));
		}

	}

	function show_dateselect_calendar($startyear, $startmonth, $holdingdiv = true) {

		global $filterstartday;

		if(!empty($filterstartday)) {
			if( $startyear == date( 'Y', (int) $filterstartday) && $startmonth == date( 'n', (int) $filterstartday) ) {
				$selday = date( 'j', (int) $filterstartday );
			} else {
				$selday = 0;
			}

		} else {
			$selday = 0;
		}

		if($startmonth == date('n') && $startyear == date('Y')) {
			$today = date('j');
			if($selday == 0) {
				$selday = $today;
			}
		} else {
			$today = 0;
		}


		$start = strtotime("$startyear-$startmonth-1");
		$dom = 1;

		if($holdingdiv) {
			echo "<div class='month'>\n";
		}

		echo "<h4 class='monthname'>";
		echo "<a href='#prevmonth' class='previousmonth' id='" . date("Y-n", strtotime('-1 month', $start)) . "'>&nbsp;</a>";
		echo "<span class='name'>" . strftime('%B %Y', $start) . "</span>";
		echo "<a href='#nextmonth' class='nextmonth' id='" . date("Y-n", strtotime('+1 month', $start)) . "'>&nbsp;</a>";
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

				if($selday == $dom) {
					$class .= " selday";
				}

				if($today == $dom) {
					$class .= " today";
				}

				echo "<li class='$class'>";
				echo "<a href='" . add_query_arg( array("filterstartday" => strtotime("$startyear-$startmonth-$dom")), remove_query_arg('paged') ) . "'>";
				echo $dom++;
				echo "</a>";
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

			if($selday == $n) {
				$class .= " selday";
			}

			if($today == $n) {
				$class .= " today";
			}

			$dow++;
			echo "<li class='$class'>";
			echo "<a href='" . add_query_arg( array("filterstartday" => strtotime("$startyear-$startmonth-$n")), remove_query_arg('paged') ) . "'>";
			echo $n;
			echo "</a>";
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


	function show_property_availability_calendar() {

		echo "<h2>" . __('Filter list based on availability','booking') . "</h2>";

		echo "<label class='main'>" . __('Starting date','booking') . "</label>";

		$this->show_dateselect_calendar(date("Y"), date("n"));

		echo "<label class='main'>" . __('Availability period','booking') . "</label>";

		// period
		echo "<select name='' id=''>";
		echo "<option value=''>" . "</option>";
		for($n = 1; $n <= 31; $n++) {
			echo "<option value='" . $n . "'";
			echo ">" . $n . "</option>";
		}
		echo "</select>&nbsp;";
		// unit

		$units = array(	"d" => __('days','booking'),
						"w" => __('weeks','booking'),
						"m" => __('months','booking'),
						"y" => __('years','booking'),
						);

		$units = apply_filters( 'staypress_availability_units', $units );

		echo "<select name='' id=''>";
		echo "<option value=''>" . "</option>";
		foreach($units as $key => $unit) {
			echo "<option value='" . $key . "'";
			echo ">" . $unit . "</option>";
		}

		echo "</select>&nbsp;";

		echo "<br/><br/>";
	}

	function update_booking_options() {

		global $action, $page;

		wp_reset_vars( array('action', 'page') );

		if(isset($action) && $action == 'updateoptions') {

			check_admin_referer('update-booking-options');

			$options = array();
			$options['checkintext'] = $_POST['checkintext'];
			$options['checkouttext'] = $_POST['checkouttext'];
			SPBCommon::update_option('sp_booking_options', $options);
			wp_safe_redirect( add_query_arg('msg', 1, wp_get_referer()));

		}

	}

	function show_options_panel() {

		global $action, $page;

		$defaultoptions = array(	'checkintext'	=>	'Check in',
									'checkouttext'	=>	'Check out'
								);
		$bookingoptions = SPBCommon::get_option('sp_booking_options', $defaultoptions);

		$messages = array();
		$messages[1] = __('Your options have been updated.','membership');

		echo "<div class='wrap nosubsub'>";

			echo "<div class='innerwrap'>\n";
			echo "<h2><a href='' class='selected'>" . __('Edit Options','property') . "</a></h2>";

			echo "<div class='wrapcontents'>\n";

					if(isset($_GET['msg'])) {
						echo '<div id="upmessage" class="updatedmessage"><p>' . $messages[(int) $_GET['msg']];
						echo '<a href="#close" id="closemessage">' . __('close', 'property') . '</a>';
						echo '</p></div>';
						$_SERVER['REQUEST_URI'] = remove_query_arg(array('msg'), $_SERVER['REQUEST_URI']);
					}

					echo "<form action='" . admin_url("admin.php?page=" . $page) . "' method='post'>";

					echo "<input type='hidden' name='action' value='updateoptions' />";
					wp_nonce_field('update-booking-options');

					echo "<p>";
					echo __('The options below control the settings, text and urls of your StayPress installation. For multi-site installs, these may change the settings for <strong>all</strong> your sites, depending on your configuration.','booking');
					echo "</p>";

					echo "<h3>" . __('Search form labels','booking') . "</h3>";
					echo "<p>" . __('Use the settings options below to change the labels on the main search forms.','booking') . "</p>";

					echo "<table class='form-table'>";
					echo "<tbody>";
					echo "<tr valign='top'>";
					// Un translated for now
					echo "<th scope='row'>" . __('Check in label','booking') . "</th>";
					echo "<td>";
					echo "<input type='text' name='checkintext' value='" . esc_attr($bookingoptions['checkintext'])  . "' class='narrow' />";
					echo "</td>";
					echo "</tr>";

					echo "<tr valign='top'>";
					// Un translated for now
					echo "<th scope='row'>" . __('Check out label','booking') . "</th>";
					echo "<td>";
					echo "<input type='text' name='checkouttext' value='" . esc_attr($bookingoptions['checkouttext'])  . "' class='narrow' />";
					echo "</td>";
					echo "</tr>";

					echo "</tbody>";
					echo "</table>";

					do_action( 'staypress_booking_options_form', $bookingoptions );

					echo "<br style='clear:both;' />";

					echo "<p class='submit'>";
						echo "<input type='submit' name='Submit' class='button-primary' value='" . esc_attr('Update Options') . "' />";
					echo "</p>";

					echo "</form>\n";

			echo "</div> <!-- wrapcontents -->\n";

			echo "</div> <!-- innerwrap -->\n";

			// Start sidebar here
			echo "<div class='rightwrap'>";
			$this->show_options_rightpanel();
			echo "</div> <!-- rightwrap -->";

			echo "</div> <!-- wrap -->\n";

	}


	function translate_status($status) {

		$stati = $this->booking->get_statuslist();
		$stati = apply_filters('staypress_booking_status_list', $stati);

		if(isset($stati[$status])) {
			return $stati[$status];
		} else {
			return "Not Found";
		}

	}

	// Linked properties filters
	function add_property_booking_action($actions, $property_id) {

		if( current_user_can('edit_booking') ) {
			// Current user can create a booking
			$actions[] = "<a href='admin.php?page=booking-add&amp;property=" . $property_id . "'>" . __('Book','booking') . "</a>";

		}

		return $actions;
	}

}

?>