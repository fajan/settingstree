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
			case 'explorertree_branch':
				if (!($helper = plugin_load('helper', 'explorertree'))){
					$data = array('error'=>true,'msg'=>"Can't load tree helper.");
					break;
				}
				$data = array('html' => $helper->htmlExplorer($INPUT->str('env'),ltrim(':'.$INPUT->str('itemid')),':') );
				if (!$data['html']) {$data['error'] = true; $data['msg'] = "Can't load tree html.";}
				break;
			case 'callback':
				if (!($helper = plugin_load('helper', 'explorertree'))){
					$data = array('error'=>true,'msg'=>"Can't load tree helper.");
					break;
				}
				$route = $helper->loadRoute($INPUT->str('route'),$INPUT->arr('loader'));
				if (!$route || !is_callable(@$route['callbacks'][$INPUT->str(event)])) {
					$data = array('error'=>true,'msg'=>"Can't load callback '".$INPUT->str('event')."'for '".$INPUT->str('route')."'!");
				}
				$data = @call_user_func_array($route['callbacks'][$INPUT->str(event)],array($INPUT->str('itemid')));
				if (!is_array($data)) $data = array('error'=>true,'msg'=>"Callback for '".$INPUT->str('event')."' does not exists!");
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