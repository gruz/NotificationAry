<?php
/**
 * A helper trait main logic except the native Joomla plugin functions
 *
 * @package     NotificationAry
 *
 * @author      Gruz <arygroup@gmail.com>
 * @copyright   Copyleft (є) 2018 - All rights reversed
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace NotificationAry\Traits;

// No direct access
defined('_JEXEC') or die;

/**
 * Main trait to contain non-core Joomla plugin functions logic
 *
 * @since 0.2.17
 */
trait Main
{
	/**
	 * Parses textarea multiline template into an assosiative array
	 *
	 * @param   string  $extension  Manual extension template field contents
	 *
	 * @return  mixed  String and to nothing if a one-row context is passed, or an array
	 *                 according to the template defined in /Helpers/predefined_contexts.php
	 */
	public static function parseManualContextTemplate($extension)
	{
		$extension = explode(PHP_EOL, $extension);
		$extension = array_map('trim', $extension);

		if (!isset($predefinedContextTemplates))
		{
			include static::$predefinedContentsFile;
		}

		if (count($extension) < count($rows) )
		{
			for ($i = count($extension); $i < count($rows); $i++)
			{
				$extension[$i] = null;
			}
		}

		// $rows is defined in predefined_contexts.php
		$extension = array_combine($rows, $extension);

		$extension = array_map(
			function ($v) { return ('false' === $v) ? false : $v; }, // phpcs:ignore
			$extension
		);

		foreach ($extension as $k => $v)
		{
			if (!preg_match('#^function#', trim($v)))
			{
				$functionFile = self::$componentBridgesFolder . '/components/' . $v;

				if (is_file($functionFile))
				{
					$extension[$k] = file_get_contents($functionFile);
					$explodes      = array(
						"defined('_JEXEC') or die('Restricted access');",
						"defined( '_JEXEC' ) or die( 'Restricted access' );",
						"defined( '_JEXEC' ) or die",
					);

					foreach ($explodes as $explode)
					{
						$tmp = explode($explode, $extension[$k]);

						if (isset($tmp[1]))
						{
							$extension[$k] = $tmp[1];

							break;
						}
					}
				}
			}
		}

		return $extension;
	}

	/**
	 * A helper function to parse HTML to get an element by an attribute
	 *
	 * Doesn't work for one-tag tags like <input />,
	 * only works for open and closed tags
	 *
	 * @author Gruz <arygroup@gmail.com>
	 * @param	string	$html					String of HTML code to be parsed
	 * @param	string	$attributeValue		Attribute value to be found, i.e. element id value
	 * @param	string	$tagname				Tag to be found. I.e. div, span, p
	 * @param	string	$attributeName		Which attribute to look for. I.e. id, for, class
	 * @return	string							Returns HTML of the element found in $html
	 */
	public static function getHTMLElementById($html, $attributeValue, $tagname = 'div', $attributeName = 'id')
	{
		$attributeValue = str_replace(' ', '\s', $attributeValue);
		$re             = '% # Match a DIV element having id="content".
			 <' . $tagname . '\b             # Start of outer DIV start tag.
			 [^>]*?             # Lazily match up to id attrib.
			 \b' . $attributeName . '\s*+=\s*+      # id attribute name and =
			 ([\'"]?+)          # $1: Optional quote delimiter.
			 \b' . $attributeValue . '\b        # specific ID to be matched.
			 (?(1)\1)           # If open quote, match same closing quote
			 [^>]*+>            # remaining outer DIV start tag.
			 (                  # $2: DIV contents. (may be called recursively!)
				(?:              # Non-capture group for DIV contents alternatives.
				# DIV contents option 1: All non-DIV, non-comment stuff...
					[^<]++         # One or more non-tag, non-comment characters.
				# DIV contents option 2: Start of a non-DIV tag...
				| <            # Match a "<", but only if it
					(?!          # is not the beginning of either
					 /?' . $tagname . '\b    # a DIV start or end tag,
					| !--        # or an HTML comment.
					)            # Ok, that < was not a DIV or comment.
				# DIV contents Option 3: an HTML comment.
				| <!--.*?-->     # A non-SGML compliant HTML comment.
				# DIV contents Option 4: a nested DIV element!
				| <' . $tagname . '\b[^>]*+>  # Inner DIV element start tag.
					(?2)           # Recurse group 2 as a nested subroutine.
					</' . $tagname . '\s*>      # Inner DIV element end tag.
				)*+              # Zero or more of these contents alternatives.
			 )                  # End 2$: DIV contents.
			 </' . $tagname . '\s*>          # Outer DIV end tag.
			 %isx';

		if (preg_match($re, $html, $matches))
		{
			return $matches[0];
			//printf("Match found:\n%s\n", $matches[0]);
		}

		return;
	}

	/**
	 * Get rule option key to reference
	 *
	 * The key is needed to get  proper value in huge,
	 * stupid and complicated plugin settings object
	 *
	 * @param   string  $optionName  Option name (field name)
	 * @param   string  $ruleUniqId  Ruile Uniqid
	 *
	 * @return   type  Description
	 */
	public static function getRuleOptionKey($optionName, $ruleUniqId)
	{
		$extensionTable = \JTable::getInstance('extension');

		$pluginId = $extensionTable->find(array('element' => 'notificationary', 'type' => 'plugin'));
		$extensionTable->load($pluginId);

		// Get joomla default object
		$plgParams = new \JRegistry;

		// Load my plugin params.
		$plgParams->loadString($extensionTable->params, 'JSON');

		$params      = $plgParams->get('{notificationgroup');
		$ruleUniqIds = $params->__ruleUniqID;
		$key         = -1;

		foreach ($ruleUniqIds as $k => $v)
		{
			if ($v == $ruleUniqId)
			{
				$key = $k;

				break;
			}
		}

		$return = array(
			'key'            => $key,
			'params'         => $params,
			'plgParams'      => $plgParams,
			'extensionTable' => $extensionTable,
		);

		return $return;
	}

	/**
	 * Get an option from a certain rule
	 *
	 * @param   string  $optionName  Option name (field name)
	 * @param   string  $ruleUniqId  Ruile Uniqid
	 *
	 * @return   mixed
	 */
	public static function getRuleOption($optionName, $ruleUniqId)
	{
		$res = self::getRuleOptionKey($optionName, $ruleUniqId);
		extract($res);

		if (isset($params->{$optionName}->{$key}))
		{
			$option = $params->{$optionName}->{$key};
		}
		else
		{
			$option = $params->{$optionName}[$key];
		}

		return $option;
	}

	/**
	 * Update an option from a certain rule and save it to DB
	 *
	 * @param   string  $optionName  Option name (field name)
	 * @param   string  $value       Value to be saved in a string format
	 *                               (jsonify before passing if needed)
	 * @param   string  $ruleUniqId  Ruile Uniqid
	 *
	 * @return   mixed
	 */
	public static function updateRuleOption($optionName, $value, $ruleUniqId)
	{
		$res = self::getRuleOptionKey($optionName, $ruleUniqId);
		extract($res);

		if (!is_array($params->{$optionName}))
		{
			$params->{$optionName}->{$key} = $value;
		}
		else
		{
			$params->{$optionName}[$key] = $value;
		}

		$plgParams->set('{notificationgroup', $params);

		// Bind to extension table
		$extensionTable->bind(array('params' => $plgParams->toString()));

		// Check and store
		if (!$extensionTable->check() || !$extensionTable->store())
		{
			return false;
		}

		return true;
	}

	/**
	 * Autooverride (based on plg_system_mvcoverride but changed a little)
	 *
	 * @param   \plgSystemNotificationary  $pluginObject Our plugin itself
	 * @return  void
	 */
	public static function autoOverride(\plgSystemNotificationary $pluginObject)
	{
		$jinput = \JFactory::getApplication()->input;

		if ($jinput->get('option', null) == 'com_dump')
		{
			return;
		}

		$app = \JFactory::getApplication();

		$parsed = $jinput->getArray();

		if (isset($parsed['mvcoverride_disable']) && $parsed['mvcoverride_disable'] == '1')
		{
			return;
		}

		/** 2018-04-07 02:31:45
		 * There was a try to autooverride only if manually allowed. But this makes thing too complicated.
		 * So I need not only add an overrider class, but also to add some logic to load it here. 
		 * To avoid it we comment the code, but this will run overrides at any page loaded.
		 * 
		 * The array below was written as an example, but never implemented as logic
		 * 
		$allowedQueries = [
			'admin' => [
				[
					'option' => 'com_users',
					'view' => 'users',
				]
			],
			'site' => [
				[
					'option' => 'com_phocadownloads',
				]
			]
		];

		if (\JFactory::getApplication()->isAdmin())
		{
			$ss = $allowedQueries['admin'];
		} 
		else
		{
			$ss = $allowedQueries['site'];				
		}

		$allowed = true;
		foreach ($ss as $key => $value) 
		{
			
			if (!isset($parsed[$key]) 
			{
				$allowed = false;
				return;

			}
				&& $parsed['option'] == 'com_users')
		}
		
		if (!$allowed) 
		{
			return;
		}


		// Add compatibility with Ajax Module Loader
		if (isset($parsed['option']) && $parsed['option'] == 'com_users'
			&&	isset($parsed['view']) && $parsed['view'] == 'users'
		)
		{
			// Do nothing here and override core classes below
		}
		else
		{
			// Do not override - not our case
			return;
		}
		*/


		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');

		$codefolder      = \realpath(__DIR__ . '/../code/');
		$files           = str_replace($codefolder, '', \JFolder::files($codefolder, '.php', true, true));
		$files           = array_fill_keys($files, $codefolder);
		$filesToOverride = $files;

		if (empty($filesToOverride))
		{
			return;
		}

		// Check scope condition
		$scope = '';

		if (\JFactory::getApplication()->isAdmin())
		{
			$scope = 'administrator';
		}

		$overridden = false;

		// Loading override files
		foreach ($filesToOverride as $fileToOverride => $overriderFolder)
		{
			if (\JFile::exists(JPATH_ROOT . $fileToOverride))
			{
				$originalFilePath = JPATH_ROOT . $fileToOverride;
			}
			elseif (strpos($fileToOverride, '/com_') === 0 && \JFile::exists(JPATH_ROOT . '/components' . $fileToOverride))
			{
				$originalFilePath = JPATH_ROOT . '/components' . $fileToOverride;
			}
			else
			{
				JLog::add("Can see an overrider file ($overriderFolder $fileToOverride) , but cannot find what to override", JLog::INFO, 'notificationary');

				continue;
			}

			preg_match('~.*/(com_[^/]*)/.*~Ui', $originalFilePath, $matches);

			$option = '';

			if (isset($matches[1]))
			{
				$option = $matches[1];
			}

			// ~ $uniqid = uniqid();

			$uniqid = strtoupper($option) . '_';

			if (!defined($uniqid . 'JPATH_SOURCE_COMPONENT'))
			{
				// Constants to replace JPATH_COMPONENT, JPATH_COMPONENT_SITE and JPATH_COMPONENT_ADMINISTRATOR
				define($uniqid . 'JPATH_SOURCE_COMPONENT', JPATH_BASE . '/components/' . $option);
				define($uniqid . 'JPATH_SOURCE_COMPONENT_SITE', JPATH_SITE . '/components/' . $option);
				define($uniqid . 'JPATH_SOURCE_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/' . $option);
			}

			// Include the original code and replace class name add a Default on
			$bufferFile = file_get_contents($originalFilePath);

			if (strpos($originalFilePath, '/controllers/') !== false)
			{
				$temp = explode('/controllers/', $originalFilePath);
				require_once $temp[0] . '/controller.php';
			}

			// Detect if source file use some constants
			preg_match_all('/JPATH_COMPONENT(_SITE|_ADMINISTRATOR)|JPATH_COMPONENT/i', $bufferFile, $definesSource);

			$overriderFilePath = $overriderFolder . $fileToOverride;

			// Append "Default" to the class name (ex. ClassNameDefault). We insert the new class name into the original regex match to get
			$rx = '/class *[a-z0-9]* *(extends|{|\n)/i';

			preg_match($rx, $bufferFile, $classes);

			if (empty($classes))
			{
				$rx = '/class *[a-z0-9]*/i';
				preg_match($rx, $bufferFile, $classes);
			}

			$parts = explode(' ', $classes[0]);

			$originalClass = $parts[1];

			$replaceClass = trim($originalClass) . 'Default';

			// Replace original class name by default
			$bufferContent = str_replace($originalClass, $replaceClass, $bufferFile);
			$bufferContent = trim($bufferContent);
			$bufferContent = preg_replace('/.*(\?>)$/', '', $bufferContent);		


			// Replace JPATH_COMPONENT constants if found, because we are loading before define these constants
			if (count($definesSource[0]))
			{
				$bufferContent = preg_replace(
					array('/JPATH_COMPONENT/', '/JPATH_COMPONENT_SITE/', '/JPATH_COMPONENT_ADMINISTRATOR/'),
					array($uniqid . 'JPATH_SOURCE_COMPONENT', $uniqid . 'JPATH_SOURCE_COMPONENT_SITE', $uniqid . 'JPATH_SOURCE_COMPONENT_ADMINISTRATOR'),
					$bufferContent
				);
			}

			// Change private methods to protected methods
			// ~ $bufferContent = preg_replace('/private *function/i', 'protected function', $bufferContent);

			// Finally we can load the base class
			// ~ $bufferContent = $this->_trimEndClodingTag($bufferContent);
			eval('?>' . $bufferContent . PHP_EOL . '?>');

			require $overriderFilePath;

			$overridden = true;
		}

		if ($overridden)
		{
			$app = \JFactory::getApplication();
			$pluginObject->prepareParams();
			$app->set('plg_system_notificationary', $pluginObject);
		}
	}
}
