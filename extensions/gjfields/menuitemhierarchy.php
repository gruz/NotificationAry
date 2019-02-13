<?php
/**
 * @package    GJFileds
 *
 * @copyright  0000 Copyright (C) All rights reversed.
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL or later
 */

/*##mygruz20130718204543 {
* It's a replacement of the core Menuitem field to show menu items in the
* drop-down list respecting menu hierarchy
/*##mygruz20130718204543 } */

defined('JPATH_PLATFORM') or die;

JFormHelper::loadFieldClass('groupedlist');

// Import the com_menus helper.
require_once realpath(JPATH_ADMINISTRATOR . '/components/com_menus/helpers/menus.php');

/**
 * Supports an HTML grouped select list of menu item grouped by menu
 *
 * @package     Joomla.Libraries
 * @subpackage  Form
 * @since       1.6
 */
class GJFieldsFormFieldMenuitemhierarchy extends JFormFieldGroupedList
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.6
	 */
	public $type = 'Menuitemhierarchy';

	/**
	 * Method to get the field option groups.
	 *
	 * @return  array  The field option objects as a nested array in groups.
	 *
	 * @since   1.6
	 */
	protected function getGroups()
	{
		$groups = array();

		// Initialize some field attributes.
		$menuType = (string) $this->element['menu_type'];
		$published = $this->element['published'] ? explode(',', (string) $this->element['published']) : array();
		$disable = $this->element['disable'] ? explode(',', (string) $this->element['disable']) : array();
		$language = $this->element['language'] ? explode(',', (string) $this->element['language']) : array();

		// Get the menu items.
		$items = MenusHelper::getMenuLinks($menuType, 0, 0, $published, $language);

		// Build group for a specific menu type.
		if ($menuType)
		{
			// Initialize the group.
			$groups[$menuType] = array();

			// Build the options array.
			foreach ($items as $link)
			{
				/*##mygruz20130718204314 {
				It was:
				It became:*/
				$repeate = $link->level - 1;

				if ($repeate > 0)
				{
					$link->text = str_repeat('-', $repeate) . ' ' . $link->text;
				}
				/*##mygruz20130718204314 } */
				$groups[$menuType][] = JHtml::_('select.option', $link->value, $link->text, 'value', 'text', in_array($link->type, $disable));
			}
		}
		// Build groups for all menu types.
		else
		{
			// Build the groups arrays.
			foreach ($items as $menu)
			{
				// Initialize the group.
				$groups[$menu->menutype] = array();

				// Build the options array.
				foreach ($menu->links as $link)
				{
				/*##mygruz20130718204314 {
				It was:
				It became:*/
				$repeate = $link->level - 1;

				if ($repeate > 0)
				{
					$link->text = str_repeat('-', $repeate) . ' ' . $link->text;
				}
				/*##mygruz20130718204314 } */
					$groups[$menu->menutype][] = JHtml::_(
						'select.option', $link->value, $link->text, 'value', 'text',
						in_array($link->type, $disable)
					);
				}
			}
		}

		// Merge any additional groups in the XML definition.
		$groups = array_merge(parent::getGroups(), $groups);

		return $groups;
	}
}

// Preserve compatibility
if (!class_exists('JFormFieldMenuitemhierarchy'))
{
	/**
	 * Old-fashioned field name
	 *
	 * @since  1.2.0
	 */
				class JFormFieldMenuitemhierarchy extends GJFieldsFormFieldMenuitemhierarchy
				{
				}
}
