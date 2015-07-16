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
				$ret = $key;
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
/*	
	private static $conf_exists = null;		// keys existing for all plugins (prot, loc or default)
	static function _isextended($key,$pluginname){
		if (!static::$conf_exists){
			global $conf;	// conf contains all plugin settings that are used by the config plugin.
			static::$cong_exists = array();
			array_walk($conf['plugin'],function($arr,$pl){
				static::$conf_exists[$pl] = array();
				array_walk($arr, function($val,$key) use($pl){
					static::$conf_exists[$pl][$key] = true;
				});
			});
		}
		return !@static::$conf_exists[$pluginname][$key];	// returns boolean: true if key does not exist.
	}*/
	
	
}
} // class_exists
