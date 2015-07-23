<?php

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

if (!defined('DOKU_SETTINGS_DIR')) define('DOKU_SETTINGS_DIR',DOKU_INC.'data/settings');

require_once('settings/settingshierarchy.class.php');


class helper_plugin_settingstree extends DokuWiki_Plugin {

	private $memcache = false;			// memcache false: not initialized, null: not present/usable, object: the helper plugin.
	private $explorer_helper = null;	// mandatory dependency, error if dependency is broken.
	private $explorer_registered = false;	// flag to indicate that the callbacks/options are registered to explorertree or not.
	private $_settingsHierarchy = array();	// settings hierarchy for a plugin array(pluginname => hierarchy)
	
	function get_explorer(){
		if (!$this->explorer_helper){
			$this->explorer_helper = plugin_load('helper','explorertree');
			if (!$this->explorer_helper){
				// what is the dokuwiki way to die with fatal plugin errors?
				trigger_error('Explorertree is a dependency but not available!',E_USER_ERROR);
			}
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
				),
				'callbacks' => array(
					'page_selected_js' => 'settingstree_selectlevel',
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
			// we don't want to use cache if it does not give performance upgrade
			if ($this->memcache->emulated()){
				$this->memcache = null;
			}
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
		$result[] = array(
                'name'   => 'checkSettingsVersion',
                'desc'   => 'Checks if a plugin settings require (re)registering settings, by comparing the version in parameter to the currently stored version.',
				'parameters' => array(
					'pluginname' => "string plugin's name that needs to be checked e.g. 'dw2pdf'.",
					'version' => "integer the version of meta/defaults for settings, that is needed (usually timestamp)",
					),
				'return' => 'boolean (stored_version < parameter_version).'
                );
		$result[] = array(
                'name'   => 'registerSettings',
                'desc'   => 'Register config settings for a plugin.',
				'parameters' => array(
					'pluginname' => "string plugin's name that needs to be stored e.g. 'dw2pdf'.",
					'version' => "integer the version of meta/defaults for settings that is going to be registered (usually timestamp)",
					'meta' => "array the settings' metas. Same structure as in 'conf/metadata.php', i.e. array('settingsname' => array('onoff'),'settingname2'=>array('string','_pattern'=>'/^[1-5]x[1-5]$/'))",
					'defaults' => "array the default values. i.e. array('settingsname'=>1,'settingsname2'=>'1x3')",
					),
                );
		$result[] = array(
                'name'   => 'showAdmin',
                'desc'   => 'Returns embeddable html that can be used on an admin page (or any page) ->explorertree + cofiguration area + placeholder for hierarchy area.',
				'parameters' => array(
					'pluginname' => "string plugin's name which's settings are displayed e.g. 'dw2pdf'.",
					'folder' => "string the folder opened by default. You should use ':' (colon) to separate namespaces.",
					),
				'return' => 'string html (echo-able or sendable via ajax)',
                );
		$result[] = array(
                'name'   => 'showHtml',
                'desc'   => 'Returns embeddable html of the configuration area only.',
				'parameters' => array(
					'pluginname' => "string plugin's name which's settings are displayed e.g. 'dw2pdf'.",
					'folder' => "string the folder opened by default. You should use ':' (colon) to separate namespaces.",
					),
				'return' => 'string html (echo-able or sendable via ajax)',
                );
		$result[] = array(
                'name'   => 'showHierarchy',
                'desc'   => 'Returns embeddable html of the hierarchy area only.',
				'parameters' => array(
					'pluginname' => "string plugin's name which's settings are displayed e.g. 'dw2pdf'.",
					'key' => "string the name of the setting e.g. 'pagesize'.",
					),
				'return' => 'string html (echo-able or sendable via ajax)',
                );
		$result[] = array(
                'name'   => 'saveLevel',
                'desc'   => 'Saves/validates changes and returns the updated embeddable html of the configuration area only.',
				'parameters' => array(
					'pluginname' => "string plugin's name which's settings are displayed e.g. 'dw2pdf'.",
					'folder' => "string the folder (level) which's  values are going to be saved. You should use ':' (colon) to separate namespaces.",
					'data' => "array the data to be saved. required structure: array('settingsname'=>array('protect'=>1/0, 'config'=>'newvalue')). Requires only the parameters that are changed!",
					'results' => "OUT array the results of save: array('success' => true/false, 'error' => 'true/false', 'msg' => 'Changes are saved/Changes are not saved (by lang)')",
					),
				'return' => 'string html (echo-able or sendable via ajax)',
                );
		$result[] = array(
                'name'   => 'getConf',
                'desc'   => 'Gets the effective values for the current namespace/page. Only values that are changeable by settingstree are returned (i.e. no ignored settings).',
				'parameters' => array(
					'pluginname' => "string plugin's name which's settings are displayed e.g. 'dw2pdf'.",
					'folder' => "string the folder (level) which's  values are returned. You should use ':' (colon) to separate namespaces.",
					),
				'return' => "array effective values for each key e.g. array('settingsname'=>1, 'settingsname2'=>'1x3')",
                );
		
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
			$TTL = 0;	// DECIDE: push this to config?
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
				$TTL = 0; // TODO: push this to config?
				$c->set("plugin_settringstree_settingsvalues_{$pluginname}",$values,$TTL);
			}
		}
		return $ret;
	}
	function getConf($pluginname,$folder){
		$set = $this->_loadSettings($pluginname);
		$level = $set->getLevel($folder);
		return $level->getAllValues();
	}
	
	
	function showAdmin($pluginname,$folder){
		$set = $this->_loadSettings($pluginname);
		$e = $this->init_explorertree();
		$ret = "";
		$ret .= "<div class='settingstree_left'>";
		$ret .= $e->htmlExplorer('settingstree',':');
		$ret .= "<div class='settingstree_left_column'></div></div>";
		$ret .= "<div class='settingstree_right'><form id='settingstree_area' method='GET' onsubmit='return false;'>";
		$level = $set->getLevel($folder);
		$ret .= $level->showHtml();
		$ret .="</form></div>";
		$ret .= "<script type='text/javascript'>	jQuery('#settingstree_area').settingsTree({$this->_treeOpts($pluginname)});</script>";
		return $ret;
	}
	function saveLevel($pluginname,$folder,$data,&$results){
		$set = $this->_loadSettings($pluginname);
		$level = $set->getLevel($folder);
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
		return $level->showHtml();
	}
	
	function showHierarchy($pluginname,$key){
		$set = $this->_loadSettings($pluginname);
		return $set->showHierarchy($key);
	}
	
	private function _treeOpts($pluginname){
		return json_encode(array(
			'token'=> getSecurityToken(),
			'pluginname'=> $pluginname,
			
		));
	}

	
}
// vim:ts=4:sw=4:et: 
