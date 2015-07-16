var settingstree_selectlevel; // this function is global, so it is callable by explorerTree
jQuery.fn.settingsTree = function(opts){
	if (jQuery(this).length !== 1){
		throw 'There must be exactly one settingsTree instance on a page!';
	}
	var $ = jQuery, $root = $(this), opts = $.extend({},opts), pluginname = opts.pluginname, token = opts.token, pending = false, _locLang = LANG.plugins.settingstree, path = ':',
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
			$('.settingstree_error_area').html($("<div class='notify'>"+_locLang.saving_changes+"</div>"));
			$.post(DOKU_BASE + 'lib/exe/ajax.php',
				{ call:'plugin_settingstree', operation: 'savelevel', pluginname: pluginname, path: path, sectok: token, data: getchanged() },
				function(r){
					if (r.token) token = r.token;
//					if (r.error) alert(r.msg);
					if (r.html){ $root.html(r.html);	}
					if (r.success){	$('.settingstree_error_area').html($("<div class='success'>"+(r.msg||"success")+"</div>"));	}
					else{			$('.settingstree_error_area').html($("<div class='error'>"+(r.msg||"fail")+"</div>"));		}
				}
			);	
			
		}
		resetlevel = function(){
			$root.find('.input_area.changed, .protect_area.changed').each(function(){
				var $inp = $(this).find('input, textarea, select'), val =$inp.val, def = $(this).data('currentval');
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
		};
	
	
	
	settingstree_selectlevel = function settingstree_selectlevel(id){
		if (has_pending()){
			$('.settingstree_error_area').html($("<div class='error'><h4>"+_locLang.pending_change+"</h4><p>"+_locLang.pending_change_explain+"</p></div>"));
			return;
		}
		$root.html('Loading...');
		$.post(DOKU_BASE + 'lib/exe/ajax.php',
			{ call:'plugin_settingstree', operation: 'loadlevel', pluginname: pluginname, path: id, sectok: token },
			function(r){
				if (r.token) token = r.token;
				if (r.error) alert(r.msg);
				if (r.path){ path = r.path;	}
				if (r.html){ $root.html(r.html); }
				else alert('Error: html not loaded!');
			}
		);	
	};
	
	init_area();
	
};