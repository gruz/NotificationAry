<?php
/**
 * @package    GJFileds
 *
 * @copyright  0000 Copyright (C) All rights reversed.
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL or later
 */

defined('JPATH_BASE') or die;

/**
 * Supports a modal article picker.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_content
 * @since       1.6
 */
if (!class_exists('GJFieldsFormField'))
{
	include 'gjfields.php';
}

/**
 * Modal article
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
class GJFieldsFormFieldModalArticle extends JFormFieldGJFields
{
	/**
	 * The form field type.
	 *
	 * @var		string
	 * @since   1.6
	 */
	protected $type = 'ModalArticle';

	/**
	 * Method to get the field input markup.
	 *
	 * @return   string  The field input markup.
	 *
	 * @since   1.6
	 */
	public function getInput()
	{
		// Load the modal behavior script.
		JHtml::_('behavior.modal', 'a.modal');

		// Build the script.
		$script = array();
		$script[] = '	function jSelectArticle_' . $this->id . '(id, title, catid, object) {';
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
		$link	= 'index.php?option=com_content&amp;view=articles&amp;layout=modal&amp;tmpl=component&amp;function=jSelectArticle_' . $this->id;

		if (isset($this->element['language']))
		{
			$link .= '&amp;forcedLanguage=' . $this->element['language'];
		}

		$db	= JFactory::getDBO();
		$db->setQuery(
			'SELECT title' .
			' FROM #__content' .
			' WHERE id = ' . (int) $this->value
		);

		try
		{
			$title = $db->loadResult();
		}
		catch (RuntimeException $e)
		{
			JError::raiseWarning(500, $e->getMessage());
		}

		if (empty($title))
		{
			// $title = JText::_('COM_CONTENT_SELECT_AN_ARTICLE');
		}

		$title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

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

		$html[] = '<a class="modal btn" title="' . JText::_('COM_CONTENT_CHANGE_ARTICLE')
								. '"  href="' . $link . '&amp;' . JSession::getFormToken()
								. '=1" rel="{handler: \'iframe\', size: {x: 800, y: 450}}"><i class="icon-file"></i> '
								. JText::_('JSELECT') . '</a>';
		$html[] = '</span>';

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
if (!class_exists('JFormFieldModalArticle'))
{
	/**
	 * Old-fashioned field name
	 *
	 * @since  1.2.0
	 */
				class JFormFieldModalArticle extends GJFieldsFormFieldModalArticle
				{
				}
}
