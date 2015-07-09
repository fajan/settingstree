<?php


if (!class_exists('settingslevel',false)){
require_once('settingswrapper.class.php');	


class settingslevel{
	public $path = null;				// absolute path
	private $_pathdiff = null;			// relative path from parent
	private $_parent = null;			// the parent or null if it's the root
	private $_children = array();		// children levels
	private $_hierarchy = null;			// the settingshierarchy containing this level.
	private $_settings = array();		// the array of settingswrapper (by key) to this level has.
	private $_values = array();			// values (array: key=>[prot,value]) for the level.
	
	function __construct(settingshierarchy $hierarchy, settingslevel $parent = null, $path){
		$this->_parent = $parent;
		$this->_hierarchy = $hierarchy;
		$this->path = ':'.ltrim($path,':');
		if ($this->_parent){
			$this->_pathdiff = 	substr($path,strlen($this->_parent->path));	// remove the parent's path from path. 
		}
	}

	function getDefault($key){
		if (($x = $this->getLocal($key)) !== null) return $x;
		return $this->_hierarchy->getCurrent($key);
	}

	function getLocal($key){
		if (($v = @$this->_values[$key]['value']) !== null){
			return $v; // value's last override was on this level.
		}
		if ($this->_parent){	// check parent level.
			return $this->_parent->getLocal($key);
		}
		// this is root
		return $this->_hierarchy->getLocal($key);	// get the 'local' variable. (null for extended variable...)
	}

	function getProtected($key){
		if (@$this->_values[$key]['prot']){
			return $this->getLocal($key); 		// if the value is protected on this level, then we return the local value.
		}
		if ($this->_parent){	// check parent level.
			return $this->_parent->getProtected($key);
		}
		return $this->_hierarchy->getProtected($key);
	}

	
	function setValues(array $values){
		$this->_values = $values;
	}
	
	private function _getSettings(){
		if (!$this->_settings){
			foreach ($this->_hierarchy->getFieldConfig() as $key=>$meta){
				$this->_settings[$key] = new settingswrapper($key,$this,$meta,$this->_values[$key]);
			}
		}
		return $this->_settings;
	}
	
	function showHtml(){
		$ret = "<div id='config__manager'><fieldset><legend>Settings for '<b>{$this->path}</b>'</legend><div class='table'><table class='inline'><tbody>";
		foreach ($this->_getSettings() as $key => $setting){
			$ret .= $setting->showHtml();
		}
		$ret .= "</tbody></table></div></fieldset></div>";
		return $ret;
	}

	
	function getLevel(array $path){
		if (empty($path)){
			return $this;
		}
		$child = array_shift($path);
		if ($child == ''){
			global $conf;
			$child = $conf['start'];
		}
		if (!($c = @$this->_children[$child])){
			$c = $this->_children[$child] = new static($this->_hierarchy,$this,$this->path.':'.$child);
		}
		return $c->getLevel($path);
	}
	
	function addLevel($path,$values){
		if (!is_array($path)){
			$path = explode(':',ltrim($path,':'));	// explode path if not already exploded.
		}
		if (empty($path)){
			$this->setValues($values);
			return;
		}
		$child = array_shift($path);
		if ($child == '') {
			global $conf;
			$child = $conf['start'];
		}
		if (!($c = @$this->_children[$child])){
			$c = $this->_children[$child] = new static($this->_hierarchy,$this,$this->path.':'.$child);
		}
		$c->addLevel($path,$values);
	}

}
} // class_exists