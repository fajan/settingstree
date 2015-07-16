<?php

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

if (!defined('DOKU_SETTINGS_DIR')) define('DOKU_SETTINGS_DIR',DOKU_INC.'data/settings');

require_once('settings/settingshierarchy.class.php');


class helper_plugin_settingstree extends DokuWiki_Plugin {

	private $memcache = false;			// memcache
	private $explorer_helper = null;
	private $explorer_registered = false;



	private $_settingsHierarchy = array();	// settings hierarchy for a plugin
	
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
					'class' => 'settingstree_explorer',
//					'id' => 'mytreeid',
				),
				'callbacks' => array(
//					'page_selected_cb' => array($this,'pageselected'),
					'page_selected_js' => 'settingstree_selectlevel',
//					'ns_selected_cb' => array($this,'nsselected'),
					'ns_selected_js' => 'settingstree_selectlevel',
				),
			));
			$this->explorer_registered = true;
		}
		return $e;
	}
	
	function cache(){
		if ($this->memcache === false){
			$this->memcache = plugin_load('helper','memcache');
			settingshierarchy::$cache = $this->memcahce;
		}
		return $this->memcahce;
	}
	
	
	
    /**
     * Constructor gets default preferences
     *
     * These can be overriden by plugins using this class
     */
    function __construct() {
		// checks if data/settings directory exists, attempts to create it, or error if it's not possible.
		if (!file_exists(DOKU_SETTINGS_DIR)){
			if (!mkdir(DOKU_SETTINGS_DIR)){
				trigger_error("Cannot create settings directory:'".DOKU_SETTINGS_DIR."'!",E_USER_ERROR);
			}
		}elseif(!is_dir(DOKU_INC."data/settings")){
			trigger_error("The '".DOKU_SETTINGS_DIR."' is not a directory!",E_USER_ERROR);
		}
		$this->cache();
		settingshierarchy::$helper = $this;

	}
	
    function getMethods() {
        $result = array();
        return $result;
    }

	function checkSettingsVersion($pluginname,$version){
		if ($this->cache() && $cache_ver = $this->cache()->get("plugin_settringstree_settingsversion_{$pluginname}")){
			return ((int)$cache_ver) < $version;
		}
		return @filemtime(DOKU_SETTINGS_DIR."/{$pluginname}.meta.json") < $version;
	}

	
	function registerSettings($pluginname,$version,$meta,$defaults){
		if (!file_put_contents($file = DOKU_SETTINGS_DIR."/{$pluginname}.meta.json",json_encode($meta))
			||
			!file_put_contents($file = DOKU_SETTINGS_DIR."/{$pluginname}.defaults.json",json_encode($defaults))
		){
			trigger_error("Can not store settings for {$pluginname} to {$file}!",E_USER_ERROR);
		}
		if ($c = $this->cache()){
			$TTL = 0;
			$c->set("plugin_settringstree_settingsversion_{$pluginname}",$version,$TTL);
			$c->set("plugin_settringstree_settingsmeta_{$pluginname}",$meta,$TTL);
			$c->set("plugin_settringstree_settingsdefaults_{$pluginname}",$defaults,$TTL);
		}
	}
	
	private function _loadSettings($pluginname){
		if (!$this->_settingsHierarchy[$pluginname]){
			$c = $this->cache();
			if (!$c || !($meta = $c->get("plugin_settringstree_settingsmeta_{$pluginname}"))){
				$meta = json_decode(@file_get_contents($file = DOKU_SETTINGS_DIR."/{$pluginname}.meta.json"),true);
			}
			if (!is_array($meta)){
				trigger_error("Could not load file: {$file}",E_USER_ERROR);
			}
			if (!$c || !($defaults = $c->get("plugin_settringstree_settingsdefaults_{$pluginname}"))){
				$defaults = json_decode(@file_get_contents($file = DOKU_SETTINGS_DIR."/{$pluginname}.defaults.json"),true);
			}
			if (!is_array($defaults)){
				trigger_error("Could not load file: {$file}",E_USER_ERROR);
			}
			if (!$c || !($values = $c->get("plugin_settringstree_settingsvalues_{$pluginname}"))){
				$values = json_decode(@file_get_contents(DOKU_SETTINGS_DIR."/{$pluginname}.json"),true);
			}
			if (!is_array($values)){ $values = array();	}
			$this->_settingsHierarchy[$pluginname] = new settingshierarchy($pluginname,$meta,$defaults,$values);
		}
		return $this->_settingsHierarchy[$pluginname];
	}
	private function _storeValues($pluginname,settingshierarchy $set){
		$c = $this->cache();
		$values = $set->getValueTree();
		if ($ret = file_put_contents(DOKU_SETTINGS_DIR."/{$pluginname}.json",json_encode($values)) !== false){
			if ($c){	// we don't update cache, if we can't save the values to the filesystem. It would be bad to have correct data until cache is flushed then suddenly something corrupt...
				$TTL = 0;
				$c->set("plugin_settringstree_settingsvalues_{$pluginname}",$values,$TTL);
			}
		}
		return $ret;
	}
	
	function showAdmin($pluginname,$folder){
		$set = $this->_loadSettings($pluginname);
		$e = $this->init_explorertree();
		$ret = $e->htmlExplorer('settingstree',':');
		$ret .= "<form id='settingstree_area' method='GET' onsubmit='return false;'>";
		$level = $set->getLevel($folder);
		$ret .= $level->showHtml();
		$ret .="</form>";
		$ret .= "<script type='text/javascript'>	jQuery('#settingstree_area').settingsTree({$this->_treeOpts($pluginname)});</script>";
		return $ret;
	}
	function saveLevel($pluginname,$folder,$data,&$results){
		$set = $this->_loadSettings($pluginname);
		$level = $set->getLevel($folder);
//		header('content-type','text/html');
		if ($level->checkValues($data) && $this->_storeValues($pluginname,$set)){ // the values are okay, and it managed to save to file/cache
			$results['error'] = false;
			$results['msg'] = $this->getLang('changes_saved');
			$results['success'] = true;
		}else{
			$results['error'] = true;
			$results['msg'] = $this->getLang('changes_not_saved');
			$results['success'] = false;
		}
		return $level->showHtml();
	}
	function showHtml($pluginname,$folder){
		$set = $this->_loadSettings($pluginname);
		$level = $set->getLevel($folder);
//		header('content-type','text/html');
		return $level->showHtml();
	}
	
	
	private function _treeOpts($pluginname){
		return json_encode(array(
			'token'=> getSecurityToken(),
			'pluginname'=> $pluginname,
			
		));
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
