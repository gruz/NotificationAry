<?php
/**
 * NotificationaryCore helper class
 *
 * @package    Notificationary

 * @author     Gruz <arygroup@gmail.com>
 * @copyright  0000 Copyleft (є) 2017 - All rights reversed
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
namespace NotificationAry\HelperClasses\Traits;

use Joomla\CMS\Factory;

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;
use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
// use NotificationAry\HelperClasses\FakeMailerClass;
use Joomla\CMS\Component\ComponentHelper;
use NotificationAry\HelperClasses\NotificationAryHelper;


// No direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Plugin code
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
trait CoreEvents
{
	use onAfterRenderHelper;

	static protected $shouldShowSwitchCheckFlag = false;
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
		$jinput = Factory::getApplication()->input;

		if ($jinput->get('option', null) == 'com_dump') {
			return true;
		}

		$this->_prepareParams();


		$context = $this->_contextAliasReplace($context);

		if (!in_array($context, $this->allowed_contexts)) {
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

		if (!$contentItem) {
			return true;
		}

		foreach ($pks as $id) {
			$contentItem->load($id);
			$contentItem->modified_by = Factory::getUser()->id;
			$this->previous_state = 'not determined';
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

		$jinput = Factory::getApplication()->input;

		if ($jinput->get('option', null) == 'com_dump') {
			return;
		}

		if (!$this->_isContentEditPage($context)) {
			return;
		}

		if ($isNew) {
			return;
		}

		$this->contentItem  = $this->_contentItemPrepare($contentItem);

		$session = Factory::getSession();
		$customReplacement = $session->get('CustomReplacement', ['context' => 'N0Ne'], $this->plg_name);

		switch ($context) {
			case $customReplacement['context']:
				$this->previous_article = $customReplacement['previous_item'];
				$this->previous_state = $customReplacement['previous_state'];
				break;
			case 'jevents.edit.icalevent':
				$dataModel = new \JEventsDataModel;
				$this->previous_article = $dataModel;
				$jevent = $dataModel->queryModel->getEventById(intval($this->contentItem->id), 1, "icaldb");

				if (!empty($jevent)) {
					$this->previous_article = $jevent;
				}

				break;
			case 'com_users.users':
				$this->previous_article = $contentItem;
				$this->previous_state = $contentItem->state;

				break;
			default:

				// $this->previous_article = Table::getInstance('content');
				// $this->previous_article = $this->_getContentItemTable($context);
				$this->previous_article = clone $contentItem;
				$this->previous_article->reset();
				$this->previous_article->load($contentItem->id);
				$this->previous_state = !empty($this->previous_article->state) ? $this->previous_article->state : null;

				break;
		}

		$this->previous_article = $this->_contentItemPrepare($this->previous_article);

		$confObject = Factory::getApplication();
		$tmpPath = $confObject->getCfg('tmp_path');

		foreach ($this->prepare_previous_versions_flag as $k => $v) {
			$this->attachments[$v] = $tmpPath . '/prev_version_id_' . $this->previous_article->id . '_' . uniqid() . '.' . $v;

			switch ($v) {
				case 'html':
				case 'txt':
					$text = '';
					$text .= '<h1>' . $this->previous_article->title . '</h1>' . PHP_EOL;

					if (!empty($this->previous_article->introtext)) {
						$text .= '<br />' . $this->previous_article->introtext . PHP_EOL;
					}

					if (!empty($this->previous_article->fulltext)) {
						$text .= '<hr id="system-readmore" />' . PHP_EOL . PHP_EOL . $this->previous_article->fulltext;
					}

					if ($v == 'txt') {
						if (!class_exists('\Html2Text\Html2Text')) {
							require_once NotificationAry_DIR . '/helpers/Html2Text.php';
						}

						// Instantiate a new instance of the class. Passing the string
						// variable automatically loads the HTML for you.
						$h2t = new \Html2Text\Html2Text($text, ['show_img_link' => 'yes']);
						$h2t->width = 120;

						// Simply call the get_text() method for the class to convert
						// the HTML to the plain text. Store it into the variable.
						$text = $h2t->get_text();
						unset($h2t);
					}

					break;
				case 'sql':
					$db = Factory::getDBO();
					$empty_contentItem = clone $this->previous_article;
					$empty_contentItem->reset();

					// $empty_contentItem = $this->_getContentItemTable($context);
					$tablename = str_replace('#__', $db->getPrefix(), $empty_contentItem->get('_tbl'));
					$text = 'UPDATE ' . $tablename . ' SET ';
					$parts = [];

					foreach ($this->previous_article as $field => $value) {
						if (is_string($value) && property_exists($empty_contentItem, $field)) {
							$parts[] = $db->quoteName($field) . '=' . $db->quote($value);
						}
					}

					$text .= implode(',', $parts);
					$text .= ' WHERE ' . $db->quoteName('id') . '=' . $db->quote($this->previous_article->id);
					break;
				default:
					$this->attachments[$v] = null;
					break;
			}

			if (!empty($this->attachments[$v])) {
				File::write($this->attachments[$v], $text);
			}
		}

		$this->noDiffFound = false;

		foreach ($this->pparams as /* $rule_number => */ $rule) {
			// Prepare global list of DIFFs to be generated, stored in $this->DIFFsToBePreparedGlobally {
			// If all possible DIFFs are already set to be generated, then don't check, else go:
			if (count($this->availableDIFFTypes) > count($this->DIFFsToBePreparedGlobally)) {
				if (isset($rule->attachdiffinfo)) {
					foreach ($rule->attachdiffinfo as $k => $v) {
						$this->DIFFsToBePreparedGlobally[$v] = $v;
					}
				}

				if ($rule->messagebodysource == 'hardcoded') {
					if ($rule->emailformat == 'plaintext' && $rule->includediffinfo_text != 'none') {
						$this->DIFFsToBePreparedGlobally[$rule->includediffinfo_text] = $rule->includediffinfo_text;
					} elseif ($rule->emailformat == 'html' && $rule->includediffinfo_html != 'none') {
						$this->DIFFsToBePreparedGlobally[$rule->includediffinfo_html] = $rule->includediffinfo_html;
					}
				}
				// Add to global needed DIFFs to be prepare the DIFFs, which may occur in custom message body
				elseif ($rule->messagebodysource == 'custom') {
					foreach ($this->availableDIFFTypes as $diffType) {
						if (strpos($rule->messagebodycustom, '%DIFF ' . $diffType . '%') !== false) {
							$this->DIFFsToBePreparedGlobally[$diffType] = $diffType;
						}
					}
				}
			}
		}

		if (!empty($this->DIFFsToBePreparedGlobally)) {
			if (!class_exists('Diff')) {
				require_once NotificationAry_DIR . '/helpers/Diff.php';
			}

			$options = [
				// 'ignoreWhitespace' => true,
				// 'ignoreCase' => true,

				// Determines how much of not changed text to show, 1 means only close to the change
				'context' => 1
			];

			$old = [];
			$old[] = '<h1>' . $this->previous_article->title . '</h1>';
			$introtext = preg_split("/\r\n|\n|\r/", StringHelper::trim($this->previous_article->introtext));
			$old = array_merge($old, $introtext);

			if (!empty($this->previous_article->fulltext)) {
				$old[] = '<hr id="system-readmore" />';
				$fulltext = preg_split("/\r\n|\n|\r/", StringHelper::trim($this->previous_article->fulltext));
				$old = array_merge($old, $fulltext);
			}

			$new = [];
			$new[] = '<h1>' . $this->contentItem->title . '</h1>';
			$introtext = preg_split("/\r\n|\n|\r/", StringHelper::trim($this->contentItem->introtext));

			$new = array_merge($new, $introtext);

			if (!empty($this->contentItem->fulltext)) {
				$new[] = '<hr id="system-readmore" />';
				$fulltext = preg_split("/\r\n|\n|\r/", StringHelper::trim($this->contentItem->fulltext));
				$new = array_merge($new, $fulltext);
			}

			// Initialize the diff class
			$diff = new \Diff($old, $new, $options);
			$css = File::read(NotificationAry_DIR . '/helpers/Diff/styles.css');
		}

		$path = $tmpPath . '/diff_id_' . $this->previous_article->id . '_' . uniqid();

		foreach ($this->DIFFsToBePreparedGlobally as $k => $v) {
			$useCSS = false;

			switch ($v) {
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
				default:
					$this->attachments[$v] = null;
					break;
			}

			$className = 'Diff_Renderer_' . str_replace('/', '_', $v);

			if (!class_exists($className)) {
				require_once NotificationAry_DIR . '/helpers/Diff/Renderer/' . $v . '.php';
			}

			// Generate a side by side diff
			$renderer = new $className;
			$text = $diff->Render($renderer);

			if (empty($text)) {
				unset($this->attachments[$v]);
				$this->noDiffFound = true;
				break;
			}

			$this->diffs[$v] = $text;

			if ($useCSS) {
				$this->diffs[$v] = '<style>' . $css . '</style>' . PHP_EOL . $text;
			}

			if ($useCSS) {
				$text = '<html><head><meta http-equiv="content-type" content="text/html; charset=utf-8" /><style>'
					. $css . '</style></head><body>' . PHP_EOL . $text . '</body></html>';
			}

			if (!empty($this->attachments[$v])) {
				File::write($this->attachments[$v], $text);
			}
		}

		$session = Factory::getSession();

		if (!empty($this->attachments)) {
			$session->set('Attachments', $this->attachments, $this->plg_name);
		}
	}

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
		$jinput = Factory::getApplication()->input;

		if ($jinput->get('option', null) == 'com_dump') {
			return;
		}

		$debug = true;
		$debug = false;

		if ($debug) {
			dumpMessage('<b>' . __FUNCTION__ . '</b>');
			dump($contentItem, 'onContentAfterSave  context = ' . $context);
		}

		$context = $this->_contextAliasReplace($context, $contentItem);

		$this->_setContext($context);

		if ($debug) {
			dump($this->context, '$this->context');
		}

		// Show debug information
		if ($this->paramGet('showContext')) {
			$jtable_class = get_class($contentItem);
			$msg = [];
			$msg[] = '</p><div class="alert-message row-fluid">';
			$msg[] = '<p><small><b>'
				. $this->plg_name . '</b> ' . Text::_('JTOOLBAR_DISABLE') . ' ' . Text::_('NOTICE') . '</small></p>';
			$msg[] = '<b>Context:</b> ' . $context;
			$msg[] = '<br><b>Item table name:</b> ' . trim($jtable_class);

			$app = Factory::getApplication();
			$msg[] = '
			<br/><button type="button" class="btn btn-warning btn-small object_values"  ><i class="icon-plus"></i></button><br/>
			<small class="object_values hide">
				<pre class="span6">
					<b>----' . $jtable_class . '----</b><br/>';

			NotificationAryHelper::buildExampleObject($contentItem, $msg);

			$user = Factory::getUser();

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
			if ($this->paramGet('showContext')) {
				$app = Factory::getApplication();
				$scriptAdded = $app->get('##mygruz20160216061544', false);

				if (!$scriptAdded) {
					$document = Factory::getDocument();

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

			Factory::getApplication()->enqueueMessage($msg . $js, 'notice');
		}

		if (!$this->_isContentEditPage($context)) {
			return;
		}

		// Blocks executing the plugin if notification switch is set to no
		if (!$this->onContentChangeStateFired) {
			// Needed for Notification switch in K2 {
			$session = Factory::getSession();

			if (!self::$shouldShowSwitchCheckFlag) {
				// Is set for onAfterContentSave
				self::$shouldShowSwitchCheckFlag = $session->get('shouldShowSwitchCheckFlagK2Special', false, $this->plg_name);

				if (self::$shouldShowSwitchCheckFlag) {
					// ~ $jinput = Factory::getApplication()->input;
					$jform = $jinput->post->getArray();
				}
			}

			// Clear anyway
			$session->clear('shouldShowSwitchCheckFlagK2Special', $this->plg_name);

			// Needed for Notification switch in K2 }
			if (self::$shouldShowSwitchCheckFlag) {
				$this->_debug('Notification switch check STARTED');

				if ($debug) {
					dump('here 1', '<b>' . __FUNCTION__ . '</b>');
				}

				if (!isset($jform)) {
					$jform = $jinput->get('jform', null, null);
				}

				// Get from Form to use if there is no in attribs or params. com_content
				// on saving at FE a New article uses 'params' while everywhere else 'attribs'
				$jform_runnotificationary = $session->get('shouldShowSwitchCheckFlagK2SpecialDefaultValue', false, $this->plg_name);

				// Clear anyway
				$session->clear('shouldShowSwitchCheckFlagK2SpecialDefaultValue', $this->plg_name);

				if (isset($jform['runnotificationary'])) {
					$jform_runnotificationary = $jform['runnotificationary'];
				}
				if (isset($jform['attribs']['runnotificationary'])) {
					$jform_runnotificationary = $jform['attribs']['runnotificationary'];
				} elseif (isset($jform['params']['runnotificationary'])) {
					$jform_runnotificationary = $jform['params']['runnotificationary'];
				} elseif (isset($jform['com_fields']['runnotificationary'])) {
					$jform_runnotificationary = $jform['com_fields']['runnotificationary'];
				}

				if ($debug) {
					dump($jform_runnotificationary, '$jform_runnotificationary');
				}

				if (!$jform_runnotificationary) {
					return;
				}

				$this->_debug('Notification switch check PASSED', true);
			}
		}

		$this->contentItem = $this->_contentItemPrepare($contentItem);

		if ($debug) {
			dump($this->contentItem, '$this->contentItem = ');
		}

		$this->_debug('Rules which allow this content item STARTED');

		$rules = $this->_leaveOnlyRulesForCurrentItem($context, $this->contentItem, 'saveItem', $isNew);

		$this->task = 'saveItem';

		if ($this->onContentChangeStateFired) {
			foreach ($rules as $kk => $vv) {
				if (!$vv->oncontentchangestate) {
					unset($rules[$kk]);
				}
			}
		}

		if ($debug) {
			dump($rules, 'rules');
		}

		$this->_debug('Rules which allow this content item PASSED', false, $rules);

		if (empty($rules)) {
			$this->_debug('<b style="color:Red;">No rules allow this content item</b>');

			return true;
		}

		if ($debug) {
			dump('here 2', '<b>' . __FUNCTION__ . '</b>');
		}

		$this->isNew = $isNew;
		$config = Factory::getConfig();

		$this->sitename = $config->get('sitename');

		if (trim($this->sitename) == '') {
			$this->sitename = Uri::root();
		}

		$user = Factory::getUser();
		$app = Factory::getApplication();

		$ShowSuccessMessage = $this->paramGet('showsuccessmessage');
		$this->SuccessMessage = '';

		if ($ShowSuccessMessage == 1) {
			$this->SuccessMessage = $this->paramGet('successmessage');
		}

		$ShowErrorMessage = $this->paramGet('showerrormessage');
		$this->ErrorMessage = '';

		if ($ShowErrorMessage == 1) {
			$this->ErrorMessage = $this->paramGet('errormessage');
		}

		// Determine actions which has been perfomed
		// Must use the modified $this->contentItem
		if (isset($this->previous_state) && $this->previous_state == $this->contentItem->state) {
			$this->publish_state_change = 'nochange';
		} elseif (isset($this->previous_state) && $this->previous_state != $this->contentItem->state) {
			switch ($this->contentItem->state) {
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

		// ~ $this->author = Factory::getUser( $contentItem->created_by );
		$this->author = NotificationAryHelper::getUser($this->contentItem->created_by);

		if ($this->contentItem->modified_by > 0) {
			$this->modifier = NotificationAryHelper::getUser($this->contentItem->modified_by);
		} else {
			$this->modifier = Factory::getUser();
		}

		$this->isAjax = $this->paramGet('useajax');

		foreach ($rules as /* $rule_number => */ $rule) {
			$this->rule = $rule;

			$Users_to_send = $this->_users_to_send();

			$users_to_send_helper = $this->_addAuthorModifier();
			$Users_to_send = array_merge($Users_to_send, $users_to_send_helper);
			$Users_to_send = $this->_remove_mails($Users_to_send);

			if ($this->paramGet('debug') && !$this->isAjax) {
				// If jdump extension is installed and enabled
				$debugmsg = 'No messages are sent in the debug mode. You can check the users to be notified.';

				if (function_exists('dump') && function_exists('dumpMessage')) {
					dumpMessage($debugmsg);
					dump($Users_to_send, '$Users_to_send');
				} else {
					$msg = [];
					$msg[] = '<div style="color:red;">' . $debugmsg . '</div>';
					$msg[] = '<pre>$Users_to_send = ';
					$msg[] = print_r($Users_to_send, true);
					$msg[] = '</pre>';
					$msg = implode(PHP_EOL, $msg);
					Factory::getApplication()->enqueueMessage($msg, 'notice');
				}

				// DO NOT SEND ANY MAILS ON DEBUG
				continue;
			}

			$this->_send_mails($Users_to_send);

			if (!$this->isAjax) {
				$canLoginBackend = $user->authorise('core.login.admin');

				if (!empty($this->broken_sends) && !empty($this->ErrorMessage)) {
					// User has back-end access
					if ($canLoginBackend) {
						$email = " " . Text::_('PLG_SYSTEM_NOTIFICATIONARY_EMAILS') . implode(" , ", $this->broken_sends);
					}

					$app->enqueueMessage(
						Text::_(ucfirst($this->plg_name)) . ' (line ' . __LINE__ . '): ' . Text::_($this->ErrorMessage) . ' ' . $email,
						'error'
					);
				} elseif (empty($this->broken_sends) && !empty($this->SuccessMessage)) {
					if (!empty($Users_to_send)) {
						$canLoginBackend = $user->authorise('core.login.admin');
						$successmessagenumberofusers = $this->paramGet('successmessagenumberofusers');
						$msg = Text::_($this->SuccessMessage);

						if ($canLoginBackend && $successmessagenumberofusers > 0) {
							$msg = $msg . ' ' . Text::_('PLG_SYSTEM_NOTIFICATIONARY_USERS_NOTIFIED') . count($Users_to_send);

							// When publishing from list, the message is the same (the same number of users in notified).
							// So if publishing to items and 10 users should be notified per item, the message says 10 mails sent, while 20 is sent.

							// To make the messages be different we add id and title
							$msg .= ' :: ID: <b>' . $this->contentItem->id . '</b> ';

							if (!empty($this->contentItem->title)) {
								$msg .= Text::_('PLG_SYSTEM_NOTIFICATIONARY_TITLE') . ' <b>: ' . $this->contentItem->title . '</b> ';
							}
						}

						$app->enqueueMessage($msg);
					}
				}
			}
		}

		if (!$this->isAjax) {
			$this->_cleanAttachments();
		} else {
			$session = Factory::getSession();
			$attachments = $session->set('AjaxHash', $this->ajaxHash, $this->plg_name);
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
		$app = Factory::getApplication();

		$jinput = $app->input;

		if ($jinput->get('option', null) == 'com_dump') {
			return;
		}

		// Block JSON response, like there was an incompatibility with RockSprocket
		// $format = $jinput->get('format', 'html');

		$session = Factory::getSession();

		// Is set in onAfterContentSave
		$ajaxHash = $session->get('AjaxHash', null, $this->plg_name);

		if (!empty($ajaxHash)) {
			$paramsToBePassed = [
				'ajaxHash' => $ajaxHash,
				'verbose' => $this->paramGet('verbose'),
				'showNumberOfUsers' => $this->paramGet('successmessagenumberofusers'),
				'debug' => $this->paramGet('debug'),
			];

			$paramsToBePassed = base64_encode(serialize($paramsToBePassed));

			// Build remote link
			$url_ajax_plugin = Route::_(
				URI::base()
					// It's a must
					. '?option=com_ajax&format=raw'
					. '&group=' . $this->plg_type
					. '&plugin=notificationAryRun'
					. '&' . Session::getFormToken() . '=1'
					. '&uniq=' . uniqid()
					. '&serialize=' . $paramsToBePassed
			);

			if ($this->paramGet('debug')) {
				$url_ajax_plugin .= '&debug=1';
				$app->enqueueMessage('<small>' . 'Ajax URL: ' . $url_ajax_plugin . '</small>', 'notice');
			}

			$doc = Factory::getDocument();

			$doc->addScriptOptions($this->plg_name, ['ajax_place' => $this->plg_full_name]);
			$doc->addScriptOptions($this->plg_name, ['ajax_url' => $url_ajax_plugin]);

			//$doc->addScriptOptions($this->plg_name, ['messages' => array('error' => Text::_('Ajax error')) ]);

			if ($this->paramGet('ajax_allow_to_cancel') && $this->paramGet('ajax_delay') > 0) {
				$doc->addScriptOptions($this->plg_name, ['start_delay' => ($this->paramGet('ajax_delay') + 1)]);
				Text::script('PLG_SYSTEM_NOTIFICATIONARY_AJAX_TIME_TO_START');
				// ~ $doc->addScriptOptions($this->plg_name, array('messages' => array('delay_text' => Text::_('PLG_SYSTEM_NOTIFICATIONARY_AJAX_TIME_TO_START')) ));
			}

			$SuccessMessage = '';

			if ($this->paramGet('showsuccessmessage')) {
				$SuccessMessage .= Text::_($this->paramGet('successmessage'));
			}

			if ($this->paramGet('successmessagenumberofusers')) {
				$SuccessMessage .= ' ' . Text::_('PLG_SYSTEM_NOTIFICATIONARY_USERS_NOTIFIED');
			}

			$doc->addScriptOptions($this->plg_name, ['messages' => ['sent' => $SuccessMessage]]);

			self::addJSorCSS('ajax.js', $this->plg_full_name);

			self::addJSorCSS('styles.css', $this->plg_full_name);

			if ($this->paramGet('debug')) {
				$doc->addScriptOptions($this->plg_name, ['debug' => true]);
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
		$app = Factory::getApplication();
		$jinput = Factory::getApplication()->input;
		$session = Factory::getSession();
		$body = $app->getBody();

		$option = $jinput->get('option', null);

		if (in_array($option, ['com_dump', 'com_ajax'])) {
			return;
		}

		NotificationAryHelper::addUserlistBadges();

		// Add NA menu item to Joomla backend
		$this->addMenuItemToBackend();

		// Output Ajax placeholder if needed
		$this->addAjaxPlaceHolder();

		// K2 doesn't run onContentPrepareForm. So we need to imitate it here.
		$view = $jinput->get('view', null);

		if ($option == 'com_k2' && $view == 'item') {
			$result = $this->k2SimulateOnContentPrepareForm();
			self::$shouldShowSwitchCheckFlag = $result['shouldShowSwitchCheckFlag'];
			if ($result['return']) {
				return;
			}
		}

		// Can be set in onContentPrepareForm or onContentAfterSave
		if (
			empty($this->context) ||
			!$this->_isContentEditPage($this->context['full']) ||
			!NotificationAryHelper::isFirstRun('onAfterRender') ||
			!self::$shouldShowSwitchCheckFlag
		) {
			return;
		}

		$checkedyes = $checkedno = 'checked="checked"';
		$selectedyes = $selectedno = 'selected="selected"';
		$active_no = $active_yes = '';

		if ($this->runnotificationary == 1) {
			$checkedno = '';
			$selectedno = '';
		} else {
			$checkedyes = '';
			$selectedyes = '';
		}

		$customReplacement = $session->get('CustomReplacement', null, $this->plg_name);
		$replacement_label = NotificationAryHelper::getNotificationSwicthHtml();

		if (ArrayHelper::getValue($customReplacement, 'context', false) === $this->context['full']) {
			$possible_tag_ids = $customReplacement['possible_tag_ids'];
			$replacement_fieldset = $customReplacement['replacement_fieldset'];

			$replace = [
				'{{$this->attribsField}}' => $this->attribsField,
				'{{$checkedyes}}' => $checkedyes,
				'{{$active_yes}}' => $active_yes,
				'{{$checkedno}}' => $checkedno,
				'{{$active_no}}' => $active_no,

			];

			$search = array_keys($replace);
			$replacement_fieldset = str_replace($search, $replace, $replacement_fieldset);
		} else {
			$customReplacement = ['option' => false];

			if (!$app->isClient('administrator') && $this->paramGet('replacement_type') === 'simple') {
				$replacement_fieldset = '
				<select id="jform_' . $this->attribsField . '_runnotificationary" name="jform[' . $this->attribsField . '][runnotificationary]" class="inputbox">
				<option value="1" ' . $selectedyes . '>' . Text::_('JYES') . '</option>
				<option value="0" ' . $selectedno . '>' . Text::_('JNO') . '</option>
				</select>
				';
			} else {
				$replacement_fieldset = '
					<fieldset id="jform_' . $this->attribsField . '_runnotificationary" class="radio btn-group btn-group-yesno nswitch" >
						<input type="radio" ' . $checkedyes . ' value="1" name="jform[' . $this->attribsField . '][runnotificationary]" id="jform_'
					. $this->attribsField . '_runnotificationary1">
						<label for="jform_' . $this->attribsField . '_runnotificationary1" class="btn ' . $active_yes . '">' . Text::_('JYES') . '</label>
						<input type="radio" ' . $checkedno . ' value="0" name="jform[' . $this->attribsField . '][runnotificationary]" id="jform_'
					. $this->attribsField . '_runnotificationary0">
						<label for="jform_' . $this->attribsField . '_runnotificationary0" class="btn' . $active_no . '">' . Text::_('JNO') . '</label>
					</fieldset>
				';
			}

			$possible_tag_ids = [
				// ~ array('textarea', 'jform_articletext'),
				['select', 'jform_catid'],
				['select', 'jform_parent_id'],
				['select', 'jform_state'],
				['select', 'jform_published'],
				['select', 'jform_access'],
				['select', 'jform_language'],

				// Jdownloads
				['select', 'jform_file_language'],

				// Jdownloads
				['div', 'k2ExtraFieldsValidationResults'],

				// Old K2
				['select', 'catid'],
			];


			// JEvents compatibility\
			if ($this->context['full'] == 'jevents.edit.icalevent') {
				$possible_tag_ids = [
					['select', 'access'],
					['select', 'catid']
				];
				$replacement_fieldset = '
					<div><fieldset id="jform_' . $this->attribsField . '_runnotificationary" class="radio btn-group btn-group-yesno nswitch" >
						<input type="radio" ' . $checkedyes . ' value="1" name="custom_runnotificationary" id="jform_' . $this->attribsField . '_runnotificationary1">
						<label for="jform_' . $this->attribsField . '_runnotificationary1" class="btn ' . $active_yes . '">' . Text::_('JYES') . '</label>
						<input type="radio" ' . $checkedno . ' value="0" name="custom_runnotificationary" id="jform_' . $this->attribsField . '_runnotificationary0">
						<label for="jform_' . $this->attribsField . '_runnotificationary0" class="btn' . $active_no . '">' . Text::_('JNO') . '</label>
					</fieldset>
				';
			}
		}

		foreach ($possible_tag_ids as $tag) {
			$attribute_name = isset($tag[2]) ? $tag[2] : 'id';
			$nswitch_placeholder = NotificationAryHelper::getHTMLElementById($body, $tag[1], $tag[0], $attribute_name);

			if (!empty($nswitch_placeholder)) {
				break;
			}
		}

		// Not possible to find a place to place the notification switch
		if (empty($nswitch_placeholder)) {
			return;
		}

		$this->HTMLtype = 'div';

		if (Factory::getApplication()->isClient('administrator') && Factory::getApplication()->getTemplate() !== 'isis') {
			$this->HTMLtype = 'li';
		}

		$replacement = $this->getReplacementHTML(compact(
			'replacement_label',
			'replacement_fieldset',
			'customReplacement',
			'selectedyes',
			'selectedno',
		));

		if (self::$shouldShowSwitchCheckFlag && !$app->isClient('administrator')) {
			// At least at protostar a tab without name appears aboove the article, I assume is generates because of the NS injected into Form.
			// Let's try to remove it
			$hiddenTab = NotificationAryHelper::getHTMLElementById($body, 'params-basic', $tagname = 'div', $attributeName = 'id');

			$tmp = explode('<div class="control-label"><label id="', $hiddenTab);

			if (empty($hiddenTab) || count($tmp) > 2 || strpos($tmp[1], 'jform_params_runnotificationary-lbl') !== 0) {
				// Don't clear
			} else {
				$hiddenTabNav = '<li><a href="#params-basic" data-toggle="tab"></a></li>';
				$body = str_replace($hiddenTabNav, '', $body);
				$body = str_replace($hiddenTab, '', $body);
			}

			$body = str_replace($nswitch_placeholder, $nswitch_placeholder . $replacement, $body);
		} elseif (self::$shouldShowSwitchCheckFlag && $app->isClient('administrator')) {
			$AdminSwitch_placeholder_label = NotificationAryHelper::getHTMLElementById(
				$body,
				'jform_' . $this->attribsField . '_runnotificationary-lbl',
				'label'
			);
			$AdminSwitch_placeholder_fieldset = NotificationAryHelper::getHTMLElementById(
				$body,
				'jform_' . $this->attribsField . '_runnotificationary',
				'fieldset'
			);

			// $nswitch_placeholder = NotificationAryHelper::getHTMLElementById($body,'jform_catid','select');
			$body = str_replace($AdminSwitch_placeholder_label, '', $body);
			$body = str_replace($AdminSwitch_placeholder_fieldset, '', $body);
			$body = str_replace($nswitch_placeholder, $nswitch_placeholder . $replacement, $body);
		} elseif ($app->isClient('administrator')) {
			$AdminSwitch_placeholder_label = NotificationAryHelper::getHTMLElementById(
				$body,
				'jform_' . $this->attribsField . '_runnotificationary-lbl',
				'label'
			);
			$AdminSwitch_placeholder_fieldset = NotificationAryHelper::getHTMLElementById(
				$body,
				'jform_' . $this->attribsField . '_runnotificationary',
				'fieldset'
			);

			$body = str_replace($AdminSwitch_placeholder_label, '', $body);
			$body = str_replace($AdminSwitch_placeholder_fieldset, '', $body);
		} else {
			return;
		}

		$app->setBody($body);

		return;
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
	public function onContentPrepare($context, &$article, &$params, $page = null)
	{
		// static $assetsAdded = false;
		$app = Factory::getApplication();

		// Replace plugin code with the subscribe/unsubscribe form if needed
		if ($app->isSite()) {
			// $body = $app->getBody();

			$regex = '/{na\ssubscribe\s(.*?)}/Ui';

			// Find all instances of plugin and put in $matches for loadposition
			// $matches[0] is full pattern match, $matches[1] is the position
			preg_match_all($regex, $article->text, $matches, PREG_SET_ORDER);

			if ($matches) {
				if (!isset($this->pparams)) {
					$this->_prepareParams();
				}

				$possible_object_parameters = ['text', 'introtext'];

				foreach ($possible_object_parameters as $param) {
					if (isset($article->{$param})) {
						$text = NotificationAryHelper::pluginCodeReplace($this, $article->{$param}, $matches);
					}

					if (isset($text) && $text !== false) {
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
	 * @param   Form   $form         The form to be altered.
	 * @param   object  $contentItem  The associated data for the form.
	 *
	 * @return  boolean
	 */
	public function onContentPrepareForm($form, $contentItem)
	{
		// ~ dump('onContentPrepareForm','onContentPrepareForm');
		// dumpTrace();
		$allowSubscribe = true;
		foreach ($this->pparams as $rule) {
			if('0' === $rule->allow_subscribe) {
				$allowSubscribe = false;
				break;
			}
		}
		if ($allowSubscribe) {
			$this->_userProfileFormHandle($form, $contentItem);
		}

		$debug = true;
		$debug = false;

		$jinput = Factory::getApplication()->input;

		if ($jinput->get('option', null) == 'com_dump') {
			return;
		}

		if ($debug) {
			dump('onContentPrepareForm', 'onContentPrepareForm');
			dump($form, 'form');
			dump($contentItem, '$contentItem');
		}

		// $var = $jinput->get('cid');

		if (!NotificationAryHelper::isFirstRun('onContentPrepareForm')) {
			return;
		}

		// Check we are manipulating a valid form.
		if (!($form instanceof Form)) {
			$this->_subject->setError('JERROR_NOT_A_FORM');

			return false;
		}

		/* ***************************************************************************************************************************** */
		/*  NOTE!!! I must load the form below even if $contentItem is empty, becasue otherwise it doesn't save the notify switch state  */
		/* ***************************************************************************************************************************** */
		if (!empty($this->context)) {
			$context = $this->context['full'];
		} else {
			$context = $form->getName();
		}

		$session = Factory::getSession();

		if ($debug) {
			dump($context, '$context from Form');
		}

		$session->set('FormContext', $context, $this->plg_name);
		$context = $this->_contextAliasReplace($context);
		$this->_setContext($context);

		if ($debug) {
			dump($context, 'here 1 $context');
		}

		if (!$this->_isContentEditPage($context)) {
			return;
		}

		if ($debug) {
			dump('here 2');
		}

		// Specially for JEvents. Here I set data for special JEvents event onEventEdit which is run after onContentPrepare
		if ('jevents.edit.icalevent' === $context) {
			global  $NotificationAryFirstRunCheck;

			if (empty($this->form)) {
				$this->form = &$form;
				$NotificationAryFirstRunCheck['onContentPrepareForm'] = null;

				return;
			}
		}

		// Determine if at least according to one rule the article notification is on. If it's on, then set the appropriate flag in $session
		// If NSwitch is off - FALSE
		self::$shouldShowSwitchCheckFlag = false;

		// ~ dump($this->pparams,'$this->pparams');
		// ~ $this->contentItem = $this->_contentItemPrepare($contentItem);

		if (!empty($contentItem)) {
			$contentItem = $this->_contentItemPrepare($contentItem);
		}

		$rules = $this->_leaveOnlyRulesForCurrentItem($context, $contentItem, 'showSwitch');

		if (empty($rules)) {
			return;
		}

		if ($debug) {
			dump($rules, '$rules');
			dump('here 3');
		}

		$session = Factory::getSession();

		if (empty($contentItem)) {
			$attribs = $session->get('AttribsField' . $context, 'attribs', $this->plg_name);
			$session->clear('AttribsField' . $context, $this->plg_name);
		} else {
			if ($form->getName() === 'com_users.profile') {
				$attribs = 'params';
			} elseif (!empty($contentItem) && !isset($contentItem->attribs)) {
				$attribs = 'params';
			} else {
				$attribs = 'attribs';
			}

			$session->set('AttribsField' . $context, $attribs, $this->plg_name);
		}

		// ~ dump($contentItem,'$contentItem');
		$app = Factory::getApplication();

		if (!empty($contentItem->$attribs)) {
			if (!is_array($contentItem->$attribs)) {
				$contentItem->$attribs = (array) json_decode($contentItem->$attribs);
			}
		}

		$this->runnotificationary = 0;

		if (isset($contentItem->{$attribs}['runnotificationary'])) {
			$this->runnotificationary = $contentItem->{$attribs}['runnotificationary'];
		} else {
			// If at lease one active rules has default switch status on
			foreach ($rules as /* $rule_number => */ $rule) {
				if ($rule->notificationswitchdefault == 1) {
					$this->runnotificationary = 1;
				}
			}
		}
		$fieldsetOpened = false;
		$string = '
			<form>
				<fields name="' . $attribs . '" >';
		if ($app->isClient('administrator')) {
			$string .= '<fieldset name="basic" >';
			$fieldsetOpened = true;
		} elseif ($form->getName() === 'com_users.profile') {
			$string .= '<fieldset name="core" label="PLG_SYSTEM_NOTIFICATIONARY_NOTIFY">';
			$fieldsetOpened = true;
		}

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

		if ($fieldsetOpened) {
			$string .= '</fieldset>';
		}

		$string .= '
						</fields>
					</form>';
		$form->load((string) $string, true);

		$this->attribsField = $attribs;
		self::$shouldShowSwitchCheckFlag = true;

		$customReplacement = $session->get('CustomReplacement', null, $this->plg_name);

		if (!empty($customReplacement) && $customReplacement['context'] == $this->context['full']) {
			$switch_selector = $customReplacement['switch_selector'];
			$form_selector = $customReplacement['form_selector'];
		} else {
			$switch_selector = "[name=\"jform[" . $attribs . "][runnotificationary]\"]:checked";
			$form_selector = 'adminForm';
		}

		foreach ($rules as /* $rule_number => */ $rule) {
			if ($rule->notificationswitchaddconfirmation) {
				$doc = Factory::getDocument();
				$language = Factory::getLanguage();

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
					. Text::_(
						Text::sprintf(
							'PLG_SYSTEM_NOTIFICATIONARY_ARE_YOU_SURE',
							'"' . Text::_('PLG_SYSTEM_NOTIFICATIONARY_NOTIFY') . '"',
							'"' . Text::_('JNo') . '"'
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
		$jinput = Factory::getApplication()->input;
		$uniq = $jinput->get('unsubscribe', null);
		$email = $jinput->get('email', null, 'raw');
		$md5 = $jinput->get('hash', null, 'raw');

		if ($uniq) {
			$serialize = (base64_encode(serialize(['unsubscribe' => $email, 'md5' => $md5])));
			$app	= Factory::getApplication();

			$redirect_url = 'index.php?option=com_ajax&format=raw'
				. '&group=' . $this->plg_type
				. '&plugin=notificationAryRun'
				. '&' . Session::getFormToken() . '=1'
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
		NotificationAryHelper::_autoOverride($this);

		$this->_prepareParams();

		foreach ($this->pparams as $k => $param) {

			if (!$param->isenabled) {
				continue;
			}
			if ($param->context_or_contenttype == "context" && $param->context == "com_zoo.item" && ComponentHelper::getComponent('com_zoo', true)->enabled) {
				NotificationAryHelper::loadZoo();
				break;
			}
		}
	}


	/**
	 * Runs on content preparation
	 *
	 * @param   string  $context  The context for the data
	 * @param   object  $data     An object containing the data for the form.
	 *
	 * @return  boolean
	 *
	 * @since   1.6
	 */
	public function onContentPrepareData($context, $data)
	{
		// Check we are manipulating a valid form.
		if (!in_array($context, ['com_users.profile', 'com_users.user', 'com_users.registration', 'com_admin.profile']))
		{
			return true;
		}

		if (is_object($data))
		{

			$rules = [];
			$nasubscribe = [];

			foreach ($this->pparams as $rule) {
				if('0' === $rule->allow_subscribe) {
					continue;
					break;
				}
				$rules[$rule->__ruleUniqID] = $rule;
				$nasubscribe[$rule->__ruleUniqID] = [
					'subscribeType' => $rule->allow_subscribe,
					'name' => $rule->{'{notificationgroup'}[0],
					'text' => $rule->allow_subscribe_default ?
							Text::_('PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIBED_TO_ALL') :
							Text::_('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBED_FROM_ALL'),
				];
			}
			if (empty($rules)) {
				return;
			}

			$userId = isset($data->id) ? $data->id : 0;

			if (!isset($data->nasubscribe) && $userId > 0)
			{
				// Load the profile data from the database.
				$db = Factory::getDbo();
				$db->setQuery(
					'SELECT profile_key, profile_value FROM #__user_profiles'
						. ' WHERE user_id = ' . (int) $userId . " AND profile_key LIKE 'notificationary.%'"
						. ' ORDER BY ordering'
				);

				try
				{
					$results = $db->loadRowList();
				}
				catch (\RuntimeException $e)
				{
					$this->_subject->setError($e->getMessage());

					return false;
				}
				// Un$nasubscribe[$rule->__ruleUniqID]
				// PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBED
				// PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIBED_TO_ALL
				$data->nasubscribe = ['subscribe' => ''];
				foreach ($results as $v)
				{
					$k = str_replace('notificationary.', '', $v[0]);
					list($ruleId, $suff) = explode('.', $k, 2);
					if (!array_key_exists($ruleId, $rules)) {
						continue;
					}
					$rule = $rules[$ruleId];
					if ('all' === $suff) {
						switch ($v[1]) {
							case 'subscribed':
								$nasubscribe[$ruleId]['text'] = Text::_('PLG_SYSTEM_NOTIFICATIONARY_SUBSCRIBED_TO_ALL');
								break;
							case 'unsubscribed':
								$nasubscribe[$ruleId]['text'] = Text::_('PLG_SYSTEM_NOTIFICATIONARY_UNSUBSCRIBED_FROM_ALL');
								# code...
								break;
						}
						// $nasubscribe[$ruleId]['text'] = $subscribeTypesTexts[$rules[$ruleId]->allow_subscribe];
					} else {
						unset($nasubscribe[$ruleId]['text']);
						$nasubscribe[$ruleId]['id'][] = $v[1];
					}
					// $k = 'subscribe';
					$value = json_decode($v[1], true);

					if ($value === null)
					{
						// $data->nasubscribe[$k] = $v[1];
						$value = $v[1];
						// $f = 'users.nasubscribe' . $k;
					}

				}
				// $data->nasubscribe['subscribe'] .= $value;
				$data->nasubscribe['subscribe'] = $nasubscribe;
				$f = 'users.subscribe';
				if (!HTMLHelper::isRegistered($f))
				{
					HTMLHelper::register($f, [__CLASS__, 'profileHelper']);
				}
			}
		}
		return true;
	}
}
