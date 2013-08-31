<?php

if(!class_exists('booking_model')) {

	class booking_model {

		var $build = 1;

		var $db;

		var $staypress_prefix = 'sp_';			// Added near the front of table names e.g. wp_sp_property

		var $tables = array('booking', 'property', 'contact', 'queue', 'booking_note', 'booking_meta');

		// Tables pointers
		var $booking;
		var $property;

		// Contact table
		var $contact;

		// Plugin update queue
		var $queue;

		// Notes and meta
		var $booking_note;
		var $booking_meta;

		// The current account and blog to access
		var $user_id = 0;
		var $blog_id = 0;

		// The user record
		var $user;

		var $property_id = false;

		var $showdeleted = false;

		// Timezone dates
		var $timeoffset = 0;

		function __construct($wpdb, $installed_build = false) {

			// Grab local pointer the database class
			$this->db =& $wpdb;

			// Set the table prefixes
			$this->set_prefix();

			if($installed_build !== false && $this->build > $installed_build) {
				// A build was passed and it is lower than our current one.
				$this->update_tables_from($installed_build);
			}

			$this->blog_id = $this->db->blogid;

		}

		function booking_model($wpdb, $installed_build = false) {
			$this->__construct($wpdb, $installed_build);
		}

		function update_tables_from($installed_build = false) {

			include_once(SPBCommon::booking_dir('includes/upgrade.php'));

			sp_upgradebooking($installed_build);

		}

		function get_booked_properties($fromstamp, $tostamp, $type = 'confirm,reserved,deposit') {

			if($type == 'all') {
				$type = 'confirm,draft,reserved,trash,cancelled,deposit,pending';
			}

			$typearray = explode(",", $type);

			$fromdate = date("Y-m-d", $fromstamp);
			$todate = date("Y-m-d", $tostamp);

			$sql = $this->db->prepare( "SELECT property_id FROM {$this->booking} WHERE startdate <= %s AND enddate >= %s AND status IN ('" . implode("','", $typearray) . "') ", $todate, $fromdate );

			$results = $this->db->get_col( $sql );

			if(!empty($results)) {
				return $results;
			} else {
				return array(0);
			}

		}

		function filter_bookings($filter, $startat = 0, $show = 15) {

			if(!empty($filter) && is_array($filter)) {

				//print_r($filter);
				if(!current_user_can('edit_others_bookings')) {
					$limitusers = $this->db->prepare("AND user_id = %d", $this->user_id);
				} else {
					$limitusers = '';
				}

				// Handle the searching first
				$insearch = false;
				$specificdate = false;
				$specificperiod = false;

				if(!empty($filter['search'])) {
					// This is a search as well as a filter
					$find = '%' . $filter['search'] . '%';
					$sqlwhere = $this->db->prepare( " AND (title LIKE %s OR notes LIKE %s)", $find, $find );
					// we are in a search
					$insearch = true;
				} else {
					$sqlwhere = '';
				}

				if(!empty($filter['property_id'])) {
					$sqlwhere .= $this->db->prepare( " AND property_id = %d", $filter['property_id'] );
					// we are in a search
					$insearch = true;
				}

				// Contact search for contact post type
				if(!empty($filter['contactsearch'])) {

					// Find the contact id to filter the results on
					$bookings = $this->_contact_search( $filter['contactsearch'] );

					if(!empty($bookings) && is_array($bookings)) {
						$sqlwhere .= " AND id IN (" . implode(',', $bookings) . ")";
					}
					// we are in a search
					$insearch = true;
				}

				// Query start date
				if(isset($filter['startday'])) {
					$sdate = strtotime($filter['startday']);
					$startday = date("Y-m-d", $sdate);
					$endday = $startday;
					$period = 1;
					// Set specific date
					$specificdate = true;
				} else {
					$sdate = strtotime("now");
					$startday = date("Y-m-d", $sdate);
					$endday = $startday;
					$period = 1;
				}

				if(isset($filter['period'])) {

					switch($filter['period']) {
						case 'day':		$endday = $startday;
										$specificperiod = true;
										break;

						case 'nextday':	$startday = date("Y-m-d", strtotime('+1 day', $sdate));
										$endday = $startday;
										$specificperiod = true;
										break;

						case 'week':	$endday = date("Y-m-d", strtotime('+7 days', $sdate));
										$specificperiod = true;
										break;

						case 'month':	$endday = date("Y-m-d", strtotime('+1 month', $sdate));
										$period = 31;
										$specificperiod = true;
										break;

						default:		$endday = $startday;
										$period = 1;
										break;

					}

				} else {
					$endday = $startday;
				}

				// Build the SQL
				if(in_array($filter['type'], array( 'all', 'in' ))) {
					$sqlin = "SELECT SQL_CALC_FOUND_ROWS * FROM {$this->booking} WHERE 1=1";
					if($specificperiod || $specificdate) {
						$sqlin .= $this->db->prepare( " AND startdate >= %s AND startdate <= %s", $startday, $endday );
					}
					$sqlin .= $this->db->prepare( " AND status != %s {$limitusers} AND blog_id = %d", 'trash', $this->blog_id );
					$sqlin .= $sqlwhere;
					$sqlin .= $this->db->prepare( " ORDER BY startdate, starttime ASC LIMIT %d, %d", $startat, $show );

				} else {
					$sqlin = '';
				}

				if(in_array($filter['type'], array( 'all', 'out' ))) {
					$sqlout = "SELECT SQL_CALC_FOUND_ROWS * FROM {$this->booking} WHERE 1=1";
					if($specificperiod || $specificdate) {
						$sqlout .= $this->db->prepare( " AND enddate >= %s AND enddate <= %s", $startday, $endday );
					}
					$sqlout .= $this->db->prepare( " AND status != %s {$limitusers} AND blog_id = %d", 'trash', $this->blog_id );
					$sqlout .= $sqlwhere;
					$sqlout .= $this->db->prepare( " ORDER BY enddate, endtime ASC LIMIT %d, %d", $startat, $show );
				} else {
					$sqlout = '';
				}

				if(in_array($filter['type'], array( 'all', 'occupied' ))) {
					$sqlocc = "SELECT SQL_CALC_FOUND_ROWS * FROM {$this->booking} WHERE 1=1";
					if($specificperiod || $specificdate) {
						$sqlocc .= $this->db->prepare( " AND enddate >= %s AND startdate <= %s", $startday, $endday );
					}
					$sqlocc .= $this->db->prepare( " AND status != %s {$limitusers} AND blog_id = %d", 'trash', $this->blog_id );
					$sqlocc .= $sqlwhere;
					$sqlocc .= $this->db->prepare( " ORDER BY enddate, endtime ASC LIMIT %d, %d", $startat, $show );
				} else {
					$sqlocc = '';
				}

			}

			return compact('sqlin', 'sqlout', 'sqlocc');
		}

		function _contact_search( $search ) {

			$sql = $this->db->prepare( "SELECT post_id FROM {$this->db->postmeta} WHERE meta_value LIKE %s AND meta_key IN ('contact_name','contact_email','contact_tel','contact_address')", '%' . $search . '%' );

			$results = $this->db->get_col( $sql );

			if(!empty($results)) {
				$results = array_unique($results);

				// We have the results table, now we need to the booking id's for these contacts
				$sql = $this->db->prepare( "SELECT meta_value FROM {$this->db->postmeta} WHERE meta_key = %s AND post_id IN (" . implode(',', $results) . ")", '_booking_id' );

				$bookings = $this->db->get_col( $sql );

				$bookings = array_filter(array_unique($bookings));

				return $bookings;
			}

			return $results;
		}

		function bookings_exist() {

			if(!current_user_can('edit_others_bookings')) {
				$limitusers = $this->db->prepare("AND user_id = %d", $this->user_id);
			} else {
				$limitusers = '';
			}

			$sql = $this->db->prepare( "SELECT COUNT(*) FROM {$this->booking} WHERE status != %s {$limitusers}", 'trash');

			return $this->db->get_var( $sql );

		}

		function get_booking($id = false, $enforcepermissions = true) {

			$sql = $this->db->prepare( "SELECT * FROM {$this->booking} WHERE id = %d", $id );

			if($enforcepermissions && !current_user_can( 'edit_others_bookings' )) {
				$sql .= $this->db->prepare( " AND user_id = %d", $this->user_id );
			}

			$booking = $this->db->get_row($sql);

			if($booking) {

				return $booking;

			} else {
				return new WP_Error('notfound', __('The booking could not be found.','booking'));
			}
		}

		function get_bookings($startat = 0, $show = 15, $filter = false) {

			$sqlin = '';
			$sqlout = '';

			if($filter) {
				extract( $this->filter_bookings($filter, $startat, $show) );
			} else {
				// No filter so do the default sql of all records for today only

				if(!current_user_can('edit_others_bookings')) {
					$limitusers = $this->db->prepare("AND user_id = %d", $this->user_id);
				} else {
					$limitusers = '';
				}

				$today = date("Y-m-d");

				$sqlin = $this->db->prepare( "SELECT SQL_CALC_FOUND_ROWS * FROM {$this->booking} WHERE startdate = %s AND status != %s {$limitusers}", $today, 'trash' );
				$sqlin .= $this->db->prepare( " AND blog_id = %d", $this->blog_id );
				$sqlin .= $this->db->prepare( " ORDER BY starttime ASC LIMIT %d, %d", $startat, $show );

				$sqlout = $this->db->prepare( "SELECT SQL_CALC_FOUND_ROWS * FROM {$this->booking} WHERE enddate = %s AND status != %s {$limitusers}", $today, 'trash' );
				$sqlout .= $this->db->prepare( " AND blog_id = %d", $this->blog_id );
				$sqlout .= $this->db->prepare( " ORDER BY endtime ASC LIMIT %d, %d", $startat, $show );

				$sqlocc = $this->db->prepare( "SELECT SQL_CALC_FOUND_ROWS * FROM {$this->booking} WHERE enddate >= %s AND startdate <= %s AND status != %s {$limitusers}", $today, $today, 'trash' );
				$sqlocc .= $this->db->prepare( " AND blog_id = %d", $this->blog_id );
				$sqlocc .= $this->db->prepare( " ORDER BY endtime ASC LIMIT %d, %d", $startat, $show );

			}

			if(!empty($sqlin)) {
				$resin = $this->db->get_results( $sqlin );
				$resintotal = $this->get_foundrows();
			} else {
				$resin = array();
				$resintotal = 0;
			}

			if(!empty($sqlout)) {
				$resout = $this->db->get_results( $sqlout );
				$resouttotal = $this->get_foundrows();
			} else {
				$resout = array();
				$resouttotal = 0;
			}

			if(!empty($sqlocc)) {
				$resocc = $this->db->get_results( $sqlocc );
				$resocctotal = $this->get_foundrows();
			} else {
				$resocc = array();
				$resocctotal = 0;
			}

			return compact('resin', 'resout', 'resocc', 'resintotal', 'resouttotal', 'resocctotal');

		}

		function validate_booking($booking_id, $booking) {

		}

		function insert_booking($booking) {

			if(!empty($booking)) {

				if( isset($booking['id']) && $booking['id'] > 0 ) {
					// update
					return $this->update_booking($booking);

				} else {
					// insert

					$this->db->insert( $this->booking, $booking );

					$booking_id = $this->db->insert_id;
				}

				return $booking_id;

			} else {
				return new WP_Error('nobooking', __('The booking details could not be added.','booking') );
			}

		}

		function update_booking($booking) {

			if(!empty($booking)) {

				if( isset($booking['id']) && $booking['id'] > 0 ) {

					$existingbooking = $this->get_booking($booking['id']);

					// Check for permissions
					if(is_wp_error($existingbooking)) {
						// No property or not this users property and user can't edit others properties
						return $existingbooking;
					}

					// update
					$this->db->update( $this->booking, $booking, array( 'id' => $booking['id'] ) );

					return $booking['id'];

				} else {
					// insert
					return $this->insert_booking( $booking );
				}

				return $booking_id;

			} else {
				return new WP_Error('nobooking', __('The booking details do not exist.','booking') );
			}

		}

		function delete_booking( $id = false ) {

			$booking = $this->get_booking($id);

			if(is_wp_error($booking)) {
				// No booking or not this users booking and user can't edit others bookings
				return $booking;
			}

			if(current_user_can('delete_booking')) {

				$sql = $this->db->prepare( "UPDATE {$this->booking} AS b SET status = 'trash' WHERE b.id = %d", $id );
				$results = $this->db->query( $sql );

				if($results) {
					return $id;
				} else {
					return new WP_Error('notdeleted', __('The booking could not be deleted.','booking'));
				}

			} else {
				return new WP_Error('nopermissions', __('You do not have permissions to delete this booking.','booking'));
			}

		}

		// Building month array functions from the more useful data model we use
		function get_month($year, $month, $prepostpack = false, $property_id = false, $excludestatus = array('draft', 'trash'), $showforall = true) {
			// Build the date range

			$firstdate = $year . "-" . $month ."-1";
			$numdays = date('t', strtotime($firstdate));
			$lastdate = $year . "-" . $month ."-" . $numdays;

			// Check if we want to pre or post pack for changeover days
			if($prepostpack) {
				$newfirstdate = strtotime("-1 day", strtotime($firstdate));
				$newlastdate = strtotime("+1 day", strtotime($lastdate));
				$firstdate = date("Y-n-j", $newfirstdate);
				$lastdate = date("Y-n-j", $newlastdate);
			}

			$sql = $this->db->prepare( "SELECT id, startdate, enddate, title, status FROM {$this->booking} WHERE blog_id = %d", $this->db->blogid);
			$sql .= $this->db->prepare( " AND (startdate <= %s AND enddate >= %s) AND status NOT IN ('" . implode("','", $excludestatus) . "')", $lastdate, $firstdate );

			if($property_id) {
				$sql .= $this->db->prepare( " AND property_id = %d", $property_id );
			}

			if(!$showforall && !current_user_can('edit_others_bookings')) {
				$sql .= $this->db->prepare(" AND user_id = %d", $this->user_id);
			}

			$sql .= " ORDER BY startdate ASC;";

			$result = $this->db->get_results( $sql );
			if(!empty($result)) {
				return $result;
			} else {
				return False;
			}
		}

		function get_montharray($year, $month, $prepostpack = false, $property_id = false, $showforall = true ) {
			// Get the main events array
			$events = $this->get_month($year, $month, $prepostpack, $property_id, $excludestatus = array('draft', 'trash'), $showforall);
			// Process into new format.
			$master = array();

			$mbegin = strtotime($year . "-" . $month . "-1");
			$mend = strtotime($year . "-" . $month . "-" . date("t",$mbegin));
			if($prepostpack) {
				$newfirstdate = strtotime("-1 day", $mbegin);
				$newlastdate = strtotime("+1 day", $mend);
				$mbegin = $newfirstdate;
				$mend = $newlastdate;
			}

			if(!empty($events)) {
				foreach($events as $event) {
					$today = strtotime($event->startdate);
					$end = strtotime($event->enddate);
					while($today <= $end) {
						if($today >= $mbegin && $today <= $mend) {
							// return only dates in this month
							$key = date("Ymd", $today);
							if(!isset($master[$key])) {
								$master[$key] = 1;
							} else {
								$master[$key] += 1;
							}
						}
						$today = strtotime("+1 day", $today);
					}
				}
			}

			return $master;
		}


		function get_statuslist() {

			return apply_filters('booking_statuslist', array(	'draft'		=>	__('Draft', 'booking'),
							'cancelled'	=>	__('Cancelled', 'booking'),
							'reserved'	=>	__('Reserved', 'booking'),
							'deposit'	=>	__('Deposit paid', 'booking'),
							'pending' 	=> 	__('Pending', 'booking'),
							'confirm'	=>	__('Confirmed / Paid', 'booking')
						));

		}

		function set_prefix($site_id = false) {

			foreach($this->tables as $table) {
				if(isset($this->db->base_prefix) && defined('STAYPRESS_GLOBAL_TABLES') && STAYPRESS_GLOBAL_TABLES == true ) {
					// Use the base_prefix - WPMU
					$this->$table = $this->db->base_prefix . $this->staypress_prefix . $table;
				} else {
					$this->$table = $this->db->prefix . $this->staypress_prefix . $table;
				}
			}

		}

		function set_userid($user_id) {
			$this->user_id = $user_id;

			$this->user = new WP_User( (int) $user_id );
		}

		function set_blogid($blog_id) {
			$this->blog_id = $blog_id;
		}

		function show_deleted($show) {
			$this->showdeleted = $show;
		}

		function get_lastid() {
			return $this->db->get_var( "SELECT LAST_INSERT_ID();" );
		}

		function get_foundrows() {
			return $this->db->get_var( "SELECT FOUND_ROWS();" );
		}

		function set_timezone($offset = 0) {
			$this->timeoffset = $offset;
		}

		// Maybe use this version or alter it
		function current_time( $type, $gmt = 0 ) {
			switch ( $type ) {
				case 'mysql':
					return ( $gmt ) ? gmdate( 'Y-m-d H:i:s' ) : gmdate( 'Y-m-d H:i:s', ( time() + ( $this->timeoffset * 3600 ) ) );
					break;
				case 'timestamp':
					return ( $gmt ) ? time() : time() + ( $this->timeoffset * 3600 );
					break;
			}
		}

		// Contacts functions - ay move to different class
		function get_contact($contact_id) {

			$sql = $this->db->prepare( "SELECT * FROM {$this->contact} WHERE id = %d", $contact_id );

			$results = $this->db->get_row( $sql );

			if(!empty($results)) {
				return $results;
			} else {
				return false;
			}

		}

		function get_bookingcontacts($booking_id = false, $type = 'publish,draft,pending,private') {

			$args = array(
				'post_type' => STAYPRESS_CONTACT_POST_TYPE,
				'post_status' => $type,
				'meta_key' => '_booking_id',
				'orderby' => 'post_modified',
				'order' => 'DESC',
				'meta_value' => $booking_id
			);

			if(!$this->user->has_cap( 'edit_others_contacts' )) {
				$args['author'] = $this->user->ID;
			}

			$get_contacts = new WP_Query;
			$contactlist = $get_contacts->query($args);

			return $contactlist;

		}

		function insert_contact($contact_id = false, $contact = false) {

			//function get_post_custom($post_id = 0) {

			$post = array(
			'post_title' => $contact['contact_name'],
			'post_name' => sanitize_title($contact['contact_name']),
			'post_status' => 'private', // You can also make this pending, or whatever you want, really.
			'post_author' => $this->user_id,
			'post_category' => array(get_option('default_category')),
			'post_type' => STAYPRESS_CONTACT_POST_TYPE,
			'comment_status' => 'closed'
			);

			// update the post
			$contact_id = wp_insert_post($post);

			if(!is_wp_error($contact_id)) {
				update_metadata('post', $contact_id, 'contact_name', $contact['contact_name']);
				update_metadata('post', $contact_id, 'contact_email', $contact['contact_email']);
				update_metadata('post', $contact_id, 'contact_tel', $contact['contact_tel']);
				update_metadata('post', $contact_id, 'contact_address', $contact['contact_address']);
				update_metadata('post', $contact_id, '_booking_id', $contact['booking_id']);
			}

			return $contact_id;

		}

		function update_contact($contact_id = false, $contact = false) {

			$post = array(
			'post_title' => $contact['contact_name'],
			'post_name' => sanitize_title($contact['contact_name']),
			'post_status' => 'private', // You can also make this pending, or whatever you want, really.
			'post_author' => $this->user_id,
			'post_category' => array(get_option('default_category')),
			'post_type' => STAYPRESS_CONTACT_POST_TYPE,
			'comment_status' => 'closed'
			);

			if($contact_id > 0) {
				$post['ID'] = $contact_id;
			}

			// update the post
			$contact_id = wp_update_post($post);

			if(!is_wp_error($contact_id)) {
				update_metadata('post', $contact_id, 'contact_name', $contact['contact_name']);
				update_metadata('post', $contact_id, 'contact_email', $contact['contact_email']);
				update_metadata('post', $contact_id, 'contact_tel', $contact['contact_tel']);
				update_metadata('post', $contact_id, 'contact_address', $contact['contact_address']);
				update_metadata('post', $contact_id, '_booking_id', $contact['booking_id']);
			}

			return $contact_id;

		}

		function add_quick_note($booking_id, $note) {
			return $this->add_note( $booking_id, 'note', $note );
		}

		function add_quick_payment($booking_id, $note, $amount, $currency) {
			return $this->add_note( $booking_id, 'payment', $note, array( 'amount' => $amount, 'currency' => $currency ) );
		}

		function add_note($booking_id, $type, $note, $note_meta = '', $reminder = false, $remindertimestamp = 0) {

			$details = array(
								'booking_id'	=>	$booking_id,
								'user_id'		=>	$this->user_id,
								'note_type'		=>	$type,
								'note'			=>	$note,
								'updated_date'	=>	date( 'Y-m-d H:i:s' ),
								'created_date'	=>	date( 'Y-m-d H:i:s' )
							);

			if(!empty($note_meta)) {
				$details['note_meta'] = serialize($note_meta);
			}

			if($reminder === true) {
				$details['reminderrequested'] = 1;
				$details['reminder_timestamp'] = $remindertimestamp;
			}

			return $this->db->insert( $this->booking_note, $details );

		}

		function get_notes( $booking_id, $type = 'all' ) {

			if($type == 'all') {
				$type = array('note', 'reminder', 'payment');
			} else {
				$type = array( $type );
			}

			$sql = $this->db->prepare( "SELECT * FROM {$this->booking_note} WHERE booking_id = %d AND note_type IN ('" . implode( "','", $type ) . "') ORDER BY created_date DESC", $booking_id );

			$results = $this->db->get_results( $sql );

			if(!empty($results)) {
				return $results;
			} else {
				return false;
			}

		}

		function get_note_meta( $booking_note_id ) {

		}

		function update_note() {

		}

		function delete_note( $booking_note_id ) {

			$sql = $this->db->prepare( "DELETE FROM {$this->booking_note} WHERE id = %d", $booking_note_id );

			return $this->db->query( $sql );

		}

		function get_currencies() {

			return apply_filters('booking_currencies', array(
							'USD'	=>	"USD",
							'EURO'	=>	"EURO",
							'GBP'	=>	"GBP"
						));

		}

		// Booking meta information
		function get_meta($id, $key, $default = false) {

			$sql = $this->db->prepare( "SELECT meta_value FROM {$this->booking_meta} WHERE meta_key = %s AND booking_id = %d AND blog_id = %d", $key, $id, $this->blog_id);

			$row = $this->db->get_var( $sql );

			if(empty($row)) {
				return $default;
			} else {
				return $row;
			}

		}

		function add_meta($id, $key, $value) {

			return $this->insertorupdate( $this->booking_meta, array( 'booking_id' => $id, 'meta_key' => $key, 'meta_value' => $value, 'blog_id' => $this->blog_id) );

		}

		function update_meta($id, $key, $value) {

			return $this->insertorupdate( $this->booking_meta, array( 'booking_id' => $id, 'meta_key' => $key, 'meta_value' => $value, 'blog_id' => $this->blog_id) );

		}

		function delete_meta($id, $key) {

			$sql = $this->db->prepare( "DELETE FROM {$this->booking_meta} WHERE meta_key = %s AND booking_id = %d AND blog_id = %s", $key, $id, $this->blog_id);
			return $this->db->query( $sql );

		}

		function insertorupdate( $table, $query ) {

				$fields = array_keys($query);
				$formatted_fields = array();
				foreach ( $fields as $field ) {
					$form = '%s';
					$formatted_fields[] = $form;
				}
				$sql = "INSERT INTO `$table` (`" . implode( '`,`', $fields ) . "`) VALUES ('" . implode( "','", $formatted_fields ) . "')";
				$sql .= " ON DUPLICATE KEY UPDATE ";

				$dup = array();
				foreach($fields as $field) {
					$dup[] = "`" . $field . "` = VALUES(`" . $field . "`)";
				}

				$sql .= implode(',', $dup);

				return $this->db->query( $this->db->prepare( $sql, $query ) );

		}


	}

}

?>