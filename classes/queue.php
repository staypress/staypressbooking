<?php
if(!class_exists('SPQueue')) {

	class SPQueue {

		private static $tables = array('queue');

		private static $queue;
		private static $staypress_prefix = 'sp_';

		private static function set_prefix() {

			global $wpdb;

			if(!empty(self::$queue)) {
				return;
			}

			foreach(self::$tables as $table) {
				self::$queue = $wpdb->prefix . self::$staypress_prefix . $table;
			}

		}

		public static function queue_operation( $identifier, $action, $area ) {

			global $blog_id;

			self::set_prefix();

			$data = array(	'blog_id' 			=> 	$blog_id,
							'object_id'			=>	$identifier,
							'object_area'		=>	$area,
							'object_operation'	=>	$action,
							'object_timestamp'	=>	time()
						);

			self::insertonduplicate( self::$queue, $data );

		}

		function insertonduplicate($table, $data) {

			global $wpdb;

			$fields = array_keys($data);
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

			return $wpdb->query( $wpdb->prepare( $sql, $data) );
		}


	}

}

?>