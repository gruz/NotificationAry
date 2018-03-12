<?php
/**
 * @package    GJFileds
 *
 * @copyright  0000 Copyright (C) All rights reversed.
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL or later
 */

defined('JPATH_PLATFORM') or die;
jimport('joomla.form.formfield');
jimport('joomla.form.helper');

if (!class_exists('GJFieldsFormField'))
{
	include 'gjfields.php';
}

/**
 * Variable field class
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
class GJFieldsFormFieldVariablefield extends GJFieldsFormField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 *
	 * @since  11.1
	 */
	protected $type = 'variablefield';

	/**
	 * getLabel
	 *
	 * If it meets a group start like `{group1`, then it sets an sets a flag,
	 * and later, till the end on the last open group, doesn't output
	 * anything, but saves the XML element object to $GLOBALS['variablefield'].
	 * Only when the last open group is closed, like `group1}`, then the class knows
	 * the XML structure - which fields are inside the group.
	 * After we have the whole picture of the group structure, we output
	 * group once or several times - depending on the values in the
	 * strating group field `{group1` value.
	 * So we need to get the whole group structure and then to clone it
	 * as many times as we need.
	 *
	 * This function determines either to store current field and sets some flags,
	 * or Either outside the group OR at the stage of outputting it shows normal label.
	 *
	 * @param   object  $form  Form object
	 */
	public function __construct($form = null)
	{
		parent::__construct($form);

		// Add CSS and JS once, define base global flag - runc only once

		// Iss used for building joomfish links
		$this->langShortCode = null;
		$this->default_lang = JComponentHelper::getParams('com_languages')->get('site');
		$language = JFactory::getLanguage();
		$language->load('lib_gjfields', __DIR__, 'en-GB', true);
		$language->load('lib_gjfields', __DIR__, $this->default_lang, true);
	}

	/**
	 * A helper function
	 *
	 * @return   void
	 */
	protected function prepareGroupField ()
	{
		$basetype = isset($this->element['basetype']) ? $this->element['basetype'] : 'text';
		$basetype = (string) $basetype;

		$first_char = JString::substr($this->element['name'], 0, 1);
		$last_char = JString::substr($this->element['name'], JString::strlen($this->element['name']) - 1, 1);

		// If start or end of group
		if ($basetype == 'group')
		{
			// Починаю нову групу (може бути будь-якого рівня вкладеності)
			if ($first_char == "{")
			{
				$GLOBALS['variablefield']['output'] = false;

				// Lower in the code I know, that last element of array $GLOBALS['variablefield']['current_group'] is always the current group.
				$GLOBALS['variablefield']['current_group'][] = (string) $this->element['name'] . '}';
				$this->groupstate = 'start';

				// I must clone
				$GLOBALS['variablefield']['fields'][] = clone $this;
			}

			/* Закінчую поточну групу і перевіряю, чи завершено останній блок
				* Якщо завершено останній блок, то роблю ітерацію
				* по всіх збережених полях і виводжу їх у getInput,
			*/
			elseif ($last_char == "}")
			{
				$this->groupstate = 'end';
				$GLOBALS['variablefield']['fields'][] = clone $this;
				array_pop($GLOBALS['variablefield']['current_group']);

				if (empty($GLOBALS['variablefield']['current_group']))
				{
					$GLOBALS['variablefield']['output'] = true;
				}
			}
		}
		// If in element from inside the group
		elseif (!empty($GLOBALS['variablefield']['current_group']))
		{
			$this->groupstate = 'continue';
			$this->defaultvalue = $this->value;
			$GLOBALS['variablefield']['fields'][] = clone $this;
		}
	}

	/**
	 * Returns either field label HTML or nothing if processing a group of fields
	 *
	 * @param   bool  $flag  If is a group of fields
	 *
	 * @return   mixed  Null or HTML
	 */
	protected function getLabel($flag = false)
	{
$debug = true;
$debug = false;

		// Here I handle dependant load of categories
		static $count = array();
		static $from_params = null;
		static $defaults = array();

		if (isset($this->element['label']))
		{
			$this->element['label'] = $this->_replaceNestedJtextConstants($this->element['label']);
		}

		if (isset($this->element['description']))
		{
			$this->element['description'] = $this->_replaceNestedJtextConstants($this->element['description']);
		}

		if (!$flag || !isset($this->origname))
		{
			$this->origname = (string) $this->fieldname;
		}

		if (!isset($defaults[$this->origname]))
		{
			$default = $this->getAttribute('default');
			$defaults[$this->origname] = $default;
		}

		$this->defaults = $defaults;

		if (isset($this->element['source_parameter']) && isset($this->element['target_parameter']) && $flag)
		{
			// $this_field_name = $this->name;
			$this_field_name = (string) $this->element['name'];
			$this_field_name = $this->origname;

if ($debug)
{
	dumpMessage($this_field_name);
	dumpTrace();
}

			if (empty($from_params))
			{
				$key_in_params = (string) $GLOBALS['variablefield']['fields'][0]->element['name'];
				$from_params = $GLOBALS['variablefield']['fields'][0]->form->getData()->toObject()->params->{$key_in_params};

if ($debug)
{
	dump($from_params, '$from_params');
}
			}

if ($debug)
{
	dump($this, '$this');
	dumpMessage($this_field_name);
}

			if (!isset($count[$this_field_name]))
			{
				$count[$this_field_name] = 0;
			}
			else
			{
				$count[$this_field_name]++;
				$count[$this_field_name]++;
			}

			$index = $count[$this_field_name];
			$source_parameters = explode(',', (string) $this->element['source_parameter']);
			$target_parameters = explode(',', (string) $this->element['target_parameter']);

if ($debug)
{
	dump((string) $this->element['target_parameter'], (string) $this->element['source_parameter']);
}

			$get_joomla_content_type_by_id = (string) $this->element['get_joomla_content_type_by_id'];

			foreach ($source_parameters as $k => $source_parameter)
			{
				$values = array();

if ($debug)
{
	dump($index, '$index');
}

				if (!isset($from_params[$source_parameter]))
				{
					for ($i = 0; $i < $index + 1; $i++)
					{
						if ($i == $index)
						{
							$values[] = $defaults[$source_parameter];
						}
						else
						{
							$values [] = null;
						}
					}

if ($debug)
{
	dump($values, '$values 1');
}
				}
				else
				{
					$values = $from_params[$source_parameter];

if ($debug)
{
	dump($values, '$values 2');
}
				}

				if (is_array($values[$index]))
				{
					$this->element[$target_parameters[$k]] = implode(',', $values[$index]);
				}
				else
			{
if ($debug)
{
	dumpMessage(' Setting <b>' . $target_parameters[$k] . '</b> to  <b>' . $values[$index] . '</b>');
}

					$this->element[$target_parameters[$k]] = $values[$index];
				}
			}
		}

		$basetype = isset($this->element['basetype']) ? $this->element['basetype'] : 'text';
		$basetype = (string) $basetype;

		// If start or end of group
		if ($basetype == 'group' || !empty($GLOBALS['variablefield']['current_group']))
		{
			return null;
		}
		else
		{
			// Let show the script, that the group has ended
			$formfield = JFormHelper::loadFieldType($basetype);
			$formfield->setup($this->element, '');

			return $formfield->getLabel();
		}

		return null;
	}

	/**
	 * Method to get the field input markup.
	 *
	 * @return  string  The field input markup.
	 */
	public function getInput()
	{
		$this->prepareGroupField();

		// If we process a field, not a group, then retun HTML for field (but prepared in my function getInputHelper() )
		if (!isset($GLOBALS['variablefield']))
		{
			return $this->getInputHelper() . PHP_EOL;
		}

		// If we process a group and it's not OUTPUT, means not the end of the last open group, then output nothing
		// but output all the fields and maybe several time otherwise

		// If it's not final stage, then just return
		if ($GLOBALS['variablefield']['output'] !== true)
		{
			return null;
		}

		$groupStartField = $GLOBALS['variablefield']['fields'][0];
		$current_values_temp = (array) $groupStartField->value;
		$current_group = $groupStartField->fieldname;

		// Тут будуть зберігатись пересортовані значення полів для активної групи
		$current_values = array();

		foreach ($current_values_temp as $fieldname => $values)
		{
			$group_number = 0;
			$values = (array) $values;

			foreach ($values as $value)
			{
				if ($value == 'variablefield::' . $current_group)
				{
					$group_number++;
				}
				elseif (is_array($value) && $value[0] == 'variablefield::' . $current_group){
					$group_number++;
				}
				elseif (is_array($value) ) {
					$current_values[$group_number][$fieldname][0][] = $value[0];
				}
				else
			{
					$current_values[$group_number][$fieldname][] = $value;
				}
			}
		}

		$output = '';
		$arrayLength = count($current_values);
		$length	= isset($groupStartField->element['length']) ? (int) $groupStartField->element['length'] : 1;
		$length = max($length, $arrayLength);

		// If the maximum field length is 1, the we do not need to output the clone buttons
		$maxRepeatLength	= isset($groupStartField->element['maxrepeatlength']) ? (int) $groupStartField->element['maxrepeatlength']: 0;

		if ($maxRepeatLength > 0)
		{
			$length = min($length, $maxRepeatLength);
		}

		for ($i = 0; $i < $length; $i++)
		{
			// Iterate all fields from the XML file inside the group
			for ($k = 0; $k < count($GLOBALS['variablefield']['fields']); $k++)
			{
				$field = $GLOBALS['variablefield']['fields'][$k];

				// В залежності чи поточний елемент - це start,
				// end групи чи всередині групи continue, робимо своє
				switch ($field->groupstate)
				{
					case 'start':
						$field->group_header = isset($current_values[$i][(string) $field->fieldname][0])?$current_values[$i][(string) $field->fieldname][0]:'';
						$field->open = isset($current_values[$i][(string) $field->fieldname][1])?$current_values[$i][(string) $field->fieldname][1]:'1';
						$ruleUniqID = !empty($current_values[$i]['__ruleUniqID'][0])?$current_values[$i]['__ruleUniqID'][0]:uniqid();
						$output .= $field->groupStartHTML($ruleUniqID);
						break;
					case 'end':
						$output .= $field->groupEndHTML();
						break;
					case 'continue':
						// Here, if the field is inside a group
					default :
						// Rework field name to take the group into the considerations
						$field->name = 'jform[params][' . $current_group . '][' . (string) $field->fieldname . ']';

						$field->value = isset($current_values[$i][(string) $field->fieldname])?$current_values[$i][(string) $field->fieldname]:$field->defaultvalue;

						if (version_compare(JVERSION, '3.0', 'ge') && $this->HTMLtype == 'div' )
						{
							$output .= PHP_EOL . '<div class="control-group">' . PHP_EOL;
							$output .= PHP_EOL . '<div class="control-label">'
																			. PHP_EOL . $field->getLabel(true) . PHP_EOL
																		. '</div><!-- control-label of a variable field -->' . PHP_EOL;
							$output .= PHP_EOL . '<div class="controls">'
														. PHP_EOL . $field->getInputHelper() . PHP_EOL
												. '</div><!-- controls of a variable field -->' . PHP_EOL;
							$output .= PHP_EOL . '</div><!-- control-group -->';
						}
						else
						{
							$output .= $field->getLabel(true) . PHP_EOL;

							// This outputs the field several times, if it's a repeatable
							$output .= $field->getInputHelper() . PHP_EOL;
						}

						switch ((string) $field->element['basetype'])
						{
							case 'blockquote':
							case 'toggler':
							case 'note':
							case 'notefixed':
							case 'spacer':

								break;
							default :
								// Let show the script, that the group has ended
								$formfield = JFormHelper::loadFieldType('hidden');
								$formfield->setup($field->element, '');
								$formfield->value = 'variablefield::' . $current_group;
								$output .= $formfield->getInput() . PHP_EOL;
								break;
						}

						if (!version_compare(JVERSION, '3.0', 'ge')  && $this->HTMLtype == 'li' )
						{
							$output .= '</li>' . PHP_EOL . '<li>';
						}
						break;
				}
			}
		}

		unset($GLOBALS['variablefield']);

		return $output;
	}

	/**
	 * Helper function
	 *
	 * @return   string
	 */
	protected function getInputHelper ()
	{
		switch ((string) $this->element['basetype'])
		{
			case 'radio':
			case 'checkbox':
				JFactory::getApplication()->enqueueMessage(
					JText::_('LIB_VARIABLEFILED_WRONG_BASETYPE') . ' <u>' . (string) $this->element['basetype'] . '</u> ',
					'error'
				);
				break;
		}

		$originalValue = (array) $this->value;

		// How many tabs
		$arrayLength = count($originalValue);
		$length = isset($this->element['length']) ? (int) $this->element['length'] : 1;
		$length = max($length, $arrayLength);

		// If the maximum field length is 1, the we do not need to output the clone buttons
		$maxRepeatLength	= isset($this->element['maxrepeatlength']) ? (int) $this->element['maxrepeatlength']: 0;

		if ($maxRepeatLength > 0)
		{
			$length = min($length, $maxRepeatLength);
		}

		$basetype = isset($this->element['basetype']) ? (string) $this->element['basetype'] : 'text';
		$basetype = (string) $basetype;
		$formfield = JFormHelper::loadFieldType($basetype);
		$this->element['clean_name'] = (string) $this->element['name'];
		$this->element['name'] = $this->name . '[]';

		if (isset($this->element->option))
		{
			foreach ($this->element->option as $Item)
			{
				$Item[0] = $this->_replaceNestedJtextConstants($Item[0]);
			}
		}

		$formfield->setup($this->element, '');

		$output = '';

		if ($maxRepeatLength == 1)
		{
			$formfield->id = $formfield->id . uniqid();
			$formfield->value = isset($originalValue[0])? $originalValue[0]:'';

			if (isset($this->defaults[$this->origname]))
			{
				$out = str_replace(
								'id="',
								'data-default="' . htmlspecialchars(JText::_($this->defaults[$this->origname])) . '" id="',
								$formfield->getInput()
							);
				$output .= $out;
			}
			else
			{
				$output .= $formfield->getInput() . PHP_EOL;
			}
		}
		else
		{
			$output = '<div class="variablefield_div repeating_block" >' . PHP_EOL;
			$formfield->id = $formfield->id . uniqid();

			for ($i = 0; $i < $length; $i++)
			{
				$output .= $this->blockElementStartHTML();

				$formfield->id = $formfield->id . '_' . $i;
				$formfield->value = isset($originalValue[$i])? $originalValue[$i]:'';
				$output .= $formfield->getInput() . PHP_EOL;

				$output .= $this->blockElementEndHTML();
			}

			$output .= PHP_EOL . '</div><!-- repeatable field (many cloned fields) -->';
		}

		if (version_compare(JVERSION, '3.0', 'ge')  && $this->HTMLtype == 'div' )
		{
			return $output;
		}
		else
		{
			return $output;
		}
	}

	/**
	 * Start group of rules
	 *
	 * @param   string  $ruleUniqID  Rule uniqid
	 *
	 * @return   string
	 */
	public function groupStartHTML($ruleUniqID='')
	{
		$output = '';

		if (version_compare(JVERSION, '3.0', 'ge')  && $this->HTMLtype == 'div'  )
		{
			$output .= '</div><!-- controls OR my empty div !-->' . $this->blockElementStartHTML(true, $ruleUniqID);
			$output .= PHP_EOL . '<div class="sliderContainer">' . PHP_EOL;
		}
		else
		{
			$output .= PHP_EOL . '</li></ul>' . PHP_EOL;
			$output .= $this->blockElementStartHTML(true, $ruleUniqID);
			$output .= PHP_EOL . '<div class="sliderContainer"><ul class="adminformlist"><li>' . PHP_EOL;
		}
		// Let show the script, that the group has ended
		$formfield = JFormHelper::loadFieldType('text');
		$formfield->setup($this->element, '');
		$formfield->id = '';
		$formfield->readonly = 'readonly';
		$formfield->class = 'ruleUniqID';

		// Remake field name to use group name
		$formfield->name = 'jform[params][' . $this->fieldname . '][__ruleUniqID][]';
		$formfield->value = !empty($ruleUniqID)?$ruleUniqID:'';
		$output .= '<span class="ruleUniqID">UniqID: ' . $formfield->getInput() . '</span>' . PHP_EOL;

		// Let show the script, that the group has ended
		$formfield = JFormHelper::loadFieldType('hidden');
		$formfield->setup($this->element, '');
		$formfield->id = '';
		$formfield->class = 'ruleUniqID';

		// Remake field name to use group name
		$formfield->name = 'jform[params][' . $this->fieldname . '][__ruleUniqID][]';
		$formfield->value = 'variablefield::' . $this->fieldname;
		$output .= $formfield->getInput() . PHP_EOL;

		return $output;
	}

	/**
	 * Group ending HTML
	 *
	 * @return   string
	 */
	public function groupEndHTML()
	{
		$output = '';

		if (version_compare(JVERSION, '3.0', 'ge')  && $this->HTMLtype == 'div'  )
		{
			$output .= PHP_EOL . '<span class="cleaner"></span></div><!-- sliderContainer --><span class="cleaner"></span>'
									. $this->blockElementEndHTML(true) . '<div>';
		}
		else
		{
			$output .= PHP_EOL . '</li></ul></div>' . $this->blockElementEndHTML(true);
			$output .= PHP_EOL;
			$output .= '<ul class="adminformlist"><li>' . PHP_EOL;
		}

		return $output;
	}

	/**
	 * Block element starting HTML
	 *
	 * @param   bool    $isGroup     If the block element is a group of rules
	 * @param   string  $ruleUniqID  The group of rules uniqUd
	 *
	 * @return   string HTML
	 */
	public function blockElementStartHTML($isGroup = false, $ruleUniqID = '')
	{
		$output = '';
		$maxRepeatLength	= isset($this->element['maxrepeatlength']) ? (int) $this->element['maxrepeatlength']: 0;

		$buttons = '';

		if ($maxRepeatLength !== 1)
		{
			$buttons .= '<a class="variablefield_buttons reset_current_slide hasTip" title="' . JText::_('JSEARCH_RESET') . '">--</a> ';
			$buttons .= '<a class="variablefield_buttons move_up_slide hasTip" title="' . JText::_('JLIB_HTML_MOVE_UP') . '">&#8657;</a>';
			$buttons .= '<a class="variablefield_buttons move_down_slide hasTip" title="' . JText::_('JLIB_HTML_MOVE_DOWN') . '">&#8659;</a>';
			$buttons .= '<a data-max-repeat-length="' . $maxRepeatLength . '" class="variablefield_buttons add_new_slide hasTip" title="'
										. JText::_('JTOOLBAR_DUPLICATE') . '" >+</a>';
			$buttons .= '<a class="variablefield_buttons delete_current_slide hasTip" title="' . JText::_('JTOOLBAR_REMOVE') . '">-</a>';
		}

		if ($isGroup)
		{
			// Group name consist of specially prepared below Label and text Input with class .hide
			// Prepare Label
			$formfield = JFormHelper::loadFieldType('text');

			// Load current XML to get all XML attributes in $formfield
			$formfield->setup($this->element, '');

			$formfield->labelClass = 'groupSlider ';

			// Need to set class like this in J3.2+
			$formfield->labelclass = 'groupSlider ';

			// Need to set class like this in J3.2+
			$formfield->class = 'groupSlider ';

			// We get Label text either from stored plugin params, or from XML attributes
			if (!empty($this->group_header))
			{
				$text = $this->group_header;
			}
			else
			{
				$text = $formfield->element['label'] ? (string) $formfield->element['label'] : (string) $formfield->element['name'];
			}

			// Is needed for toggler JS
			$goupname = 'variablegroup__' . str_replace('{', '', $this->element['name']);
			$output .= '<div class="variablefield_div repeating_group ' . $goupname . '" >' . '<div class="buttons_container">';

			$text = JText::_($text);
			$formfield->element['label']  = '';

			// Prepare buttons
			$editbutton  = '<a class="hasTip editGroupName editGroupNameButton" title="' . JText::_('JACTION_EDIT') . '::">✍</a>';
			$editbutton .= '<a class="hasTip cancelGroupNameEdit editGroupNameButton hide " title="' . JText::_('JCANCEL') . '::">✕</a>';
			$output .= $formfield->getLabel() . $editbutton . '<span style="float:right;">' . $buttons . '</span>';

			// $formfield->getLabel();
			// $output .= $editbutton.$buttons;

			// Prepare input field for group name
			$formfield->element['size']  = '';

			$formfield->element['class'] = 'groupnameEditField';

			// Need to set class like this in J3.2+
			$formfield->class = 'groupnameEditField';

			// $formfield->value = htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
			// $formfield->value = addslashes($text);
			$formfield->value = $text;

			// Remake field name to use group name
			$formfield->name = 'jform[params][' . $this->fieldname . '][' . (string) $this->fieldname . '][]';
			$formfield->element['readonly'] = 'true';

			// Need to set class like this in J3.2+
			$formfield->readonly = 'true';

			JHTML::_('behavior.tooltip');
			$output .= '<span class="hdr-wrppr inactive hasTip" title="'
									. JText::_('LIB_VARIABLEFILED_GROUPNAME_TIP') . '">' . $formfield->getInput() . '</span>' . PHP_EOL;
			$output .= '</div><!-- buttons_container -->';

			// Field to store group status - opened or closed
			$formfield = JFormHelper::loadFieldType('hidden');
			$formfield->setup($this->element, '');

			// Remake field name to use group name
			$formfield->name = 'jform[params][' . $this->fieldname . '][' . (string) $this->fieldname . '][]';

			$formfield->element['class'] = 'groupState';

			// Need to set class like this in J3.2+
			$formfield->class = 'groupState';

			$formfield->element['disabled'] = null;
			$formfield->element['onchange'] = null;
			$formfield->value = $this->open;
			$output .= $formfield->getInput() . PHP_EOL;

			// Let show the script, that the group has ended
			$formfield = JFormHelper::loadFieldType('hidden');
			$formfield->setup($this->element, '');

			// Remake field name to use group name
			$formfield->name = 'jform[params][' . $this->fieldname . '][' . (string) $this->fieldname . '][]';
			$formfield->value = 'variablefield::' . $this->fieldname;
			$output .= $formfield->getInput() . PHP_EOL;
		}
		else
		{
			$output .= '<div class="variablefield_div repeating_element" >'
									. '<div class="buttons_container">' . $buttons . '</div><!-- buttons_container -->';
		}

		return $output;
	}

	/**
	 * Final HTML for block element
	 *
	 * @return   string  Closing HTML
	 */
	public function blockElementEndHTML()
	{
		$output = '';

		if (version_compare(JVERSION, '3.0', 'ge')  && $this->HTMLtype == 'div')
		{
			$output .= PHP_EOL . '</div><!-- variablefield_div repeating_group -->' . PHP_EOL;
		}
		else
		{
			$output .= PHP_EOL . '</div><!-- variablefield_div repeating_group -->' . PHP_EOL;
		}

		return $output;
	}

	/**
	 * Replaces JTEXT constants in JTEXT constants.
	 *
	 * @param   string  $text  String to be parsed with JText::_()
	 *
	 * @return   string  Translated string with translated substrings
	 */
	protected function _replaceNestedJtextConstants($text)
	{
		$text = JText::_($text);

		if (empty($text))
		{
			return;
		}

		preg_match_all('/#~#([^#~#]*)#~#/i', $text, $matches);

		if (!empty($matches[0]))
		{
			foreach ($matches[1] as $k => $string)
			{
				if (JText::_($string) != $string)
				{
					$text = str_replace($matches[0][$k], JText::_($string), $text);
				}
			}
		}

		return $text;
	}
}

// Preserve compatibility
if (!class_exists('JFormFieldVariablefield'))
{
	/**
	 * Old-fashioned field name
	 *
	 * @since  1.2.0
	 */
				class JFormFieldVariablefield extends GJFieldsFormFieldVariablefield
				{
				}
}
