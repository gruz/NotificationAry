<?php
/**
 * NotificationaryCore helper class
 *
 * @package    Notificationary

 * @author     Gruz <arygroup@gmail.com>
 * @copyright  0000 Copyleft (є) 2017 - All rights reversed
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace NotificationAry\HelperClasses;

use NotificationAry\HelperClasses\NotificationAryHelper;
use NotificationAry\HelperClasses\FakeMailerClass;

// No direct access
defined('_JEXEC') or die('Restricted access');

use JText,
	JTable,
	JForm,
	JString,
	JEventsDataModel,
	JURI,
	JUserHelper,
	JFile, 
	JFolder,
	JUser,
	JApplication,
	JLoader,
	JPath,
	JCategories,
	JModelLegacy,
	JRoute,
	JApplicationHelper,
	JSession,
	JFactory
;
/**
 * Plugin code
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
class NotificationaryCore extends \JPluginGJFields
{
	use Traits\CoreEvents;
	use Traits\AjaxEvents;
	use Traits\BuildMail;
	use Traits\ParamsHandler;
	// use Traits\SmallFunctions;
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

	protected $availableDIFFTypes = array('Text/Unified', 'Text/Context', 'Html/SideBySide', 'Html/Inline');

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

	static protected $shouldShowSwitchCheckFlag = false;

	protected $context_aliases = array(
		'com_content.category' => 'com_categories.category',
		"com_banners.category" => 'com_categories.category',
		// ~ "com_content.form" => 'com_content.article',
		// ~ "com_jdownloads.form" => 'com_jdownloads.download',

		// "com_categories.categorycom_content" => 'com_categories.category',
		// "com_categories.categorycom_banners" => 'com_categories.category',
		'com_categories.categories' => 'com_categories.category',
	);

	// Joomla article object differs from i.e. K2 object. Make them look the same for some variables
	protected $object_variables_to_replace = array(
		// State in com_contact means not status, but a state (region), so use everywhere published
		array('published', 'state'),
		array('fulltext', 'description'),
		array('title', 'name'),

		// User note
		array('title', 'subject'),

		// JEvents
		array('title', '_title'),
		array('fulltext', '_content'),
		array('published', '_state'),
		array('id', '_ev_id'),

		// Banner client
		array('fulltext', 'extrainfo'),

		// Contact
		array('fulltext', 'misc'),

		// User note
		array('fulltext', 'body'),

		// Banner category
		array('created_by', 'created_user_id'),

		// Jdownloads. Third parameter means force to override created_by with created_id even if created_by exists
		array('created_by', 'created_id', true),

		// Banner category
		array('modified_by', 'modified_user_id'),
		array('created', 'created_time'),
		array('modified', 'modified_time'),

		// JDownloads
		array('id', 'file_id'),
		array('catid', 'cat_id'),
		array('title', 'file_title'),

		array('introtext', false),
		array('title', false),
		array('alias', false),
		array('fulltext', false),
	);

	public static $CustomHTMLReplacementRules;

	/**
	 * Constructor.
	 *
	 * @param   object  &$subject  The object to observe.
	 * @param   array   $config    An optional associative array of configuration settings.
	 */
	public function __construct(&$subject, $config)
	{
		$jinput = JFactory::getApplication()->input;

		if ($jinput->get('option', null) == 'com_dump') {
			return;
		}

		parent::__construct($subject, $config);

		$this->_preparePluginHasBeenSavedOrAppliedFlag();

		// Would not work at most of servers. Try to let save huge forms.
		ini_set('max_input_vars', 5000);

		if ($this->pluginHasBeenSavedOrApplied) {
			$this->_updateRulesIfHashSaved();
		}

		// Generate users
		while (true) {
			if (!$this->paramGet('debug')) {
				break;
			}

			if (!$this->paramGet('generatefakeusers')) {
				break;
			}

			$usergroups = $this->paramGet('fakeusersgroups');

			$usernum = (int) $this->paramGet('fakeusersnumber');

			if (NotificationAryHelper::isFirstRun('userGenerator')) {
				NotificationAryHelper::userGenerator($usernum, $usergroups);
			}

			break;
		}
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
	private function _getExtensionInfo($context = null, $id = null)
	{
		if (!empty($id)) {
			$contentType = JTable::getInstance('contenttype');
			$contentType->load($id);
			$context = $this->_contextAliasReplace($contentType->type_alias);
		} else {
			$contentType = JTable::getInstance('contenttype');
			$contentType->load(array('type_alias' => $context));
		}

		$extension_info = NotificationAryHelper::_parseManualContextTemplate($context);

		if (!is_array($extension_info)) {
			if (!isset($predefined_context_templates)) {
				include NotificationAry_DIR . '/helpers/predefined_contexts.php';
			}

			$extension_info = array_flip($rows);

			foreach ($extension_info as $k => $v) {
				$extension_info[$k] = null;
			}
		}

		if (empty($extension_info['Context'])) {
			$extension_info['Context'] = $context;
		}

		// Extensions is not registred in Joomla
		if (empty($contentType->type_id)) {
			return array($extension_info, $contentType);
		}

		list($option, $suffix) = explode('.', $context, 2);
		$category_context = $option . '.category';
		$contentTypeCategory = JTable::getInstance('contenttype');
		$contentTypeCategory->load(array('type_alias' => $category_context));

		foreach ($extension_info as $key => $value) {
			if (!empty($value) || $value === false) {
				continue;
			}

			switch ($key) {
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
					if (!empty($contentTypeCategory->getContentTable()->type_id)) {
						// ~ $extension_info[$key] = get_class($contentTypeCategory->getContentTable());
						$extension_info[$key] = NotificationAryHelper::get_class_from_ContentTypeObject($contentType);
					}

					break;
				case 'Category context':
					if (!empty($contentTypeCategory->getContentTable()->type_id)) {
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
					$extension_info[$key] = '\\'. $contentType->router;
					break;
				default:

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
	public function _isContentEditPage(&$context)
	{
		$this->_prepareParams();

		if (!empty($context)) {
			if (in_array($context, $this->allowed_contexts)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get's content item table if possible, usually a JTable extended class
	 *
	 * @param   string  $context           Context, helps to determine which table to get
	 * @param   bool    $getCategoryTable  Wether we are getting a category table or a content item table
	 *
	 * @return   type  Description
	 */
	public function _getContentItemTable($context, $getCategoryTable = false)
	{
		// Parse context var in case it's an extension template textarea field
		$extension_info = NotificationAryHelper::_parseManualContextTemplate($context);

		if (!is_array($extension_info)) {
			if (isset($this->predefined_context_templates) && isset($this->predefined_context_templates[$context])) {
				$extension_info = $this->predefined_context_templates[$context];
			}
		}

		// No context in the manual template entered
		if ($context == $extension_info) {
			return false;
		}

		if (is_array($extension_info)) {
			if ($getCategoryTable) {
				$jtableClassName = $extension_info['Category table class'];

				if (!empty($extension_info['Category context'])) {
					$context = $extension_info['Category context'];
				}
			} else {
				$jtableClassName = $extension_info['Item table class'];

				if (!empty($extension_info['Context'])) {
					$context = $extension_info['Context'];
				}
			}
		}

		if (!empty($jtableClassName)) {
			if (strpos($jtableClassName, ':')  !== false) {
				$tablename = explode(':', $jtableClassName);
				$path = $tablename[0];
				$jtableClassName = $tablename[1];
			}

			$tablename = explode('Table', $jtableClassName);

			if (isset($tablename[1])) {
				$type = $tablename[1];
				$prefix = explode('_', $tablename[0]);
				$prefix = $tablename[0] . 'Table';
			} else {
				$type = $tablename[0];
			}

			$temp = explode('.', $context, 2);

			if (empty($path)) {
				JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/' . $temp[0] . '/tables');
			} else {
				JTable::addIncludePath(JPATH_ROOT . '/' . $path);
			}
		} else {
			// $contenttypeObject = JTable::getInstance( 'contenttype');
			// $contenttypeObject->load( $extension );
			$context = $this->_contextAliasReplace($context);

			switch ($context) {
				case 'com_content.article':
					// $contentItem = JTable::getInstance( 'content');
					$type = 'content';
					$prefix = null;
					break;
				case 'com_users.user':
					$type = 'user';
					$prefix = null;
					break;
				default:
					$tablename = explode('.', $context);
					JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/' . $tablename[0] . '/tables');

					// Category
					$type = $tablename[1];
					$prefix = explode('_', $tablename[0]);
					$prefix = $prefix[1] . 'Table';
					break;
			}
		}

		if (empty($prefix) && empty($type)) {
			return false;
		}

		if (!empty($prefix)) {
			$contentItem = JTable::getInstance($type, $prefix);
		} else {
			$contentItem = JTable::getInstance($type);
		}

		if (!$contentItem || !method_exists($contentItem, 'load')) {
			if (!$this->paramGet('debug')) {
				$app = JFactory::getApplication();
				$appReflection = new ReflectionClass(get_class($app));
				$_messageQueue = $appReflection->getProperty('_messageQueue');
				$_messageQueue->setAccessible(true);
				$messages = $_messageQueue->getValue($app);
				$cmpstr = JText::sprintf('JLIB_DATABASE_ERROR_NOT_SUPPORTED_FILE_NOT_FOUND', $type);

				foreach ($messages as $key => $message) {
					if ($message['message'] == $cmpstr) {
						unset($messages[$key]);
					}
				}

				$_messageQueue->setValue($app, $messages);
			} else {
				if (!isset($prefix)) {
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
	 * Set's global plugin context array to know what type of content we work with
	 *
	 * @param   string  $context  Context
	 *
	 * @return   array
	 */
	public function _setContext($context)
	{
		$this->context = array();
		$this->context['full'] = $context;
		$tmp = explode('.', $context);
		$this->context['option'] = $tmp[0];

		if (strpos($this->context['option'], 'com_') !== 0) {
			$this->context['option'] = 'com_' . $tmp[0];
		}

		$this->context['task'] = $tmp[1];
		$tmp = explode('_', $this->context['option']);

		if (isset($tmp[1])) {
			$this->context['extension'] = $tmp[1];
		}

		switch ($this->context['option']) {
			case 'com_categories':
				$this->context['extension'] = 'content';
				break;
			default:

				break;
		}
	}

	// ~ public function onBeforeHotspotSave($context, $contentItem, $isNew) { $this->onContentAfterSave($context, $contentItem, $isNew);	}
	// ~ public function onAfterHotspotSave($context, $contentItem, $isNew) { $this->onContentAfterSave($context, $contentItem, $isNew);	}

	/**
	 * Clean attachment files from the temp folder
	 *
	 * @return   void
	 */
	protected function _cleanAttachments()
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
	protected function _send_mails(&$Users_to_send)
	{
		if (empty($Users_to_send)) {
			return;
		}

		$app = JFactory::getApplication();

		if ($this->paramGet('forceNotTimeLimit')) {
			$maxExecutionTime = ini_get('max_execution_time');
			set_time_limit(0);
		}

		foreach ($Users_to_send as $key => $value) {
			if (!empty($value['id'])) {
				// ~ $user = JFactory::getUser($value['id']);
				$user = NotificationAryHelper::getUser($value['id']);
			} else {
				$user = NotificationAryHelper::getUserByEmail($value['email']);
			}

			if (empty($user->id)) {
				$user = JFactory::getUser(0);
				$user->set('email', $value['email']);
			}

			$mail = $this->_buildMail($user);

			if (!$mail) {
				continue;
			}

			if ($this->isAjax) {
				$mailer = new FakeMailerClass;
			} else {
				// This object is not serializable, so I need to use a simple object to store and pass information to the ajax part
				$mailer = JFactory::getMailer();
			}

			if ($this->rule->emailformat != 'plaintext') {
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

			if (isset($this->rule->attachpreviousversion)) {
				foreach ($this->rule->attachpreviousversion as $k => $v) {
					if (isset($this->attachments[$v])) {
						$mailer->addAttachment($this->attachments[$v]);
					}
				}
			}

			if (isset($this->rule->attachdiffinfo)) {
				foreach ($this->rule->attachdiffinfo as $k => $v) {
					if (isset($this->attachments[$v])) {
						$mailer->addAttachment($this->attachments[$v]);
					}
				}
			}

			$curr_root = parse_url(JURI::root());
			$live_site_host = $curr_root['scheme'] . '://' . $curr_root['host'] . '/';
			$live_site = JURI::root();

			$link = $live_site . 'index.php?unsubscribe=' . $this->rule->__ruleUniqID
				. '&email=' . $user->email . '&hash=' . md5($user->id . $this->rule->__ruleUniqID);

			if ($this->rule->messagebodysource == 'hardcoded') {
				$includeunsubscribelink = $this->rule->ausers_includeunsubscribelink;

				if ($includeunsubscribelink) {
					if ($this->rule->emailformat == 'plaintext') {
						$mail['body'] .= PHP_EOL . PHP_EOL . JText::_('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBE') . ': ' . $link;
					} else {
						$mail['body'] .= '<br/><br/><a href="' . $link . '">' . JText::_('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBE') . '</a>';
					}
				}
			} else {
				$mail['body'] = str_replace('%UNSUBSCRIBE LINK%', $link, $mail['body']);
			}

			$mailer->setBody($mail['body']);

			if ($this->isAjax) {
				if (!isset($this->ajaxHash)) {
					$this->ajaxHash = uniqid();
				}

				$mailer_ser = base64_encode(serialize($mailer));
				$tmpPath = JFactory::getApplication()->getCfg('tmp_path');
				$filename = $this->plg_name . '_' . $this->ajaxHash . '_' . uniqid();
				JFile::write($tmpPath . '/' . $filename, $mailer_ser);

				continue;
			}

			$send = $mailer->Send();

			if ($send !== true) {
				$this->broken_sends[] = $mail['email'];
			}
		}

		if ($this->paramGet('forceNotTimeLimit')) {
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

		if ($debug) {
			dump('users 1');
		}

		// 0 => New and Updates; 1 => New only; 2=> Updated only
		$nofityOn = $this->rule->ausers_notifyon;

		// If notify only at New, but the article is not new
		if ($nofityOn == 1 && !$this->isNew) {
			return array();
		}

		if ($debug) {
			dump('users 2');
		}

		// If notify only at Updated, but the article is New
		if ($nofityOn == 2 && $this->isNew) {
			return array();
		}

		if ($debug) {
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

		if ($debug) {
			dump($status_action_to_notify, '$status_action_to_notify');
			dump($this->publish_state_change, '$this->publish_state_change');
			dump($this->contentItem->state, '$this->contentItem->state');
		}

		while (true) {
			if (in_array('always', $status_action_to_notify)) {
				break;
			}

			// If current item status is among allowed statuses
			if (in_array((string) $this->contentItem->state, $status_action_to_notify)) {
				break;
			}

			$intersect = array_intersect($status_action_to_notify, $possible_actions);

			// If there is an action among selected parameters in $status_action_to_notify
			if (!empty($intersect)) {
				// Then we check the action happened to the content item
				if ($this->publish_state_change == 'nochange' || $this->publish_state_change == 'not determined') {
					// Do nothing, means returning empty array.
					// So if we want a notification on an action but the action cannot be determined, then noone has to be notified
				} elseif (in_array($this->publish_state_change, $status_action_to_notify)) {
					break;
				}
			}

			if ($debug) {
				dump(
					$status_action_to_notify,
					'Content item status or action is now among allowed options. $this->contentItem->state ='
						. $this->contentItem->state . ' | $this->publish_state_change ='
						. $this->publish_state_change . ' | Allowed options '
				);
			}

			return array();
		}

		if ($debug) {
			dump('users 4 - Content status or action is among allowed ones');
		}

		$user = JFactory::getUser();

		// Check if notifications turned on for current user
		if (!$this->_checkAllowed($user, $paramName = 'allowuser')) {
			return array();
		}

		if ($debug) {
			dump('users 5 - check if notifications turned on for current article ...');
		}

		// Check if notifications turned on for current article
		if (!$this->_checkAllowed($this->contentItem, $paramName = 'article')) {
			return array();
		}

		if ($debug) {
			dump('users 6 - start creating a list of emails ');
		}

		$users_to_send = array();
		$UserIds = array();

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
		if ($onGroupLevels == -1 && $onItems == 0) {
			return $users_to_send;
		}

		$GroupLevels = $this->rule->{$groupName . 'selection'};
		$UserIds = $this->rule->{$itemName . 'selection'};

		// If exclude some user groups, but no groups selected, then it's assumed that all groups are to be included
		if ($onGroupLevels == 2 && empty($GroupLevels)) {
			$onGroupLevels = 0;
		}

		// If include some user groups, but no groups selected, then it's assumed that all groups are to be excluded
		if ($onGroupLevels == 1 && empty($GroupLevels)) {
			$onGroupLevels = -1;
		}

		// If exclude/include some users, but no user ids selected, then it's assumed no specific rules applied per user
		if (($onItems == 1 || $onItems == 2) && empty($UserIds)) {
			$onItems = 0;
		}

		$db = JFactory::getDBO();

		// Create WHERE conditions start here

		// Prepare ids of groups and items to include in the WHERE below
		while (true) {
			// If no limitation set - for user groups and specific users either all or selected - break
			if ($onGroupLevels == 0 && $onItems == 0)
			// ~ if (($onGroupLevels == 0  && $onItems == 0) || $onGroupLevels == 0  && $onItems == 1 )
			{
				break;
			}

			// If selected groups (otherwise, if no or all groups - we add nothing to WHERE)
			if ($onGroupLevels > 0) {
				if (!is_array($GroupLevels)) {
					$GroupLevels = explode(',', $GroupLevels);
				}

				$GroupLevels = array_map('intval', $GroupLevels);
				$GroupLevels = array_map(array($db, 'Quote'), $GroupLevels);

				if ($onGroupLevels == 1) {
					$GroupWhere = 'AND';
				} elseif ($onGroupLevels == 2) {
					$GroupWhere = 'NOT';
				}
			}

			// If use selected user ids, then prepare the array of the ids for WHERE
			if ($onItems != 0) {
				if (!is_array($UserIds)) {
					$UserIds = explode(',', $UserIds);
				}

				$UserIds = array_map('intval', $UserIds);
				$UserIds = array_map(array($db, 'Quote'), $UserIds);

				$UserWhere = 'AND';

				if ($onItems == 1) {
					$UserWhere = 'AND';
				} elseif ($onItems == 2) {
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

		if (!empty($this->contentItem->modified_by) && $this->contentItem->modified_by != $this->contentItem->created_by) {
			$query->where(" id <> " . $db->Quote($this->contentItem->modified_by));
		}

		if (!empty($GroupLevels)) {
			$where = '';

			if (!empty($GroupWhere) && $GroupWhere == 'NOT') {
				$where .= $GroupWhere;
			}

			$where .= ' ( group_id = ' . implode(' OR group_id = ', $GroupLevels) . ')';
			$query->where($where);
		}

		if (!empty($UserIds)) {
			$where = '';

			if ($UserWhere == 'NOT') {
				$where .= $UserWhere;
			} else {
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
		switch ($this->rule->allow_subscribe) {
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
				foreach ($users_to_send as $k => $user) {
					if (is_array($this->contentItem->catid)) {
						$unset = true;
						foreach ($this->contentItem->catid as $k => $catid) {
							if (NotificationAryHelper::checkIfUserSubscribedToTheCategory($this->rule, $user, $catid, $force = true)) {
								$unset = false;
							}
						}

						if ($unset) {
							unset($users_to_send[$k]);
						}
					} else {
						if (!NotificationAryHelper::checkIfUserSubscribedToTheCategory($this->rule, $user, $this->contentItem->catid, $force = true)) {
							unset($users_to_send[$k]);
						}
					}
				}

				break;

				// Do nothing, as users cannot subscribe themselves
			case '0':
			default:
				break;
		}

		$notifyonlyifcanview = $this->rule->ausers_notifyonlyifcanview;

		// E.g. joomla banner has no access option, so we ignore it here
		if ($notifyonlyifcanview && isset($this->contentItem->access)) {
			foreach ($users_to_send as $k => $value) {
				if (!empty($value['id'])) {
					// ~ $user = JFactory::getUser($value['id']);
					$user = NotificationAryHelper::getUser($value['id']);
				} else {
					$user = JFactory::getUser(0);
					$user->set('email', $value['email']);
				}

				$canView = false;

				// $canEdit = $user->authorise('core.edit', 'com_content.article.'.$this->contentItem->id);
				// $canLoginBackend = $user->authorise('core.login.admin');

				if (in_array($this->contentItem->access, $user->getAuthorisedViewLevels())) {
					$canView = true;
				}

				if (!$canView) {
					unset($users_to_send[$k]);
				}
			}
		}

		$Users_Add_emails = $this->rule->ausers_additionalmailadresses;
		$Users_Add_emails = explode(PHP_EOL, $Users_Add_emails);
		$Users_Add_emails = array_map('trim', $Users_Add_emails);

		foreach ($Users_Add_emails as $cur_email) {
			$cur_email = JString::trim($cur_email);

			if ($cur_email == "") {
				continue;
			}

			$add_mail_flag = true;

			foreach ($users_to_send as $v => $k) {
				if ($k['email'] == $cur_email) {
					$add_mail_flag = false;
					break;
				}
			}

			if ($add_mail_flag) {
				$users_to_send[]['email'] = $cur_email;
			}
		}

		if ($debug) {
			dump($users_to_send, 'users 7');
		}

		return (array) $users_to_send;
	}

	/**
	 * Adds content authtor and/or modifier if needed
	 *
	 * @return   array  Array of arrays with author and modifier data
	 */
	protected function _addAuthorModifier()
	{
		$users_to_send_helper = array();

		// If I'm the author and I modify the content item
		if ($this->author->id == $this->modifier->id) {
			if (!$this->rule->ausers_notifymodifier) {
				return array();
			}

			if ($this->rule->ausers_notifymodifier) {
				$users_to_send_helper[] = array(
					'id' => $this->modifier->id,
					'email' => $this->modifier->email,
					'name' => $this->modifier->name,
					'username' => $this->modifier->username
				);

				return $users_to_send_helper;
			}
		}

		// If I modify the content item, but I'm not the author
		if ($this->rule->ausers_notifymodifier) {
			$users_to_send_helper[] = array(
				'id' => $this->modifier->id,
				'email' => $this->modifier->email,
				'name' => $this->modifier->name,
				'username' => $this->modifier->username
			);
		}

		// ** If I'm the author, but someone else modifies my article ** //

		// If the article has no author, then go out
		if ($this->author->id == 0) {
			return $users_to_send_helper;
		}

		// If the author should be notfied only for allowed modifiers
		if ($this->rule->author_foranyuserchanges == '0' && !$this->_checkAllowed($this->modifier, $paramName = 'allowuser')) {
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
		if ($nauthor == '5') {
			$users_to_send_helper[] = array(
				'id' => $this->author->id,
				'email' => $this->author->email,
				'name' => $this->author->name,
				'username' => $this->author->username
			);

			return $users_to_send_helper;
		}

		while (true) {
			// If never to notify author
			if ($nauthor == '0') {
				break;
			}

			// If notify on `publish only` or on `unpublish only`, but the state was not changed
			if (($nauthor == '1' || $nauthor == '2')
				&& ($this->publish_state_change == 'nochange' || $this->publish_state_change == 'not determined')
			) {
				break;
			}

			// If notify on `publish or on unpublish` , but the state was not changed
			if ($nauthor == '6'  && ($this->publish_state_change == 'nochange' || $this->publish_state_change == 'not determined')) {
				break;
			}

			// If article is unpublished but is set to notify only in published articles
			if ($this->contentItem->state == '0' && $nauthor == '3') {
				break;
			}

			// If article is published but is set to notify only in unpublished articles
			if ($this->contentItem->state == '1' && $nauthor == '4') {
				break;
			}

			// If notify on `on publish or unpublish`, but the acion is not neiher published or unpublished
			if ($nauthor == '6' && !($this->publish_state_change == 'unpublish' || $this->publish_state_change == 'publish')) {
				break;
			}

			// If notify on `on publish only`, but the acion is not published
			if ($nauthor == '1' && !($this->publish_state_change == 'publish')) {
				break;
			}

			// If notify on `on unpublish only`, but the acion is not unpublished
			if ($nauthor == '2' && !($this->publish_state_change == 'unpublish')) {
				break;
			}

			// Add author to the list of receivers
			$users_to_send_helper[] = array('id' => $this->author->id, 'email' => $this->author->email);
			break;
		}

		return $users_to_send_helper;
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
	public function _checkAllowed(&$object, $paramName, $fieldNamePrefix = 'ausers')
	{
		$debug = true;
		$debug = false;

		if (empty($this->task)) {
			$this->task = '';
		}

		$className = get_class($object);

		if ($debug) {
			dumpMessage('<b>' . __FUNCTION__ . '</b>');
			dumpMessage('<b>' . $className . '</b>');
		}

		if (!empty($this->task) && $this->task == 'saveItem') {
			$this->_debug(' > <b>' . $className . '</b>');

			if (in_array($className, ['JUser', 'Joomla\CMS\User\User'])) {
				$selectionDebugTextGroups = '<i>user groups</i>';
				$selectionDebugTextSpecific = '<i>specific users</i>';
			} else {
				$selectionDebugTextGroups = '<i>categories</i>';
				$selectionDebugTextSpecific = '<i>specific content items</i>';
			}
		}

		if (in_array($className, ['JUser', 'Joomla\CMS\User\User']) && !empty($this->rule)) {
			foreach ($this->rule->usersAddedByEmail as $user) {
				if ($user->id == $object->id) {
					return true;
				}
			}
		}

		if (!in_array($className, ['JUser', 'Joomla\CMS\User\User']) && empty($object->id)) {
			$msg = '';

			if ($debug) {
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

		if (!in_array($className, ['JUser', 'Joomla\CMS\User\User']) && $this->task == 'saveItem') {
			$this->rule->content_language = (array) $this->rule->content_language;

			if (empty($this->rule->content_language) || in_array('always', $this->rule->content_language)) {
				// Do nothing
			} else {
				if (!in_array($object->language, $this->rule->content_language)) {
					return false;
				}
			}
		}

		if ($debug) {
			dumpMessage('here 1');
		}

		$groupName = $fieldNamePrefix . '_' . $paramName . 'groups';
		$itemName = $fieldNamePrefix . '_' . $paramName . 's';
		$onGroupLevels = $this->_getP($groupName, $fieldNamePrefix);
		$onItems = $this->_getP($itemName, $fieldNamePrefix);

		switch ($onGroupLevels) {
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

		switch ($onItems) {
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

		if ($debug) {
			dump($onGroupLevels, $groupName);
			dump($onItems, $itemName);
		}

		if (!empty($this->task) && $this->task == 'saveItem') {
			$this->_debug(' > ' . $selectionDebugTextGroups . ' selection', false, $onGroupLevels);
			$this->_debug(' > Specific ' . $selectionDebugTextSpecific . ' selection', false, $onItems);
		}

		// Allowed for all
		if ($onGroupLevels == 'all' && $onItems == 'all') {
			if (!empty($this->task) && $this->task == 'saveItem') {
				$this->_debug(' > Always allowed. PASSED');
			}

			return true;
		}

		if ($debug) {
			dumpMessage('here 2');
		}
		// Get which group the user belongs to, or which category the user belongs to
		switch ($className) {
				// If means &object is user, not article
			case "JUser":
			case "Joomla\CMS\User\User":
				$object->temp_gid = $object->get('groups');

				if ($object->temp_gid === null) {
					$table   = JUser::getTable();
					$table->load($object->id);
					$object->temp_gid = $table->groups;
				}

				if (empty($object->temp_gid)) {
					$object->temp_gid = array($object->gid);
				}
				break;

				// If means &object is article, not user
			default:
				$object->temp_gid = (array) $object->catid;
				break;
		}

		if (!empty($this->task) && $this->task == 'saveItem') {
			$this->_debug(' > Current obect ' . $selectionDebugTextGroups . ' (ids)', false, $object->temp_gid);
		}

		// If not all grouplevels allowed then check if current user is allowed
		$isOk = false;

		$groupToBeIncluded = false;
		$groupToBeExcluded = false;

		if ($onGroupLevels != 'all') {
			// Get user groups/categories to be included/excluded
			$GroupLevels = $this->_getP($groupName . 'selection', $fieldNamePrefix);

			if (!is_array($GroupLevels)) {
				$GroupLevels = explode(',', $GroupLevels);
			}

			if (!empty($this->task) && $this->task == 'saveItem') {
				$this->_debug(' > ' . $selectionDebugTextGroups . ' included/excluded', false, $GroupLevels);
			}

			// Check only categories, as there are no sections
			$gid_in_array = false;

			foreach ($object->temp_gid as $gid) {
				if (in_array($gid, $GroupLevels)) {
					$gid_in_array = true;
					break;
				}
			}

			if ($onGroupLevels == 'include' && $gid_in_array) {
				$groupToBeIncluded = true;

				if (!empty($this->task) && $this->task == 'saveItem') {
					$this->_debug(' > Is allowed based on ' . $selectionDebugTextGroups . ' YES');
				}
			} elseif ($onGroupLevels == 'exclude' && $gid_in_array) {
				$groupToBeExcluded = true;

				if (!empty($this->task) && $this->task == 'saveItem') {
					$this->_debug(' > Is NOT allowed based on ' . $selectionDebugTextGroups . ' YES');
				}
			}
		}

		// ~ $isOk = false;
		$forceInclude = false;
		$forceExclude = false;

		// If not all user allowed then check if current user is allowed
		if ($onItems != 'all') {
			$Items = $this->_getP($itemName . 'selection', $fieldNamePrefix);

			if (!is_array($Items)) {
				$Items = explode(',', $Items);
			}

			$item_in_array = in_array($object->id, $Items);

			if (!empty($this->task) && $this->task == 'saveItem') {
				$this->_debug(' > ' . $selectionDebugTextSpecific . ' included/excluded', false, $Items);
			}

			if ($onItems == 'include' && $item_in_array) {
				$forceInclude = true;

				if (!empty($this->task) && $this->task == 'saveItem') {
					$this->_debug(' > Is FORCED to be INCLUDED based on ' . $selectionDebugTextSpecific . '');
				}

				return true;
			} elseif ($onItems == 'exclude' && $item_in_array) {
				$forceExclude = true;

				if (!empty($this->task) && $this->task == 'saveItem') {
					$this->_debug(' > Is FORCED to be EXCLUDED based on ' . $selectionDebugTextSpecific . '');
				}

				return false;
			}
		}

		if ($debug) {
			dumpMessage('here 3');
		}

		if (!empty($this->task) && $this->task == 'saveItem') {
			$this->_debug(' > Is ALLOWED based on ' . $selectionDebugTextSpecific . ' YES');
		}

		$itemAllowed = true;

		if ($groupToBeIncluded) {
			if (!empty($this->task) && $this->task == 'saveItem') {
				$this->_debug(' > Object belongs to included ' . $selectionDebugTextGroups . '. CHECK PASSED');
			}

			return true;
		}

		if ($debug) {
			dumpMessage('here 4');
		}

		if ($groupToBeExcluded) {
			if (!empty($this->task) && $this->task == 'saveItem') {
				$this->_debug(' > Object belongs to excluded ' . $selectionDebugTextGroups . '. CHECK FAILED');
			}

			return false;
		}

		if ($debug) {
			dumpMessage('here 5');
		}

		if ($onGroupLevels == 'exclude' && !$groupToBeExcluded) {
			if (!empty($this->task) && $this->task == 'saveItem') {
				$this->_debug(' > Object doesn\'t belong to excluded ' . $selectionDebugTextGroups . '. CHECK PASSED');
			}

			return true;
		}

		if ($debug) {
			dumpMessage('here 6');
		}

		if (!empty($this->task) && $this->task == 'saveItem') {
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
	protected function _remove_mails($Users_to_send)
	{
		$Users_Exclude_emails = $this->rule->ausers_excludeusers;
		$Users_Exclude_emails = explode(PHP_EOL, $Users_Exclude_emails);
		$Users_Exclude_emails = array_map('trim', $Users_Exclude_emails);

		foreach ($Users_Exclude_emails as $cur_email) {
			$cur_email = JString::trim($cur_email);

			if ($cur_email == "") {
				continue;
			}

			foreach ($Users_to_send as $v => $k) {
				if ($k['email'] == $cur_email) {
					unset($Users_to_send[$v]);
					break;
				}
			}
		}

		return $Users_to_send;
	}

	/**
	 * Save rule from hash
	 *
	 * @return   void
	 */
	private function _updateRulesIfHashSaved()
	{
		$debug = true;
		$debug = false;

		// Get extension table class
		$extensionTable = JTable::getInstance('extension');

		$pluginId = $extensionTable->find(array('element' => $this->plg_name, 'type' => 'plugin'));
		$extensionTable->load($pluginId);

		$group = $this->params->get('{notificationgroup');

		if ($debug) {
			echo '<pre style="float:right;width:25%;margin:0;background:#efffef;position: absolute; top:0;right: 0%"> Line: '
				. __LINE__ . '  BEFORE UPDATES' . PHP_EOL;
			var_dump($group);
			echo PHP_EOL . '</pre>' . PHP_EOL;
		}

		$json_templates = $group->use_json_template;

		if ($debug) {
			echo '<pre style="float:right;width:25%;margin:0;background:#efefff;position: absolute; top:0;right: 75%"> Line: ' . __LINE__ . ' ' . PHP_EOL;
			var_dump($json_templates);
			echo PHP_EOL . '</pre>' . PHP_EOL;
		}

		$rules_to_update = array();

		foreach ($json_templates as $k => $v) {
			if ($v == 'variablefield::{notificationgroup') {
				continue;
			}

			if (empty($v)) {
				continue;
			}

			if ($decoded = json_decode(base64_decode($v))) {
				$rules_to_update[$k] = $decoded;
			} else {
				$hash_srip = substr($v, 0, 20) . ' ......... ' . substr($v, -20);

				JFactory::getApplication()->enqueueMessage(
					$this->plg_name . ": "
						. JText::_('PLG_SYSTEM_NOTIFICATIONARY_COULD_NOT_APPLY_CONFIGURATION_HASH')
						. '<i>' . $hash_srip . '</i>',
					'error'
				);
			}

			$json_templates[$k] = null;
		}

		$group->use_json_template = $json_templates;

		if (empty($rules_to_update)) {
			return;
		}

		if ($debug) {
			echo '<pre style="float:right;width:25%;margin:0;background:#ffefef;position: absolute; top:0;right: 50%"> Line: ' . __LINE__ . ' HASH ' . PHP_EOL;
			var_dump($rules_to_update);
			echo PHP_EOL . '</pre>' . PHP_EOL;
		}

		foreach ($rules_to_update as $rule_index => $rules) {
			foreach ($rules as $key => $array) {
				if ($key == '__ruleUniqID') {
					continue;
				}
				// ~ var_dump($array);
				if (!is_array($array)) {
					// ~ var_dump($array);
					$array = (array) $array;

					$tmp_array = array();

					foreach ($array as $k => $v) {
						$tmp_array[] = $v;
					}

					$array = $tmp_array;
					unset($tmp_array);

					// ~ var_dump($array);
					// ~ exit;
				}

				if (is_string($array[0])) {
					$group->{$key}[$rule_index] = $array[0];
				} else {
					$tmp_array = array();

					$current_group_index = 0;

					foreach ($group->{$key} as $ke => $va) {
						$tmp_array[$current_group_index][] = $va;

						if ($va[0] == 'variablefield::{notificationgroup') {
							$current_group_index = $current_group_index + 2;
						}
					}

					$tmp_array[$rule_index] = $array;

					if ($debug) {
						echo '<pre> Line: ' . __LINE__ . ' tmp_array ' . PHP_EOL;
						print_r($tmp_array);
						echo PHP_EOL . '</pre><hr>' . PHP_EOL;
					}

					$group->{$key} = array();

					foreach ($tmp_array as $kd => $vd) {
						foreach ($vd as $k => $v) {
							$group->{$key}[] = $v;
						}
					}
				}
			}
		}

		if ($debug) {
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
		if (!$extensionTable->check()) {
			$this->setError($extensionTable->getError());

			// ~ return false;
		}

		if (!$extensionTable->store()) {
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
		if ($userObject->id > 0) {
			if ($md5 != md5($userObject->id . $uniq)) {
				echo $msg = '<b style="color:red">' . JText::sprintf('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBE_FAILED', $user) . '</b>';

				return;
			}
		}

		$excludeUsers = NotificationAryHelper::getRuleOption('ausers_excludeusers', $uniq);
		$excludeUsers = explode(PHP_EOL, $excludeUsers);
		$excludeUsers = array_map('trim', $excludeUsers);
		$msg = '';

		if (!in_array($user, $excludeUsers)) {
			$excludeUsers[] = $user;
			$excludeUsers = array_filter($excludeUsers);
			$excludeUsers = implode(PHP_EOL, $excludeUsers);

			if (!NotificationAryHelper::updateRuleOption('ausers_excludeusers', $excludeUsers, $uniq)) {
				$msg = '<b style="color:red">' . JText::sprintf('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBE_FAILED', $user) . '</b>';
			} else {
				$msg = '<b style="color:green">' . JText::sprintf('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBED', $user) . '</b>';
			}
		} else {
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

		if (is_array($contentItem)) {
			$convertedFromArray = true;
			$return = (object) $contentItem;
		} elseif (is_object($contentItem)) {
			$return = clone $contentItem;
		} else {
			return $contentItem;
		}

		if (isset($return->_contentItemPrepareAlreadyPrepared)) {
			if ($convertedFromArray) {
				$return = (array) $return;
			}

			return $return;
		}

		$return->_contentItemPrepareAlreadyPrepared = true;

		if (property_exists($return, 'state') && $return->state === null) {
			$return->state = 0;
		}

		if (property_exists($return, 'published') && (empty($return->state))) {
			$return->state = $return->published;
		}

		foreach ($this->object_variables_to_replace as $array) {
			if ($array[1] === false && !property_exists($return, $array[0])) {
				$return->{$array[0]} = null;
			} elseif (!property_exists($return, $array[0]) && property_exists($return, $array[1])) {
				$return->{$array[0]} = $return->{$array[1]};
			} elseif (isset($array[2]) && $array[2] && property_exists($return, $array[1])) {
				$return->{$array[0]} = $return->{$array[1]};
			}
		}

		if (!isset($return->id)) {
			if (method_exists($contentItem, 'get')) {
				$tbl_key = $contentItem->get('_tbl_key');

				if (!empty($tbl_key)) {
					$return->id = $contentItem->{$tbl_key};
				}
			}
		}

		if ($convertedFromArray) {
			$return = (array) $return;
		}

		$session = JFactory::getSession();
		$CustomReplacement = $session->get('CustomReplacement', null, $this->plg_name);

		if (isset($CustomReplacement['context'])) {
			if (empty($contentItem->introtext)) {
				$return->introtext = isset($CustomReplacement['introtext']) ? $CustomReplacement['introtext'] : null;
			}

			if (empty($contentItem->fulltext)) {
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
		while (true) {
			// When editing an article at first (not after page reload), I can meet such ""
			$tmp = explode('.', $context);

			if (count($tmp) == 3 && $tmp[2] == 'filter') {
				$context = $tmp[0] . '.' . $tmp[1];
				break;
			}

			if ($contentItem && !empty($contentItem->extension) && $context == 'com_categories.category') {
				$context = $contentItem->extension . '.category';
			}

			if (strpos($context, 'com_categories.categorycom_') === 0) {
				return str_replace('com_categories.category', '', $context) . '.category';
			}

			$session = JFactory::getSession();
			$formContext = $session->get('FormContext', null, $this->plg_name);

			// When editing an article at first (not after page reload), I can meet such ""
			$tmp = explode('.', $formContext);
			$flag = false;

			if (!isset($tmp[2])) {
				$flag = true;
			}

			if (isset($tmp[2]) && $tmp[2] != 'filter') {
				$flag = true;
			}

			if ($formContext && $context !== $formContext && $flag) {
				$formContext = explode('.', $formContext);
				$currentContext = explode('.', $context);

				if ($currentContext[0] == $formContext[0]) {
					$context = $formContext[0] . '.' . $formContext[1];
				}
			}

			break;
		}

		if (isset($this->context_aliases[$context])) {
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
	protected function _debug($msg, $newLine = true, $var = 'not set')
	{
		if (!$this->paramGet('debug')) {
			return;
		}

		if ($var === 0) {
			$var = 'NO';
		}

		if ($var === 1) {
			$var = 'YES';
		}

		if (function_exists('dump') && function_exists('dumpMessage')) {
			if ($var !== 'not set') {
				dump($var, $msg);
			} else {
				dumpMessage($msg);
			}
		} else {
			$out_msg = array();
			$out_msg[] = $msg;

			if ($var !== 'not set') {
				if (is_array($var) || is_object($var)) {
					$out_msg[] = '<pre>';
					$out_msg[] = print_r($var, true);
					$out_msgmsg[] = '</pre>';
				} else {
					$out_msg[] = ' | ' . $var;
				}
			}

			$msg = implode(PHP_EOL, $out_msg);

			if ($newLine) {
				$msg = '<br>' . PHP_EOL . $msg;
			}

			JFactory::getApplication()->enqueueMessage($msg, 'notice');
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
		if (!($form instanceof JForm)) {
			$this->_subject->setError('JERROR_NOT_A_FORM');

			return false;
		}

		// Check we are manipulating a valid form.
		$name = $form->getName();

		$app = JFactory::getApplication();

		if (!in_array($name, array('com_admin.profile', 'com_users.user', 'com_users.profile', "com_users.users.default.filter"))) {
			return true;
		}

		// Pass the plugin object to be available in the field to have plugin params parsed there
		$app->set($this->plg_full_name, $this);

		if ($name == "com_users.users.default.filter") {
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

		if (empty($userID)) {
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
}
