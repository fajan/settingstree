<?php

if (!class_exists('settingswrapper',false)){
require_once(DOKU_INC.'lib/plugins/config/settings/config.class.php');

class settingswrapper{
	private $_setting = null;				// the settings_* class form config plugin.
	private $_key = null;					// the key of the setting
	private $_level = null;					// the settingslevel object
	public $_value = null;					// the value defined in this level
	public $_protect = false;				// the protection added by this level
	private $_old_val = null;			// we store the old value here if the value is updated.
	private $_updated = false;
	private $_meta = null;
	
	function __construct($key,settingslevel $level, array $meta, $set){
		$this->_key = $key;
		$this->_level = $level;
		$this->_protect = @$set['protect'];
		$this->_value = @$set['value'];
		$this->_meta = $meta;
		$this->_initSetting($meta);
	}
	
	
	/** Tells if the level has setting (anything that needs to be saved).
	 *  returns boolean.
	 *
	function has_setting(){
		return $this->_override || $this->_protect;
	}*/
	
	function setProtect($val){
		if ($val === null)
			unset($this->_protect);
		else
			$this->_protect = (bool)$val;
		return true;
	}
	
	function tryUpdate($val){
		$this->_old_val = $this->_setting->_local;
		$changed = $this->_setting->update($val);
		if ($this->_setting->_error){
			return false;
		}
		$this->_updated = true;
		if ($this->_setting->_local !== null && $this->_setting->_local === $this->_setting->_default){ 
			$this->_setting->_local = null; 
		}
		$this->_value = $this->_setting->_local;
		return true;
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
		
		$this->_setting->initialize(
			$this->_level->getDefault($this->_key),
			$this->_level->getLocal($this->_key),
		// protection applies from parent not the current level. If you protect a level's value, you can still change it on this level, but it's protected for children.
			$this->_level->getParentProtected($this->_key)
		);
		
	}
	function currentValue(){
		return $this->_setting->_local === null ? $this->_setting->_default : $this->_setting->_local;
	}
	
	function showHtml(){
		$lang = $this->_level->getHierarchy();
		list($label,$input) = $this->_setting->html($lang);	// html only uses the $plugin as parameter to have a getLang method, we emulate that on hierarchy.
		$cssclass = $this->_setting->is_default() ? ' class="default"' : ($this->_setting->is_protected() ? ' class="protected"' : '');
//		$has_error = $this->_setting->error() ? ' class="value error"' : ' class="value"';
		$errorclass = $this->_setting->error() ? ' value error' : ' value';
		$has_icon = $this->_setting->caution() ? '<img src="'.DOKU_PLUGIN_IMAGES.$this->_setting->caution().'.png" alt="'.$this->_setting->caution().'" title="'.$lang->getLang($this->_setting->caution()).'" />' : '';
		$ret = "<tr {$cssclass}><td class='label' colspan='2'>";
		$ret .=	"<span class='outkey'>{$this->_setting->_out_key(true,true)}</span>{$has_icon}{$label}";
/*		$ret .= "<code><small>debug: _local = ".var_export($this->_setting->_local ,1).",
		_default = ".var_export($this->_setting->_default ,1).",
		_protected = ".var_export($this->_setting->_protected ,1).",
		_updated: '{$this->_updated}',
		_old_val: ".var_export($this->_old_val ,1)."</small></code><br/>";*/
		$ret .= "<button class='settingstree_button_show_hierarchy' onclick=\"jQuery('#hierarchy_area_{$this->_key}').slideToggle('fast');\">".settingshierarchy::$helper->getLang('show_hierarchy')."</button>";
		$ret .= "</td></tr>";
		$ret .= "<tr {$cssclass}><td class='protect_area' data-currentval='".($this->_protect ? '1' : '0')."'>
				<label for='settingstree_{$this->_key}_protect'>".settingshierarchy::$helper->getLang('protected')."</label>
				<input class='protect_input' type='checkbox' name='protect[{$this->_key}]' id='settingstree_{$this->_key}_protect' value='1' ".($this->_protect ? 'checked="checked"' : '')." ".($this->_level->getParentProtected($this->_key) !== null ? "disabled='disabled'":"")."/>
			</td>";
		$ret .= "<td class='input_area  {$errorclass}' data-currentval='{$this->currentValue()}'>";
		if ($this->_setting->_error){
			$ret .= "<div class='error'>".settingshierarchy::$helper->getLang('invalid_value').($this->_setting->_input !== null ? ": <code>{$this->format($this->_setting->_input)}</code>": "!")." </div>";
		}elseif ($this->_updated){
			$ret .= "<div class='info'>".settingshierarchy::$helper->getLang('updated_value_from').": <code>{$this->format($this->_old_val)}</code> </div>";
//			if ($this->_current_value === $this->_level->getDefault()){	}
		}
		$ret .= "{$input}</td></tr>";
		$h = $this->showHierarchy($open);
		$ret .=	"<tr {$cssclass}><td colspan='2' class='hierarchy_area' id='hierarchy_area_{$this->_key}' ".($open ? "" : "style='display: none;'").">{$h}</td></tr>";
//		$ret .= ""
		return $ret;
	}
	
	function format($value){
		if ($value === null) return "[".settingshierarchy::$helper->getLang('default_value')."]";
		if ($this->_meta[0] == 'onoff'){
			return settingshierarchy::$helper->getLang($value ? "on" : "off");
		}
		if ($value === ''){
			return "[".settingshierarchy::$helper->getLang('empty_string')."]";
		}
		return $value;
	}
	
	function showHierarchy(&$open){
		$level = $this->_level;
		$root = $level->getHierarchy();
		$ret = array();
		do{
			$p = $level->isLevelProtected($this->_key);
			$v = $level->isLevelValue($this->_key);
			if ($p || $v){
				$lev = "<li>".($level !== $this->_level ? settingshierarchy::$helper->getLang('on_level')." '<b>{$level->getPath()}</b>':":settingshierarchy::$helper->getLang('on_this_level'));
				$lev .= ($p ? "<span>".settingshierarchy::$helper->getLang('became_protected').".</span>" : "");
				$lev .= ($v ? "<span>".settingshierarchy::$helper->getLang('value_set_to')." <code>{$this->format($level->getLevelValue($this->_key))}</code>".($level->isLevelValueIgnored($this->_key) ? ' '.settingshierarchy::$helper->getLang('but_ignored') : "").".</span>" : "");
				$lev .= "</li>";
				array_unshift($ret,$lev);
			}
			$level = $level->getParent();
		}while ($level);
		if (!$root->isExtended($this->_key)){
			$lev = "<li>".settingshierarchy::$helper->getLang("in_config").":";
			$lev .=	"<span>".settingshierarchy::$helper->getLang('default_is')." <code>{$this->format($root->getDefault($this->_key))}</code>.</span>";
			$lev .= (($v =  $root->getLocal($this->_key))!==null ? "<span>".settingshierarchy::$helper->getLang('local_is')." <code>{$this->format($v)}</code>.</span>" : "");
			$lev .= ($root->getProtected($this->_key)!==null ? "<span>".settingshierarchy::$helper->getLang('became_protected').".</span>" : "");
			$lev .= "</li>";
		}
		else{
			$lev = "<li>".settingshierarchy::$helper->getLang("this_is_extended");
			$lev .=	"<span>".settingshierarchy::$helper->getLang('default_is')." <code>{$this->format($root->getDefault($this->_key))}</code>.</span>";
			$lev .= "</li>";
		}
		array_unshift($ret,$lev);
		
		$open = (count($ret) > 1);
		return "<ul class='settings_hierarchy_history'>".implode("\n",$ret)."</ul>";
	}
	
}
} // class_exists