<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Form
 *
 * @copyright   Copyright (C) 2005 - 2011 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

JFormHelper::loadFieldClass('textarea');

/**
 * Textarea with additional features
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
class GJFieldsFormFieldTextareafixed extends JFormFieldTextarea
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  11.1
	 */
	protected $type = 'Textareafixed';

	/**
	 * Method to get the textarea field input markup.
	 * Use the rows and columns attributes to specify the dimensions of the area.
	 *
	 * @return  void
	 */
	public function getInput()
	{
		if (empty($this->value))
		{
			$this->value = JText::_(JText::_($this->element['default']) . $this->Addition('default'));
		}

		$output = parent::getInput();
		$output .= $this->Addition('input');

		return $output;
	}

	/**
	 * Get label override
	 *
	 * Appends additional output to the label
	 *
	 * @return   void
	 */
	public function getLabel()
	{
		$output = parent::getLabel();
		$output = str_replace('</label>', $this->Addition('label') . '</label>', $output);

		return $output;
	}

	/**
	 * Adds additional text to the output form an additional file
	 *
	 * @param   string  $fieldNameCore  Name of the element where additional should be added to
	 *
	 * @return   string  Additional HTML to be added to the output
	 */
	protected function Addition ($fieldNameCore)
	{
		$fName = (string) $this->element[$fieldNameCore . 'Addition'];

		if (!empty($fName))
		{
			$text = '';
			$addition = explode(';$', $this->element[$fieldNameCore . 'Addition']);

			if (!file_exists(JPATH_SITE . '/' . $addition[0]))
			{
				JFactory::getApplication()->enqueueMessage(
					JText::_('LIB_GJFIELDS_LABELADDITION_FILE_DOES_NOT_EXISTS')
						. ' : ' . $addition[0] . '<br/>' . $this->element['label']
						. ' : ' . $this->element['name'],
					'error');
			}
			else
			{
				require JPATH_SITE . '/' . $addition[0];

				if (!isset(${$addition[1]}))
				{
					JFactory::getApplication()->enqueueMessage(
						JText::_('LIB_GJFIELDS_LABELADDITION_VARIABLE_DOES_NOT_EXISTS')
							. ' : ' . $addition[1] . '<br/>' . $this->element['label']
							. ' : ' . $this->element['name'],
						'error');
				}
				else
				{
					$additionVar = ${$addition[1]};

					if (!is_array($additionVar))
					{
						$text .= $additionVar;
					}
					else
					{
						$text .= implode('', $additionVar);
					}
				}
			}

			return $text;
		}
	}
}

// Preserve compatibility
if (!class_exists('JFormFieldTextareafixed'))
{
	/**
	 * Old-fashioned field name
	 *
	 * @since  1.2.0
	 */
				class JFormFieldTextareafixed extends GJFieldsFormFieldTextareafixed
				{
				}
}
