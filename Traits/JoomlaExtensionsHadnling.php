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
	private function getExtensionInfo($context = null, $id = null)
	{
		if (!empty($id))
		{
			$contentType = \JTable::getInstance('contenttype');
			$contentType->load($id);
			$context = $this->contextAliasReplace($contentType->{'type_alias'});

		}
		else
		{
			$contentType = \JTable::getInstance('contenttype');
			$contentType->load(array('type_alias' => $context));
		}

		$extensionInfo = self::parseManualContextTemplate($context);

		// Extensions is not registred in Joomla
		if (empty($contentType->{'type_id'}))
		{
			return array($extensionInfo, $contentType);
		}

		list($option, $suffix) = explode('.', $context, 2);
		$categoryContext      = $option . '.category';
		$contentTypeCategory   = \JTable::getInstance('contenttype');
		$contentTypeCategory->load(array('type_alias' => $categoryContext));

		foreach ($extensionInfo as $key => $value)
		{
			if (!empty($value) || $value === false)
			{
				continue;
			}

			switch ($key)
			{
				case 'Item table class':
					// NOTE! Old way:
					// ~ $extensionInfo[$key] = get_class($contentType->getContentTable());

					// This would not work in some cases, as $contentType->getContentTable() may return false if the appropriate table is not loaded.
					// Must use the custom function
					$extensionInfo[$key] = self::get_class_from_ContentTypeObject($contentType);

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
						// ~ $extensionInfo[$key] = get_class($contentTypeCategory->getContentTable());
						$extensionInfo[$key] = self::get_class_from_ContentTypeObject($contentType);
					}

					break;
				case 'Category context':
					if (!empty($contentTypeCategory->getContentTable()->type_id))
					{
						$extensionInfo[$key] = $categoryContext;
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
					$extensionInfo[$key] = $contentType->router;

					break;
				default :

					break;
			}

			return array($extensionInfo, $contentType);
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
		$extensionInfo = self::parseManualContextTemplate($context);

		if (!is_array($extensionInfo))
		{
			if (isset($this->predefinedContextTemplates) && isset($this->predefinedContextTemplates[$context]))
			{
				$extensionInfo = $this->predefinedContextTemplates[$context];
			}
		}

		// No context in the manual template entered
		if ($context == $extensionInfo)
		{
			return false;
		}

		if (is_array($extensionInfo))
		{
			if ($getCategoryTable)
			{
				$jtableClassName = $extensionInfo['Category table class'];

				if (!empty($extensionInfo['Category context']))
				{
					$context = $extensionInfo['Category context'];
				}
			}
			else
			{
				$jtableClassName = $extensionInfo['Item table class'];

				if (!empty($extensionInfo['Context']))
				{
					$context = $extensionInfo['Context'];
				}
			}
		}

		if (!empty($jtableClassName))
		{
			if (strpos($jtableClassName, ':')  !== false)
			{
				$tablename       = explode(':', $jtableClassName);
				$path            = $tablename[0];
				$jtableClassName = $tablename[1];
			}

			$tablename = explode('Table', $jtableClassName);

			if (isset($tablename[1]))
			{
				$type   = $tablename[1];
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
			$context = $this->contextAliasReplace($context);

			switch ($context)
			{
				case 'com_content.article':
					// $contentItem = \JTable::getInstance( 'content');
					$type   = 'content';
					$prefix = null;

					break;
				case 'com_users.user':
					$type   = 'user';
					$prefix = null;

					break;
				default :
					$tablename = explode('.', $context);
					\JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/' . $tablename[0] . '/tables');

					// Category
					$type   = $tablename[1];
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
				$app           = \JFactory::getApplication();
				$appReflection = new \ReflectionClass(get_class($app));
				$_messageQueue = $appReflection->getProperty('_messageQueue');
				$_messageQueue->setAccessible(true);
				$messages = $_messageQueue->getValue($app);
				$cmpstr   = \JText::sprintf('JLIB_DATABASE_ERROR_NOT_SUPPORTED_FILE_NOT_FOUND', $type);

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
					\JText::_(ucfirst($this->plgName)) . ' (line ' . __LINE__ . '): ' . $type . ' => ' . $prefix,
					'warning'
				);
			}

			return false;
		}

		return $contentItem;
	}
}
