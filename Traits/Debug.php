<?php
/**
 * Debug
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
trait Debug
{
	
	/**
	 * Outputs debug message
	 *
	 * @param   string  $msg      Debug message
	 * @param   bool    $newLine  If yo start from new line
	 * @param   string  $var      Description
	 *
	 * @return   type  Description
	 */
	protected function _debug($msg, $newLine = true, $var = 'not set' )
	{
		if (!$this->paramGet('debug'))
		{
			return;
		}

		if ($var === 0)
		{
			$var = 'NO';
		}

		if ($var === 1)
		{
			$var = 'YES';
		}

		if (function_exists('dump') && function_exists('dumpMessage'))
		{
			if ($var !== 'not set')
			{
				dump($var, $msg);
			}
			else
			{
				dumpMessage($msg);
			}
		}
		else
		{
			$out_msg = array();
			$out_msg[] = $msg;

			if ($var !== 'not set')
			{
				if (is_array($var) || is_object($var))
				{
					$out_msg[] = '<pre>';
					$out_msg[] = print_r($var, true);
					$out_msgmsg[] = '</pre>';
				}
				else
				{
					$out_msg [] = ' | ' . $var;
				}
			}

			$msg = implode(PHP_EOL, $out_msg);

			if ($newLine)
			{
				$msg = '<br>' . PHP_EOL . $msg;
			}

			\JFactory::getApplication()->enqueueMessage($msg, 'notice');
		}
	}

		/**
	 * This is a debug function. Generates a number of users for testing purposes
	 *
	 * @author Gruz <arygroup@gmail.com>
	 *
	 * @param type $name Description
	 *
	 * @return type Description
	 */
	static function userGenerator($number = 2, $groups = 'default')
	{
		$jinput = \JFactory::getApplication()->input;

		if ($jinput->get('option') == 'com_plugins' && $jinput->get('task') == 'plugin.apply')
		{
			//ok
		}
		else
		{
			return null;
		}

		$instance = JUser::getInstance();
		jimport('joomla.application.component.helper');
		$config = JComponentHelper::getParams('com_users');
		$db = \JFactory::getDBO();
		$query = $db->getQuery(true);
		$query->select('id , title');
		$query->from('#__usergroups');
		$query->where('id IN ('.implode(',', $groups).')');
		$db->setQuery((string) $query);

		$defaultUserGroupNames = $db->loadAssocList();
		$defaultUserGroup = $db->loadResultArray();
		$acl = \JFactory::getACL();

		// For each group
		for ($k = 0; $k < count($defaultUserGroupNames); $k++)
		{
			// For each number
			for ($i = 0; $i < $number; $i++)
			{
				$user = array();
				$hash = uniqid();

				if (!empty($defaultUserGroupNames))
				{
					$user['fullname'] = 'Fake ' . $defaultUserGroupNames[$k]['title'].' ' . $hash;
				}
				else
				{
					$user['fullname'] = 'Fake User ' . $hash;
				}

				$user['username'] = str_replace(' ','_',$user['fullname']);
				$user['email'] = $hash."@test.com";
				$user['password_clear'] = microtime();

				$instance->set('id'             , 0);
				$instance->set('name'           , $user['fullname']);
				$instance->set('username'       , $user['username']);
				$instance->set('password_clear' , $user['password_clear']);
				$instance->set('email'          , $user['email']);  // Result should contain an email (check)
				$instance->set('usertype'       , 'deprecated');
				$instance->set('groups'         , array($defaultUserGroupNames[$k]['id']));

				// If autoregister is set let's register the user
				$autoregister = isset($options['autoregister']) ? $options['autoregister'] :  $config->get('autoregister', 1);

				if ($autoregister)
				{
					if (!$instance->save())
					{
						\JFactory::getApplication()->enqueueMessage($instance->getError(), 'error');

						return;
					}
				}
				else
				{
					// No existing user and autoregister off, this is a temporary user.
					$instance->set('tmp_user', true);
				}
			}
		}
	}

}
