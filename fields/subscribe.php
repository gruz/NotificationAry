<?php
/**
 * @package    NotificationAry
 *
 * @copyright  0000 Copyleft (є) 2017 - All rights reversed
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

use NotificationAry;

if (!class_exists('GJFieldsFormField'))
{
	include JPATH_ROOT . '/libraries/gjfields/gjfields.php';
}

/**
 * Textarea field which allows to load additional data from files
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
class NAFormFieldSubscribe extends GJFieldsFormField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  11.1
	 */
	protected $type = 'Subscribe';

	/**
	 * Label markup
	 *
	 * @return  string  The field input label markup.
	 */
	public function getLabel()
	{
		return;
	}

	/**
	 * Label markup
	 *
	 * @return  string  The field input markup.
	 */
	public function getInput()
	{
		$app = \JFactory::getApplication();

		// Pass the plugin object to be available in the field to have plugin params parsed there
		$pluginObject = $app->get('plg_system_notificationary');

		// Load NA subscribed options from the user profiles table

		$ruleIDs = $this->element['ruleids'] ? (string) $this->element['ruleids'] : null;
		$userID = $this->element['userid'] ? (string) $this->element['userid'] : null;
		$isProfile = $this->element['isProfile'] ? (string) $this->element['isProfile'] : null;

		if (!empty($userID))
		{
			$user = \JFactory::getUser($userID);
		}
		else
		{
			$user = \JFactory::getUser();
		}

		$rules = $pluginObject->pparams;

		if (!empty($ruleIDs) && is_string($ruleIDs))
		{
			$ruleIDs = array_map('trim', explode(',', $ruleIDs));
			// ~ $rules_tmp = $rules;
			foreach ($ruleIDs as $k => $ruleUniqID)
			{
				foreach ($rules as $j => $rule)
				{
					if ($rule->__ruleUniqID == $ruleUniqID)
					{
						$rules_tmp[] = $rule;
					}
				}
			}

			$rules = $rules_tmp;
		}

		// Prepare names of plugin settings fields. These strange names are due to the plugin history
		// when the plugin had admin users settings (ausers) and registred users settings (rusers)
		$paramName = 'notifyuser';
		$groupName = 'ausers_' . $paramName . 'groups';
		$itemName = 'ausers_' . $paramName . 's';

		$output = array();

		foreach ($rules as $ruleNumber => $rule)
		{
			$form = array();

			$msg = null;

			if (!$rule->allow_subscribe)
			{
				if ($app->isSite() && !$isProfile)
				{
					$msg = '<span style="color:red;">['
						. \JText::_('PLG_SYSTEM_NOTIFICATIONARY_RULE_DOESNT_ALLOW_TO_SUBSCRIBE') . ': ' . $rule->__ruleUniqID
						. ']</span>';
					$output[] = $msg;
				}
				continue;
			}

			if (!$rule->isenabled)
			{
				$msg = '<span style="color:red;">['
					. \JText::_('PLG_SYSTEM_NOTIFICATIONARY_RULE_DISABLED') . ': ' . $rule->__ruleUniqID
					. ']</span>';
				$output[] = $msg;
				continue;
			}

			$onGroupLevels = $rule->{$groupName};
			$GroupLevels = $rule->{$groupName . 'selection'};


			$pluginObject->rule = $rule;

			if (!$pluginObject->_checkAllowed($user, $paramName = 'notifyuser', $fieldNamePrefix = 'ausers'))
			{
				// Debug line
				// ~ dump(': User is not allowed to subscribe', $rule->__ruleUniqID);
				continue;
			}

			$form['title'] = $rule->{'{notificationgroup'}[0];

			// Determine selectbox to subscribe to all/none/selected categories or the rule state
			switch ($rule->allow_subscribe)
			{
				// Per category subscribe
				case '1':

				// Per rule subscribe
				case '2':
					$allowedCategories = NotificationAry::getProfileData($user->id, $rule->__ruleUniqID);

					if (empty($allowedCategories))
					{
						if ($rule->allow_subscribe_default)
						{
							$selectAllValue = 'all';
						}
						else
						{
							$selectAllValue = 'none';
						}
					}
					elseif (in_array('subscribed', $allowedCategories))
					{
						$selectAllValue = 'all';
					}
					elseif (in_array('unsubscribed', $allowedCategories))
					{
						$selectAllValue = 'none';
					}
					else
					{
						$selectAllValue = 'selected';
					}
			}

			switch ($rule->allow_subscribe)
			{
				// Per category subscribe
				case '1':
					/*
					switch ($rule->context_or_contenttype)
					{
						case 'content_type':
							// This is the id of the content type in Joomla. E.g. 1 for com_content categories
							$scope = $rule->content_type;
							break;
						case 'context':
							$scope = $rule->content_type;
							break;
						default :

							break;
					}
					*/

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

					$options = array();
					$values = array();

					// Iterate categories and and add only needed ones and checked if needed.
					foreach ($categories as $k => $category)
					{
						/*
								<field name="ausers_articlegroups" maxrepeatlength="1"
								 		type="gjfields.variablefield" basetype="list" default="0"
								 		label="PLG_SYSTEM_NOTIFICATIONARY_FIELD_CATEGORIES" description="PLG_SYSTEM_NOTIFICATIONARY_FIELD_NOTIFY_ON_DESC">
									<option value="1">PLG_SYSTEM_NOTIFICATIONARY_FIELD_SELECTION</option>
									<option value="2">PLG_SYSTEM_NOTIFICATIONARY_FIELD_EXCLUDE_SELECTION</option>
									<option value="0">JALL</option>
								</field>
						 */

						$continue = false;

						switch ($rule->ausers_articlegroups)
						{
							// If selected categories to be included
							case '1':
								if (!in_array($category->value, $rule->ausers_articlegroupsselection))
								{
									$continue = true;
								}
								break;

							// If selected categories to be excluded
							case '2':
								if (in_array($category->value, $rule->ausers_articlegroupsselection))
								{
									$continue = true;
								}
								break;
							default :

								break;
						}

						if ($continue)
						{
							continue;
						}

						if (in_array($category->value, $allowedCategories))
						{
							$values[] = $category->value;
						}

						$options[] = '<option value="' . $category->value . '"><![CDATA[' . $category->text . ']]></option>';
					}

					$form['options'] = $options;
					$form['values'] = $values;
					$form['selectAllValue'] = $selectAllValue;
					$form['sublayout'] = 'percategory';

					break;

				// The whole rule subscribe
				case '2':
					$form['selectAllValue'] = $selectAllValue;
					$form['sublayout'] = 'perrule';
					break;
				default :

					break;
			}

			// Make layout path in the plugin folder dynamic, don't hardcode it
			$pluginLayoutPath = str_replace(JPATH_PLUGINS, '', __DIR__);
			$pluginLayoutPath = explode('/', $pluginLayoutPath);
			array_pop($pluginLayoutPath);
			$pluginLayoutPath = implode('/', $pluginLayoutPath);
			$pluginLayoutPath = JPATH_PLUGINS . $pluginLayoutPath . '/layouts';

			$form['rule'] = $rule;
			$form['debug'] = false;
			$form['hash'] = '_' . uniqid();
			$form['user'] = $user;

			$layout = new JLayoutFile('nasubscribe', null, array('debug' => $form['debug'], 'client' => 1));

			$form['layout'] = $layout;

			$paths = $layout->getIncludePaths();
			$paths[] = $pluginLayoutPath;
			$layout->setIncludePaths($paths);

			$form = $layout->render($form);

			$output[] = $form;

		}

		$output = implode(PHP_EOL, $output);

		$doc = \JFactory::getDocument();

											// It's a must part
		$url_ajax_plugin = \JURI::base() . '?option=com_ajax&format=raw'

				// $this->plgType should contain your plugin group (system, content etc.),
				// E.g. for a system plugin plg_system_menuary it should be system
				. '&group=' . 'system'

				// The function from plugin you want to call

				// The PHP functon must start from onAjax e.g. PlgSystemValidationAry::onAjaxValidate,
				// while here we should use only after onAjax - `validate`
				. '&plugin=notificationArySubscribeUpdate'

				// It's optional to add to the link. Just in case to ignore link result caching.
				. '&uniq=' . uniqid();

		// Add the link to the HTML DOM to let later your ajax JS script get the link to call
		// You'll be able to get the link in JS like <code>var link = Joomla.optionsStorage.notificationary.ajax_url;</code>

		$doc->addScriptOptions('notificationary', array('ajax_url' => $url_ajax_plugin ));
		$doc->addScriptOptions('notificationary', array('task' => 'subscription'));

		\JPluginGJFields::addJSorCSS('ajax_subscribe.js', 'plg_system_notificationary', static::$debug);
		\JPluginGJFields::addJSorCSS('spinning.css', 'plg_system_notificationary', static::$debug);

		\JText::script('PLG_SYSTEM_NOTIFICATIONARY_LOADING');

		return $output;
	}
}
