<?php
/**
 * A plugin which sends notifications when an article is added or modified at a Joomla web-site
 *
 * @package     NotificationAry
 *
 * @author      Gruz <arygroup@gmail.com>
 * @copyright   Copyleft (є) 2016 - All rights reversed
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace NotificationAry;

use Joomla\String\StringHelper;

// No direct access
defined('_JEXEC') or die;

\JLoader::registerNamespace('NotificationAry', __DIR__, false, false, 'psr4');

jimport('gjfields.gjfields');
jimport('gjfields.helper.plugin');

// ~ jimport('joomla.filesystem.folder');

// ~ jimport('joomla.plugin.plugin');
// ~ jimport('joomla.filesystem.file');

$latestGjfieldsNeededVersion = '1.2.0';
$errorMsg                    = 'Install the latest GJFields plugin version <span style="color:black;">'
	. __FILE__ . '</span>: <a href="http://www.gruz.org.ua/en/extensions/gjfields-sefl-reproducing-joomla-jform-fields.html">GJFields</a>';

$isOk = true;

while (true)
{
	$isOk = false;

	if (!class_exists('\JPluginGJFields'))
	{
		$errorMsg = 'Strange, but missing GJFields library for <span style="color:black;">'
			. __FILE__ . '</span><br> The library should be installed together with the extension... Anyway, reinstall it:
			<a href="http://www.gruz.org.ua/en/extensions/gjfields-sefl-reproducing-joomla-jform-fields.html">GJFields</a>';

		break;
	}

	$gjfieldsVersion = file_get_contents(JPATH_ROOT . '/libraries/gjfields/gjfields.xml');
	preg_match('~<version>(.*)</version>~Ui', $gjfieldsVersion, $gjfieldsVersion);
	$gjfieldsVersion = $gjfieldsVersion[1];

	if (version_compare($gjfieldsVersion, $latestGjfieldsNeededVersion, '<'))
	{
		break;
	}

	$isOk = true;

	break;
}

if (!$isOk)
{
	\JFactory::getApplication()->enqueueMessage($errorMsg, 'error');
}
else
{
	$comPath = JPATH_SITE . '/components/com_content/';

	if (!class_exists('ContentRouter'))
	{
		require_once $comPath . 'router.php';
	}

	if (!class_exists('ContentHelperRoute'))
	{
		require_once $comPath . 'helpers/route.php';
	}

	/**
	 * Plugin code
	 *
	 * @author  Gruz <arygroup@gmail.com>
	 * @since   0.0.1
	 */
	class PlgSystemNotificationaryCore extends \JPluginGJFields
	{
		use Traits\Ajax;
		use Traits\Attachments;
		use Traits\BackendInterfaceHelper;
		use Traits\BuildLinks;
		use Traits\BuildMail;
		use Traits\Check;
		use Traits\Debug;
		use Traits\Extensions\Zoo;
		use Traits\JoomlaExtensionsHadnling;
		use Traits\MailSend;
		use Traits\Main;
		use Traits\Normalization;
		use Traits\ParamsHandling;
		use Traits\PrepareUsersToNotify;
		use Traits\Properties;
		use Traits\SmallFunctions;
		use Traits\Subscribe;


		/**
		 * Constructor.
		 *
		 * @param   object  $subject  The object to observe
		 * @param   array   $config   An optional associative array of configuration settings.
		 *
		 */
		public function __construct(&$subject, $config)
		{
			\JLoader::registerNamespace('Html2Text', self::$helpersFolder, false, false, 'psr4');
			$jinput = \JFactory::getApplication()->input;

			if ($jinput->get('option', null) == 'com_dump')
			{
				return;
			}

			parent::__construct($subject, $config);

			// Handle generated in gjfiles name in snake_case to follow Joomla coding standards camelCase
			$mapPlgNamings = [
				'plg_name',
				'plg_type',
				'plg_full_name',
				'plg_path_relative',
				'plg_path' ,
			];

			foreach ($mapPlgNamings as $oldKey) {
				$newKey = lcfirst(str_replace('_', '', ucwords($oldKey, '_')));
				$this->$newKey = $this->$oldKey;
			}

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

				if (self::isFirstRun('userGenerator'))
				{
					self::userGenerator($usernum, $usergroups);
				}

				break;
			}
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
			$jinput = \JFactory::getApplication()->input;

			if ($jinput->get('option', null) == 'com_dump')
			{
				return true;
			}

			$this->prepareParams();

			$context = $this->contextAliasReplace($context);

			if (!in_array($context, $this->allowedContexts))
			{
				return true;
			}

			/** ##mygruz20180313030701 {
			if ($context == 'com_categories.category')
			{
				$context .= $jinput->get('extension', null);
			}
			It was:
			It became: */
			/** ##mygruz20180313030701 } */

			$this->onContentChangeStateFired = true;

			$contentItem = $this->getContentItemTable($context);

			if (!$contentItem)
			{
				return true;
			}

			foreach ($pks as $id)
			{
				$contentItem->load($id);
				$contentItem->{'modified_by'} = \JFactory::getUser()->id;
				$this->previousState          = 'not determined';
				$this->onContentAfterSave($context, $contentItem, false);
			}

			return true;
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

			$jinput = \JFactory::getApplication()->input;

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

			$session           = \JFactory::getSession();
			$CustomReplacement = $session->get('CustomReplacement', null, $this->plgName);

			switch ($context)
			{
				case $CustomReplacement['context']:
					$this->previousArticle = $CustomReplacement['previous_item'];
					$this->previousState   = $CustomReplacement['previous_state'];

					break;
				case 'jevents.edit.icalevent':
					$dataModel             = new JEventsDataModel;
					$this->previousArticle = $dataModel;
					$jevent                = $dataModel->queryModel->getEventById(intval($this->contentItem->id), 1, "icaldb");

					if (!empty($jevent))
					{
						$this->previousArticle = $jevent;
					}

					break;
				default :

					// $this->previousArticle = \JTable::getInstance('content');
					// $this->previousArticle = $this->getContentItemTable($context);
					$this->previousArticle = clone $contentItem;
					$this->previousArticle->reset();
					$this->previousArticle->load($contentItem->id);
					$this->previousState = $this->previousArticle->state;

					break;
			}

			$this->previousArticle = $this->_contentItemPrepare($this->previousArticle);

			$confObject = \JFactory::getApplication();
			$tmpPath    = $confObject->getCfg('tmp_path');

			foreach ($this->preparePreviousVersionsFlag as $k => $v)
			{
				$this->attachments[$v] = $tmpPath . '/prev_version_id_' . $this->previousArticle->id . '_' . uniqid() . '.' . $v;

				switch ($v)
				{
					case 'html':
					case 'txt':
						$text = '';
						$text .= '<h1>' . $this->previousArticle->title . '</h1>' . PHP_EOL;

						if (!empty($this->previousArticle->introtext))
						{
							$text .= '<br />' . $this->previousArticle->introtext . PHP_EOL;
						}

						if (!empty($this->previousArticle->fulltext))
						{
							$text .= '<hr id="system-readmore" />' . PHP_EOL . PHP_EOL . $this->previousArticle->fulltext;
						}

						if ($v == 'txt')
						{
							if (!class_exists('Html2Text'))
							{
								require_once self::$helpersFolder . '/Html2Text.php';
							}

							// Instantiate a new instance of the class. Passing the string
							// variable automatically loads the HTML for you.
							$h2t        = new Html2Text\Html2Text($text, array('show_img_link' => 'yes'));
							$h2t->width = 120;

							// Simply call the get_text() method for the class to convert
							// the HTML to the plain text. Store it into the variable.
							$text = $h2t->get_text();
							unset($h2t);
						}

						break;
					case 'sql':
						$db                = \JFactory::getDBO();
						$empty_contentItem = clone $this->previousArticle;
						$empty_contentItem->reset();

						// $empty_contentItem = $this->getContentItemTable($context);
						$tablename = str_replace('#__', $db->getPrefix(), $empty_contentItem->get('_tbl'));
						$text      = 'UPDATE ' . $tablename . ' SET ';
						$parts     = array();

						foreach ($this->previousArticle as $field => $value)
						{
							if (is_string($value) && property_exists($empty_contentItem, $field))
							{
								$parts[] = $db->quoteName($field) . '=' . $db->quote($value);
							}
						}

						$text .= implode(',', $parts);
						$text .= ' WHERE ' . $db->quoteName('id') . '=' . $db->quote($this->previousArticle->id);

						break;
					default :
						$this->attachments[$v] = null;

						break;
				}

				if (!empty($this->attachments[$v]))
				{
					\JFile::write($this->attachments[$v], $text);
				}
			}

			$this->noDiffFound = false;

			foreach ($this->pparams as $ruleNumber => $rule)
			{
				// Prepare global list of DIFFs to be generated, stored in $this->DIFFsToBePreparedGlobally {
				// If all possible DIFFs are already set to be generated, then don't check, else go:
				if (count($this->availableDIFFTypes) > count($this->DIFFsToBePreparedGlobally))
				{
					if (isset($rule->attachdiffinfo))
					{
						foreach ($rule->attachdiffinfo as $k => $v)
						{
							$this->DIFFsToBePreparedGlobally[$v] = $v;
						}
					}

					if ($rule->messagebodysource == 'hardcoded')
					{
						if ($rule->emailformat == 'plaintext' && $rule->includediffinfo_text != 'none')
						{
							$this->DIFFsToBePreparedGlobally[$rule->includediffinfo_text] = $rule->includediffinfo_text;
						}
						elseif ($rule->emailformat == 'html' && $rule->includediffinfo_html != 'none')
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

			if (!empty($this->DIFFsToBePreparedGlobally))
			{
				if (!class_exists('Diff'))
				{
					require_once self::$helpersFolder . '/Diff.php';
				}

				$options = array(
					// 'ignoreWhitespace' => true,
					// 'ignoreCase' => true,

					// Determines how much of not changed text to show, 1 means only close to the change
					'context' => 1,
				);

				$old       = array();
				$old[]     = '<h1>' . $this->previousArticle->title . '</h1>';
				$introtext = preg_split("/\r\n|\n|\r/", StringHelper::trim($this->previousArticle->introtext));
				$old       = array_merge($old, $introtext);

				if (!empty($this->previousArticle->fulltext))
				{
					$old[]    = '<hr id="system-readmore" />';
					$fulltext = preg_split("/\r\n|\n|\r/", StringHelper::trim($this->previousArticle->fulltext));
					$old      = array_merge($old, $fulltext);
				}

				$new       = array();
				$new[]     = '<h1>' . $this->contentItem->title . '</h1>';
				$introtext = preg_split("/\r\n|\n|\r/", StringHelper::trim($this->contentItem->introtext));

				$new = array_merge($new, $introtext);

				if (!empty($this->contentItem->fulltext))
				{
					$new[]    = '<hr id="system-readmore" />';
					$fulltext = preg_split("/\r\n|\n|\r/", StringHelper::trim($this->contentItem->fulltext));
					$new      = array_merge($new, $fulltext);
				}

				// Initialize the diff class
				$diff = new \Diff($old, $new, $options);
				$css  = \JFile::read(self::$helpersFolder . '/Diff/styles.css');
			}

			$path = $tmpPath . '/diff_id_' . $this->previousArticle->id . '_' . uniqid();

			foreach ($this->DIFFsToBePreparedGlobally as $k => $v)
			{
				$useCSS = false;

				switch ($v)
				{
					case 'Text/Unified':
					case 'Text/Context':
						$fileNamePart          = explode('/', $v);
						$this->attachments[$v] = $path . '_' . $fileNamePart[1] . '.txt';

						break;
					case 'Html/SideBySide':
					case 'Html/Inline':
						$useCSS                = true;
						$fileNamePart          = explode('/', $v);
						$this->attachments[$v] = $path . '_' . $fileNamePart[1] . '.html';

						break;
					default :
						$this->attachments[$v] = null;

						break;
				}

				$className = 'Diff_Renderer_' . str_replace('/', '_', $v);

				if (!class_exists($className))
				{
					require_once self::$helpersFolder . '/Diff/Renderer/' . $v . '.php';
				}

				// Generate a side by side diff
				$renderer = new $className;
				$text     = $diff->Render($renderer);

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
					\JFile::write($this->attachments[$v], $text);
				}
			}

			$session = \JFactory::getSession();

			if (!empty($this->attachments))
			{
				$session->set('Attachments', $this->attachments, $this->plgName);
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
			// dump($contentItem, 'context = ' . $context . ' | isNew = ' . $isNew);
			// ~ dumpTrace();
			$jinput = \JFactory::getApplication()->input;

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

			$context = $this->contextAliasReplace($context, $contentItem);

			$this->_setContext($context);

			if ($debug)
			{
				dump($this->context, '$this->context');
			}

			// Show debug information
			if ($this->paramGet('showContext'))
			{
				$jtable_class = get_class($contentItem);
				$msg          = array();
				$msg[]        = '</p><div class="alert-message row-fluid">';
				$msg[]        = '<p><a href="http://static.xscreenshot.com/2016/05/10/00/screen_31582c1a5da14780c3ab5f5181a4f46f" target="_blank"><small><b>'
					. $this->plgName . '</b> ' . \JText::_('JTOOLBAR_DISABLE') . ' ' . \JText::_('NOTICE') . '</small></a></p>';
				$msg[] = '<b>Context:</b> ' . $context;
				$msg[] = '<br><b>Item table name:</b> ' . trim($jtable_class);

				$app   = \JFactory::getApplication();
				$msg[] = '
				<br/><button type="button" class="btn btn-warning btn-small object_values"  ><i class="icon-plus"></i></button><br/>
				<small class="object_values hide">
					<pre class="span6">
						<b>----' . $jtable_class . '----</b><br/>';

				self::buildExampleObject($contentItem, $msg);

				$user = \JFactory::getUser();

				$msg[] = '
					</pre>';
				$msg[] = '
					<pre class="span6"><b>----' . get_class($user) . '----</b><br/>';

				self::buildExampleUser($user, $msg);

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
					$app         = \JFactory::getApplication();
					$scriptAdded = $app->get('##mygruz20160216061544', false);

					if (!$scriptAdded)
					{
						$document = \JFactory::getDocument();

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

				\JFactory::getApplication()->enqueueMessage($msg . $js, 'notice');
			}

			if (!$this->_isContentEditPage($context))
			{
				return;
			}

			// Blocks executing the plugin if notification switch is set to no
			if (!$this->onContentChangeStateFired)
			{
				// Needed for Notification switch in K2 {
				$session = \JFactory::getSession();

				if (!$this->shouldShowSwitchCheckFlag)
				{
					// Is set for onAfterContentSave
					$this->shouldShowSwitchCheckFlag = $session->get('shouldShowSwitchCheckFlagK2Special', false, $this->plgName);

					if ($this->shouldShowSwitchCheckFlag)
					{
						// ~ $jinput = \JFactory::getApplication()->input;
						$jform = $jinput->post->getArray();
					}
				}

				// Clear anyway
				$session->clear('shouldShowSwitchCheckFlagK2Special', $this->plgName);

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

					// Get from \JForm to use if there is no in attribs or params. com_content
					// on saving at FE a New article uses 'params' while everywhere else 'attribs'
					$jform_runnotificationary = $session->get('shouldShowSwitchCheckFlagK2SpecialDefaultValue', false, $this->plgName);

					// Clear anyway
					$session->clear('shouldShowSwitchCheckFlagK2SpecialDefaultValue', $this->plgName);

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

			$rules = $this->leaveOnlyRulesForCurrentItem($context, $this->contentItem, 'saveItem', $isNew);

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
			$config      = \JFactory::getConfig();

			$this->sitename = $config->get('sitename');

			if (trim($this->sitename) == '')
			{
				$this->sitename = \JURI::root();
			}

			$user = \JFactory::getUser();
			$app  = \JFactory::getApplication();

			$ShowSuccessMessage   = $this->paramGet('showsuccessmessage');
			$this->SuccessMessage = '';

			if ($ShowSuccessMessage == 1)
			{
				$this->SuccessMessage = $this->paramGet('successmessage');
			}

			$ShowErrorMessage   = $this->paramGet('showerrormessage');
			$this->ErrorMessage = '';

			if ($ShowErrorMessage == 1)
			{
				$this->ErrorMessage = $this->paramGet('errormessage');
			}

			// Determine actions which has been perfomed
			// Must use the modified $this->contentItem
			if (isset($this->previousState) && $this->previousState == $this->contentItem->state)
			{
				$this->publishStateChange = 'nochange';
			}
			elseif (isset($this->previousState) && $this->previousState != $this->contentItem->state)
			{
				switch ($this->contentItem->state)
				{
					case '1':
						$this->publishStateChange = 'publish';

						break;
					case '0':
						$this->publishStateChange = 'unpublish';

						break;
					case '2':
						$this->publishStateChange = 'archive';

						break;
					case '-2':
						$this->publishStateChange = 'trash';

						break;
				}
			}

			// ~ $this->author = \JFactory::getUser( $contentItem->created_by );
			$this->author = self::getUser($this->contentItem->created_by);

			if ($this->contentItem->{'modified_by'} > 0)
			{
				$this->modifier = self::getUser($this->contentItem->{'modified_by'});
			}
			else
			{
				$this->modifier = \JFactory::getUser();
			}

			$this->isAjax = $this->paramGet('useajax');

			foreach ($rules as $ruleNumber => $rule)
			{
				$this->rule = $rule;

				$Users_to_send = $this->_users_to_send();

				$users_to_send_helper = $this->_addAuthorModifier();
				$Users_to_send        = array_merge($Users_to_send, $users_to_send_helper);
				$Users_to_send        = $this->_remove_mails($Users_to_send);

				if ($this->paramGet('debug') && !$this->isAjax)
				{
					// If jdump extension is installed and enabled
					$debugmsg = 'No messages are sent in the debug mode. You can check the users to be notified.';

					if (function_exists('dump') && function_exists('dumpMessage'))
					{
						dumpMessage($debugmsg);
						dump($Users_to_send, '$Users_to_send');
					}
					else
					{
						$msg   = array();
						$msg[] = '<div style="color:red;">' . $debugmsg . '</div>';
						$msg[] = '<pre>$Users_to_send = ';
						$msg[] = print_r($Users_to_send, true);
						$msg[] = '</pre>';
						$msg   = implode(PHP_EOL, $msg);
						\JFactory::getApplication()->enqueueMessage($msg, 'notice');
					}

					// DO NOT SEND ANY MAILS ON DEBUG
					continue;
				}

				if ($this->isAjax)
				{
					if (!class_exists('fakeMailerClass'))
					{
						require_once self::$helpersFolder . '/fakeMailerClass.php';
					}
				}

				$this->_send_mails($Users_to_send);

				if (!$this->isAjax)
				{
					$canLoginBackend = $user->authorise('core.login.admin');

					/**
					 * Broken email sends
					 *
					 * @var array
					 */
					if (!empty($this->brokenSends) && !empty($this->ErrorMessage))
					{
						// User has back-end access
						if ($canLoginBackend)
						{
							/**
							 * Broken email sends
							 *
							 * @var array
							 */
							$email = " " . \JText::_('PLG_SYSTEM_NOTIFICATIONARY_EMAILS') . implode(" , ", $this->brokenSends);
						}

						$app->enqueueMessage(
							\JText::_(ucfirst($this->plgName)) . ' (File: '. __FILE__ .' , line: ' . __LINE__ . '): ' . \JText::_($this->ErrorMessage) . ' ' . $email,
							'error'
						);
					}
					/**
					 * Broken email sends
					 *
					 * @var array
					 */
					elseif (empty($this->brokenSends) && !empty($this->SuccessMessage))
					{
						if (!empty($Users_to_send))
						{
							$canLoginBackend             = $user->authorise('core.login.admin');
							$successmessagenumberofusers = $this->paramGet('successmessagenumberofusers');
							$msg                         = \JText::_($this->SuccessMessage);

							if ($canLoginBackend && $successmessagenumberofusers > 0)
							{
								$msg = $msg . ' ' . \JText::_('PLG_SYSTEM_NOTIFICATIONARY_USERS_NOTIFIED') . count($Users_to_send);

								// When publishing from list, the message is the same (the same number of users in notified).
								// So if publishing to items and 10 users should be notified per item, the message says 10 mails sent, while 20 is sent.

								// To make the messages be different we add id and title
								$msg .= ' :: ID: <b>' . $this->contentItem->id . '</b> ';

								if (!empty($this->contentItem->title))
								{
									$msg .= \JText::_('PLG_SYSTEM_NOTIFICATIONARY_TITLE') . ' <b>: ' . $this->contentItem->title . '</b> ';
								}
							}

							$app->enqueueMessage($msg);
						}
					}
				}
			}

			if (!$this->isAjax)
			{
				$this->cleanAttachments();
			}
			else
			{
				$session     = \JFactory::getSession();
				$attachments = $session->set('AjaxHash', $this->ajaxHash, $this->plgName);
			}
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
			$app = \JFactory::getApplication();

			$jinput = $app->input;

			if ($jinput->get('option', null) == 'com_dump')
			{
				return;
			}

			// Block JSON response, like there was an incompatibility with RockSprocket
			$format = $jinput->get('format', 'html');

			$session = \JFactory::getSession();

			// Is set in onAfterContentSave
			$ajaxHash = $session->get('AjaxHash', null, $this->plgName);

			if (!empty($ajaxHash))
			{
				$paramsToBePassed = array(
					'ajaxHash'          => $ajaxHash,
					'verbose'           => $this->paramGet('verbose'),
					'showNumberOfUsers' => $this->paramGet('successmessagenumberofusers'),
					'debug'             => $this->paramGet('debug'),
				);

				$paramsToBePassed = base64_encode(serialize($paramsToBePassed));

				// Build remote link
				$url_ajax_plugin = \JRoute::_(
							\JURI::base()
							// It's a must
							. '?option=com_ajax&format=raw'
							. '&group=' . $this->plgType
							. '&plugin=notificationAryRun'
							. '&' . \JSession::getFormToken() . '=1'
							. '&uniq=' . uniqid()
							. '&serialize=' . $paramsToBePassed
					);

				if ($this->paramGet('debug'))
				{
					$url_ajax_plugin .= '&debug=1';
					$app->enqueueMessage('<small>' . 'Ajax URL: ' . $url_ajax_plugin . '</small>', 'notice');
				}

				$doc = \JFactory::getDocument();

				$doc->addScriptOptions($this->plgName, array('ajax_place' => $this->plgFullName));
				$doc->addScriptOptions($this->plgName, array('ajax_url' => $url_ajax_plugin));

				//$doc->addScriptOptions($this->plgName, ['messages' => array('error' => \JText::_('Ajax error')) ]);

				if ($this->paramGet('ajax_allow_to_cancel') && $this->paramGet('ajax_delay') > 0)
				{
					$doc->addScriptOptions($this->plgName, array('start_delay' => ($this->paramGet('ajax_delay') + 1)));
					\JText::script('PLG_SYSTEM_NOTIFICATIONARY_AJAX_TIME_TO_START');
					// ~ $doc->addScriptOptions($this->plgName, array('messages' => array('delay_text' => \JText::_('PLG_SYSTEM_NOTIFICATIONARY_AJAX_TIME_TO_START')) ));
				}

				$SuccessMessage = '';

				if ($this->paramGet('showsuccessmessage'))
				{
					$SuccessMessage .= \JText::_($this->paramGet('successmessage'));
				}

				if ($this->paramGet('successmessagenumberofusers'))
				{
					$SuccessMessage .= ' ' . \JText::_('PLG_SYSTEM_NOTIFICATIONARY_USERS_NOTIFIED');
				}

				$doc->addScriptOptions($this->plgName, array('messages' => array('sent' => $SuccessMessage) ));

				self::addJSorCSS('ajax.js', $this->plgFullName);

				self::addJSorCSS('styles.css', $this->plgFullName);

				if ($this->paramGet('debug'))
				{
					$doc->addScriptOptions($this->plgName, array('debug' => true));
				}
			}
		}

		/**
		 * Adds the notification switch at a content item view and cleans attachments
		 *
		 * @return void
		 */
		public function onAfterRender()
		{
			$jinput = \JFactory::getApplication()->input;

			if ($jinput->get('option', null) == 'com_dump')
			{
				return;
			}

			if ($jinput->get('option', null) == 'com_ajax')
			{
				return;
			}

			self::addUserlistBadges();

			$app = \JFactory::getApplication();

			// Block JSON response, like there was an incompatibility with RockSprocket
			$format = $jinput->get('format', 'html');

			// Add NA menu item to Joomla backend
			if ($app->isAdmin() && $this->paramGet('add_menu_item') && $format == 'html')
			{
				$body = $app->getBody();

				// Get extension table class
				$extensionTable = \JTable::getInstance('extension');

				$pluginId = $extensionTable->find(array('element' => $this->plgName, 'type' => 'plugin'));

				$language = \JFactory::getLanguage();

				// Have to load curren logged in language to show the proper menu item language, not the default backend language
				$language->load($this->plgFullName, $this->plgPath, $language->get('tag'), true);

				$menu = '<li><a class="menu-'
					. $this->plgName . ' " href="index.php?option=com_plugins&task=plugin.edit&extension_id=' . $pluginId . '">'
					. \JText::_($this->plgFullName . '_MENU')
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
				if (count($body) == 2)
				{
					$body = $body[0] . $js . '</body>' . $body[1];
					$app->setBody($body);
				}
			}

			// Output Ajax placeholder if needed {
			$session = \JFactory::getSession();

			// Is set in onAfterContentSave
			$ajaxHash = $session->get('AjaxHash', null, $this->plgName);

			$session->clear('AjaxHash', $this->plgName);

			if (!empty($ajaxHash))
			{
				$placeDebug = '';
				$user        = \JFactory::getUser();

				// Since the _checkAllowed checks the global settings, there is no $this->rule passed and used there
				if ($this->paramGet('ajax_allow_to_cancel') && $this->_checkAllowed($user, $paramName = 'allowuser', $prefix = 'ajax'))
				{
					$placeDebug .= '<button type="button" id="' . $this->plgFullName . '_close">X</button>';
				}

				if ($this->paramGet('debug'))
				{
					// ~ $placeDebug .= '<div style="position:fixed">';
					$placeDebug .= '<a id="clear" class="btn btn-error">Clear</a>';
					$placeDebug .= '<a id="continue" class="btn btn-warning">Continue</a>';

				// ~ $placeDebug .= '</div>';
				}
				else
				{
					$placeDebug .= '<small>';

					if ($this->paramGet('ajax_allow_to_cancel') && $this->_checkAllowed($user, $paramName = 'allowuser', $prefix = 'ajax'))
					{
						$placeDebug .= \JText::_('PLG_SYSTEM_NOTIFICATIONARY_AJAX_TIME_TO_CANCEL');
						$placeDebug .= '. ';
					}

					$placeDebug .= \JText::_('PLG_SYSTEM_NOTIFICATIONARY_AJAX_SENDING_MESSAGES') . '</small>';
				}

				$ajax_place_holder = '<div class="nasplace" >' . $placeDebug . '<div class="nasplaceitself" id="' . $this->plgFullName . '" ></div>';

				$body = $app->getBody();
				$body = str_replace('</body>', $ajax_place_holder . '</body>', $body);
				$body = $app->setBody($body);
			}

			// K2 doesn't run onContentPrepareForm. So we need to imitate it here.
			$option = $jinput->get('option', null);
			$view   = $jinput->get('view', null);

			if ($option == 'com_k2' && $view == 'item')
			{
				// Prepare to imitate onContentPrepareForm {
				$this->prepareParams();
				$context                 = 'com_k2.item';
				$this->allowedContexts[] = $context;
				$this->_setContext($context);

				$this->shouldShowSwitchCheckFlag = false;
				$contentItem                     = $this->getContentItemTable($context);
				$contentItem->load($jinput->get('cid', 0));

				jimport('joomla.form.form');
				$form   = \JForm::getInstance('itemForm', JPATH_ADMINISTRATOR . '/components/com_k2/models/item.xml');
				$values = array('params' => json_decode($contentItem->params));
				$form->bind($values);

				// Prepare to imitate onContentPrepareForm }

				$this->onContentPrepareForm($form, $contentItem);
				$rules = $this->leaveOnlyRulesForCurrentItem($context, $contentItem, 'showSwitch');

				if (empty($rules))
				{
					return;
				}

				$this->shouldShowSwitchCheckFlag = true;

				// Is set for onAfterContentSave as onContentPrepareForm is not run, but this method onAfterRender runs after onContentAfterSave.
				$session->set('shouldShowSwitchCheckFlagK2Special', true, $this->plgName);

				// If the NS should be shown but cannot be shown due to HTML layout problems, then we need to know default value
				$rule = array_pop($rules);

				$session->set('shouldShowSwitchCheckFlagK2SpecialDefaultValue', (bool) $rule->notificationswitchdefault, $this->plgName);
			}

			// Can be set in onContentPrepareForm or onContentAfterSave
			if (empty($this->context))
			{
				return;
			}

			if (!$this->_isContentEditPage($this->context['full']))
			{
				return;
			}

			if (!self::isFirstRun('onAfterRender'))
			{
				return;
			}

			if (!$this->shouldShowSwitchCheckFlag)
			{
				return;
			}

			$body        = $app->getBody();
			$app         = \JFactory::getApplication();
			$checkedyes  = $checkedno  = 'checked="checked"';
			$selectedyes = $selectedno = 'selected="selected"';
			$active_no   = $active_yes   = '';

			if ($this->runnotificationary == 1)
			{
				$checkedno  = '';
				$selectedno = '';

			// $active_yes='active btn-success';
			}
			else
			{
				$checkedyes  = '';
				$selectedyes = '';

				// $active_no=' active btn-danger';
			}

			$CustomReplacement = $session->get('CustomReplacement', null, $this->plgName);

			$replacement_label = '
					<label title="" data-original-title="<strong>' . \JText::_('PLG_SYSTEM_NOTIFICATIONARY_NOTIFY') . '</strong><br />'
					. \JText::_('PLG_SYSTEM_NOTIFICATIONARY_NOTIFY_DESC')
					. '" class="hasTip hasTooltip required" for="jform_runnotificationary" id="jform_attribs_runnotificationary-lbl">'
					. \JText::_('PLG_SYSTEM_NOTIFICATIONARY_NOTIFY') . '</label>';

			if (!empty($CustomReplacement) && $CustomReplacement['context'] == $this->context['full'])
			{
				$possible_tag_ids     = $CustomReplacement['possible_tag_ids'];
				$replacement_fieldset = $CustomReplacement['replacement_fieldset'];

				$replace = array(
					'{{$this->attribsField}}' => $this->attribsField,
					'{{$checkedyes}}'         => $checkedyes,
					'{{$active_yes}}'         => $active_yes,
					'{{$checkedno}}'          => $checkedno,
					'{{$active_no}}'          => $active_no,

				);

				$search               = array_keys($replace);
				$replacement_fieldset = str_replace($search, $replace, $replacement_fieldset);
			}
			else
			{
				$CustomReplacement = array('option' => false);

				if (!$app->isAdmin() && $this->paramGet('replacement_type') === 'simple')
				{
					$replacement_fieldset = '
					<select id="jform_' . $this->attribsField . '_runnotificationary" name="jform[' . $this->attribsField . '][runnotificationary]" class="inputbox">
					<option value="1" ' . $selectedyes . '>' . \JText::_('JYES') . '</option>
					<option value="0" ' . $selectedno . '>' . \JText::_('JNO') . '</option>
					</select>
					';
				}
				else
				{
					$replacement_fieldset = '
						<fieldset id="jform_' . $this->attribsField . '_runnotificationary" class="radio btn-group btn-group-yesno nswitch" >
							<input type="radio" ' . $checkedyes . ' value="1" name="jform[' . $this->attribsField . '][runnotificationary]" id="jform_'
								. $this->attribsField . '_runnotificationary1">
							<label for="jform_' . $this->attribsField . '_runnotificationary1" class="btn ' . $active_yes . '">' . \JText::_('JYES') . '</label>
							<input type="radio" ' . $checkedno . ' value="0" name="jform[' . $this->attribsField . '][runnotificationary]" id="jform_'
								. $this->attribsField . '_runnotificationary0">
							<label for="jform_' . $this->attribsField . '_runnotificationary0" class="btn' . $active_no . '">' . \JText::_('JNO') . '</label>
						</fieldset>
					';
				}

				// $oldFieldsFormat = self::getHTMLElementById($body,'adminformlist','ul','class');

				// NOTE! self::getHTMLElementById doesn't work with non-double tags like <input ... /> .
				$possible_tag_ids = array(

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
					$possible_tag_ids = array(
						array('select', 'access'),
						array('select', 'catid'),
					);
					$replacement_fieldset = '
						<div><fieldset id="jform_' . $this->attribsField . '_runnotificationary" class="radio btn-group btn-group-yesno nswitch" >
							<input type="radio" ' . $checkedyes . ' value="1" name="custom_runnotificationary" id="jform_' . $this->attribsField . '_runnotificationary1">
							<label for="jform_' . $this->attribsField . '_runnotificationary1" class="btn ' . $active_yes . '">' . \JText::_('JYES') . '</label>
							<input type="radio" ' . $checkedno . ' value="0" name="custom_runnotificationary" id="jform_' . $this->attribsField . '_runnotificationary0">
							<label for="jform_' . $this->attribsField . '_runnotificationary0" class="btn' . $active_no . '">' . \JText::_('JNO') . '</label>
						</fieldset>
					';
				}
			}

			$oldFormat = false;

			foreach ($possible_tag_ids as $tag)
			{
				$attribute_name      = isset($tag[2]) ? $tag[2] : 'id';
				$nswitch_placeholder = self::getHTMLElementById($body, $tag[1], $tag[0], $attribute_name);

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

			if (\JFactory::getApplication()->isAdmin() && \JFactory::getApplication()->getTemplate() !== 'isis')
			{
				$this->HTMLtype = 'li';
			}

			// JEvents compatibility
			if ($this->context['full'] == 'jevents.edit.icalevent')
			{
				$replacement = '
				<div class="row">
					<div class="span2 col-sm-2">
						' . $replacement_label . '
					</div>
					<div class="span10 col-sm-10">
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
										. \JText::_('PLG_SYSTEM_NOTIFICATIONARY_NOTIFY')
									. '</strong><br />'
									. \JText::_('PLG_SYSTEM_NOTIFICATIONARY_NOTIFY_DESC') . '" class="hasTooltip required" for="="jform_'
									. $this->attribsField . '_runnotificationary" id="jform_attribs_runnotificationary-lbl">'
								. \JText::_('PLG_SYSTEM_NOTIFICATIONARY_NOTIFY')
							. '</label>';

						$field = '
							<select id="jform_'
								. $this->attribsField . '_runnotificationary" name="jform[' . $this->attribsField . '][runnotificationary]"  size="1" class="inputbox">
									<option value="0" ' . $selectedno . '>' . \JText::_('JNo') . '</option>
									<option value="1" ' . $selectedyes . '>' . \JText::_('JYes') . '</option>
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
				// $nswitch_placeholder = self::getHTMLElementById($body,'jform_catid','select');

				// At least at protostar a tab without name appears aboove the article, I assume is generates because of the NS injected into \JForm.
				// Let's try to remove it
				$hiddenTab = self::getHTMLElementById($body, 'params-basic', $tagname = 'div', $attributeName = 'id');

				$tmp = explode('<div class="control-label"><label id="', $hiddenTab);

				if (empty($hiddenTab) || count($tmp) > 2 || strpos($tmp[1], 'jform_params_runnotificationary-lbl') !== 0)
				{
					// Don't clear
				}
				else
				{
					$hiddenTabNav = '<li><a href="#params-basic" data-toggle="tab"></a></li>';
					$body         = str_replace($hiddenTabNav, '', $body);
					$body         = str_replace($hiddenTab, '', $body);
				}

				$body = str_replace($nswitch_placeholder, $nswitch_placeholder . $replacement, $body);
			}
			elseif ($this->shouldShowSwitchCheckFlag && $app->isAdmin())
			{
				$AdminSwitch_placeholder_label = self::getHTMLElementById(
																					$body, 'jform_' . $this->attribsField . '_runnotificationary-lbl', 'label'
																				);
				$AdminSwitch_placeholder_fieldset = self::getHTMLElementById(
																							$body, 'jform_' . $this->attribsField . '_runnotificationary', 'fieldset'
																						);

				// $nswitch_placeholder = self::getHTMLElementById($body,'jform_catid','select');
				$body = str_replace($AdminSwitch_placeholder_label, '', $body);
				$body = str_replace($AdminSwitch_placeholder_fieldset, '', $body);
				$body = str_replace($nswitch_placeholder, $nswitch_placeholder . $replacement, $body);
			}
			elseif ($app->isAdmin())
			{
				$AdminSwitch_placeholder_label = self::getHTMLElementById(
																					$body, 'jform_' . $this->attribsField . '_runnotificationary-lbl', 'label'
																				);
				$AdminSwitch_placeholder_fieldset = self::getHTMLElementById(
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
		 * Replace plugin code at Frontend
		 *
		 * @param   string  $context   The context of the content being passed to the plugin.
		 * @param   object  $article   The article object
		 * @param   object  $params    The article params
		 * @param   int     $page      Returns int 0 when is called not form an article, and empty when called from an article
		 *
		 * @return   void
		 */
		public function onContentPrepare($context, &$article, &$params, $page = null)
		{
			static $assetsAdded = false;
			$app                = \JFactory::getApplication();

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
						$this->prepareParams();
					}

					$possibleObjectParameters = array('text', 'introtext');

					foreach ($possibleObjectParameters as $param)
					{
						if (isset($article->{$param}))
						{
							$text = self::pluginCodeReplace($this, $article->{$param}, $matches);
						}

						if (isset($text) && $text !== false)
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
		 * @param   \JForm   $form         The form to be altered.
		 * @param   object   $contentItem  The associated data for the form.
		 *
		 * @return  boolean
		 */
		public function onContentPrepareForm($form, $contentItem)
		{
			// ~ dump('onContentPrepareForm','onContentPrepareForm');
			// ~ dumpTrace();

			$this->userProfileFormHandle($form, $contentItem);

			$debug  = true;
			$debug  = false;
			$jinput = \JFactory::getApplication()->input;

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

			if (!self::isFirstRun('onContentPrepareForm'))
			{
				return;
			}

			// Check we are manipulating a valid form.

			if (!$this->checkIsForm($form))
			{
				$this->_subject->setError('JERROR_NOT_A_FORM');

				return false;
			}

			/** ***************************************************************************************************************************** */
			/**  NOTE!!! I must load the form below even if $contentItem is empty, becasue otherwise it doesn't save the notify switch state  */
			/** ***************************************************************************************************************************** */
			if (!empty($this->context))
			{
				$context = $this->context['full'];
			}
			else
			{
				$context = $form->getName();
			}

			$session = \JFactory::getSession();

			if ($debug)
			{
				dump($context, '$context from Form');
			}

			$session->set('FormContext', $context, $this->plgName);
			$context = $this->contextAliasReplace($context);
			$this->_setContext($context);

			if ($debug)
			{
				dump($context, 'here 1 $context');
			}

			if (!$this->_isContentEditPage($context))
			{
				return;
			}

			if ($debug)
			{
				dump('here 2');
			}

			// Specially for JEvents. Here I set data for special JEvents event onEventEdit which is run after onContentPrepare
			if ($context == "jevents.edit.icalevent")
			{
				global  $NotificationAryFirstRunCheck;

				if (empty($this->form))
				{
					$this->form                                           = &$form;
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

			$rules = $this->leaveOnlyRulesForCurrentItem($context, $contentItem, 'showSwitch');

			if (empty($rules))
			{
				return;
			}

			if ($debug)
			{
				dump($rules, '$rules');
				dump('here 3');
			}

			$session = \JFactory::getSession();

			if (empty($contentItem))
			{
				$attribs = $session->get('AttribsField' . $context, 'attribs', $this->plgName);
				$session->clear('AttribsField' . $context, $this->plgName);
			}
			else
			{
				$attribs = 'attribs';

				if (!empty($contentItem) && !isset($contentItem->{$attribs}))
				{
					$attribs = 'params';
				}

				$session->set('AttribsField' . $context, $attribs, $this->plgName);
			}

			// ~ dump($contentItem,'$contentItem');
			$app = \JFactory::getApplication();

			if (!empty($contentItem->$attribs))
			{
				if (!is_array($contentItem->$attribs))
				{
					$contentItem->$attribs = (array) json_decode($contentItem->$attribs);
				}
			}

			$this->runnotificationary = 0;

			if (isset($contentItem->{$attribs}['runnotificationary']))
			{
				$this->runnotificationary = $contentItem->{$attribs}['runnotificationary'];
			}
			else
			{
				// If at lease one active rules has default switch status on
				foreach ($rules as $ruleNumber => $rule)
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
			$this->attribsField              = $attribs;
			$this->shouldShowSwitchCheckFlag = true;

			$CustomReplacement = $session->get('CustomReplacement', null, $this->plgName);

			if (!empty($CustomReplacement) && $CustomReplacement['context'] == $this->context['full'])
			{
				$switch_selector = $CustomReplacement['switch_selector'];
				$form_selector   = $CustomReplacement['form_selector'];
			}
			else
			{
				$switch_selector = "[name=\"jform[" . $attribs . "][runnotificationary]\"]:checked";
				$form_selector   = 'adminForm';
			}

			foreach ($rules as $ruleNumber => $rule)
			{
				if ($rule->notificationswitchaddconfirmation)
				{
					$doc      = \JFactory::getDocument();
					$language = \JFactory::getLanguage();

					// Have to load current logged in user language to show the proper menu item language, not the default backend language
					$language->load($this->plgFullName, $this->plgPath, $language->get('tag'), true);

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
								. \JText::_(
										\JText::sprintf(
											'PLG_SYSTEM_NOTIFICATIONARY_ARE_YOU_SURE',
											'"' . \JText::_('PLG_SYSTEM_NOTIFICATIONARY_NOTIFY') . '"',
											'"' . \JText::_('JNo') . '"'
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
		 * Redirects ajax requests to unsubscribe users
		 *
		 * @return void;
		 */
		public function onAfterRoute()
		{
			$jinput = \JFactory::getApplication()->input;
			$uniq   = $jinput->get('unsubscribe', null);
			$email  = $jinput->get('email', null, 'raw');
			$md5    = $jinput->get('hash', null, 'raw');

			if ($uniq)
			{
				$serialize = (base64_encode(serialize(array('unsubscribe' => $email, 'md5' => $md5))));
				$app	      = \JFactory::getApplication();

				$redirect_url = 'index.php?option=com_ajax&format=raw'
					. '&group=' . $this->plgType
					. '&plugin=notificationAryRun'
					. '&' . \JSession::getFormToken() . '=1'
					. '&uniq=' . uniqid()
					. '&uniq=' . $uniq
					. '&serialize=' . $serialize;

				$app->redirect($redirect_url);
			}
		}

		/**
		 * Using MVC override approach to override com_users to filter users by subscriptions
		 *
		 * @return   void
		 */
		public function onAfterInitialise()
		{
			self::autoOverride($this);

			$this->prepareParams();

			foreach ($this->pparams as $k => $param)
			{
				if (!$param->isenabled)
				{
					continue;
				}

				if ($param->context_or_contenttype == "context" && $param->context == "com_zoo.item" && \JComponentHelper::getComponent('com_zoo', true)->enabled)
				{
					self::loadZoo();

					break;
				}
			}
		}
	}

	// PlgSystemNotificationaryCore }

	\JLoader::register('plgSystemNotificationary', __FILE__);

	// Generate and empty object
	$plgParams = new \JRegistry;

	// Get plugin details
	$plugin = \JPluginHelper::getPlugin('system', 'notificationary');

	// Load params into our params object
	$plgParams->loadString($plugin->params);
	$jinput = \JFactory::getApplication()->input;

	if ($jinput->get('option', null) == 'com_dump')
	{
		return;
	}

	$notificationgroup = $plgParams->get('{notificationgroup');

	// Convert properties to camelCase to follow Joomla coding standards
	$notificationgroup = PlgSystemNotificationaryCore::camelSizeProperties($notificationgroup);

	$customTemplates = [];

	if (!empty($notificationgroup->contextOrContenttype))
	{
		$enabled = $plgParams->get('{notificationgroup')->isenabled;

		foreach ($notificationgroup->contextOrContenttype as $k => $v)
		{
			if ('context' === $v && $enabled[$k] == 1)
			{
				// $customTemplates[] = $plgParams->get('{notificationgroup')->context[$k];
				$customTemplate = $plgParams->get('{notificationgroup')->context[$k];
				$customTemplate = PlgSystemNotificationaryCore::parseManualContextTemplate($customTemplate);


				if (!empty($customTemplate['Context']))
				{
					$customTemplates[$customTemplate['Context']] = $customTemplate;
				}
			}
		}

		if (!isset($predefinedContextTemplates))
		{
			include PlgSystemNotificationaryCore::$predefinedContentsFile;
		}
	}

	$tempAliasFunctions = array();

	foreach ($customTemplates as $context => $array)
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
				$tempAliasFunctions [$array[$functionName]] = 'public ' . $array[$functionName];
			}
			else
			{
				// Prevent error if custom file doesn't exists'
				if (strpos($array[$functionName], '/') === false)
				{
					$tempAliasFunctions [$array[$functionName]] = '
					public function ' . $array[$functionName] . ' ($context, $contentItem, $isNew) {
						return $this->' . $functionName . '($context, $contentItem, $isNew);
					}
					';
				}
			}
		}
	}

	$classDynamic = '
		class plgSystemNotificationary extends NotificationAry\plgSystemNotificationaryCore {
			public function __construct(& $subject, $config) {
				parent::__construct($subject, $config);
			}
			' . implode(PHP_EOL, $tempAliasFunctions) . '
		}';

	eval($classDynamic);
}
