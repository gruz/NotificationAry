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
//if (!class_exists('GJFieldsFormField')) {include ('gjfields.php');}


class JFormFieldTextareafixed extends JFormFieldTextarea	{
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
	 * @return  string  The field input markup.
	 * @since   11.1
	 */
	function getInput()
	{

		// Initialize some field attributes.
		$class		= $this->element['class'] ? ' class="'.(string) $this->element['class'].'"' : '';
		$disabled	= ((string) $this->element['disabled'] == 'true') ? ' disabled="disabled"' : '';
		$columns	= $this->element['cols'] ? ' cols="'.(int) $this->element['cols'].'" style="width:inherit;"' : '';
		$rows		= $this->element['rows'] ? ' rows="'.(int) $this->element['rows'].'"' : '';

		// Initialize JavaScript field attributes.
		$onchange	= $this->element['onchange'] ? ' onchange="'.(string) $this->element['onchange'].'"' : '';

		$this->element['default'] = JText::_($this->element['default']).$this->Addition('default');
		//~ // Used for testing default
		//~ if (empty($this->value )) {
			//~ $this->value = $this->element['default'];
		//~ }
		if ($this->value == '') { $this->value = (string)$this->element['default'] ;}
		if ((string)$this->element['default'] == $this->value || (string)$this->element['default'] == JText::_($this->value)) {
			$this->value = JText::_($this->value);
			$this->value = str_replace('\n',PHP_EOL,$this->value);
		}
		return '<textarea name="'.$this->name.'" id="'.$this->id.'"' .
				$columns.$rows.$class.$disabled.$onchange.'>' .
				htmlspecialchars($this->value, ENT_COMPAT, 'UTF-8') .
				'</textarea>';
	}

	public function getLabel()
	{
		$output = parent::getLabel();
		$output = str_replace('</label>',$this->Addition('label').'</label>',$output);
		return $output;
		if ($this->hidden)
		{
			return '';
		}

		// Get the label text from the XML element, defaulting to the element name.
		$text = $this->element['label'] ? (string) $this->element['label'] : (string) $this->element['name'];
		$text = $this->translateLabel ? JText::_($text) : $text;

		$text .= $this->Addition('label');

		// Forcing the Alias field to display the tip below
		$position = $this->element['name'] == 'alias' ? ' data-placement="bottom" ' : '';

		$description = ($this->translateDescription && !empty($this->description)) ? JText::_($this->description) : $this->description;
		$displayData = array(
				'text'        => $text,
				'description' => $description,
				'for'         => $this->id,
				'required'    => (bool) $this->required,
				'classes'     => explode(' ', $this->labelclass),
				'position'    => $position
			);

			$text     = $displayData['text'];
			$desc     = $displayData['description'];
			$for      = $displayData['for'];
			$req      = $displayData['required'];
			$classes  = array_filter((array) $displayData['classes']);
			$position = $displayData['position'];

			$id = $for . '-lbl';
			$title = '';

			// If a description is specified, use it to build a tooltip.
			if (!empty($desc))	{
				JHtml::_('bootstrap.tooltip');
				$classes[] = 'hasTooltip';
				$title     = ' title="' . JHtml::tooltipText(null,$desc, 0) . '"';
			}

			// If required, there's a class for that.
			if ($req)
			{
				$classes[] = 'required';
			}

			$return = '<label id="'	.$id.'" for="'	.$for	.'" class="'.implode(' ', $classes)	.'" '	.$title	.$position	.'>'	.$text;
			if ($req) {
				$return .='<span class="star">&#160;*</span>';
			}
			$return .= '</label>';
			return $return;
	}

	function Addition ($fieldNameCore) {
		$fName = (string)$this->element[$fieldNameCore.'Addition'];
		if (!empty($fName)) {
			$text = '';
			$addition = explode(';$',$this->element[$fieldNameCore.'Addition']);
			if (!file_exists(JPATH_SITE.'/'.$addition[0])) {
				JFactory::getApplication()->enqueueMessage(JText::_('LIB_GJFIELDS_LABELADDITION_FILE_DOES_NOT_EXISTS').' : '.$addition[0].'<br/>'.$this->element['label'].' : '.$this->element['name'], 'error');
			}
			else {
				require JPATH_SITE.'/'.$addition[0];
				if (!isset(${$addition[1]})) {
					JFactory::getApplication()->enqueueMessage(JText::_('LIB_GJFIELDS_LABELADDITION_VARIABLE_DOES_NOT_EXISTS').' : '.$addition[1].'<br/>'.$this->element['label'].' : '.$this->element['name'], 'error');
				}
				else {
					$additionVar = $$addition[1];
					if (!is_array($additionVar)) {
						$text .= $additionVar;
					}
					else {
						$text .= implode('',$additionVar);
					}
				}
			}
			return $text;
		}
	}
}

