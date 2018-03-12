<?php
/**
 * @package    GJFileds
 *
 * @copyright  0000 Copyright (C) All rights reversed.
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL or later
 */

JFormHelper::loadFieldClass('list');

defined('JPATH_PLATFORM') or die;

/**
 * This field replaces original list field because the original one doesn't
 * allow to have multiple lists with several default values
 *
 * @package     Joomla.Platform
 * @subpackage  Form
 * @since       11.1
 */
class GJFieldsFormFieldList extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  11.1
	 */
	protected $type = 'GJList';

	/**
	 * Method to get the field input markup for a generic list.
	 * Use the multiple attribute to enable multiselect.
	 *
	 * @return  string  The field input markup.
	 *
	 * @since   11.1
	 */
	protected function getInput()
	{
		if (!empty($this->element['placeholder']))
		{
			$this->class = $this->element['class'] . '" data-placeholder="' . JText::_($this->element['placeholder']) . '"';
		}

		// This is a fix to allow multiple default values
		if ($this->multiple && !empty($this->value) && !is_array($this->value))
		{
			$this->value = array_map('trim', explode(",", $this->value));
		}

		return parent::getInput();
	}
}

// Preserve compatibility
if (!class_exists('JFormFieldGJList'))
{
	/**
	 * Old-fashioned field name
	 *
	 * @since  1.2.0
	 */
				class JFormFieldGJList extends GJFieldsFormFieldList
				{
				}
}
