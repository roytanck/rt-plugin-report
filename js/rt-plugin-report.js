jQuery(document).ready( function( $ ){

	var rtpr_slugs = rt_plugin_report_vars.plugin_slugs;
	var rtpr_slugs_array = rtpr_slugs.split(',');
	var rtpr_nrof_plugins = rtpr_slugs_array.length;
	var rtpr_progress = 0;

	function rtpr_process_next_plugin(){
		if( rtpr_slugs_array.length > 0 ){
			var slug = rtpr_slugs_array.shift();
			if( $( '.rt-plugin-report-row-temp-' + slug ).length ){
				rtpr_get_plugin_info( slug );
			} else {
				rtpr_process_next_plugin();
			}
		}
		// update the progress information on the page
		var perc = Math.ceil( ( rtpr_progress / rtpr_nrof_plugins ) * 100 );
		if( perc < 100 ){
			$('#rt-plugin-report-progress').html( '<div class="rt-plugin-report-progress-outer"><div class="rt-plugin-report-progress-inner" style="width:' + perc + '%;"></div></div>' );
			rtpr_progress++;	
		} else {
			$('#rt-plugin-report-progress').html( '' );
		}
		
	}

	function rtpr_get_plugin_info( slug ){
		var data = {
			'action': 'rt_get_plugin_info',
			'slug': slug,
			'nonce': rt_plugin_report_vars.ajax_nonce
		};

		jQuery.post( ajaxurl, data, function(response) {
			// parse the response
			obj = jQuery.parseJSON(response);
			// replace the temporary table row with the new data
			$('#rt-plugin-report-table .rt-plugin-report-row-temp-' + slug ).replaceWith( obj.html );
			// on to the next...
			rtpr_process_next_plugin();
		});
	}

	// kick things off
	rtpr_process_next_plugin();


	// Create the export button.
	$('#rt-plugin-report-buttons').append('<button class="button" href="#" id="rt-plugin-report-export-btn">' + rt_plugin_report_vars.export_btn + '</button>');

	// Export button event handler.
	$('#rt-plugin-report-export-btn').click( function( e ){

		// Create a backup of the table's html markup.
		var table_backup = $('#rt-plugin-report-table').html();

		// Attempt to put local styles in elements with known CSS classes.
		$('td.rt-risk-low').attr( 'style', 'background-color: #dfd; color: #090 !important; font-weight: bold;' );
		$('td.rt-risk-high').attr( 'style', 'background-color: #fdd; color: #c00 !important; font-weight: bold;' );
		$('td.rt-risk-med').attr( 'style', 'font-weight: bold;' );

		// Call the function that does the exporting.
		rtpr_export_table();

		// Restore the backup.
		$('#rt-plugin-report-table').html( table_backup );
	});


	// Export function based on https://stackoverflow.com/a/27843359 .
	function rtpr_export_table(){
		var table_html = $('#rt-plugin-report-table').html();
		var uri = 'data:application/vnd.ms-excel;charset=UTF-8;base64,';
		var template = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>{worksheet}</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body><table>{table}</table></body></html>';
		var format = function(s, c) {
			return s.replace(/{(\w+)}/g, function(m, p) {
				return c[p];
			})
		};
		var ctx = {
			worksheet: 'Worksheet',
			table: table_html
		}
		var link = document.createElement( 'a' );
		var now = new Date();
		link.download = 'plugin-report-' + now.getFullYear() + '-' + String( '0' + now.getMonth() ).slice(-2) + '-' + String( '0' + now.getDate() ).slice(-2) + '.xls';
		link.href = uri + btoa( format( template, ctx ) );
		link.click();
		link.remove();
	}

});
