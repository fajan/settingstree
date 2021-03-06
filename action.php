<?php
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

/**
 * Action part of the tag plugin, handles tag display and index updates
 */
class action_plugin_settingstree extends DokuWiki_Action_Plugin {

    public function register(Doku_Event_Handler $controller) {
		$controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this,	'_ajax_call');
	}


    /**
     * Register the events
     *
     * @param $event DOKU event on ajax call
     * @param $param parameters, ignored
     */
	function _ajax_call(&$event, $param) {
		if ($event->data !== 'plugin_settingstree') {
			return;
		}
		//no other ajax call handlers needed
		$event->stopPropagation();
		$event->preventDefault();
		//e.g. access additional request variables
		global $INPUT; //available since release 2012-10-13 "Adora Belle"
		if (!checkSecurityToken()){
			$data = array('error'=>true,'msg'=>'invalid security token!');
		}else{
			switch($INPUT->str('operation')){
				case 'loadlevel':
					if (!($helper = plugin_load('helper', 'settingstree'))){
						$data = array('error'=>true,'msg'=>"Can't load tree helper.");
						break;
					}
					switch ($INPUT->str('showtype','normal')){
						case 'export':
							$data = array(
								'html' => $helper->showExportHtml($INPUT->str('pluginname'),':'.ltrim($INPUT->str('path'),':'),$INPUT->arr('options',array())),
								'path'=> ':'.ltrim($INPUT->str('path'),':'),
							);
							break;
						case 'normal':
						default:
							$data = array(
								'html' => $helper->showHtml($INPUT->str('pluginname'),':'.ltrim($INPUT->str('path'),':')),
								'path'=> ':'.ltrim($INPUT->str('path'),':')
							);
					}
					if (!$data['html']) {$data['error'] = true; $data['msg'] = "Can't load level html.";}
					break;
				case 'show_hierarchy':
					if (!($helper = plugin_load('helper', 'settingstree'))){
						$data = array('error'=>true,'msg'=>"Can't load tree helper.");
						break;
					}
					$data = array('html' => $helper->showHierarchy($INPUT->str('pluginname'),$INPUT->str('key')));
					if (!$data['html']) {$data['error'] = true; $data['msg'] = "Can't load level html.";}
					break;
				case 'savelevel':
					if (!($helper = plugin_load('helper', 'settingstree'))){
						$data = array('error'=>true,'msg'=>"Can't load tree helper.");
						break;
					}
					$html = $helper->saveLevel($INPUT->str('pluginname'),':'.ltrim($INPUT->str('path'),':'),$INPUT->arr('data'),$data);
					$data['html'] = $html;
					
					if (!$data['html']) {$data['error'] = true; $data['msg'] = "Can't load level html.";}
					break;
				case 'exportlevel':
					if (!($helper = plugin_load('helper', 'settingstree'))){
						$data = array('error'=>true,'msg'=>"Can't load tree helper.");
						break;
					}
					$html = $helper->exportLevel($INPUT->str('pluginname'),':'.ltrim($INPUT->str('path'),':'),$INPUT->arr('data'),$data,$INPUT->arr('options',array()));
					$data['html'] = $html;					
					// we expect null for success (export will start with the options) and only need to display the configurations again when there is an error.
					if (!$data['html'] && !$data['success']) {$data['error'] = true; $data['msg'] = "Can't load level html.";}
					break;
				default:
					$data = array('error'=>true,'msg'=>'Unknown operation: '.$INPUT->str('operation'));
					break;
			}
		//data
		//json library of DokuWiki
		}
		if (is_array($data)) $data['token'] = getSecurityToken();
		require_once DOKU_INC . 'inc/JSON.php';
		$json = new JSON();
	 	//set content type
		header('Content-Type: application/json');
		echo $json->encode($data);
//		$this->get_helper()->check_meta_changes();
		
	}	
}