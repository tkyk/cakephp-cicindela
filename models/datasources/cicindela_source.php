<?php
/**
 * Cicindela DataSource
 * 
 * Copyright (c) Takayuki Miwa <i@tkyk.name>
 * Licensed under the MIT license: http://www.opensource.org/licenses/mit-license.php
 * 
 */

App::import('Core', 'HttpSocket');

class CicindelaSource extends DataSource
{
  var $_baseConfig = array('host' => 'localhost',
			   'path' => '/cicindela/',
			   'set' => "");

  var $_recommendQueryParams = array('op', 'item_id',
					'user_id', 'category_id',
					'limit');

  var $_baseUrl = null;
  var $_queriesLog = array();

  /**
   * If you want to support these features,
   * turn them on explicitly.
   * 
   * @var array
   */
  var $_features = array('listSources' => false,
			 'describe'    => false);

  /**
   * @override
   */
  function isInterfaceSupported($interface)
  {
    switch(true) {
    case isset($this->_features[$interface]):
      return $this->_features[$interface];
    default:
      return parent::isInterfaceSupported($interface); 
    }
  }

  /**
   * Constructor
   */
  function __construct($config=array())
  {
    $this->debug     = Configure::read('debug') > 0;
    $this->fullDebug = Configure::read('debug') > 1;

    parent::__construct($config);
    $this->connect();
  }

  function connect()
  {
    $this->_baseUrl = "http://". $this->config['host'] . $this->config['path'];
    $this->connected = true;
  }

  function close()
  {
    if($this->fullDebug) {
      $this->showLog();
    }
  }

  function read(&$model, $query = array(), $recursive = null)
  {
    $params = array();
    foreach($this->_recommendQueryParams as $key) {
      if(!empty($query[$key])) {
	$params[$key] = $query[$key];
      }
    }
    $values = $this->recommend($params);
    return is_array($values) ? array($model->alias => $values) : $values;
  }

  function _buildUrl($type)
  {
    return $this->_baseUrl . $type ."?set=". $this->config['set'];
  }

  function record($params)
  {
    $url  = $this->_buildUrl("record");
    $http = $this->_httpGet($url, $params);
    return $http->response['status']['code'] == 204;    
  }

  function recommend($params)
  {
    $url  = $this->_buildUrl("recommend");
    $http = $this->_httpGet($url, $params);
    $body = $http->response['body'];

    if($http->response['status']['code'] == 200) {
      return preg_split('/(\r\n|\n)/', $body, -1,  PREG_SPLIT_NO_EMPTY);
    }
    return false;
  }

  function _httpGet($url, $params)
  {
    $http = new HttpSocket();
    $t = getMicrotime();
    $http->get($url, $params);
    $took = round((getMicrotime() - $t) * 1000, 0);

    if($this->fullDebug) {
      $this->logQuery($http, $took);
    }
    return $http;
  }

  function logQuery($http, $took)
  {
    $this->_queriesLog[] = array('url' => $http->buildUri($http->request['uri']),
				 'status' => $http->response['raw']['status-line'],
				 'took' => $took);
  }

  function showLog()
  {
    if (PHP_SAPI != 'cli') {
      $tableId = "cakeRequestLog_" . preg_replace('/[^A-Za-z0-9_]/', '_', uniqid(time(), true));
      printf('<table class="cake-sql-log" id="%s" cellspacing="0" border= "0">', h($tableId));
      printf('<caption>(%s) %s request(s)</caption>', h($this->configKeyName), h(count($this->_queriesLog)));
      print ("<thead>\n<tr><th>Nr</th><th>URL</th><th>Status</th><th>Took (ms)</th></tr>\n</thead>\n<tbody>\n");
      foreach ($this->_queriesLog as $i => $data) {
	printf('<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td></tr>',
	       $i+1, $data['url'], $data['status'], $data['took']);
      }
      print ("</tbody></table>\n");
    } else {
      foreach ($this->_queriesLog as $i => $data) {
	print (($i + 1) . ". {$data['url']} {$data['status']}\n");
      }
    }
  }

}
