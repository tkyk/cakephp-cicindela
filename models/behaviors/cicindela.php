<?php
/**
 * Cicindela Behavior
 *
 * Copyright (c) Takayuki Miwa <i@tkyk.name>
 * Licensed under the MIT license: http://www.opensource.org/licenses/mit-license.php
 * 
 * == Model
 * <code>
 * class ProductRecommendation extends AppModel {
 *   // use cicindela DataSource.
 *   var $useDbConfig = 'productRecommendation'; 
 * 
 *   var $actsAs = array('Cicindela');
 * }
 * </code>
 * 
 * This behavior provides following methods:
 * 
 * - insert ($data_type, $data)
 * - remove ($data_type, $conditions)
 * - setCategory ($item_id, $category_id)
 * - removeCategory ($item_id, $category_id)
 * - find ('for_item', $conditions)
 * - find ('for_user', $conditions)
 * - find ('similar_users', $conditions)
 *
 * These methods are corresponding to the Cicindela Web API.
 * See http://code.google.com/p/cicindela2/wiki/WebAPI .
 * 
 * You can also use shortcut methods named $data_type
 * such as pick, rating, tag, removePick, removeRating, etc.
 * 
 * Note that Model::save* and Model::delete* cannot be used.
 * 
 */

class CicindelaBehavior extends ModelBehavior
{
  var $_argsMap = array('pick' => array('user_id', 'item_id'),
			'rating' => array('user_id', 'item_id', 'rating'),
			'tag' => array('user_id', 'item_id', 'tag_id'),
			'uninterested' => array('user_id', 'item_id'));

  var $_findTypes = array('for_item' => true,
			  'for_user' => true,
			  'similar_users' => true);

  function __construct()
  {
    parent::__construct();
    $this->mapMethods['/^(?:'.join('|', array_keys($this->_argsMap)) .')$/'] = '_insert';
    $this->mapMethods[$this->__deleteMethodMatcher()] = '_remove';
    $this->mapMethods[$this->__findMethodMatcher()] = '_findCicindela';
  }

  function setup($model, $settings=array())
  {
    $model->_findMethods+= $this->_findTypes;
  }

  function __deleteMethodMatcher()
  {
    $names = array();
    foreach($this->_argsMap as $objName => $_) {
      $names[] = "remove". ucfirst($objName);
    }
    return '/^(?:'. join('|', $names) .')$/';
  }

  function __findMethodMatcher()
  {
    $names = array_map('ucfirst', array_keys($this->_findTypes));
    return '/^_find(?:'. join('|', $names) .')$/';
  }

  function insert($model, $obj, $params)
  {
    return $this->_callSource($model, 'record',
			      "insert_{$obj}", $params);
  }

  function remove($model, $obj, $params)
  {
    return $this->_callSource($model, 'record',
			      "delete_{$obj}", $params);
  }

  function setCategory($model, $item_id, $category_id)
  {
    return $this->_callSource($model, 'record',
			      "set_category", compact('item_id', 'category_id'));
  }

  function removeCategory($model, $item_id, $category_id)
  {
    return $this->_callSource($model, 'record',
			      "remove_category", compact('item_id', 'category_id'));
  }

  function _callSource($model, $method, $op, $params)
  {
    $params['op'] = $op;
    $db = ConnectionManager::getDataSource($model->useDbConfig);
    return $db->{$method}($params);
  }

  function _insert($model, $methodName)
  {
    $args = func_get_args();
    $args = array_slice($args, 2);
    $params = array();
    foreach($this->_argsMap[$methodName] as $i => $paramName) {
      $params[$paramName] = $args[$i];
    }
    return $this->insert($model, $methodName, $params);
  }

  function _remove($model, $methodName)
  {
    $obj = strtolower(str_replace('remove', '', $methodName));

    $args = func_get_args();
    $args = array_slice($args, 2);
    $params = array();
    foreach($this->_argsMap[$obj] as $i => $paramName) {
      $params[$paramName] = $args[$i];
    }
    return $this->remove($model, $obj, $params);
  }

  function _findCicindela($model, $methodName, $state, $query=array(), $results=array())
  {
    $type = substr($methodName, 4);
    if ($state == 'before') {
      $query['op'] = $model->findQueryType;
      return $query;
    }

    if ($state == 'after' &&
	is_array($results) &&
	isset($results[$model->alias])) {
      return $results[$model->alias];
    }
  }

}
