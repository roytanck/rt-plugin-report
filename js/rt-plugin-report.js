jQuery(document).ready( function( $ ){

	var rtpr_slugs = rt_plugin_report_vars.plugin_slugs;
	var rtpr_slugs_array = rtpr_slugs.split(',');
	var rtpr_nrof_plugins = rtpr_slugs_array.length;
	var rtpr_progress = 0;

	function rtpr_process_next_plugin(){
		if( rtpr_slugs_array.length > 0 ){
			var slug = rtpr_slugs_array.shift();
			//if( !$( '.rt-plugin-report-row-' + slug ).length ){
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
			$('#rt-plugin-report-progress').html( rt_plugin_report_vars.complete_str );
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

});