<?php
/**
 * @package    GJFileds
 *
 * @copyright  0000 Copyright (C) All rights reversed.
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL or later
 */

defined('JPATH_BASE') or die;

if (!class_exists('GJFieldsFormField'))
{
	include 'gjfields.php';
}

/**
 * Supports a modal article picker.
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
class GJFieldsFormFieldModalContentitem extends JFormFieldGJFields
{
	/**
	 * The form field type.
	 *
	 * @var		string
	 * @since   1.6
	 */
	protected $type = 'ModalContentitem';

	/**
	 * Method to get the field input markup.
	 *
	 * @return   string  The field input markup.
	 */
	public function getInput()
	{
		// Load the modal behavior script.
		JHtml::_('behavior.modal', 'a.modal');

		// Build the script.
		$script = array();
		$script[] = '	function jSelectContentitem_' . $this->id . '(id, title, catid, object) {';
		$script[] = '
		if (document.id("' . $this->id . '_id").value.trim() == \'\') {
				document.id("' . $this->id . '_id").value = id;
		} else {
			var currentValues = document.id("' . $this->id . '_id").value.split(\',\');
			if (currentValues.contains(id)) {
				return true;
			}
			document.id("' . $this->id . '_id").value = document.id("' . $this->id . '_id").value+\',\'+id
		}
							';

		// $script[] = '		document.id("' . $this->id . '_id").value = id;';
		// $script[] = '		document.id("' . $this->id . '_name").value = id;';
		$script[] = '		SqueezeBox.close();';
		$script[] = '	}';

		// Add the script to the document head.
		JFactory::getDocument()->addScriptDeclaration(implode("\n", $script));

		// Setup variables for display.
		$html	= array();

		if (is_array($this->value))
		{
			foreach ($this->value as $k => $v)
			{
				if (empty($v) || $v === "")
				{
					unset ($this->value[$k]);
				}
			}

			$value = implode(',', $this->value);
		}
		elseif (0 == (int) $this->value || empty ($this->value))
		{
			$value = '';
		}
		else
		{
			$value = $this->value;
		}

		// Here class='required' for client side validation
		$class = '';

		if ($this->required)
		{
			$class = ' class="required modal-value"';
		}

		// The current user display field.
		$html[] = '<span class="input-append">';
		$html[] = '<input type="text" id="'
								. $this->id . '_id"' . $class . ' name="'
								. $this->name . '" value="'
								. $value . '" />';

		$context_or_contenttype = (string) $this->element['context_or_contenttype'];

// ~ dumpMessage('ModalItem');
// ~ dump ($context_or_contenttype,'$context_or_contenttype');
		$link = array();

		if ($context_or_contenttype != 'context')
		{
			$extension = (string) $this->element['extension'];
			$category = JTable::getInstance('contenttype');
			$category->load($extension);
			$extension = $category->type_alias;

// ~ dump ($extension,'extension');
			$component = explode('.', $extension);
			$component = $component[0];
			$view = explode('.', $extension, 2);
			$view = end($view);

			if (empty($component))
			{
				$component = 'com_content';
			}

			if (empty($view))
			{
				$view = 'article';
			}

			switch ($component)
			{
				case '':
				case 'com_banners':
				case 'com_tags':
				case 'com_users':
					break;
				case 'com_k2':
					break;
				default :
					$link['layout'] = 'modal';
					$link['tmpl'] = 'component';
					$link['function'] = 'jSelectContentitem_' . $this->id;

					if ($view == 'category')
					{
						$link['option'] = 'com_categories';
						$link['extension'] = $component;
					}
					else
					{
						$link['option'] = $component;
						$link['view'] = $view . 's';
					}
					break;
			}
		}

		if (!empty($link))
		{
			$link = 'index.php?' . JURI::buildQuery($link);

			// $link	= 'index.php?option=com_content&amp;view=articles&amp;layout=modal&amp;tmpl=component&amp;function=jSelectContentitem_' . $this->id;

			if (isset($this->element['language']))
			{
				$link .= '&amp;forcedLanguage=' . $this->element['language'];
			}

			$html[] = '<a class="modal btn" title="' . JText::_('COM_CONTENT_CHANGE_ARTICLE') . '"  href="' . $link . '&amp;'
									. JSession::getFormToken() . '=1" rel="{handler: \'iframe\', size: {x: 800, y: 450}}"><i class="icon-file"></i> '
									. JText::_('JSELECT') . '</a>';
		}

		$html[] = '</span>';

// ~ $html[] = $link;

		return implode("\n", $html);
	}

	/**
	 * Method to get the filtering groups (null means no filtering)
	 *
	 * @return  mixed  array of filtering groups or null.
	 *
	 * @since   1.6.0
	 */
	protected function getGroups()
	{
		return null;
	}

	/**
	 * Method to get the users to exclude from the list of users
	 *
	 * @return  mixed  Array of users to exclude or null to to not exclude them
	 *
	 * @since   1.6.0
	 */
	protected function getExcluded()
	{
		return null;
	}
}

// Preserve compatibility
if (!class_exists('JFormFieldModalContentitem'))
{
	/**
	 * Old-fashioned field name
	 *
	 * @since  1.2.0
	 */
				class JFormFieldModalContentitem extends GJFieldsFormFieldModalContentitem
				{
				}
}
