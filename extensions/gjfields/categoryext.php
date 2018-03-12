<?php
/**
 * @package     Joomla.Legacy
 * @subpackage  Form
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

JFormHelper::loadFieldClass('list');

/**
 * Form Field class for the Joomla Platform.
 * Supports an HTML select list of categories
 *
 * @since  11.1
 */
class GJFieldsFormFieldCategoryext extends JFormFieldCategory
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  11.1
	 */
	public $type = 'Categoryext';

	/**
	 * Method to get the field options for category
	 * Use the extension attribute in a form to specify the.specific extension for
	 * which categories should be displayed.
	 * Use the show_root attribute to specify whether to show the global category root in the list.
	 *
	 * @return  array    The field option objects.
	 *
	 * @since   11.1
	 */
	public function getOptions($flag = false)
	{
		// Due to inheritance the function can be called twice. To avoid we use the $flag parameter and static $options
		static $options = false;

		if (!$flag)
		{
			return $options;
		}

		$options = array();

		$context_or_contenttype = $this->element['context_or_contenttype']
			? (string) $this->element['context_or_contenttype'] : (string) $this->element['scope'];

		$extension = $this->element[$context_or_contenttype] ? (string) $this->element[$context_or_contenttype] : (string) $this->element['scope'];

		$extension = explode(PHP_EOL, $extension);
		$extension = trim($extension[0]);

		switch ($context_or_contenttype)
		{
			case 'context':
				break;
			case 'content_type':
			default :
				$category = JTable::getInstance('contenttype');
				$category->load($extension);

				$extension = $category->type_alias;
				break;
		}

		$extension = explode('.', $extension);
		$extension = $extension[0];

		$published = (string) $this->element['published'];

		// Load the category options for a given extension.
		if (!empty($extension))
		{
			switch ($extension)
			{
				case 'com_k2':
					try
					{
						$db = JFactory::getDBO();
						$query = 'SELECT m.* FROM #__k2_categories m WHERE trash = 0 ORDER BY parent, ordering';
						$db->setQuery($query);
						$mitems = $db->loadObjectList();
						$children = array();

						if ($mitems)
						{
							foreach ($mitems as $v)
							{
								if (K2_JVERSION != '15')
								{
									$v->title = $v->name;
									$v->parent_id = $v->parent;
								}

								$pt = $v->parent;
								$list = @$children[$pt] ? $children[$pt] : array();
								array_push($list, $v);
								$children[$pt] = $list;
							}
						}

						$list = JHTML::_('menu.treerecurse', 0, '', array(), $children, 9999, 0, 0);
						$mitems = array();

						foreach ($list as $item)
						{
							$item->treename = JString::str_ireplace('&#160;', ' -', $item->treename);
							$mitems[] = JHTML::_('select.option', $item->id, $item->treename);
						}

						$options = $mitems;
					}
					catch (Exception $e)
					{
						JFactory::getApplication()->enqueueMessage(
							JText::sprintf('LIB_GJFIELDS_NOT_INSTALLED', $extension) . '<br><pre>' . $e->getMessage() . '</pre>',
							'error'
						);
					}
					break;
				case 'com_jdownloads':
					$file = JPATH_ADMINISTRATOR . '/components/' . $extension . '/models/fields/jdcategoryselect.php';

					if (!file_exists($file))
					{
						JFactory::getApplication()->enqueueMessage(
							JText::sprintf('LIB_GJFIELDS_NOT_INSTALLED', $extension) . '<br>' . JText::_('LIB_GJFIELDS_FILE_NOT_EXISTS') . '<pre>' . $file . '</pre>',
							'error'
						);
						break;
					}

					JLoader::register('JFormFieldjdCategorySelect', $file);
					$formfield = JFormHelper::loadFieldType('jdcategoryselect');
					$formfield->setup($this->element, '');
					$options = $formfield->getOptions();

					break;
				default :
					if (strpos($extension, 'com_') !== 0)
					{
						$extension = 'com_' . $extension;
					}

					// Filter over published state or not depending upon if it is present.
					if ($published)
					{
						$options = JHtml::_('category.options', $extension, array('filter.published' => explode(',', $published)));
					}
					else
					{
						$options = JHtml::_('category.options', $extension);
					}

					// Verify permissions.  If the action attribute is set, then we scan the options.
					if ((string) $this->element['action'])
					{
						// Get the current user object.
						$user = JFactory::getUser();

						foreach ($options as $i => $option)
						{
							/*
							 * To take save or create in a category you need to have create rights for that category
							 * unless the item is already in that category.
							 * Unset the option if the user isn't authorised for it. In this field assets are always categories.
							 */
							if ($user->authorise('core.create', $extension . '.category.' . $option->value) != true)
							{
								unset($options[$i]);
							}
						}
					}
					break;
			}

			if (isset($this->element['show_root']))
			{
				array_unshift($options, JHtml::_('select.option', '0', JText::_('JGLOBAL_ROOT')));
			}
		}
		else
		{
			JLog::add(
				'GJFields: ' . $this->getAttribute('name') . ' ' . JText::_('JLIB_FORM_ERROR_FIELDS_CATEGORY_ERROR_EXTENSION_EMPTY'),
				JLog::WARNING,
				'jerror'
			);
		}

		// Merge any additional options in the XML definition.

		/*##mygruz20160213194844 {
		$options = array_merge(parent::getOptions(), $options);
		It was:
		It became:*/
		/*##mygruz20160213194844 } */

		return $options;
	}

	/**
	 * HTML output
	 *
	 * @return   string
	 */
	protected function getInput()
	{
		$options = (array) $this->getOptions(true);

		if (empty($options))
		{
			$formfield = JFormHelper::loadFieldType('text');
			$formfield->setup($this->element, '');

			if (is_array($this->value))
			{
				$formfield->value = implode(',', $this->value);
			}
			else
			{
				$formfield->value = $this->value;
			}

			$formfield->hint = JText::_((string) $this->hint);

			return $formfield->getInput() . PHP_EOL;
		}

		return parent::getInput();
	}
}

// Preserve compatibility
if (!class_exists('JFormFieldCategoryext'))
{
	/**
	 * Old-fashioned field name
	 *
	 * @since  1.2.0
	 */
				class JFormFieldCategoryext extends GJFieldsFormFieldCategoryext
				{
				}
}
