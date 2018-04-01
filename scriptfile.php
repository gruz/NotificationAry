<?php
/**
 * The installer script which installs languages and performs migrating
 *
 * @package		NotificationAry
 * @subpackage	NotificationAry.Script
 * @author Gruz <arygroup@gmail.com>
 * @copyright	Copyleft - All rights reversed
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
defined('_JEXEC') or die;

if (!class_exists('ScriptAry'))
{
	include dirname(__FILE__) . '/scriptary.php';
}

class plgSystemNotificationaryInstallerScript extends ScriptAry
{
	/**
	 * method to uninstall the component
	 *
	 * @return void
	 */
	function uninstall($parent)
	{
		// $parent is the class calling this method
		echo '<p>' . JText::_('You may wish to uninstall GJFields library used together with this extension. Other extensions may also use GJFields. If you uninstall GJFields by mistake, you can always reinstall it.') . '</p>';
	}

	/**
	 * method to update the component
	 *
	 * @return void
	 */
	function update($parent)
	{
		// $parent is the class calling this method
		$manifest = $parent->getParent()->getManifest();

		if (!$this->_installAllowed($manifest))
		{
			return false;
		}

		$this->_updateParams($manifest);

		if (!empty($this->updateMessages))
		{
			echo '<div class="alert alert-message"><h3><span class="icon-notification" style="color:red;"></span> '
				. JText::_('Some parameters were changed') . '</h3>';
				echo '<ul><li>' . implode('</li><li>', $this->updateMessages) . '</li></ul>';
			echo '</div>';
		}
	}

	/**
	 * method to run before an install/update/uninstall method
	 *
	 * @return void
	 */
	function preflight($type, $parent)
	{
		if (!parent::preflight($type, $parent))
		{
			return false;
		}

		$db = JFactory::getDbo();

		// Remove NAS langpacks
		$query = $db->getQuery(true);
		$query->select(array('extension_id', 'name'));
		$query->from('#__extensions');
		$query->where($db->quoteName('name') . ' LIKE ' . $db->quote('%_notifyarticlesubmit_language_pack'));
		$db->setQuery($query);

		$rows = $db->loadAssocList();

		foreach ($rows as $row)
		{
			$installer = new JInstaller();
			$res = $installer->uninstall('file', $row['extension_id']);

			if ($res)
			{
				$msg = '<b style="color:green">' . JText::sprintf('COM_INSTALLER_UNINSTALL_SUCCESS', $row['name']) . '</b>';
			}
			else
			{
				$msg = '<b style="color:red">' . JText::sprintf('COM_INSTALLER_UNINSTALL_ERROR', $row['name']) . '</b>';
			}

			$this->messages[] = $msg;
		}

		// *** Remove NotifyArticleSubmit updates as new NotificationAry should be used
		$query = $db->getQuery(true);
		$conditions = array(
			 $db->quoteName('element') . ' LIKE ' . $db->quote('%notifyarticlesubmit%')
		);

		$table_name = '#__updates';
		$query->delete($db->quoteName($table_name));
		$query->where($conditions);
		$db->setQuery($query);
		$res = $db->execute();

		if ($res)
		{
			$msg = '<b style="color:green">' . $table_name . ' ' . JText::sprintf('JGLOBAL_HELPREFRESH_BUTTON') . ' OK </b>';
		}
		else
		{
			$msg = '<b style="color:red">' . $table_name . ' ' . JText::sprintf('JGLOBAL_HELPREFRESH_BUTTON') . ' ' . JText::_('ERROR') . '  </b>';
		}

		// *** Remove NotifyArticleSubmit servers as new NotificationAry should be used
		// Select update_site_ids as they have to be removed from 2 tables later
		// Reset the query using our newly populated query object.
		$query = $db->getQuery(true);
		$query->select(array('update_site_id'));
		$query->from('#__update_sites');
		$query->where($db->quoteName('name') . ' LIKE ' . $db->quote('%notifyarticlesubmit%'));
		$db->setQuery($query);

		// Load the results as a list of stdClass objects.
		$results = $db->loadColumn();

		if (!empty($results))
		{
			$query = $db->getQuery(true);
			$conditions = array(
				 $db->quoteName('update_site_id') . ' IN (' . implode(',',$results).' )'
			);
			$table_name = '#__update_sites';
			$query->delete($db->quoteName($table_name));
			$query->where($conditions);
			$db->setQuery($query);
			$res = $db->execute();
			if ($res)
			{
				$msg = '<b style="color:green">' . $table_name . ' ' . JText::sprintf('JGLOBAL_HELPREFRESH_BUTTON') . ' OK </b>';
			}
			else
			{
				$msg = '<b style="color:red">' . $table_name . ' ' . JText::sprintf('JGLOBAL_HELPREFRESH_BUTTON') . ' ' . JText::_('ERROR') . '  </b>';
			}

			$query = $db->getQuery(true);
			$conditions = array(
				 $db->quoteName('update_site_id') . ' IN (' . implode(',',$results).' )'
			);
			$table_name = '#__update_sites_extensions';
			$query->delete($db->quoteName($table_name));
			$query->where($conditions);
			$db->setQuery($query);
			$res = $db->execute();

			if ($res)
			{
				$msg = '<b style="color:green">' . $table_name . ' ' . JText::sprintf('JGLOBAL_HELPREFRESH_BUTTON') . ' OK </b>';
			}
			else
			{
				$msg = '<b style="color:red">' . $table_name . ' ' . JText::sprintf('JGLOBAL_HELPREFRESH_BUTTON') . ' ' . JText::_('ERROR') . '  </b>';
			}
		}

		if (!empty($this->messages))
		{
			echo '<ul><li>' . implode('</li><li>', $this->messages) . '</li></ul>';
		}

		// $parent is the class calling this method
		// $type is the type of change (install, update or discover_install)

		// echo '<p>' . JText::_('COM_HELLOWORLD_PREFLIGHT_' . $type . '_TEXT') . '</p>';
	}

	/**
	 * method to run after an install/update/uninstall method
	 *
	 * @return void
	 */
	function postflight( $type, $parent )
	{
		$manifest = $parent->getParent()->getManifest();

		if ($type != 'uninstall' && !$this->_installAllowed($manifest))
		{
			return false;
		}

		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.file');


		$db = JFactory::getDbo();

		// Select NAS parameters and store them to newly installed NA parameters. Uninstall NAS
		// Select NotifyArticleSubmit params
		$query = $db->getQuery(true);
		$query->select(array('extension_id', 'name', 'params', 'element'));
		$query->from('#__extensions');
		$query->where($db->quoteName('element') . ' = ' . $db->quote('notifyarticlesubmit'));
		$query->where($db->quoteName('folder') . ' = ' . $db->quote('content'));
		$db->setQuery($query);
		$row = $db->loadAssoc();

		if (!empty($row))
		{
			$query = $db->getQuery(true);
			$fields = array(
				$db->quoteName('params') . '=' . $db->Quote($row['params']),
			);
			$conditions = array(
				$db->quoteName('folder') . '=' . $db->Quote($this->plgType),
				$db->quoteName('element') . '=' . $db->Quote($this->plgName),
			);
			$query->update($db->quoteName('#__extensions'))->set($fields)->where($conditions);
			$db->setQuery($query);

			$result = $db->execute();

			$getAffectedRows = $db->getAffectedRows();
			$msg = '';

			if($getAffectedRows>0)
			{
				$msg = '<b style="color:green">' . JText::sprintf('COM_INSTALLER_MSG_UPDATE_SUCCESS', JText::_($this->plgFullName)) . '</b>';
			}
			else
			{
				$msg = '<b style="color:red">' . JText::sprintf('COM_INSTALLER_MSG_UPDATE_ERROR', JText::_($this->plgName)) . '</b>';
			}

			$this->messages[] = $msg;

			$msg = '';

			$installer = new JInstaller();
			$res = '';

			$res = $installer->uninstall('plugin',$row['extension_id']);

			if ($res)
			{
				$msg = '<b style="color:green">' . JText::sprintf('COM_INSTALLER_UNINSTALL_SUCCESS', $row['element']) . '</b>';
			}
			else
			{
				$msg = '<b style="color:red">' . JText::sprintf('COM_INSTALLER_UNINSTALL_ERROR', $row['element']) . '</b>';
			}

			$this->messages[] = $msg;
		}

		// Remove AjaxHelpAry
		$query = $db->getQuery(true);
		$query->select(array('extension_id', 'name', 'params', 'element'));
		$query->from('#__extensions');
		$query->where($db->quoteName('element') . ' = ' . $db->quote('ajaxhelpary'));
		$query->where($db->quoteName('folder') . ' = ' . $db->quote('ajax'));
		$db->setQuery($query);
		$row = $db->loadAssoc();

		if (!empty($row))
		{
			$installer = new JInstaller();
			$res = $installer->uninstall('plugin', $row['extension_id']);

			if ($res)
			{
				$msg = '<b style="color:green">' . JText::sprintf('COM_INSTALLER_UNINSTALL_SUCCESS', $row['name']) . '</b>';
			}
			else
			{
				$msg = '<b style="color:red">' . JText::sprintf('COM_INSTALLER_UNINSTALL_ERROR', $row['name']) . '</b>';
			}

			$this->messages[] = $msg;
		}


		if ($type == 'install') {
			$this->_prepareDefaultParams($manifest);
		}

		parent::postflight($type, $parent, $publishPlugin = true);

	}

	private function _prepareDefaultParams ($manifest, $groupname = 'notificationgroup')
	{
$debug = true;
$debug = false;
		// Get extension table class
		$extensionTable = JTable::getInstance('extension');
		// Find plugin id, in my case it was plg_ajax_ajaxhelpary
		$pluginId = $extensionTable->find( array('element' => $this->ext_name, 'type' => 'plugin') );

		$extensionTable->load($pluginId);

		// Get joomla default object
		$params = new \JRegistry;
		$params_new = new \JRegistry;
		$params->loadString($extensionTable->params, 'JSON'); // Load my plugin params.

		// The parameters in the DB are stored as a one-dimensional array
		// This may be either after a fresh install or when upgrading from a fresh install.
		// This is the case, when the plugin was not saved before.
		$groupOfRules = $params->get('{'.$groupname);
//~ dump ($groupOfRules,'$groupOfRules');
if ($debug) {
echo '<pre> Line: '.__LINE__.' '.PHP_EOL;
print_r($params);
echo PHP_EOL.'</pre>'.PHP_EOL;
}
//~ dump ($params->toObject(),'$params');
		if (empty($groupOfRules) || is_string($groupOfRules)) {
			$inside_group = false;
			$notificationgroup = new stdClass;
			foreach ($manifest->xpath('//fieldset/field') as $field) {
				$fname = (string) $field['name'];
				if ($fname == '{'.$groupname ) {
					$inside_group = true;
				} elseif ($fname == $groupname.'}' ) {
					$inside_group = false;
					continue;
				} elseif (substr($fname,0,1) == '{' || substr($fname, -1) == '}' ) {
					continue;
				}


				if ($inside_group) {
					//~ $notificationgroup->$fname = $notif_val;
					if ($fname == '{'.$groupname ) {
						$notificationgroup->$fname = array($params->get ($fname),'1','variablefield::{notificationgroup');

					} else {
						if ($fname == 'ausers_notifyusergroupsselection') {
							$notificationgroup->$fname = array($params->get ($fname,8),'variablefield::{notificationgroup');//Force superadmin group by default
						}
						else {
							$notificationgroup->$fname = array($params->get ($fname,(string)$field['default']),'variablefield::{notificationgroup');
						}
					}
					//~ echo '<span style="color:red">'.$field['name'] . '</span><br>';
				} else {
					$params_new->set($fname,$params->get ($fname,(string)$field['default']));
					//~ echo $field['name'] . '<br>';
				}
			}
			$notificationgroup->__ruleUniqID[] = uniqid();
			$notificationgroup->__ruleUniqID[] = 'variablefield::{notificationgroup';
			$params_new->set('{'.$groupname,$notificationgroup);
//~ echo '<pre> Line: '.__LINE__.' '.PHP_EOL;
//~ print_r($params_new);
//~ echo PHP_EOL.'</pre>'.PHP_EOL;
		}
		else {
			$countOfGroups = count($groupOfRules->{'{'.$groupname})/3;
//~ dump ($countOfGroups,'$countOfGroups');
			$inside_group = false;
			$notificationgroup = new stdClass;
			foreach ($manifest->xpath('//fieldset/field') as $field) {
				$fname = (string) $field['name'];
				if ($fname == '{'.$groupname ) {
					$inside_group = true;
				} elseif ($fname == $groupname.'}' ) {
					$inside_group = false;
					continue;
				} elseif (substr($fname,0,1) == '{' || substr($fname, -1) == '}' ) {
					continue;
				}
				if ($inside_group) {
//~ dump ($params->get ($fname),$fname);
					//~ $notificationgroup->$fname = $notif_val;
					//~ if ($fname == '{'.$groupname ) {
						//~ if (isset($groupOfRules->$fname)) {
							//~ $notificationgroup->$fname = $groupOfRules->$fname;
						//~ }
						//~ else {
							//~ $notificationgroup->$fname = (array((string)$field['default'],'0','variablefield::{notificationgroup'));
						//~ }
					//~ } else {
					//~ }
					if (isset($groupOfRules->$fname)) {
						$notificationgroup->$fname = $groupOfRules->$fname;
					}
					else {
						$notificationgroup->$fname = array();
						for ($i = 0; $i < $countOfGroups; $i++) {
							$notificationgroup->$fname = array_merge($notificationgroup->$fname,array((string)$field['default'],'variablefield::{notificationgroup'));
						}
					}
					//~ echo '<span style="color:blue">'.$field['name'] . '</span><br>';
				} else {
					$params_new->set($fname,$params->get ($fname));
					//~ echo $field['name'] . '<br>';
				}
			}
//~ dump ($notificationgroup,'$notificationgroup');
			if (isset($groupOfRules->__ruleUniqID)) {
				$notificationgroup->__ruleUniqID = $groupOfRules->__ruleUniqID;
			} else {
				$notificationgroup->__ruleUniqID[] = uniqid();
				$notificationgroup->__ruleUniqID[] = 'variablefield::{notificationgroup';
			}
			$params_new->set('{'.$groupname,$notificationgroup);
		}
if ($debug) {
echo '<pre> Line: '.__LINE__.' '.PHP_EOL;
print_r($params_new);
echo PHP_EOL.'</pre>'.PHP_EOL;
exit;
}
//~ dump ($params_new->toObject(),'$params_new');


		$params = $params_new;
		unset($params_new);

		$extensionTable->bind( array('params' => $params->toString()) ); // Bind to extension table

		// check and store
		if (!$extensionTable->check() || !$extensionTable->store()) {
			$msg = '<b style="color:red">' . JText::sprintf('COM_INSTALLER_MSG_UPDATE_ERROR',JText::_($this->ext_full_name)) . '</b>';
		}
		else {
			$msg = '<b style="color:green">' . JText::sprintf('COM_INSTALLER_MSG_UPDATE_SUCCESS',JText::_($this->ext_full_name)) . '</b>';
		}
		$this->messages[] = $msg;

	}

	private function _updateParams ($manifest, $groupname = 'notificationgroup')
	{
		if(!class_exists('paramsHelper'))
		{
			include __DIR__.'/Helpers/paramsHelper.php';
		}

		$helper = new paramsHelper($element = 'notificationary', $type = 'plugin', $groupname, $manifest);

		$status_action_to_notify_FLAG = false;

		foreach ($helper->groups as $groupIndex => $group)
		{
			foreach ($group as $k => $arrayValues)
			{
				if ($k == 'states_to_notify')
				{
					$status_action_to_notify_FLAG = true;

					foreach ($arrayValues as $j => $l)
					{
						switch ($l[0])
						{
							case 'all':
								$helper->groups[$groupIndex]['status_action_to_notify'][] = (array) 'always';
								break;
							default :
								$helper->groups[$groupIndex]['status_action_to_notify'][] = (array) $l;
								break;
						}
					}

					unset($helper->groups[$groupIndex]['states_to_notify']);
				}

				if ($k == 'ausers_notifyonaction')
				{
					$status_action_to_notify_FLAG = true;

					foreach ($arrayValues as $j => $l)
					{
						switch ($l)
						{
							case '1':
								$helper->groups[$groupIndex]['status_action_to_notify'][] = (array) 'publish';
								break;
							case '2':
								$helper->groups[$groupIndex]['status_action_to_notify'][] = (array) 'unpublish';
								break;
							case '5':
								$helper->groups[$groupIndex]['status_action_to_notify'][] = (array) 'always';
								break;
							case '6':
								$helper->groups[$groupIndex]['status_action_to_notify'][] = (array) 'publish';
								$helper->groups[$groupIndex]['status_action_to_notify'][] = (array) 'unpublish';
								break;
							case '3':
								$helper->groups[$groupIndex]['status_action_to_notify'][] = (array) '1';
								break;
							case '4':
								$helper->groups[$groupIndex]['status_action_to_notify'][] = (array) '0';
								break;
						}
					}

					unset($helper->groups[$groupIndex]['ausers_notifyonaction']);
				}
			}
		}

		if ($status_action_to_notify_FLAG)
		{
			$this->updateMessages[] = 'Please check/update options for <b>"' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_FIELD_STATUS_ACTION_TO_NOTIFY') . '"</b>.
				<img width="545" src="http://static.xscreenshot.com/2016/05/12/03/screen_110d9b0551d56aff16ef7b970b2cc672" style="border-radius:5px;">
			 ';
		}
/*
echo '<hr/>';
$right = 75;
foreach ($helper->groups as $k=>$v) {
	echo '<pre style="float:right;width:25%;margin:0;background:#ffefef;position: absolute; top:0;right: '.$right.'%"> Line: '.__LINE__.' '.PHP_EOL;

	echo PHP_EOL.'</pre>'.PHP_EOL;
	$right = $right-25;
}
exit;
*/

		$helper->save();
	}
}
