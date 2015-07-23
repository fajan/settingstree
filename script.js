var settingstree_selectlevel, settingstree_show_in_hierarchy; // these functions are global, so it is callable by explorerTree / button events
jQuery.fn.settingsTree = function(opts){
	if (jQuery(this).length !== 1){
		throw 'There must be exactly one settingsTree instance on a page!';
	}
	var $ = jQuery, $root = $(this), opts = $.extend({},opts), pluginname = opts.pluginname, token = opts.token, pending = false, path = ':',
		getLang = function (msgid){
			var str;
			if ((str = LANG.plugins.settingstree[msgid]) === undefined){
				str = '{msgid:'+msgid+'}';	// Note: if lang keys needs to be html escaped then there is a conceptual problem about msgids...
			}
			return str;
		},
		getchanged = function(){
			var values = {};
			$root.find('.input_area.changed, .protect_area.changed').each(function(){
				var $inp = $(this).find('input, textarea, select'), name = $inp.prop('name'), val = $inp.val(), m;
				if ($inp.is(':checkbox')){
					val = $inp.prop('checked') ? 1 : 0;
				}
				if (!(m = name.match(/^(config|protect)\[(.*)\]$/))){	// we don't know what area is this...
					return;
				}
				if (!values[m[2]]){ values[m[2]] = {};}
				values[m[2]][m[1]] = val;
			});
			return values;
		},
		savelevel = function(){
			$('.settingstree_error_area').html($("<div class='notify'>"+getLang('saving_changes')+"</div>"));
			var changes = getchanged();
			$.post(DOKU_BASE + 'lib/exe/ajax.php',
				{ call:'plugin_settingstree', operation: 'savelevel', pluginname: pluginname, path: path, sectok: token, data: changes },
				function(r){
					if (r.token) token = r.token;
					if (r.html){ $root.html(r.html);	}
					if (r.success){	
						$('.settingstree_error_area').html(("<div class='success'>"+(r.msg||"success")+"</div>"));
						// update the hierarchy, if it was changed by the save, and save was successful.
						var key = $('.settingstree_left_column').data('current');
						if (key && changes[key] !== undefined){
							$('.settingstree_left_column').data('current',null);
							settingstree_show_in_hierarchy(key,path);
						}
					}
					else{			$('.settingstree_error_area').html($("<div class='error'>"+(r.msg||"fail")+"</div>"));		}
				}
			);	
			
		}
		resetlevel = function(){
			$root.find('.input_area.changed, .protect_area.changed').each(function(){
				var $inp = $(this).find('input, textarea, select'), val =$inp.val(), def = $(this).data('currentval');
				if ($inp.is(':checkbox')){
					$inp.prop('checked',def ? true : false);
				}else{
					$inp.val(def);
				}
				$(this).removeClass('changed');
			})
		}
		inputchange = function(){
			var $inp = $(this), $inpa = $inp.parents('.input_area:first, .protect_area:first'), val = $inp.val();
			if ($inp.is(':checkbox')){
				val = $inp.prop('checked') ? 1 : 0;
			}
			if (val == $inpa.data('currentval')){
				$inpa.removeClass('changed');
			}else{
				$inpa.addClass('changed');
			}
		}
		init_area = function(){
			$root.on('change','input, textarea, select',inputchange);
			$root.on('settingstree_save',savelevel);
			$root.on('settingstree_cancel',resetlevel);
		},
		has_pending = function(){
			return (pending || $root.has('.input_area.changed, .protect_area.changed').length);
		},
		open_hierarchy_level = function(open_level){
			var $hier = $('.settingstree_left_column');
			$hier.find('.highlighted_level').removeClass('highlighted_level');
			$hier.find('[data-path="'+open_level+'"]').addClass('highlighted_level');
		};
	
	
	
	settingstree_selectlevel = function settingstree_selectlevel(id){
		if (has_pending()){
			$('.settingstree_error_area').html($("<div class='error'><h4>"+getLang('pending_change')+"</h4><p>"+getLang('pending_change_explain')+"</p></div>"));
			return;
		}
		$root.html('<div class="settingstree_error_area"><div class="notify">'+getLang('loading_level')+'</div></div>');
		$.post(DOKU_BASE + 'lib/exe/ajax.php',
			{ call:'plugin_settingstree', operation: 'loadlevel', pluginname: pluginname, path: id, sectok: token },
			function(r){
				if (r.token) token = r.token;
				if (r.error) alert(r.msg);
				if (r.path){ path = r.path;	}
				if (r.html){ 
					$root.html(r.html); 
					var key = $('.settingstree_left_column').data('current');
					if (key){
						settingstree_show_in_hierarchy(key,path);
					}
				} 
				else alert('Error: html not loaded!');
			}
		);	
	};
	settingstree_show_in_hierarchy = function(key,open_level){
		var $left_col = $('.settingstree_left_column'), current = $left_col.data('current')||null;
		if (current !== key){
			$left_col.html('<div class="notify">'+getLang('loading_hierarchy')+'</div>');
			$.post(DOKU_BASE + 'lib/exe/ajax.php',
				{ call:'plugin_settingstree', operation: 'show_hierarchy', pluginname: pluginname, key: key, sectok: token },
				function(r){
					if (r.token) token = r.token;
					if (r.error) alert(r.msg);
					if (r.html){ 
						$left_col.html(r.html);
						$left_col.data('current',key);
						open_hierarchy_level(open_level);
					}
					else alert('Error: html not loaded!');
				}
			);	
		}else{
			open_hierarchy_level(open_level);
		}
	}
	init_area();
	
};