<?php

if (!class_exists('settingswrapper',false)){
require_once(DOKU_INC.'lib/plugins/config/settings/config.class.php');

class settingswrapper{
	private $_setting = null;				// the settings_* class form config plugin.
	private $_key = null;					// the key of the setting
	private $_level = null;					// the settingslevel object
	private $_value = null;					// the value defined in this level
	private $_protect = false;				// the protection added by this level
	public static $plugin = null;			// this point to the plugin_helper_settingstree
	
	
	function __construct($key,settingslevel $level, array $meta, $set){
		$this->_key = $key;
		$this->_level = $level;
		$this->_protect = @$set['protect'];
		$this->_override = @$set['value'];
		$this->_initSetting($meta);
	}
	
	
	/** Tells if the level has setting (anything that needs to be saved).
	 *  returns boolean.
	 */
	function has_setting(){
		return $this->_override || $this->_protect;
	}
	
	private function _initSetting($meta){
		$class_meta = array_shift($meta);
		if($class_meta && class_exists('setting_'.$class_meta)){
			$class = 'setting_'.$class_meta;
		}elseif($class_meta == ''){
			$class = 'setting';			// the '' option is the textarea...
		}else{
			die('TODO: extend this function!');
		}
		$this->_setting = new $class($this->_key,$meta);
		$local = $this->_level->getLocal($this->_key);		// local means: the value defined in local.php -> non-protected but non-default value
		$prot = $this->_level->getProtected($this->_key);  // protected: the value is in local.protected.php, regardless of it is in local.php or not.
		
		$this->_setting->initialize(
			$this->_value !== null ? $this->_value : $this->_level->getDefault($this->_key),
			$local,
			$prot
		);
		
	}
	
	
	function showHtml(){
		list($label,$input) = $this->_setting->html(static::$plugin);
		$cssclass = $this->_setting->is_default() ? ' class="default"' : ($this->_setting->is_protected() ? ' class="protected"' : '');
		$has_error = $this->_setting->error() ? ' class="value error"' : ' class="value"';
		$has_icon = $this->_setting->caution() ? '<img src="'.DOKU_PLUGIN_IMAGES.$this->_setting->caution().'.png" alt="'.$this->_setting->caution().'" title="'.static::$plugin->getLang($this->_setting->caution()).'" />' : '';
		$ret = "<tr {$cssclass}><td class='label'><span class='outkey'>{$this->_setting->_out_key(true,true)}</span>{$has_icon}{$label}</td><td {$has_error}>{$input}</td></tr>";
//		$ret .= ""
		return $ret;
	}
	
	
}
} // class_exists