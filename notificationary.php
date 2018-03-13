<?php
/**
 * A plugin which sends notifications when an article is added or modified at a Joomla web-site
 *
 * @package     NotificationAry
 * @subpackage  com_teaching
 *
 * @author      Gruz <arygroup@gmail.com>
 * @copyright   Copyleft (є) 2016 - All rights reversed
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
defined('_JEXEC') or die;

jimport('gjfields.gjfields');
jimport('gjfields.helper.plugin');
jimport('joomla.filesystem.folder');

$latest_gjfields_needed_version = '1.2.0';
$error_msg = 'Install the latest GJFields plugin version <span style="color:black;">'
	. __FILE__ . '</span>: <a href="http://www.gruz.org.ua/en/extensions/gjfields-sefl-reproducing-joomla-jform-fields.html">GJFields</a>';

$isOk = true;

while (true)
{
	$isOk = false;

	if (!class_exists('JPluginGJFields'))
	{
		$error_msg = 'Strange, but missing GJFields library for <span style="color:black;">'
			. __FILE__ . '</span><br> The library should be installed together with the extension... Anyway, reinstall it:
			<a href="http://www.gruz.org.ua/en/extensions/gjfields-sefl-reproducing-joomla-jform-fields.html">GJFields</a>';
		break;
	}

	$gjfields_version = file_get_contents(JPATH_ROOT . '/libraries/gjfields/gjfields.xml');
	preg_match('~<version>(.*)</version>~Ui', $gjfields_version, $gjfields_version);
	$gjfields_version = $gjfields_version[1];

	if (version_compare($gjfields_version, $latest_gjfields_needed_version, '<'))
	{
		break;
	}

	$isOk = true;
	break;
}

if (!$isOk)
{
	JFactory::getApplication()->enqueueMessage($error_msg, 'error');
}
else
{
jimport('joomla.plugin.plugin');
jimport('joomla.filesystem.file');

$com_path = JPATH_SITE . '/components/com_content/';

// ~ require_once $com_path.'router.php';
if (!class_exists('ContentRouter') )
{
	require_once $com_path . 'router.php';
}

// ~ require_once $com_path.'helpers/route.php';
if (!class_exists('ContentHelperRoute') )
{
	require_once $com_path . 'helpers/route.php';
}

if (!class_exists('NotificationAryHelper') )
{
	require_once dirname(__FILE__) . '/helpers/helper.php';
}


	/**
	 * Plugin code
	 *
	 * @author  Gruz <arygroup@gmail.com>
	 * @since   0.0.1
	 */
	class PlgSystemNotificationaryCore extends JPluginGJFields
				{
		// Enable this variable to load local non-minified JS and CSS
		static public $debug = false;

		protected $previous_state;

		protected $onContentChangeStateFired = false;

		protected $contentItem;

		protected $sitename;

		protected $plg_type;

		protected $plg_name;

		protected $plg_full_name;

		protected $langShortCode;

		protected $availableDIFFTypes = array ('Text/Unified','Text/Context','Html/SideBySide','Html/Inline');

		// Here will be stored which previos article versions need to be attached
		protected $prepare_previous_versions_flag = array();

		// This flag is used to determine if DIFF info is needed at least once. Otherwise, DIFF library is not loaded.
		protected $includeDiffInBody = false;

		// Flag to know what diffs should be prepared gloablly.
		protected $DIFFsToBePreparedGlobally = array();

		protected $broken_sends = array();

		// Article is New
		protected $isNew = false;

		protected $publish_state_change = 'not determined';

		// Contains all contexts to run the plugin at
		protected $allowed_contexts = array();

		// Contains all components to run the plugin at
		protected $allowed_components = array();

		// Contains context=>jtableclass (manually entered) ties
		// ~ protected $jtable_classes = array();

		// A variable to pass current context between functions. Currenlty used to determine if to show a notification switch.
		// Is set in onContentPrepareForm to and used in onAfterRender to know if onAfterRender should run
		protected $context = array();

		protected $shouldShowSwitchCheckFlag = false;

		protected $context_aliases = array(
						'com_content.category' => 'com_categories.category' ,
						"com_banners.category" => 'com_categories.category',
						// ~ "com_content.form" => 'com_content.article',
						// ~ "com_jdownloads.form" => 'com_jdownloads.download',

						// "com_categories.categorycom_content" => 'com_categories.category',
						// "com_categories.categorycom_banners" => 'com_categories.category',
						'com_categories.categories' => 'com_categories.category',
					);

		// Joomla article object differs from i.e. K2 object. Make them look the same for some variables
		protected $object_variables_to_replace = array (
				// State in com_contact means not status, but a state (region), so use everywhere published
				array ('published','state'),
				array ('fulltext','description'),
				array ('title','name'),

				// User note
				array ('title','subject'),

				// JEvents
				array ('title','_title'),
				array ('fulltext','_content'),
				array ('published','_state'),
				array ('id','_ev_id'),

				// Banner client
				array ('fulltext','extrainfo'),

				// Contact
				array ('fulltext','misc'),

				// User note
				array ('fulltext','body'),

				// Banner category
				array ('created_by','created_user_id'),

				// Jdownloads. Third parameter means force to override created_by with created_id even if created_by exists
				array ('created_by','created_id', true),

				// Banner category
				array ('modified_by','modified_user_id'),
				array ('created','created_time'),
				array ('modified','modified_time'),

				// JDownloads
				array ('id','file_id'),
				array ('catid','cat_id'),
				array ('title','file_title'),

				array ('introtext', false),
				array ('title', false),
				array ('alias', false),
				array ('fulltext', false),
			);

		public static $CustomHTMLReplacementRules;

		/**
		 * Constructor.
		 *
		 * @param   object  &$subject  The object to observe.
		 * @param   array   $config    An optional associative array of configuration settings.
		 */
		public function __construct(& $subject, $config)
		{
			$jinput = JFactory::getApplication()->input;

			if ($jinput->get('option', null) == 'com_dump')
			{
				return;
			}

			parent::__construct($subject, $config);

			$this->_preparePluginHasBeenSavedOrAppliedFlag();

			// Would not work at most of servers. Try to let save huge forms.
			ini_set('max_input_vars', 5000);

			if ($this->pluginHasBeenSavedOrApplied)
			{
				$this->_updateRulesIfHashSaved();
			}

			// Generate users
			while (true)
			{
				if (!$this->paramGet('debug'))
				{
					break;
				}

				if (!$this->paramGet('generatefakeusers'))
				{
					break;
				}

				$usergroups = $this->paramGet('fakeusersgroups');

				$usernum = (integer) $this->paramGet('fakeusersnumber');

				if (NotificationAryHelper::isFirstRun('userGenerator'))
				{
					NotificationAryHelper::userGenerator($usernum, $usergroups);
				}

				break;
			}
		}

		/**
		 * Geat plugin parameters from DB and parses them for later usage in the plugin
		 *
		 * @return   type  Description
		 */
		public function _prepareParams()
		{
			// No need to run if already generated
			if (!empty($this->pparams))
			{
				return;
			}

			// $this->_updateRulesIfHashSaved();

			// Get variable fields params parsed in a nice way, stored to $this->pparams
			$this->getGroupParams('{notificationgroup');

			// Some parameters preparations
			foreach ($this->pparams as $rule_number => $rule)
			{
				$rule = (object) $rule;

				// Do not handle this rule, if it shound never be run . $rule->ausers_notifyon part is here for legacy
				if (!$rule->isenabled || $rule->ausers_notifyon == 3)
				{
					unset($this->pparams[$rule_number]);
					continue;
				}

				$this->pparams[$rule_number] = (object) $rule;

				// This array is used to build mails once per user group, as mails to users from the same user groups are the same.
				// Here we just init it to be used when building a mail.
				$this->pparams[$rule_number]->cachedMailBuilt = array();

				// If categories entered manually, then convert to array
				if (!empty($rule->ausers_articlegroupsselection) && strpos($rule->ausers_articlegroupsselection[0], ',') !== false )
				{
					$this->pparams[$rule_number]->ausers_articlegroupsselection = array_map('trim', explode(',', $rule->ausers_articlegroupsselection[0]));
				}

				// Prepare global cumulative flag to know which prev. versions to be attached in all rules.

				// To later prepare all needed attached files together and only once.
				// So we avoid preparing the same attached files which may be needed for several groups
				if (isset($this->pparams[$rule_number]->attachpreviousversion) )
				{
					if (!is_array($this->pparams[$rule_number]->attachpreviousversion))
					{
						$this->pparams[$rule_number]->attachpreviousversion = (array) $this->pparams[$rule_number]->attachpreviousversion;
					}

					foreach ($this->pparams[$rule_number]->attachpreviousversion as $k => $v)
					{
						$this->prepare_previous_versions_flag[$v] = $v;
					}
				}
				// Here we get the extension and the context to be notified. We use either a registred in Joomla extension (like DPCalendar or core Articles) or
				if ($rule->context_or_contenttype == "content_type")
				{
					list($extension_info, $contentType) = $this->_getExtensionInfo($context = null, $id = $rule->content_type);
					$this->pparams[$rule_number]->contenttype_title = $contentType->type_title;
				}
				else
				{
					$templateRows = array_map('trim', explode(PHP_EOL, $this->pparams[$rule_number]->context));
					$context = trim($templateRows[0]);

					if (empty($context))
					{
						JFactory::getApplication()->enqueueMessage(
							JText::_(
								ucfirst($this->plg_name)
							)
							. ' (line ' . __LINE__ . '): '
							. JText::sprintf(
									'PLG_SYSTEM_NOTIFICATIONARY_NO_EXTENSION_SELECTED',
									$this->pparams[$rule_number]->{'{notificationgroup'}[0],
									$this->pparams[$rule_number]->__ruleUniqID
							),
							'warning'
						);

						unset($this->pparams[$rule_number]);
						continue;
					}

					list($extension_info, $contentType) = $this->_getExtensionInfo($context, $id = null);
					$this->pparams[$rule_number]->contenttype_title = $extension_info['Context'];

					$i = 0;

					$extension_info_merged = array();

					foreach ($extension_info as $key => $value)
					{
						$extension_info_merged[$key] = $templateRows[$i];
						$i++;
					}

					$extension_info = $extension_info_merged;

					unset($extension_info_merged);
				}

				if (!empty($extension_info['contextAliases']))
				{
					$contextAliases = explode(',', $extension_info['contextAliases']);
					$contextAliases = array_map('trim', $contextAliases);

					foreach ($contextAliases as $ka => $va)
					{
						$this->allowed_contexts[] = $va;
						$this->context_aliases[$va] = $extension_info['Context'];
					}
				}

				$this->pparams[$rule_number]->context = $extension_info['Context'];
				$this->pparams[$rule_number]->extension_info = $extension_info;
				$this->predefined_context_templates[$extension_info['Context']] = $extension_info;

				unset($extension_info);

				// $this->allowed_contexts[] = $rule->context;
				$this->allowed_contexts[] = $this->pparams[$rule_number]->context;

				$component = explode('.', $this->pparams[$rule_number]->context);
				$this->allowed_components[] = $component[0];

				// Prepare options for author and editor mailbody
				$includes = array('author','modifier');
				$available_options = array(
					'introtext',
					'fulltext',
					'frontendviewlink',
					'frontendeditlink',
					'backendeditlink',
					'unsubscribelink'
				);

				foreach ($includes as $include)
				{
					$mb_type = $this->pparams[$rule_number]->{$include . '_mailbody_type'};
					$mb = $this->pparams[$rule_number]->{$include . '_mailbody'};

					if (!is_array($mb))
					{
						$mb = array_map('trim', explode(',', $mb));
					}

					if ($mb_type == 'inherit')
					{
						foreach ($available_options as $k => $v)
						{
							$this->pparams[$rule_number]->{'ausers_' . $include . 'include' . $v} = $this->pparams[$rule_number]->{'ausers_include' . $v};
						}
					}
					else
					{
						foreach ($available_options as $k => $v)
						{
							if (in_array($v, $mb))
							{
								$this->pparams[$rule_number]->{'ausers_' . $include . 'include' . $v} = true;
							}
							else
							{
								$this->pparams[$rule_number]->{'ausers_' . $include . 'include' . $v} = false;
							}
						}
					}
				}

				$additionalmailadresses = $this->pparams[$rule_number]->ausers_additionalmailadresses;
				$additionalmailadresses = array_map('trim', explode(PHP_EOL, $additionalmailadresses));

				$this->pparams[$rule_number]->usersAddedByEmail = array();

				foreach ($additionalmailadresses as $k => $v)
				{
					$user = NotificationAryHelper::getUserByEmail($v);

					if ($user->id)
					{
						$this->pparams[$rule_number]->usersAddedByEmail[] = $user;
						unset($additionalmailadresses[$k]);
					}
				}

				$this->pparams[$rule_number]->ausers_additionalmailadresses = implode(PHP_EOL, $additionalmailadresses);
			}

			$this->allowed_contexts = array_unique($this->allowed_contexts);
			$this->allowed_components = array_unique($this->allowed_components);
		}

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
				$contentType = JTable::getInstance('contenttype');
				$contentType->load($id);
				$context = $this->_contextAliasReplace($contentType->type_alias);
			}
			else
			{
				$contentType = JTable::getInstance('contenttype');
				$contentType->load(array('type_alias' => $context));
			}

			$extension_info = NotificationAryHelper::_parseManualContextTemplate($context);

			if (!is_array($extension_info))
			{
				if (!isset($predefined_context_templates))
				{
					include dirname(__FILE__) . '/helpers/predefined_contexts.php';
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
			$contentTypeCategory = JTable::getInstance('contenttype');
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
						$extension_info[$key] = NotificationAryHelper::get_class_from_ContentTypeObject($contentType);
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
							$extension_info[$key] = NotificationAryHelper::get_class_from_ContentTypeObject($contentType);
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
		 * Checks if current page is a content item edit page
		 *
		 * @param   string  &$context  Context
		 *
		 * @return   bool
		 */
		public function _isContentEditPage(&$context )
		{
			$this->_prepareParams();

			if (!empty($context))
			{
				if (in_array($context, $this->allowed_contexts))
				{
					return true;
				}
			}

			return false;
		}

		/**
		 * Run plugin on change article state from article list.
		 *
		 * @param   string   $context  The context for the content passed to the plugin.
		 * @param   array    $pks      A list of primary key ids of the content that has changed state.
		 * @param   integer  $value    The value of the state that the content has been changed to.
		 *
		 * @return  boolean
		 */
		public function onContentChangeState($context, $pks, $value)
		{
			// ~ dumpMessage('onContentChangeState');
			$jinput = JFactory::getApplication()->input;

			if ($jinput->get('option', null) == 'com_dump')
			{
				return true;
			}

			$this->_prepareParams();


			$context = $this->_contextAliasReplace($context);

			if (!in_array($context, $this->allowed_contexts))
			{
				return true;
			}

			/* ##mygruz20180313030701 {  
			if ($context == 'com_categories.category')
			{
				$context .= $jinput->get('extension', null);
			}
			It was:
			It became: */
			/* ##mygruz20180313030701 } */

			$this->onContentChangeStateFired = true;

			$contentItem = $this->_getContentItemTable($context);

			if (!$contentItem)
			{
				return true;
			}

			foreach ($pks as $id)
			{
				$contentItem->load($id);
				$contentItem->modified_by = JFactory::getUser()->id;
				$this->previous_state = 'not determined';
				$this->onContentAfterSave($context, $contentItem, false);
			}

			return true;
		}

		/**
		 * Get's content item table if possible, usually a JTable extended class
		 *
		 * @param   string  $context           Context, helps to determine which table to get
		 * @param   bool    $getCategoryTable  Wether we are getting a category table or a content item table
		 *
		 * @return   type  Description
		 */
		public function _getContentItemTable ($context, $getCategoryTable = false)
		{
			// Parse context var in case it's an extension template textarea field
			$extension_info = NotificationAryHelper::_parseManualContextTemplate($context);

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
						JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/' . $temp[0] . '/tables');
					}
					else
					{
						JTable::addIncludePath(JPATH_ROOT . '/' . $path);
					}
			}
			else
			{
				// $contenttypeObject = JTable::getInstance( 'contenttype');
				// $contenttypeObject->load( $extension );
				$context = $this->_contextAliasReplace($context);

				switch ($context)
				{
					case 'com_content.article':
						// $contentItem = JTable::getInstance( 'content');
						$type = 'content';
						$prefix = null;
						break;
					case 'com_users.user':
						$type = 'user';
						$prefix = null;
						break;
					default :
						$tablename = explode('.', $context);
						JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/' . $tablename[0] . '/tables');

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
				$contentItem = JTable::getInstance($type, $prefix);
			}
			else
			{
				$contentItem = JTable::getInstance($type);
			}

			if (!$contentItem || !method_exists($contentItem, 'load'))
			{
				if (!$this->paramGet('debug'))
				{
					$app = JFactory::getApplication();
					$appReflection = new ReflectionClass(get_class($app));
					$_messageQueue = $appReflection->getProperty('_messageQueue');
					$_messageQueue->setAccessible(true);
					$messages = $_messageQueue->getValue($app);
					$cmpstr = JText::sprintf('JLIB_DATABASE_ERROR_NOT_SUPPORTED_FILE_NOT_FOUND', $type);

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

					JFactory::getApplication()->enqueueMessage(
						JText::_(ucfirst($this->plg_name)) . ' (line ' . __LINE__ . '): ' . $type . ' => ' . $prefix,
						'warning'
					);
				}

				return false;
			}

			return $contentItem;
		}

		/**
		 * Save previous state  to a variable to check if it has been changed after content save
		 *
		 * @param   string  $context      Context
		 * @param   object  $contentItem  Content item object, e.g. Joomla article
		 * @param   bool    $isNew        If article is new
		 *
		 * @return   void
		 */
		public function onContentBeforeSave($context, $contentItem, $isNew)
		{
// ~ dump($context,'onContentBeforeSave');

			$jinput = JFactory::getApplication()->input;

			if ($jinput->get('option', null) == 'com_dump')
			{
				return;
			}

			if (!$this->_isContentEditPage($context))
			{
				return;
			}

			if ($isNew)
			{
				return;
			}

			$this->contentItem  = $this->_contentItemPrepare($contentItem);

			$session = JFactory::getSession();
			$CustomReplacement = $session->get('CustomReplacement', null, $this->plg_name);

			switch ($context)
			{
				case $CustomReplacement['context']:
					$this->previous_article = $CustomReplacement['previous_item'];
					$this->previous_state = $CustomReplacement['previous_state'];
					break;
				case 'jevents.edit.icalevent':
					$dataModel = new JEventsDataModel;
					$this->previous_article = $dataModel;
					$jevent = $dataModel->queryModel->getEventById(intval($this->contentItem->id), 1, "icaldb");

					if (!empty($jevent))
					{
						$this->previous_article = $jevent;
					}

					break;
				default :

					// $this->previous_article = JTable::getInstance('content');
					// $this->previous_article = $this->_getContentItemTable($context);
					$this->previous_article = clone $contentItem;
					$this->previous_article->reset();
					$this->previous_article->load($contentItem->id);
					$this->previous_state = $this->previous_article->state;

					break;
			}

			$this->previous_article = $this->_contentItemPrepare($this->previous_article);

			$confObject = JFactory::getApplication();
			$tmpPath = $confObject->getCfg('tmp_path');

			foreach ($this->prepare_previous_versions_flag as $k => $v)
			{
				$this->attachments[$v] = $tmpPath . '/prev_version_id_' . $this->previous_article->id . '_' . uniqid() . '.' . $v;

				switch ($v)
				{
					case 'html':
					case 'txt':
						$text = '';
						$text .= '<h1>' . $this->previous_article->title . '</h1>' . PHP_EOL;

						if (!empty($this->previous_article->introtext))
						{
							$text .= '<br />' . $this->previous_article->introtext . PHP_EOL;
						}

						if (!empty($this->previous_article->fulltext))
						{
							$text .= '<hr id="system-readmore" />' . PHP_EOL . PHP_EOL . $this->previous_article->fulltext;
						}

						if ($v == 'txt')
						{
							if (!class_exists('Html2Text') )
							{
								require_once dirname(__FILE__) . '/helpers/Html2Text.php';
							}

							// Instantiate a new instance of the class. Passing the string
							// variable automatically loads the HTML for you.
							$h2t = new Html2Text\Html2Text($text, array('show_img_link' => 'yes'));
							$h2t->width = 120;

							// Simply call the get_text() method for the class to convert
							// the HTML to the plain text. Store it into the variable.
							$text = $h2t->get_text();
							unset ($h2t);
						}

						break;
					case 'sql':
						$db = JFactory::getDBO();
						$empty_contentItem = clone $this->previous_article;
						$empty_contentItem->reset();

						// $empty_contentItem = $this->_getContentItemTable($context);
						$tablename = str_replace('#__', $db->getPrefix(), $empty_contentItem->get('_tbl'));
						$text = 'UPDATE ' . $tablename . ' SET ';
						$parts = array();

						foreach ($this->previous_article as $field => $value)
						{
							if (is_string($value) && property_exists($empty_contentItem, $field) )
							{
								$parts[] = $db->quoteName($field) . '=' . $db->quote($value);
							}
						}

						$text .= implode(',', $parts);
						$text .= ' WHERE ' . $db->quoteName('id') . '=' . $db->quote($this->previous_article->id);
						break;
					default :
						$this->attachments[$v] = null;
						break;
				}

				if (!empty($this->attachments[$v]))
				{
					JFile::write($this->attachments[$v], $text);
				}
			}

			$this->noDiffFound = false;

			foreach ($this->pparams as $rule_number => $rule)
			{
				// Prepare global list of DIFFs to be generated, stored in $this->DIFFsToBePreparedGlobally {
				// If all possible DIFFs are already set to be generated, then don't check, else go:
				if (count($this->availableDIFFTypes) > count($this->DIFFsToBePreparedGlobally))
				{
					if (isset($rule->attachdiffinfo) )
					{
						foreach ($rule->attachdiffinfo as $k => $v)
						{
							$this->DIFFsToBePreparedGlobally[$v] = $v;
						}
					}

					if ($rule->messagebodysource == 'hardcoded')
					{
						if ($rule->emailformat == 'plaintext' && $rule->includediffinfo_text != 'none' )
						{
							$this->DIFFsToBePreparedGlobally[$rule->includediffinfo_text] = $rule->includediffinfo_text;
						}
						elseif ($rule->emailformat == 'html' && $rule->includediffinfo_html != 'none' )
						{
							$this->DIFFsToBePreparedGlobally[$rule->includediffinfo_html] = $rule->includediffinfo_html;
						}
					}
					// Add to global needed DIFFs to be prepare the DIFFs, which may occur in custom message body
					elseif ($rule->messagebodysource == 'custom')
					{
						foreach ($this->availableDIFFTypes as $diffType)
						{
							if (strpos($rule->messagebodycustom, '%DIFF ' . $diffType . '%') !== false)
							{
								$this->DIFFsToBePreparedGlobally[$diffType] = $diffType;
							}
						}
					}
				}
			}

			if (!empty($this->DIFFsToBePreparedGlobally) )
			{
				if (!class_exists('Diff') )
				{
					require_once dirname(__FILE__) . '/helpers/Diff.php';
				}

				$options = array(
					// 'ignoreWhitespace' => true,
					// 'ignoreCase' => true,

					// Determines how much of not changed text to show, 1 means only close to the change
					'context' => 1
				);

				$old = array();
				$old[] = '<h1>' . $this->previous_article->title . '</h1>';
				$introtext = preg_split("/\r\n|\n|\r/", JString::trim($this->previous_article->introtext));
				$old = array_merge($old, $introtext);

				if (!empty($this->previous_article->fulltext))
				{
					$old[] = '<hr id="system-readmore" />';
					$fulltext = preg_split("/\r\n|\n|\r/", JString::trim($this->previous_article->fulltext));
					$old = array_merge($old, $fulltext);
				}

				$new = array();
				$new[] = '<h1>' . $this->contentItem->title . '</h1>';
				$introtext = preg_split("/\r\n|\n|\r/", JString::trim($this->contentItem->introtext));

				$new = array_merge($new, $introtext);

				if (!empty($this->contentItem->fulltext))
				{
					$new[] = '<hr id="system-readmore" />';
					$fulltext = preg_split("/\r\n|\n|\r/", JString::trim($this->contentItem->fulltext));
					$new = array_merge($new, $fulltext);
				}

				// Initialize the diff class
				$diff = new Diff($old, $new, $options);
				$css = JFile::read(dirname(__FILE__) . '/helpers/Diff/styles.css');
			}

			$path = $tmpPath . '/diff_id_' . $this->previous_article->id . '_' . uniqid();

			foreach ($this->DIFFsToBePreparedGlobally as $k => $v)
			{
				$useCSS = false;

				switch ($v)
				{
					case 'Text/Unified':
					case 'Text/Context':
						$fileNamePart = explode('/', $v);
						$this->attachments[$v] = $path . '_' . $fileNamePart[1] . '.txt';
						break;
					case 'Html/SideBySide':
					case 'Html/Inline':
						$useCSS = true;
						$fileNamePart = explode('/', $v);
						$this->attachments[$v] = $path . '_' . $fileNamePart[1] . '.html';
						break;
					default :
						$this->attachments[$v] = null;
						break;
				}

				$className = 'Diff_Renderer_' . str_replace('/', '_', $v);

				if (!class_exists($className))
				{
					require_once dirname(__FILE__) . '/helpers/Diff/Renderer/' . $v . '.php';
				}

				// Generate a side by side diff
				$renderer = new $className;
				$text = $diff->Render($renderer);

				if (empty($text))
				{
					unset($this->attachments[$v]);
					$this->noDiffFound = true;
					break;
				}

				$this->diffs[$v] = $text;

				if ($useCSS)
				{
					$this->diffs[$v] = '<style>' . $css . '</style>' . PHP_EOL . $text;
				}

				if ($useCSS)
				{
					$text = '<html><head><meta http-equiv="content-type" content="text/html; charset=utf-8" /><style>'
						. $css . '</style></head><body>' . PHP_EOL . $text . '</body></html>';
				}

				if (!empty($this->attachments[$v]))
				{
					JFile::write($this->attachments[$v], $text);
				}
			}

			$session = JFactory::getSession();

			if (!empty($this->attachments) )
			{
				$session->set('Attachments', $this->attachments, $this->plg_name);
			}
		}

		/**
		 * Set's global plugin context array to know what type of content we work with
		 *
		 * @param   string  $context  Context
		 *
		 * @return   array
		 */
		public function _setContext ($context)
		{
			$this->context = array();
			$this->context['full'] = $context;
			$tmp = explode('.', $context);
			$this->context['option'] = $tmp[0];

			if (strpos($this->context['option'], 'com_') !== 0)
			{
				$this->context['option'] = 'com_' . $tmp[0];
			}

			$this->context['task'] = $tmp[1];
			$tmp = explode('_', $this->context['option']);

			if (isset($tmp[1]))
			{
				$this->context['extension'] = $tmp[1];
			}

			switch ($this->context['option'])
			{
				case 'com_categories':
					$this->context['extension'] = 'content';
					break;
				default :

					break;
			}
		}

		// ~ public function onBeforeHotspotSave($context, $contentItem, $isNew) { $this->onContentAfterSave($context, $contentItem, $isNew);	}
		// ~ public function onAfterHotspotSave($context, $contentItem, $isNew) { $this->onContentAfterSave($context, $contentItem, $isNew);	}

		/**
		 * Does the main job - send notifications
		 *
		 * @param   string  $context      Context
		 * @param   object  $contentItem  Content item object, e.g. Joomla article
		 * @param   bool    $isNew        If article is new
		 *
		 * @return   type  Description
		 */
		public function onContentAfterSave($context, $contentItem, $isNew)
		{
// ~ dumpTrace();
			$jinput = JFactory::getApplication()->input;

			if ($jinput->get('option', null) == 'com_dump')
			{
				return;
			}

			$debug = true;
			$debug = false;

			if ($debug)
			{
				dumpMessage('<b>' . __FUNCTION__ . '</b>');
				dump($contentItem, 'onContentAfterSave  context = ' . $context);
			}

			$context = $this->_contextAliasReplace($context, $contentItem);

			$this->_setContext($context);

			if ($debug)
			{
				dump($this->context, '$this->context');
			}

			// Show debug information
			if ($this->paramGet('showContext'))
			{
				$jtable_class = get_class($contentItem);
				$msg = array();
				$msg[] = '</p><div class="alert-message row-fluid">';
				$msg[] = '<p><a href="http://static.xscreenshot.com/2016/05/10/00/screen_31582c1a5da14780c3ab5f5181a4f46f" target="_blank"><small><b>'
					. $this->plg_name . '</b> ' . JText::_('JTOOLBAR_DISABLE') . ' ' . JText::_('NOTICE') . '</small></a></p>';
				$msg[] = '<b>Context:</b> ' . $context;
				$msg[] = '<br><b>Item table name:</b> ' . trim($jtable_class);

				$app = JFactory::getApplication();
				$msg[] = '
				<br/><button type="button" class="btn btn-warning btn-small object_values"  ><i class="icon-plus"></i></button><br/>
				<small class="object_values hide">
					<pre class="span6">
						<b>----' . $jtable_class . '----</b><br/>';

				NotificationAryHelper::buildExampleObject($contentItem, $msg);

				$user = JFactory::getUser();

				$msg[] = '
					</pre>';
				$msg[] = '
					<pre class="span6"><b>----' . get_class($user) . '----</b><br/>';

				NotificationAryHelper::buildExampleUser($user, $msg);

				$msg[] = '
					</pre>
				</small><br style="clear:both;" />
				</div><p>';

				$msg = implode('', $msg);

				// $msg .= '<pre>'.print_r ($contentItem, true) . '</pre>';
				$js = '';

				// Have to add script here, because K2 doesn't run any other function in the except onContentAfterSave,
				// but onContentAfterSave is fired in a way the $js can be added as inline code.
				if ($this->paramGet('showContext'))
				{
					$app = JFactory::getApplication();
					$scriptAdded = $app->get('##mygruz20160216061544', false);

					if (!$scriptAdded)
					{
						$document = JFactory::getDocument();

						$js = "<script type=\"text/javascript\">";
						$js .= "
							jQuery(document).ready(function(){
								//jQuery('small.object_values').toggle('hide');
								 jQuery('button.object_values').live('click', function(event) {
										jQuery(this).nextAll('small.object_values:first').toggle('show');
								 });
							});
						";

						$js .= "</script>";

						// Not to add scripit twice in mailBuildHelper.php
						$app->set('##mygruz20160216061544', true);
						$document->addScriptDeclaration($js);
					}
				}

				JFactory::getApplication()->enqueueMessage($msg . $js, 'notice');
			}

			if (!$this->_isContentEditPage($context) )
			{
				return;
			}

			// Blocks executing the plugin if notification switch is set to no
			if (!$this->onContentChangeStateFired)
			{
				// Needed for Notification switch in K2 {
				$session = JFactory::getSession();

				if (!$this->shouldShowSwitchCheckFlag)
				{
					// Is set for onAfterContentSave
					$this->shouldShowSwitchCheckFlag = $session->get('shouldShowSwitchCheckFlagK2Special', false, $this->plg_name);

					if ($this->shouldShowSwitchCheckFlag)
					{
						// ~ $jinput = JFactory::getApplication()->input;
						$jform = $jinput->post->getArray();
					}
				}

				// Clear anyway
				$session->clear('shouldShowSwitchCheckFlagK2Special', $this->plg_name);

				// Needed for Notification switch in K2 }
				if ($this->shouldShowSwitchCheckFlag)
				{
					$this->_debug('Notification switch check STARTED');

					if ($debug)
					{
						dump('here 1', '<b>' . __FUNCTION__ . '</b>');
					}

					if (!isset($jform))
					{
						$jform = $jinput->get('jform', null, null);
					}

					// Get from JForm to use if there is no in attribs or params. com_content
					// on saving at FE a New article uses 'params' while everywhere else 'attribs'
					$jform_runnotificationary = $session->get('shouldShowSwitchCheckFlagK2SpecialDefaultValue', false, $this->plg_name);

					// Clear anyway
					$session->clear('shouldShowSwitchCheckFlagK2SpecialDefaultValue', $this->plg_name);

					if (isset($jform['attribs']['runnotificationary']))
					{
						$jform_runnotificationary = $jform['attribs']['runnotificationary'];
					}
					elseif (isset($jform['params']['runnotificationary']))
					{
						$jform_runnotificationary = $jform['params']['runnotificationary'];
					}
					elseif (isset($jform['com_fields']['runnotificationary']))
					{
						$jform_runnotificationary = $jform['com_fields']['runnotificationary'];
					}

					if ($debug)
					{
						dump($jform_runnotificationary, '$jform_runnotificationary');
					}

					if (!$jform_runnotificationary)
					{
						return;
					}

					$this->_debug('Notification switch check PASSED', true);
				}
			}

			$this->contentItem = $this->_contentItemPrepare($contentItem);

			if ($debug)
			{
				dump($this->contentItem, '$this->contentItem = ');
			}

			$this->_debug('Rules which allow this content item STARTED');

			$rules = $this->_leaveOnlyRulesForCurrentItem($context, $this->contentItem, 'saveItem', $isNew);

			$this->task = 'saveItem';

			if ($this->onContentChangeStateFired)
			{
				foreach ($rules as $kk => $vv)
				{
					if (!$vv->oncontentchangestate)
					{
						unset($rules[$kk]);
					}
				}
			}

			if ($debug)
			{
				dump($rules, 'rules');
			}

			$this->_debug('Rules which allow this content item PASSED', false, $rules);

			if (empty($rules))
			{
				$this->_debug('<b style="color:Red;">No rules allow this content item</b>');

				return true;
			}

			if ($debug)
			{
				dump('here 2', '<b>' . __FUNCTION__ . '</b>');
			}

			$this->isNew = $isNew;
			$config = JFactory::getConfig();

			$this->sitename = $config->get('sitename');

			if (trim($this->sitename) == '')
			{
				$this->sitename = JURI::root();
			}

			$user = JFactory::getUser();
			$app = JFactory::getApplication();

			$ShowSuccessMessage = $this->paramGet('showsuccessmessage');
			$this->SuccessMessage = '';

			if ($ShowSuccessMessage == 1)
			{
				$this->SuccessMessage = $this->paramGet('successmessage');
			}

			$ShowErrorMessage = $this->paramGet('showerrormessage');
			$this->ErrorMessage = '';

			if ($ShowErrorMessage == 1)
			{
				$this->ErrorMessage = $this->paramGet('errormessage');
			}

			// Determine actions which has been perfomed
			// Must use the modified $this->contentItem
			if (isset($this->previous_state) && $this->previous_state == $this->contentItem->state)
			{
				$this->publish_state_change = 'nochange';
			}
			elseif (isset($this->previous_state) && $this->previous_state != $this->contentItem->state)
			{
				switch ($this->contentItem->state)
				{
					case '1':
						$this->publish_state_change = 'publish';
						break;
					case '0':
						$this->publish_state_change = 'unpublish';
						break;
					case '2':
						$this->publish_state_change = 'archive';
						break;
					case '-2':
						$this->publish_state_change = 'trash';
						break;
				}
			}

			// ~ $this->author = JFactory::getUser( $contentItem->created_by );
			$this->author = NotificationAryHelper::getUser($this->contentItem->created_by);

			if ($this->contentItem->modified_by > 0 )
			{
				$this->modifier = NotificationAryHelper::getUser($this->contentItem->modified_by);
			}
			else
			{
				$this->modifier = JFactory::getUser();
			}

			$this->isAjax = $this->paramGet('useajax');

			foreach ($rules as $rule_number => $rule)
			{
				$this->rule = $rule;

				$Users_to_send = $this->_users_to_send();

				$users_to_send_helper = $this->_addAuthorModifier();
				$Users_to_send = array_merge($Users_to_send, $users_to_send_helper);
				$Users_to_send = $this->_remove_mails($Users_to_send);

				if ($this->paramGet('debug') && !$this->isAjax)
				{
					// If jdump extension is installed and enabled
					$debugmsg = 'No messages are sent in the debug mode. You can check the users to be notified.';

					if (function_exists('dump') && function_exists('dumpMessage') )
					{
						dumpMessage($debugmsg);
						dump($Users_to_send, '$Users_to_send');
					}
					else
					{
							$msg = array();
							$msg[] = '<div style="color:red;">' . $debugmsg . '</div>';
							$msg[] = '<pre>$Users_to_send = ';
							$msg[] = print_r($Users_to_send, true);
							$msg[] = '</pre>';
							$msg = implode(PHP_EOL, $msg);
						JFactory::getApplication()->enqueueMessage($msg, 'notice');
					}

					// DO NOT SEND ANY MAILS ON DEBUG
					continue;
				}

				if ($this->isAjax)
				{
					if (!class_exists('fakeMailerClass'))
					{
						require_once dirname(__FILE__) . '/helpers/fakeMailerClass.php';
					}
				}

				$this->_send_mails($Users_to_send);

				if (!$this->isAjax)
				{
					$canLoginBackend = $user->authorise('core.login.admin');

					if (!empty ($this->broken_sends) && !empty($this->ErrorMessage))
					{
						// User has back-end access
						if ($canLoginBackend )
						{
							$email = " " . JText::_('PLG_SYSTEM_NOTIFICATIONARY_EMAILS') . implode(" , ", $this->broken_sends);
						}

						$app->enqueueMessage(
							JText::_(ucfirst($this->plg_name)) . ' (line ' . __LINE__ . '): ' . JText::_($this->ErrorMessage) . ' ' . $email,
							'error'
						);
					}
					elseif (empty ($this->broken_sends) && !empty($this->SuccessMessage) )
					{
						if (!empty($Users_to_send) )
						{
							$canLoginBackend = $user->authorise('core.login.admin');
							$successmessagenumberofusers = $this->paramGet('successmessagenumberofusers');
							$msg = JText::_($this->SuccessMessage);

							if ($canLoginBackend && $successmessagenumberofusers > 0)
							{
								$msg = $msg . ' ' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_USERS_NOTIFIED') . count($Users_to_send);

								// When publishing from list, the message is the same (the same number of users in notified).
								// So if publishing to items and 10 users should be notified per item, the message says 10 mails sent, while 20 is sent.

								// To make the messages be different we add id and title
								$msg .= ' :: ID: <b>' . $this->contentItem->id . '</b> ';

								if (!empty($this->contentItem->title))
								{
									$msg .= JText::_('PLG_SYSTEM_NOTIFICATIONARY_TITLE') . ' <b>: ' . $this->contentItem->title . '</b> ';
								}
							}

							$app->enqueueMessage($msg);
						}
					}
				}
			}

			if (!$this->isAjax)
			{
				$this->_cleanAttachments();
			}
			else
			{
				$session = JFactory::getSession();
				$attachments = $session->set('AjaxHash', $this->ajaxHash, $this->plg_name);
			}
		}

		/**
		 * Clean attachment files from the temp folder
		 *
		 * @return   void
		 */
		protected function _cleanAttachments ()
		{
			$session = JFactory::getSession();
			$session->set('Attachments', null, $this->plg_name);
			$session->set('Diffs', null, $this->plg_name);

			// Remove accidently unremoved attachments
			$files = JFolder::files(JFactory::getApplication()->getCfg('tmp_path'), 'diff_id_*', false, true);
			JFile::delete($files);
			$files = JFolder::files(JFactory::getApplication()->getCfg('tmp_path'), 'prev_version_id_*', false, true);
			JFile::delete($files);
			$files = JFolder::files(JFactory::getApplication()->getCfg('tmp_path'), $this->plg_name . '_*', false, true);
			JFile::delete($files);
		}

		/**
		 * Sends emails to all passed in array users
		 *
		 * @param   array  &$Users_to_send  Users to be notified
		 *
		 * @return   void
		 */
		protected function _send_mails (&$Users_to_send)
		{
			if (empty($Users_to_send) )
			{
				return;
			}

			$app = JFactory::getApplication();

			if ($this->paramGet('forceNotTimeLimit'))
			{
				$maxExecutionTime = ini_get('max_execution_time');
				set_time_limit(0);
			}

			foreach ($Users_to_send as $key => $value)
			{
				if (!empty($value['id']))
				{
					// ~ $user = JFactory::getUser($value['id']);
					$user = NotificationAryHelper::getUser($value['id']);
				}
				else
				{
					$user = NotificationAryHelper::getUserByEmail($value['email']);
				}

				if (empty($user->id))
				{
					$user = JFactory::getUser(0);
					$user->set('email', $value['email']);
				}

				$mail = $this->_buildMail($user);

				if (!$mail)
				{
					continue;
				}

				if ($this->isAjax)
				{
					$mailer = new fakeMailerClass;
				}
				else
				{
					// This object is not serializable, so I need to use a simple object to store and pass information to the ajax part
					$mailer = JFactory::getMailer();
				}

				if ($this->rule->emailformat != 'plaintext')
				{
					$mailer->isHTML(true);
					$mailer->Encoding = 'base64';
				}

				$mailer->setSubject($mail['subj']);

				$senderEmail = !empty($this->rule->sender_email) ? $this->rule->sender_email : $app->getCfg('mailfrom');
				$senderName = !empty($this->rule->sender_name) ? $this->rule->sender_name : $app->getCfg('fromname');
				$mailer->setSender(array($senderEmail, $senderName));

				$replyToEmail = !empty($this->rule->replyto_email) ? $this->rule->replyto_email : $app->getCfg('mailfrom');
				$replyToName = !empty($this->rule->replyto_name) ? $this->rule->replyto_name : $app->getCfg('fromname');

				$mailer->addReplyTo($replyToEmail, $replyToName);

				// ~ $mailer->setSender(array($app->getCfg('mailfrom'), $app->getCfg('fromname')));

				$mailer->addRecipient($mail['email'], $user->name);

				if (isset($this->rule->attachpreviousversion) )
				{
					foreach ($this->rule->attachpreviousversion as $k => $v)
					{
						if (isset($this->attachments[$v]))
						{
							$mailer->addAttachment($this->attachments[$v]);
						}
					}
				}

				if (isset($this->rule->attachdiffinfo) )
				{
					foreach ($this->rule->attachdiffinfo as $k => $v)
					{
						if (isset($this->attachments[$v]))
						{
							$mailer->addAttachment($this->attachments[$v]);
						}
					}
				}

				$curr_root = parse_url(JURI::root());
				$live_site_host = $curr_root['scheme'] . '://' . $curr_root['host'] . '/';
				$live_site = JURI::root();

				$link = $live_site . 'index.php?unsubscribe=' . $this->rule->__ruleUniqID
									. '&email=' . $user->email . '&hash=' . md5($user->id . $this->rule->__ruleUniqID);

				if ($this->rule->messagebodysource == 'hardcoded')
				{
					$includeunsubscribelink = $this->rule->ausers_includeunsubscribelink;

					if ($includeunsubscribelink)
					{
						if ($this->rule->emailformat == 'plaintext')
						{
							$mail['body'] .= PHP_EOL . PHP_EOL . JText::_('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBE') . ': ' . $link;
						}
						else
						{
							$mail['body'] .= '<br/><br/><a href="' . $link . '">' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBE') . '</a>';
						}
					}
				}
				else
				{
					$mail['body'] = str_replace('%UNSUBSCRIBE LINK%', $link, $mail['body']);
				}

				$mailer->setBody($mail['body']);

				if ($this->isAjax)
				{
					if (!isset($this->ajaxHash))
					{
						$this->ajaxHash = uniqid();
					}

					$mailer_ser = base64_encode(serialize($mailer));
					$tmpPath = JFactory::getApplication()->getCfg('tmp_path');
					$filename = $this->plg_name . '_' . $this->ajaxHash . '_' . uniqid();
					JFile::write($tmpPath . '/' . $filename, $mailer_ser);

					continue;
				}

				$send = $mailer->Send();

				if ($send !== true)
				{
					$this->broken_sends[] = $mail['email'];
				}
			}

			if ($this->paramGet('forceNotTimeLimit'))
			{
				set_time_limit($maxExecutionTime);
			}
		}

		/**
		 * Prepares users` to be notified data
		 *
		 * @return   array  Array with array element, which contain users data
		 */
		protected function _users_to_send()
		{
$debug = true;
$debug = false;

if ($debug)
{
	dump('users 1');
}

			// 0 => New and Updates; 1 => New only; 2=> Updated only
			$nofityOn = $this->rule->ausers_notifyon;

			// If notify only at New, but the article is not new
			if ($nofityOn == 1 && !$this->isNew )
			{
				return array();
			}

if ($debug)
{
	dump('users 2');
}

			// If notify only at Updated, but the article is New
			if ($nofityOn == 2 && $this->isNew )
			{
				return array ();
			}

if ($debug)
{
	dump('users 3');
}
			/*
			$onAction = $this->rule->ausers_notifyonaction;
				1=>ON_PUBLISH_ONLY;
				2=>ON_UNPUBLISH_ONLY;
				6=>ON_PUBLISH_OR_UNPUBLISH;
				3=>ON_CHANGES_IN_PUBLISHED_ONLY;
				4=>ON_CHANGES_IN_UNPUBLISHED_ONLY;
				5=>ALWAYS;
			*/
			$status_action_to_notify = (array) $this->rule->status_action_to_notify;
			$possible_actions = array('publish', 'unpublish', 'archive', 'trash');
				/*
				<option value="always">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ALWAYS</option>
				<option value="1">#~#JSTATUS#~#:#~#JPUBLISHED#~#</option>
				<option value="0">#~#JSTATUS#~#:#~#JUNPUBLISHED#~#</option>
				<option value="2">#~#JSTATUS#~#:#~#JARCHIVED#~#</option>
				<option value="-2">#~#JSTATUS#~#:#~#JTRASHED#~#</option>
				<option value="publish">#~#PLG_SYSTEM_NOTIFICATIONARY_ACTION#~#:#~#JTOOLBAR_PUBLISH#~#</option>
				<option value="unpublish">#~#PLG_SYSTEM_NOTIFICATIONARY_ACTION#~#:#~#JTOOLBAR_UNPUBLISH#~#</option>
				<option value="archive">#~#PLG_SYSTEM_NOTIFICATIONARY_ACTION#~#:#~#JTOOLBAR_ARCHIVE#~#</option>
				<option value="trash">#~#PLG_SYSTEM_NOTIFICATIONARY_ACTION#~#:#~#JTOOLBAR_TRASH#~#</option>
				*/

				/*
				* // Possible status changes (action)
				$this->publish_state_change = 'nochange';
				$this->publish_state_change = 'not determined';
				$this->publish_state_change = 'publish';
				$this->publish_state_change = 'unpublish';
				$this->publish_state_change = 'archive';
				$this->publish_state_change = 'trash';
				*/

if ($debug)
{
	dump($status_action_to_notify, '$status_action_to_notify');
	dump($this->publish_state_change, '$this->publish_state_change');
	dump($this->contentItem->state, '$this->contentItem->state');
}

			while (true)
			{
				if (in_array('always', $status_action_to_notify))
				{
					break;
				}

				// If current item status is among allowed statuses
				if (in_array((string) $this->contentItem->state, $status_action_to_notify))
				{
					break;
				}

				$intersect = array_intersect($status_action_to_notify, $possible_actions);

				// If there is an action among selected parameters in $status_action_to_notify
				if (!empty($intersect))
				{
					// Then we check the action happened to the content item
					if ($this->publish_state_change == 'nochange' || $this->publish_state_change == 'not determined')
					{
						// Do nothing, means returning empty array.
						// So if we want a notification on an action but the action cannot be determined, then noone has to be notified
					}
					elseif (in_array($this->publish_state_change, $status_action_to_notify))
					{
						break;
					}
				}

if ($debug)
{
	dump(
		$status_action_to_notify,
		'Content item status or action is now among allowed options. $this->contentItem->state ='
			. $this->contentItem->state . ' | $this->publish_state_change ='
			. $this->publish_state_change . ' | Allowed options '
	);
}

				return array();
			}

if ($debug)
{
	dump('users 4 - Content status or action is among allowed ones');
}

			$user = JFactory::getUser();

			// Check if notifications turned on for current user
			if (!$this->_checkAllowed($user, $paramName = 'allowuser'))
			{
				return array ();
			}

if ($debug)
{
	dump('users 5 - check if notifications turned on for current article ...');
}

			// Check if notifications turned on for current article
			if (!$this->_checkAllowed($this->contentItem, $paramName = 'article'))
			{
				return array ();
			}

if ($debug)
{
	dump('users 6 - start creating a list of emails ');
}

			$users_to_send = array();
			$UserIds = array ();

			$paramName = 'notifyuser';

			/*
			The variables keep the names of NA rule options telling
			which user groups and users to notify - all, selected, none
					<field name="ausers_notifyusergroups" maxrepeatlength="1" type="variablefield" basetype="list" default="1"
						label="PLG_SYSTEM_NOTIFICATIONARY_FIELD_USER_GROUP_LEVELS" description="PLG_SYSTEM_NOTIFICATIONARY_FIELD_USER_GROUP_LEVELS_DESC">
						<option value="1">PLG_SYSTEM_NOTIFICATIONARY_FIELD_SELECTION</option>
						<option value="2">PLG_SYSTEM_NOTIFICATIONARY_FIELD_EXCLUDE_SELECTION</option>
						<option value="0">JALL</option>
						<option value="-1">JNONE</option>
					</field>
			*/
			$groupName = 'ausers_' . $paramName . 'groups';
			$itemName = 'ausers_' . $paramName . 's';

			// Which group levels to be notified - all, none, selected
			// Group levels means either user groups of article categories
			$onGroupLevels = $this->rule->{$groupName};

			/*
			Which items to be notified - all, none, selected
			Items here means articles or users
					<field name="ausers_notifyusers" maxrepeatlength="1" type="variablefield" basetype="list" default="0"
					 label="PLG_SYSTEM_NOTIFICATIONARY_FIELD_SPECIFIC_USERS" description="PLG_SYSTEM_NOTIFICATIONARY_FIELD_SPECIFIC_DESC">
						<option value="1">PLG_SYSTEM_NOTIFICATIONARY_FIELD_SELECTION</option>
						<option value="2">PLG_SYSTEM_NOTIFICATIONARY_FIELD_EXCLUDE_SELECTION</option>
						<option value="0">PLG_SYSTEM_NOTIFICATIONARY_FIELD_NO_SPECIFIC_RULES</option>
					</field>
			 * */
			$onItems = $this->rule->{$itemName};

			// When to return no users selected
				if ($onGroupLevels == -1 && $onItems == 0)
				{
					return $users_to_send;
				}

				$GroupLevels = $this->rule->{$groupName . 'selection'};
				$UserIds = $this->rule->{$itemName . 'selection'};

				// If exclude some user groups, but no groups selected, then it's assumed that all groups are to be included
				if ($onGroupLevels == 2 && empty($GroupLevels))
				{
					$onGroupLevels = 0;
				}

				// If include some user groups, but no groups selected, then it's assumed that all groups are to be excluded
				if ($onGroupLevels == 1 && empty($GroupLevels))
				{
					$onGroupLevels = -1;
				}

				// If exclude/include some users, but no user ids selected, then it's assumed no specific rules applied per user
				if (($onItems == 1 || $onItems == 2) && empty($UserIds))
				{
					$onItems = 0;
				}

			$db = JFactory::getDBO();

			// Create WHERE conditions start here

			// Prepare ids of groups and items to include in the WHERE below
			while (true)
			{
				// If no limitation set - for user groups and specific users either all or selected - break
				if ($onGroupLevels == 0 && $onItems == 0)
				// ~ if (($onGroupLevels == 0  && $onItems == 0) || $onGroupLevels == 0  && $onItems == 1 )
				{
					break;
				}

				// If selected groups (otherwise, if no or all groups - we add nothing to WHERE)
				if ($onGroupLevels > 0)
				{
					if (!is_array($GroupLevels))
					{
						$GroupLevels = explode(',', $GroupLevels);
					}

					$GroupLevels = array_map('intval', $GroupLevels);
					$GroupLevels = array_map(array($db, 'Quote'), $GroupLevels);

					if ($onGroupLevels == 1)
					{
						$GroupWhere = 'AND';
					}
					elseif ($onGroupLevels == 2)
					{
						$GroupWhere = 'NOT';
					}
				}

				// If use selected user ids, then prepare the array of the ids for WHERE
				if ($onItems != 0)
				{
					if (!is_array($UserIds))
					{
						$UserIds = explode(',', $UserIds);
					}

					$UserIds = array_map('intval', $UserIds);
					$UserIds = array_map(array($db, 'Quote'), $UserIds);

					$UserWhere = 'AND';

					if ($onItems == 1)
					{
						$UserWhere = 'AND';
					}
					elseif ($onItems == 2)
					{
						$UserWhere = 'NOT';
					}
				}

				break;
			}

			// Just in case
			$GroupLevels = array_filter($GroupLevels);
			$UserIds = array_filter($UserIds);

			// $prevent_from_sending = array_filter($prevent_from_sending);
			$query = $db->getQuery(true);
			$query->select('name, username, email, id, group_id as gid ');
			$query->from('#__users AS users');
			$query->leftJoin('#__user_usergroup_map AS map ON users.id = map.user_id');
			$query->where('block = 0');
			$query->where($db->quoteName('id') . " <> " . $db->Quote($this->contentItem->created_by));

			if (!empty($this->contentItem->modified_by) && $this->contentItem->modified_by != $this->contentItem->created_by)
			{
				$query->where(" id <> " . $db->Quote($this->contentItem->modified_by));
			}

			if (!empty($GroupLevels))
			{
				$where = '';

				if (!empty($GroupWhere) && $GroupWhere == 'NOT')
				{
					$where .= $GroupWhere;
				}

				$where .= ' ( group_id = ' . implode(' OR group_id = ', $GroupLevels) . ')';
				$query->where($where);
			}

			if (!empty($UserIds))
			{
				$where = '';

				if ($UserWhere == 'NOT')
				{
					$where .= $UserWhere;
				}
				else
				{
					$where .= 'TRUE OR';
				}

				$where .= ' ( id = ' . implode(' OR id=', $UserIds) . ')';
				$query->where($where);
			}

			$query->group('id');

			$db->setQuery((string) $query);

			$users_to_send = $db->loadAssocList();

			// If the rule allows to subscribe manually,
			// then we have to check if the user has some subscription personalization
			switch ($this->rule->allow_subscribe)
			{
				/*
					<field name="allow_subscribe" maxrepeatlength="1" type="gjfields.variablefield" basetype="list" default="1"
						label="PLG_SYSTEM_NOTIFICATIONARY_FIELD_ALLOW_SUBSCRIBE"
						description="PLG_SYSTEM_NOTIFICATIONARY_FIELD_ALLOW_SUBSCRIBE_DESC">
						<option value="2">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ALLOW_RULE_SUBSCRIBE</option>
						<option value="1">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ALLOW_PER_CATEGORY_SUBSCRIBE</option>
						<option value="0">JNO</option>
					</field>
				 */
				// Per category subscribe or per rule subscribe?
				case '1':
				case '2':
					// Removed users which are not subscribed to the category
					foreach ($users_to_send as $k => $user)
					{
						if (is_array($this->contentItem->catid))
						{
							$unset = true;
							foreach ($this->contentItem->catid as $k => $catid)
							{
								if (NotificationAryHelper::checkIfUserSubscribedToTheCategory($this->rule, $user, $catid, $force = true))
								{
									$unset = false;
								}
							}

							if ($unset)
							{
								unset($users_to_send[$k]);
							}

						}
						else
						{
							if (!NotificationAryHelper::checkIfUserSubscribedToTheCategory($this->rule, $user, $this->contentItem->catid, $force = true))
							{
								unset($users_to_send[$k]);
							}
						}
					}

					break;

				// Do nothing, as users cannot subscribe themselves
				case '0':
				default :
					break;
			}

			$notifyonlyifcanview = $this->rule->ausers_notifyonlyifcanview;

			// E.g. joomla banner has no access option, so we ignore it here
			if ($notifyonlyifcanview && isset($this->contentItem->access))
			{
				foreach ($users_to_send as $k => $value)
				{
					if (!empty($value['id']))
					{
						// ~ $user = JFactory::getUser($value['id']);
						$user = NotificationAryHelper::getUser($value['id']);
					}
					else
					{
						$user = JFactory::getUser(0);
						$user->set('email', $value['email']);
					}

					$canView = false;

					// $canEdit = $user->authorise('core.edit', 'com_content.article.'.$this->contentItem->id);
					// $canLoginBackend = $user->authorise('core.login.admin');

					if (in_array($this->contentItem->access, $user->getAuthorisedViewLevels()))
					{
						$canView = true;
					}

					if (!$canView)
					{
						unset($users_to_send[$k]);
					}
				}
			}

			$Users_Add_emails = $this->rule->ausers_additionalmailadresses;
			$Users_Add_emails = explode(PHP_EOL, $Users_Add_emails);
			$Users_Add_emails = array_map('trim', $Users_Add_emails);

			foreach ($Users_Add_emails as $cur_email)
			{
				$cur_email = JString::trim($cur_email);

				if ($cur_email == "")
				{
					continue;
				}

				$add_mail_flag = true;

				foreach ($users_to_send as $v => $k)
				{
					if ($k['email'] == $cur_email )
					{
						$add_mail_flag = false;
						break;
					}
				}

				if ($add_mail_flag)
				{
					$users_to_send[]['email'] = $cur_email;
				}
			}

if ($debug)
{
	dump($users_to_send, 'users 7');
}

			return (array) $users_to_send;
		}

		/**
		 * Adds content authtor and/or modifier if needed
		 *
		 * @return   array  Array of arrays with author and modifier data
		 */
		protected function _addAuthorModifier ()
		{
			$users_to_send_helper = array();

			// If I'm the author and I modify the content item
			if ($this->author->id == $this->modifier->id )
			{
				if (!$this->rule->ausers_notifymodifier )
				{
					return array();
				}

				if ($this->rule->ausers_notifymodifier )
				{
					$users_to_send_helper[] = array (
							'id' => $this->modifier->id,
							'email' => $this->modifier->email,
							'name' => $this->modifier->name,
							'username' => $this->modifier->username
						);

					return $users_to_send_helper;
				}
			}

			// If I modify the content item, but I'm not the author
			if ($this->rule->ausers_notifymodifier )
			{
				$users_to_send_helper[] = array (
						'id' => $this->modifier->id,
						'email' => $this->modifier->email,
						'name' => $this->modifier->name,
						'username' => $this->modifier->username
					);
			}

			// ** If I'm the author, but someone else modifies my article ** //

			// If the article has no author, then go out
			if ($this->author->id == 0 )
			{
				return $users_to_send_helper;
			}

			// If the author should be notfied only for allowed modifiers
			if ($this->rule->author_foranyuserchanges == '0' && !$this->_checkAllowed($this->modifier, $paramName = 'allowuser'))
			{
				return $users_to_send_helper;
			}

			// If we are here, then I'm (current user, modifier) not the author, and the author is allowed to be notified about changes perfomed by me.
			// So we check now if the current action perfomed over the content item allows to notify the author

			/* $this->rule->author_notifyonaction options:
			<option value="0">PLG_SYSTEM_NOTIFICATIONARY_FIELD_NEVER</option>
			<option value="1">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ON_PUBLISH_ONLY</option>
			<option value="2">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ON_UNPUBLISH_ONLY</option>
			<option value="6">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ON_PUBLISH_OR_UNPUBLISH</option>
			<option value="3">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ON_CHANGES_IN_PUBLISHED_ONLY</option>
			<option value="4">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ON_CHANGES_IN_UNPUBLISHED_ONLY</option>
			<option value="5">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ALWAYS</option>
			*/

			$nauthor = $this->rule->author_notifyonaction;

			// Always
			if ($nauthor == '5')
			{
				$users_to_send_helper[] = array (
						'id' => $this->author->id,
						'email' => $this->author->email,
						'name' => $this->author->name,
						'username' => $this->author->username
					);

				return $users_to_send_helper;
			}

			while (true)
			{
				// If never to notify author
				if ($nauthor == '0')
				{
					break;
				}

				// If notify on `publish only` or on `unpublish only`, but the state was not changed
				if (($nauthor == '1' || $nauthor == '2')
					&& ($this->publish_state_change == 'nochange' || $this->publish_state_change == 'not determined'))
				{
					break;
				}

				// If notify on `publish or on unpublish` , but the state was not changed
				if ($nauthor == '6'  && ($this->publish_state_change == 'nochange' || $this->publish_state_change == 'not determined'))
				{
					break;
				}

				// If article is unpublished but is set to notify only in published articles
				if ($this->contentItem->state == '0' && $nauthor == '3' )
				{
					break;
				}

				// If article is published but is set to notify only in unpublished articles
				if ($this->contentItem->state == '1' && $nauthor == '4' )
				{
					break;
				}

				// If notify on `on publish or unpublish`, but the acion is not neiher published or unpublished
				if ($nauthor == '6' && !($this->publish_state_change == 'unpublish' || $this->publish_state_change == 'publish'))
				{
					break;
				}

				// If notify on `on publish only`, but the acion is not published
				if ($nauthor == '1' && !($this->publish_state_change == 'publish'))
				{
					break;
				}

				// If notify on `on unpublish only`, but the acion is not unpublished
				if ($nauthor == '2' && !($this->publish_state_change == 'unpublish'))
				{
					break;
				}

				// Add author to the list of receivers
				$users_to_send_helper[] = array ('id' => $this->author->id, 'email' => $this->author->email);
				break;
			}

			return $users_to_send_helper;
		}

		/**
		 * Don't remember
		 *
		 * @param   string  $name             Parameter name
		 * @param   string  $fieldNamePrefix  Where to get the name
		 *
		 * @return   mixed  Parameter value
		 */
		public function _getP ($name, $fieldNamePrefix)
		{
			if ($fieldNamePrefix == 'ausers')
			{
				return $this->rule->{$name};
			}
			else
			{
				return $this->paramGet($name);
			}
		}

		/**
		 * Checks if the passed $object is allowed by a group of options
		 *
		 * E.g. checks is a current user is among allowed with a group of options users
		 * Check user groups and specific users
		 * Or if a current content item is among allowed content items.
		 * Checks categories and specific content items
		 * The XML plugin file structure must follow a convention to let the function work. E.g. for users:
		 * - select if all or selected user groups to use or to exclude selected (all, include, exclude)
		 * - select specific user groups to use or to exclude
		 * - select if all or selected specific user to use or to exclude selected (all, include, exclude)
		 * - select specific users to use or to exclude
		 * XML example
		 *		<field name="ausers_allowusergroups" maxrepeatlength="1" type="variablefield" basetype="list"
		 * 			default="0"
		 * 			label="PLG_SYSTEM_NOTIFICATIONARY_FIELD_USER_GROUP_LEVELS" description="PLG_SYSTEM_NOTIFICATIONARY_FIELD_NOTIFY_ON_DESC">
		 *				<option value="1">PLG_SYSTEM_NOTIFICATIONARY_FIELD_SELECTION</option>
		 *				<option value="2">PLG_SYSTEM_NOTIFICATIONARY_FIELD_EXCLUDE_SELECTION</option>
		 *				<option value="0">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ALL</option>
		 *		</field>
		 *		<field name="{ausers_allowusergroups12" maxrepeatlength="1" type="variablefield" basetype="toggler" param="ausers_allowusergroups" value="1,2"/>
		 *			<field name="ausers_allowusergroupsselection" maxrepeatlength="1" type="variablefield" basetype="usergroup"
		 * 				multiple="multiple" notregistered="0" publicfrontend="disable" registred="disable" default=""
		 * 				label="" description="PLG_SYSTEM_NOTIFICATIONARY_FIELD_USER_GROUP_LEVELS_DESC"/>
		 *		<field name="ausers_allowusergroups12}" maxrepeatlength="1" type="variablefield" basetype="toggler"/>
		 *
		 *	<field name="ausers_allowusers" maxrepeatlength="1" type="variablefield" basetype="list" default="0"
		 * 		label="PLG_SYSTEM_NOTIFICATIONARY_FIELD_SPECIFIC_USERS" description="PLG_SYSTEM_NOTIFICATIONARY_FIELD_NOTIFY_ON_DESC">
		 *			<option value="1">PLG_SYSTEM_NOTIFICATIONARY_FIELD_SELECTION</option>
		 *			<option value="2">PLG_SYSTEM_NOTIFICATIONARY_FIELD_EXCLUDE_SELECTION</option>
		 *			<option value="0">PLG_SYSTEM_NOTIFICATIONARY_FIELD_ALL</option>
		 *	</field>
		 *	<field name="{ausers_allowusers12" maxrepeatlength="1" type="variablefield" basetype="toggler" param="ausers_allowusers" value="1,2"/>
		 *		<field name="ausers_allowusersselection" maxrepeatlength="1" type="variablefield"
		 * 			basetype="users" default="" label="PLG_SYSTEM_NOTIFICATIONARY_FIELD_USER_IDS"
		 * 			description="PLG_SYSTEM_NOTIFICATIONARY_FIELD_USER_IDS_DESC"/>
		 *	<field name="ausers_allowusers12}" maxrepeatlength="1" type="variablefield" basetype="toggler"/>
		 *	Function call to check if allowed:
		 * $user = JFactory::getUser();
		 * if (!$this->_checkAllowed($user, $paramName = 'allowuser', $fieldNamePrefix='ausers' )) { return; }
		 *
		 * @param   object  &$object          Either content item object or Joomla user object
		 * @param   string  $paramName        Param name, example 'allowuser'
		 * @param   string  $fieldNamePrefix  See the example
		 *
		 * @return  bool  true if the object is allowed according to the group of options
		 */
		public function _checkAllowed(&$object, $paramName, $fieldNamePrefix='ausers')
		{
$debug = true;
$debug = false;

			if (empty($this->task))
			{
				$this->task = '';
			}

			$className = get_class($object);

if ($debug)
{
	dumpMessage('<b>' . __FUNCTION__ . '</b>');
	dumpMessage('<b>' . $className . '</b>');
}

			if (!empty($this->task) && $this->task == 'saveItem')
			{
				$this->_debug(' > <b>' . $className . '</b>');

				if (in_array($className, ['JUser', 'Joomla\CMS\User\User']))
				{
					$selectionDebugTextGroups = '<i>user groups</i>';
					$selectionDebugTextSpecific = '<i>specific users</i>';
				}
				else
				{
					$selectionDebugTextGroups = '<i>categories</i>';
					$selectionDebugTextSpecific = '<i>specific content items</i>';
				}
			}

			if (in_array($className, ['JUser', 'Joomla\CMS\User\User']) && !empty($this->rule))
			{
				foreach ($this->rule->usersAddedByEmail as $user)
				{
					if ($user->id == $object->id)
					{
						return true;
					}
				}
			}

			if (!in_array($className, ['JUser', 'Joomla\CMS\User\User']) && empty($object->id))
			{
				$msg = '';

if ($debug)
{
	$msg = var_dump(debug_backtrace(), true);
}

				JFactory::getApplication()->enqueueMessage(
					JText::_(ucfirst($this->plg_name))
						. ' (line ' . __LINE__ . '): '
						. ' _checkAllowed method cannot be run with an empty object<br/>' . $msg,
					'error'
				);

				return false;
			}

			if (!in_array($className, ['JUser', 'Joomla\CMS\User\User']) && $this->task == 'saveItem')
			{
				$this->rule->content_language = (array) $this->rule->content_language;

				if (empty($this->rule->content_language) || in_array('always', $this->rule->content_language) )
				{
					// Do nothing
				}
				else
				{
					if (!in_array($object->language, $this->rule->content_language))
					{
						return false;
					}
				}
			}

if ($debug)
{
	dumpMessage('here 1');
}

			$groupName = $fieldNamePrefix . '_' . $paramName . 'groups';
			$itemName = $fieldNamePrefix . '_' . $paramName . 's';
			$onGroupLevels = $this->_getP($groupName, $fieldNamePrefix);
			$onItems = $this->_getP($itemName, $fieldNamePrefix);

			switch ($onGroupLevels)
			{
				case '0':
					$onGroupLevels = 'all';
					break;
				case '1':
					$onGroupLevels = 'include';
					break;
				case '2':
					$onGroupLevels = 'exclude';
					break;
			}

			switch ($onItems)
			{
				case '0':
					$onItems = 'all';
					break;
				case '1':
					$onItems = 'include';
					break;
				case '2':
					$onItems = 'exclude';
					break;
			}

if ($debug)
{
	dump($onGroupLevels, $groupName);
	dump($onItems, $itemName);
}

			if (!empty($this->task) && $this->task == 'saveItem')
			{
				$this->_debug(' > ' . $selectionDebugTextGroups . ' selection', false, $onGroupLevels);
				$this->_debug(' > Specific ' . $selectionDebugTextSpecific . ' selection', false, $onItems);
			}

			// Allowed for all
			if ($onGroupLevels == 'all' && $onItems == 'all')
			{
				if (!empty($this->task) && $this->task == 'saveItem')
				{
					$this->_debug(' > Always allowed. PASSED');
				}

				return true;
			}

if ($debug)
{
	dumpMessage('here 2');
}
			// Get which group the user belongs to, or which category the user belongs to
			switch ($className)
			{
				// If means &object is user, not article
				case "JUser":
				case "Joomla\CMS\User\User":
					$object->temp_gid = $object->get('groups');

					if ($object->temp_gid === null)
					{
							$table   = JUser::getTable();
							$table->load($object->id);
							$object->temp_gid = $table->groups;
					}

					if (empty($object->temp_gid))
					{
						$object->temp_gid = array($object->gid);
					}
					break;

				// If means &object is article, not user
				default:
					$object->temp_gid = (array) $object->catid;
					break;
			}

			if (!empty($this->task) && $this->task == 'saveItem')
			{
				$this->_debug(' > Current obect ' . $selectionDebugTextGroups . ' (ids)', false, $object->temp_gid);
			}

			// If not all grouplevels allowed then check if current user is allowed
			$isOk = false;

			$groupToBeIncluded = false;
			$groupToBeExcluded = false;

			if ($onGroupLevels != 'all' )
			{
				// Get user groups/categories to be included/excluded
				$GroupLevels = $this->_getP($groupName . 'selection', $fieldNamePrefix);

				if (!is_array($GroupLevels))
				{
					$GroupLevels = explode(',', $GroupLevels);
				}

				if (!empty($this->task) && $this->task == 'saveItem')
				{
					$this->_debug(' > ' . $selectionDebugTextGroups . ' included/excluded', false, $GroupLevels);
				}

				// Check only categories, as there are no sections
				$gid_in_array = false;

				foreach ($object->temp_gid as $gid)
				{
					if (in_array($gid, $GroupLevels))
					{
						$gid_in_array = true;
						break;
					}
				}

				if ($onGroupLevels == 'include' && $gid_in_array)
				{
					$groupToBeIncluded = true;

					if (!empty($this->task) && $this->task == 'saveItem')
					{
						$this->_debug(' > Is allowed based on ' . $selectionDebugTextGroups . ' YES');
					}
				}
				elseif ($onGroupLevels == 'exclude' && $gid_in_array)
				{
					$groupToBeExcluded = true;

					if (!empty($this->task) && $this->task == 'saveItem')
					{
						$this->_debug(' > Is NOT allowed based on ' . $selectionDebugTextGroups . ' YES');
					}
				}
			}

			// ~ $isOk = false;
			$forceInclude = false;
			$forceExclude = false;

			// If not all user allowed then check if current user is allowed
			if ($onItems != 'all' )
			{
				$Items = $this->_getP($itemName . 'selection', $fieldNamePrefix);

				if (!is_array($Items))
				{
					$Items = explode(',', $Items);
				}

				$item_in_array = in_array($object->id, $Items);

				if (!empty($this->task) && $this->task == 'saveItem')
				{
					$this->_debug(' > ' . $selectionDebugTextSpecific . ' included/excluded', false, $Items);
				}

				if ($onItems == 'include' && $item_in_array)
				{
					$forceInclude = true;

					if (!empty($this->task) && $this->task == 'saveItem')
					{
						$this->_debug(' > Is FORCED to be INCLUDED based on ' . $selectionDebugTextSpecific . '');
					}

					return true;
				}
				elseif ($onItems == 'exclude' && $item_in_array)
				{
					$forceExclude = true;

					if (!empty($this->task) && $this->task == 'saveItem')
					{
						$this->_debug(' > Is FORCED to be EXCLUDED based on ' . $selectionDebugTextSpecific . '');
					}

					return false;
				}
			}

if ($debug)
{
	dumpMessage('here 3');
}

			if (!empty($this->task) && $this->task == 'saveItem')
			{
				$this->_debug(' > Is ALLOWED based on ' . $selectionDebugTextSpecific . ' YES');
			}

			$itemAllowed = true;

			if ($groupToBeIncluded)
			{
				if (!empty($this->task) && $this->task == 'saveItem')
				{
					$this->_debug(' > Object belongs to included ' . $selectionDebugTextGroups . '. CHECK PASSED');
				}

				return true;
			}

if ($debug)
{
	dumpMessage('here 4');
}

			if ($groupToBeExcluded)
			{
				if (!empty($this->task) && $this->task == 'saveItem')
				{
					$this->_debug(' > Object belongs to excluded ' . $selectionDebugTextGroups . '. CHECK FAILED');
				}

				return false;
			}

if ($debug)
{
	dumpMessage('here 5');
}

			if ($onGroupLevels == 'exclude' && !$groupToBeExcluded )
			{
				if (!empty($this->task) && $this->task == 'saveItem')
				{
					$this->_debug(' > Object doesn\'t belong to excluded ' . $selectionDebugTextGroups . '. CHECK PASSED');
				}

				return true;
			}

if ($debug)
{
	dumpMessage('here 6');
}

			if (!empty($this->task) && $this->task == 'saveItem')
			{
				$this->_debug(' > Object does not belong to included ' . $selectionDebugTextGroups . '. CHECK FAILED');
			}

			return false;
		}

		/**
		 * Removes emails which should not be notified
		 *
		 * @param   array  $Users_to_send  Array with users to be notified
		 *
		 * @return   array  Array with removed user items if needed
		 */
		protected function _remove_mails ($Users_to_send)
		{
			$Users_Exclude_emails = $this->rule->ausers_excludeusers;
			$Users_Exclude_emails = explode(PHP_EOL, $Users_Exclude_emails);
			$Users_Exclude_emails = array_map('trim', $Users_Exclude_emails);

			foreach ($Users_Exclude_emails as $cur_email)
			{
				$cur_email = JString::trim($cur_email);

				if ($cur_email == "")
				{
					continue;
				}

				foreach ($Users_to_send as $v => $k)
				{
					if ($k['email'] == $cur_email)
					{
						unset ($Users_to_send[$v]);
						break;
					}
				}
			}

			return $Users_to_send;
		}

		/**
		 * Builds content item link
		 *
		 * It's important that site/view is called before site/edit, as Itemid is stored after site/view run
		 *
		 * @param   string  $zone  Site scope: admin/site
		 * @param   string  $task  Task: view/edit
		 * @param   string  $lang  Language code
		 *
		 * @return   string  SEF(if possible) link
		 */
		protected function _buildLink($zone = 'site', $task = 'edit', $lang = null)
		{
			if ($lang == null && $this->langShortCode != null )
			{
				$lang = '&lang=' . $this->langShortCode;
			}

			// Do not run every time, only once
			if (isset($this->link[$zone][$task][$lang]))
			{
				return $this->link[$zone][$task][$lang];
			}

			$link = '';
			$curr_root = parse_url(JURI::root());
			$live_site_host = $curr_root['scheme'] . '://' . $curr_root['host'] . '/';
			$live_site = JURI::root();

			switch ($task)
			{
				case 'edit':
					$link = 'index.php?option=' . $this->context['option'] . '&task=' . $this->context['task'] . '.edit';

					if ($zone == 'site')
					{
						switch ($this->context['option'])
						{
							/*
							case 'com_k2':
								* // $link = false;
								$link = 'index.php?option='.$this->context['option']
								 	.'&view='.$this->context['task'].'&task=edit&cid='.$this->contentItem->id.'&tmpl=component';
								* // index.php?option=com_k2&view=item&task=edit&cid=1&tmpl=component&Itemid=510
								break;
							case 'com_newsfeed':
							case 'com_banners':
							*/
							case 'com_newsfeeds':
							case 'com_tags':
							case 'com_users':
								$link = false;
								break;
							/*
							case 'com_jdownloads':
								$link = $link . '&'.'a_id='.$this->contentItem->id;
								break;
							*/
							default :
								if ($this->rule->extension_info['Frontend edit link'] === false)
								{
									$link = false;
								}
								elseif (empty($this->rule->extension_info['Frontend edit link']))
								{
									// Try to use some default link form
									$link = $link . '&' . $this->context['task'][0] . '_id=' . $this->contentItem->id;
								}
								else
								{
									$link = str_replace('##ID##', $this->contentItem->id, $this->rule->extension_info['Frontend edit link']);

									/* For ZOO frontend edit link
									 * Check /administrator/components/com_zoo/helpers/route.php line 394
									 * /administrator/components/com_zoo/helpers/submission.php line 62
									 * /administrator/components/com_zoo/framework/helpers/system.php line 56
									 */
									if (strpos($this->rule->extension_info['Frontend edit link'], '##SUBMISSION_HASH##') !== false)
									{
										$submission_id = null;
										$type_id = 'article';
										$item_id = $this->contentItem->id;
										$edit = 1;
										$seed = $submission_id.$type_id.$item_id.$edit;

										// index.php?option=com_zoo&view=submission&layout=submission&submission_id=&type_id=article&item_id=##ID##&redirect=itemedit
										$seed = JApplication::getHash($seed);
										$link = str_replace('##SUBMISSION_HASH##', $seed, $link);
									}
								}

								break;
						}

						// Get previously stored Itemid and attach to the current FE link if needed
						if ($link)
						{
							$checkIfItemIdExists = NotificationAryHelper::getVarFromQuery($link, 'Itemid');

							if (empty($checkIfItemIdExists) && !empty($this->link_itemid[$zone]['view'][$lang]))
							{
								$link .= '&Itemid=' . $this->link_itemid[$zone]['view'][$lang];
							}

							$link = $this->_makeSEF($link);
						}
					}
					elseif ($zone == 'admin')
					{
						switch ($this->context['option'])
						{
							/* /administrator/index.php?option=com_k2&view=item&cid=1
							case 'com_k2':
								$link = 'index.php?option='.$this->context['option'].'&view='.$this->context['task'].'&cid='.$this->contentItem->id;
								break;
							*/
							/* /administrator/index.php?option=com_jdownloads&task=download.edit&file_id=36
							case 'com_jdownloads':
								$link = $link . '&file_id='.$this->contentItem->id;
								$link = "administrator/index.php?option='.$this->context['option'].'&task=article.edit&id=".$this->contentItem->id;
								break;
							*/
							default :
								// ~ if (isset($this->rule->extension_info)) {
								if ($this->rule->extension_info['Backend edit link'] === false )
								{
									$link = false;
								}
								elseif (empty($this->rule->extension_info['Backend edit link']) )
								{
									$link = $link . '&id=' . $this->contentItem->id;
								}
								else
								{
									$link = str_replace('##ID##', $this->contentItem->id, $this->rule->extension_info['Backend edit link']);
								}
								break;
						}

						$link = 'administrator/' . $link;
						$link = $live_site . $link;
					}
					break;
				case 'view':
					$app = JFactory::getApplication();
					$break = false;

					$catid = (is_array($this->contentItem->catid)) ? $this->contentItem->catid[0] : $this->contentItem->catid;

					switch ($this->context['option'])
					{
						case 'com_users':
						case 'com_banners':
							$link = false;
							$break = true;
							break;
						default :
							$extension_info = $this->rule->extension_info;

							if ($extension_info['View link'] === false)
							{
								$link = false;
							}
							elseif (!empty($extension_info['View link']))
							{
								$link = str_replace('##ID##', $this->contentItem->id, $extension_info['View link']);

								// Need to find Itemid at backend
								if ($app->isAdmin() && strpos($link, 'Itemid=') === false)
								{
									if (!empty($extension_info['RouterClass::RouterMethod']))
									{
										$parts = explode('::', $extension_info['RouterClass::RouterMethod']);
										JLoader::register($parts[0], JPATH_ROOT . '/components/' . $this->context['option'] . '/helpers/route.php');
										$link = $parts[0]::{$parts[1]}($this->contentItem->id, $catid);
									}
									else
									{
										$db = JFactory::getDBO();
										$query = $db->getQuery(true);
										$query->select('id')->from('#__menu')->where($db->quoteName('link') . " = " . $db->Quote($link));
										$query->where($db->quoteName('menutype') . " <> " . $db->Quote('main'));
										$query->where($db->quoteName('published') . " = " . $db->Quote('1'));
										$db->setQuery((string) $query);
										$Itemid = $db->loadResult();

										if (!empty($Itemid))
										{
											$link	.= '&Itemid=' . $Itemid;
										}
										else
										{
											// Do nothing
										}
									}
								}
								else
								{
									$link = str_replace('##ID##', $this->contentItem->id, $this->rule->extension_info['View link']);
								}
							}
							else
							{
								$break = false;

								switch ($this->context['option'])
								{
									case 'com_users':
									case 'com_banners':
										$link = false;
										$break = true;
										break;
									default :
										$this->contentItem->slug = $this->contentItem->id . ':' . $this->contentItem->alias;
										break;
								}

								if ($break)
								{
									break;
								}

								// $this->contentItem->slug = $this->contentItem->id.':'.$this->contentItem->alias;
								$routerClass = $this->context['extension'] . 'HelperRoute';
								$routerMethod = 'get' . $this->context['task'] . 'Route';

								JLoader::register($routerClass, JPATH_ROOT . '/components/' . $this->context['option'] . '/helpers/route.php');

								if (class_exists($routerClass) && method_exists($routerClass, $routerMethod))
								{
									if ( $this->real_context == "com_categories.category")
									{
										$catid = $this->contentItem->id;
									}
									else
									{
										$catid = (is_array($this->contentItem->catid)) ? $this->contentItem->catid[0] : $this->contentItem->catid;
									}

									switch ($this->context['full'])
									{
										/*
										case "com_dpcalendar.category":
											$link	= $routerClass::$routerMethod($this->contentItem->id);
											break;
										case "com_k2.item":
											$link	= $routerClass::$routerMethod($this->contentItem->id);
											$link2 = K2HelperRoute::getItemRoute($item->id.':'.urlencode($item->alias), $item->catid.':'.urlencode($item->category->alias));
											break;
										*/
										default :
											$link	= $routerClass::$routerMethod($this->contentItem->id, $catid, $this->contentItem->language);
											break;
									}
									// $link	= ContentHelperRoute::getArticleRoute($this->contentItem->slug, $this->contentItem->catid, $this->contentItem->language);
								}
								else
								{
									switch ($this->context['extension'])
									{
										/*
										case 'k2':
											if ( $app->isAdmin() ) {
												$db = JFactory::getDBO();
												$query = $db->getQuery(true);
												$query->select('id')->from('#__menu')->where($db->quoteName('link')." = ".$db->Quote($link));
												$db->setQuery((string)$query);
												$Itemid = $db->loadResult();
												if (!empty($Itemid)) {
													$link	.= '&Itemid=' . $Itemid;
												}
												$link2 = ContentHelperRoute::getArticleRoute($this->contentItem->slug, $this->contentItem->catid, $this->contentItem->language);
											}
											break;
										*/
										default :
											$link = 'index.php?option=' . $this->context['option'];
											$link .= '&view=' . $this->context['task'] . '&id=' . $this->contentItem->id;
											break;
									}
								}

								$link = str_replace($this->contentItem->slug, $this->contentItem->id, $link);
								$link = str_replace($live_site, '', $link);
							}
							break;
					}

					if ($break)
					{
						break;
					}

					if ($link)
					{
						$this->link_itemid[$zone][$task][$lang] = NotificationAryHelper::getVarFromQuery($link, 'Itemid');
						$link = $this->_makeSEF($link);
					}
					break;
			}

			if ($link)
			{
				$link = JPath::clean($link);
				$link = str_replace(':/', '://', $link);
				$link = str_replace('&amp;', '&', $link);
			}

			$this->link[$zone][$task][$lang] = $link;

			return $this->link[$zone][$task][$lang];
		}

		/**
		 * Tries to get properly sef link, especially needed when working in backend
		 *
		 * @param   string  $link  Non-sef Joomla link
		 *
		 * @return   string  SEF joomla link if is possible or needed to be SEF
		 */
		private function _makeSEF($link)
		{
			$conf = JFactory::getConfig();

			if ($conf->get('sef') != 1)
			{
				$live_site_host = JURI::root();

				return $live_site_host . $link;
			}

			$curr_root = parse_url(JURI::root());

			// Add non-standard port if needed
			$port = isset($curr_root['port']) ? ':' . $curr_root['port'] : '';

			$live_site_host = $curr_root['scheme'] . '://' . $curr_root['host'] . $port . '/';

			$app = JFactory::getApplication();

			if ($app->isAdmin())
			{
				// After struggling much with getting proper SEF link from Backend, I had to use this remote call
				$user = JFactory::getUser();

				// I create a fake session as a flag to show onAjaxNotificationAryGetFEURL that it's a call from myself
				$session = JFactory::getSession();
				$sessionId = $session->getId();

				// Get current user password hash to later restore it
				$db		= JFactory::getDBO();
				$query	= $db->getQuery(true);
				$query->select(array('password'));
				$query->from('#__users');
				$query->where('id=' . $db->Quote($user->id));
				$db->setQuery($query);
				$origPass = $db->loadResult();

				// Build remote link
				$url_ajax_plugin = JRoute::_(
							JURI::ROOT()
							// It's a must
							. '?option=com_ajax&format=raw'
							. '&group=' . $this->plg_type
							. '&plugin=notificationAryGetFEURL'
							// . '&' . JSession::getFormToken() . '=1'
							. '&userid=' . $user->id
							. '&sid=' . $sessionId
							// Pass your data if needed
							. '&serialize=' . base64_encode(serialize($link))
					);

				$res_link = $link;

				if (function_exists('curl_version') && false)
				{
					$curlSession = curl_init();
					curl_setopt($curlSession, CURLOPT_URL, $url_ajax_plugin);
					curl_setopt($curlSession, CURLOPT_BINARYTRANSFER, true);
					curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
					$data = curl_exec($curlSession);
					curl_close($curlSession);
					$res_link = $data;
				}
				elseif (ini_get('allow_url_fopen'))
				{
					$res_link = file_get_contents($url_ajax_plugin);
				}

				if (trim($res_link) == '')
				{
					$res_link = $link;
				}

				$query	= $db->getQuery(true);

				$query->update('#__users');
				$query->set('password = ' . $db->Quote($origPass));
				$query->where('id=' . $db->Quote($user->id));
				$db->setquery($query);
				$db->execute();

				if (strpos($res_link, 'http:/') === false && strpos($res_link, 'https:/') === false)
				{
					$link = $live_site_host . '/' . $res_link;
				}
				else
				{
					$link = $res_link;
				}

				$link = JPath::clean($link);
			}
			else
			{
				$app = JApplication::getInstance('site');
				$router = $app->getRouter();
				$url = $router->build($link);
				$url->setHost($live_site_host);
				$url = $url->toString();
				$url = JPath::clean($url);
				$link = $url;

				if ($this->isNew)
				{
					$jinput = JFactory::getApplication()->input;
					$submit_url = JRoute::_('index.php?Itemid=' . $jinput->get('Itemid', null));
					$submit_url = JPath::clean($live_site_host . $submit_url);
					$link = str_replace($submit_url, $live_site_host, $link);
				}
			}

			return $link;
		}

		/**
		 * The function to be called from as Ajax to build really working SEF links from backend.
		 *
		 * Creates a backend user session, gets JRoute::_() and delets the session.
		 * It's a must to fake login-logout at FE, as JRoute::_() doesn't create correct links
		 * for e.g. content items limited to Registred if you are not logged in at FE.
		 *
		 * @return   void
		 */
		public function onAjaxNotificationAryGetFEURl()
		{
			$app	= JFactory::getApplication();

			// Has to work as a FE called function
			if ($app->isAdmin())
			{
				return;
			}

			$jinput = JFactory::getApplication()->input;
			$userId = $jinput->get('userid', null);

			if (empty($userId))
			{
				return;
			}

			$serialize = $jinput->get('serialize', null, 'string');

			$url = unserialize(base64_decode($serialize));

			$sid = $jinput->get('sid', null);

			if (empty($serialize) || empty($sid) || empty($url))
			{
				return;
			}

			// ~ $hash = JApplicationHelper::getHash($userId . $sid . $url);

			// Get the database connection object and verify its connected.
			$db = JFactory::getDbo();

			try
			{
				// Get the session data from the database table.
				$query = $db->getQuery(true)
					->select($db->quoteName('session_id'))
				->from($db->quoteName('#__session'))
				->where($db->quoteName('session_id') . ' = ' . $db->quote($sid));

				$db->setQuery($query);
				$rows = $db->loadRowList();
			}
			catch (RuntimeException $e)
			{
				return;
			}

			if (count($rows) < 1)
			{
				return;
			}

			$user	= JFactory::getUser();

			if ($user->id == $userId)
			{
				echo JRoute::_($url);

				return;
			}

			$instance = JFactory::getUser($userId);

			if (empty($instance->id))
			{
				return;
			}

			// Temporary login user at FE

			/*
			Get the user which has the current token form DB to
			temporary store one's password. We need this to perform
			autologin. We cannot decode the existing password, so we
			use a temporary known password, login and then after
			login restore the preserved password directly in the
			 database
			*/
			$session = JFactory::getSession();


			// Set a temporary password for the user
			$temp_pass = JApplicationHelper::getHash(JUserHelper::genRandomPassword());

			$query = $db->getQuery(true);
			$query->update('#__users');
			$query->set('password = ' . $db->Quote(md5($temp_pass)));
			$query->where('id=' . $db->Quote($userId));
			$db->setquery($query);
			$db->execute();

			$credentials = array ('username' => $instance->username, 'password' => $temp_pass);
			$result = $app->login($credentials);

			$url = JRoute::_($url);
			echo $url;

			$session->close();

			die();
		}

		/**
		 * Builds mail body and subject
		 *
		 * @param   JUserObject  $user  Joomla use object
		 *
		 * @return   mixed  False or array with mail parts (subject and body)
		 */
		protected function _buildMail ($user)
		{
			// Need this for authors and modifiers as they are not checked anywhere else
			if ($user->block == 1)
			{
				return false;
			}

			if ($user->id == 0 )
			{
				// If it's an added directly email, then make it clear to later IF-ELSE statements
				$user->id = -1;
			}

			if ($user->id == $this->author->id || $user->id == $this->modifier->id)
			{
				// Do not cache for author or modifier
			}
			else
			{
				if (!$this->rule->personalize)
				{
					$userGroupToCache = $user->groups;
					sort($userGroupToCache);
					$userGroupToCache = implode(',', $userGroupToCache);
					$hash = $this->contentItem->id . '|' . $userGroupToCache;

					if (isset($this->rule->cachedMailBuilt[$hash]))
					{
						$mail = $this->rule->cachedMailBuilt[$hash];
						$mail['email'] = $user->email;

						return $mail;
					}
				}
			}

			static $user_language_loaded = false;
			$app = JFactory::getApplication();

			if ( $app->isAdmin() )
			{
				$lang_code = $user->getParam('admin_language');
			}
			else
			{
				$lang_code = $user->getParam('language');
			}

			$language = JFactory::getLanguage();

			if (!empty($lang_code) && $lang_code != $this->default_lang)
			{
				$language->load($this->plg_base_name, JPATH_ADMINISTRATOR, $lang_code, true);
				$language->load($this->plg_full_name, JPATH_ADMINISTRATOR, $lang_code, true);
				$user_language_loaded = true;
			}
			elseif ($user_language_loaded)
			{
				$language->load($this->plg_base_name, JPATH_ADMINISTRATOR, 'en-GB', true);
				$language->load($this->plg_full_name, JPATH_ADMINISTRATOR, 'en-GB', true);

				if ($this->default_lang != 'en-GB')
				{
					$language->load($this->plg_base_name, JPATH_ADMINISTRATOR, $this->default_lang, true);
					$language->load($this->plg_full_name, JPATH_ADMINISTRATOR, $this->default_lang, true);
				}

				$user_language_loaded = false;
				$lang_code = $this->default_lang;
			}
			else
			{
				$user_language_loaded = false;
				$lang_code = $this->default_lang;
			}

			$canView = false;

			// User has back-end access
			$canEdit = $user->authorise('core.edit', $this->context['full'] . '.' . $this->contentItem->id);
			$canLoginBackend = $user->authorise('core.login.admin');

			// This workaround is needed becasue $user->getAuthorisedViewLevels() fails on $user->id == -1
			$setAgain = false;

			if ($user->id == -1)
			{
				$setAgain = true;
				$user->id = 0;
			}

			if (empty($this->contentItem->access) || in_array($this->contentItem->access, $user->getAuthorisedViewLevels()))
			{
				$canView = true;
			}

			if ($setAgain)
			{
				$user->id = -1;
			}


			$notifyonlyifcanview = $this->rule->ausers_notifyonlyifcanview;

			// Just in case. Such users should be already removed before
			if ($notifyonlyifcanview == 1 && !$canView)
			{
				return false;
			}

			$this->_loadMailPlaceholders();
			$place_holders_subject = $this->place_holders_subject;
			$place_holders_body = $this->place_holders_body;

			$place_holders_subject['%SITENAME%'] = $this->sitename;
			$place_holders_subject['%SITELINK%'] = JURI::root();

			if ($this->rule->personalize)
			{
				$place_holders_subject['%TO_NAME%'] = $user->name;
				$place_holders_subject['%TO_USERNAME%'] = $user->username;
				$place_holders_subject['%TO_EMAIL%'] = $user->email;
			}


			if ($this->isNew)
			{
				$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_NEW_CONTENT_ITEM');

				if ($user->id == $this->contentItem->created_by)
				{
					if ($this->contentItem->created_by != $this->contentItem->modified_by)
					{
						$place_holders_subject['%ACTION%'] = $this->modifier->username . ' '
							. JText::_('PLG_SYSTEM_NOTIFICATIONARY_HAS_ADDED_A_CONTENT_ITEM_WITH_YOU_SET_AS_AUTHOR');
					}
					else
					{
						$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_YOU_HAVE_JUST_ADDED_A_CONTENT_ITEM');
					}
				}
				// Current user is not the article's author, but the article's author is set to anoter user
				elseif ($user->id == $this->contentItem->modified_by)
				{
					$place_holders_subject['%ACTION%'] = JText::sprintf(
																								'PLG_SYSTEM_NOTIFICATIONARY_YOU_HAVE_JUST_ADDED_A_CONTENT_ITEM_WITH_AUTHOR',
																								$this->author->username
																							);
				}
			}
			// Article not new
			else
			{
				if ($this->publish_state_change == 'publish')
				{
					// Current user have changed article state
					if ($user->id == $this->contentItem->modified_by)
					{
						$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_YOU_HAVE_JUST_PUBLISHED_A_CONTENT_ITEM');
					}
					// The article was not modifed by the user, but the user is the article's author
					elseif ($user->id == $this->contentItem->created_by && $user->id != $this->contentItem->modified_by)
					{
						$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_YOUR_CONTENT_ITEM_HAS_BEEN_PUBLISHED');
						$place_holders_body['%ACTION%'] = $this->modifier->username . ' ' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_HAS_PUBLISHED_YOUR_CONTENT_ITEM');
					}
					// User neither modifier nor author
					else
					{
						$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_A_CONTENT_ITEM_HAS_BEEN_PUBLISHED');
					}
				}
				elseif ($this->publish_state_change == 'unpublish')
				{
					// Current user have changed article state
					if ($user->id == $this->contentItem->modified_by)
					{
						$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_YOU_HAVE_JUST_UNPUBLISHED_A_CONTENT_ITEM');
					}
					// The article was not modifed by the user, but the user is the article's author
					elseif ($user->id == $this->contentItem->created_by && $user->id != $this->contentItem->modified_by)
					{
							$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_YOUR_CONTENT_ITEM_HAS_BEEN_UNPUBLISHED');
							$place_holders_body['%ACTION%'] = $this->modifier->username . ' ' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_HAS_UNPUBLISHED_YOUR_CONTENT_ITEM');
					}
					// User neither modifier nor author
					else
					{
						$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_A_CONTENT_ITEM_HAS_BEEN_UNPUBLISHED');
					}
				}
				else
				{
					if ($user->id == $this->contentItem->modified_by)
					{
						$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_YOU_HAVE_JUST_UPDATED_A_CONTENT_ITEM');
					}
					elseif ($user->id == $this->contentItem->created_by && $user->id != $this->contentItem->modified_by)
					{
							$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_YOUR_CONTENT_ITEM_HAS_BEEN_MODIFIED');
							$place_holders_body['%ACTION%'] = $this->modifier->username . ' ' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_HAS_MODIFIED_YOUR_CONTENT_ITEM');
					}
					else
					{
						$place_holders_subject['%ACTION%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_A_CONTENT_ITEM_HAS_BEEN_CHANGED');
					}
				}
			}

			switch ($this->contentItem->state)
			{
				case '1':
					$place_holders_subject['%STATUS%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_JPUBLISHED');
					break;
				case '0':
					$place_holders_subject['%STATUS%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_JUNPUBLISHED');
					break;
				case '2':
					$place_holders_subject['%STATUS%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_JARCHIVED');
					break;
				case '-2':
					$place_holders_subject['%STATUS%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_JTRASHED');
					break;
			}

			if ($this->rule->messagebodysource == 'hardcoded')
			{
				$place_holders_subject['%STATUS%'] = '<b>'
					. JText::_('PLG_SYSTEM_NOTIFICATIONARY_JSTATUS') . ':</b> ' . $place_holders_subject['%STATUS%'];
				$include = '';

				$isAuthor = false;
				$isModifier = false;

				if ($user->id == $this->contentItem->created_by)
				{
					$isAuthor = true;
					$include = 'author';
				}
				elseif ($user->id == $this->contentItem->modified_by)
				{
					$isModifier = true;
					$include = 'modifier';
				}

				$IncludeIntroText = $this->rule->{'ausers_' . $include . 'includeintrotext'};
				$IncludeFullText = $this->rule->{'ausers_' . $include . 'includefulltext'};
				$IncludeFrontendViewLink = $this->rule->{'ausers_' . $include . 'includefrontendviewlink'};
				$IncludeFrontendEditLink = $this->rule->{'ausers_' . $include . 'includefrontendeditlink'};
				$IncludeBackendEditLink = $this->rule->{'ausers_' . $include . 'includebackendeditlink'};
				/*
				$IncludeFrontendViewLink = $this->rule->ausers_includefrontendviewlink;
				$IncludeFrontendEditLink = $this->rule->ausers_includefrontendeditlink;
				$IncludeBackendEditLink = $this->rule->ausers_includebackendeditlink;
				*/

				if (!isset($this->contentItem->introtext) )
				{
					$IncludeIntroText = false;
				}

				if (!isset($this->contentItem->fulltext))
				{
					$IncludeFullText = false;
				}

				$IncludeArticleTitle = $this->rule->ausers_includearticletitle;
				$IncludeCategoryTree = $this->rule->ausers_includecategorytree;
				$IncludeAuthorName = $this->rule->ausers_includeauthorname;
				$IncludeModifierName = $this->rule->ausers_includemodifiername;
				$IncludeCreatedDate = $this->rule->ausers_includecreateddate;
				$IncludeModifiedDate = $this->rule->ausers_includemodifieddate;
				$IncludeContenttype = $this->rule->ausers_includecontenttype;

				$place_holders_subject['%TITLE%']  = $this->contentItem->title;
				$place_holders_subject['%MODIFIER%']  = "<b>" . JText::_('PLG_SYSTEM_NOTIFICATIONARY_MODIFIER') . '</b>: ' . $this->modifier->username;

				// $place_holders_subject['%CONTENT_TYPE%'] = $this->rule->type_title;

				$this->rule->manage_subscription_link = trim($this->rule->manage_subscription_link);

				if (!empty($this->rule->manage_subscription_link))
				{
					$link = str_replace(JURI::root(), '', $this->rule->manage_subscription_link);
					$link = JURI::root() . $link;

					if ($this->rule->emailformat == 'plaintext')
					{
						$place_holders_body['%MANAGE SUBSCRIPTION LINK%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_FIELD_MANAGE_SUBSCRIPTION')
							. ': ' . $link;
					}
					else
					{
						$place_holders_body['%MANAGE SUBSCRIPTION LINK%'] = '<a href="' . $link . '">'
							. JText::_('PLG_SYSTEM_NOTIFICATIONARY_FIELD_MANAGE_SUBSCRIPTION') . '</a>';
					}
				}

				// ---------------- body ---------------- //
				if ($IncludeArticleTitle)
				{
					$place_holders_body['%TITLE HARDCODED%']  = "<b>" . JText::_('PLG_SYSTEM_NOTIFICATIONARY_TITLE') . '</b>: ' . $this->contentItem->title;
				}

				if ($IncludeModifierName)
				{
					$place_holders_body['%MODIFIER HARDCODED%'] = $place_holders_subject['%MODIFIER%'];
				}

				if ($IncludeAuthorName)
				{
					$place_holders_body['%AUTHOR%']  = "<b>" . JText::_('PLG_SYSTEM_NOTIFICATIONARY_AUTHOR') . '</b>: ' . $this->author->username;
				}

				if ($IncludeCreatedDate && !empty($this->contentItem->created) )
				{
					$place_holders_body['%CREATED DATE%']  = "<b>" . JText::_('PLG_SYSTEM_NOTIFICATIONARY_CREATED') . '</b>: '
						. NotificationAryHelper::getCorrectDate($this->contentItem->created);
				}

				if ($IncludeModifiedDate)
				{
					if (is_null($this->contentItem->modified))
					{
						$place_holders_body['%MODIFIED DATE%']  = "<b>" . JText::_('PLG_SYSTEM_NOTIFICATIONARY_MODIFIED') . '</b>: ' . JText::_('JNO');
					}
					else
					{
						$place_holders_body['%MODIFIED DATE%']  = "<b>" . JText::_('PLG_SYSTEM_NOTIFICATIONARY_MODIFIED') . '</b>: '
							. NotificationAryHelper::getCorrectDate($this->contentItem->modified) . '<br/>';
					}
				}

				if ($IncludeCategoryTree)
				{
					$this->_buildCategoryTree();
					$place_holders_body['%CATEGORY PATH%'] = "<b>"
						. JText::_('PLG_SYSTEM_NOTIFICATIONARY_JCATEGORY') . '</b>: ' . implode(' > ', $this->categoryTree);
				}

				if ($IncludeContenttype)
				{
					$place_holders_body['%CONTENT_TYPE%'] = "<b>" . JText::_('PLG_SYSTEM_NOTIFICATIONARY_CONTENT_TYPE') . '</b>: ' . $this->rule->contenttype_title;
				}

				if ($IncludeFrontendViewLink && $this->contentItem->state == 1 && $canView)
				{
					if ($link = $this->_buildLink($zone = 'site', $task = 'view'))
					{
						$place_holders_body['%FRONT VIEW LINK%']  = "<b>" . JText::_('PLG_SYSTEM_NOTIFICATIONARY_VIEW_CONTENT_ITEM') . '</b>: <br/>' . PHP_EOL;
						$place_holders_body['%FRONT VIEW LINK%'] .= '<a href="' . $link . '">' . $link . '</a>';
					}
				}
				elseif ($IncludeFrontendViewLink && $this->contentItem->state == 1 && !$canView)
				{
					if ($link = $this->_buildLink($zone = 'site', $task = 'view'))
					{
						$place_holders_body['%FRONT VIEW LINK%']  = "<b>" . JText::_('PLG_SYSTEM_NOTIFICATIONARY_VIEW_CONTENT_ITEM') . '</b>: <br/>' . PHP_EOL;
						$place_holders_body['%FRONT VIEW LINK%'] .= JText::_('PLG_SYSTEM_NOTIFICATIONARY_JERROR_ALERTNOAUTHOR') . '<br/>' . PHP_EOL;
						$place_holders_body['%FRONT VIEW LINK%'] .= '<a href="' . $link . '">' . $link . '</a>';
					}
				}
				elseif ($this->contentItem->state != 1)
				{
					$place_holders_body['%FRONT VIEW LINK%'] = "<b>"
						. JText::_('PLG_SYSTEM_NOTIFICATIONARY_THIS_CONTENT_ITEM_MUST_BE_REVIEWED_AND_MAY_BE_PUBLISHED_BY_AN_ADMINISTRATOR_USER') . "</b>.";

					if ($link = $this->_buildLink($zone = 'site', $task = 'view'))
					{
						$place_holders_body['%FRONT VIEW LINK%'] .= '<br>' . PHP_EOL . $link;
					}
				}

				if ($isModifier || $isAuthor)
				{
					$place_holders_body['%CONTENT ID%'] = "<b>"
						. JText::_('PLG_SYSTEM_NOTIFICATIONARY_YOUR_CONTENT_ITEM_ID_FOR_FUTHER_REFERENCE_IS') . "</b> " . $this->contentItem->id;
				}

				// Add FE edit link
				if ($IncludeFrontendEditLink && $canEdit )
				{
					if ($link = $this->_buildLink($zone = 'site', $task = 'edit'))
					{
						$place_holders_body['%FRONT EDIT LINK%']  = "<b>"
							. JText::_('PLG_SYSTEM_NOTIFICATIONARY_IF_YOU_ARE_LOGGED_IN_TO_FRONTEND_USE_THIS_LINK_TO_EDIT_THE_CONTENT_ITEM')
							. '</b>' . '<br/>' . PHP_EOL;
						$place_holders_body['%FRONT EDIT LINK%']  .= '<a href="' . $link . '">' . $link . '</a>';
					}
				}

				// Add BE edit link
				if ($IncludeBackendEditLink && $canLoginBackend )
				{
					$place_holders_body['%BACKEND EDIT LINK%']  = "<b>"
						. JText::_('PLG_SYSTEM_NOTIFICATIONARY_IF_YOU_ARE_LOGGED_IN_TO_BACKEND_USE_THIS_LINK_TO_EDIT_THE_CONTENT_ITEM')
						. '</b>' . '<br/>' . PHP_EOL;

					$place_holders_body['%BACKEND EDIT LINK%'] .= '<a href="' . $this->_buildLink($zone = 'admin', $task = 'edit') . '">'
						. $this->_buildLink($zone = 'admin', $task = 'edit') . '</a>';
				}
			}
			else
			{
				$place_holders_subject['%TITLE%']  = $this->contentItem->title;
				$place_holders_subject['%MODIFIER%']  = $this->modifier->username;
				$place_holders_subject['%CONTENT_TYPE%'] = $this->rule->contenttype_title;

				// ---------------- body ---------------- //
				$place_holders_body['%AUTHOR%']  = $this->author->username;
				$place_holders_body['%CREATED DATE%']  = NotificationAryHelper::getCorrectDate($this->contentItem->created);

				if (!isset($this->contentItem->modified) || is_null($this->contentItem->modified))
				{
					$place_holders_body['%MODIFIED DATE%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_JNO');
				}
				else
				{
					$place_holders_body['%MODIFIED DATE%']  = NotificationAryHelper::getCorrectDate($this->contentItem->modified);
				}

				if (strpos($this->rule->messagebodycustom, '%CATEGORY PATH%') !== false)
				{
					$this->_buildCategoryTree();
					$place_holders_body['%CATEGORY PATH%'] = implode(' > ', $this->categoryTree);
				}

				$place_holders_body['%FRONT VIEW LINK%'] = '';

				if ($this->contentItem->state == 1 && $canView)
				{
					if ($link = $this->_buildLink($zone = 'site', $task = 'view'))
					{
						$place_holders_body['%FRONT VIEW LINK%'] .= $this->_buildLink($zone = 'site', $task = 'view');
					}
					else
					{
						$place_holders_body['%FRONT VIEW LINK%'] .= JText::_('PLG_SYSTEM_NOTIFICATIONARY_NO_FE_LINK');
					}
				}
				elseif ($this->contentItem->state == 1 && !$canView)
				{
					$place_holders_body['%FRONT VIEW LINK%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_JERROR_ALERTNOAUTHOR');
				}
				elseif ($this->contentItem->state != 1 && $canEdit)
				{
					if ($link = $this->_buildLink($zone = 'site', $task = 'view'))
					{
						$place_holders_body['%FRONT VIEW LINK%'] = "<b>"
							. JText::_('PLG_SYSTEM_NOTIFICATIONARY_THIS_CONTENT_ITEM_MUST_BE_REVIEWED_AND_MAY_BE_PUBLISHED_BY_AN_ADMINISTRATOR_USER') . "</b>.";

						$place_holders_body['%FRONT VIEW LINK%'] .= PHP_EOL . $link;
					}
					else
					{
						$place_holders_body['%FRONT VIEW LINK%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_NO_FE_LINK');
					}
				}
				elseif ($this->contentItem->state != 1)
				{
					if ($link = $this->_buildLink($zone = 'site', $task = 'view'))
					{
						$place_holders_body['%FRONT VIEW LINK%'] = "<b>"
							. JText::_('PLG_SYSTEM_NOTIFICATIONARY_THIS_CONTENT_ITEM_MUST_BE_REVIEWED_AND_MAY_BE_PUBLISHED_BY_AN_ADMINISTRATOR_USER') . "</b>.";
						$place_holders_body['%FRONT VIEW LINK%'] .= PHP_EOL . $link;
					}
					else
					{
						$place_holders_body['%FRONT VIEW LINK%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_NO_FE_LINK');
					}
				}

				if ($canEdit)
				{
					if ($link = $this->_buildLink($zone = 'site', $task = 'edit'))
					{
						$place_holders_body['%FRONT EDIT LINK%'] = $link;
					}
					else
					{
						$place_holders_body['%FRONT EDIT LINK%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_NO_FE_LINK');
					}
				}
				else
				{
					$place_holders_body['%FRONT EDIT LINK%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_JERROR_ALERTNOAUTHOR');
				}

				if ($canLoginBackend && $canEdit)
				{
					$place_holders_body['%BACKEND EDIT LINK%'] = $this->_buildLink($zone = 'admin', $task = 'edit');
				}
				else
				{
					$place_holders_body['%BACKEND EDIT LINK%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_JERROR_ALERTNOAUTHOR');
				}

				$place_holders_body['%CONTENT ID%']  = $this->contentItem->id;
			}

			// Strip plugin tags
			if ($this->rule->strip_plugin_tags)
			{
				if (!empty($this->contentItem->introtext))
				{
					$this->contentItem->introtext = NotificationAryHelper::stripPluginTags($this->contentItem->introtext);
				}

				if (!empty($this->contentItem->fulltext))
				{
					$this->contentItem->fulltext = NotificationAryHelper::stripPluginTags($this->contentItem->fulltext);
				}
			}

			if ($this->rule->make_image_path_absolute == 'absolute')
			{
				$domain = JURI::root();

				if (!empty($this->contentItem->introtext))
				{
					$this->contentItem->introtext = str_replace('href="mailto:', '##mygruz20161114125806', $this->contentItem->introtext);
					$this->contentItem->introtext = str_replace('href=\'mailto:', '##mygruz20161114125807', $this->contentItem->introtext);

					$this->contentItem->introtext = preg_replace("/(href|src)\=\"([^(http)])(\/)?/", "$1=\"$domain$2", $this->contentItem->introtext);

					$this->contentItem->introtext = str_replace('##mygruz20161114125806', 'href="mailto:', $this->contentItem->introtext);
					$this->contentItem->introtext = str_replace('##mygruz20161114125807', 'href=\'mailto:', $this->contentItem->introtext);
				}

				if (!empty($this->contentItem->fulltext))
				{
					$this->contentItem->fulltext = str_replace('href="mailto:', '##mygruz20161114125806', $this->contentItem->fulltext);
					$this->contentItem->fulltext = str_replace('href=\'mailto:', '##mygruz20161114125807', $this->contentItem->fulltext);

					$this->contentItem->fulltext = preg_replace("/(href|src)\=\"([^(http)])(\/)?/", "$1=\"$domain$2", $this->contentItem->fulltext);

					$this->contentItem->fulltext = str_replace('##mygruz20161114125806', 'href="mailto:', $this->contentItem->fulltext);
					$this->contentItem->fulltext = str_replace('##mygruz20161114125807', 'href=\'mailto:', $this->contentItem->fulltext);
				}
			}

			// *** prepare introtext and fulltext {

			// Instantiate a new instance of the class. Passing the string
			// variable automatically loads the HTML for you.
			if ($this->rule->emailformat == 'plaintext')
			{
				if (!class_exists('Html2Text') )
				{
					require_once dirname(__FILE__) . '/helpers/Html2Text.php';
				}
			}

			if (empty($this->rule->introtext))
			{
				if ($this->rule->emailformat == 'plaintext')
				{
					$h2t = new Html2Text\Html2Text($this->contentItem->introtext, array('show_img_link' => 'yes'));
					$h2t->width = 120;

					// Simply call the get_text() method for the class to convert
					// the HTML to the plain text. Store it into the variable.
					$this->rule->introtext = $h2t->get_text();
					unset ($h2t);
				}
				else
				{
					$this->rule->introtext = $this->contentItem->introtext;
				}
			}

			if (empty($this->rule->fulltext))
			{
				if ($this->rule->emailformat == 'plaintext')
				{
					// Instantiate a new instance of the class. Passing the string
					// variable automatically loads the HTML for you.
					$h2t = new Html2Text\Html2Text($this->contentItem->fulltext);
					$h2t->width = 120;

					// Simply call the get_text() method for the class to convert
					// the HTML to the plain text. Store it into the variable.
					$this->rule->fulltext = $h2t->get_text();
					unset ($h2t);
				}
				else
				{
					$this->rule->fulltext = $this->contentItem->fulltext;
				}
			}
			// *** prepare introtext and fulltext }

			if (empty($this->rule->fulltext))
			{
				$fulltext = '[' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_FIELD_NO_CONTENT') . ']';
			}
			else
			{
				$fulltext = $this->rule->fulltext;
			}

			if ($this->rule->messagebodysource == 'hardcoded')
			{
				if ($IncludeIntroText  )
				{
					$place_holders_body['%INTRO TEXT%'] = "\n\n<br/><br/>...........<b>"
							. JText::_('PLG_SYSTEM_NOTIFICATIONARY_INTRO_TEXT') . "</b>:...........\n<br/>" . $this->rule->introtext;
				}

				if ($IncludeFullText  )
				{
					$place_holders_body['%FULL TEXT%'] = "\n\n<br/>...........<b>"
							. JText::_('PLG_SYSTEM_NOTIFICATIONARY_FULL_TEXT') . "</b>:...........\n<br/>" . $fulltext;
				}

				$diffType = 'none';

				if ($this->rule->emailformat == 'plaintext')
				{
					$diffType = $this->rule->includediffinfo_text;
				}
				else
				{
					$diffType = $this->rule->includediffinfo_html;
				}

				if (!$this->onContentChangeStateFired && isset($this->rule->attachdiffinfo) && $this->noDiffFound)
				{
					$diffContents = PHP_EOL . '<br /><span style="color:red;">' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_NO_DIFF_FOUND') . '</span><br/>' . PHP_EOL;

					$place_holders_body['%DIFF ' . $diffType . '%'] = $diffContents;
				}
				else
				{
					if ($diffType != 'none' && isset($this->diffs[$diffType]))
					{
						$diffContents = PHP_EOL . '<hr><center>					.....Diff.......</center>' . PHP_EOL;
						$diffContents .= $this->diffs[$diffType];
						$place_holders_body['%DIFF ' . $diffType . '%'] = $diffContents;
					}
				}
			}
			else
			{
				// Custom mailbody
				$place_holders_body['%INTRO TEXT%'] = $this->rule->introtext;
				$place_holders_body['%FULL TEXT%'] = $fulltext;

				if (!$this->onContentChangeStateFired)
				{
					$noDiffEchoed = false;

					foreach ($this->availableDIFFTypes as $diffType)
					{
						if ($this->rule->emailformat == 'plaintext' && in_array($diffType, array('Html/SideBySide', 'Html/Inline')) )
						{
							continue;
						}

						if ($this->rule->emailformat == 'html' && !in_array($diffType, array('Html/SideBySide', 'Html/Inline')) )
						{
							continue;
						}

						if (strpos($this->rule->messagebodycustom, '%DIFF ' . $diffType . '%') !== false)
						{
							if ($this->noDiffFound)
							{
								if (!$noDiffEchoed)
								{
									$place_holders_body['%DIFF ' . $diffType . '%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_NO_DIFF_FOUND');
									$noDiffEchoed = true;
								}
								else
								{
									$place_holders_body['%DIFF ' . $diffType . '%'] = '';
								}
							}
							else
							{
								$place_holders_body['%DIFF ' . $diffType . '%'] = $this->diffs[$diffType];
							}
						}
					}
				}
				else
				{
					foreach ($this->availableDIFFTypes as $diffType)
					{
						$place_holders_body['%DIFF ' . $diffType . '%'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_NO_DIFF_FOUND');
						break;
					}
				}
			}

			// Just a symlink to be compatible for ReL, as he may already have used the %ACTION BODY% tag
			$place_holders_body['%ACTION BODY%'] = &$place_holders_body['%ACTION%'];

			// Just a symlink to be compatible for ReL, as he may already have used the %ACTION BODY% tag
			$place_holders_body['%STATE%'] = &$place_holders_body['%STATUS%'];

			// Switched to Content item
			$place_holders_body['%ARTICLE ID%'] = &$place_holders_body['%CONTENT ID%'];

			foreach ($place_holders_subject as $k => $v)
			{
					if (!isset($place_holders_body[$k]) || empty($place_holders_body[$k]))
					{
						$place_holders_body[$k] = $v;
					}
			}

			if ($this->rule->messagebodysource == 'hardcoded')
			{
				$place_messagesubject = '%ACTION% %SITENAME% (%SITELINK%): %TITLE%';
				$place_messagebody = array (
						'%TITLE HARDCODED%',
						'%CONTENT_TYPE%',
						'%CATEGORY PATH%',
						'%STATUS%',

						// Empty line
						'',
						'%AUTHOR%',
						'%MODIFIER HARDCODED%',
						'%CREATED DATE%',
						'%MODIFIED DATE%',
						'%CONTENT ID%',
						'',
						'%FRONT VIEW LINK%',
						'',
						'%FRONT EDIT LINK%',
						'',
						'%BACKEND EDIT LINK%',
						'',
						'%INTRO TEXT%',
						'',
						'%FULL TEXT%',
						'',
						'%MANAGE SUBSCRIPTION LINK%',
						'%DIFF Html/SideBySide%',
						'%DIFF Html/Inline%',
						'%DIFF Text/Unified%',
						'%DIFF Text/Context%'
					);

				// Clear empty fields from the template
				foreach ($place_messagebody as $k => $v)
				{
					if ($v === '')
					{
						continue;
					}

					if (empty($place_holders_body[$v]))
					{
						unset ($place_messagebody[$k]);
						continue;
					}
				}

				// Clear double empty lines {
				$prev_line_empty = false;
				$place_messagebody_temp = array();

				foreach ($place_messagebody as $line)
				{
					if (!empty($line))
					{
						$place_messagebody_temp[] = $line;
						$prev_line_empty = false;
						continue;
					}

					if (empty($line))
					{
						if ($prev_line_empty)
						{
							continue;
						}
						elseif (!$prev_line_empty)
						{
							$place_messagebody_temp[] = $line;
							$prev_line_empty = true;
						}
					}
				}

				$place_messagebody = $place_messagebody_temp;

				unset($place_messagebody_temp);

				// Clear double empty lines }

				$glue = '<br/>' . PHP_EOL;

				if ($this->rule->emailformat == 'plaintext')
				{
					$glue = PHP_EOL;
				}

				$place_messagebody = '%ACTION% %SITENAME% (%SITELINK%)' . $glue . implode($glue, $place_messagebody);
			}
			else
			{
				$place_messagesubject = JText::_($this->rule->messagesubjectcustom);
				$place_messagebody = $this->rule->messagebodycustom;
			}

			foreach ($place_holders_subject as $k => $v)
			{
				$place_messagesubject = str_replace($k, $v, $place_messagesubject);
			}

			$place_messagesubject = $this->_replaceRunPHPOnPlaceHolder($place_messagesubject);
			$place_messagesubject = $this->_replaceObjectPlaceHolders($place_messagesubject);

			$mail['subj'] = strip_tags($place_messagesubject);

			// Preserve placeholder
			$place_messagebody = str_replace('%UNSUBSCRIBE LINK%', '##mygruz20160414053402', $place_messagebody);

			foreach ($place_holders_body as $k => $v)
			{
				if ($this->rule->emailformat == 'plaintext')
				{
					$v = strip_tags($v);
				}

				$place_messagebody = str_replace($k, $v, $place_messagebody);
			}

			$place_messagebody = $this->_replaceRunPHPOnPlaceHolder($place_messagebody);
			$place_messagebody = $this->_replaceObjectPlaceHolders($place_messagebody);

			// Place back
			$place_messagebody = str_replace('##mygruz20160414053402', '%UNSUBSCRIBE LINK%', $place_messagebody);

			$mail['body'] = $place_messagebody;
			$this->rule->cachedMailBuilt[$hash] = $mail;
			$mail['email'] = $user->email;

			return $mail;
		}

		/**
		 * Run in-message PHP code, but do not run PHP code
		 *
		 * @param   string  $place_messagebody  Body text with php placeholders
		 *
		 * @return   string  Text with PHP placeholders evaled
		 */
		public function _replaceRunPHPOnPlaceHolder ($place_messagebody)
		{
			preg_match_all("/(<\s*\?php)(.*?)(\?\s*>)/msi", $place_messagebody, $php_codes);

			foreach ($php_codes[0] as $ck => $code_line)
			{
				$code_line = $this->_replaceObjectPlaceHolders($code_line);
				ob_start();
				eval('?>' . $code_line);
				$code_line = ob_get_contents();
				ob_end_clean();
				$place_messagebody = str_replace($php_codes[0][$ck], $code_line, $place_messagebody);
			}

			return $place_messagebody;
		}

		/**
		 * Replaces placeholders by the object variables
		 *
		 * @param   string  $text  Mail body text
		 *
		 * @return   type  Description
		 */
		protected function _replaceObjectPlaceHolders($text)
		{
			/* ##mygruz20160705172743 {
			It was:
			preg_match_all('/##([^#]*)#([^#]*)##([^#]*)/Ui',$text,$matches);
			preg_match_all('/##([^#]*)#([^#]*)##([^#]*)#{0,2}/Ui', $text, $matches);
			It became: */
			preg_match_all('/##([^ \n]*)##/i', $text, $matches);
			/* ##mygruz20160705172743 } */

			if (!empty($matches[1]))
			{
				foreach ($matches[1] as $k => $v)
				{
					$path = [];
					$tmp = explode('##', $v);

					if (!empty($tmp[1]))
					{
						$path[3] = $tmp[1];
					}

					$tmp = explode('#', $tmp[0]);

					$path[1] = $tmp[0];
					$path[2] = $tmp[1];

					switch ($path[1])
					{
						case 'Content':
							if (empty($path[3]))
							{
								if (isset($this->contentItem->{$path[2]}))
								{
									$value = $this->contentItem->{$path[2]};

									if (is_array($value))
									{
										$value = implode(',', $value);
									}
								}
							}
							else
							{
								if (is_array($this->contentItem->{$path[2]}) && isset($this->contentItem->{$path[2]}[$path[3]]))
								{
									$value = $this->contentItem->{$path[2]}[$path[3]];

									if (is_array($value))
									{
										$value = implode(',', $value);
									}
								}
							}
							break;
						case 'User':
							$user = JFactory::getUser();

							if (isset($user->{$path[2]}))
							{
								$value = $user->{$path[2]};

								if (is_array($value))
								{
									$value = implode(',', $user->{$path[2]});
								}
							}
							break;
					}

					$text = str_replace($matches[0][$k], (string) $value, $text);
				}
			}

			return $text;
		}

		/**
		 * Builds content item category tree
		 *
		 * @return   void
		 */
		protected function _buildCategoryTree ()
		{
			if (!empty($this->categoryTree))
			{
				return;
			}

			$this->categoryTree = array();

			// ~ $category_table = JTable::getInstance( 'category');
			// ~ $category_table->load($this->contentItem->catid);

			// Need to pass the $options array with access false to get categroies with all access levels
			$options = array ();
			$options['access'] = false;

			$catid = (is_array($this->contentItem->catid)) ? $this->contentItem->catid[0] : $this->contentItem->catid;

			switch ($this->context['extension'])
			{
				case 'k2':
				case 'zoo':
					// ~ $context = 'com_'.$this->context['extension'].'.category';

					$context = $this->context['full'];
					$contentItem = $this->_getContentItemTable($context, $category = true);
					$contentItem->load($catid);
					array_unshift($this->categoryTree, $contentItem->name);

					// $this->categoryTree[] = $contentItem->name;
					while ($contentItem->parent != 0)
					{
						$contentItem->load($contentItem->parent);
						array_unshift($this->categoryTree, $contentItem->name);
					}

					return;
					break;
				case 'jdownloads':
					$cat = JTable::getInstance('category', 'jdownloadsTable');
					$cat->load($catid);
					$path = $cat->getParentCategoryPath($catid);
					$this->categoryTree = explode('/', $path);

					return;
					break;
				default :
					break;
			}

			if (isset($this->contentItem->extension))
			{
				$scope = explode('_', $this->contentItem->extension);
				$cat = JCategories::getInstance($scope[1], $options);
				$cat_id = $this->contentItem->id;
			}
			else
			{
				$cat = JCategories::getInstance($this->context['extension'], $options);

				// ~ JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_categories'.DS.'tables');
				// ~ $cat = JCategories::getInstance('users',$options);

				$catid = (is_array($this->contentItem->catid)) ? $this->contentItem->catid[0] : $this->contentItem->catid;
				$cat_id = $catid;
			}

			$this->categoryTree = array();

			// If current extension which item is saved, doesn't use Joomla native category system
			if (!$cat)
			{
				return;
			}

			if (method_exists($cat, 'get'))
			{
				$cat = $cat->get($cat_id);
			}

			if (method_exists($cat, 'hasParent'))
			{
				while ($cat->hasParent())
				{
					array_unshift($this->categoryTree, $cat->title);
					$cat = $cat->getParent();
				}

				if ($cat->title !== 'ROOT')
				{
					array_unshift($this->categoryTree, $cat->title);
				}
			}
		}

		/**
		 * Save rule from hash
		 *
		 * @return   void
		 */
		private function _updateRulesIfHashSaved ()
		{
$debug = true;
$debug = false;

			// Get extension table class
			$extensionTable = JTable::getInstance('extension');

			$pluginId = $extensionTable->find(array('element' => $this->plg_name, 'type' => 'plugin'));
			$extensionTable->load($pluginId);

			$group = $this->params->get('{notificationgroup');

if ($debug)
{
echo '<pre style="float:right;width:25%;margin:0;background:#efffef;position: absolute; top:0;right: 0%"> Line: '
			. __LINE__ . '  BEFORE UPDATES' . PHP_EOL;
var_dump($group);
echo PHP_EOL . '</pre>' . PHP_EOL;
}

			$json_templates = $group->use_json_template;

if ($debug)
{
echo '<pre style="float:right;width:25%;margin:0;background:#efefff;position: absolute; top:0;right: 75%"> Line: ' . __LINE__ . ' ' . PHP_EOL;
var_dump($json_templates);
echo PHP_EOL . '</pre>' . PHP_EOL;
}

			$rules_to_update = array();

			foreach ($json_templates as $k => $v)
			{
				if ($v == 'variablefield::{notificationgroup' )
				{
					continue;
				}

				if (empty($v))
				{
					continue;
				}

				if ($decoded = json_decode(base64_decode($v)))
				{
					$rules_to_update[$k] = $decoded;
				}
				else
				{
					$hash_srip = substr($v, 0, 20) . ' ......... ' . substr($v, -20);

					JFactory::getApplication()->enqueueMessage(
						$this->plg_name . ": "
						. JText::_('PLG_SYSTEM_NOTIFICATIONARY_COULD_NOT_APPLY_CONFIGURATION_HASH')
						. '<i>' . $hash_srip . '</i>', 'error');
				}

				$json_templates[$k] = null;
			}

			$group->use_json_template = $json_templates;

			if (empty($rules_to_update))
			{
				return;
			}

if ($debug)
{
echo '<pre style="float:right;width:25%;margin:0;background:#ffefef;position: absolute; top:0;right: 50%"> Line: ' . __LINE__ . ' HASH ' . PHP_EOL;
var_dump($rules_to_update);
echo PHP_EOL . '</pre>' . PHP_EOL;
}

			foreach ($rules_to_update as $rule_index => $rules)
			{
				foreach ($rules as $key => $array)
				{
					if ($key == '__ruleUniqID')
					{
						continue;
					}
// ~ var_dump($array);
					if (!is_array($array))
					{
// ~ var_dump($array);
						$array = (array) $array;

						$tmp_array = array();

						foreach ($array as $k => $v)
						{
							$tmp_array[] = $v;
						}

						$array = $tmp_array;
						unset($tmp_array);

// ~ var_dump($array);
// ~ exit;
					}

					if (is_string($array[0]))
					{
						$group->{$key}[$rule_index] = $array[0];
					}
					else
					{
						$tmp_array = array();

						$current_group_index = 0;

						foreach ($group->{$key} as $ke => $va)
						{
							$tmp_array[$current_group_index][] = $va;

							if ($va[0] == 'variablefield::{notificationgroup')
							{
								$current_group_index = $current_group_index + 2;
							}
						}

						$tmp_array[$rule_index] = $array;

if ($debug)
{
echo '<pre> Line: ' . __LINE__ . ' tmp_array ' . PHP_EOL;
print_r($tmp_array);
echo PHP_EOL . '</pre><hr>' . PHP_EOL;
}

						$group->{$key} = array();

						foreach ($tmp_array as $kd => $vd)
						{
							foreach ($vd as $k => $v)
							{
								$group->{$key}[] = $v;
							}
						}
					}
				}
			}

if ($debug)
{
echo '<pre style="float:right;width:25%;margin:0;background:#efefff;position: absolute; top:0;right: 25%"> Line: '
			. __LINE__ . ' AFTER UPDATES ' . PHP_EOL;
var_dump($group);
echo PHP_EOL . '</pre>' . PHP_EOL;
exit;
}

			// Set to parameters
			$this->params->set('{notificationgroup', $group);

			// Bind to extension table
			$extensionTable->bind(array('params' => $this->params->toString()));

			// Check and store
			if (!$extensionTable->check())
			{
				$this->setError($extensionTable->getError());

				// ~ return false;
			}

			if (!$extensionTable->store())
			{
				$this->setError($extensionTable->getError());

				// ~ return false;
			}

			$app	= JFactory::getApplication();
			$uri = JFactory::getURI();
			$pageURL = $uri->toString();

			$app->redirect($pageURL);

			return;
		}

		/**
		 * Handles AJAX mail sending.
		 *
		 * Prepares data to be passed to ajax
		 * and adds JS scripts and JS snippets to the document
		 *
		 * @return  void
		 */
		public function onBeforeRender()
		{
			$app = JFactory::getApplication();

			$jinput = $app->input;

			if ($jinput->get('option', null) == 'com_dump')
			{
				return;
			}

			// Block JSON response, like there was an incompatibility with RockSprocket
			$format = $jinput->get('format', 'html');

			$session = JFactory::getSession();

			// Is set in onAfterContentSave
			$ajaxHash = $session->get('AjaxHash', null, $this->plg_name);

			if (!empty($ajaxHash))
			{
				$paramsToBePassed = array(
					'ajaxHash' => $ajaxHash,
					'verbose' => $this->paramGet('verbose'),
					'showNumberOfUsers' => $this->paramGet('successmessagenumberofusers'),
					'debug' => $this->paramGet('debug'),
				);

				$paramsToBePassed = base64_encode(serialize($paramsToBePassed));

				// Build remote link
				$url_ajax_plugin = JRoute::_(
							JURI::base()
							// It's a must
							. '?option=com_ajax&format=raw'
							. '&group=' . $this->plg_type
							. '&plugin=notificationAryRun'
							. '&' . JSession::getFormToken() . '=1'
							. '&uniq=' . uniqid()
							. '&serialize=' . $paramsToBePassed
					);

				if ($this->paramGet('debug'))
				{
					$url_ajax_plugin .= '&debug=1';
					$app->enqueueMessage('<small>' . 'Ajax URL: ' . $url_ajax_plugin . '</small>', 'notice');
				}

				$doc = JFactory::getDocument();

				$doc->addScriptOptions($this->plg_name, array('ajax_place' => $this->plg_full_name));
				$doc->addScriptOptions($this->plg_name, array('ajax_url' => $url_ajax_plugin));

				//$doc->addScriptOptions($this->plg_name, ['messages' => array('error' => JText::_('Ajax error')) ]);

				if ($this->paramGet('ajax_allow_to_cancel') && $this->paramGet('ajax_delay') > 0)
				{
					$doc->addScriptOptions($this->plg_name, array('start_delay' => ($this->paramGet('ajax_delay') + 1)));
					JText::script('PLG_SYSTEM_NOTIFICATIONARY_AJAX_TIME_TO_START');
				 // ~ $doc->addScriptOptions($this->plg_name, array('messages' => array('delay_text' => JText::_('PLG_SYSTEM_NOTIFICATIONARY_AJAX_TIME_TO_START')) ));
				}

				$SuccessMessage = '';

				if ($this->paramGet('showsuccessmessage'))
				{
					$SuccessMessage .= JText::_($this->paramGet('successmessage'));
				}

				if ($this->paramGet('successmessagenumberofusers'))
				{
					$SuccessMessage .= ' ' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_USERS_NOTIFIED');
				}

				$doc->addScriptOptions($this->plg_name,['messages' => array('sent' => $SuccessMessage) ] );

				self::addJSorCSS('ajax.js', $this->plg_full_name);

				self::addJSorCSS('styles.css', $this->plg_full_name);

				if ($this->paramGet('debug'))
				{
					$doc->addScriptOptions($this->plg_name, array('debug' => true));
				}
			}
		}

		/**
		 * Unsubscribes a user passed via an unsubscribe link
		 *
		 * @param   string  $uniq       Uniqid passed via URL
		 * @param   string  $serialize  Hash contanina informatio
		 *
		 * @return   string Raw output message
		 */
		public function _unsubscribe($uniq, $serialize)
		{
			$user = $serialize['unsubscribe'];
			$md5 = $serialize['md5'];

			$userObject = NotificationAryHelper::getUserByEmail($user);

			// $user->load(array('email'=>$email));
			if ($userObject->id > 0 )
			{
				if ($md5 != md5($userObject->id . $uniq))
				{
					echo $msg = '<b style="color:red">' . JText::sprintf('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBE_FAILED', $user) . '</b>';

					return;
				}
			}

			$excludeUsers = NotificationAryHelper::getRuleOption('ausers_excludeusers', $uniq);
			$excludeUsers = explode(PHP_EOL, $excludeUsers);
			$excludeUsers = array_map('trim', $excludeUsers);
			$msg = '';

			if (!in_array($user, $excludeUsers))
			{
				$excludeUsers[] = $user;
				$excludeUsers = array_filter($excludeUsers);
				$excludeUsers = implode(PHP_EOL, $excludeUsers);

				if (!NotificationAryHelper::updateRuleOption('ausers_excludeusers', $excludeUsers, $uniq))
				{
					$msg = '<b style="color:red">' . JText::sprintf('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBE_FAILED', $user) . '</b>';
				}
				else
				{
					$msg = '<b style="color:green">' . JText::sprintf('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBED', $user) . '</b>';
				}
			}
			else
			{
					$msg = '<b style="color:blue">' . JText::sprintf('PLG_SYSTEM_NOTIFICATIONARY_NOT_SUBSCRIBED', $user) . '</b>';
			}

			// Mark the rule as unsubscribed in the profile as well
			$db = JFactory::getDbo();
				$query = $db->getQuery(true)
					->delete($db->quoteName('#__user_profiles'))
					->where($db->quoteName('user_id') . ' = ' . (int) $userObject->id)
					->where($db->quoteName('profile_key') . ' LIKE ' . $db->quote('notificationary.' . $uniq . '.all'));
				$db->setQuery($query);
				$db->execute();

			$tuples = array();
			$order = 1;

			$tuples[] = '('
				. $userObject->id . ', '
				. $db->quote('notificationary.' . $uniq . '.all') . ', '
				. $db->quote('unsubscribed') . ', ' . ($order++)
			. ')';

			$db->setQuery('INSERT INTO #__user_profiles VALUES ' . implode(', ', $tuples));
			$db->execute();

			echo $msg;
		}

		/**
		 * Ajax entry point to update subscription
		 *
		 * @return   string  Json-formatted string
		 */
		public function onAjaxNotificationArySubscribeUpdate()
		{
			$resposne = array('success' => false);

			$jinput = JFactory::getApplication()->input;
			$token = JSession::getFormToken();

			if (!JSession::checkToken())
			{
				$resposne['message'] = JText::_('JINVALID_TOKEN');

				return json_encode($resposne);
			}

			$user = JFactory::getUser();

			$app = JFactory::getApplication();

			if ($user->guest)
			{
				$resposne['message'] = JText::_('JERROR_ALERTNOAUTHOR');

				return json_encode($resposne);
			}

			$userid = $jinput->post->get('userid');

			if ($userid != $user->id)
			{
				if (!$user->authorise('core.manage', 'com_users'))
				{
					$resposne['message'] = JText::_('JERROR_ALERTNOAUTHOR');

					return json_encode($resposne);
				}
			}

			$user = JFactory::getUser($userid);
			$ruleUniqID = $jinput->post->get('ruleUniqID');

			$categoriesToBeStored = $jinput->post->get('categoriesToSubscribe_' . $ruleUniqID, array(), 'array');
			$subscribeToAll = $jinput->post->get('subscribetoall_' . $ruleUniqID, 'selected');

			$excludeUsers = NotificationAryHelper::getRuleOption('ausers_excludeusers', $ruleUniqID);
			$excludeUsers = explode(PHP_EOL, $excludeUsers);
			$excludeUsers = array_map('trim', $excludeUsers);

			if (($key = array_search($user->email, $excludeUsers)) !== false)
			{
				unset($excludeUsers[$key]);
			}

			$excludeUsers = implode(PHP_EOL, $excludeUsers);

			if (!NotificationAryHelper::updateRuleOption('ausers_excludeusers', $excludeUsers, $ruleUniqID))
			{
				$resposne['message'] = JText::_('Could not remove the user from excluded users');
			}

			try
			{
				$db = JFactory::getDbo();
				$query = $db->getQuery(true)
					->delete($db->quoteName('#__user_profiles'))
					->where($db->quoteName('user_id') . ' = ' . (int) $user->id)
					->where($db->quoteName('profile_key') . ' LIKE ' . $db->quote('notificationary.' . $ruleUniqID . '.%'));
				$db->setQuery($query);
				$db->execute();

				$tuples = array();
				$order = 1;

				if ($subscribeToAll == 'all')
				{
					$tuples[] = '('
						. $user->id . ', '
						. $db->quote('notificationary.' . $ruleUniqID . '.all') . ', '
						. $db->quote('subscribed') . ', ' . ($order++)
					. ')';
				}

				if (!empty($categoriesToBeStored))
				{
					foreach ($categoriesToBeStored as $k => $v)
					{
						$tuples[] = '(' . $user->id . ', ' . $db->quote('notificationary.' . $ruleUniqID . '.' . $v) . ', ' . $db->quote($v) . ', ' . ($order++) . ')';
					}
				}

				if ($subscribeToAll == 'none'
					|| empty($categoriesToBeStored) && $subscribeToAll != 'all')
				{
					$tuples[] = '('
						. $user->id . ', '
						. $db->quote('notificationary.' . $ruleUniqID . '.all') . ', '
						. $db->quote('unsubscribed') . ', ' . ($order++)
					. ')';
				}

				$db->setQuery('INSERT INTO #__user_profiles VALUES ' . implode(', ', $tuples));
				$db->execute();

				$resposne['success'] = true;
				$resposne['message'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIPTION_UPDATED');
			}
			catch (RuntimeException $e)
			{
				$this->_subject->setError($e->getMessage());

				$resposne['success'] = false;
				$resposne['message'] = JText::_('PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIPTION_UPDATE_FAILED');
			}

			return json_encode($resposne);
		}

		/**
		 * Entry point for Ajax data passed via AJAX plugin
		 *
		 * Gruz uses this function as a default one in this case.
		 * The same is in MenuAry
		 *
		 * @return   void
		 */
		public function onAjaxNotificationAryRun()
		{
			$jinput = JFactory::getApplication()->input;

			// Uniqid passed via URL
			$uniq = $jinput->get('uniq', null);

			// Hash containing information
			$serialize = $jinput->get('serialize', null, 'string');

			$session = JFactory::getSession();

			$serialize = unserialize(base64_decode($serialize));

			if (isset($serialize['unsubscribe']))
			{
				$this->_unsubscribe($uniq, $serialize);

				return;
			}

			$hash = $serialize['ajaxHash'];

			$counter = $session->get('AjaxHashCounter' . $hash, -1, $this->plg_name);

			$files = JFolder::files(JFactory::getApplication()->getCfg('tmp_path'), $this->plg_name . '_' . $hash . '_*', false, true);

			if (empty($files))
			{
				$this->_cleanAttachments();
				$session->clear('AjaxHashCounter' . $hash, $this->plg_name);

				// ~ $counter = $counter-1;
				if ($serialize['showNumberOfUsers'])
				{
					$numberSentTotal = $session->get('AjaxHashCounterTotal' . $hash, -1, $this->plg_name);
					$numberSentFailed = $session->get('AjaxHashCounterFailed' . $hash, 0, $this->plg_name);
					$numberSent = $numberSentTotal - $numberSentFailed;

					$return = array ('message' => $numberSent, 'finished' => true);
				}
				else
				{
					$return = array ('message' => '', 'finished' => true);
				}

				return json_encode($return);
			}

			$messages = array();

			if ($counter == -1)
			{
				if ($serialize['verbose'])
				{
					$messages[] = JText::_('JAll') . ': ' . count($files) . '<br/>';
				}

				$counter = 0;
				$session->set('AjaxHashCounterTotal' . $hash, count($files), $this->plg_name);
			}

			// Number or mails sent per iteration
			for ($i = 0; $i < $this->paramGet('mails_per_iteration'); $i++)
			{
				// ~ sleep(2);
				if (!isset($files[$i]) || !file_exists($files[$i]))
				{
					break;
				}

				$counter++;
				$file = $files[$i];

				if (!class_exists('fakeMailerClass') )
				{
					require_once dirname(__FILE__) . '/helpers/fakeMailerClass.php';
				}

				$mailer_temp = unserialize(base64_decode(file_get_contents($file)));

				$mailer = JFactory::getMailer();

				foreach ($mailer_temp as $k => $v)
				{
					if ($k == 'addRecipient' || $k == 'addReplyTo')
					{
						foreach ($v as $recepients)
						{
							$mailer->$k($recepients[0], $recepients[1]);
						}
					}
					elseif ($k == 'Encoding')
					{
						$mailer->$k = $v;
					}
					else
					{
						$mailer->$k($v);
					}
				}

				// $return = '';

				if ($serialize['verbose'])
				{
					$toName = $mailer->getToAddresses();
					$toName = $toName[0][1] . ' &lt;' . NotificationAryHelper::obfuscate_email($toName[0][0]) . '&gt; ';
					$messages[] = $counter . ' ';
				}

				$session->set('AjaxHashCounter' . $hash, $counter, $this->plg_name);

				if (!$serialize['debug'])
				{
					$send = $mailer->Send();
				}
				else
				{
					$send = 'debug';
				}

				if ( $send === 'debug' )
				{
					if ($serialize['verbose'])
					{
						$messages[] = $toName . ' SEND IMITATION OK';
					}
				}
				elseif ( $send !== true )
				{
					if ($serialize['verbose'])
					{
						$messages[] = $toName . ' ..... <i class="icon-remove"  style="color:red"></i>';
						$numberSentFailed = $session->get('AjaxHashCounterFailed' . $hash, 0, $this->plg_name);
						$numberSentFailed++;
						$session->set('AjaxHashCounterFailed' . $hash, $numberSentFailed, $this->plg_name);
					}

					$messages[] = '<br/>Error sending email: ' . $send->__toString();
				}
				else
				{
					if ($serialize['verbose'])
					{
						$messages[] = $toName . ' <i class="icon-checkmark"></i>';
					}
				}

				JFile::delete($file);
				unset($mailer);

				if ($serialize['verbose'])
				{
					$messages[] = '<br/>';
				}
			}

			$return = array ('message' => implode(PHP_EOL, $messages), 'finished' => false);

			return json_encode($return);
		}

		/**
		 * Adds the notification switch at a content item view and cleans attachments
		 *
		 * @return void
		 */
		public function onAfterRender()
		{
			$jinput = JFactory::getApplication()->input;

			if ($jinput->get('option', null) == 'com_dump')
			{
				return;
			}

			if ($jinput->get('option', null) == 'com_ajax')
			{
				return;
			}

			NotificationAryHelper::addUserlistBadges();

			$app = JFactory::getApplication();

			// Block JSON response, like there was an incompatibility with RockSprocket
			$format = $jinput->get('format', 'html');

			// Add NA menu item to Joomla backend
			if ($app->isAdmin() && $this->paramGet('add_menu_item') && $format == 'html')
			{
				$body = $app->getBody();

				// Get extension table class
				$extensionTable = JTable::getInstance('extension');

				$pluginId = $extensionTable->find(array('element' => $this->plg_name, 'type' => 'plugin'));

				$language = JFactory::getLanguage();

				// Have to load curren logged in language to show the proper menu item language, not the default backend language
				$language->load($this->plg_full_name, $this->plg_path, $language->get('tag'), true);

				$menu = '<li><a class="menu-'
					. $this->plg_name . ' " href="index.php?option=com_plugins&task=plugin.edit&extension_id=' . $pluginId . '">'
					. JText::_($this->plg_full_name . '_MENU')
					. ' <i class="icon-mail-2"></i></a></li>';

				$js = '
				<script>
					jQuery(document).ready(function($){
						var $menu = $("#menu > li:nth-child(5) > ul ");
						if ($menu.length)
						{
							$menu.append(\'' . $menu . '\')
						}
					});
				</script>';

				$body = explode('</body>', $body, 2);

				// The second check of a non-normal Joomla page
				// (e.g. JSON format has no body tag). Needed because of problems with RockSprocket
				if (count($body) == 2 )
				{
					$body = $body[0] . $js . '</body>' . $body[1];
					$app->setBody($body);
				}
			}

			// Output Ajax placeholder if needed {
			$session = JFactory::getSession();

			// Is set in onAfterContentSave
			$ajaxHash = $session->get('AjaxHash', null, $this->plg_name);

			$session->clear('AjaxHash', $this->plg_name);

			if (!empty($ajaxHash))
			{
				$place_debug = '';
				$user = JFactory::getUser();

				// Since the _checkAllowed checks the global settings, there is no $this->rule passed and used there
				if ($this->paramGet('ajax_allow_to_cancel') && $this->_checkAllowed($user, $paramName = 'allowuser', $prefix = 'ajax'))
				{
					$place_debug .= '<button type="button" id="' . $this->plg_full_name . '_close">X</button>';
				}

				if ($this->paramGet('debug'))
				{
					// ~ $place_debug .= '<div style="position:fixed">';
					$place_debug .= '<a id="clear" class="btn btn-error">Clear</a>';
					$place_debug .= '<a id="continue" class="btn btn-warning">Continue</a>';

					// ~ $place_debug .= '</div>';
				}
				else
				{
					$place_debug .= '<small>';

					if ($this->paramGet('ajax_allow_to_cancel') && $this->_checkAllowed($user, $paramName = 'allowuser', $prefix = 'ajax'))
					{
						$place_debug .= JText::_('PLG_SYSTEM_NOTIFICATIONARY_AJAX_TIME_TO_CANCEL');
						$place_debug .= '. ';
					}

					$place_debug .= JText::_('PLG_SYSTEM_NOTIFICATIONARY_AJAX_SENDING_MESSAGES') . '</small>';
				}

				$ajax_place_holder = '<div class="nasplace" >' . $place_debug . '<div class="nasplaceitself" id="' . $this->plg_full_name . '" ></div>';

				$body = $app->getBody();
				$body = str_replace('</body>', $ajax_place_holder . '</body>', $body);
				$body = $app->setBody($body);
			}

			// K2 doesn't run onContentPrepareForm. So we need to imitate it here.
			$option = $jinput->get('option', null);
			$view = $jinput->get('view', null);

			if ($option == 'com_k2' && $view == 'item')
			{
				// Prepare to imitate onContentPrepareForm {
				$this->_prepareParams();
				$context = 'com_k2.item';
				$this->allowed_contexts[] = $context;
				$this->_setContext($context);

				$this->shouldShowSwitchCheckFlag = false;
				$contentItem = $this->_getContentItemTable($context);
				$contentItem->load($jinput->get('cid', 0));

				jimport('joomla.form.form');
				$form = JForm::getInstance('itemForm', JPATH_ADMINISTRATOR . '/components/com_k2/models/item.xml');
				$values = array('params' => json_decode($contentItem->params));
				$form->bind($values);

				// Prepare to imitate onContentPrepareForm }

				$this->onContentPrepareForm($form, $contentItem);
				$rules = $this->_leaveOnlyRulesForCurrentItem($context, $contentItem, 'showSwitch');

				if (empty($rules))
				{
					return;
				}

				$this->shouldShowSwitchCheckFlag = true;

				// Is set for onAfterContentSave as onContentPrepareForm is not run, but this method onAfterRender runs after onContentAfterSave.
				$session->set('shouldShowSwitchCheckFlagK2Special', true, $this->plg_name);

				// If the NS should be shown but cannot be shown due to HTML layout problems, then we need to know default value
				$rule = array_pop($rules);

				$session->set('shouldShowSwitchCheckFlagK2SpecialDefaultValue', (bool) $rule->notificationswitchdefault, $this->plg_name);
			}

			// Can be set in onContentPrepareForm or onContentAfterSave
			if (empty($this->context))
			{
				return;
			}

			if (!$this->_isContentEditPage($this->context['full']) )
			{
				return;
			}

			if (!NotificationAryHelper::isFirstRun('onAfterRender'))
			{
				return;
			}

			if (!$this->shouldShowSwitchCheckFlag)
			{
				return;
			}

			$body = $app->getBody();
			$app = JFactory::getApplication();
			$checkedyes = $checkedno = 'checked="checked"';
			$selectedyes = $selectedno = 'selected="selected"';
			$active_no = $active_yes = '';

			if ($this->runnotificationary == 1)
			{
				$checkedno = '';
				$selectedno = '';

				// $active_yes='active btn-success';
			}
			else
			{
				$checkedyes = '';
				$selectedyes = '';

				// $active_no=' active btn-danger';
			}

			$CustomReplacement = $session->get('CustomReplacement', null, $this->plg_name);

			$replacement_label = '
					<label title="" data-original-title="<strong>' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_NOTIFY') . '</strong><br />'
					. JText::_('PLG_SYSTEM_NOTIFICATIONARY_NOTIFY_DESC')
					. '" class="hasTip hasTooltip required" for="jform_runnotificationary" id="jform_attribs_runnotificationary-lbl">'
					. JText::_('PLG_SYSTEM_NOTIFICATIONARY_NOTIFY') . '</label>';

			if (!empty($CustomReplacement) && $CustomReplacement['context'] == $this->context['full'])
			{
				$possible_tag_ids = $CustomReplacement['possible_tag_ids'];
				$replacement_fieldset = $CustomReplacement['replacement_fieldset'];

				$replace = [
					'{{$this->attribsField}}' => $this->attribsField,
					'{{$checkedyes}}' => $checkedyes,
					'{{$active_yes}}' => $active_yes,
					'{{$checkedno}}' => $checkedno,
					'{{$active_no}}' => $active_no,

				];

				$search = array_keys($replace);
				$replacement_fieldset = str_replace($search, $replace, $replacement_fieldset);
			}
			else
			{
				$CustomReplacement = ['option' => false];

				if (!$app->isAdmin() && $this->paramGet('replacement_type') === 'simple')
				{
					$replacement_fieldset = '
					<select id="jform_' . $this->attribsField . '_runnotificationary" name="jform[' . $this->attribsField . '][runnotificationary]" class="inputbox">
					<option value="1" ' . $selectedyes . '>' . JText::_('JYES') . '</option>
					<option value="0" ' . $selectedno . '>' . JText::_('JNO') . '</option>
					</select>
					';
				}
				else
				{
					$replacement_fieldset = '
						<fieldset id="jform_' . $this->attribsField . '_runnotificationary" class="radio btn-group btn-group-yesno nswitch" >
							<input type="radio" ' . $checkedyes . ' value="1" name="jform[' . $this->attribsField . '][runnotificationary]" id="jform_'
								. $this->attribsField . '_runnotificationary1">
							<label for="jform_' . $this->attribsField . '_runnotificationary1" class="btn ' . $active_yes . '">' . JText::_('JYES') . '</label>
							<input type="radio" ' . $checkedno . ' value="0" name="jform[' . $this->attribsField . '][runnotificationary]" id="jform_'
								. $this->attribsField . '_runnotificationary0">
							<label for="jform_' . $this->attribsField . '_runnotificationary0" class="btn' . $active_no . '">' . JText::_('JNO') . '</label>
						</fieldset>
					';
				}

				// $oldFieldsFormat = NotificationAryHelper::getHTMLElementById($body,'adminformlist','ul','class');

				// NOTE! NotificationAryHelper::getHTMLElementById doesn't work with non-double tags like <input ... /> .
				$possible_tag_ids = array (

					// ~ array('textarea', 'jform_articletext'),
					array('select', 'jform_catid'),
					array('select', 'jform_parent_id'),
					array('select', 'jform_state'),
					array('select', 'jform_published'),
					array('select', 'jform_access'),
					array('select', 'jform_language'),

					// Jdownloads
					array('select', 'jform_file_language'),

					// Jdownloads
					array('div', 'k2ExtraFieldsValidationResults'),

					// Old K2
					array('select', 'catid'),
				);


				// JEvents compatibility\
				if ($this->context['full'] == 'jevents.edit.icalevent')
				{
					$possible_tag_ids = array (
						array('select', 'access'),
						array('select', 'catid')
					);
					$replacement_fieldset = '
						<div><fieldset id="jform_' . $this->attribsField . '_runnotificationary" class="radio btn-group btn-group-yesno nswitch" >
							<input type="radio" ' . $checkedyes . ' value="1" name="custom_runnotificationary" id="jform_' . $this->attribsField . '_runnotificationary1">
							<label for="jform_' . $this->attribsField . '_runnotificationary1" class="btn ' . $active_yes . '">' . JText::_('JYES') . '</label>
							<input type="radio" ' . $checkedno . ' value="0" name="custom_runnotificationary" id="jform_' . $this->attribsField . '_runnotificationary0">
							<label for="jform_' . $this->attribsField . '_runnotificationary0" class="btn' . $active_no . '">' . JText::_('JNO') . '</label>
						</fieldset>
					';
				}
			}


			$oldFormat = false;

			foreach ($possible_tag_ids as $tag)
			{
				$attribute_name = isset($tag[2]) ? $tag[2] : 'id';
				$nswitch_placeholder = NotificationAryHelper::getHTMLElementById($body, $tag[1], $tag[0], $attribute_name);

				if (!empty($nswitch_placeholder))
				{
					break;
				}
			}

			// Not possible to find a place to place the notification switch
			if (empty($nswitch_placeholder))
			{
				return;
			}

			$this->HTMLtype = 'div';

			if (JFactory::getApplication()->isAdmin() && JFactory::getApplication()->getTemplate() !== 'isis')
			{
				$this->HTMLtype = 'li';
			}

			// JEvents compatibility\
			if ($this->context['full'] == 'jevents.edit.icalevent')
			{
				$replacement = '
				<div class="row">
					<div class="span2">
						' . $replacement_label . '
					</div>
					<div class="span10">
						' . $replacement_fieldset . '
					</div>
				</div>
				';
			}
			else
			{
				$replacement = '
				<div class="control-group ">
					<div class="control-label">
						' . $replacement_label . '
					</div>
					<div class="controls">
						' . $replacement_fieldset . '
					</div>
				</div>
				';
			}

			switch ($this->context['option'])
			{
				case $CustomReplacement['option'] :
					break;
				case 'com_jdownloads':
					if ($app->isAdmin())
					{
						$replacement = '</li>' . $replacement . '<li>';
					}
					else
					{
						$label = '
							<label title="" data-original-title="<strong>'
										. JText::_('PLG_SYSTEM_NOTIFICATIONARY_NOTIFY')
									. '</strong><br />'
									. JText::_('PLG_SYSTEM_NOTIFICATIONARY_NOTIFY_DESC') . '" class="hasTooltip required" for="="jform_'
									. $this->attribsField . '_runnotificationary" id="jform_attribs_runnotificationary-lbl">'
								. JText::_('PLG_SYSTEM_NOTIFICATIONARY_NOTIFY')
							. '</label>';

						$field = '
							<select id="jform_'
								. $this->attribsField . '_runnotificationary" name="jform[' . $this->attribsField . '][runnotificationary]"  size="1" class="inputbox">
									<option value="0" ' . $selectedno . '>' . JText::_('JNo') . '</option>
									<option value="1" ' . $selectedyes . '>' . JText::_('JYes') . '</option>
							</select>';

						$replacement = '</div><div class="formelm">' . $label . $field . '</div><div>';
					}

					break;
				case 'com_k2':
					$replacement = str_replace('jform[params]', 'params', $replacement);

					if (!$app->isAdmin())
					{
						$replacement = str_replace('btn-group', '', $replacement);
						$replacement = str_replace('class="btn', 'class="', $replacement);
					}
					// $replacement = '</div></td></tr><tr><td><div>'.$replacement.'';

					break;
				default :
					if (!$app->isAdmin() && $this->paramGet('replacement_type', 'simple') === 'simple')
					{
						// Do nothing
					}
					elseif ($this->HTMLtype == 'div')
					{
						$replacement = '</div></div>' . $replacement . '<div style="display:none;"><div>';
					}
					else
					{
						$replacement = '</li><li>' . $replacement . '</li><li>';
					}

					break;
			}

			// ~ if($this->_shouldShowSwitchCheck() && $this->paramGet('notificationswitchfrontend') == 1 && !$app->isAdmin()) {
			if ($this->shouldShowSwitchCheckFlag && !$app->isAdmin())
			{
				// $nswitch_placeholder = NotificationAryHelper::getHTMLElementById($body,'jform_catid','select');

				// At least at protostar a tab without name appears aboove the article, I assume is generates because of the NS injected into JForm.
				// Let's try to remove it
				$hiddenTab = NotificationAryHelper::getHTMLElementById($body, 'params-basic', $tagname = 'div', $attributeName = 'id');

				$tmp = explode('<div class="control-label"><label id="', $hiddenTab);

				if (empty($hiddenTab) || count($tmp) > 2 || strpos($tmp[1], 'jform_params_runnotificationary-lbl') !== 0)
				{
					// Don't clear
				}
				else
				{
					$hiddenTabNav = '<li><a href="#params-basic" data-toggle="tab"></a></li>';
					$body = str_replace($hiddenTabNav, '', $body);
					$body = str_replace($hiddenTab, '', $body);
				}

				$body = str_replace($nswitch_placeholder, $nswitch_placeholder . $replacement, $body);
			}
			elseif ($this->shouldShowSwitchCheckFlag && $app->isAdmin())
			{
				$AdminSwitch_placeholder_label = NotificationAryHelper::getHTMLElementById(
																					$body, 'jform_' . $this->attribsField . '_runnotificationary-lbl', 'label'
																				);
				$AdminSwitch_placeholder_fieldset = NotificationAryHelper::getHTMLElementById(
																							$body, 'jform_' . $this->attribsField . '_runnotificationary', 'fieldset'
																						);

				// $nswitch_placeholder = NotificationAryHelper::getHTMLElementById($body,'jform_catid','select');
				$body = str_replace($AdminSwitch_placeholder_label, '', $body);
				$body = str_replace($AdminSwitch_placeholder_fieldset, '', $body);
				$body = str_replace($nswitch_placeholder, $nswitch_placeholder . $replacement, $body);
			}
			elseif ($app->isAdmin())
			{
				$AdminSwitch_placeholder_label = NotificationAryHelper::getHTMLElementById(
																					$body, 'jform_' . $this->attribsField . '_runnotificationary-lbl', 'label'
																				);
				$AdminSwitch_placeholder_fieldset = NotificationAryHelper::getHTMLElementById(
																							$body, 'jform_' . $this->attribsField . '_runnotificationary', 'fieldset'
																						);

				$body = str_replace($AdminSwitch_placeholder_label, '', $body);
				$body = str_replace($AdminSwitch_placeholder_fieldset, '', $body);
			}
			else
			{
				return;
			}

			$app->setBody($body);

			return;
		}

		/**
		 * Checks all rules and returns only compatible with current contenItem
		 * Doesn't check some options which are only known onAfterContentSave
		 *
		 * @param   string  $context      Context
		 * @param   object  $contentItem  Content item object
		 * @param   string  $task         Description
		 * @param   bool    $isNew        isNew flag
		 *
		 * @return   type  Description
		 */
		public function _leaveOnlyRulesForCurrentItem($context, $contentItem, $task, $isNew = false)
		{
			$this->task = $task;
$debug = true;
$debug = false;

if ($debug)
{
	dumpMessage('<b>' . __FUNCTION__ . '</b> . | Task : ' . $this->task . ' | isNew ' . $isNew);
}

			// ~ static $rules = array('switch'=>array(),'content'=>array());
			static $rules = array();

			if (!empty($rules[$task]))
			{
				return $rules[$task];
			}

			if (empty($contentItem->id) && $task == 'showSwitch')
			{
				$isNew = true;
			}

if ($debug)
{
	dump($contentItem, '$contentItem');
}

			foreach ($this->pparams as $rule_number => $rule)
			{
				// Pass rule to _checkAllowed
				$this->rule = $rule;

if ($debug)
{
	dump($rule, '$rule ' . $rule_number);
}

if ($task == 'saveItem')
{
	$this->_debug('Checking rule <b>' . $rule->{'{notificationgroup'}[0] . '</b>', false, $rule);
}

				// Not our context
				if ($rule->context != $context )
				{
					if ($task == 'saveItem')
					{
						$this->_debug('Context wrong. Rule: <b>' . $rule->context . '</b>=<b>' . $context . '</b> content. CHECK FAILED');
					}

					continue;
				}

				if ($task == 'saveItem')
				{
					$this->_debug('Context check  PASSED');
				}

if ($debug)
{
	dumpMessage('here 1 ');
}

				if ($rule->ausers_notifyon == 1 && !$isNew)
				{
					if ($task == 'saveItem')
					{
						$this->_debug('Only new allowed but content is not new. CHECK FAILED');
					}

					continue;
				}

if ($debug)
{
	dumpMessage('here 2');
}

				if ($task == 'saveItem')
				{
					$this->_debug('Only new allowed and is New?  PASSED');
				}

				if ($rule->ausers_notifyon == 2 && $isNew)
				{
					if ($task == 'saveItem')
					{
						$this->_debug('Only update is allowed but content is new. CHECK FAILED');
					}

					continue;
				}

if ($debug)
{
	dumpMessage('here 3');
}

				if ($task == 'saveItem')
				{
					$this->_debug('Only update allowed and isn\'t new?  PASSED');
				}

				$user = JFactory::getUser();

				if ($task == 'saveItem')
				{
					$this->_debug('User allowed?   START CHECK');
				}

				// Check if allowed notifications for actions performed by this user
				if (!$this->_checkAllowed($user, $paramName = 'allowuser'))
				{
					if ($task == 'saveItem')
					{
						$this->_debug('User is not allowed to send notifications. CHECK FAILED');
					}

					continue;
				}

if ($debug)
{
	dumpMessage('here 4');
}

				if ($task == 'saveItem')
				{
					$this->_debug('User allowed?   PASSED');
				}

				if ($task == 'showSwitch')
				{
					if (!$rule->shownotificationswitch)
					{
						continue;
					}

if ($debug)
{
	dumpMessage('here 5');
}

					$app = JFactory::getApplication();

					if (!$app->isAdmin() && !$rule->notificationswitchfrontend)
					{
						continue;
					}

if ($debug)
{
	dumpMessage('here 6');
}

					// I assume that notification swicth should be shown for all categories as we may start editing in a non-selected category,
					// but save an item, to a selected category. We must allow the user to select wether to switch

					/*
					if (!$isNew) {
						if (!$this->_checkAllowed($contentItem, $paramName = 'article')) { continue; }
					}
					*/

					// Check if the user is allowed to show the switch
					if (!$this->_checkAllowed($user, $paramName = 'allowswitchforuser'))
					{
						continue;
					}

if ($debug)
{
	dumpMessage('here 7');
}
				}
				elseif ($task == 'saveItem')
				{
					if ($task == 'saveItem')
					{
						$this->_debug('Content allowed?   START CHECK');
					}

					if (!$this->_checkAllowed($contentItem, $paramName = 'article'))
					{
						if ($task == 'saveItem')
						{
							$this->_debug('Content item is not among allowed categories or specific items. CHECK FAILED');
						}

						continue;
					}

					if ($task == 'saveItem')
					{
						$this->_debug('Content allowed? ? PASSED');
						$this->_debug('<b>This rule sends notifications for the content item!!!</b>');
					}
				}

				$rules[$task][$rule_number] = $rule;
			}

			unset($this->task);

			if (isset($rules[$task]))
			{
				return $rules[$task];
			}

			return false;
		}

		/**
		 * Replace plugin code at Frontend
		 *
		 * @param   string  $context   The context of the content being passed to the plugin.
		 * @param   object  &$article  The article object
		 * @param   object  &$params   The article params
		 * @param   int     $page      Returns int 0 when is called not form an article, and empty when called from an article
		 *
		 * @return   void
		 */
		public function onContentPrepare($context, &$article, &$params, $page=null)
		{
			static $assetsAdded = false;
			$app = JFactory::getApplication();

			// Replace plugin code with the subscribe/unsubscribe form if needed
			if ($app->isSite())
			{
				// $body = $app->getBody();

				$regex = '/{na\ssubscribe\s(.*?)}/Ui';

				// Find all instances of plugin and put in $matches for loadposition
				// $matches[0] is full pattern match, $matches[1] is the position
				preg_match_all($regex, $article->text, $matches, PREG_SET_ORDER);

				if ($matches)
				{
					if (!isset($this->pparams))
					{
						$this->_prepareParams();
					}

					$possible_object_parameters = array('text', 'introtext');

					foreach ($possible_object_parameters as $param)
					{
						if (isset($article->{$param}))
						{
							$text = NotificationAryHelper::pluginCodeReplace($this, $article->{$param}, $matches);
						}

						if (isset($text) && $text !==false)
						{
								$article->{$param} = $text;
						}
					}
				}
			}
		}

		/**
		 * Prepares from data at the article edit view.
		 *
		 * It's a must function. The field must not only be outputted in another place (onAfterRender)
		 * but also needs to be called in this function to let it be saved. If just outputting a field, it's not
		 * saved to DB when a content item is saved
		 *
		 * @param   JForm   $form         The form to be altered.
		 * @param   object  $contentItem  The associated data for the form.
		 *
		 * @return  boolean
		 */
		public function onContentPrepareForm($form, $contentItem)
		{
// ~ dump('onContentPrepareForm','onContentPrepareForm');
// ~ dumpTrace();
			$this->_userProfileFormHandle($form, $contentItem);

$debug = true;
$debug = false;
			$jinput = JFactory::getApplication()->input;

			if ($jinput->get('option', null) == 'com_dump')
			{
				return;
			}

if ($debug)
{
	dump('onContentPrepareForm', 'onContentPrepareForm');
	dump($form, 'form');
	dump($contentItem, '$contentItem');
}

			$var = $jinput->get('cid');

			if (!NotificationAryHelper::isFirstRun('onContentPrepareForm'))
			{
				return;
			}

			// Check we are manipulating a valid form.
			if (!($form instanceof JForm))
			{
				$this->_subject->setError('JERROR_NOT_A_FORM');

				return false;
			}

			/* ***************************************************************************************************************************** */
			/*  NOTE!!! I must load the form below even if $contentItem is empty, becasue otherwise it doesn't save the notify switch state  */
			/* ***************************************************************************************************************************** */
			if (!empty($this->context))
			{
				$context = $this->context['full'];
			}
			else
			{
				$context = $form->getName();
			}

			$session = JFactory::getSession();

if ($debug)
{
	dump($context, '$context from Form');
}

			$session->set('FormContext', $context, $this->plg_name);
			$context = $this->_contextAliasReplace($context);
			$this->_setContext($context);

if ($debug)
{
	dump($context, 'here 1 $context');
}

			if (!$this->_isContentEditPage($context) )
			{
				return;
			}

if ($debug)
{
	dump('here 2');
}

			// Specially for JEvents. Here I set data for special JEvents event onEventEdit which is run after onContentPrepare
			if ($context == "jevents.edit.icalevent" )
			{
				global  $NotificationAryFirstRunCheck;

				if (empty($this->form))
				{
					$this->form = &$form;
					$NotificationAryFirstRunCheck['onContentPrepareForm'] = null;

					return;
				}
			}

			// Determine if at least according to one rule the article notification is on. If it's on, then set the appropriate flag in $session
			// If NSwitch is off - FALSE
			$this->shouldShowSwitchCheckFlag = false;

// ~ dump($this->pparams,'$this->pparams');
			// ~ $this->contentItem = $this->_contentItemPrepare($contentItem);

			if (!empty($contentItem))
			{
				$contentItem = $this->_contentItemPrepare($contentItem);
			}

			$rules = $this->_leaveOnlyRulesForCurrentItem($context, $contentItem, 'showSwitch');

			if (empty($rules))
			{
				return;
			}

if ($debug)
{
	dump($rules, '$rules');
	dump('here 3');
}

			$session = JFactory::getSession();

			if (empty($contentItem))
			{
				$attribs = $session->get('AttribsField' . $context, 'attribs', $this->plg_name);
				$session->clear('AttribsField' . $context, $this->plg_name);
			}
			else
			{
				$attribs = 'attribs';

				if (!empty($contentItem) && !isset($contentItem->{$attribs}))
				{
					$attribs = 'params';
				}

				$session->set('AttribsField' . $context, $attribs, $this->plg_name);
			}

// ~ dump($contentItem,'$contentItem');
			$app = JFactory::getApplication();

			if (!empty($contentItem->$attribs))
			{
				if (!is_array($contentItem->$attribs))
				{
					$contentItem->$attribs = (array) json_decode($contentItem->$attribs);
				}
			}

			$this->runnotificationary = 0;

			if (isset ($contentItem->{$attribs}['runnotificationary']))
			{
				$this->runnotificationary = $contentItem->{$attribs}['runnotificationary'];
			}
			else
			{
				// If at lease one active rules has default switch status on
				foreach ($rules as $rule_number => $rule)
				{
					if ($rule->notificationswitchdefault == 1)
					{
						$this->runnotificationary = 1;
					}
				}
			}

			$string = '
						<form>
							<fields name="' . $attribs . '">';

								if ($app->isAdmin())
								{
									$string .= '<fieldset name="basic" >';
								}
								/*
								if (version_compare(JVERSION, '3.7', '<') == 1 || true)
								{
									$string .= '<fieldset name="basic" >';
								}
								*/

								$string .= '
									<field
										label="PLG_SYSTEM_NOTIFICATIONARY_NOTIFY"
										description="PLG_SYSTEM_NOTIFICATIONARY_NOTIFY_DESC"
										name="runnotificationary"
										type="radio"
										class="btn-group btn-group-yesno nswitch"
										default="' . $this->runnotificationary . '"
										>
										<option value="0">JNO</option>
										<option value="1">JYES</option>
									</field>';

								if ($app->isAdmin())
								{
									$string .= '</fieldset>';
								}
								/*
								if (version_compare(JVERSION, '3.7', '<') == 1 || true)
								{
									$string .= '</fieldset>';
								}
								*/

								$string .= '
							</fields>
						</form>';

			$form->load((string) $string, true);
			$this->attribsField = $attribs;
			$this->shouldShowSwitchCheckFlag = true;

			$CustomReplacement = $session->get('CustomReplacement', null, $this->plg_name);

			if (!empty($CustomReplacement) && $CustomReplacement['context'] == $this->context['full'])
			{
				$switch_selector = $CustomReplacement['switch_selector'];
				$form_selector = $CustomReplacement['form_selector'];
			}
			else
			{
				$switch_selector = "[name=\"jform[" . $attribs . "][runnotificationary]\"]:checked";
				$form_selector = 'adminForm';
			}

			foreach ($rules as $rule_number => $rule)
			{
				if ($rule->notificationswitchaddconfirmation)
				{
					$doc = JFactory::getDocument();
					$language = JFactory::getLanguage();

					// Have to load current logged in user language to show the proper menu item language, not the default backend language
					$language->load($this->plg_full_name, $this->plg_path, $language->get('tag'), true);

					$js = "
						jQuery(document).ready(function (\$){

							jQuery('form[name=\"" . $form_selector . "\"]').submit(function(event) {
								var n = this.task.value.indexOf('cancel');

								if (n !== -1)
								{
									return true;
								}
								var \$switch=\$('" . $switch_selector . "');

								if (\$switch < 1 || \$switch.val() != 1)
								{
									return;
								}
								var c = confirm('"
								. JText::_(
										JText::sprintf(
											'PLG_SYSTEM_NOTIFICATIONARY_ARE_YOU_SURE',
											'"' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_NOTIFY') . '"',
											'"' . JText::_('JNo') . '"'
										),
										true
									)
								. "');
								return c; //you can just return c because it will be true or false
							});
						});";
					$doc->addScriptDeclaration($js);

					break;
				}
			}

			return true;
		}

		/**
		 * Loads available mail placeholders from a special file
		 *
		 * @return   type  void
		 */
		public function _loadMailPlaceholders()
		{
			// Load placeholders, but only once, no need to to load file each time building a email
			static $placeholders_loaded = false;

			if (!$placeholders_loaded)
			{
				require JPATH_SITE . '/plugins/system/notificationary/helpers/field_mailbodyHelper.php';

				foreach ($ph_body as $k => $v)
				{
					if (strpos($v, '%') !== 0)
					{
						unset($ph_body[$k]);
						continue;
					}
				}

				foreach ($ph_subject as $k => $v)
				{
					$this->place_holders_subject[$v] = null;
				}

				foreach ($ph_body as $k => $v)
				{
					$this->place_holders_body[$v] = null;
				}

				$placeholders_loaded = true;
			}
		}

		/**
		 * Normalized content item object to the common form
		 *
		 * If a content item object as own names of properties like "description"
		 * insted of "fulltext", then it creates fulltext property based on the pairs
		 * in $this->object_variables_to_replace
		 *
		 * @param   object  $contentItem  Content item object
		 *
		 * @return  object   Normalized object
		 */
		public function _contentItemPrepare($contentItem)
		{
			$convertedFromArray = false;

			if (is_array($contentItem))
			{
				$convertedFromArray = true;
				$return = (object) $contentItem;
			}
			elseif(is_object($contentItem))
			{
				$return = clone $contentItem;
			}
			else
			{
				return $contentItem;
			}

			if (isset($return->_contentItemPrepareAlreadyPrepared))
			{
				if ($convertedFromArray)
				{
					$return = (array) $return;
				}

				return $return;
			}

      		$return->_contentItemPrepareAlreadyPrepared = true;

			if (property_exists($return, 'state') && $return->state === null)
			{
				$return->state = 0;
			}

			if (property_exists($return, 'published') && (empty($return->state)))
			{
				$return->state = $return->published;
			}

			foreach ($this->object_variables_to_replace as $array)
			{
				if ($array[1] === false && !property_exists($return, $array[0]))
				{
					$return->{$array[0]} = null;
				}
				elseif (!property_exists($return, $array[0]) && property_exists($return, $array[1]))
				{
					$return->{$array[0]} = $return->{$array[1]};
				}
				elseif (isset($array[2]) && $array[2] && property_exists($return, $array[1]))
				{
					$return->{$array[0]} = $return->{$array[1]};
				}
			}

			if (!isset($return->id))
			{
				if (method_exists($contentItem, 'get'))
				{
				$tbl_key = $contentItem->get('_tbl_key');

				if (!empty($tbl_key))
				{
					$return->id = $contentItem->{$tbl_key};
				}
				}
					}

			if ($convertedFromArray)
			{
				$return = (array) $return;
			}

			$session = JFactory::getSession();
			$CustomReplacement = $session->get('CustomReplacement', null, $this->plg_name);

			if (isset($CustomReplacement['context']))
			{
				if (empty($contentItem->introtext))
				{
					$return->introtext = isset($CustomReplacement['introtext']) ? $CustomReplacement['introtext'] : null;
				}

				if (empty($contentItem->fulltext))
				{
					$return->fulltext = isset($CustomReplacement['fulltext']) ? $CustomReplacement['fulltext'] : null;
				}

			}

			return $return;
		}

		/**
		 * Replace some contexts which should be handled in the same way
		 *
		 * @param   string  $context      Context
		 * @param   mixed   $contentItem  Content item
		 *
		 * @return   string  replace context
		 */
		public function _contextAliasReplace($context, $contentItem = false)
		{
			$this->real_context = $context;
						/*
						"com_categories.categorycom_content" => 'com_categories.category',
						"com_banners.category" => 'com_categories.category',
						"com_categories.categorycom_banners" => 'com_categories.category',
						*/
			while (true)
			{
				// When editing an article at first (not after page reload), I can meet such ""
				$tmp = explode('.', $context);

				if (count($tmp) == 3 && $tmp[2] == 'filter')
				{
					$context = $tmp[0] . '.' . $tmp[1];
					break;
				}

				if ($contentItem && !empty($contentItem->extension) && $context == 'com_categories.category')
				{
					$context = $contentItem->extension . '.category';
				}

				if (strpos($context, 'com_categories.categorycom_') === 0)
				{
					return str_replace('com_categories.category', '', $context) . '.category';
				}

				$session = JFactory::getSession();
				$formContext = $session->get('FormContext', null, $this->plg_name);

				// When editing an article at first (not after page reload), I can meet such ""
				$tmp = explode('.', $formContext);
				$flag = false;

				if (!isset($tmp[2]))
				{
					$flag = true;
				}

				if (isset($tmp[2]) && $tmp[2] != 'filter')
				{
					$flag = true;
				}

				if ($formContext && $context !== $formContext && $flag)
				{
					$formContext = explode('.', $formContext);
					$currentContext = explode('.', $context);

					if ($currentContext[0] == $formContext[0])
					{
						$context = $formContext[0] . '.' . $formContext[1];
					}
				}

				break;
			}

			if (isset($this->context_aliases[$context]))
			{
				return $this->context_aliases[$context];
			}

			return $context;
		}

		/**
		 * Outputs debug message
		 *
		 * @param   string  $msg      Debug message
		 * @param   bool    $newLine  If yo start from new line
		 * @param   string  $var      Description
		 *
		 * @return   type  Description
		 */
		protected function _debug($msg, $newLine = true, $var = 'not set' )
		{
			if (!$this->paramGet('debug'))
			{
				return;
			}

			if ($var === 0)
			{
				$var = 'NO';
			}

			if ($var === 1)
			{
				$var = 'YES';
			}

			if (function_exists('dump') && function_exists('dumpMessage'))
			{
				if ($var !== 'not set')
				{
					dump($var, $msg);
				}
				else
				{
					dumpMessage($msg);
				}
			}
			else
			{
				$out_msg = array();
				$out_msg[] = $msg;

				if ($var !== 'not set')
				{
					if (is_array($var) || is_object($var))
					{
						$out_msg[] = '<pre>';
						$out_msg[] = print_r($var, true);
						$out_msgmsg[] = '</pre>';
					}
					else
					{
						$out_msg [] = ' | ' . $var;
					}
				}

				$msg = implode(PHP_EOL, $out_msg);

				if ($newLine)
				{
					$msg = '<br>' . PHP_EOL . $msg;
				}

				JFactory::getApplication()->enqueueMessage($msg, 'notice');
			}
		}

		/**
		 * Redirects ajax requests to unsubscribe users
		 *
		 * @return void;
		 */
		public function onAfterRoute()
		{
			$jinput = JFactory::getApplication()->input;
			$uniq = $jinput->get('unsubscribe', null);
			$email = $jinput->get('email', null, 'raw');
			$md5 = $jinput->get('hash', null, 'raw');

			if ($uniq)
			{
				$serialize = (base64_encode(serialize(array('unsubscribe' => $email, 'md5' => $md5))));
				$app	= JFactory::getApplication();

				$redirect_url = 'index.php?option=com_ajax&format=raw'
					. '&group=' . $this->plg_type
					. '&plugin=notificationAryRun'
					. '&' . JSession::getFormToken() . '=1'
					. '&uniq=' . uniqid()
					. '&uniq=' . $uniq
					. '&serialize=' . $serialize;

				$app->redirect($redirect_url);
			}
		}

		/**
		 * Adds additional fields to the user editing form
		 *
		 * @param   JForm  $form  The form to be altered.
		 * @param   mixed  $data  The associated data for the form.
		 *
		 * @return  boolean
		 *
		 * @since   1.6
		 */
		public function _userProfileFormHandle($form, $data)
		{
			if (!($form instanceof JForm))
			{
				$this->_subject->setError('JERROR_NOT_A_FORM');

				return false;
			}

			// Check we are manipulating a valid form.
			$name = $form->getName();

			$app = JFactory::getApplication();

			if (!in_array($name, array('com_admin.profile', 'com_users.user', 'com_users.profile', "com_users.users.default.filter")))
			{
				return true;
			}

			// Pass the plugin object to be available in the field to have plugin params parsed there
			$app->set($this->plg_full_name, $this);

			if ($name == "com_users.users.default.filter")
			{
				JForm::addFormPath(__DIR__ . '/forms');
				$form->loadFile('filter', false);

				$items_model = JModelLegacy::getInstance('Users', 'UsersModel');
				$ruleUniqID = $items_model->getState('filter.naruleUniqID');
				/* // ##mygruz20170214152631 DO NOT DELETE.
				 * I tried to make the filters be opened upong a page load
				 * but this didn't work. Not to invest
				*/

				return true;
			}

			$jinput = JFactory::getApplication()->input;
			$userID = $jinput->get('id', null);

      if (empty($userID))
      {
        return;
      }

			// Add the registration fields to the form.
			JForm::addFormPath(__DIR__ . '/forms');
			$form->loadFile('subscribe', false);
			$form->setFieldAttribute('subscribe', 'userid', $userID, 'nasubscribe');
			$form->setFieldAttribute('subscribe', 'isProfile', true, 'nasubscribe');

			$doc = JFactory::getDocument();
			$js = '
				jQuery(document).ready(function($){
					var label = $(".nasubscribe").closest("div.control-group").find(".control-label:first").text().trim();
					if (label.length === 0)
					{
						$(".nasubscribe").closest("div.controls").css("margin-left", "0");
					}

				});
			';
			$doc->addScriptDeclaration($js);
		}

		/**
		 * Using MVC override approach to override com_users to filter users by subscriptions
		 *
		 * @return   void
		 */
		public function onAfterInitialise()
		{
			NotificationAryHelper::_autoOverride($this);

			$this->_prepareParams();

			foreach ($this->pparams as $k => $param)
			{

				if (!$param->isenabled)
				{
					continue;
				}
				if ($param->context_or_contenttype == "context" && $param->context == "com_zoo.item" && JComponentHelper::getComponent('com_zoo', true)->enabled)
				{
					NotificationAryHelper::loadZoo();
					break;
				}
			}
		}
	}

	JLoader::register('plgSystemNotificationary', __FILE__);

	// Generate and empty object
	$plgParams = new JRegistry;

	// Get plugin details
	$plugin = JPluginHelper::getPlugin('system', 'notificationary');

	// Load params into our params object
	$plgParams->loadString($plugin->params);
	$jinput = JFactory::getApplication()->input;

	if ($jinput->get('option', null) == 'com_dump')
	{
		return;
	}

	$notificationgroup = $plgParams->get('{notificationgroup');

	$custom_templates = array();

	if (!empty($notificationgroup->context_or_contenttype))
	{
		$context_or_contenttype = $notificationgroup->context_or_contenttype;
		$enabled = $plgParams->get('{notificationgroup')->isenabled;

		foreach ($context_or_contenttype as $k => $v )
		{
			if ($v == 'context' && $enabled[$k] == 1 )
			{
				// $custom_templates[] = $plgParams->get('{notificationgroup')->context[$k];
				$custom_template = $plgParams->get('{notificationgroup')->context[$k];
				$custom_template = NotificationAryHelper::_parseManualContextTemplate($custom_template);

				if (!empty($custom_template['Context']))
				{
					$custom_templates[$custom_template['Context']] = $custom_template;
				}
			}
		}

		if (!isset($predefined_context_templates))
		{
			include dirname(__FILE__) . '/helpers/predefined_contexts.php';
		}
	}

	$temp_alias_functions = array();

	foreach ($custom_templates as $context => $array)
	{
		foreach ($functionsToBeAliased as $functionName)
		{
			if (empty($array[$functionName]))
			{
				continue;
			}

			$array[$functionName] = trim($array[$functionName]);

			if (strpos($array[$functionName], 'function ') === 0 || strpos($array[$functionName], 'static function ') === 0)
			{
				$temp_alias_functions [$array[$functionName]] = 'public ' . $array[$functionName];
			}
			else
			{
				// Prevent error if custom file doesn't exists'
				if (strpos($array[$functionName], '/') === false)
				{
					$temp_alias_functions [$array[$functionName]] = '
					public function ' . $array[$functionName] . ' ($context, $contentItem, $isNew) {
						return $this->' . $functionName . '($context, $contentItem, $isNew);
					}
					';
				}
			}
		}
	}

	$class_dynamic = '
	class plgSystemNotificationary extends plgSystemNotificationaryCore {
		public function __construct(& $subject, $config) {
			parent::__construct($subject, $config);
		}
		' . implode(PHP_EOL, $temp_alias_functions) . '
	}';
	eval($class_dynamic);
}
