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
	 * Adds a badge to the menu items created and contorlled by this plugin
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

		if (($option == 'com_users') && ($view == 'users') && $type == 'html')
		{
			$items_model = \JModelLegacy::getInstance('Users', 'UsersModel');
			$ruleUniqID = $items_model->getState('filter.naruleUniqID');
			$nacategory = $items_model->getState('filter.nacategory');

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
			$uri = new JUri($matches[2]);
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


}
