<?php

if (!class_exists('settingswrapper',false)){
require_once(DOKU_INC.'lib/plugins/config/settings/config.class.php');

class settingswrapper{
	protected $_setting = null;				// the settings_* class form config plugin.
	protected $_key = null;					// the key of the setting
	protected $_level = null;					// the settingslevel object
	public $_value = null;					// the value defined in this level
	public $_protect = false;				// the protection added by this level
	protected $_old_val = null;				// we store the old value here if the value is updated.
	protected $_updated = false;				// flag to indicate if value was updated.
	protected $_changed = false;				// we mark a field for change (e.g. save failed, we want to show field as changed.)
	
	function __construct($key,settingslevel $level, array $meta, $set){
		$this->_key = $key;
		$this->_level = $level;
		$this->_protect = @$set['protect'];
		$this->_value = @$set['value'];
		$this->_initSetting($meta);
	}

	function setProtect($val){
		if (!$val)
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
	
	protected function _initSetting($meta){
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
		if ($this->_changed) return $this->_old_val;
		return $this->_setting->_local === null ? $this->_setting->_default : $this->_setting->_local;
	}
	function markChanged(){
		$this->_changed = true;
	}

	protected function _showHierarchyButton(){
		return "<button class='settingstree_button_show_hierarchy' onclick=\"jQuery(this).trigger('show_in_hierarchy',['{$this->_key}','{$this->_level->path}']);\">".settingshierarchy::$helper->getLang('show_hierarchy')."</button>";
	}
	protected function _showProtectedCell(){
		return "<td class='protect_area' data-currentval='".($this->_protect ? '1' : '0')."'>
				<label for='settingstree_{$this->_key}_protect'>".settingshierarchy::$helper->getLang('protected')."</label>
				<input class='protect_input' type='checkbox' name='protect[{$this->_key}]' id='settingstree_{$this->_key}_protect' value='1' "
				.($this->_protect ? 'checked="checked"' : '')." ".($this->_level->getParentProtected($this->_key) !== null ? "disabled='disabled'":"")."/>
			</td>";
	}
	protected function _showInputUpdateResults(){
		if ($this->_setting->_error){
			return "<div class='error'>".settingshierarchy::$helper->getLang('invalid_value').($this->_setting->_input !== null ? ": <code>{$this->format($this->_setting->_input)}</code>": "!")." </div>";
		}elseif ($this->_updated && !$this->changed){
			return "<div class='info'>".settingshierarchy::$helper->getLang('updated_value_from').": <code>{$this->format($this->_old_val,$default)}</code>". ($default ? " <small>(".settingshierarchy::$helper->getLang('default_value').")</small> ":"")." </div>";
		}

	}
	
	function showHtml(){
		$lang = $this->_level->getHierarchy();

		list($label,$input) = $this->_setting->html($lang);	// html only uses the $plugin as parameter to have a getLang method, we emulate that on hierarchy.
		$cssclass = $this->_setting->is_default() ? ' class="default"' : ($this->_setting->is_protected() ? ' class="protected"' : '');
		$errorclass = ($this->_setting->error() ? ' value error' : ' value'). ($this->_changed ? " changed" : "");
		$has_icon = $this->_setting->caution() ? '<img src="'.DOKU_PLUGIN_IMAGES.$this->_setting->caution().'.png" alt="'.$this->_setting->caution().'" title="'.$lang->getLang($this->_setting->caution()).'" />' : '';


		$ret = "<tr {$cssclass}><td class='label' colspan='2'>";
		$ret .=	"<span class='outkey'>{$this->_setting->_out_key(true,true)}</span>{$has_icon}{$label}";
		// DECIDE: push to plugin debug?
		/*		$ret .= "<code><small>debug: _local = ".var_export($this->_setting->_local ,1).",
		_default = ".var_export($this->_setting->_default ,1).",
		_protected = ".var_export($this->_setting->_protected ,1).",
		_updated: '{$this->_updated}',
		_old_val: ".var_export($this->_old_val ,1)."</small></code><br/>";*/
		$ret .= $this->_showHierarchyButton();
		$ret .= "</td></tr>";
		$ret .= "<tr {$cssclass}>".$this->_showProtectedCell();
		$ret .= "<td class='input_area  {$errorclass}' data-currentval='{$this->currentValue()}'>";
		$ret .= $this->_showInputUpdateResults();
		$ret .= "{$input}</td></tr>";
		return $ret;
	}
	
	function format($value,&$default = null){
		$default = false;
		if ($value === null){ 
			$default = true;
			$value = $this->_level->getDefault($this->_key);
		}
		return $this->_level->getHierarchy()->format($this->_key,$value);
	}
	
	
}

class settingswrapper_export extends settingswrapper{
	protected function _showHierarchyButton(){
		return "";
	}
	protected function _showProtectedCell(){
		return "";
	}
	function showHtml(){
		$lang = $this->_level->getHierarchy();

		list($label,$input) = $this->_setting->html($lang);	// html only uses the $plugin as parameter to have a getLang method, we emulate that on hierarchy.
		$input = strtr($input,array('id="config_'=>'id="export_'));	// we need to replace the id/for for the inputs/labels, as they can be present on pages where config options are available and then they can violate the id-uniqueness constraint of html.
		$label = strtr($label,array('for="config_'=>'for="export_'));
		$cssclass = $this->_setting->is_default() ? ' class="default"' : ($this->_setting->is_protected() ? ' class="protected"' : '');
		$errorclass = ($this->_setting->error() ? ' value error' : ' value'). ($this->_changed ? " changed" : "");
		$has_icon = $this->_setting->caution() ? '<img src="'.DOKU_PLUGIN_IMAGES.$this->_setting->caution().'.png" alt="'.$this->_setting->caution().'" title="'.$lang->getLang($this->_setting->caution()).'" />' : '';


		$ret = "<tr {$cssclass}><td class='label'>";
		$ret .=	"<span class='outkey'>{$this->_setting->_out_key(true,true)}</span>{$has_icon}{$label}";
		$ret .= "</td>";
		$ret .= "<td class='input_area  {$errorclass}' data-currentval='{$this->currentValue()}'>";
		$ret .= $this->_showInputUpdateResults();
		$ret .= "{$input}</td></tr>";
		return $ret;
	}
}
} // class_exists