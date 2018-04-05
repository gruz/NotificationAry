<?php
/**
 * Subscribe
 *
 * @package     NotificationAry
 *
 * @author      Gruz <arygroup@gmail.com>
 * @copyright   Copyleft (є) 2018 - All rights reversed
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */


namespace NotificationAry\Traits;

use Joomla\CMS\HTML\HTMLHelper;

/**
 * A helper trait
 *
 * @since 0.2.17
 */
trait Subscribe
{

	/**
	 * Replaces plugin code with the subscribe/unsubscribe form if needed
	 *
	 * If the user is guest, then just removes the plugin code.
	 * Removes the code if the user doesn't match any NA rule or there is no such a rule
	 * Replaces the plugin code with the subscribe/unsubscribe form otherwise.
	 * The plugin code format: {na subscribe 5889f0565a762} where 5889f0565a762 is the
	 * NA rule UniqID. See the screenshot to get the idea
	 * http://view.xscreenshot.com/a3dbc86f705ab26c2c2b15627b40dc52
	 *
	 * @param   object  $pluginObject  NA plugin object
	 * @param   array   $body          HTML body
	 * @param   array   $matches       Plugin code matches found
	 *
	 * @return   mixed  HTML string with body if something was replaced or false if no replace occurred
	 */
	public static function pluginCodeReplace($pluginObject, $body, $matches)
	{
		// Load NA subscribed options from the user profiles table
		$user = \JFactory::getUser();

		$rules = $pluginObject->pparams;

		$app = \JFactory::getApplication();
		$app->set('plg_system_notificationary', $pluginObject);

		$replaced = false;

		$replacements = array();

		// Prepare names of plugin settings fields. These strange names are due to the plugin history
		// when the plugin had admin users settings (ausers) and registred users settings (rusers)
		$paramName = 'notifyuser';
		$groupName = 'ausers_' . $paramName . 'groups';
		$itemName = 'ausers_' . $paramName . 's';

		\JForm::addFieldPath($pluginObject->plg_path . '/fields');

		$formfield = \JFormHelper::loadFieldType('na.subscribe');

		foreach ($matches as $keymatches => $match)
		{
			$replace_code = $match[0];
			$ruleUniqID = $match[1];

			$form = array();

			if (\JFactory::getUser()->guest)
			{
				$replacements[$replace_code] = '';
				$replaced = true;

				continue;
			}

			$msg = null;

			foreach ($rules as $ruleNumber => $rule)
			{
				if ($rule->__ruleUniqID != $ruleUniqID)
				{
					continue;
				}

				if (!$rule->allow_subscribe)
				{
					$replacements[$replace_code] = '<span style="color:red;">['
						. \JText::_('PLG_SYSTEM_NOTIFICATIONARY_RULE_DOESNT_ALLOW_TO_SUBSCRIBE') . ': ' . $ruleUniqID
						. ']</span>';
					continue;
				}

				if (!$rule->isenabled)
				{
					$replacements[$replace_code] = '<span style="color:red;">['
						. \JText::_('PLG_SYSTEM_NOTIFICATIONARY_RULE_DISABLED') . ': ' . $ruleUniqID
						. ']</span>';
					continue;
				}

				$element = simplexml_load_string(
					'
						<field
							name="subscribe"
							type="na.subscribe"
							ruleids="' . $ruleUniqID . '"
							label="PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIBE_SELECT_LABEL"
						/>
					');

				$formfield->setup($element, '');
				$replacements[$replace_code] = $formfield->renderField();
			}
		}

		// Try to remove wrapping <p> tag. Stupid way, a preg function should be used. Lazy to implement.
		$patterns = array(
			'<p>' . $replace_code . '</p>',
			'<p>' . PHP_EOL . $replace_code . PHP_EOL . '</p>',
		);

		$replaced = false;

		foreach ($replacements as $replace_code => $form)
		{
			foreach ($patterns as $k => $pattern)
			{
				if (strpos($body, $pattern) !== false)
				{
					$replace_code = $pattern;
					break;
				}
			}

			$body_tmp = $body;

			$body = str_replace(array_keys($replacements), $replacements, $body);

			if ($body_tmp !== $body)
			{
				$replaced = true;
			}
		}

		if ($replaced)
		{
			return $body;
		}

		// To avoid later run setBody method
		return false;
	}
	
	/**
	 * Get profile data (used from subscribe)
	 *
	 * @param   int     $userid  User id
	 * @param   string  $ruleid  NA rule id
	 * @param   bool    $force   Force DB query rerun
	 *
	 * @return   string
	 */
	public static function getProfileData($userid, $ruleid, $force = false)
	{
		static $queried = false;
		static $subscribeData;

		if ($queried && isset($queried[$ruleid]) && $queried[$ruleid] && !$force)
		{
			return $subscribeData[$ruleid];
		}

		// Load the profile data from the database.
		$db = \JFactory::getDbo();
		$db->setQuery(
			'SELECT profile_value FROM #__user_profiles'
				. ' WHERE user_id = ' . (int) $userid . " AND profile_key LIKE " . $db->q('notificationary.' . $ruleid . '.%')
				. ' ORDER BY ordering'
		);

		$subscribeData[$ruleid] = $db->loadColumn();

		$queried[$ruleid] = true;

		return $subscribeData[$ruleid];
	}

	/**
	 * Adds a badge to Users subscribed to NA
	 *
	 * The fuction is taken from a Ganty particle
	 *
	 * @return  void
	 */
	public static function addUserlistBadges()
	{
		$document = \JFactory::getDocument();
		$type   = $document->getType();

		$app = \JFactory::getApplication();
		$option = $app->input->getString('option');
		$view   = $app->input->getString('view');
		$task   = $app->input->getString('task');

		if ('com_users' === $option && 'users' === $view && 'html' === $type)
		{
			$itemsModel = \JModelLegacy::getInstance('Users', 'UsersModel');
			$ruleUniqID = $itemsModel->getState('filter.naruleUniqID');
			$nacategory = $itemsModel->getState('filter.nacategory');

			if (empty($ruleUniqID))
			{
				return;
			}

			$app = \JFactory::getApplication();
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

			$body = $app->getBody();

			$body = preg_replace_callback(
				'/(<a\s[^>]*href=")([^"]*)("[^>]*>)(.*)(<\/a>)/siU',
				function($matches) use ($rule)
				{
					return self::appendHtml($matches, $rule);
				},
				$body
			);

			$app->setBody($body);
		}
	}


	/**
	 * Appends HTML to menu items
	 *
	 * @param   array   $matches  Parsed by preg link
	 * @param   string  $rule     NA rule
	 *
	 * @return   string  Html with MenuAry badges inserted
	 */
	public static function appendHtml(array $matches, $rule)
	{
		$html = $matches[0];

		if (strpos($matches[2], 'task=user.edit'))
		{
			$uri = new \JUri($matches[2]);
			$id = (int) $uri->getVar('id');

			if ($id && in_array($uri->getVar('option'), array('com_users')) )
			{
				$profileData = self::getProfileData($id, $rule->__ruleUniqID, true);

				if (empty($profileData))
				{
					if ($rule->allow_subscribe_default == 1)
					{
						$profileData[] = 'subscribed';
					}
					else
					{
						$profileData[] = 'unsubscribed';
					}
				}

				if (in_array('unsubscribed', $profileData))
				{
					$iconClass = '';
					$iconText = \JText::_('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBED_FROM_ALL');
					$titleText = '<span class="label label-info">' . \JText::_('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBED_FROM_ALL');
				}
				elseif (in_array('subscribed', $profileData))
				{
					$iconClass = '';
					$iconText = \JText::_('PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIBED_TO_ALL');
					$titleText = '<span class="label label-success">' . \JText::_('PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIBED_TO_ALL');
				}
				else
				{
					$scope = $rule->{$rule->context_or_contenttype};

					// We load the field just to reuse the getOptions function
					\JForm::addFieldPath(JPATH_LIBRARIES . '/gjfields');

					$formfield = \JFormHelper::loadFieldType('gjfields.categoryext');
					$element = simplexml_load_string(
						'
							<field name="subscribe_categories" maxrepeatlength="1" type="gjfields.variablefield"
								basetype="gjfields.categoryext" extension="com_content"

								context_or_contenttype="' . $rule->context_or_contenttype . '"

								scope="' . $scope . '"
								published="1"

								source_parameter="context_or_contenttype,content_type,context"
								target_parameter="context_or_contenttype,content_type,context"
								multiple="multiple" size="20" show_uncategorized="1" label="PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIBE_TO_CATEGORY"
								description="" class="chzn-custom-value"
								hint="PLG_SYSTEM_NOTIFICATIONARY_FIELD_CATEGORIES_CUSTOM"/>

						');

					$formfield->setup($element, '', $rule->__ruleUniqID);

					// Here we get all categories for the NA rule. So we need to filter out
					// not allowed to be subscribed to categories.
					$categories = $formfield->getOptions(true);

					$iconText = \JText::_('PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIBED_TO') . ':<br/> ';

					foreach ($categories as $k => $category)
					{
						if (in_array($category->value, $profileData))
						{
							$iconText .= $category->text . '<br/>';
						}
					}

					$iconClass = 'icon-help';
					$titleText = '<span class="label">' . \JText::_('PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIBED_TO');
				}

				// ~ $iconClass = 'icon-help';
				// ~ icon-checkbox-partial

				$html = $matches[1] . $uri . $matches[3] . $matches[4] . $matches[5];
				$html .= ' ' . $titleText . '
					<span
						onMouseOver="this.style.color=\'#00F\'"
						onMouseOut="this.style.color=\'#000\'"
						class="hasTooltip ' . $iconClass . '" style="
						cursor: help;"
						title="' . HTMLHelper::tooltipText($iconText) . '"></span>' . '</span>';
			}
		}

		return $html;
	}

	/**
	 * Adds additional fields to the user editing form
	 *
	 * @param   \JForm  $form  The form to be altered.
	 * @param   mixed   $data  The associated data for the form.
	 *
	 * @return  boolean
	 *
	 * @since   1.6
	 */
	public function userProfileFormHandle($form, $data)
	{
		if (!$this->checkIsForm($form))
		{
			$this->_subject->setError('JERROR_NOT_A_FORM');

			return false;
		}

		// Check we are manipulating a valid form.
		$name = $form->getName();

		$app = \JFactory::getApplication();

		if (!in_array($name, array('com_admin.profile', 'com_users.user', 'com_users.profile', "com_users.users.default.filter")))
		{
			return true;
		}

		// Pass the plugin object to be available in the field to have plugin params parsed there
		$app->set($this->plgFullName, $this);

		if ("com_users.users.default.filter" === $name)
		{
			\JForm::addFormPath(__DIR__ . '/../forms');
			$form->loadFile('filter', false);

			$items_model = \JModelLegacy::getInstance('Users', 'UsersModel');
			$ruleUniqID  = $items_model->getState('filter.naruleUniqID');
			/** // ##mygruz20170214152631 DO NOT DELETE.
				* I tried to make the filters be opened upong a page load
				* but this didn't work. Not to invest
			*/

			return true;
		}

		$jinput = \JFactory::getApplication()->input;
		$userID = $jinput->get('id', null);

		if (empty($userID))
		{
			return;
		}

		// Add the registration fields to the form.
		\JForm::addFormPath(__DIR__ . '/../forms');
		$form->loadFile('subscribe', false);
		$form->setFieldAttribute('subscribe', 'userid', $userID, 'nasubscribe');
		$form->setFieldAttribute('subscribe', 'isProfile', true, 'nasubscribe');

		$doc = \JFactory::getDocument();
		$js  = '
			jQuery(document).ready(function($){
				var label = $(".nasubscribe").closest("div.control-group").find(".control-label:first").text().trim();
				if (label.length === 0)
				{
					$(".nasubscribe").closest("div.controls").css("margin-left", "0");
				}

			});
		';
		$doc->addScriptDeclaration($js);
	}


}
