<?php
/*
Plugin Name: StayPress Booking plugin
Version: 1.3
Plugin URI: http://staypress.com/
Description: The StayPress Booking management plugin
Author: StayPress team
Author URI: http://staypress.org/

Copyright 2012  (email: support@staypress.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

//define('SP_ONMS', true);

require_once('includes/config.php');
require_once('classes/common.php');
// Set up my location
SPBCommon::set_booking_url(__FILE__);
SPBCommon::set_booking_dir(__FILE__);

if(is_admin()) {
	require_once('classes/model.php');
	require_once('classes/queue.php');
	require_once('classes/administration.php');
	// Adminstration interface
	$sp_bookingadmin = new sp_bookingadmin();
} else {
	require_once('classes/model.php');
	require_once('classes/public.php');
	// Public interface
	$sp_booking = new sp_booking();
}

// Load secondary plugins
SPBCommon::load_booking_addons();

?>