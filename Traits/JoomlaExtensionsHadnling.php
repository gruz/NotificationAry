<?php
/**
 * Handles Joomla extensions to work with NAS
 *
 * @package     NotificationAry
 *
 * @author      Gruz <arygroup@gmail.com>
 * @copyright   Copyleft (є) 2018 - All rights reversed
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */


namespace NotificationAry\Traits;

/**
 * A helper trait
 *
 * @since 0.2.17
 */
trait JoomlaExtensionsHadnling
{

	/**
	 * Get needed for the plugin extension information
	 *
	 * I get $extension_info from the predefined_contexts.php.
	 * If there is no infromation in the predefined context matching current context,
	 * then I get the context string back. In that case I just load empty array $extension_info
	 * Othervise it loads everything possible from the predefined_contexts.php into array $extension_info
	 * After that I have to updated the $extension_info with the joomla registred information from #__content_types if possible
	 *
	 * @param   string  $context  Context
	 * @param   string  $id       type_id from #__content_types
	 *
	 * @return  array  extension info according to the template in predefined_contexts.php
	 */
	private function _getExtensionInfo ($context = null ,$id = null)
	{
		if (!empty($id))
		{
			$contentType = \JTable::getInstance('contenttype');
			$contentType->load($id);
			$context = $this->_contextAliasReplace($contentType->type_alias);
		}
		else
		{
			$contentType = \JTable::getInstance('contenttype');
			$contentType->load(array('type_alias' => $context));
		}

		$extension_info = self::_parseManualContextTemplate($context);

		if (!is_array($extension_info))
		{
			if (!isset($predefined_context_templates))
			{
				include static::$predefinedContentsFile;
			}

			$extension_info = array_flip($rows);

			foreach ($extension_info as $k => $v)
			{
				$extension_info[$k] = null;
			}
		}

		if (empty($extension_info['Context']) )
		{
			$extension_info['Context'] = $context;
		}

		// Extensions is not registred in Joomla
		if (empty($contentType->type_id))
		{
			return array($extension_info, $contentType);
		}

		list($option, $suffix) = explode('.', $context, 2);
		$category_context = $option . '.category';
		$contentTypeCategory = \JTable::getInstance('contenttype');
		$contentTypeCategory->load(array('type_alias' => $category_context));

		foreach ($extension_info as $key => $value)
		{
			if (!empty($value) || $value === false)
			{
				continue;
			}

			switch ($key)
			{
				case 'Item table class':
					// NOTE! Old way:
					// ~ $extension_info[$key] = get_class($contentType->getContentTable());

					// This would not work in some cases, as $contentType->getContentTable() may return false if the appropriate table is not loaded.
					// Must use the custom function
					$extension_info[$key] = self::get_class_from_ContentTypeObject($contentType);
					break;
				case 'View link':
					break;
				case 'Frontend edit link':
					break;
				case 'Backend edit link':
					break;
				case 'Category table class':
					if (!empty($contentTypeCategory->getContentTable()->type_id))
					{
						// ~ $extension_info[$key] = get_class($contentTypeCategory->getContentTable());
						$extension_info[$key] = self::get_class_from_ContentTypeObject($contentType);
					}

					break;
				case 'Category context':
					if (!empty($contentTypeCategory->getContentTable()->type_id))
					{
						$extension_info[$key] = $category_context;
					}

					break;
				case 'onContentAfterSave':
					break;
				case 'onContentBeforeSave':
					break;
				case 'onContentChangeState':
					break;
				case 'onContentPrepareForm':
					break;
				case 'contextAliases':
					break;
				case 'RouterClass::RouterMethod':
					$extension_info[$key] = $contentType->router;
					break;
				default :

					break;
			}

			return array($extension_info, $contentType);
		}
	}


	/**
	 * Get's content item table if possible, usually a \JTable extended class
	 *
	 * @param   string  $context           Context, helps to determine which table to get
	 * @param   bool    $getCategoryTable  Wether we are getting a category table or a content item table
	 *
	 * @return   type  Description
	 */
	public function _getContentItemTable ($context, $getCategoryTable = false)
	{
		// Parse context var in case it's an extension template textarea field
		$extension_info = self::_parseManualContextTemplate($context);

		if (!is_array($extension_info))
		{
			if (isset($this->predefined_context_templates) && isset($this->predefined_context_templates[$context]))
			{
				$extension_info = $this->predefined_context_templates[$context];
			}
		}

		// No context in the manual template entered
		if ($context == $extension_info)
		{
			return false;
		}

		if (is_array($extension_info))
		{
			if ($getCategoryTable)
			{
				$jtableClassName = $extension_info['Category table class'];

				if (!empty($extension_info['Category context']))
				{
					$context = $extension_info['Category context'];
				}
			}
			else
			{
				$jtableClassName = $extension_info['Item table class'];

				if (!empty($extension_info['Context']))
				{
					$context = $extension_info['Context'];
				}
			}
		}

		if (!empty($jtableClassName))
		{
				if (strpos($jtableClassName, ':')  !== false)
				{
					$tablename = explode(':', $jtableClassName);
					$path = $tablename[0];
					$jtableClassName = $tablename[1];
				}

				$tablename = explode('Table', $jtableClassName);

				if (isset($tablename[1]))
				{
					$type = $tablename[1];
					$prefix = explode('_', $tablename[0]);
					$prefix = $tablename[0] . 'Table';
				}
				else
				{
					$type = $tablename[0];
				}

				$temp = explode('.', $context, 2);

				if (empty($path))
				{
					\JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/' . $temp[0] . '/tables');
				}
				else
				{
					\JTable::addIncludePath(JPATH_ROOT . '/' . $path);
				}
		}
		else
		{
			// $contenttypeObject = \JTable::getInstance( 'contenttype');
			// $contenttypeObject->load( $extension );
			$context = $this->_contextAliasReplace($context);

			switch ($context)
			{
				case 'com_content.article':
					// $contentItem = \JTable::getInstance( 'content');
					$type = 'content';
					$prefix = null;
					break;
				case 'com_users.user':
					$type = 'user';
					$prefix = null;
					break;
				default :
					$tablename = explode('.', $context);
					\JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/' . $tablename[0] . '/tables');

					// Category
					$type = $tablename[1];
					$prefix = explode('_', $tablename[0]);
					$prefix = $prefix[1] . 'Table';
					break;
			}
		}

		if (empty($prefix) && empty($type))
		{
			return false;
		}

		if (!empty($prefix))
		{
			$contentItem = \JTable::getInstance($type, $prefix);
		}
		else
		{
			$contentItem = \JTable::getInstance($type);
		}

		if (!$contentItem || !method_exists($contentItem, 'load'))
		{
			if (!$this->paramGet('debug'))
			{
				$app = \JFactory::getApplication();
				$appReflection = new ReflectionClass(get_class($app));
				$_messageQueue = $appReflection->getProperty('_messageQueue');
				$_messageQueue->setAccessible(true);
				$messages = $_messageQueue->getValue($app);
				$cmpstr = \JText::sprintf('JLIB_DATABASE_ERROR_NOT_SUPPORTED_FILE_NOT_FOUND', $type);

				foreach ($messages as $key => $message)
				{
					if ($message['message'] == $cmpstr)
					{
						unset($messages[$key]);
					}
				}

				$_messageQueue->setValue($app, $messages);
			}
			else
			{
				if (!isset($prefix))
				{
					$prefix = '';
				}

				\JFactory::getApplication()->enqueueMessage(
					\JText::_(ucfirst($this->plg_name)) . ' (line ' . __LINE__ . '): ' . $type . ' => ' . $prefix,
					'warning'
				);
			}

			return false;
		}

		return $contentItem;
	}


}
