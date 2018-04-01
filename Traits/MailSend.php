<?php
/**
 * Sending emails
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
trait MailSend
{

	/**
	 * Sends emails to all passed in array users
	 *
	 * @param   array  &$Users_to_send  Users to be notified
	 *
	 * @return   void
	 */
	protected function _send_mails (&$Users_to_send)
	{
		if (empty($Users_to_send) )
		{
			return;
		}

		$app = \JFactory::getApplication();

		if ($this->paramGet('forceNotTimeLimit'))
		{
			$maxExecutionTime = ini_get('max_execution_time');
			set_time_limit(0);
		}

		foreach ($Users_to_send as $key => $value)
		{
			if (!empty($value['id']))
			{
				// ~ $user = \JFactory::getUser($value['id']);
				$user = self::getUser($value['id']);
			}
			else
			{
				$user = self::getUserByEmail($value['email']);
			}

			if (empty($user->id))
			{
				$user = \JFactory::getUser(0);
				$user->set('email', $value['email']);
			}

			$mail = $this->_buildMail($user);

			if (!$mail)
			{
				continue;
			}

			if ($this->isAjax)
			{
				$mailer = new fakeMailerClass;
			}
			else
			{
				// This object is not serializable, so I need to use a simple object to store and pass information to the ajax part
				$mailer = \JFactory::getMailer();
			}

			if ($this->rule->emailformat != 'plaintext')
			{
				$mailer->isHTML(true);
				$mailer->Encoding = 'base64';
			}

			$mailer->setSubject($mail['subj']);

			$senderEmail = !empty($this->rule->sender_email) ? $this->rule->sender_email : $app->getCfg('mailfrom');
			$senderName = !empty($this->rule->sender_name) ? $this->rule->sender_name : $app->getCfg('fromname');
			$mailer->setSender(array($senderEmail, $senderName));

			$replyToEmail = !empty($this->rule->replyto_email) ? $this->rule->replyto_email : $app->getCfg('mailfrom');
			$replyToName = !empty($this->rule->replyto_name) ? $this->rule->replyto_name : $app->getCfg('fromname');

			$mailer->addReplyTo($replyToEmail, $replyToName);

			// ~ $mailer->setSender(array($app->getCfg('mailfrom'), $app->getCfg('fromname')));

			$mailer->addRecipient($mail['email'], $user->name);

			if (isset($this->rule->attachpreviousversion) )
			{
				foreach ($this->rule->attachpreviousversion as $k => $v)
				{
					if (isset($this->attachments[$v]))
					{
						$mailer->addAttachment($this->attachments[$v]);
					}
				}
			}

			if (isset($this->rule->attachdiffinfo) )
			{
				foreach ($this->rule->attachdiffinfo as $k => $v)
				{
					if (isset($this->attachments[$v]))
					{
						$mailer->addAttachment($this->attachments[$v]);
					}
				}
			}

			$curr_root = parse_url(JURI::root());
			$live_site_host = $curr_root['scheme'] . '://' . $curr_root['host'] . '/';
			$live_site = JURI::root();

			$link = $live_site . 'index.php?unsubscribe=' . $this->rule->__ruleUniqID
								. '&email=' . $user->email . '&hash=' . md5($user->id . $this->rule->__ruleUniqID);

			if ($this->rule->messagebodysource == 'hardcoded')
			{
				$includeunsubscribelink = $this->rule->ausers_includeunsubscribelink;

				if ($includeunsubscribelink)
				{
					if ($this->rule->emailformat == 'plaintext')
					{
						$mail['body'] .= PHP_EOL . PHP_EOL . \JText::_('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBE') . ': ' . $link;
					}
					else
					{
						$mail['body'] .= '<br/><br/><a href="' . $link . '">' . \JText::_('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBE') . '</a>';
					}
				}
			}
			else
			{
				$mail['body'] = str_replace('%UNSUBSCRIBE LINK%', $link, $mail['body']);
			}

			$mailer->setBody($mail['body']);

			if ($this->isAjax)
			{
				if (!isset($this->ajaxHash))
				{
					$this->ajaxHash = uniqid();
				}

				$mailer_ser = base64_encode(serialize($mailer));
				$tmpPath = \JFactory::getApplication()->getCfg('tmp_path');
				$filename = $this->plgName . '_' . $this->ajaxHash . '_' . uniqid();
				JFile::write($tmpPath . '/' . $filename, $mailer_ser);

				continue;
			}

			$send = $mailer->Send();

			if ($send !== true)
			{
				/**
				 * Broken email sends
				 *
				 * @var array
				 */
				$this->brokenSends[] = $mail['email'];
			}
		}

		if ($this->paramGet('forceNotTimeLimit'))
		{
			set_time_limit($maxExecutionTime);
		}
	}

}
