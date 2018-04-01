<?php
/**
 *
 * @author Gruz <arygroup@gmail.com>
 * @copyright	Copyleft - All rights reversed
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');


class paramsHelper
{
	function __construct($element, $type, $groupname, $manifest)
	{
		$this->manifest = $manifest;
		$this->groupname = $groupname;

		// Get extension table class
		$this->extensionTable = JTable::getInstance('extension');

		// Find plugin id, in my case it was plg_ajax_ajaxhelpary
		$this->pluginId = $this->extensionTable->find( array('element' => $element, 'type' => $type) );
		$this->extensionTable->load($this->pluginId);

		// Get joomla default object
		$this->params = new \JRegistry;
		$this->params->loadString($this->extensionTable->params, 'JSON'); // Load my plugin params.
		// $this->params already contains this and is the same array

		//$this->groups = $this->params->get($groupname);
		$this->parseGroups();

		return;
	}

	private function parseGroups()
	{
$debug = true;
$debug = false;
		$groupOfRules = $this->params->get('{'.$this->groupname);

		if (empty($groupOfRules) || is_string($groupOfRules)) {
			return;
		}
		$countOfGroups = count($groupOfRules->{'{'.$this->groupname})/3;

		$this->groups = array();

if($debug)
{
	echo '<pre style="float:right;width:25%;margin:0;background:#efffef;position: absolute; top:0;right: 0%"> Line: '.__LINE__.'  BEFORE UPDATES'.PHP_EOL;
	var_dump($groupOfRules);
	echo PHP_EOL.'</pre>'.PHP_EOL;
}
		foreach ($groupOfRules as $fieldName=>$valuesArray) {
			$counter = 0;
			foreach ($valuesArray as $i=>$val) {
				if (is_string($val)) {
					if ($val == 'variablefield::{'.$this->groupname ){
						$counter++;
					} else {
						$this->groups[$counter][$fieldName][] = $val;
					}
				}
				if (is_array($val)) {
					if ($val[0] == 'variablefield::{'.$this->groupname ){
						$counter++;
					} else {
						$this->groups[$counter][$fieldName][] = $val;
					}
				}

			}


		}
if($debug) {
	$right = 75;
	foreach ($this->groups as $k=>$v) {
		echo '<pre style="float:right;width:25%;margin:0;background:#efefff;position: absolute; top:0;right: '.$right.'%"> Line: '.__LINE__.' '.PHP_EOL;
		var_dump($v);
		echo PHP_EOL.'</pre>'.PHP_EOL;
		$right = $right-25;
	}
//exit;
}

	}
	private function unParseGroups() {
		$combinedGroup = new stdClass;
		foreach ($this->groups as $numOfGroup=>$group) {
			foreach ($group as $fieldName=>$arrayValues) {
				if (!isset($combinedGroup->$fieldName)) {
					$combinedGroup->$fieldName = array();
				}
				if (is_string($arrayValues[0])) {
					$arrayValues[] = 'variablefield::{'.$this->groupname;
					$combinedGroup->{$fieldName} = array_merge($combinedGroup->{$fieldName},$arrayValues);
				}
				if (is_array($arrayValues[0])) {
					$arrayValues[] =	(array)('variablefield::{'.$this->groupname);
					$combinedGroup->{$fieldName} = array_merge($combinedGroup->{$fieldName},$arrayValues);
				}
			}
		}
		$this->groups = $combinedGroup;
//~ echo '<pre style="z-index:10000;position:absolute;"> Line: '.__LINE__.' '.PHP_EOL;
//~ print_r($this->groups);
//~ echo PHP_EOL.'</pre>'.PHP_EOL;
//exit;
	}
	function save() {
		$this->unParseGroups();

		$this->params->set('{'.$this->groupname,$this->groups); // Set to parameters
		$this->extensionTable->bind( array('params' => $this->params->toString()) ); // Bind to extension table

		// check and store
		if (!$this->extensionTable->check()) {
			 $this->setError($this->extensionTable->getError());
			 //~ return false;
		}
		if (!$this->extensionTable->store()) {
			 $this->setError($this->extensionTable->getError());
			 //~ return false;
		}

	}
}
