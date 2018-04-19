<?php
/**
 * Check
 *
 * @package     NotificationAry
 *
 * @author      Gruz <arygroup@gmail.com>
 * @copyright   Copyleft (є) 2018 - All rights reversed
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */


namespace NotificationAry\Traits;

/**
 * A helper trait
 *
 * @since 0.2.17
 */
trait Check
{

			/**
	 * Checks all rules and returns only compatible with current contenItem
	 * Doesn't check some options which are only known onAfterContentSave
	 *
	 * @param   string  $context      Context
	 * @param   object  $contentItem  Content item object
	 * @param   string  $task         Description
	 * @param   bool    $isNew        isNew flag
	 *
	 * @return   type  Description
	 */
	public function leaveOnlyRulesForCurrentItem($context, $contentItem, $task, $isNew = false)
	{
		$this->task = $task;

		$debug = true;
		$debug = false;

		if ($debug)
		{
			dumpMessage('<b>' . __FUNCTION__ . '</b> . | Task : ' . $this->task . ' | isNew ' . $isNew);
		}

		// ~ static $rules = array('switch'=>array(),'content'=>array());
		static $rules = array();

		if (!empty($rules[$task]))
		{
			return $rules[$task];
		}

		if (empty($contentItem->id) && $task == 'showSwitch')
		{
			$isNew = true;
		}

		if ($debug)
		{
			dump($contentItem, '$contentItem');
		}

		foreach ($this->pparams as $ruleNumber => $rule)
		{
			// Pass rule to checkAllowed
			$this->rule = $rule;

			if ($debug)
			{
				dump($rule, '$rule ' . $ruleNumber);
			}

			if ($task == 'saveItem')
			{
				$this->_debug('Checking rule <b>' . $rule->{'{notificationgroup'}[0] . '</b>', false, $rule);
			}

			// Not our context
			if ($rule->context != $context )
			{
				if ($task == 'saveItem')
				{
					$this->_debug('Context wrong. Rule: <b>' . $rule->context . '</b>=<b>' . $context . '</b> content. CHECK FAILED');
				}

				continue;
			}

			if ($task == 'saveItem')
			{
				$this->_debug('Context check  PASSED');
			}

			if ($debug)
			{
				dumpMessage('here 1 ');
			}

			if ($rule->ausers_notifyon == 1 && !$isNew)
			{
				if ($task == 'saveItem')
				{
					$this->_debug('Only new allowed but content is not new. CHECK FAILED');
				}

				continue;
			}

			if ($debug)
			{
				dumpMessage('here 2');
			}

			if ($task == 'saveItem')
			{
				$this->_debug('Only new allowed and is New?  PASSED');
			}

			if ($rule->ausers_notifyon == 2 && $isNew)
			{
				if ($task == 'saveItem')
				{
					$this->_debug('Only update is allowed but content is new. CHECK FAILED');
				}

				continue;
			}

			if ($debug)
			{
				dumpMessage('here 3');
			}

			if ($task == 'saveItem')
			{
				$this->_debug('Only update allowed and isn\'t new?  PASSED');
			}

			$user = \JFactory::getUser();

			if ($task == 'saveItem')
			{
				$this->_debug('User allowed?   START CHECK');
			}

			// Check if allowed notifications for actions performed by this user
			if (!$this->checkAllowed($user, $paramName = 'allowuser'))
			{
				if ($task == 'saveItem')
				{
					$this->_debug('User is not allowed to send notifications. CHECK FAILED');
				}

				continue;
			}

			if ($debug)
			{
				dumpMessage('here 4');
			}

			if ($task == 'saveItem')
			{
				$this->_debug('User allowed?   PASSED');
			}

			if ($task == 'showSwitch')
			{
				if (!$rule->shownotificationswitch)
				{
					continue;
				}

				if ($debug)
				{
					dumpMessage('here 5');
				}

				$app = \JFactory::getApplication();

				if (!$app->isAdmin() && !$rule->notificationswitchfrontend)
				{
					continue;
				}

				if ($debug)
				{
					dumpMessage('here 6');
				}

				// I assume that notification swicth should be shown for all categories as we may start editing in a non-selected category,
				// but save an item, to a selected category. We must allow the user to select wether to switch

				/*
				if (!$isNew) {
					if (!$this->checkAllowed($contentItem, $paramName = 'article')) { continue; }
				}
				*/

				// Check if the user is allowed to show the switch
				if (!$this->checkAllowed($user, $paramName = 'allowswitchforuser'))
				{
					continue;
				}

				if ($debug)
				{
					dumpMessage('here 7');
				}
			}
			elseif ($task == 'saveItem')
			{
				if ($task == 'saveItem')
				{
					$this->_debug('Content allowed?   START CHECK');
				}

				if (!$this->checkAllowed($contentItem, $paramName = 'article'))
				{
					if ($task == 'saveItem')
					{
						$this->_debug('Content item is not among allowed categories or specific items. CHECK FAILED');
					}

					continue;
				}

				if ($task == 'saveItem')
				{
					$this->_debug('Content allowed? ? PASSED');
					$this->_debug('<b>This rule sends notifications for the content item!!!</b>');
				}
			}

			$rules[$task][$ruleNumber] = $rule;
		}

		unset($this->task);

		if (isset($rules[$task]))
		{
			return $rules[$task];
		}

		return false;
	}

	/**
	 * Checks if the passed $object is allowed by a group of options
	 *
	 * E.g. checks is a current user is among allowed with a group of options users
	 * Check user groups and specific users
	 * Or if a current content item is among allowed content items.
	 * Checks categories and specific content items
	 * The XML plugin file structure must follow a convention to let the function work. E.g. for users:
	 * - select if all or selected user groups to use or to exclude selected (all, include, exclude)
	 * - select specific user groups to use or to exclude
	 * - select if all or selected specific user to use or to exclude selected (all, include, exclude)
	 * - select specific users to use or to exclude
	 * XML example
	 *		<field name="ausers_allowusergroups" maxrepeatlength="1" type="variablefield" basetype="list"
		* 			default="0"
		* 			label="PLG_SYSTEM_NOTIFICATIONARY_FIELD_USER_GROUP_LEVELS" description="PLG_SYSTEM_NOTIFICATIONARY_FIELD_NOTIFY_ON_DESC">
		*				<option value="1">PLG_SYSTEM_NOTIFICATIONARY_FIELD_SELECTION</option>
		*				<option value="2">PLG_SYSTEM_NOTIFICATIONARY_FIELD_EXCLUDE_SELECTION</option>
		*				<option value="0">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ALL</option>
		*		</field>
		*		<field name="{ausers_allowusergroups12" maxrepeatlength="1" type="variablefield" basetype="toggler" param="ausers_allowusergroups" value="1,2"/>
		*			<field name="ausers_allowusergroupsselection" maxrepeatlength="1" type="variablefield" basetype="usergroup"
		* 				multiple="multiple" notregistered="0" publicfrontend="disable" registred="disable" default=""
		* 				label="" description="PLG_SYSTEM_NOTIFICATIONARY_FIELD_USER_GROUP_LEVELS_DESC"/>
		*		<field name="ausers_allowusergroups12}" maxrepeatlength="1" type="variablefield" basetype="toggler"/>
		*
		*	<field name="ausers_allowusers" maxrepeatlength="1" type="variablefield" basetype="list" default="0"
		* 		label="PLG_SYSTEM_NOTIFICATIONARY_FIELD_SPECIFIC_USERS" description="PLG_SYSTEM_NOTIFICATIONARY_FIELD_NOTIFY_ON_DESC">
		*			<option value="1">PLG_SYSTEM_NOTIFICATIONARY_FIELD_SELECTION</option>
		*			<option value="2">PLG_SYSTEM_NOTIFICATIONARY_FIELD_EXCLUDE_SELECTION</option>
		*			<option value="0">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ALL</option>
		*	</field>
		*	<field name="{ausers_allowusers12" maxrepeatlength="1" type="variablefield" basetype="toggler" param="ausers_allowusers" value="1,2"/>
		*		<field name="ausers_allowusersselection" maxrepeatlength="1" type="variablefield"
		* 			basetype="users" default="" label="PLG_SYSTEM_NOTIFICATIONARY_FIELD_USER_IDS"
		* 			description="PLG_SYSTEM_NOTIFICATIONARY_FIELD_USER_IDS_DESC"/>
		*	<field name="ausers_allowusers12}" maxrepeatlength="1" type="variablefield" basetype="toggler"/>
		*	Function call to check if allowed:
		* $user = \JFactory::getUser();
		* if (!$this->checkAllowed($user, $paramName = 'allowuser', $fieldNamePrefix='ausers' )) { return; }
		*
		* @param   object  &$object          Either content item object or Joomla user object
		* @param   string  $paramName        Param name, example 'allowuser'
		* @param   string  $fieldNamePrefix  See the example
		*
		* @return  bool  true if the object is allowed according to the group of options
		*/
		public function checkAllowed(&$object, $paramName, $fieldNamePrefix='ausers')
		{
			$debug = true;
			$debug = false;
	
			if (empty($this->task))
			{
				$this->task = '';
			}
	
			$className = get_class($object);
	
			if ($debug)
			{
				dumpMessage('<b>' . __FUNCTION__ . '</b>');
				dumpMessage('<b>' . $className . '</b>');
			}
	
			if (!empty($this->task) && $this->task == 'saveItem')
			{
				$this->_debug(' > <b>' . $className . '</b>');
	
				if (in_array($className, ['\JUser', 'Joomla\CMS\User\User']))
				{
					$selectionDebugTextGroups = '<i>user groups</i>';
					$selectionDebugTextSpecific = '<i>specific users</i>';
				}
				else
				{
					$selectionDebugTextGroups = '<i>categories</i>';
					$selectionDebugTextSpecific = '<i>specific content items</i>';
				}
			}
	
			if (in_array($className, ['\JUser', 'Joomla\CMS\User\User']) && !empty($this->rule))
			{
				foreach ($this->rule->usersAddedByEmail as $user)
				{
					if ($user->id == $object->id)
					{
						return true;
					}
				}
			}
	
			if (!in_array($className, ['\JUser', 'Joomla\CMS\User\User']) && empty($object->id))
			{
				$msg = '';
	
				if ($debug)
				{
					$msg = var_dump(debug_backtrace(), true);
				}
	
				\JFactory::getApplication()->enqueueMessage(
					\JText::_(ucfirst($this->plgName))
						. ' ( ' . str_replace(JPATH_ROOT, '', __FILE__) . ':' . __LINE__ . '): '
						. ' checkAllowed method cannot be run with an empty object<br/>' . $msg,
					'error'
				);
	
				return false;
			}
	
			if (!in_array($className, ['\JUser', 'Joomla\CMS\User\User']) && $this->task == 'saveItem')
			{
				$this->rule->content_language = (array) $this->rule->content_language;
	
				if (empty($this->rule->content_language) || in_array('always', $this->rule->content_language) )
				{
					// Do nothing
				}
				else
				{
					if (!in_array($object->language, $this->rule->content_language))
					{
						return false;
					}
				}
			}
	
			if ($debug)
			{
				dumpMessage('here 1');
			}
	
			$groupName = $fieldNamePrefix . '_' . $paramName . 'groups';
			$itemName = $fieldNamePrefix . '_' . $paramName . 's';
			$onGroupLevels = $this->_getP($groupName, $fieldNamePrefix);
			$onItems = $this->_getP($itemName, $fieldNamePrefix);
	
			switch ($onGroupLevels)
			{
				case '0':
					$onGroupLevels = 'all';
					break;
				case '1':
					$onGroupLevels = 'include';
					break;
				case '2':
					$onGroupLevels = 'exclude';
					break;
			}
	
			switch ($onItems)
			{
				case '0':
					$onItems = 'all';
					break;
				case '1':
					$onItems = 'include';
					break;
				case '2':
					$onItems = 'exclude';
					break;
			}
	
			if ($debug)
			{
				dump($onGroupLevels, $groupName);
				dump($onItems, $itemName);
			}
	
			if (!empty($this->task) && $this->task == 'saveItem')
			{
				$this->_debug(' > ' . $selectionDebugTextGroups . ' selection', false, $onGroupLevels);
				$this->_debug(' > Specific ' . $selectionDebugTextSpecific . ' selection', false, $onItems);
			}
	
			// Allowed for all
			if ($onGroupLevels == 'all' && $onItems == 'all')
			{
				if (!empty($this->task) && $this->task == 'saveItem')
				{
					$this->_debug(' > Always allowed. PASSED');
				}
	
				return true;
			}
	
			if ($debug)
			{
				dumpMessage('here 2');
			}
			// Get which group the user belongs to, or which category the user belongs to
			switch ($className)
			{
				// If means &object is user, not article
				case "\JUser":
				case "Joomla\CMS\User\User":
					$object->temp_gid = $object->get('groups');
	
					if ($object->temp_gid === null)
					{
							$table   = \JUser::getTable();
							$table->load($object->id);
							$object->temp_gid = $table->groups;
					}
	
					if (empty($object->temp_gid))
					{
						$object->temp_gid = array($object->gid);
					}
					break;
	
				// If means &object is article, not user
				default:
					$object->temp_gid = (array) $object->catid;
					break;
			}
	
			if (!empty($this->task) && $this->task == 'saveItem')
			{
				$this->_debug(' > Current obect ' . $selectionDebugTextGroups . ' (ids)', false, $object->temp_gid);
			}
	
			// If not all grouplevels allowed then check if current user is allowed
			$isOk = false;
	
			$groupToBeIncluded = false;
			$groupToBeExcluded = false;
	
			if ($onGroupLevels != 'all' )
			{
				// Get user groups/categories to be included/excluded
				$GroupLevels = $this->_getP($groupName . 'selection', $fieldNamePrefix);
	
				if (!is_array($GroupLevels))
				{
					$GroupLevels = explode(',', $GroupLevels);
				}
	
				if (!empty($this->task) && $this->task == 'saveItem')
				{
					$this->_debug(' > ' . $selectionDebugTextGroups . ' included/excluded', false, $GroupLevels);
				}
	
				// Check only categories, as there are no sections
				$gid_in_array = false;
	
				foreach ($object->temp_gid as $gid)
				{
					if (in_array($gid, $GroupLevels))
					{
						$gid_in_array = true;
						break;
					}
				}
	
				if ($onGroupLevels == 'include' && $gid_in_array)
				{
					$groupToBeIncluded = true;
	
					if (!empty($this->task) && $this->task == 'saveItem')
					{
						$this->_debug(' > Is allowed based on ' . $selectionDebugTextGroups . ' YES');
					}
				}
				elseif ($onGroupLevels == 'exclude' && $gid_in_array)
				{
					$groupToBeExcluded = true;
	
					if (!empty($this->task) && $this->task == 'saveItem')
					{
						$this->_debug(' > Is NOT allowed based on ' . $selectionDebugTextGroups . ' YES');
					}
				}
			}
	
			// ~ $isOk = false;
			$forceInclude = false;
			$forceExclude = false;
	
			// If not all user allowed then check if current user is allowed
			if ($onItems != 'all' )
			{
				$Items = $this->_getP($itemName . 'selection', $fieldNamePrefix);
	
				if (!is_array($Items))
				{
					$Items = explode(',', $Items);
				}
	
				$item_in_array = in_array($object->id, $Items);
	
				if (!empty($this->task) && $this->task == 'saveItem')
				{
					$this->_debug(' > ' . $selectionDebugTextSpecific . ' included/excluded', false, $Items);
				}
	
				if ($onItems == 'include' && $item_in_array)
				{
					$forceInclude = true;
	
					if (!empty($this->task) && $this->task == 'saveItem')
					{
						$this->_debug(' > Is FORCED to be INCLUDED based on ' . $selectionDebugTextSpecific . '');
					}
	
					return true;
				}
				elseif ($onItems == 'exclude' && $item_in_array)
				{
					$forceExclude = true;
	
					if (!empty($this->task) && $this->task == 'saveItem')
					{
						$this->_debug(' > Is FORCED to be EXCLUDED based on ' . $selectionDebugTextSpecific . '');
					}
	
					return false;
				}
			}
	
			if ($debug)
			{
				dumpMessage('here 3');
			}
	
			if (!empty($this->task) && $this->task == 'saveItem')
			{
				$this->_debug(' > Is ALLOWED based on ' . $selectionDebugTextSpecific . ' YES');
			}
	
			$itemAllowed = true;
	
			if ($groupToBeIncluded)
			{
				if (!empty($this->task) && $this->task == 'saveItem')
				{
					$this->_debug(' > Object belongs to included ' . $selectionDebugTextGroups . '. CHECK PASSED');
				}
	
				return true;
			}
	
			if ($debug)
			{
				dumpMessage('here 4');
			}
	
			if ($groupToBeExcluded)
			{
				if (!empty($this->task) && $this->task == 'saveItem')
				{
					$this->_debug(' > Object belongs to excluded ' . $selectionDebugTextGroups . '. CHECK FAILED');
				}
	
				return false;
			}
	
			if ($debug)
			{
				dumpMessage('here 5');
			}
	
			if ($onGroupLevels == 'exclude' && !$groupToBeExcluded )
			{
				if (!empty($this->task) && $this->task == 'saveItem')
				{
					$this->_debug(' > Object doesn\'t belong to excluded ' . $selectionDebugTextGroups . '. CHECK PASSED');
				}
	
				return true;
			}
	
			if ($debug)
			{
				dumpMessage('here 6');
			}
	
			if (!empty($this->task) && $this->task == 'saveItem')
			{
				$this->_debug(' > Object does not belong to included ' . $selectionDebugTextGroups . '. CHECK FAILED');
			}
	
			return false;
		}

		
	/**
	 * Gets class name
	 *
	 * @param   object  $object  Object
	 *
	 * @return   string
	 */
	static public  function get_class_from_ContentTypeObject ($object)
	{
		$result = false;
		$tableInfo = json_decode($object->table);

		if (is_object($tableInfo) && isset($tableInfo->special))
		{
			if (is_object($tableInfo->special) && isset($tableInfo->special->type) && isset($tableInfo->special->prefix))
			{
				$class = isset($tableInfo->special->class) ? $tableInfo->special->class : '\JTable';

				if (!class_implements($class, '\JTableInterface'))
				{
					// This isn't an instance of \JTableInterface. Abort.
					throw new RuntimeException('Class must be an instance of \JTableInterface');
				}

				// ~ $result = $class::getInstance($tableInfo->special->type, $tableInfo->special->prefix);
				$result = $tableInfo->special->prefix . $tableInfo->special->type;
			}
		}

		return $result;
	}

		/**
	 * Checks if the user is subscribed to the passed category in the passed rule
	 *
	 * @param   object  $rule   NA rule
	 * @param   mixed   $user   User either \JUser object or an array with basic user information
	 * @param   int     $catid  Category id
	 * @param   bool    $force  Force DB query rerun
	 *
	 * @return   bool  True if subscribed
	 */
	public static function checkIfUserSubscribedToTheCategory($rule, $user, $catid, $force = false)
	{
		if (is_array($user))
		{
			$user = (object) $user;
		}

		// Get unsubscribed from the rule users
		$unsubscribedEmails = array_map('trim', explode(PHP_EOL, $rule->ausers_excludeusers));

		if (in_array($user->email, $unsubscribedEmails))
		{
			return false;
		}

		$allowedCategories = self::getProfileData($user->id, $rule->__ruleUniqID, $force);

		if (empty($allowedCategories) && $rule->allow_subscribe_default)
		{
			return true;
		}
		elseif (empty($allowedCategories) && !$rule->allow_subscribe_default)
		{
			return false;
		}

		if (in_array('unsubscribed', $allowedCategories))
		{
			return false;
		}

		if (in_array('subscribed', $allowedCategories))
		{
			return true;
		}

		if (in_array('subscribed', $allowedCategories))
		{
			return true;
		}

		if (in_array($catid, $allowedCategories))
		{
			return true;
		}

		return false;
	}

}
