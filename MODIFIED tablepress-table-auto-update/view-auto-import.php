<?php
/**
 * Auto Import Table View
 *
 * @package TablePress
 * @subpackage Auto Import Table View
 * @author Tobias Bäthge
 * @since 1.0.0
 */
//Modified by (12/19/2019): David Bui
//Modifier Email: Davidqb@uci.edu

// Prohibit direct script loading.
defined( 'ABSPATH' ) || die( 'No direct script access allowed!' );

/**
 * Auto Import Table View class
 *
 * @since 1.0.0
 */
class TablePress_Auto_Import_View extends TablePress_Import_View {

	protected $auto_import_config;

	/**
	 * Set up the view with data and do things that are specific for this view.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action Action for this view.
	 * @param array $data Data for this view.
	 */
	public function setup( $action, array $data ) {
		$params = array(
			'option_name' => 'tablepress_auto_import_config',
			'default_value' => array(),
		);
		$this->auto_import_config = TablePress::load_class( 'TablePress_WP_Option', 'class-wp_option.php', 'classes', $params );

		parent::setup( $action, $data );

		$this->add_meta_box( 'tables-auto-import', 'Auto Import Tables', array( $this, 'postbox_auto_import' ), 'additional' );

		$this->process_action_messages( array(
			'error_auto_import' => 'Error: The Auto Import configuration could not be saved.',
			'success_auto_import' => 'The Auto Import configuration was saved successfully.'
		) );
	}

	/**
	 * Print the form for the Auto Update tables list.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Data for this screen.
	 * @param array $box  Information about the text box.
	 */
    //Modified by David Bui
	public function postbox_auto_import( $data, $box ) {
		$schedule = $this->auto_import_config->get( '#schedule', 'daily' );
?>
		Perform Auto Update: <select name="auto_import_schedule">
			<option value="quarterhourly"<?php selected( $schedule, 'quarterhourly' ); ?>>Every 15 minutes</option>
			<option value="hourly"<?php selected( $schedule, 'hourly' ); ?>>Once Hourly</option>
			<option value="twicedaily"<?php selected( $schedule, 'twicedaily' ); ?>>Twice Daily</option>
			<option value="daily"<?php selected( $schedule, 'daily' ); ?>>Once Daily</option>
		</select><br /><br />
<?php
		echo '<input type="submit" value="Save Auto Import Configuration" class="button button-large submit_auto_import_config" name="submit_auto_import_config" /><br /><br />';
		echo '<table class="widefat" cellspacing="0">' . "\n";
		echo '<thead><th>ID</th><th>Name</th><th>Auto Import</th><th>Format</th><th>Source Type</th><th style="min-width:300px">Source</th><th>Last Auto Import</th></thead>' . "\n";
		echo '<tfoot><th>ID</th><th>Name</th><th>Auto Import</th><th>Format</th><th>Source Type</th><th>Source</th><th>Last Auto Import</th></tfoot>' . "\n";
		echo "<tbody>\n";
		$alternate = true;
		foreach ( $data['table_ids'] as $table_id ) {
			$table = TablePress::$model_table->load( $table_id, false, false ); // Load table, without table data, options, and visibility settings
			$auto_import_table = $this->auto_import_config->get( $table['id'], array( 'id' => $table['id'], 'auto_import' => false, 'source' => 'http://', 'source_type' => 'url', 'source_format' => 'csv', 'last_auto_import' => '-' ) );
			$alternate_text = ( $alternate ) ?  ' class="alternate"' : ''; $alternate = ! $alternate;
			echo "\t<tr{$alternate_text}>
			<td>{$table['id']}<input type=\"hidden\" name=\"auto_import[{$table['id']}][id]\" value=\"{$auto_import_table['id']}\" /></td>
			<td><strong>{$table['name']}</strong></td>
			<td><label for=\"cb_auto_import_{$table['id']}\"><input type=\"checkbox\" id=\"cb_auto_import_{$table['id']}\" class=\"cb_auto_import\" name=\"auto_import[{$table['id']}][auto_import]\" value=\"true\"" . checked( $auto_import_table['auto_import'], true, false ) . " /> Active</label></td>
			<td><select name=\"auto_import[{$table['id']}][source_format]\">
				<option value=\"csv\"" . selected( $auto_import_table['source_format'], 'csv', false ) . ">CSV</option>
				<option value=\"html\"" . selected( $auto_import_table['source_format'], 'html', false ) . ">HTML</option>
				<option value=\"json\"" . selected( $auto_import_table['source_format'], 'json', false ) . ">JSON</option>
				<option value=\"xls\"" . selected( $auto_import_table['source_format'], 'xls', false ) . ">XLS</option>
				<option value=\"xlsx\"" . selected( $auto_import_table['source_format'], 'xlsx', false ) . ">XLSX</option>
			</select></td>
			<td><select class=\"source_type\" name=\"auto_import[{$table['id']}][source_type]\"><option value=\"API\"" . selected( $auto_import_table['source_type'], 'API', false ) . ">API</option><option value=\"url\"" . selected( $auto_import_table['source_type'], 'url', false ) . ">URL</option><option value=\"server\"" . selected( $auto_import_table['source_type'], 'server', false ) . ">File on server</option></select></td>
			<td><input type=\"text\" class=\"input_auto_import_source large-text\" name=\"auto_import[{$table['id']}][source]\" value=\"{$auto_import_table['source']}\" /></td>
			<td>{$auto_import_table['last_auto_import']}</td>
			</tr>\n";
		}
		echo "</tbody></table><br />\n";
		echo '<input type="submit" value="Save Auto Import Configuration" class="button button-large submit_auto_import_config" name="submit_auto_import_config" />';

		add_action( 'admin_footer', array( $this, '_add_footer_js' ) );
	}

	/**
	 * JS to turn off form validation for the main form.
	 *
	 * @since 1.0.0
	 */
    //Modified by David Bui
	public function _add_footer_js() {
		$abspath = ABSPATH;
		echo <<<JSSCRIPT
<script type="text/javascript">
jQuery(document).ready( function($) {
	$( '#tablepress-page' ).find( '.submit_auto_import_config' ).on( 'click', function() {
		$( '#tablepress-page' ).find( 'form' ).off( 'submit.tablepress' );
		$( '#tablepress_import-tables-auto-import' ).find( 'select, .input_auto_import_source' ).prop( 'disabled', false );
	} );
	$( '#tablepress_import-tables-auto-import' ).on( 'change', '.cb_auto_import', function() {
		$( this ).parents( 'tr' ).find( 'select, .input_auto_import_source' ).prop( 'disabled', ! $(this).prop( 'checked' ) );
	} )
	.find( '.cb_auto_import' ).change();
	$( '#tablepress_import-tables-auto-import' ).on( 'change', '.source_type', function() {
		var source_field = $( this ).parents( 'tr' ).find( '.input_auto_import_source' ),
			source = source_field.val(),
			type = $(this).val(),
			abspath = '{$abspath}',
			new_source = '';
		if ( 'url' === type && abspath === source ) {
			new_source = 'http://';
		} else if ( 'server' === type && 'http://' === source ) {
			new_source = abspath;
		}else if ('API' == type){
            new_source = 'Token';
        }
		source_field.val( new_source );
	} );
});
</script>
JSSCRIPT;
	}

} // class TablePress_Auto_Import_View
