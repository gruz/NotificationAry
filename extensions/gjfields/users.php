<?php
/**
 * @package     Joomla.Libraries
 * @subpackage  Form
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

JFormHelper::loadFieldClass('user');
/**
 * Field to select a user ID from a modal list.
 *
 * @since  1.6
 */
class GJFieldsFormFieldUsers extends JFormField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.6
	 */
	public $type = 'Users';

	/**
	 * Filtering groups
	 *
	 * @var  array
	 */
	protected $groups = null;

	/**
	 * Users to exclude from the list of users
	 *
	 * @var  array
	 */
	protected $excluded = null;

	/**
	 * Layout to render
	 *
	 * @var  string
	 */
	/*##mygruz20160509132658 {
	It was:
	protected $layout = 'joomla.form.field.user';
	It became:*/
	protected $layout = 'gjfields.layouts.users';
	/*##mygruz20160509132658 } */

	/**
	 * Method to get the user field input markup.
	 *
	 * @return  string  The field input markup.
	 *
	 * @since   1.6
	 */
	protected function getInput()
	{
		if (empty($this->layout))
		{
			throw new UnexpectedValueException(sprintf('%s has no layout assigned.', $this->name));
		}

		/*##mygruz20160509151935 {
		It was:
		return $this->getRenderer($this->layout)->render($this->getLayoutData());
		It became:*/

		$includePaths = array();
		$basePath = JPATH_LIBRARIES . '/gjfields/layouts/';
		$includePaths[] = $basePath . JFactory::getApplication()->getTemplate() . '/';
		$includePaths[] = $basePath;
		$layout = 'users';

		if (!empty($this->element['simple']) && $this->element['simple'] == 'true')
		{
			// Do nothing
		}
		else
		{
			$this->element['name'] = $this->element['name'] . '[]';
		}

		$renderer = $this->getRenderer($layout);
		$renderer->getDefaultIncludePaths();
		$renderer->setIncludePaths(array_merge($renderer->getIncludePaths(), $includePaths));

		$data = array_merge(
			$this->getLayoutData(),
			array('simple' => (isset($this->element['simple']) && (string) $this->element['simple'] == 'true'  )? true: false)
		);

		return $renderer->render($data, true);
		/*##mygruz20160509151935 } */
	}

	/**
	 * Get the data that is going to be passed to the layout
	 *
	 * @return  array
	 */
	public function getLayoutData()
	{
		/* ##mygruz20160510204638 {
		It was:
		// Get the basic field data
		$data = parent::getLayoutData();

		// Load the current username if available.
		$table = JTable::getInstance('user');

		if (is_numeric($this->value))
		{
			$table->load($this->value);
		}
		// Handle the special case for "current".
		elseif (strtoupper($this->value) == 'CURRENT')
		{
			// 'CURRENT' is not a reasonable value to be placed in the html
			$this->value = JFactory::getUser()->id;
			$table->load($this->value);
		}
		else
		{
			$table->name = JText::_('JLIB_FORM_SELECT_USER');
		}
		*
		$extraData = array(
				'userName'  => $table->name,
				'groups'    => $this->getGroups(),
				'excluded'  => $this->getExcluded()
		);
		It became: */
		$data = parent::getLayoutData();
		$extraData = array(
				// 'userName'  => $table->name,
				'groups'    => $this->getGroups(),
				'excluded'  => $this->getExcluded()
		);
		/* ##mygruz20160510204638 } */

		return array_merge($data, $extraData);
	}

	/**
	 * Method to get the filtering groups (null means no filtering)
	 *
	 * @return  mixed  array of filtering groups or null.
	 *
	 * @since   1.6
	 */
	protected function getGroups()
	{
		if (isset($this->element['groups']))
		{
			return explode(',', $this->element['groups']);
		}

		return null;
	}

	/**
	 * Method to get the users to exclude from the list of users
	 *
	 * @return  mixed  Array of users to exclude or null to to not exclude them
	 *
	 * @since   1.6
	 */
	protected function getExcluded()
	{
		return explode(',', $this->element['exclude']);
	}
}

// Preserve compatibility
if (!class_exists('JFormFieldUsers'))
{
	/**
	 * Old-fashioned field name
	 *
	 * @since  1.2.0
	 */
				class JFormFieldUsers extends GJFieldsFormFieldUsers
				{
				}
}
