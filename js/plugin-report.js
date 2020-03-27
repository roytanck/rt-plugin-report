jQuery(document).ready( function( $ ){

	var rtpr_slugs = plugin_report_vars.plugin_slugs;
	var rtpr_slugs_array = rtpr_slugs.split(',');
	var rtpr_nrof_plugins = rtpr_slugs_array.length;
	var rtpr_progress = 0;

	function rtpr_process_next_plugin(){
		if( rtpr_slugs_array.length > 0 ){
			var slug = rtpr_slugs_array.shift();
			if( $( '.plugin-report-row-temp-' + slug ).length ){
				rtpr_get_plugin_info( slug );
			} else {
				rtpr_process_next_plugin();
			}
		}
		// update the progress information on the page
		var perc = Math.ceil( ( rtpr_progress / rtpr_nrof_plugins ) * 100 );
		if( perc < 100 ){
			$('#plugin-report-progress').html( '<div class="plugin-report-progress-outer"><div class="plugin-report-progress-inner" style="width:' + perc + '%;"></div></div>' );
			rtpr_progress++;	
		} else {
			$('#plugin-report-progress').html( '' );
		}
		
	}

	function rtpr_get_plugin_info( slug ){
		var data = {
			'action': 'rt_get_plugin_info',
			'slug': slug,
			'nonce': plugin_report_vars.ajax_nonce
		};

		jQuery.post( ajaxurl, data, function(response) {
			// parse the response
			obj = jQuery.parseJSON(response);
			// replace the temporary table row with the new data
			$('#plugin-report-table .plugin-report-row-temp-' + slug ).replaceWith( obj.html );
			// on to the next...
			rtpr_process_next_plugin();
		});
	}

	// kick things off
	rtpr_process_next_plugin();


	// Create the export button.
	$('#plugin-report-buttons').append('<button class="button" href="#" id="plugin-report-export-btn">' + plugin_report_vars.export_btn + '</button>');

	// Export button event handler.
	$('#plugin-report-export-btn').click( function( e ){

		// Create a backup of the table's html markup.
		var table_backup = $('#plugin-report-table').html();

		// Attempt to put local styles in elements with known CSS classes.
		$('td.pr-risk-low').attr( 'style', 'background-color: #dfd; color: #090 !important; font-weight: bold;' );
		$('td.pr-risk-high').attr( 'style', 'background-color: #fdd; color: #c00 !important; font-weight: bold;' );
		$('td.pr-risk-med').attr( 'style', 'font-weight: bold;' );

		// Call the function that does the exporting.
		rtpr_export_table();

		// Restore the backup.
		$('#plugin-report-table').html( table_backup );
	});


	// Export function based on https://stackoverflow.com/a/27843359 .
	function rtpr_export_table(){
		var table_html = $('#plugin-report-table').html();
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
		link.href = URL.createObjectURL( new Blob( [ "\ufeff", format( template, ctx ) ], {type: 'application/vnd.ms-excel'} ) );
		link.click();
		link.remove();
	}

});
