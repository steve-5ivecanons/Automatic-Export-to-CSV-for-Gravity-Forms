<?php
/*
Plugin Name: Automatic Export to CSV for Gravity Forms
Plugin URI: http://gravitycsv.com
Description: Automatically send an email containing a CSV export of your Gravity Form entries on a schedule.
Version: 0.3.2
Author: Alex Cavender
Author URI: http://alexcavender.com/
Text Domain: automatic_csv_export_for_gravity_forms
Domain Path: /languages
*/

defined( 'ABSPATH' ) or die();

define( 'GF_AUTOMATIC_CSV_VERSION', '0.3.2' );

// require 'api.php';

class GravityFormsAutomaticCSVExport {

	public function __construct() {

		if ( class_exists( 'GFAPI' ) ) {

			add_filter( 'cron_schedules', array($this, 'add_weekly' ) );
			add_filter( 'cron_schedules', array($this, 'add_monthly' ) );
			add_action( 'admin_init', array($this, 'gforms_create_schedules' ) );

			global $wpdb;
			$forms = $wpdb->get_results( "SELECT form_id, display_meta FROM wp_rg_form_meta" );

			foreach ( $forms as $form ) {
				$form_id = $form->form_id;
				$display_meta = $form->display_meta;
				$decode = json_decode( $display_meta );

				if ( $decode ){
					$enabled = $decode->automatic_csv_export_for_gravity_forms->enabled;
					if ( $enabled == 1 ) {
						add_action( 'csv_export_' . $form_id , array( $this, 'gforms_automated_export' ) );
					}
				}
			}

		}

	}


	/**
		* Set up weekly schedule as an interval
		*
		* @since 0.1
		*
		* @param array $schedules.
		* @return array $schedules.
	*/
	public function add_weekly( $schedules ) {
		// add a 'weekly' schedule to the existing set
		$schedules['weekly'] = array(
			'interval' => 604800,
			'display' => __('Once Weekly')
		);
		return $schedules;
	}


	/**
		* Set up monthly schedule as an interval
		*
		* @since 0.1
		*
		* @param array $schedules.
		* @return array $schedules.
	*/
	public function add_monthly( $schedules ) {
		// add a 'weekly' schedule to the existing set
		$schedules['monthly'] = array(
			'interval' => 604800 * 4,
			'display' => __('Once Monthly')
		);
		return $schedules;
	}


	/**
		* Create schedules for each enabled form
		*
		* @since 0.1
		*
		* @param void
		* @return void
	*/
	public function gforms_create_schedules(){

		$forms = GFAPI::get_forms();

		foreach ( $forms as $form ) {

			$form_id = $form['id'];

			$enabled = $form['automatic_csv_export_for_gravity_forms']['enabled'];

			if ( $enabled == 1 ) {

				if ( ! wp_next_scheduled( 'csv_export_' . $form_id ) ) {

					$form = GFAPI::get_form( $form_id );

					$frequency = $form['automatic_csv_export_for_gravity_forms']['csv_export_frequency'];

					wp_schedule_event( time(), $frequency, 'csv_export_' . $form_id );

				}

			}

			else {

				$timestamp = wp_next_scheduled( 'csv_export_' . $form_id );

				wp_unschedule_event( $timestamp, 'csv_export_' . $form_id );

			}

		}

	}


	/**
		* Run Automated Exports
		*
		* @since 0.1
		*
		* @param void
		* @return void
	*/
	public function gforms_automated_export() {

		$output = "";
		$form_id = explode('_', current_filter())[2];
		$form = GFAPI::get_form( $form_id ); // get form by ID
		$search_criteria = array();

		if ( $form['automatic_csv_export_for_gravity_forms']['search_criteria'] == 'all' ) {
			$search_criteria = array();
		}

		if ( $form['automatic_csv_export_for_gravity_forms']['search_criteria'] == 'previous_day' ) {

			$search_criteria['start_date'] = date('Y-m-d', time() - 60 * 60 * 24 );

			$search_criteria['end_date'] = date('Y-m-d', time() - 60 * 60 * 24 );

		}

		if ( $form['automatic_csv_export_for_gravity_forms']['search_criteria'] == 'previous_week' ) {

			$search_criteria['start_date'] = date('Y-m-d', time() - 604800000 );

			$search_criteria['end_date'] = date('Y-m-d', time() - 60 * 60 * 24 );

		}

		if ( $form['automatic_csv_export_for_gravity_forms']['search_criteria'] == 'previous_month' ) {

			$search_criteria['start_date'] = date('Y-m-d', time() - 2678400000 );

			$search_criteria['end_date'] = date('Y-m-d', time() - 60 * 60 * 24 );

		}

		require_once( GFCommon::get_base_path() . '/export.php' );

		$_POST['export_field'] = array();

		foreach( $form['fields'] as $field ){
			$_POST['export_field'][] = $field->id;
		}


		$_POST['export_date_start'] = $search_criteria['start_date'];

		$_POST['export_date_end']   = $search_criteria['end_date'];


		$export = self::start_automated_export( $form, $offset = 0, $form_id . '-' . date('Y-m-d-giA') );

		$upload_dir = wp_upload_dir();

		$baseurl = $upload_dir['baseurl'];

		$path = $upload_dir['path'];

		$server = $_SERVER['HTTP_HOST'];

		$email_address = $form['automatic_csv_export_for_gravity_forms']['email_address'];

		// Send an email using the latest csv file
		$attachments = $path . '/export-' . $form_id . '-' . date('Y-m-d-giA') . '.csv';

		$headers[] = 'From: WordPress <wordpress@' . $server . '>';
		//$headers[] = 'Bcc: bcc@yourdomain.com';
		wp_mail( $email_address , 'Automatic Form Export', 'CSV export is attached to this message', $headers, $attachments);
	}


	public static function start_automated_export( $form, $offset = 0, $export_id = '' ) {

		$time_start         = microtime( true );

		/***
		 * Allows the export max execution time to be changed.
		 *
		 * When the max execution time is reached, the export routine stop briefly and submit another AJAX request to continue exporting entries from the point it stopped.
		 *
		 * @since 2.0.3.10
		 *
		 * @param int   20    The amount of time, in seconds, that each request should run for.  Defaults to 20 seconds.
		 * @param array $form The Form Object
		 */
		$max_execution_time = apply_filters( 'gform_export_max_execution_time', 20, $form ); // seconds
		$page_size          = 20;

		$form_id = $form['id'];
		$fields  = $_POST['export_field'];

		$start_date = empty( $_POST['export_date_start'] ) ? '' : self::get_gmt_date( $_POST['export_date_start'] . ' 00:00:00' );
		$end_date   = empty( $_POST['export_date_end'] ) ? '' : self::get_gmt_date( $_POST['export_date_end'] . ' 23:59:59' );

		$search_criteria['status']        = 'active';
		$search_criteria['field_filters'] = GFCommon::get_field_filters_from_post( $form );
		if ( ! empty( $start_date ) ) {
			$search_criteria['start_date'] = $start_date;
		}

		if ( ! empty( $end_date ) ) {
			$search_criteria['end_date'] = $end_date;
		}

		//$sorting = array( 'key' => 'date_created', 'direction' => 'DESC', 'type' => 'info' );
		$sorting = array( 'key' => 'id', 'direction' => 'DESC', 'type' => 'info' );

		$form = GFExport::add_default_export_fields( $form );

		$total_entry_count     = GFAPI::count_entries( $form_id, $search_criteria );
		$remaining_entry_count = $offset == 0 ? $total_entry_count : $total_entry_count - $offset;

		// Adding BOM marker for UTF-8
		$lines = '';

		// Set the separator
		$separator = gf_apply_filters( array( 'gform_export_separator', $form_id ), ',', $form_id );

		$field_rows = GFExport::get_field_row_count( $form, $fields, $remaining_entry_count );

		if ( $offset == 0 ) {

			//Adding BOM marker for UTF-8
			$lines = chr( 239 ) . chr( 187 ) . chr( 191 );

			//writing header
			$headers = array();
			foreach ( $fields as $field_id ) {
				$field = RGFormsModel::get_field( $form, $field_id );
				$label = gf_apply_filters( array( 'gform_entries_field_header_pre_export', $form_id, $field_id ), GFCommon::get_label( $field, $field_id ), $form, $field );
				$value = str_replace( '"', '""', $label );

				GFCommon::log_debug( "GFExport::start_export(): Header for field ID {$field_id}: {$value}" );

				if ( strpos( $value, '=' ) === 0 ) {
					// Prevent Excel formulas
					$value = "'" . $value;
				}

				$headers[ $field_id ] = $value;

				$subrow_count = isset( $field_rows[ $field_id ] ) ? intval( $field_rows[ $field_id ] ) : 0;
				if ( $subrow_count == 0 ) {
					$lines .= '"' . $value . '"' . $separator;
				} else {
					for ( $i = 1; $i <= $subrow_count; $i ++ ) {
						$lines .= '"' . $value . ' ' . $i . '"' . $separator;
					}
				}

				//GFCommon::log_debug( "GFExport::start_export(): Lines: {$lines}" );
			}
			$lines = substr( $lines, 0, strlen( $lines ) - 1 ) . "\n";

			if ( $remaining_entry_count == 0 ) {
				GFExport::write_file( $lines, $export_id );
			}
		}

		// Paging through results for memory issues
		while ( $remaining_entry_count > 0 ) {

			$paging = array(
				'offset'    => $offset,
				'page_size' => $page_size,
			);
			$leads = GFAPI::get_entries( $form_id, $search_criteria, $sorting, $paging );

			$leads = gf_apply_filters( array( 'gform_leads_before_export', $form_id ), $leads, $form, $paging );

			GFCommon::log_debug( __METHOD__ . '(): search criteria: ' . print_r( $search_criteria, true ) );
			GFCommon::log_debug( __METHOD__ . '(): sorting: ' . print_r( $sorting, true ) );
			GFCommon::log_debug( __METHOD__ . '(): paging: ' . print_r( $paging, true ) );

			foreach ( $leads as $lead ) {
				GFCommon::log_debug( __METHOD__ . '(): Processing entry #' . $lead['id'] );

				foreach ( $fields as $field_id ) {
					switch ( $field_id ) {
						case 'date_created' :
							$lead_gmt_time   = mysql2date( 'G', $lead['date_created'] );
							$lead_local_time = GFCommon::get_local_timestamp( $lead_gmt_time );
							$value           = date_i18n( 'Y-m-d H:i:s', $lead_local_time, true );
							break;
						default :
							$field = RGFormsModel::get_field( $form, $field_id );

							$value = is_object( $field ) ? $field->get_value_export( $lead, $field_id, false, true ) : rgar( $lead, $field_id );
							$value = apply_filters( 'gform_export_field_value', $value, $form_id, $field_id, $lead );

							//GFCommon::log_debug( "GFExport::start_export(): Value for field ID {$field_id}: {$value}" );
							break;
					}

					if ( isset( $field_rows[ $field_id ] ) ) {
						$list = empty( $value ) ? array() : unserialize( $value );

						foreach ( $list as $row ) {
							$row_values = array_values( $row );
							$row_str    = implode( '|', $row_values );

							if ( strpos( $row_str, '=' ) === 0 ) {
								// Prevent Excel formulas
								$row_str = "'" . $row_str;
							}

							$lines .= '"' . str_replace( '"', '""', $row_str ) . '"' . $separator;
						}

						//filling missing subrow columns (if any)
						$missing_count = intval( $field_rows[ $field_id ] ) - count( $list );
						for ( $i = 0; $i < $missing_count; $i ++ ) {
							$lines .= '""' . $separator;
						}
					} else {
						$value = maybe_unserialize( $value );
						if ( is_array( $value ) ) {
							$value = implode( '|', $value );
						}

						if ( strpos( $value, '=' ) === 0 ) {
							// Prevent Excel formulas
							$value = "'" . $value;
						}

						$lines .= '"' . str_replace( '"', '""', $value ) . '"' . $separator;
					}
				}
				$lines = substr( $lines, 0, strlen( $lines ) - 1 );

				//GFCommon::log_debug( "GFExport::start_export(): Lines: {$lines}" );

				$lines .= "\n";
			}

			$offset += $page_size;
			$remaining_entry_count -= $page_size;

			if ( ! seems_utf8( $lines ) ) {
				$lines = utf8_encode( $lines );
			}

			$lines = apply_filters( 'gform_export_lines', $lines );

			GFExport::write_file( $lines, $export_id );

			/*
			BEGIN mods by Alex C
			*/

			$upload_dir = wp_upload_dir();

			$baseurl = $upload_dir['baseurl'];

			$path = $upload_dir['path'];

			$myfile = fopen( $path . "/export-" . $form_id . '-' . date('Y-m-d-giA') . ".csv", "w") or die("Unable to open file!");

			fwrite($myfile, $lines);
			fclose($myfile);

			/*
			END mods by Alex C
			*/



			$time_end       = microtime( true );
			$execution_time = ( $time_end - $time_start );

			if ( $execution_time >= $max_execution_time ) {
				break;
			}

			$lines = '';
		}

		$complete = $remaining_entry_count <= 0;

		if ( $complete ) {
			/**
			 * Fires after exporting all the entries in form
			 *
			 * @param array  $form       The Form object to get the entries from
			 * @param string $start_date The start date for when the export of entries should take place
			 * @param string $end_date   The end date for when the export of entries should stop
			 * @param array  $fields     The specified fields where the entries should be exported from
			 */
			do_action( 'gform_post_export_entries', $form, $start_date, $end_date, $fields );
		}

		$offset = $complete ? 0 : $offset;

		$status = array(
			'status'   => $complete ? 'complete' : 'in_progress',
			'offset'   => $offset,
			'exportId' => $export_id,
			'progress' => $remaining_entry_count > 0 ? intval( 100 - ( $remaining_entry_count / $total_entry_count ) * 100 ) . '%' : '',
		);

		GFCommon::log_debug( __METHOD__ . '(): Status: ' . print_r( $status, 1 ) );

		return $status;
	}

}
$automatedexportclass = new GravityFormsAutomaticCSVExport();


add_action( 'gform_loaded', array( 'GF_Automatic_Csv_Bootstrap', 'load' ), 5 );

class GF_Automatic_Csv_Bootstrap {

    public static function load() {

        if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }

         require_once( 'class-gf-automatic-csv-addon.php' );

        GFAddOn::register( 'GFAutomaticCSVAddOn' );
    }

}

function gf_simple_addon() {
    return GFAutomaticCSVAddOn::get_instance();
}
