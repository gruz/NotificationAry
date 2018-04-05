<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_users
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\Utilities\ArrayHelper;
use NotificationAry\NotificationAry;

/**
 * Methods supporting a list of user records.
 *
 * @since  1.6
 */
class UsersModelUsers extends UsersModelUsersDefault
{
	/**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 *
	 * @see     JController
	 * @since   1.6
	 */
	public function __construct($config = array())
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = array(
				'id', 'a.id',
				'name', 'a.name',
				'username', 'a.username',
				'email', 'a.email',
				'block', 'a.block',
				'sendEmail', 'a.sendEmail',
				'registerDate', 'a.registerDate',
				'lastvisitDate', 'a.lastvisitDate',
				'activation', 'a.activation',
				'active',
				'group_id',
				'range',
				'lastvisitrange',
				'state',
			);
			/** ##mygruz20170216154842 { Add filter to keep the search tools tab open if needed.
			It was:
			It became: */
			$config['filter_fields'][] = 'naruleUniqID';
			$config['filter_fields'][] = 'nacategory';
			/** ##mygruz20170216154842 } */
		}

		parent::__construct($config);
	}

	/**
	 * Build an SQL query to load the list data.
	 *
	 * This functions totally overrides the original one, but later, when needed, gets parent function result and
	 * merges with prepared here.
	 *
	 * @return  JDatabaseQuery
	 *
	 * @since   1.6
	 */
	protected function getListQuery()
	{
		$session = \JFactory::getSession();

		// Here on some reason I cannot use getState/setState. The value is not stored between page load
		// So I arrived at a decision to use Session instead
		$ruleUniqIDPrevious = $session->get('naruleUniqIDPrevious', null, 'notificationary');

		$ruleUniqID = $this->getState('filter.naruleUniqID');

		if (!empty($ruleUniqIDPrevious) && $ruleUniqIDPrevious != $ruleUniqID)
		{
			$this->setState('filter.nacategory', '');
		}

		$session->set('naruleUniqIDPrevious', $ruleUniqID, 'notificationary');

		if (empty($ruleUniqID))
		{
			$query = parent::getListQuery();

			return $query;
		}

		$categoryID = $this->getState('filter.nacategory');

		$db    = $this->getDbo();
		$app = \JFactory::getApplication();

		// ~ Pass the plugin object to be available in the field to have plugin params parsed there
		$pluginObject = $app->get('plg_system_notificationary');

		// Load NA subscribed options from the user profiles table
		$rules = $pluginObject->pparams;

		foreach ($rules as $ruleNumber => $rule)
		{
			if ($rule->__ruleUniqID == $ruleUniqID)
			{
				break;
			}
		}

		if (empty($rule))
		{
			$query = parent::getListQuery();

			return $query;
		}

		// Prepare names of plugin settings fields. These strange names are due to the plugin history
		// when the plugin had admin users settings (ausers) and registred users settings (rusers)
		$paramName = 'notifyuser';
		$groupName = 'ausers_' . $paramName . 'groups';
		$itemName = 'ausers_' . $paramName . 's';

		$onGroupLevels = $rule->{$groupName};
		$groupLevels = $rule->{$groupName . 'selection'};

		if (!is_array($groupLevels))
		{
			$groupLevels = explode(',', $groupLevels);
		}

		/*
		 * 	We add WHERE statement for
		 * - selected/unselected usergroups
		 * - selected/unselected user ids (get additional user ids from include/exclude emails)
		 * - users un/subscribed to the category either by default or per category
		 */

		/*
		 * Now add WHERE statement to limit to un/selected user groups
		 */

		/*
			<field name="ausers_notifyusergroups" maxrepeatlength="1" type="gjfields.variablefield" basetype="list" default="1"
			label="PLG_SYSTEM_NOTIFICATIONARY_FIELD_USER_GROUP_LEVELS" description="PLG_SYSTEM_NOTIFICATIONARY_FIELD_USER_GROUP_LEVELS_DESC">
				<option value="1">PLG_SYSTEM_NOTIFICATIONARY_FIELD_SELECTION</option>
				<option value="2">PLG_SYSTEM_NOTIFICATIONARY_FIELD_EXCLUDE_SELECTION</option>
				<option value="0">JALL</option>
				<option value="-1">JNONE</option>
			</field>
		*/
		switch ($onGroupLevels)
		{
			// All user groups - do not limit the by WHERE
			case '0':
				break;

			// No user groups - exclude all user groups (maybe only selected users allowed)
			case '-1':
					$query = parent::getListQuery();
					$query->join('LEFT', '#__user_usergroup_map AS mapNA ON mapNA.user_id = a.id')
						->group(
							$db->quoteName(
								array(
									'a.id',
									'a.name',
									'a.username',
									'a.password',
									'a.block',
									'a.sendEmail',
									'a.registerDate',
									'a.lastvisitDate',
									'a.activation',
									'a.params',
									'a.email'
								)
							)
						);

						// Add a non-existing id
						$query->where('mapNA.group_id = -1');
				break;

			// Selected usergroups
			case '1':

				// Check if the filter has already these values added to determine if we need a separate join
				$groupId = $this->getState('filter.group_id');
				$groups  = $this->getState('filter.groups');

				// NOTE. It seems com_users model has some code, which can be remove, so this part may stop working
				// and will need a manual join addition as below

				// If a filters for a group is set, we just pass the list of groups to be included only
				if (!empty($groupId) || !empty($groups))
				{
					$this->setState('filter.groups', $groupLevels);
					$query = parent::getListQuery();
				}
				else
				{
					// Here we select only users belonging to allowed in NA user groups
					$query = parent::getListQuery();
					$query->join('LEFT', '#__user_usergroup_map AS mapNA ON mapNA.user_id = a.id')
						->group(
							$db->quoteName(
								array(
									'a.id',
									'a.name',
									'a.username',
									'a.password',
									'a.block',
									'a.sendEmail',
									'a.registerDate',
									'a.lastvisitDate',
									'a.activation',
									'a.params',
									'a.email'
								)
							)
						);

						$query->where('mapNA.group_id IN (' . implode(',', $groupLevels) . ')');
				}

				break;

			// Excluded usergroups
			case '2':
					// Here we select only users NOT belonging to selected in NA user groups
					$query = parent::getListQuery();
					$query->join('LEFT', '#__user_usergroup_map AS mapNA ON mapNA.user_id = a.id')
						->group(
							$db->quoteName(
								array(
									'a.id',
									'a.name',
									'a.username',
									'a.password',
									'a.block',
									'a.sendEmail',
									'a.registerDate',
									'a.lastvisitDate',
									'a.activation',
									'a.params',
									'a.email'
								)
							)
						);

						$query->where('mapNA.group_id NOT IN (' . implode(',', $groupLevels) . ')');

				break;
			default :

				break;
		}

		// Now we prepare user id's to be included/excluded
		$onItems = $rule->{$itemName};
		$userIds = $rule->{$itemName . 'selection'};

		if (is_string($userIds))
		{
			$userIds = array_map('trim', explode(PHP_EOL, $rule->{$itemName . 'selection'}));
		}

		$userIdsToInclude = array();
		$userIdsToExclude = array();

		// Select user ids by emails
		$includeEmails = array_map('trim', explode(PHP_EOL, $rule->ausers_additionalmailadresses));
		$excludeEmails = array_map('trim', explode(PHP_EOL, $rule->ausers_excludeusers));

		foreach ($includeEmails as $k => $v)
		{
			$user = plgSystemNotificationary::getUserByEmail($v);

			if (0 !== $user->id)
			{
				$userIdsToInclude[] = $user->id;
			}
		}

		foreach ($excludeEmails as $k => $v)
		{
			$user = plgSystemNotificationary::getUserByEmail($v);

			if (0 !== $user->id)
			{
				$userIdsToExclude[] = $user->id;
			}
		}

		/**
		Which items to be notified - all, none, selected
		Items here means articles or users
				<field name="ausers_notifyusers" maxrepeatlength="1" type="variablefield" basetype="list" default="0"
				 label="PLG_SYSTEM_NOTIFICATIONARY_FIELD_SPECIFIC_USERS" description="PLG_SYSTEM_NOTIFICATIONARY_FIELD_SPECIFIC_DESC">
					<option value="1">PLG_SYSTEM_NOTIFICATIONARY_FIELD_SELECTION</option>
					<option value="2">PLG_SYSTEM_NOTIFICATIONARY_FIELD_EXCLUDE_SELECTION</option>
					<option value="0">PLG_SYSTEM_NOTIFICATIONARY_FIELD_NO_SPECIFIC_RULES</option>
				</field>
		*/

		switch ($onItems)
		{
			// No specific users selected
			case '0':
				// Do nothing
				break;

			// Selected some ids
			case '1':
				$userIdsToInclude = array_merge($userIds, $userIdsToInclude);
				break;

			// Deselected some ids
			case '2':
				$userIdsToExclude = array_merge($userIds, $userIdsToExclude);
				break;
			default :

				break;
		}

		if (!empty($userIdsToInclude))
		{
			$query->where('a.id IN (' . implode(',', $userIdsToInclude) . ')');
		}

		if (!empty($userIdsToExclude))
		{
			$query->where('a.id NOT IN (' . implode(',', $userIdsToExclude) . ')');
		}

		if (!empty($categoryID))
		{
			$query->join('LEFT', '#__user_profiles AS profileNA ON profileNA.user_id = a.id');

			/**
				<field name="allow_subscribe_default" maxrepeatlength="1" type="gjfields.variablefield" basetype="list"
						default="1" label="PLG_SYSTEM_NOTIFICATIONARY_FIELD_PER_CATEGORY_SUBSCRIBE_DEFAULT"
						description="PLG_SYSTEM_NOTIFICATIONARY_FIELD_PER_CATEGORY_SUBSCRIBE_DEFAULT_DESC">
					<option value="1">JYES</option>
					<option value="0">JNO</option>
				</field>
			*/

			/** Here is the subquery we try to build below
			SELECT user_id FROM `a8rtd_user_profiles`
			WHERE
			user_id NOT IN (
			  ( SELECT user_id FROM `a8rtd_user_profiles`
				WHERE
					profile_key = 'notificationary.57b86a9395123.21'
					OR
					(
						profile_key = 'notificationary.57b86a9395123.all'
						AND
						profile_value = 'subscribed'

					)
			   )

			)
			AND
			profile_key LIKE 'notificationary.57b86a9395123.%'
			*/
			$db        = $this->getDbo();
			$subQuery0 = $db->getQuery(true);
			$subQuery1 = $db->getQuery(true);

			$subQuery0->select('user_id');
			$subQuery0->from($db->qn('#__user_profiles'));
			$subQuery0->where($db->qn('profile_key') . '=' . $db->q('notificationary.' . $ruleUniqID . '.' . $categoryID));
			$subQuery0->orWhere(
				[
					$db->qn('profile_key') . ' = ' . $db->q('notificationary.' . $ruleUniqID . '.all'),
					$db->qn('profile_value') . ' = ' . $db->q('subscribed')
				]
			);

			$subQuery1->select('user_id');
			$subQuery1->from($db->qn('#__user_profiles'));
			$subQuery1->where($db->qn('user_id') . ' NOT IN (' . $subQuery0 . ')');
			$subQuery1->where($db->qn('profile_key') . ' LIKE ' . $db->q('notificationary.' . $ruleUniqID . '.%'));

			// Is users are subscribed by default
			if (1 == $rule->allow_subscribe_default)
			{
				$query->where('a.id NOT IN (' . $subQuery1 . ')');
			}
			else
			{
				$query->where('a.id IN (' . $subQuery0 . ')');
			}
		}

		return $query;
	}
}
