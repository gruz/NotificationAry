<?php
/**
 * @package    GJFields
 *
 * @copyright  0000 Copyleft (є) 2017 - All rights reversed
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

/**
 * Field to select a user id from a modal list.
 *
 * Seems to be not used nowadays // ##mygruz20170201085407
 *
 * @package     Joomla.Libraries
 * @subpackage  Form
 * @since       1.6.0
 */
if (!class_exists('GJFieldsFormField'))
{
	include 'gjfields.php';
}

/**
 * User groups
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
class GJFieldsFormFieldUsergroups extends GJFieldsFormField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.6.0
	 */
	public $type = 'Usergroups';

	/**
	 * Method to get the user field input markup.
	 *
	 * @return  string  The field input markup.
	 *
	 * @since   1.6.0
	 */
	public function getInput()
	{
		$attr = '';

		// Initialize some field attributes.
		$attr .= $this->element['class'] ? ' class="' . (string) $this->element['class'] . '"' : '';
		$attr .= ((string) $this->element['disabled'] == 'true') ? ' disabled="disabled"' : '';
		$attr .= $this->element['size'] ? ' size="' . (int) $this->element['size'] . '"' : '';
		$attr .= $this->multiple ? ' multiple="multiple"' : '';
		$attr .= $this->required ? ' required="required" aria-required="true"' : '';
		$allowAll = ((string) $this->element['allowall'] == 'true') ? true : false;
		$selected = $this->value;
		$name = $this->name;

		if (isset($this->element['excludeLevels']))
		{
			$excludeLevels = explode(',', $this->element['excludeLevels']);
			$excludeLevels = array_map('trim', $excludeLevels);
		}
		else
		{
			$excludeLevels = array();
		}

		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select('a.id AS value, a.title AS text, COUNT(DISTINCT b.id) AS level')
			->from($db->quoteName('#__usergroups') . ' AS a')
			->join('LEFT', $db->quoteName('#__usergroups') . ' AS b ON a.lft > b.lft AND a.rgt < b.rgt')
			->group('a.id, a.title, a.lft, a.rgt')
			->order('a.lft ASC');
		$db->setQuery($query);
		$options = $db->loadObjectList();

		for ($i = 0, $n = count($options); $i < $n; $i++)
		{
			if (in_array($options[$i]->level, $excludeLevels))
			{
				unset($options[$i]);
				continue;
			}

			$options[$i]->text = str_repeat('- ', $options[$i]->level) . $options[$i]->text;
		}

		// If all usergroups is allowed, push it into the array.
		if ($allowAll)
		{
			array_unshift($options, JHtml::_('select.option', '', JText::_('JOPTION_ACCESS_SHOW_ALL_GROUPS')));
		}

		return JHtml::_('select.genericlist', $options, $name, array('id' => uniqid(), 'list.attr' => $attr, 'list.select' => $selected));
	}
}

// Preserve compatibility
if (!class_exists('JFormFieldUsergroups'))
{
	/**
	 * Old-fashioned field name
	 *
	 * @since  1.2.0
	 */
				class JFormFieldUsergroups extends GJFieldsFormFieldUsergroups
				{
				}
}
