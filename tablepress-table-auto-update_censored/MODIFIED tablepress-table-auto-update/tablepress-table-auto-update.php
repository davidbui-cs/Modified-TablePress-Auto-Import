<?php
/*
Plugin Name: UCI MIND Modified TablePress Extension: Table Auto Update
Plugin URI: https://tablepress.org/extensions/table-auto-import/
Description: Extension for TablePress to allow periodic automatic import of tables
Version: 1.2
Author: Tobias Bäthge
Author URI: https://tobias.baethge.com/
Modified by (12/18/2019): David Bui
Modifier Email: davidqb@uci.edu
*/

/**
 * PHP class that wraps the Table Auto Update functionality
 */
class TablePress_Table_Auto_Update {

	/**
	 * Instance of the Table Model.
	 *
	 * @var Model_Table
	 * @since 1.0.0
	 */
	protected static $model_table;

	/**
	 * Instance of the Importer Class.
	 *
	 * @var TablePress_Import
	 * @since 1.0.0
	 */
	protected static $importer;

	/**
	 * Constructor function, called when plugin is loaded.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Register hook to add and remove cron hooks, when the plugin is deactivated.
		register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivation_hook' ) );

		// Register the cron hooks to have the update process run every 15 minutes.
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_add_quarterhourly' ) );
		add_action( 'tablepress_table_auto_import_hook', array( __CLASS__, 'auto_import_tables' ) );

		// Load the Auto Import View.
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			add_action( 'tablepress_run', array( $this, 'run' ) );
		}
	}

	/**
	 * Clear/Unschedule the cron hook on plugin deactivation, and delete options.
	 *
	 * @since 1.0.0
	 */
	public static function deactivation_hook() {
		wp_clear_scheduled_hook( 'tablepress_table_auto_import_hook' );
		delete_option( 'tablepress_auto_import_config' );
	}

	/**
	 * Add "quarterhourly" as a new possible interval for cron hooks, as WP doesn't have it by default.
	 *
	 * @since 1.0.0
	 *
	 * @param array $schedules Current WP Cron schedules.
	 * @return array Extended WP Cron schedules.
	 */
	public static function cron_add_quarterhourly( $schedules ) {
		// Adds once every 15 minutes to the existing schedules.
		$schedules['quarterhourly'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display' => 'Once every 15 minutes',
		);
		return $schedules;
	}

	/**
	 * Every 15 minutes: Loop through the list of tables, import them from their given source,
	 * and replace the existing data with the new data.
	 *
	 * @since 1.0.0
	 */
	public static function auto_import_tables() {
		// Load the tables that are to be auto imported.
		$params = array(
			'option_name' => 'tablepress_auto_import_config',
			'default_value' => array(),
		);
		$auto_import_config = TablePress::load_class( 'TablePress_WP_Option', 'class-wp_option.php', 'classes', $params );
		$tables = $auto_import_config->get();

		// Nothing to do if there are no tables to be imported automatically.
		if ( empty( $tables ) ) {
			return;
		}

		self::$model_table = TablePress::load_model( 'table' );
		self::$importer = TablePress::load_class( 'TablePress_Import', 'class-import.php', 'classes' );

		// For each table that shall be updated, and that exists, run the update function.
		foreach ( $tables as $table_id => $table_config ) {
			// #schedule entry is not a table.
			if ( '#schedule' === $table_id ) {
				continue;
			}

			if ( true !== $table_config['auto_import'] ) {
				continue;
			}
			if ( ! self::$model_table->table_exists( $table_id ) ) {
				continue;
			}

			$result = self::_import_table( $table_id, $table_config['source_type'], $table_config['source_format'], $table_config['source'] );
			$tables[ $table_id ]['last_auto_import'] = ( ( false !== $result ) ? 'Success' : '<strong>Failed</strong>' ). ' @ ' . current_time( 'mysql' );
		}
		$auto_import_config->update( $tables );
	}

	/**
	 * Update a single table from the given source.
	 *
	 * @since 1.0.0
	 *
	 * @param string $table_id      Table ID.
	 * @param string $source_type   Type of the source ('url' or 'server').
	 * @param string $source_format Import format (CSV/HTML/JSON/XLS/XLSX).
	 * @param string $source        Source for the import (a URL or a location on the server).
	 * @return bool|string False on error, table ID on success.
	 */
	protected static function _import_table( $table_id, $source_type, $source_format, $source ) {
		if ( empty( $source ) ) {
			return false;
		}

		switch ( $source_type ) {
			case 'url':
				if ( 'http://' === $source ) {
					return false;
				}
				$import_data = wp_remote_fopen( $source );
				break;
			case 'server':
				if ( ABSPATH === $source ) {
					return false;
				}
				if ( ! is_readable( $source ) ) {
					return false;
				}
				$import_data = file_get_contents( $source );
				break;
                
            //modified here
            case 'API':
                $import_data = self::_request_API($source);
                break;
			default:
				return false;
		}

		if ( empty( $import_data ) ) {
			return false;
		}

		return self::_import_tablepress_table( $table_id, $import_data, $source_format );
	}
    //Modified Here
    protected static function _request_API($source){
        $data = array(
            'token' => $source,
            'content' => 'report',
            'format' => 'csv',
            'report_id' => '', //insert report id
            'rawOrLabel' => 'raw',
            'rawOrLabelHeaders' => 'raw',
            'exportCheckboxLabel' => 'false',
            'returnFormat' => 'csv'
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'URL'); //insert URL 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

	/**
	 * Import a table by replacing an existing table.
	 *
	 * @since 1.0.0
	 *
	 * @param bool|string $replace_id ID of the table to be replaced.
	 * @param array       $data       Data to import.
	 * @param string      $format     Import format.
	 * @return bool|string False on error, table ID on success.
	 */
	protected static function _import_tablepress_table( $replace_id, $data, $format ) {
		$imported_table = self::$importer->import_table( $format, $data );
		if ( false === $imported_table ) {
			return false;
		}

		// Load existing table from DB.
		$table = self::$model_table->load( $replace_id, false, true ); // Don't load data, but options and visibility.
		if ( is_wp_error( $table ) ) {
			return false;
		}
		// Don't change name and description when a table is replaced.
		$imported_table['name'] = $table['name'];
		$imported_table['description'] = $table['description'];

		// If the imported table has visibility settings (e.g. full JSON format) use those.
		if ( isset( $imported_table['visibility'] ) && isset( $imported_table['visibility']['rows'] ) && isset( $imported_table['visibility']['columns'] ) ) {
			$table['visibility']['rows'] = $imported_table['visibility']['rows'];
			$table['visibility']['columns'] = $imported_table['visibility']['columns'];
		}

		// Merge new or existing table with information from the imported table.
		$imported_table['id'] = $table['id']; // Existing table ID.
		// Cut visibility array (if the imported table is smaller), and pad correctly if imported table is bigger than existing table.
		$num_rows = count( $imported_table['data'] );
		$num_columns = count( $imported_table['data'][0] );
		$imported_table['visibility'] = array(
			'rows' => array_pad( array_slice( $table['visibility']['rows'], 0, $num_rows ), $num_rows, 1 ),
			'columns' => array_pad( array_slice( $table['visibility']['columns'], 0, $num_columns ), $num_columns, 1 ),
		);

		// Check if new data is ok.
		$table = self::$model_table->prepare_table( $table, $imported_table, false );
		if ( is_wp_error( $table ) ) {
			return false;
		}

		// Replace existing table with imported table.
		$table_id = self::$model_table->save( $table );

		return $table_id;
	}

	/**
	 * Start-up the TablePress Auto Import Controller, which is run when TablePress is run.
	 *
	 * @since 1.0.0
	 */
	public function run() {
		add_filter( 'tablepress_load_file_full_path', array( $this, 'change_import_view_full_path' ), 10, 3 );
		add_filter( 'tablepress_load_class_name', array( $this, 'change_view_import_class_name' ) );
		 // Do this before intended TablePress method is called, to be able to remove the action.
		add_action( 'admin_post_tablepress_import', array( $this, 'handle_post_action_auto_import' ), 9 );
	}

	/**
	 * Change View Import file path, to load extended view.
	 *
	 * @since 1.0.0
	 *
	 * @param string $full_path Full path of the file that shall be loaded.
	 * @param string $file      File name of the file that shall be loaded.
	 * @param string $folder    Folder name of the file that shall be loaded.
	 * @return string Changed full path.
	 */
	public function change_import_view_full_path( $full_path, $file, $folder ) {
		if ( 'view-import.php' === $file ) {
			require_once $full_path; // Load desired file first, as we derive from it in the new $full_path file.
			$full_path = plugin_dir_path( __FILE__ ) . 'view-auto-import.php';
		}
		return $full_path;
	}

	/**
	 * Change View Import class name, to load extended view.
	 *
	 * @since 1.0.0
	 *
	 * @param string $class Name of the class that shall be loaded.
	 * @return string Changed class name.
	 */
	public function change_view_import_class_name( $class ) {
		if ( 'TablePress_Import_View' === $class ) {
			$class = 'TablePress_Auto_Import_View';
		}
		return $class;
	}

	/**
	 * Save Auto Import Configuration.
	 *
	 * @since 1.0.0
	 */
	public function handle_post_action_auto_import() {
		if ( ! isset( $_POST['submit_auto_import_config'] ) ) {
			return;
		}

		// Remove TablePress Import action handling.
		remove_action( 'admin_post_tablepress_import', array( TablePress::$controller, 'handle_post_action_import' ) );

		TablePress::check_nonce( 'import' );

		if ( ! current_user_can( 'tablepress_import_tables' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		if ( empty( $_POST['auto_import'] ) || ! is_array( $_POST['auto_import'] ) ) {
			TablePress::redirect( array( 'action' => 'import', 'message' => 'error_auto_import' ) );
		} else {
			$auto_import = stripslashes_deep( $_POST['auto_import'] );
		}

		$params = array(
			'option_name' => 'tablepress_auto_import_config',
			'default_value' => array(),
		);
		$auto_import_config = TablePress::load_class( 'TablePress_WP_Option', 'class-wp_option.php', 'classes', $params );

		$schedule = isset( $_POST['auto_import_schedule'] ) ? $_POST['auto_import_schedule'] : 'daily';
		$config = array( '#schedule' => $schedule ); // '#' makes sure that this is not overwritten by a table ID, as these can not contain '#'.
		foreach ( $auto_import as $table_id => $table ) {
			$table['auto_import'] = ( isset( $table['auto_import'] ) && 'true' === $table['auto_import'] ) ? true : false;
			$table['last_auto_import'] = '-';
			if ( ! isset( $table['source'] ) ) {
				$table['source'] = 'http://';
			}
			if ( ! isset( $table['source_type'] ) ) {
				$table['source_type'] = 'url';
			}
			if ( ! isset( $table['source_format'] ) ) {
				$table['source_format'] = 'csv';
			}
			// Only save things for tables that have changes and not just the default settings.
			if ( $table['auto_import'] || 'http://' !== $table['source'] || 'url' !== $table['source_type'] || 'csv' !== $table['source_format'] ) {
				$config[ (string) $table['id'] ] = $table;
			}
		}
		$result = $auto_import_config->update( $config );

		wp_clear_scheduled_hook( 'tablepress_table_auto_import_hook' );
		if ( ! wp_next_scheduled( 'tablepress_table_auto_import_hook' ) ) {
			wp_schedule_event( time(), $schedule, 'tablepress_table_auto_import_hook' );
		}

		TablePress::redirect( array( 'action' => 'import', 'message' => 'success_auto_import' ) );
	}

} // end TablePress_Table_Auto_Update

// Bootstrap, instantiates the plugin.
new TablePress_Table_Auto_Update;
