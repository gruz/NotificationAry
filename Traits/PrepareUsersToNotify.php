<?php
/**
 * PrepareUsersToNotify
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
trait PrepareUsersToNotify
{

	/**
	 * Prepares users` to be notified data
	 *
	 * @return   array  Array with array element, which contain users data
	 */
	protected function _users_to_send()
	{
		$debug = true;
		$debug = false;

		if ($debug)
		{
			dump('users 1');
		}

		// 0 => New and Updates; 1 => New only; 2=> Updated only
		$nofityOn = $this->rule->ausers_notifyon;

		// If notify only at New, but the article is not new
		if ($nofityOn == 1 && !$this->isNew )
		{
			return array();
		}

		if ($debug)
		{
			dump('users 2');
		}

		// If notify only at Updated, but the article is New
		if ($nofityOn == 2 && $this->isNew )
		{
			return array ();
		}

		if ($debug)
		{
			dump('users 3');
		}
		/*
		$onAction = $this->rule->ausers_notifyonaction;
			1=>ON_PUBLISH_ONLY;
			2=>ON_UNPUBLISH_ONLY;
			6=>ON_PUBLISH_OR_UNPUBLISH;
			3=>ON_CHANGES_IN_PUBLISHED_ONLY;
			4=>ON_CHANGES_IN_UNPUBLISHED_ONLY;
			5=>ALWAYS;
		*/
		$status_action_to_notify = (array) $this->rule->status_action_to_notify;
		$possible_actions = array('publish', 'unpublish', 'archive', 'trash');
			/*
			<option value="always">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ALWAYS</option>
			<option value="1">#~#JSTATUS#~#:#~#JPUBLISHED#~#</option>
			<option value="0">#~#JSTATUS#~#:#~#JUNPUBLISHED#~#</option>
			<option value="2">#~#JSTATUS#~#:#~#JARCHIVED#~#</option>
			<option value="-2">#~#JSTATUS#~#:#~#JTRASHED#~#</option>
			<option value="publish">#~#PLG_SYSTEM_NOTIFICATIONARY_ACTION#~#:#~#JTOOLBAR_PUBLISH#~#</option>
			<option value="unpublish">#~#PLG_SYSTEM_NOTIFICATIONARY_ACTION#~#:#~#JTOOLBAR_UNPUBLISH#~#</option>
			<option value="archive">#~#PLG_SYSTEM_NOTIFICATIONARY_ACTION#~#:#~#JTOOLBAR_ARCHIVE#~#</option>
			<option value="trash">#~#PLG_SYSTEM_NOTIFICATIONARY_ACTION#~#:#~#JTOOLBAR_TRASH#~#</option>
			*/

			/*
			* // Possible status changes (action)
			$this->publishStateChange = 'nochange';
			$this->publishStateChange = 'not determined';
			$this->publishStateChange = 'publish';
			$this->publishStateChange = 'unpublish';
			$this->publishStateChange = 'archive';
			$this->publishStateChange = 'trash';
			*/

		if ($debug)
		{
			dump($status_action_to_notify, '$status_action_to_notify');
			dump($this->publishStateChange, '$this->publishStateChange');
			dump($this->contentItem->state, '$this->contentItem->state');
		}

		while (true)
		{
			if (in_array('always', $status_action_to_notify))
			{
				break;
			}

			// If current item status is among allowed statuses
			if (in_array((string) $this->contentItem->state, $status_action_to_notify))
			{
				break;
			}

			$intersect = array_intersect($status_action_to_notify, $possible_actions);

			// If there is an action among selected parameters in $status_action_to_notify
			if (!empty($intersect))
			{
				// Then we check the action happened to the content item
				if ($this->publishStateChange == 'nochange' || $this->publishStateChange == 'not determined')
				{
					// Do nothing, means returning empty array.
					// So if we want a notification on an action but the action cannot be determined, then noone has to be notified
				}
				elseif (in_array($this->publishStateChange, $status_action_to_notify))
				{
					break;
				}
			}

			if ($debug)
			{
				dump(
					$status_action_to_notify,
					'Content item status or action is now among allowed options. $this->contentItem->state ='
						. $this->contentItem->state . ' | $this->publishStateChange ='
						. $this->publishStateChange . ' | Allowed options '
				);
			}

			return array();
		}

		if ($debug)
		{
			dump('users 4 - Content status or action is among allowed ones');
		}

		$user = \JFactory::getUser();

		// Check if notifications turned on for current user
		if (!$this->_checkAllowed($user, $paramName = 'allowuser'))
		{
			return array ();
		}

		if ($debug)
		{
			dump('users 5 - check if notifications turned on for current article ...');
		}

		// Check if notifications turned on for current article
		if (!$this->_checkAllowed($this->contentItem, $paramName = 'article'))
		{
			return array ();
		}

		if ($debug)
		{
			dump('users 6 - start creating a list of emails ');
		}

		$users_to_send = array();
		$UserIds = array ();

		$paramName = 'notifyuser';

		/*
		The variables keep the names of NA rule options telling
		which user groups and users to notify - all, selected, none
				<field name="ausers_notifyusergroups" maxrepeatlength="1" type="variablefield" basetype="list" default="1"
					label="PLG_SYSTEM_NOTIFICATIONARY_FIELD_USER_GROUP_LEVELS" description="PLG_SYSTEM_NOTIFICATIONARY_FIELD_USER_GROUP_LEVELS_DESC">
					<option value="1">PLG_SYSTEM_NOTIFICATIONARY_FIELD_SELECTION</option>
					<option value="2">PLG_SYSTEM_NOTIFICATIONARY_FIELD_EXCLUDE_SELECTION</option>
					<option value="0">JALL</option>
					<option value="-1">JNONE</option>
				</field>
		*/
		$groupName = 'ausers_' . $paramName . 'groups';
		$itemName = 'ausers_' . $paramName . 's';

		// Which group levels to be notified - all, none, selected
		// Group levels means either user groups of article categories
		$onGroupLevels = $this->rule->{$groupName};

		/*
		Which items to be notified - all, none, selected
		Items here means articles or users
				<field name="ausers_notifyusers" maxrepeatlength="1" type="variablefield" basetype="list" default="0"
					label="PLG_SYSTEM_NOTIFICATIONARY_FIELD_SPECIFIC_USERS" description="PLG_SYSTEM_NOTIFICATIONARY_FIELD_SPECIFIC_DESC">
					<option value="1">PLG_SYSTEM_NOTIFICATIONARY_FIELD_SELECTION</option>
					<option value="2">PLG_SYSTEM_NOTIFICATIONARY_FIELD_EXCLUDE_SELECTION</option>
					<option value="0">PLG_SYSTEM_NOTIFICATIONARY_FIELD_NO_SPECIFIC_RULES</option>
				</field>
			* */
		$onItems = $this->rule->{$itemName};

		// When to return no users selected
			if ($onGroupLevels == -1 && $onItems == 0)
			{
				return $users_to_send;
			}

			$GroupLevels = $this->rule->{$groupName . 'selection'};
			$UserIds = $this->rule->{$itemName . 'selection'};

			// If exclude some user groups, but no groups selected, then it's assumed that all groups are to be included
			if ($onGroupLevels == 2 && empty($GroupLevels))
			{
				$onGroupLevels = 0;
			}

			// If include some user groups, but no groups selected, then it's assumed that all groups are to be excluded
			if ($onGroupLevels == 1 && empty($GroupLevels))
			{
				$onGroupLevels = -1;
			}

			// If exclude/include some users, but no user ids selected, then it's assumed no specific rules applied per user
			if (($onItems == 1 || $onItems == 2) && empty($UserIds))
			{
				$onItems = 0;
			}

		$db = \JFactory::getDBO();

		// Create WHERE conditions start here

		// Prepare ids of groups and items to include in the WHERE below
		while (true)
		{
			// If no limitation set - for user groups and specific users either all or selected - break
			if ($onGroupLevels == 0 && $onItems == 0)
			// ~ if (($onGroupLevels == 0  && $onItems == 0) || $onGroupLevels == 0  && $onItems == 1 )
			{
				break;
			}

			// If selected groups (otherwise, if no or all groups - we add nothing to WHERE)
			if ($onGroupLevels > 0)
			{
				if (!is_array($GroupLevels))
				{
					$GroupLevels = explode(',', $GroupLevels);
				}

				$GroupLevels = array_map('intval', $GroupLevels);
				$GroupLevels = array_map(array($db, 'Quote'), $GroupLevels);

				if ($onGroupLevels == 1)
				{
					$GroupWhere = 'AND';
				}
				elseif ($onGroupLevels == 2)
				{
					$GroupWhere = 'NOT';
				}
			}

			// If use selected user ids, then prepare the array of the ids for WHERE
			if ($onItems != 0)
			{
				if (!is_array($UserIds))
				{
					$UserIds = explode(',', $UserIds);
				}

				$UserIds = array_map('intval', $UserIds);
				$UserIds = array_map(array($db, 'Quote'), $UserIds);

				$UserWhere = 'AND';

				if ($onItems == 1)
				{
					$UserWhere = 'AND';
				}
				elseif ($onItems == 2)
				{
					$UserWhere = 'NOT';
				}
			}

			break;
		}

		// Just in case
		$GroupLevels = array_filter($GroupLevels);
		$UserIds = array_filter($UserIds);

		// $prevent_from_sending = array_filter($prevent_from_sending);
		$query = $db->getQuery(true);
		$query->select('name, username, email, id, group_id as gid ');
		$query->from('#__users AS users');
		$query->leftJoin('#__user_usergroup_map AS map ON users.id = map.user_id');
		$query->where('block = 0');
		$query->where($db->quoteName('id') . " <> " . $db->Quote($this->contentItem->created_by));

		if (!empty($this->contentItem->{'modified_by'}) && $this->contentItem->{'modified_by'} != $this->contentItem->created_by)
		{
			$query->where(" id <> " . $db->Quote($this->contentItem->{'modified_by'}));
		}

		if (!empty($GroupLevels))
		{
			$where = '';

			if (!empty($GroupWhere) && $GroupWhere == 'NOT')
			{
				$where .= $GroupWhere;
			}

			$where .= ' ( group_id = ' . implode(' OR group_id = ', $GroupLevels) . ')';
			$query->where($where);
		}

		if (!empty($UserIds))
		{
			$where = '';

			if ($UserWhere == 'NOT')
			{
				$where .= $UserWhere;
			}
			else
			{
				$where .= 'TRUE OR';
			}

			$where .= ' ( id = ' . implode(' OR id=', $UserIds) . ')';
			$query->where($where);
		}

		$query->group('id');

		$db->setQuery((string) $query);

		$users_to_send = $db->loadAssocList();

		// If the rule allows to subscribe manually,
		// then we have to check if the user has some subscription personalization
		switch ($this->rule->allow_subscribe)
		{
			/*
				<field name="allow_subscribe" maxrepeatlength="1" type="gjfields.variablefield" basetype="list" default="1"
					label="PLG_SYSTEM_NOTIFICATIONARY_FIELD_ALLOW_SUBSCRIBE"
					description="PLG_SYSTEM_NOTIFICATIONARY_FIELD_ALLOW_SUBSCRIBE_DESC">
					<option value="2">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ALLOW_RULE_SUBSCRIBE</option>
					<option value="1">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ALLOW_PER_CATEGORY_SUBSCRIBE</option>
					<option value="0">JNO</option>
				</field>
				*/
			// Per category subscribe or per rule subscribe?
			case '1':
			case '2':
				// Removed users which are not subscribed to the category
				foreach ($users_to_send as $k => $user)
				{
					if (is_array($this->contentItem->catid))
					{
						$unset = true;
						foreach ($this->contentItem->catid as $k => $catid)
						{
							if (self::checkIfUserSubscribedToTheCategory($this->rule, $user, $catid, $force = true))
							{
								$unset = false;
							}
						}

						if ($unset)
						{
							unset($users_to_send[$k]);
						}

					}
					else
					{
						if (!self::checkIfUserSubscribedToTheCategory($this->rule, $user, $this->contentItem->catid, $force = true))
						{
							unset($users_to_send[$k]);
						}
					}
				}

				break;

			// Do nothing, as users cannot subscribe themselves
			case '0':
			default :
				break;
		}

		$notifyonlyifcanview = $this->rule->ausers_notifyonlyifcanview;

		// E.g. joomla banner has no access option, so we ignore it here
		if ($notifyonlyifcanview && isset($this->contentItem->access))
		{
			foreach ($users_to_send as $k => $value)
			{
				if (!empty($value['id']))
				{
					// ~ $user = \JFactory::getUser($value['id']);
					$user = self::getUser($value['id']);
				}
				else
				{
					$user = \JFactory::getUser(0);
					$user->set('email', $value['email']);
				}

				$canView = false;

				// $canEdit = $user->authorise('core.edit', 'com_content.article.'.$this->contentItem->id);
				// $canLoginBackend = $user->authorise('core.login.admin');

				if (in_array($this->contentItem->access, $user->getAuthorisedViewLevels()))
				{
					$canView = true;
				}

				if (!$canView)
				{
					unset($users_to_send[$k]);
				}
			}
		}

		$Users_Add_emails = $this->rule->ausers_additionalmailadresses;
		$Users_Add_emails = explode(PHP_EOL, $Users_Add_emails);
		$Users_Add_emails = array_map('trim', $Users_Add_emails);

		foreach ($Users_Add_emails as $cur_email)
		{
			$cur_email = JString::trim($cur_email);

			if ($cur_email == "")
			{
				continue;
			}

			$add_mail_flag = true;

			foreach ($users_to_send as $v => $k)
			{
				if ($k['email'] == $cur_email )
				{
					$add_mail_flag = false;
					break;
				}
			}

			if ($add_mail_flag)
			{
				$users_to_send[]['email'] = $cur_email;
			}
		}

		if ($debug)
		{
			dump($users_to_send, 'users 7');
		}

		return (array) $users_to_send;
	}

	/**
	 * Adds content authtor and/or modifier if needed
	 *
	 * @return   array  Array of arrays with author and modifier data
	 */
	protected function _addAuthorModifier ()
	{
		$users_to_send_helper = array();

		// If I'm the author and I modify the content item
		if ($this->author->id == $this->modifier->id )
		{
			if (!$this->rule->ausers_notifymodifier )
			{
				return array();
			}

			if ($this->rule->ausers_notifymodifier )
			{
				$users_to_send_helper[] = array (
						'id' => $this->modifier->id,
						'email' => $this->modifier->email,
						'name' => $this->modifier->name,
						'username' => $this->modifier->username
					);

				return $users_to_send_helper;
			}
		}

		// If I modify the content item, but I'm not the author
		if ($this->rule->ausers_notifymodifier )
		{
			$users_to_send_helper[] = array (
					'id' => $this->modifier->id,
					'email' => $this->modifier->email,
					'name' => $this->modifier->name,
					'username' => $this->modifier->username
				);
		}

		// ** If I'm the author, but someone else modifies my article ** //

		// If the article has no author, then go out
		if ($this->author->id == 0 )
		{
			return $users_to_send_helper;
		}

		// If the author should be notfied only for allowed modifiers
		if ($this->rule->author_foranyuserchanges == '0' && !$this->_checkAllowed($this->modifier, $paramName = 'allowuser'))
		{
			return $users_to_send_helper;
		}

		// If we are here, then I'm (current user, modifier) not the author, and the author is allowed to be notified about changes perfomed by me.
		// So we check now if the current action perfomed over the content item allows to notify the author

		/* $this->rule->author_notifyonaction options:
		<option value="0">PLG_SYSTEM_NOTIFICATIONARY_FIELD_NEVER</option>
		<option value="1">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ON_PUBLISH_ONLY</option>
		<option value="2">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ON_UNPUBLISH_ONLY</option>
		<option value="6">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ON_PUBLISH_OR_UNPUBLISH</option>
		<option value="3">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ON_CHANGES_IN_PUBLISHED_ONLY</option>
		<option value="4">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ON_CHANGES_IN_UNPUBLISHED_ONLY</option>
		<option value="5">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ALWAYS</option>
		*/

		$nauthor = $this->rule->author_notifyonaction;

		// Always
		if ($nauthor == '5')
		{
			$users_to_send_helper[] = array (
					'id' => $this->author->id,
					'email' => $this->author->email,
					'name' => $this->author->name,
					'username' => $this->author->username
				);

			return $users_to_send_helper;
		}

		while (true)
		{
			// If never to notify author
			if ($nauthor == '0')
			{
				break;
			}

			// If notify on `publish only` or on `unpublish only`, but the state was not changed
			if (($nauthor == '1' || $nauthor == '2')
				&& ($this->publishStateChange == 'nochange' || $this->publishStateChange == 'not determined'))
			{
				break;
			}

			// If notify on `publish or on unpublish` , but the state was not changed
			if ($nauthor == '6'  && ($this->publishStateChange == 'nochange' || $this->publishStateChange == 'not determined'))
			{
				break;
			}

			// If article is unpublished but is set to notify only in published articles
			if ($this->contentItem->state == '0' && $nauthor == '3' )
			{
				break;
			}

			// If article is published but is set to notify only in unpublished articles
			if ($this->contentItem->state == '1' && $nauthor == '4' )
			{
				break;
			}

			// If notify on `on publish or unpublish`, but the acion is not neiher published or unpublished
			if ($nauthor == '6' && !($this->publishStateChange == 'unpublish' || $this->publishStateChange == 'publish'))
			{
				break;
			}

			// If notify on `on publish only`, but the acion is not published
			if ($nauthor == '1' && !($this->publishStateChange == 'publish'))
			{
				break;
			}

			// If notify on `on unpublish only`, but the acion is not unpublished
			if ($nauthor == '2' && !($this->publishStateChange == 'unpublish'))
			{
				break;
			}

			// Add author to the list of receivers
			$users_to_send_helper[] = array ('id' => $this->author->id, 'email' => $this->author->email);
			break;
		}

		return $users_to_send_helper;
	}

		/**
	 * Removes emails which should not be notified
	 *
	 * @param   array  $Users_to_send  Array with users to be notified
	 *
	 * @return   array  Array with removed user items if needed
	 */
	protected function _remove_mails ($Users_to_send)
	{
		$Users_Exclude_emails = $this->rule->ausers_excludeusers;
		$Users_Exclude_emails = explode(PHP_EOL, $Users_Exclude_emails);
		$Users_Exclude_emails = array_map('trim', $Users_Exclude_emails);

		foreach ($Users_Exclude_emails as $cur_email)
		{
			$cur_email = JString::trim($cur_email);

			if ($cur_email == "")
			{
				continue;
			}

			foreach ($Users_to_send as $v => $k)
			{
				if ($k['email'] == $cur_email)
				{
					unset ($Users_to_send[$v]);
					break;
				}
			}
		}

		return $Users_to_send;
	}


	/**
	 * Gets JUser object by email
	 *
	 * @param   string  $email  Email
	 *
	 * @return   JUser  Either a JUser object for an existing user or a blank JUser object filled with the email
	 */
	static public function getUserByEmail ($email)
	{
		$db = \JFactory::getDbo();

		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__users'))
			->where($db->quoteName('email') . ' = ' . $db->quote($email));

		$db->setQuery($query, 0, 1);

		$result = $db->loadResult();

		if (empty($result))
		{
			$user = \JFactory::getUser(0);
			$user->email = $email;
		}
		else
		{
			$user = \JFactory::getUser($db->loadResult());
		}

		return $user;
	}

}
