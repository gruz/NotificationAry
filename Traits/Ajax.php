<?php
/**
 * Ajax
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
trait Ajax
{
	/**
	 * The function to be called from as Ajax to build really working SEF links from backend.
	 *
	 * Creates a backend user session, gets JRoute::_() and delets the session.
	 * It's a must to fake login-logout at FE, as JRoute::_() doesn't create correct links
	 * for e.g. content items limited to Registred if you are not logged in at FE.
	 *
	 * @return   void
	 */
	public function onAjaxNotificationAryGetFEURl()
	{
		$app	 = \JFactory::getApplication();

		// Has to work as a FE called function
		if ($app->isAdmin())
		{
			return;
		}

		$jinput = \JFactory::getApplication()->input;
		$userId = $jinput->get('userid', null);

		if (empty($userId))
		{
			return;
		}

		$serialize = $jinput->get('serialize', null, 'string');

		$url = unserialize(base64_decode($serialize));

		$sid = $jinput->get('sid', null);

		if (empty($serialize) || empty($sid) || empty($url))
		{
			return;
		}

		// ~ $hash = JApplicationHelper::getHash($userId . $sid . $url);

		// Get the database connection object and verify its connected.
		$db = \JFactory::getDbo();

		try
		{
			// Get the session data from the database table.
			$query = $db->getQuery(true)
				->select($db->quoteName('session_id'))
				->from($db->quoteName('#__session'))
				->where($db->quoteName('session_id') . ' = ' . $db->quote($sid));

			$db->setQuery($query);
			$rows = $db->loadRowList();
		}
		catch (RuntimeException $e)
		{
			return;
		}

		if (count($rows) < 1)
		{
			return;
		}

		$user	 = \JFactory::getUser();

		if ($user->id == $userId)
		{
			echo JRoute::_($url);

			return;
		}

		$instance = \JFactory::getUser($userId);

		if (empty($instance->id))
		{
			return;
		}

		// Temporary login user at FE

		/*
		Get the user which has the current token form DB to
		temporary store one's password. We need this to perform
		autologin. We cannot decode the existing password, so we
		use a temporary known password, login and then after
		login restore the preserved password directly in the
			database
		*/
		$session = \JFactory::getSession();

		// Set a temporary password for the user
		$tempPass = JApplicationHelper::getHash(JUserHelper::genRandomPassword());

		$query = $db->getQuery(true);
		$query->update('#__users');
		$query->set('password = ' . $db->Quote(md5($tempPass)));
		$query->where('id=' . $db->Quote($userId));
		$db->setquery($query);
		$db->execute();

		$credentials = array('username' => $instance->username, 'password' => $tempPass);
		$result      = $app->login($credentials);

		$url = JRoute::_($url);
		echo $url;

		$session->close();

		die();
	}

	/**
	 * Unsubscribes a user passed via an unsubscribe link
	 *
	 * @param   string  $uniq       Uniqid passed via URL
	 * @param   string  $serialize  Hash contanina informatio
	 *
	 * @return   string Raw output message
	 */
	public function _unsubscribe($uniq, $serialize)
	{
		$user = $serialize['unsubscribe'];
		$md5  = $serialize['md5'];

		$userObject = self::getUserByEmail($user);

		// $user->load(array('email'=>$email));
		if ($userObject->id > 0)
		{
			if ($md5 != md5($userObject->id . $uniq))
			{
				echo $msg = '<b style="color:red">' . \JText::sprintf('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBE_FAILED', $user) . '</b>';

				return;
			}
		}

		$excludeUsers = self::getRuleOption('ausers_excludeusers', $uniq);
		$excludeUsers = explode(PHP_EOL, $excludeUsers);
		$excludeUsers = array_map('trim', $excludeUsers);
		$msg          = '';

		if (!in_array($user, $excludeUsers))
		{
			$excludeUsers[] = $user;
			$excludeUsers   = array_filter($excludeUsers);
			$excludeUsers   = implode(PHP_EOL, $excludeUsers);

			if (!self::updateRuleOption('ausers_excludeusers', $excludeUsers, $uniq))
			{
				$msg = '<b style="color:red">' . \JText::sprintf('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBE_FAILED', $user) . '</b>';
			}
			else
			{
				$msg = '<b style="color:green">' . \JText::sprintf('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBED', $user) . '</b>';
			}
		}
		else
		{
			$msg = '<b style="color:blue">' . \JText::sprintf('PLG_SYSTEM_NOTIFICATIONARY_NOT_SUBSCRIBED', $user) . '</b>';
		}

		// Mark the rule as unsubscribed in the profile as well
		$db    = \JFactory::getDbo();
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__user_profiles'))
			->where($db->quoteName('user_id') . ' = ' . (int) $userObject->id)
			->where($db->quoteName('profile_key') . ' LIKE ' . $db->quote('notificationary.' . $uniq . '.all'));
		$db->setQuery($query);
		$db->execute();

		$tuples = array();
		$order  = 1;

		$tuples[] = '('
			. $userObject->id . ', '
			. $db->quote('notificationary.' . $uniq . '.all') . ', '
			. $db->quote('unsubscribed') . ', ' . ($order++)
		. ')';

		$db->setQuery('INSERT INTO #__user_profiles VALUES ' . implode(', ', $tuples));
		$db->execute();

		echo $msg;
	}

	/**
	 * Ajax entry point to update subscription
	 *
	 * @return   string  Json-formatted string
	 */
	public function onAjaxNotificationArySubscribeUpdate()
	{
		$resposne = array('success' => false);

		$jinput = \JFactory::getApplication()->input;
		$token  = JSession::getFormToken();

		if (!JSession::checkToken())
		{
			$resposne['message'] = \JText::_('JINVALID_TOKEN');

			return json_encode($resposne);
		}

		$user = \JFactory::getUser();

		$app = \JFactory::getApplication();

		if ($user->guest)
		{
			$resposne['message'] = \JText::_('JERROR_ALERTNOAUTHOR');

			return json_encode($resposne);
		}

		$userid = $jinput->post->get('userid');

		if ($userid != $user->id)
		{
			if (!$user->authorise('core.manage', 'com_users'))
			{
				$resposne['message'] = \JText::_('JERROR_ALERTNOAUTHOR');

				return json_encode($resposne);
			}
		}

		$user       = \JFactory::getUser($userid);
		$ruleUniqID = $jinput->post->get('ruleUniqID');

		$categoriesToBeStored = $jinput->post->get('categoriesToSubscribe_' . $ruleUniqID, array(), 'array');
		$subscribeToAll       = $jinput->post->get('subscribetoall_' . $ruleUniqID, 'selected');

		$excludeUsers = self::getRuleOption('ausers_excludeusers', $ruleUniqID);
		$excludeUsers = explode(PHP_EOL, $excludeUsers);
		$excludeUsers = array_map('trim', $excludeUsers);

		if (($key = array_search($user->email, $excludeUsers)) !== false)
		{
			unset($excludeUsers[$key]);
		}

		$excludeUsers = implode(PHP_EOL, $excludeUsers);

		if (!self::updateRuleOption('ausers_excludeusers', $excludeUsers, $ruleUniqID))
		{
			$resposne['message'] = \JText::_('Could not remove the user from excluded users');
		}

		try
		{
			$db    = \JFactory::getDbo();
			$query = $db->getQuery(true)
				->delete($db->quoteName('#__user_profiles'))
				->where($db->quoteName('user_id') . ' = ' . (int) $user->id)
				->where($db->quoteName('profile_key') . ' LIKE ' . $db->quote('notificationary.' . $ruleUniqID . '.%'));
			$db->setQuery($query);
			$db->execute();

			$tuples = array();
			$order  = 1;

			if ($subscribeToAll == 'all')
			{
				$tuples[] = '('
					. $user->id . ', '
					. $db->quote('notificationary.' . $ruleUniqID . '.all') . ', '
					. $db->quote('subscribed') . ', ' . ($order++)
				. ')';
			}

			if (!empty($categoriesToBeStored))
			{
				foreach ($categoriesToBeStored as $k => $v)
				{
					$tuples[] = '(' . $user->id . ', ' . $db->quote('notificationary.' . $ruleUniqID . '.' . $v) . ', ' . $db->quote($v) . ', ' . ($order++) . ')';
				}
			}

			if ($subscribeToAll == 'none'
				|| empty($categoriesToBeStored) && $subscribeToAll != 'all')
			{
				$tuples[] = '('
					. $user->id . ', '
					. $db->quote('notificationary.' . $ruleUniqID . '.all') . ', '
					. $db->quote('unsubscribed') . ', ' . ($order++)
				. ')';
			}

			$db->setQuery('INSERT INTO #__user_profiles VALUES ' . implode(', ', $tuples));
			$db->execute();

			$resposne['success'] = true;
			$resposne['message'] = \JText::_('PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIPTION_UPDATED');
		}
		catch (RuntimeException $e)
		{
			$this->_subject->setError($e->getMessage());

			$resposne['success'] = false;
			$resposne['message'] = \JText::_('PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIPTION_UPDATE_FAILED');
		}

		return json_encode($resposne);
	}

	/**
	 * Entry point for Ajax data passed via AJAX plugin
	 *
	 * Gruz uses this function as a default one in this case.
	 * The same is in MenuAry
	 *
	 * @return   void
	 */
	public function onAjaxNotificationAryRun()
	{
		$jinput = \JFactory::getApplication()->input;

		// Uniqid passed via URL
		$uniq = $jinput->get('uniq', null);

		// Hash containing information
		$serialize = $jinput->get('serialize', null, 'string');

		$session = \JFactory::getSession();

		$serialize = unserialize(base64_decode($serialize));

		if (isset($serialize['unsubscribe']))
		{
			$this->_unsubscribe($uniq, $serialize);

			return;
		}

		$hash = $serialize['ajaxHash'];

		$counter = $session->get('AjaxHashCounter' . $hash, -1, $this->plgName);

		$files = \JFolder::files(\JFactory::getApplication()->getCfg('tmp_path'), $this->plgName . '_' . $hash . '_*', false, true);

		if (empty($files))
		{
			$this->_cleanAttachments();
			$session->clear('AjaxHashCounter' . $hash, $this->plgName);

			// ~ $counter = $counter-1;
			if ($serialize['showNumberOfUsers'])
			{
				$numberSentTotal  = $session->get('AjaxHashCounterTotal' . $hash, -1, $this->plgName);
				$numberSentFailed = $session->get('AjaxHashCounterFailed' . $hash, 0, $this->plgName);
				$numberSent       = $numberSentTotal - $numberSentFailed;

				$return = array('message' => $numberSent, 'finished' => true);
			}
			else
			{
				$return = array('message' => '', 'finished' => true);
			}

			return json_encode($return);
		}

		$messages = array();

		if ($counter == -1)
		{
			if ($serialize['verbose'])
			{
				$messages[] = \JText::_('JAll') . ': ' . count($files) . '<br/>';
			}

			$counter = 0;
			$session->set('AjaxHashCounterTotal' . $hash, count($files), $this->plgName);
		}

		// Number or mails sent per iteration
		for ($i = 0; $i < $this->paramGet('mails_per_iteration'); $i++)
		{
			// ~ sleep(2);
			if (!isset($files[$i]) || !file_exists($files[$i]))
			{
				break;
			}

			$counter++;
			$file = $files[$i];

			if (!class_exists('fakeMailerClass'))
			{
				require_once self::$helpersFolder . '/fakeMailerClass.php';
			}

			$mailer_temp = unserialize(base64_decode(file_get_contents($file)));

			$mailer = \JFactory::getMailer();

			foreach ($mailer_temp as $k => $v)
			{
				if ($k == 'addRecipient' || $k == 'addReplyTo')
				{
					foreach ($v as $recepients)
					{
						$mailer->$k($recepients[0], $recepients[1]);
					}
				}
				elseif ($k == 'Encoding')
				{
					$mailer->$k = $v;
				}
				else
				{
					$mailer->$k($v);
				}
			}

			// $return = '';

			if ($serialize['verbose'])
			{
				$toName     = $mailer->getToAddresses();
				$toName     = $toName[0][1] . ' &lt;' . self::obfuscateEmail($toName[0][0]) . '&gt; ';
				$messages[] = $counter . ' ';
			}

			$session->set('AjaxHashCounter' . $hash, $counter, $this->plgName);

			if (!$serialize['debug'])
			{
				$send = $mailer->Send();
			}
			else
			{
				$send = 'debug';
			}

			if ($send === 'debug')
			{
				if ($serialize['verbose'])
				{
					$messages[] = $toName . ' SEND IMITATION OK';
				}
			}
			elseif ($send !== true)
			{
				if ($serialize['verbose'])
				{
					$messages[]       = $toName . ' ..... <i class="icon-remove"  style="color:red"></i>';
					$numberSentFailed = $session->get('AjaxHashCounterFailed' . $hash, 0, $this->plgName);
					$numberSentFailed++;
					$session->set('AjaxHashCounterFailed' . $hash, $numberSentFailed, $this->plgName);
				}

				$messages[] = '<br/>Error sending email: ' . $send->__toString();
			}
			else
			{
				if ($serialize['verbose'])
				{
					$messages[] = $toName . ' <i class="icon-checkmark"></i>';
				}
			}

			\JFile::delete($file);
			unset($mailer);

			if ($serialize['verbose'])
			{
				$messages[] = '<br/>';
			}
		}

		$return = array('message' => implode(PHP_EOL, $messages), 'finished' => false);

		return json_encode($return);
	}
}
