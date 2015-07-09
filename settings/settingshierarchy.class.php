<?php

if (!class_exists('settingshierarchy',false)){
	
require_once('settingslevel.class.php');	


class settingshierarchy{
	private $_root = null;					// root settingslevel of the hierarchy
	private $_pluginname = null;			// the pluginname of the settings we set
	private $_meta = array();				// the metadata for each setting (from plugin config) array by key=>meta
	private $_defaults = array();			// the defaults for each settings (from plugin config) array by key=>default
	private $_values = array();				// the values and protections set by admins in admin pages. array by path => [key => [ prot, value]];
	
	private static $conf_local = null;		// local configurations (only for plugins)
	private static $conf_protected = null;	// protected configurations (only for plugins)
	
	
	function __construct($pluginname,$meta,$defaults,$values){
		$this->_pluginname = $pluginname;
		$this->_meta = $meta;
		$this->_defaults = $defaults;
		$this->_values = $values;
	}
	
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
				$this->_root->getValues($values);
			}
			else{
				$this->_root->addLevel($path,$values);
			}
		}
	}


	function getCurrent($key){
		if (($x = $this->getLocal($key)) != null) return $x;
		return $this->_defaults[$key];
	}
	function getLocal($key){ 			return self::_getlocal($key,$this->_pluginname);		}
	function getProtected($key){		return self::_getprotected($key,$this->_pluginname);	}
	function isExtended($key){			return self::_isextended($key,$this->_pluginname);	}
	
	static function _getlocal($key,$pluginname){

		if (!static::$conf_local){
			$conf = array();
			require (DOKU_INC."conf/local.php");
			if (is_array(!$conf['plugin'])) $conf['plugin'] = array();	// no plugins sub-array
			static::$conf_local = $conf['plugin'];
		}
		return @static::$conf_local[$pluginname][$key];
	}
	static function _getprotected($key,$pluginname){
		if (!static::$conf_protected){
			$conf = array();
			require (DOKU_INC."conf/local.protected.php");
			if (is_array(!$conf['plugin'])) $conf['plugin'] = array();	// no plugins sub-array
			static::$conf_protected = $conf['plugin'];
		}
		return @static::$conf_protected[$pluginname][$key];
	}
	static function _isextended($key,$pluginname){
		global $conf;	// conf contains all plugin settings that are used by the config plugin.
		return !array_key_exists($key,(array)@$conf['plugin'][$pluginname]);
	}
	
	
}
} // class_exists
