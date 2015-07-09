var settingstree_selectlevel; // this function is global, so it is callable by explorerTree
jQuery.fn.settingsTree = function(opts){
	if (jQuery(this).length !== 1){
		throw 'There must be exactly one settingsTree instance on a page!';
	}
	var $ = jQuery, root = $(this), opts = $.extend({},opts), pluginname = opts.pluginname, token = opts.token;
	
	settingstree_selectlevel = function settingstree_selectlevel(id){
		$.post(DOKU_BASE + 'lib/exe/ajax.php',
			{ call:'plugin_settingstree', operation: 'loadlevel', pluginname: pluginname, path: id, sectok: token },
			function(r){
				if (r.token) token = r.token;
				if (r.error) alert(r.msg);
				if (r.html){
					$('#settingstree_area').html(r.html);
				}
				else alert('Error: html not loaded!');
			}
		);	
	};
	
	
};