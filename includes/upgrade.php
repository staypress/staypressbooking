<?php

function sp_upgradebooking($from = false) {

	switch($from) {
		case false:	sp_createbookingtables();
					break;

		default:	sp_createbookingtables();
					break;
	}

}

function sp_createbookingtables() {

	global $wpdb;

	if( !empty($wpdb->base_prefix) && defined('STAYPRESS_GLOBAL_TABLES') && STAYPRESS_GLOBAL_TABLES == true ) {
		$prefix = $wpdb->base_prefix;
	} else {
		$prefix = $wpdb->prefix;
	}

	$sql = "CREATE TABLE IF NOT EXISTS `{$prefix}sp_booking` (
	  `id` bigint(20) NOT NULL auto_increment,
	  `user_id` bigint(20) default NULL,
	  `blog_id` bigint(20) default '0',
	  `property_id` bigint(20) default '0',
	  `contact_id` bigint(20) default '0',
	  `startdate` datetime default '0000-00-00 00:00:00',
	  `enddate` datetime default '0000-00-00 00:00:00',
	  `starttime` time default NULL,
	  `endtime` time default NULL,
	  `title` varchar(250) default NULL,
	  `notes` text,
	  `status` varchar(20) default NULL,
	  PRIMARY KEY  (`id`),
	  KEY `blog_id` (`blog_id`),
	  KEY `property_id` (`property_id`),
	  KEY `contact_id` (`contact_id`),
	  KEY `startdate` (`startdate`),
	  KEY `enddate` (`enddate`),
	  KEY `user_id` (`user_id`)
	);";
	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `{$prefix}sp_booking_meta` (
	  `booking_id` bigint(20) NOT NULL default '0',
	  `meta_key` varchar(250) NOT NULL default '',
	  `meta_value` text,
	  `blog_id` bigint(20) NOT NULL default '0',
	  PRIMARY KEY  (`booking_id`,`blog_id`,`meta_key`)
	);";
	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `{$prefix}sp_booking_note` (
	  `id` bigint(20) NOT NULL auto_increment,
	  `booking_id` bigint(20) default NULL,
	  `user_id` bigint(20) default NULL,
	  `note_type` varchar(50) default NULL,
	  `note` text,
	  `updated_date` timestamp NULL default NULL on update CURRENT_TIMESTAMP,
	  `created_date` timestamp NULL default NULL,
	  `reminderrequested` int(11) default '0',
	  `reminder_timestamp` timestamp NULL default NULL,
	  `note_meta` text,
	  PRIMARY KEY  (`id`),
	  KEY `booking_id` (`booking_id`),
	  KEY `user_id` (`user_id`),
	  KEY `booking_id_2` (`booking_id`,`note_type`)
	);";
	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `{$prefix}sp_queue` (
	  `blog_id` bigint(20) NOT NULL default '0',
	  `object_id` bigint(20) NOT NULL default '0',
	  `object_area` varchar(100) NOT NULL default '',
	  `object_operation` varchar(20) NOT NULL default '',
	  `object_timestamp` int(11) default NULL,
	  PRIMARY KEY  (`object_id`,`object_area`,`object_operation`,`blog_id`),
	  KEY `object_timestamp` (`object_timestamp`)
	);";
	$wpdb->query($sql);

}

?>