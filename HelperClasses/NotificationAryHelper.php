<?php
/**
 * NotificationAry helper
 *
 * @package    Notificationary

 * @author     Gruz <arygroup@gmail.com>
 * @copyright  0000 Copyleft (є) 2017 - All rights reversed
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace NotificationAry\HelperClasses;

// No direct access
defined('_JEXEC') or die('Restricted access');

use JFactory;
use JUser;
use JComponentHelper;
use JHTML;
use JText;
use JForm;
use JFormHelper;
use JTable;
use JRegistry;
use JFolder;
use JFile, JLog, JUri, App;

/**
 * Helper class
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
class NotificationAryHelper
{
	/**
	 * This is a debug function. Generates a number of users for testing purposes
	 *
	 * @author Gruz <arygroup@gmail.com>
	 *
	 * @param type $name Description
	 *
	 * @return type Description
	 */
	static function userGenerator($number = 2, $groups = 'default')
	{
		$jinput = JFactory::getApplication()->input;

		if ($jinput->get('option') == 'com_plugins' && $jinput->get('task') == 'plugin.apply')
		{
			//ok
		}
		else
		{
			return null;
		}

		$instance = JUser::getInstance();
		jimport('joomla.application.component.helper');
		$config = JComponentHelper::getParams('com_users');
		$db = JFactory::getDBO();
		$query = $db->getQuery(true);
		$query->select('id , title');
		$query->from('#__usergroups');
		$query->where('id IN ('.implode(',', $groups).')');
		$db->setQuery((string) $query);

		$defaultUserGroupNames = $db->loadAssocList();
		$defaultUserGroup = $db->loadResultArray();
		$acl = JFactory::getACL();

		// For each group
		for ($k = 0; $k < count($defaultUserGroupNames); $k++)
		{
			// For each number
			for ($i = 0; $i < $number; $i++)
			{
				$user = array();
				$hash = uniqid();

				if (!empty($defaultUserGroupNames))
				{
					$user['fullname'] = 'Fake ' . $defaultUserGroupNames[$k]['title'].' ' . $hash;
				}
				else
				{
					$user['fullname'] = 'Fake User ' . $hash;
				}

				$user['username'] = str_replace(' ','_',$user['fullname']);
				$user['email'] = $hash."@test.com";
				$user['password_clear'] = microtime();

				$instance->set('id'             , 0);
				$instance->set('name'           , $user['fullname']);
				$instance->set('username'       , $user['username']);
				$instance->set('password_clear' , $user['password_clear']);
				$instance->set('email'          , $user['email']);  // Result should contain an email (check)
				$instance->set('usertype'       , 'deprecated');
				$instance->set('groups'         , array($defaultUserGroupNames[$k]['id']));

				// If autoregister is set let's register the user
				$autoregister = isset($options['autoregister']) ? $options['autoregister'] :  $config->get('autoregister', 1);

				if ($autoregister)
				{
					if (!$instance->save())
					{
						JFactory::getApplication()->enqueueMessage($instance->getError(), 'error');

						return;
					}
				}
				else
				{
					// No existing user and autoregister off, this is a temporary user.
					$instance->set('tmp_user', true);
				}
			}
		}
	}

	/**
	 * Parses textarea multiline template into an assosiative array
	 *
	 * @param   string  $context_template  Manual extension template field contents
	 *
	 * @return  mixed  String and to nothing if a one-row context is passed, or an array according to the template defined in /helpers/predefined_contexts.php
	 */
	static public function _parseManualContextTemplate ($context_template)
	{
		$tmp = explode(PHP_EOL,$context_template);
		// If (count($tmp)==1) { return $context_template; }
		$tmp = array_map('trim', $tmp);
		$context = $tmp[0];
		$extension = array();

		if (!isset($predefined_context_templates))
		{
			include NotificationAry_DIR . '/helpers/predefined_contexts.php';
		}

		if (!isset($predefined_context_templates[$context]))
		{
			return $context_template;
		}

		if (count($tmp)==1)
		{
			$tmp = array_flip($rows);

			foreach ($tmp as $k => $v)
			{
				$tmp[$k] = null;
			}
		}

		// $rows is defined in predefined_contexts.php
		foreach ($rows as $row_number=>$row_name)
		{
			if (isset($tmp[$row_number]) && trim($tmp[$row_number]) != '')
			{
				if ($tmp[$row_number] == 'false')
				{
					$tmp[$row_number] = false;
				}

				$extension[$row_name] = $tmp[$row_number];
			}
			else
			{
				if (isset($predefined_context_templates[$context][$row_name]))
				{
					$extension[$row_name] = $predefined_context_templates[$context][$row_name];
				}
				else
				{
					$extension[$row_name] = '';
				}
			}
		}

		foreach ($extension as $k => $v)
		{
			$key = '.inc';

			if(!preg_match('#^function#',trim($v)))
			{
				$function_file = dirname(__FILE__) . '/components/' . $v;

				if (is_file($function_file))
				{
					$extension[$k] = file_get_contents($function_file);
					$explodes = array (
						"defined('_JEXEC') or die('Restricted access');",
						"defined( '_JEXEC' ) or die( 'Restricted access' );",
						"defined( '_JEXEC' ) or die"
					);

					foreach ($explodes as $explode)
					{
						$tmp = explode($explode,$extension[$k]);

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
	 * @param	string	$attibuteValue		Attribute value to be found, i.e. element id value
	 * @param	string	$tagname				Tag to be found. I.e. div, span, p
	 * @param	string	$attributeName		Which attribute to look for. I.e. id, for, class
	 * @return	string							Returns HTML of the element found in $html
	 */
	static public function getHTMLElementById($html,$attributeValue,$tagname = 'div', $attributeName = 'id')
	{
		$attributeValue = str_replace(' ', '\s', $attributeValue);
		$re = '% # Match a DIV element having id="content".
			 <' . $tagname.'\b             # Start of outer DIV start tag.
			 [^>]*?             # Lazily match up to id attrib.
			 \b' . $attributeName.'\s*+=\s*+      # id attribute name and =
			 ([\'"]?+)          # $1: Optional quote delimiter.
			 \b' . $attributeValue.'\b        # specific ID to be matched.
			 (?(1)\1)           # If open quote, match same closing quote
			 [^>]*+>            # remaining outer DIV start tag.
			 (                  # $2: DIV contents. (may be called recursively!)
				(?:              # Non-capture group for DIV contents alternatives.
				# DIV contents option 1: All non-DIV, non-comment stuff...
					[^<]++         # One or more non-tag, non-comment characters.
				# DIV contents option 2: Start of a non-DIV tag...
				| <            # Match a "<", but only if it
					(?!          # is not the beginning of either
					 /?' . $tagname.'\b    # a DIV start or end tag,
					| !--        # or an HTML comment.
					)            # Ok, that < was not a DIV or comment.
				# DIV contents Option 3: an HTML comment.
				| <!--.*?-->     # A non-SGML compliant HTML comment.
				# DIV contents Option 4: a nested DIV element!
				| <' . $tagname.'\b[^>]*+>  # Inner DIV element start tag.
					(?2)           # Recurse group 2 as a nested subroutine.
					</' . $tagname.'\s*>      # Inner DIV element end tag.
				)*+              # Zero or more of these contents alternatives.
			 )                  # End 2$: DIV contents.
			 </' . $tagname.'\s*>          # Outer DIV end tag.
			 %isx';

		if (preg_match($re,$html, $matches))
		{
			return $matches[0];
			 //printf("Match found:\n%s\n", $matches[0]);
		}

		return null;
	}

	/**
	 * NOT USED, for future
	 *
	 * Check the syntax of some PHP code.
	 * @param string $code PHP code to check.
	 * @return boolean|array If false, then check was successful, otherwise an array(message,line) of errors is returned.
	 */
	public function php_syntax_error($code)
	{
		if (!defined("CR"))
		{
			define("CR", "\r");
		}

		if (!defined("LF"))
		{
			define("LF", "\n");
		}

		if (!defined("CRLF"))
		{
			define("CRLF", "\r\n");
		}

		$braces = 0;
		$inString = 0;

		foreach (token_get_all('<?php ' . $code) as $token)
		{
			if (is_array($token))
			{
					switch ($token[0])
					{
						case T_CURLY_OPEN:
						case T_DOLLAR_OPEN_CURLY_BRACES:
						case T_START_HEREDOC:
							++$inString;
							break;
						case T_END_HEREDOC:
							--$inString;
							break;
					}
			}
			elseif ($inString & 1)
			{
					switch ($token)
					{
						case '`':
						case '\'':
						case '"':
							--$inString;
							break;
					}
			}
			else
			{
				switch ($token)
				{
					case '`':
					case '\'':
					case '"':
						++$inString;
						break;
					case '{':
						++$braces;
						break;
					case '}':
							if ($inString)
							{
								--$inString;
							}
							else
							{
								--$braces;

								if ($braces < 0)
								{
									break 2;
								}
							}

							break;
				}
			}
		}

		$inString = @ini_set('log_errors', false);
		$token = @ini_set('display_errors', true);
		ob_start();
		$braces || $code = "if(0){{$code}\n}";

			if (eval($code) === false)
			{
				if ($braces)
				{
					$braces = PHP_INT_MAX;
				}
				else
				{
					false !== strpos($code, CR) && $code = strtr(str_replace(CRLF, LF, $code), CR, LF);
					$braces = substr_count($code, LF);
				}

				$code = ob_get_clean();
				$code = strip_tags($code);

				if (preg_match("'syntax error, (.+) in .+ on line (\d+)$'s", $code, $code))
				{
					$code[2] = (int) $code[2];
					$code = $code[2] <= $braces
						? array($code[1], $code[2])
						: array('unexpected $end' . substr($code[1], 14), $braces);
				}
				else
				{
					$code = array('syntax error', 0);
				}
			}
			else
			{
				ob_end_clean();
				$code = false;
			}

		@ini_set('display_errors', $token);
		@ini_set('log_errors', $inString);

		return $code;
	}

	/**
	 * Gets JUser object by email
	 *
	 * @param   string  $email  Email
	 *
	 * @return   JUser  Either a JUser object for an existing user or a blank JUser object filled with the email
	 */
	static public function getUserByEmail ($email)
	{
		$db = JFactory::getDbo();

		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__users'))
			->where($db->quoteName('email') . ' = ' . $db->quote($email));

		$db->setQuery($query, 0, 1);

		$result = $db->loadResult();

		if (empty($result))
		{
			$user = JFactory::getUser(0);
			$user->email = $email;
		}
		else
		{
			$user = JFactory::getUser($db->loadResult());
		}

		return $user;
	}

	/**
	 * Converts DB date to the date including joomla offset. Just a shortcut to JHTML::_('date',  $value, 'Y-m-d H:i:s')
	 *
	 * @param   string  $value  Date in format 0000-00-00 00:00:00
	 *
	 * @return  string  Date in format 0000-00-00 00:00:00
	 */
	static public function getCorrectDate($value)
	{
		if ($value == "0000-00-00 00:00:00")
		{
			return JText::_('JNONE');
		}

		$value = JHTML::_('date',  $value, 'Y-m-d H:i:s');

		return $value;
	}

	/**
	 * Load user from table by id if exists or returns an empty user
	 *
	 * @param   string  $user_id  User id
	 *
	 * @return   JUser
	 */
	static public function getUser($user_id)
	{
		$table   = JUser::getTable();

		if ($table->load($user_id))
		{
			$user = JFactory::getUser($user_id);
		}
		else
		{
			$user = JFactory::getUser(0);
		}

		return $user;
	}

	/**
	 * Returns a variable from a link
	 *
	 * @param   string  $link     Url
	 * @param   string  $varName  Name of the url query parameter
	 *
	 * @return   string
	 */
	static public function getVarFromQuery($link, $varName)
	{
		$parsed_url = parse_url($link);

		parse_str($parsed_url['query'], $query_params);

		if (!empty($query_params[$varName]))
		{
			return $query_params[$varName];
		}

		return null;
	}

	/**
	 * Gets class name
	 *
	 * @param   object  $object  Object
	 *
	 * @return   string
	 */
	static public  function get_class_from_ContentTypeObject ($object)
	{
		$result = false;
		$tableInfo = json_decode($object->table);

		if (is_object($tableInfo) && isset($tableInfo->special))
		{
			if (is_object($tableInfo->special) && isset($tableInfo->special->type) && isset($tableInfo->special->prefix))
			{
				$class = isset($tableInfo->special->class) ? $tableInfo->special->class : 'JTable';

				if (!class_implements($class, 'JTableInterface'))
				{
					// This isn't an instance of JTableInterface. Abort.
					throw new \RuntimeException('Class must be an instance of JTableInterface');
				}

				// ~ $result = $class::getInstance($tableInfo->special->type, $tableInfo->special->prefix);
				$result = $tableInfo->special->prefix . $tableInfo->special->type;
			}
		}

		return $result;
	}

	/**
	 * Checks if it's the first run of the function. The plugin is executed twice  - as system and as content.
	 *
	 * Is needed i.e. to properly add notification switch, to run the add routine only once
	 *
	 * @param   string  $name  Test
	 *
	 * @return  bool  True is the functions is rub not the first time
	 */
	static public function isFirstRun($name = 'unknown')
	{
		global  $NotificationAryFirstRunCheck;

		if (empty($NotificationAryFirstRunCheck[$name]))
		{
			$NotificationAryFirstRunCheck[$name] = 'not first';

			return true;
		}

		return false;
	}

	/**
	 * Hides full email
	 *
	 * Used when sending in ajax mode
	 * not to show real emails registred at the web-site
	 *
	 * @param   string  $email  Email address
	 *
	 * @return   string  Hiddedn email address
	 */
	static public function obfuscate_email($email)
	{
		$em   = explode("@", $email);
		$name = implode(array_slice($em, 0, count($em) - 1), '@');
		$len  = floor(strlen($name) / 2);

		return substr($name, 0, $len) . str_repeat('*', $len) . "@" . end($em);
	}

	/**
	 * Removes plugin tags
	 *
	 * @param   string  $text  HTML
	 *
	 * @return   string
	 */
	static public function stripPluginTags($text)
	{
		$plugins = array();
		$patterns = array('/\{\w*/', '~\{/\w*~');

		foreach ($patterns as $pattern)
		{
			preg_match_all($pattern, $text, $matches);

			foreach ($matches[0] as $match)
			{
				$match = str_replace('{', '', $match);

				if (strlen($match))
				{
					$plugins[$match] = $match;
				}
			}

			$find = array();
			$replace = array();

			foreach ($plugins as $plugin)
			{
				$find[] = '\{' . $plugin . '\s?.*?\}.*?\{/' . $plugin . '\}';
				$find[] = '\{' . $plugin . '\s?.*?\}';
				$replace[] = '';
				$replace[] = '';
			}

			if (count($find))
			{
				foreach ($find as $key => $f)
				{
					$f = '/' . str_replace('/', '\/', $f) . '/';
					$find[$key] = $f;
				}

				$text = preg_replace($find, $replace, $text);
			}
		}

		return $text;
	}

	/**
	 * Prepares a test object output
	 *
	 * @param   object  $contentObject              A content object to be parsed
	 * @param   array   &$place_holders_body_input  Array to place HTML output
	 *
	 * @return   void
	 */
	static public function buildExampleObject($contentObject, &$place_holders_body_input)
	{
		foreach ($contentObject as $key => $value)
		{
			if (is_array($value))
			{
				foreach ($value as $kf => $vf)
				{
					$place_holders_body_input[] = ''
						. '<span style="color:red;">##Content#' . $key . '##' . $kf . '##</span> => ' . htmlentities((string) $vf) . '<br/>';
				}
			}
			else
			{
				if (is_object($value))
				{
					continue;
				}

				$place_holders_body_input[] = ''
					. '<span style="color:red;">##Content#' . $key . '##</span> => ' . htmlentities((string) $value) . '<br/>';
			}
		}
	}

	/**
	 * Prepares a test object output
	 *
	 * @param   JUser  $user                       A content object to be parsed
	 * @param   array  &$place_holders_body_input  Array to place HTML output
	 *
	 * @return   void
	 */
	public static function buildExampleUser($user, &$place_holders_body_input)
	{
		foreach ($user as $key => $value)
		{
			if ($key == 'password')
			{
				continue;
			}

			if (is_array($value))
			{
				$value = implode(',', $value);
			}

			if ( is_object( $value ) )
			{
				continue;
			}

			$place_holders_body_input[] = '<span style="color:red;">##User#' . $key . '##</span> => ' . htmlentities((string) $value) . '<br/>';
		}
	}

	/**
	 * Replaces plugin code with the subscribe/unsubscribe form if needed
	 *
	 * If the user is guest, then just removes the plugin code.
	 * Removes the code if the user doesn't match any NA rule or there is no such a rule
	 * Replaces the plugin code with the subscribe/unsubscribe form otherwise.
	 * The plugin code format: {na subscribe 5889f0565a762} where 5889f0565a762 is the
	 * NA rule UniqID. See the screenshot to get the idea
	 * http://view.xscreenshot.com/a3dbc86f705ab26c2c2b15627b40dc52
	 *
	 * @param   object  $pluginObject  NA plugin object
	 * @param   array   $body          HTML body
	 * @param   array   $matches       Plugin code matches found
	 *
	 * @return   mixed  HTML string with body if something was replaced or false if no replace occurred
	 */
	public static function pluginCodeReplace($pluginObject, $body, $matches)
	{
		// Load NA subscribed options from the user profiles table
		$user = JFactory::getUser();

		$rules = $pluginObject->pparams;

		$app = JFactory::getApplication();
		$app->set('plg_system_notificationary', $pluginObject);

		$replaced = false;

		$replacements = array();

		// Prepare names of plugin settings fields. These strange names are due to the plugin history
		// when the plugin had admin users settings (ausers) and registred users settings (rusers)
		$paramName = 'notifyuser';
		$groupName = 'ausers_' . $paramName . 'groups';
		$itemName = 'ausers_' . $paramName . 's';

		JForm::addFieldPath($pluginObject->plg_path . '/fields');

		$formfield = JFormHelper::loadFieldType('na.subscribe');

		foreach ($matches as $keymatches => $match)
		{
			$replace_code = $match[0];
			$ruleUniqID = $match[1];

			$form = array();

			if (JFactory::getUser()->guest)
			{
				$replacements[$replace_code] = '';
				$replaced = true;

				continue;
			}

			$msg = null;

			foreach ($rules as $ruleNumber => $rule)
			{
				if ($rule->__ruleUniqID != $ruleUniqID)
				{
					continue;
				}

				if (!$rule->allow_subscribe)
				{
					$replacements[$replace_code] = '<span style="color:red;">['
						. JText::_('PLG_SYSTEM_NOTIFICATIONARY_RULE_DOESNT_ALLOW_TO_SUBSCRIBE') . ': ' . $ruleUniqID
						. ']</span>';
					continue;
				}

				if (!$rule->isenabled)
				{
					$replacements[$replace_code] = '<span style="color:red;">['
						. JText::_('PLG_SYSTEM_NOTIFICATIONARY_RULE_DISABLED') . ': ' . $ruleUniqID
						. ']</span>';
					continue;
				}

				$element = simplexml_load_string(
					'
						<field
							name="subscribe"
							type="na.subscribe"
							ruleids="' . $ruleUniqID . '"
							label="PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIBE_SELECT_LABEL"
						/>
					');

				$formfield->setup($element, '');
				$replacements[$replace_code] = $formfield->renderField();
			}
		}

		// Try to remove wrapping <p> tag. Stupid way, a preg function should be used. Lazy to implement.
		$patterns = array(
			'<p>' . $replace_code . '</p>',
			'<p>' . PHP_EOL . $replace_code . PHP_EOL . '</p>',
		);

		$replaced = false;

		foreach ($replacements as $replace_code => $form)
		{
			foreach ($patterns as $k => $pattern)
			{
				if (strpos($body, $pattern) !== false)
				{
					$replace_code = $pattern;
					break;
				}
			}

			$body_tmp = $body;

			$body = str_replace(array_keys($replacements), $replacements, $body);

			if ($body_tmp !== $body)
			{
				$replaced = true;
			}
		}

		if ($replaced)
		{
			return $body;
		}

		// To avoid later run setBody method
		return false;
	}

	/**
	 * Get profile date (used from subscribe)
	 *
	 * @param   int     $userid  User id
	 * @param   string  $ruleid  NA rule id
	 * @param   bool    $force   Force DB query rerun
	 *
	 * @return   string
	 */
	public static function getProfileData($userid, $ruleid, $force = false)
	{
		static $queried = false;
		static $subscribeData;

		if ($queried && isset($queried[$ruleid]) && $queried[$ruleid] && !$force)
		{
			return $subscribeData[$ruleid];
		}

		// Load the profile data from the database.
		$db = JFactory::getDbo();
		$db->setQuery(
			'SELECT profile_value FROM #__user_profiles'
				. ' WHERE user_id = ' . (int) $userid . " AND profile_key LIKE " . $db->q('notificationary.' . $ruleid . '.%')
				. ' ORDER BY ordering'
		);

		$subscribeData[$ruleid] = $db->loadColumn();

		$queried[$ruleid] = true;

		return $subscribeData[$ruleid];
	}

	/**
	 * Checks if the user is subscribed to the passed category in the passed rule
	 *
	 * @param   object  $rule   NA rule
	 * @param   mixed   $user   User either JUser object or an array with basic user information
	 * @param   int     $catid  Category id
	 * @param   bool    $force  Force DB query rerun
	 *
	 * @return   bool  True if subscribed
	 */
	public static function checkIfUserSubscribedToTheCategory($rule, $user, $catid, $force = false)
	{
		if (is_array($user))
		{
			$user = (object) $user;
		}

		// Get unsubscribed from the rule users
		$unsubscribedEmails = array_map('trim', explode(PHP_EOL, $rule->ausers_excludeusers));

		if (in_array($user->email, $unsubscribedEmails))
		{
			return false;
		}

		$allowedCategories = self::getProfileData($user->id, $rule->__ruleUniqID, $force);

		if (empty($allowedCategories) && $rule->allow_subscribe_default)
		{
			return true;
		}
		elseif (empty($allowedCategories) && !$rule->allow_subscribe_default)
		{
			return false;
		}

		if (in_array('unsubscribed', $allowedCategories))
		{
			return false;
		}

		if (in_array('subscribed', $allowedCategories))
		{
			return true;
		}

		if (in_array('subscribed', $allowedCategories))
		{
			return true;
		}

		if (in_array($catid, $allowedCategories))
		{
			return true;
		}

		return false;
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
		$extensionTable = JTable::getInstance('extension');

		$pluginId = $extensionTable->find(array('element' => 'notificationary', 'type' => 'plugin'));
		$extensionTable->load($pluginId);

		// Get joomla default object
		$plgParams = new JRegistry;

		// Load my plugin params.
		$plgParams->loadString($extensionTable->params, 'JSON');

		$params = $plgParams->get('{notificationgroup');
		$ruleUniqIds = $params->__ruleUniqID;
		$key = -1;

		foreach ($ruleUniqIds as $k => $v)
		{
			if ($v == $ruleUniqId)
			{
				$key = $k;
				break;
			}
		}

		$return = array(
			'key' => $key,
			'params' => $params,
			'plgParams' => $plgParams,
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
	 * @return   type  Description
	 */
	public static function _autoOverride($pluginObject)
	{
			$jinput = JFactory::getApplication()->input;

			if ($jinput->get('option', null) == 'com_dump')
			{
				return;
			}

			$app = JFactory::getApplication();

			$parsed = $jinput->getArray();

			if (isset($parsed['mvcoverride_disable']) && $parsed['mvcoverride_disable'] == '1' )
			{
				return;
			}

			// Add compatibility with Ajax Module Loader
			if (isset($parsed['option']) && $parsed['option'] == 'com_users'
				&&	isset($parsed['view']) && $parsed['view'] == 'users')
			{
				// Do nothing here and override core classes below
			}
			else
			{
				// Do not override - not our case
				return;
			}

			jimport('joomla.filesystem.file');
			jimport('joomla.filesystem.folder');

			$codefolder = __DIR__ . '/../code/';
			$files = str_replace($codefolder, '', JFolder::files($codefolder, '.php', true, true));
			$files = array_fill_keys($files, $codefolder);
			$files_to_override = $files;

			if (empty($files_to_override))
			{
				return;
			}

			// Check scope condition
			$scope = '';

			if (JFactory::getApplication()->isAdmin())
			{
				$scope = 'administrator';
			}

			// Do not override wrong scope for components
			foreach ($files_to_override as $fileToOverride => $overriderFolder)
			{
				if (JFactory::getApplication()->isAdmin())
				{
					if (strpos($fileToOverride, '/com_') === 0)
					{
						unset($files_to_override[$fileToOverride]);
					}

					if (strpos($fileToOverride, '/components/com_') === 0)
					{
						unset($files_to_override[$fileToOverride]);
					}
				}
				else
				{
					if (strpos($fileToOverride, '/administrator/com_') === 0)
					{
						unset($files_to_override[$fileToOverride]);
					}

					if (strpos($fileToOverride, '/administrator/components/com_') === 0)
					{
						unset($files_to_override[$fileToOverride]);
					}
				}
			}

			$overridden = false;

			// Loading override files
			foreach ($files_to_override as $fileToOverride => $overriderFolder)
			{
				if (JFile::exists(JPATH_ROOT . $fileToOverride))
				{
					$originalFilePath = JPATH_ROOT . $fileToOverride;
				}
				elseif (strpos($fileToOverride, '/com_') === 0 && JFile::exists(JPATH_ROOT . '/components' . $fileToOverride))
				{
					$originalFilePath = JPATH_ROOT . '/components' . $fileToOverride;
				}
				else
				{
					JLog::add("Can see an overrider file ($overriderFolder" . "$fileToOverride) , but cannot find what to override", JLog::INFO, 'notificationary');
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

				if (strpos($originalFilePath, '/controllers/') !== false )
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

				// Replace JPATH_COMPONENT constants if found, because we are loading before define these constants
				if (count($definesSource[0]))
				{
					$bufferContent = preg_replace(
						array('/JPATH_COMPONENT/','/JPATH_COMPONENT_SITE/','/JPATH_COMPONENT_ADMINISTRATOR/'),
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
				$app = JFactory::getApplication();
				$pluginObject->_prepareParams();
				$app->set('plg_system_notificationary', $pluginObject);
			}
	}

	/**
	 * Adds a badge to the menu items created and contorlled by this plugin
	 *
	 * The fuction is taken from a Ganty particle
	 *
	 * @return  void
	 */
	public static function addUserlistBadges()
	{
		$document = JFactory::getDocument();
		$type   = $document->getType();

		$app = JFactory::getApplication();
		$option = $app->input->getString('option');
		$view   = $app->input->getString('view');
		$task   = $app->input->getString('task');

		if (($option == 'com_users') && ($view == 'users') && $type == 'html')
		{
			$items_model = JModelLegacy::getInstance('Users', 'UsersModel');
			$ruleUniqID = $items_model->getState('filter.naruleUniqID');
			$nacategory = $items_model->getState('filter.nacategory');

			if (empty($ruleUniqID))
			{
				return;
			}

			$app = JFactory::getApplication();
			$pluginObject = $app->get('plg_system_notificationary');

			// Load NA subscribed options from the user profiles table
			$rules = $pluginObject->pparams;

			foreach ($rules as $ruleNumber => $rule)
			{
				if ($rule->__ruleUniqID == $ruleUniqID)
				{
					break;
				}
			}

			$body = $app->getBody();

			$body = preg_replace_callback(
				'/(<a\s[^>]*href=")([^"]*)("[^>]*>)(.*)(<\/a>)/siU',
				function($matches) use ($rule)
				{
					return self::appendHtml($matches, $rule);
				},
				$body
			);

			$app->setBody($body);
		}
	}

	/**
	 * Appends HTML to menu items
	 *
	 * @param   array   $matches  Parsed by preg link
	 * @param   string  $rule     NA rule
	 *
	 * @return   string  Html with MenuAry badges inserted
	 */
	public static function appendHtml(array $matches, $rule)
	{
		$html = $matches[0];

		if (strpos($matches[2], 'task=user.edit'))
		{
			$uri = new JUri($matches[2]);
			$id = (int) $uri->getVar('id');

			if ($id && in_array($uri->getVar('option'), array('com_users')) )
			{
				$profileData = self::getProfileData($id, $rule->__ruleUniqID, true);

				if (empty($profileData))
				{
					if ($rule->allow_subscribe_default == 1)
					{
						$profileData[] = 'subscribed';
					}
					else
					{
						$profileData[] = 'unsubscribed';
					}
				}

				if (in_array('unsubscribed', $profileData))
				{
					$iconClass = '';
					$iconText = JText::_('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBED_FROM_ALL');
					$titleText = '<span class="label label-info">' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBED_FROM_ALL');
				}
				elseif (in_array('subscribed', $profileData))
				{
					$iconClass = '';
					$iconText = JText::_('PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIBED_TO_ALL');
					$titleText = '<span class="label label-success">' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIBED_TO_ALL');
				}
				else
				{
					$scope = $rule->{$rule->context_or_contenttype};

					// We load the field just to reuse the getOptions function
					JForm::addFieldPath(JPATH_LIBRARIES . '/gjfields');

					$formfield = JFormHelper::loadFieldType('gjfields.categoryext');
					$element = simplexml_load_string(
						'
							<field name="subscribe_categories" maxrepeatlength="1" type="gjfields.variablefield"
											basetype="gjfields.categoryext" extension="com_content"

											context_or_contenttype="' . $rule->context_or_contenttype . '"

											scope="' . $scope . '"
											published="1"

											source_parameter="context_or_contenttype,content_type,context"
											target_parameter="context_or_contenttype,content_type,context"
											multiple="multiple" size="20" show_uncategorized="1" label="PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIBE_TO_CATEGORY"
											description="" class="chzn-custom-value"
											hint="PLG_SYSTEM_NOTIFICATIONARY_FIELD_CATEGORIES_CUSTOM"/>

						');

					$formfield->setup($element, '', $rule->__ruleUniqID);

					// Here we get all categories for the NA rule. So we need to filter out
					// not allowed to be subscribed to categories.
					$categories = $formfield->getOptions(true);

					$iconText = JText::_('PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIBED_TO') . ':<br/> ';

					foreach ($categories as $k => $category)
					{
						if (in_array($category->value, $profileData))
						{
							$iconText .= $category->text . '<br/>';
						}
					}

					$iconClass = 'icon-help';
					$titleText = '<span class="label">' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIBED_TO');
				}

				// ~ $iconClass = 'icon-help';
				// ~ icon-checkbox-partial

				$html = $matches[1] . $uri . $matches[3] . $matches[4] . $matches[5];
				$html .= ' ' . $titleText . '
					<span
						onMouseOver="this.style.color=\'#00F\'"
						onMouseOut="this.style.color=\'#000\'"
						class="hasTooltip ' . $iconClass . '" style="
						cursor: help;"
						title="' . JHtml::tooltipText($iconText) . '"></span>' . '</span>';
			}
		}

		return $html;
	}

	/**
	 * Loads Zoo compatibility code
	 *
	 * @return   void
	 */
	public static function loadZoo()
	{
		// Make sure ZOO exists
		if (!JComponentHelper::getComponent('com_zoo', true)->enabled)
		{
			return;
		}

		// Load ZOO config
		jimport('joomla.filesystem.file');

		if (!JFile::exists(JPATH_ADMINISTRATOR . '/components/com_zoo/config.php') || !JComponentHelper::getComponent('com_zoo', true)->enabled)
		{
			return;
		}

		require_once JPATH_ADMINISTRATOR . '/components/com_zoo/config.php';

		// Make sure App class exists
		if (!class_exists('App'))
		{
			return;
		}

		// Here are a number of events for demonstration purposes.

		// Have a look at administrator/components/com_zoo/config.php
		// and also at administrator/components/com_zoo/events/

		// Get the ZOO App instance
		$zoo = App::getInstance('zoo');

		// Register event
		// ~ $zoo->event->dispatcher->connect('item:saved', array('plgSystemZooevent', 'itemSaved'));

		$zoo->event->register('PlgSystemNotificationary');

		$zoo->event->dispatcher->connect('application:init', array('PlgSystemNotificationary', 'onEditZooItem'),('init'));
		$zoo->event->dispatcher->connect('item:init', array('PlgSystemNotificationary', 'onEditZooItem'));
		// ~ $zoo->event->dispatcher->connect('item:beforedisplay', array('PlgSystemNotificationary', 'onEditZooItem'));
		$zoo->event->dispatcher->connect('item:save', array('PlgSystemNotificationary', 'onBeforeSaveZooItem'));
		$zoo->event->dispatcher->connect('item:saved', array('PlgSystemNotificationary', 'onAfterSaveZooItem'));
		// ~ $zoo->event->dispatcher->connect('item:stateChanged', array('PlgSystemNotificationary', 'onStateChangedZooItem'));


/********************************************************************************************************************

		$zoo->event->register('PlgSystemNotificationary');
		$zoo->event->dispatcher->connect('application:installed', array('PlgSystemNotificationary', 'onEditZooItem'),('installed'));
		$zoo->event->dispatcher->connect('application:init', array('PlgSystemNotificationary', 'onEditZooItem'),('init'));
		$zoo->event->dispatcher->connect('application:saved', array('PlgSystemNotificationary', 'onEditZooItem'),('saved'));
		$zoo->event->dispatcher->connect('application:deleted', array('PlgSystemNotificationary', 'onEditZooItem'),('deleted'));
		$zoo->event->dispatcher->connect('application:addmenuitems', array('PlgSystemNotificationary', 'onEditZooItem'),('addmenuitems'));
		$zoo->event->dispatcher->connect('application:configparams', array('PlgSystemNotificationary', 'onEditZooItem'),('configparams'));
		$zoo->event->dispatcher->connect('application:sefbuildroute', array('PlgSystemNotificationary', 'onEditZooItem'),('sefbuildroute'));
		$zoo->event->dispatcher->connect('application:sefparseroute', array('PlgSystemNotificationary', 'onEditZooItem'),('sefparseroute'));
		$zoo->event->dispatcher->connect('application:sh404sef', array('PlgSystemNotificationary', 'onEditZooItem'),('sh404sef'));

		$zoo->event->register('PlgSystemNotificationary');
		$zoo->event->dispatcher->connect('category:init', array('PlgSystemNotificationary', 'onEditZooItem'),('init'));
		$zoo->event->dispatcher->connect('category:saved', array('PlgSystemNotificationary', 'onEditZooItem'),('saved'));
		$zoo->event->dispatcher->connect('category:deleted', array('PlgSystemNotificationary', 'onEditZooItem'),('deleted'));
		$zoo->event->dispatcher->connect('category:stateChanged', array('PlgSystemNotificationary', 'onEditZooItem'),('stateChanged'));

		$zoo->event->register('PlgSystemNotificationary');
		$zoo->event->dispatcher->connect('item:init', array('PlgSystemNotificationary', 'onEditZooItem'),('init'));
		$zoo->event->dispatcher->connect('item:save', array('PlgSystemNotificationary', 'onEditZooItem'),('save'));
		$zoo->event->dispatcher->connect('item:saved', array('PlgSystemNotificationary', 'onEditZooItem'),('saved'));
		$zoo->event->dispatcher->connect('item:deleted', array('PlgSystemNotificationary', 'onEditZooItem'),('deleted'));
		$zoo->event->dispatcher->connect('item:stateChanged', array('PlgSystemNotificationary', 'onEditZooItem'),('stateChanged'));
		$zoo->event->dispatcher->connect('item:beforedisplay', array('PlgSystemNotificationary', 'onEditZooItem'),('beforeDisplay'));
		$zoo->event->dispatcher->connect('item:afterdisplay', array('PlgSystemNotificationary', 'onEditZooItem'),('afterDisplay'));
		$zoo->event->dispatcher->connect('item:beforeSaveCategoryRelations', array('PlgSystemNotificationary', 'onEditZooItem'),('beforeSaveCategoryRelations'));
		$zoo->event->dispatcher->connect('item:orderquery', array('PlgSystemNotificationary', 'onEditZooItem'),('orderquery'));

		$zoo->event->register('PlgSystemNotificationary');
		$zoo->event->dispatcher->connect('comment:init', array('PlgSystemNotificationary', 'onEditZooItem'),('init'));
		$zoo->event->dispatcher->connect('comment:saved', array('PlgSystemNotificationary', 'onEditZooItem'),('saved'));
		$zoo->event->dispatcher->connect('comment:deleted', array('PlgSystemNotificationary', 'onEditZooItem'),('deleted'));
		$zoo->event->dispatcher->connect('comment:stateChanged', array('PlgSystemNotificationary', 'onEditZooItem'),('stateChanged'));

		$zoo->event->register('PlgSystemNotificationary');
		$zoo->event->dispatcher->connect('submission:init', array('PlgSystemNotificationary', 'onEditZooItem'),('init'));
		$zoo->event->dispatcher->connect('submission:beforesave', array('PlgSystemNotificationary', 'onEditZooItem'),('beforeSave'));
		$zoo->event->dispatcher->connect('submission:saved', array('PlgSystemNotificationary', 'onEditZooItem'),('saved'));
		$zoo->event->dispatcher->connect('submission:deleted', array('PlgSystemNotificationary', 'deleted'));

		$zoo->event->register('PlgSystemNotificationary');
		$zoo->event->dispatcher->connect('element:download', array('PlgSystemNotificationary', 'onEditZooItem'),('download'));
		$zoo->event->dispatcher->connect('element:configform', array('PlgSystemNotificationary', 'onEditZooItem'),('configForm'));
		$zoo->event->dispatcher->connect('element:configparams', array('PlgSystemNotificationary', 'onEditZooItem'),('configParams'));
		$zoo->event->dispatcher->connect('element:configxml', array('PlgSystemNotificationary', 'onEditZooItem'),('configXML'));
		$zoo->event->dispatcher->connect('element:afterdisplay', array('PlgSystemNotificationary', 'onEditZooItem'),('afterDisplay'));
		$zoo->event->dispatcher->connect('element:beforedisplay', array('PlgSystemNotificationary', 'onEditZooItem'),('beforeDisplay'));
		$zoo->event->dispatcher->connect('element:aftersubmissiondisplay', array('PlgSystemNotificationary', 'onEditZooItem'),('afterSubmissionDisplay'));
		$zoo->event->dispatcher->connect('element:beforesubmissiondisplay', array('PlgSystemNotificationary', 'onEditZooItem'),('beforeSubmissionDisplay'));
		$zoo->event->dispatcher->connect('element:beforeedit', array('PlgSystemNotificationary', 'onEditZooItem'),('beforeEdit'));
		$zoo->event->dispatcher->connect('element:afteredit', array('PlgSystemNotificationary', 'onEditZooItem'),('afterEdit'));

		$zoo->event->register('PlgSystemNotificationary');
		$zoo->event->dispatcher->connect('layout:init', array('PlgSystemNotificationary',  'onEditZooItem'),('init'));

		$zoo->event->register('PlgSystemNotificationary');
		$zoo->event->dispatcher->connect('tag:saved', array('PlgSystemNotificationary',  'onEditZooItem'),('saved'));
		$zoo->event->dispatcher->connect('tag:deleted', array('PlgSystemNotificationary',  'onEditZooItem'),('deleted'));

		$zoo->event->register('PlgSystemNotificationary');
		$zoo->event->dispatcher->connect('type:beforesave', array('PlgSystemNotificationary', 'onEditZooItem'),('beforesave'));
		$zoo->event->dispatcher->connect('type:aftersave', array('PlgSystemNotificationary', 'onEditZooItem'),('aftersave'));
		$zoo->event->dispatcher->connect('type:copied', array('PlgSystemNotificationary', 'onEditZooItem'),('copied'));
		$zoo->event->dispatcher->connect('type:deleted', array('PlgSystemNotificationary', 'onEditZooItem'),('deleted'));
		$zoo->event->dispatcher->connect('type:editdisplay', array('PlgSystemNotificationary', 'onEditZooItem'),('editDisplay'));
		$zoo->event->dispatcher->connect('type:coreconfig', array('PlgSystemNotificationary', 'onEditZooItem'),('coreconfig'));
		$zoo->event->dispatcher->connect('type:assignelements', array('PlgSystemNotificationary', 'onEditZooItem'),('assignelements'));
*/
	}

	/**
	 * Loads Zoo compatibility code
	 *
	 * @param   int  $contentItem  Zoo content item
	 *
	 * @return   bool  True if editing a Zoo item page
	 */
	public static function isZooEditPage($contentItem)
	{
		if (get_class($contentItem) != 'Item')
		{
			return false;
		}

		$contentItemId = $contentItem->id;

		$jinput = JFactory::getApplication()->input;
		$option = $jinput->get('option');

		if ($option !== 'com_zoo')
		{
			return false;
		}

		$app = JFactory::getApplication();

		// Replace plugin code at Frontend
		if ($app->isSite())
		{
			$mustBeVars = [
				// ~ 'view' => 'submission',
				'layout' => 'submission',
				'type_id' => 'article',
				'item_id' => $contentItemId
			];

			foreach ($mustBeVars as $k => $v)
			{
				if ($v != $jinput->get($k))
				{
					return false;
				}
			}

		}
		else
		{
			$mustBeVars = [
				'task' => ['edit','apply', 'publish', 'unpublish'],
				'controller' => 'item',
				'cid' => [[$contentItemId], $contentItemId],
			];


			foreach ($mustBeVars as $k => $v)
			{
				$param = $jinput->get($k);

				if ($v == $param)
				{
					continue;
				}

				if (is_array($v) && in_array($param, $v))
				{
					continue;
				}

				return false;
			}
		}

		return true;
	}
}
