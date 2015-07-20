<?php


if (!class_exists('settingslevel',false)){
require_once('settingswrapper.class.php');	


class settingslevel{
	public $path = null;				// absolute path
	private $_parent = null;			// the parent or null if it's the root
	private $_children = array();		// children levels
	private $_hierarchy = null;			// the settingshierarchy containing this level.
	private $_settings = null;			// the array of settingswrapper (by key) to this level has.
	private $_values = null;			// values (array: key=>[prot,value]) for the level.
	
	function __construct(settingshierarchy $hierarchy, settingslevel $parent = null, $path){
		$this->_parent = $parent;
		$this->_hierarchy = $hierarchy;
		$this->path = ':'.ltrim($path,':');
	}

	function getLevelNameRelative(){
		if ($this->path == ':'){
			global $lang;
			return '['.$lang['mediaroot'].']';
		}
		if (!$this->_parent){
			return $this->path;
		}
		$val = substr($this->path,strlen($this->_parent->path));
		if ($val[0] == ':') $val = substr($val,1);
		return $val;
	}
	function getHierarchy(){
		return $this->_hierarchy;
	}
	function getCurrent($key){
		/*	1, if there is protected value then that (here)
			2, if there is value for level then that (in getCurrentNoProt)
			3, parent's current value (in getCurrentNoProt) */
		if (($v = $this->getProtected($key)) !== null) {return $v;}
		return $this->getCurrentNoProt($key);
		
	}
	private function getCurrentNoProt($key){
		// getCurrentNoProt: getProtected() may return getCurrent() value, but getCurrent() value checks getProtected()... we need a getCurrent() without calling getProtected()
		/*	1, if there is protected value then that (in getCurrent)
			2, if there is value for level then that (here)
			3, parent's current value (here) */ 
		if (($v = @$this->_values[$key]['value']) !== null){ return $v;	}
		if ($this->_parent) { return $this->_parent->getCurrent($key);	}
		/*		root's current:
			1, if there is protected, then that (done)
			2, if root has value then that (done)
			3, if config's local then that
			4, config's default
		*/
		if (($v = $this->_hierarchy->getLocal($key)) !== null) {return $v;}
		return $this->_hierarchy->getDefault($key);
	}

	function getDefault($key){
		/*	1, if there is a protected value then that
			2, parents's current value */
		if (($v = $this->getProtected($key)) !== null) {return $v;}
		if ($this->_parent){		return $this->_parent->getCurrent($key);	}
		/* root's default: 
			1, if config's protected then that (done)
			2, if config's local then that
			3, config's default */
		if (($v = $this->_hierarchy->getLocal($key)) !== null) {return $v;}
		return $this->_hierarchy->getDefault($key);
	}

	
	function getLocal($key){
		/*	1, if there is a protected value then null
			2, if the level has value then that
			3, null */
		if (($v = $this->getProtected($key)) !== null) {return null;}
		if (($v = @$this->_values[$key]['value']) !== null){ return $v;	}
		return null;
	}
	
	function getProtected($key){
		/*	1, if there is a protected value from parent then that
			2, if there is a protection on this level then the current value
			3, null */
		if (($v = $this->getParentProtected($key)) !== null)  {return $v;}
		if (@$this->_values[$key]['protect']){ return $this->getCurrentNoProt($key); }
		return null;
	}
	function getParentProtected($key){
		if ($this->_parent){	// check parent level.
			return $this->_parent->getProtected($key);
		}
		return $this->_hierarchy->getProtected($key);
	}
	
	
	function isLevelValue($key){
		return isset($this->_values[$key]['value']);
	}
	function getLevelValue($key){
		return @$this->_values[$key]['value'];
	}
	function isLevelValueIgnored($key){
		return (isset($this->_values[$key]['value']) && ($this->getParentProtected($key) !== null));	
	}
	function isLevelProtected($key){
		return isset($this->_values[$key]['protect']);
	}
	
	function setValues(array $values){	// setValues should always be called before getSettings. If not, just ignore.
		if ($this->_settings === null)
			$this->_values = $values;
	}
	function getValues(){
		return $this->_values;
	}
	function getValuesRecursive(){
		$ret = array();
		$v = $this->getValues();
		if (!empty($v)){
			$ret[$this->path] = $v;
		}
		foreach($this->_children as $child){
			$ret = array_merge($ret,$child->getValuesRecursive());
		}
		return $ret;
	}
	
	function getParent(){
		return $this->_parent;
	}
	
	private function _getSettings(){
		if (!$this->_settings){
			foreach ($this->_hierarchy->getFieldConfig() as $key=>$meta){
				$this->_settings[$key] = new settingswrapper($key,$this,$meta,$this->_values[$key]);
			}
		}
		return $this->_settings;
	}
	function checkValues($data){
		$set = $this->_getSettings();
		$save_success = true;
		foreach ($data as $key=>$new){
			if (isset($new['config'])){
				if (!$set[$key]->tryUpdate($new['config'])){	// returns false on error
					$save_success = false;
				}else{
					$par_val = $this->getDefault($key);
//					echo "/* {$key}: '{$par_val}' != {$set[$key]->_value} */";
					if ($set[$key]->_value !== null && $par_val !== $set[$key]->_value){
						$this->_values[$key]['value'] = $set[$key]->_value;	// we do need to save value, as it's not default. (default == parent's value)
					}else{
						unset($this->_values[$key]['value']);				// we do need to delete the value, as it's default.
					}
				}
			}
			if (isset($new['protect'])){
				if ($new['protect'] === 'false') $new['protect'] = false;
				if ($new['protect'] === 'true') $new['protect'] = true;
				$par_val = $this->getParentProtected($key);
				$toset = $par_val ? null : $new['protect'];
				$set[$key]->setProtect($toset);
				if ($toset)	$this->_values[$key]['protect'] = true;	// we only save if a level is protected.
				else unset($this->_values[$key]['protect']);
			}
			if (empty($this->_values[$key]))
				unset($this->_values[$key]);
		}
		return $save_success;
	}
	
	
	function showHtml(){
//		$ret .= "<input type='hidden' name='settingstree_path' value='{$this->path}' /><input type='hidden' name='settingstree_pluginname' value='{$this->_hierarchy->getPluginName()}' />";
		$ret .= "<div class='settingstree_error_area'></div>";
		$ret .= "<div id='config__manager' data-path='{$this->path}'><fieldset><legend>".(sprintf(settingshierarchy::$helper->getLang('settings_for_%s'),$this->path))."</legend><div class='table'><table class='inline'><tbody>";
		foreach ($this->_getSettings() as $key => $setting){
			$ret .= $setting->showHtml();
		}
		$ret .= "</tbody></table></div></fieldset></div>";
		$ret .= "<div class='settingstree_error_area'></div>";
		$ret .= "<div class='settingstree_buttons'>
			<button id='settingstree_save_button' onclick=\"jQuery(this).trigger('settingstree_save')\">".settingshierarchy::$helper->getLang('save')."</button> 
			<button id='settingstree_cancel_button'  onclick=\"jQuery(this).trigger('settingstree_cancel')\">".settingshierarchy::$helper->getLang('cancel')."</button>
		</div>";
		return $ret;
	}

	function getPath(){
		return $this->path;
	}
	function isRoot(){
		return !$this->_parent;
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
			$this->_children[$child] = new static($this->_hierarchy,$this,$this->path.':'.$child);
			$c = $this->_children[$child];
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
			$this->_children[$child] = new static($this->_hierarchy,$this,$this->path.':'.$child);
			$c = $this->_children[$child];
		}
		$c->addLevel($path,$values);
	}

	function getChildren(){
		return $this->_children;
	}
}
} // class_exists