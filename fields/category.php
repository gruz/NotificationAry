<?php
/**
 * @package    NotificationAry
 *
 * @copyright  0000 Copyleft (є) 2017 - All rights reversed
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

if (!class_exists('GJFieldsFormField'))
{
	include JPATH_ROOT . '/libraries/gjfields/gjfields.php';
}

/**
 * Select list of NotificationAry categories according to the selected rule.
 *
 * Used in com_users user list
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
class NAFormFieldCategory extends \JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  11.1
	 */
	protected $type = 'Category';

	/**
	 * Label markup
	 *
	 * @return  string  The field input markup.
	 */
	public function getInput()
	{
		$options = $this->getOptions();

		if (empty($options))
		{
			return '';
		}

		return parent::getInput();
	}

	/**
	 * Method to get the field options.
	 *
	 * @return  array  The field option objects.
	 *
	 * @since   11.1
	 */
	public function getOptions()
	{
		$items_model = \JModelLegacy::getInstance('Users', 'UsersModel');
		$ruleUniqID = $items_model->getState('filter.naruleUniqID');

		if (empty($ruleUniqID))
		{
			return;
			// ~ return parent::getOptions();
		}

		$app = \JFactory::getApplication();
		// Pass the plugin object to be available in the field to have plugin params parsed there
		$pluginObject = $app->get('plg_system_notificationary');

		// Load NA subscribed options from the user profiles table

		// ~ $ruleIDs = $this->element['ruleids'] ? (string) $this->element['ruleids'] : null;

		$rules = $pluginObject->pparams;

		// Prepare names of plugin settings fields. These strange names are due to the plugin history
		// when the plugin had admin users settings (ausers) and registred users settings (rusers)
		$paramName = 'notifyuser';
		$groupName = 'ausers_' . $paramName . 'groups';
		$itemName = 'ausers_' . $paramName . 's';

		foreach ($rules as $ruleNumber => $rule)
		{
			if ($rule->__ruleUniqID == $ruleUniqID)
			{
				break;
			}
		}

		// Now $rule contains the needed rule options

		$onGroupLevels = $rule->{$groupName};
		$GroupLevels = $rule->{$groupName . 'selection'};

		$pluginObject->rule = $rule;

		// Per category subscribe - 1
		if ($rule->allow_subscribe != 1)
		{
			return null;
		}

		$scope = $rule->{$rule->context_or_contenttype};

		// We load the field just to reuse the getOptions function
		\JForm::addFieldPath(JPATH_LIBRARIES . '/gjfields');

		$formfield = JFormHelper::loadFieldType('gjfields.categoryext');
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

			$options[] = $category;
		}


		return array_merge(parent::getOptions(), $options);
	}
}
