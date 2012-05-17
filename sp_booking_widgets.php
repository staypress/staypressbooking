<?php
/*
Plugin Name: StayPress Booking Plugin Widgets
Version: 1.2
Plugin URI: http://staypress.com
Description: StayPress Booking Widgets
Author:
Author URI: http://staypress.com

Copyright 2010  (email: barry@mapinated.com)

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
require_once('includes/config.php');
require_once('classes/common.php');
// Set up my location
SPBCommon::set_booking_url(__FILE__);
SPBCommon::set_booking_dir(__FILE__);

class sp_availabiltycalendar extends WP_Widget {

	function sp_availabiltycalendar() {

		// Load the text-domain
		$locale = apply_filters( 'staypress_locale', get_locale() );
		$mofile = SPBCommon::booking_dir( "includes/lang/booking-$locale.mo" );

		if ( file_exists( $mofile ) )
			load_plugin_textdomain( 'booking', $mofile );

		$widget_ops = array( 'classname' => 'sp_availabiltycalendar', 'description' => __('Availability calendar widget', 'booking') );
		$control_ops = array('width' => 250, 'height' => 350, 'id_base' => 'sp_availabiltycalendar');
		$this->WP_Widget( 'sp_availabiltycalendar', __('Availability Calendar', 'booking'), $widget_ops, $control_ops );

		add_action('init', array(&$this, 'enqueue_page_headings'));
	}

	function enqueue_page_headings() {

		// Only need these on the public side of the site
		if(is_admin()) {
			return;
		}
		// Check if we are actually being used anywhere
		if(is_active_widget(false, false, 'sp_availabiltycalendar')) {
			// we are an active widget so add in our styles and js
			wp_enqueue_style('bookingwidgetcss', SPBCommon::booking_url('css/booking.calendarwidget.css'), array());
		}

	}

	function widget( $args, $instance ) {

		extract( $args );

		// build the check array
		$defaults = array(
			'title' 		=> __('Availability', 'booking'),
			'parsetype' 	=> 'url',
			'property_id'	=> false,
			'months'		=> 12,
			'bookedclass'	=> 'booked',
			'weekstarts'	=> 'sunday',
			'excludestatus'	=> 'draft'
		);

		foreach($defaults as $key => $value) {
			if(isset($instance[$key])) {
				$defaults[$key] = $instance[$key];
			}
		}

		//sp_booking_show_calendar($startyear, $startmonth, $property_id, $excludestatus = array('draft'), $holdingdiv = true)

		echo $before_widget;
		$title = apply_filters('widget_title', $instance['title'] );

		if ( $title ) {
			echo $before_title . $title . $after_title;
		}
		// Show the calendar li list.
		// Start this month
		$start = date("Y-m-01");

		// get the exclude list
		$excludestatus = explode(",", $instance['excludestatus']);
		foreach( (array) $excludestatus as $key => $value) {
			$excludestatus[$key] = trim($value);
		}

		// get the property id
		switch( $instance['parsetype'] ) {
			case 'define':	if(defined('STAYPRESS_ON_PROPERTY_PAGE')) $property_id = (int) STAYPRESS_ON_PROPERTY_PAGE;
							break;
			case 'below':	$property_id = (int) $instance['property_id'];
							break;

			case 'url':		$uri = $_SERVER['REQUEST_URI'];
							$number = (int) $instance['property_id'];
							//
							$urisplit = explode("/",$uri);
							if( is_numeric($number) && count($urisplit) > (int) $number ) {
								$property_id = $urisplit[$number];
							} else {
								$property_id = false;
							}
							break;

			case 'querystring':
							$property_id = (int) $_GET[$instance['property_id']];
							break;
		}

		for($n = 0; $n < (int) $instance['months']; $n++) {
			$showing = strtotime('+' . $n . ' months', strtotime($start));
			if(function_exists('sp_booking_show_calendar')) {
				sp_booking_show_calendar(date("Y", $showing), date("n", $showing), $property_id, $excludestatus, $instance['bookedclass'], true);
			}
		}

		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$defaults = array(
			'title' 		=> __('Availability', 'booking'),
			'parsetype' 	=> 'url',
			'property_id'	=> false,
			'months'		=> 12,
			'bookedclass'	=> 'booked',
			'weekstarts'	=> 'sunday',
			'excludestatus'	=> 'draft'
		);

		foreach ( $defaults as $key => $val ) {
			$instance[$key] = $new_instance[$key];
		}

		return $instance;
	}

	function form( $instance ) {

		$defaults = array(
			'title' 		=> __('Availability', 'booking'),
			'parsetype' 	=> 'url',
			'property_id'	=> false,
			'months'		=> 12,
			'bookedclass'	=> 'booked',
			'weekstarts'	=> 'sunday',
			'excludestatus'	=> 'draft'
		);

		$instance = wp_parse_args( (array) $instance, $defaults );

		?>
			<p>
				<?php _e('Set your calendar display options with the settings below','booking'); ?>
			</p>
			<p>
				<?php _e('Title:','booking'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'title' ); ?>' id='<?php echo $this->get_field_id( 'title' ); ?>' value='<?php echo esc_attr(stripslashes($instance['title'])); ?>' />
			</p>
			<p>
				<?php _e('Number of months:','booking'); ?><br/>
				<select name='<?php echo $this->get_field_name( 'months' ); ?>' id='<?php echo $this->get_field_id( 'months' ); ?>'>
					<?php
					for($n = 1; $n <= 24; $n++) {
						?>
						<option value='<?php echo $n; ?>' <?php if($instance['months'] == $n) echo "selected='selected'"; ?>><?php echo $n; ?></option>
						<?php
					}
					?>
				</select>
			</p>
			<p>
				<?php _e('Get property details from:','booking'); ?><br/>
				<select name='<?php echo $this->get_field_name( 'parsetype' ); ?>' id='<?php echo $this->get_field_id( 'parsetype' ); ?>'>
					<option value='define' <?php if($instance['parsetype'] == 'define') echo "selected='selected'"; ?>><?php echo __('Automatic','booking'); ?></option>
					<option value='below' <?php if($instance['parsetype'] == 'below') echo "selected='selected'"; ?>><?php echo __('Setting below','booking'); ?></option>
					<option value='url' <?php if($instance['parsetype'] == 'url') echo "selected='selected'"; ?>><?php echo __('Parse URL','booking'); ?></option>
					<option value='querystring' <?php if($instance['parsetype'] == 'querystring') echo "selected='selected'"; ?>><?php echo __('Querystring','booking'); ?></option>
				</select>
			</p>
			<p>
				<?php _e('Property ID:','booking'); ?><br/><small><?php _e('or url segment / querystring','booking'); ?></small><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'property_id' ); ?>' id='<?php echo $this->get_field_id( 'property_id' ); ?>' value='<?php echo esc_attr(stripslashes($instance['property_id'])); ?>' />
			</p>
			<!--
			<p>
				<?php _e('Week starts:','booking'); ?><br/>
				<select name='<?php echo $this->get_field_name( 'weekstarts' ); ?>' id='<?php echo $this->get_field_id( 'weekstarts' ); ?>'>
					<option value='sunday' <?php if($instance['weekstarts'] == 'sunday') echo "selected='selected'"; ?>><?php echo __('Sunday','booking'); ?></option>
					<option value='monday' <?php if($instance['weekstarts'] == 'monday') echo "selected='selected'"; ?>><?php echo __('Monday','booking'); ?></option>
				</select>
			</p>
			-->
			<p>
				<?php _e('Exclude booking status:','booking'); ?><br/><small><?php _e('comma separated','booking'); ?></small><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'excludestatus' ); ?>' id='<?php echo $this->get_field_id( 'excludestatus' ); ?>' value='<?php echo esc_attr(stripslashes($instance['excludestatus'])); ?>' />
			</p>
			<p>
				<?php _e('Booked class:','booking'); ?><br/>
				<input type='text' class='widefat' name='<?php echo $this->get_field_name( 'bookedclass' ); ?>' id='<?php echo $this->get_field_id( 'bookedclass' ); ?>' value='<?php echo esc_attr(stripslashes($instance['bookedclass'])); ?>' />
			</p>
			<p>&nbsp;</p>
	<?php
	}
}

function sp_bookingwidgets_register() {
	register_widget( 'sp_availabiltycalendar' );
}

add_action( 'widgets_init', 'sp_bookingwidgets_register' );

?>