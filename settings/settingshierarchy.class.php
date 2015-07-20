<?php

if (!class_exists('settingshierarchy',false)){
	
require_once('settingslevel.class.php');	


class settingshierarchy{
	private $_root = null;					// root settingslevel of the hierarchy
	private $_pluginname = null;			// the pluginname of the settings we set
	private $_meta = array();				// the metadata for each setting (from plugin config) array by key=>meta
	private $_defaults = array();			// the defaults for each settings (from plugin config) array by key=>default
	private $_values = array();				// the values and protections set by admins in admin pages. array by path => [key => [ prot, value]];
	
	private $_lang = null;					// to be used for getLang
	private static $conf_local = null;		// local configurations (only for plugins)
	private static $conf_protected = null;	// protected configurations (only for plugins)
	public static $cache = null;			// memcache
	public static $helper = null;			// settingstree for local getLang
	
	
	function __construct($pluginname,$meta,$defaults,$values){
		$this->_pluginname = $pluginname;
		$this->_meta = $meta;
		$this->_defaults = $defaults;
		$this->_values = $values;
	}
	
	function getLang($key){
		$lang = $this->_getLangInited();
		global $conf;
		if (!($ret = @$lang[$conf['lang']][$key])){
			if (!($ret = @$lang['en'][$key])){
				// we need to check, if it's '{key}_o_{option}' from a plugin setting, and return null if it is, but translation does not exists, to display only the 'option' part of it.
				if (!preg_match('~^('.implode('|',array_keys($this->_meta)).')_o_~',$key)){
					$ret = '{msgid:'.$key.'}'; // else we need to return the something if we want to display, that the key is missing instead of simply ignore a message...
					// Note: if lang keys needs to be html escaped then there is a conceptual problem about msgids...
				/** 	imagine a situation: 
				 *	function resultMessage($error){
				 *		$message = '';
				 *		if ($error)
				 *			$message .= getLang('corruption_warning');			// key missing, should be 'Corruption WARNING!';
				 *		$message .= ' '.sprintf(
				 *			getLang('your_data_is_%s_saved'),					// key=>value: 'Your data is %s saved!'
				 *			($error ? getLang('not') : ''),						// key missing, should be 'not'
				 *			);
				 *		if ($error)
				 *			$message .= ' '.getLang('backup_your_data');		// key missing, should be 'Make sure you backup your data manually!'
				 *		return trim($message);
				 *	}
				 *		on error:
				 *		The user should see if keys where there:			 'Corruption WARNING! Your data is not saved! Make sure you backup your data manually!';
				 *		User should at least see:							 '{msgid:corruption_warning} Your data is {msgid:not} saved! {msgid:backup_your_data}';
				 *		By ignoring missing messages, the user will see: 	 'Your data is  saved!'; 
				 */
				}
			}
		}
		return $ret;
	}
	
	private function _getLangInited(){
		if ($this->_lang === null){
			global $conf;
			$ls = array($conf['lang']);
			if ($conf['lang'] !== 'en') $ls[] = 'en';	// English is always the fallback. 
			//TODO: update for multi-level localization  ['de_DE-informal','de_DE','de','en_GB','en']
			
			foreach ($ls as $l){	// for all language we need
				$path = DOKU_INC."lib/plugins/{$this->_pluginname}/lang/{$l}/settings.php";
				if (static::$cache 	//if caching is enabled
					&& @filemtime($path) <= static::$cache->get("plugin_{$this->_pluginname}_lang_{$l}_settings_time") 	// and cached version is old enough (note: cache is not bound to settingstree: "plugin_{name}_lang_{lang}_type[_time]" is usable by all plugins
					&& $ll = static::$cache->get("plugin_{$this->_pluginname}_lang_{$l}_settings")						// and cache contains the language array
					){
					$this->_lang[$l] = $ll;	
					continue; // use that, no need to include the files.
				}
				if (file_exists($path)){
					$lang = array();
					@include($path);
					$this->_lang[$l] = $lang;
					if (static::$cache){	// update the cache so next we don't need to read filesystem
						static::$cache->set("plugin_{$this->_pluginname}_lang_{$l}_settings",$lang,0);
						static::$cache->set("plugin_{$this->_pluginname}_lang_{$l}_settings_time",filemtime($path),0);
					}
				}
			}
		}
		return $this->_lang;
	}
	function getPluginName(){return $this->_pluginname;}
	function getLevel($folder){
		if (!$this->_root) $this->_loadTree();
		$path = explode(':',ltrim(strtr($folder,'/',':'),':'));
		if ($path[0] == '') return $this->_root;
		return $this->_root->getLevel($path);
	}
	
	
	function getFieldConfig(){
		$return = array();
		foreach ($this->_meta as $key=>$meta){
			if (@$meta['_ignore_for_settingstree']) continue;
			$return[$key] = $meta;
		}
		return $return;
	}
	
	
	private function _loadTree(){
		$this->_root = new settingslevel($this,null,':');
		foreach ($this->_values as $path=>$values){
			if ($path == ':'){
				$this->_root->setValues($values);
			}
			else{
				$this->_root->addLevel($path,$values);
			}
		}
	}
	function getValueTree(){
		return $this->_root->getValuesRecursive();
	}

/*	function getCurrent($key){
		if (($x = $this->getLocal($key)) != null) return $x;
		return $this->_defaults[$key];
	}*/

	function getDefault($key){ 			return @$this->_defaults[$key];		}
	function getLocal($key){ 			return self::_getlocal($key,$this->_pluginname);		}
	function getProtected($key){		return self::_getprotected($key,$this->_pluginname);	}
	function isExtended($key){			return self::_isextended($key,$this->_pluginname);	}
	
	static function _getlocal($key,$pluginname){
		if (static::_isextended($key,$pluginname)){
			return null;
		}
		if (!static::$conf_local){
			$conf = array();
			require (DOKU_INC."conf/local.php");
			if (is_array(!$conf['plugin'])) $conf['plugin'] = array();	// no plugins sub-array
			static::$conf_local = $conf['plugin'];
		}
		return @static::$conf_local[$pluginname][$key];
	}
	static function _getprotected($key,$pluginname){
		if (static::_isextended($key,$pluginname)){
			return null;
		}
		if (!static::$conf_protected){
			$conf = array();
			require (DOKU_INC."conf/local.protected.php");
			if (is_array(!$conf['plugin'])) $conf['plugin'] = array();	// no plugins sub-array
			static::$conf_protected = $conf['plugin'];
		}
		return @static::$conf_protected[$pluginname][$key];
	}
	static function _isextended($key,$pluginname){
		global $conf;
		return !array_key_exists($key,(array)@$conf['plugin'][$pluginname]);
	}

	function showHierarchyLevelRecursive($level,$key,&$empty){
		$ch_empty = true;
		$chn = $level->getChildren();
		if (!empty($chn)){
			$chhtml = '<ul>';
			foreach ($chn as $ch){
				$_chhtml = $this->showHierarchyLevelRecursive($ch,$key,$_empty);
				if (!$_empty) $chhtml .= $_chhtml;
				$ch_empty = $ch_empty && $_empty;
			}
			$chhtml .= '</ul>';
		}
		$p = $level->isLevelProtected($key);
		$v = $level->isLevelValue($key);
		$empty = !$p && !$v && $ch_empty;
		$lev = "<li data-path='{$level->getPath()}' class='".($empty ? 'empty':'')."'>";
		$lev .= "<b class='".($p ? 'protect':'').' '.($v ? 'value':'')."'>"
//			.settingshierarchy::$helper->getLang('on_level').": "
			."{$level->getLevelNameRelative()}</b>";//.settingshierarchy::$helper->getLang('on_level')." '<b>{$level->getPath()}</b>':";
		$lev .= ($p ? "<span class='_p'>".settingshierarchy::$helper->getLang('became_protected').".</span>" : "");
		$lev .= ($v ? "<span class='_v'>".settingshierarchy::$helper->getLang('value_set_to')." <code>{$this->format($key,$level->getLevelValue($key))}</code>".($level->isLevelValueIgnored($key) ? " <i class='_i'>".settingshierarchy::$helper->getLang('but_ignored')."</i>" : "").".</span>" : "");
		return $lev . ($ch_empty ? "" : $chhtml) ."</li>";
	}
	function showHierarchy($key){
		$ret .= '<ul class="settings_hierarchy_history">';
		$ret .= "<li class='title'>".sprintf(settingshierarchy::$helper->getLang("settings_for_%s"),$key).'</li>';
		if (!$this->isExtended($key)){
			$v = $this->getLocal($key) !== null;
			$p = $this->getProtected($key) !== null;
			$ret .= "<li><b class='".($p ? 'protect':'').' '.($v ? 'value':'')."'>".settingshierarchy::$helper->getLang("in_config").":</b>";
			$ret .=	"<span class='_d'>".settingshierarchy::$helper->getLang('default_is')." <code>{$this->format($key,$this->getDefault($key))}</code>.</span>";
			$ret .= ($p ? "<span class='_p'>".settingshierarchy::$helper->getLang('became_protected').".</span>" : "");
			$ret .= ($v ? "<span class='_v'>".settingshierarchy::$helper->getLang('local_is')." <code>{$this->format($key,$this->getLocal($key))}</code>.</span>" : "");
		}
		else{
			$ret .= "<li><b>".settingshierarchy::$helper->getLang("this_is_extended")."</b>";
			$ret .=	"<span class='_d'>".settingshierarchy::$helper->getLang('default_is')." <code>{$this->format($key,$this->getDefault($key))}</code>.</span>";
		}
		if (!$this->_root) $this->_loadTree();
		$roothtml = "<ul>".$this->showHierarchyLevelRecursive($this->_root,$key,$empty)."</ul>";
		return $ret. ($empty ? "" : $roothtml)."</li></ul>";
	}
	function format($key,$value){
		if ($value === null) return "[".settingshierarchy::$helper->getLang('default_value')."]";
		if ($this->_meta[$key][0] == 'onoff'){
			return settingshierarchy::$helper->getLang($value ? "on" : "off");
		}
		if ($value === ''){
			return "[".settingshierarchy::$helper->getLang('empty_string')."]";
		}
		return $value;
	}
	
}
} // class_exists
