<?php

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

class helper_plugin_settingstree extends DokuWiki_Plugin {

	private $memcache = false;			// memcache
	
	private $options = array(
	);

	private $_settings = array();		// settings options/definitions
	private $_settingstree = array();	// settings hierarchy for the definitions

	private $explorer_helper = null;
	private $explorer_registered = false;
	
	function get_explorer(){
		if (!$this->explorer_helper){
			$this->explorer_helper = plugin_load('helper','explorertree');
		}
		return $this->explorer_helper;
	}
	function init_explorertree(){
		if (!($e = $this->get_explorer())) return;
		if (!$this->explorer_registered){
			$e->registerRoute('settingstree',array(
				'init_plugin' => array(					// this is the method to register routing, hence this method itself is the 'init_plugin' option.
					'plugin' => 'settingstree',
					'type' => 'helper',
					'method' => 'init_explorertree',
				),
				'vars' => array(
//					'class' => 'explorertree',
//					'id' => 'mytreeid',
				),
				'callbacks' => array(
//					'page_selected_cb' => array($this,'pageselected'),
//					'page_selected_js' => 'my_selected_js',
//					'ns_selected_cb' => array($this,'nsselected'),
//					'ns_selected_js' => 'my_selected_js',
				),
			));
			$this->explorer_registered = true;
		}
		return $e;
	}
	
	function cache(){
		if ($this->memcache === false){
			$this->memcache = plugin_load('helper','memcache');
		}
		return $this->memcahce;
	}
	
	private function _getSettingsOptions($name){
		if (!@$this->_settings[$name]){
			if (!file_exists(DOKU_INC."data/settings")){
				if (!mkdir(DOKU_INC."data/settings")){
					trigger_error("Cannot create settings directory:'".DOKU_INC."data/settings'!",E_USER_ERROR);
				}
			}elseif(!is_dir(DOKU_INC."data/settings")){
					trigger_error("The '".DOKU_INC."data/settings' is not a directory!",E_USER_ERROR);
			}
			$name_esc = preg_replace_callback('/(\W)/',
				function($m){ return "-".ord($m[1])."-"; },
				$name);

			if (file_exists(DOKU_INC."data/settings/{$name_esc}.json")){
				if ($json = file_get_contents(DOKU_INC."data/settings/{$name_esc}.json")){
					if ($data = json_decode($json,true)){
						$this->_settings[$name] = $data;
					}
				}
			}
		}
		return $this->_settings[$name];
	}
	
	
	
    /**
     * Constructor gets default preferences
     *
     * These can be overriden by plugins using this class
     */
    function __construct() {
	}
	
    function getMethods() {
        $result = array();
        return $result;
    }

	function getlocal($key,$pluginname){
		if (!$this->localconfig){
			$conf = array();
			require (DOKU_INC."conf/local.php");
			$this->localconfig = $conf;
		}
		return @$this->localconfig['plugin'][$pluginname][$key];
	}
	function getprotected($key,$pluginname){
		if (!$this->protectedconfig){
			$conf = array();
			require (DOKU_INC."conf/local.protected.php");
			$this->protectedconfig = $conf;
		}
		return @$this->protectedconfig['plugin'][$pluginname][$key];
	}
	
	function registerSettingsOptions($pluginname,$options){
		$this->_getSettingsOptions($pluginname);
		require_once(DOKU_INC.'lib/plugins/config/settings/config.class.php');
		$meta = array(); $conf = array();
		require (DOKU_INC."lib/plugins/{$pluginname}/conf/default.php");
		require (DOKU_INC."lib/plugins/{$pluginname}/conf/metadata.php");
		$this->settings = array();
		foreach ($meta as $key => $met){
			$class = $meta[$key][0];
			if($class && class_exists('setting_'.$class)){
				$class = 'setting_'.$class;
			} else {
				
			}
			$param = $met;
			array_shift($param);
			if ($class){
				$this->settings[$key] = new $class($key,$param);
				$this->settings[$key]->initialize(@$conf[$key],@$this->getlocal($key,$pluginname),@$this->getprotected($key,$pluginname));
			}
		}
	}
	
	
	function showDialog($pluginname,$folder){
		$e = $this->init_explorertree();
		$ret = $e->htmlExplorer('settingstree',':');
		$ret .= "<div id='config__manager'><fieldset><legend>BlaBla</legend><div class='table'><table class='inclide'><tbody>";
		foreach ($this->settings as $key => $settings){
			list($label,$input) = $this->settings[$key]->html($this);
			$cssclass = $this->settings[$key]->is_default() ? ' class="default"' : ($this->settings[$key]->is_protected() ? ' class="protected"' : '');
			$has_error = $this->settings[$key]->error() ? ' class="value error"' : ' class="value"';
            $has_icon = $this->settings[$key]->caution() ? '<img src="'.DOKU_PLUGIN_IMAGES.$this->settings[$key]->caution().'.png" alt="'.$this->settings[$key]->caution().'" title="'.$this->getLang($this->settings[$key]->caution()).'" />' : '';
			$ret .= "<tr {$cssclass}><td class='label'><span class='outkey'>{$this->settings[$key]->_out_key(true,true)}</span>{$has_icon}{$label}</td><td {$has_error}>{$input}</td></tr>";
		}
		$ret .= "</tbody></table></div></fieldset></div>";
		return $ret;
	}
	
/*	function registerRoute($name,array $options){
		$this->routes[$name] = array_replace_recursive ($this->options,$options);
	}
	function getOptions($name = null){
		if (!$name) return $this->options;
		return @$this->routes[$name][$options];
	}

	function loadRoute($name,array $reg = null){
		if (!$name) return $this->options;
		if ((! @$this->routes[$name]) && $reg){
			if (($p = plugin_load($reg['type'],$reg['plugin'])) && $met = $reg['method']){
				call_user_func(array($p,$met),array());
			}
		}
		return @$this->routes[$name];
	}
*/
	
}
// vim:ts=4:sw=4:et: 
