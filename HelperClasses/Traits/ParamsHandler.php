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

use NotificationAry\HelperClasses\NotificationAryHelper;
// use NotificationAry\HelperClasses\FakeMailerClass;

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

trait ParamsHandler
{
    	/**
	 * Geat plugin parameters from DB and parses them for later usage in the plugin
	 *
	 * @return   type  Description
	 */
	public function _prepareParams()
	{
		// No need to run if already generated
		if (!empty($this->pparams)) {
			return;
		}

		// $this->_updateRulesIfHashSaved();

		// Get variable fields params parsed in a nice way, stored to $this->pparams
		$this->getGroupParams('{notificationgroup');

		// Some parameters preparations
		foreach ($this->pparams as $rule_number => $rule) {
			$rule = (object) $rule;

			// Do not handle this rule, if it shound never be run . $rule->ausers_notifyon part is here for legacy
			if (!$rule->isenabled || $rule->ausers_notifyon == 3) {
				unset($this->pparams[$rule_number]);
				continue;
			}

			$this->pparams[$rule_number] = (object) $rule;

			// This array is used to build mails once per user group, as mails to users from the same user groups are the same.
			// Here we just init it to be used when building a mail.
			$this->pparams[$rule_number]->cachedMailBuilt = array();

			// If categories entered manually, then convert to array
			if (!empty($rule->ausers_articlegroupsselection) && strpos($rule->ausers_articlegroupsselection[0], ',') !== false) {
				$this->pparams[$rule_number]->ausers_articlegroupsselection = array_map('trim', explode(',', $rule->ausers_articlegroupsselection[0]));
			}

			// Prepare global cumulative flag to know which prev. versions to be attached in all rules.

			// To later prepare all needed attached files together and only once.
			// So we avoid preparing the same attached files which may be needed for several groups
			if (isset($this->pparams[$rule_number]->attachpreviousversion)) {
				if (!is_array($this->pparams[$rule_number]->attachpreviousversion)) {
					$this->pparams[$rule_number]->attachpreviousversion = (array) $this->pparams[$rule_number]->attachpreviousversion;
				}

				foreach ($this->pparams[$rule_number]->attachpreviousversion as $k => $v) {
					$this->prepare_previous_versions_flag[$v] = $v;
				}
			}
			// Here we get the extension and the context to be notified. We use either a registred in Joomla extension (like DPCalendar or core Articles) or
			if ($rule->context_or_contenttype == "content_type") {
				list($extension_info, $contentType) = $this->_getExtensionInfo($context = null, $id = $rule->content_type);
				$this->pparams[$rule_number]->contenttype_title = $contentType->type_title;
			} else {
				$templateRows = array_map('trim', explode(PHP_EOL, $this->pparams[$rule_number]->context));
				$context = trim($templateRows[0]);

				if (empty($context)) {
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

				foreach ($extension_info as $key => $value) {
					$extension_info_merged[$key] = $templateRows[$i];
					$i++;
				}

				$extension_info = $extension_info_merged;

				unset($extension_info_merged);
			}

			if (!empty($extension_info['contextAliases'])) {
				$contextAliases = explode(',', $extension_info['contextAliases']);
				$contextAliases = array_map('trim', $contextAliases);

				foreach ($contextAliases as $ka => $va) {
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
			$includes = array('author', 'modifier');
			$available_options = array(
				'introtext',
				'fulltext',
				'frontendviewlink',
				'frontendeditlink',
				'backendeditlink',
				'unsubscribelink'
			);

			foreach ($includes as $include) {
				$mb_type = $this->pparams[$rule_number]->{$include . '_mailbody_type'};
				$mb = $this->pparams[$rule_number]->{$include . '_mailbody'};

				if (!is_array($mb)) {
					$mb = array_map('trim', explode(',', $mb));
				}

				if ($mb_type == 'inherit') {
					foreach ($available_options as $k => $v) {
						$this->pparams[$rule_number]->{'ausers_' . $include . 'include' . $v} = $this->pparams[$rule_number]->{'ausers_include' . $v};
					}
				} else {
					foreach ($available_options as $k => $v) {
						if (in_array($v, $mb)) {
							$this->pparams[$rule_number]->{'ausers_' . $include . 'include' . $v} = true;
						} else {
							$this->pparams[$rule_number]->{'ausers_' . $include . 'include' . $v} = false;
						}
					}
				}
			}

			$additionalmailadresses = $this->pparams[$rule_number]->ausers_additionalmailadresses;
			$additionalmailadresses = array_map('trim', explode(PHP_EOL, $additionalmailadresses));

			$this->pparams[$rule_number]->usersAddedByEmail = array();

			foreach ($additionalmailadresses as $k => $v) {
				$user = NotificationAryHelper::getUserByEmail($v);

				if ($user->id) {
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
	 * Don't remember
	 *
	 * @param   string  $name             Parameter name
	 * @param   string  $fieldNamePrefix  Where to get the name
	 *
	 * @return   mixed  Parameter value
	 */
	public function _getP($name, $fieldNamePrefix)
	{
		if ($fieldNamePrefix == 'ausers') {
			return $this->rule->{$name};
		} else {
			return $this->paramGet($name);
		}
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

		if ($debug) {
			dumpMessage('<b>' . __FUNCTION__ . '</b> . | Task : ' . $this->task . ' | isNew ' . $isNew);
		}

		// ~ static $rules = array('switch'=>array(),'content'=>array());
		static $rules = array();

		if (!empty($rules[$task])) {
			return $rules[$task];
		}

		if (empty($contentItem->id) && $task == 'showSwitch') {
			$isNew = true;
		}

		if ($debug) {
			dump($contentItem, '$contentItem');
		}

		foreach ($this->pparams as $rule_number => $rule) {
			// Pass rule to _checkAllowed
			$this->rule = $rule;

			if ($debug) {
				dump($rule, '$rule ' . $rule_number);
			}

			if ($task == 'saveItem') {
				$this->_debug('Checking rule <b>' . $rule->{'{notificationgroup'}[0] . '</b>', false, $rule);
			}

			// Not our context
			if ($rule->context != $context) {
				if ($task == 'saveItem') {
					$this->_debug('Context wrong. Rule: <b>' . $rule->context . '</b>=<b>' . $context . '</b> content. CHECK FAILED');
				}

				continue;
			}

			if ($task == 'saveItem') {
				$this->_debug('Context check  PASSED');
			}

			if ($debug) {
				dumpMessage('here 1 ');
			}

			if ($rule->ausers_notifyon == 1 && !$isNew) {
				if ($task == 'saveItem') {
					$this->_debug('Only new allowed but content is not new. CHECK FAILED');
				}

				continue;
			}

			if ($debug) {
				dumpMessage('here 2');
			}

			if ($task == 'saveItem') {
				$this->_debug('Only new allowed and is New?  PASSED');
			}

			if ($rule->ausers_notifyon == 2 && $isNew) {
				if ($task == 'saveItem') {
					$this->_debug('Only update is allowed but content is new. CHECK FAILED');
				}

				continue;
			}

			if ($debug) {
				dumpMessage('here 3');
			}

			if ($task == 'saveItem') {
				$this->_debug('Only update allowed and isn\'t new?  PASSED');
			}

			$user = JFactory::getUser();

			if ($task == 'saveItem') {
				$this->_debug('User allowed?   START CHECK');
			}

			// Check if allowed notifications for actions performed by this user
			if (!$this->_checkAllowed($user, $paramName = 'allowuser')) {
				if ($task == 'saveItem') {
					$this->_debug('User is not allowed to send notifications. CHECK FAILED');
				}

				continue;
			}

			if ($debug) {
				dumpMessage('here 4');
			}

			if ($task == 'saveItem') {
				$this->_debug('User allowed?   PASSED');
			}

			if ($task == 'showSwitch') {
				if (!$rule->shownotificationswitch) {
					continue;
				}

				if ($debug) {
					dumpMessage('here 5');
				}

				$app = JFactory::getApplication();

				if (!$app->isAdmin() && !$rule->notificationswitchfrontend) {
					continue;
				}

				if ($debug) {
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
				if (!$this->_checkAllowed($user, $paramName = 'allowswitchforuser')) {
					continue;
				}

				if ($debug) {
					dumpMessage('here 7');
				}
			} elseif ($task == 'saveItem') {
				if ($task == 'saveItem') {
					$this->_debug('Content allowed?   START CHECK');
				}

				if (!$this->_checkAllowed($contentItem, $paramName = 'article')) {
					if ($task == 'saveItem') {
						$this->_debug('Content item is not among allowed categories or specific items. CHECK FAILED');
					}

					continue;
				}

				if ($task == 'saveItem') {
					$this->_debug('Content allowed? ? PASSED');
					$this->_debug('<b>This rule sends notifications for the content item!!!</b>');
				}
			}

			$rules[$task][$rule_number] = $rule;
		}

		unset($this->task);

		if (isset($rules[$task])) {
			return $rules[$task];
		}

		return false;
	}

}
