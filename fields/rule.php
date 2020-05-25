<?php
/**
 * @package    NotificationAry
 *
 * @copyright  0000 Copyleft (є) 2017 - All rights reversed
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

if (!class_exists('\\GJFieldsFormField'))
{
	include JPATH_ROOT . '/libraries/gjfields/gjfields.php';
}

/**
 * Select list of NotificationAry rules. Used in com_users user list
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
class NAFormFieldRule extends \JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  11.1
	 */
	protected $type = 'Rule';

	/**
	 * Method to get the field options.
	 *
	 * @return  array  The field option objects.
	 *
	 * @since   11.1
	 */
	public function getOptions()
	{
		$app = \JFactory::getApplication();

		// Pass the plugin object to be available in the field to have plugin params parsed there
		$pluginObject = $app->get('plg_system_notificationary');

		// Load NA subscribed options from the user profiles table

		// ~ $ruleIDs = $this->element['ruleids'] ? (string) $this->element['ruleids'] : null;

		$rules = $pluginObject->pparams;

		$options = array();

		foreach ($rules as $ruleNumber => $rule)
		{
			if (!$rule->allow_subscribe || !$rule->isenabled)
			{
				unset($rules[$ruleNumber]);
				continue;
			}

			$option = new \stdClass;
			$option->value = $rule->__ruleUniqID;
			$option->text = $rule->{'{notificationgroup'}[0];

			$options[] = $option;
		}

		$options = array_merge(parent::getOptions(), $options);

		return $options;
	}
}
