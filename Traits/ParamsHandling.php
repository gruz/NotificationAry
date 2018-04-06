<?php
/**
 * Hadnling plugin params
 *
 * @package     NotificationAry
 *
 * @author      Gruz <arygroup@gmail.com>
 * @copyright   Copyleft (є) 2018 - All rights reversed
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */


namespace NotificationAry\Traits;

/**
 * Hadnling plugin params
 *
 * @since 0.2.17
 */

trait ParamsHandling
{
	/**
	 * Geat plugin parameters from DB and parses them for later usage in the plugin
	 */
	public function prepareParams()
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
		foreach ($this->pparams as $ruleNumber => $rule)
		{
			$rule = (object) $rule;

			// Do not handle this rule, if it shound never be run . $rule->ausers_notifyon part is here for legacy
			if (!$rule->isenabled || $rule->ausers_notifyon == 3)
			{
				unset($this->pparams[$ruleNumber]);
				continue;
			}

			$this->pparams[$ruleNumber] = (object) $rule;

			// This array is used to build mails once per user group, as mails to users from the same user groups are the same.
			// Here we just init it to be used when building a mail.
			$this->pparams[$ruleNumber]->cachedMailBuilt = array();

			// If categories entered manually, then convert to array
			if (!empty($rule->ausers_articlegroupsselection) && strpos($rule->ausers_articlegroupsselection[0], ',') !== false )
			{
				$this->pparams[$ruleNumber]->ausers_articlegroupsselection = array_map('trim', explode(',', $rule->ausers_articlegroupsselection[0]));
			}

			// Prepare global cumulative flag to know which prev. versions to be attached in all rules.

			// To later prepare all needed attached files together and only once.
			// So we avoid preparing the same attached files which may be needed for several groups
			if (isset($this->pparams[$ruleNumber]->attachpreviousversion) )
			{
				if (!is_array($this->pparams[$ruleNumber]->attachpreviousversion))
				{
					$this->pparams[$ruleNumber]->attachpreviousversion = (array) $this->pparams[$ruleNumber]->attachpreviousversion;
				}

				foreach ($this->pparams[$ruleNumber]->attachpreviousversion as $k => $v)
				{
					$this->preparePreviousVersionsFlag[$v] = $v;
				}
			}
			// Here we get the extension and the context to be notified. We use either a registred in Joomla extension (like DPCalendar or core Articles) or
			if ("content_type" === $rule->context_or_contenttype)
			{
				list($extensionInfo, $contentType) = $this->getExtensionInfo($context = null, $id = $rule->content_type);
				$this->pparams[$ruleNumber]->contenttype_title = $contentType->type_title;
			}
			else
			{
				$templateRows = array_map('trim', explode(PHP_EOL, $this->pparams[$ruleNumber]->context));

				$context = trim($templateRows[0]);

				if (empty($context))
				{
					\JFactory::getApplication()->enqueueMessage(
						\JText::_(
							ucfirst($this->plgName)
						)
						. ' (line ' . __LINE__ . '): '
						. \JText::sprintf(
							'PLG_SYSTEM_NOTIFICATIONARY_NO_EXTENSION_SELECTED',
							$this->pparams[$ruleNumber]->{'{notificationgroup'}[0],
							$this->pparams[$ruleNumber]->__ruleUniqID
						),
						'warning'
					);

					unset($this->pparams[$ruleNumber]);
					continue;
				}

				list($extensionInfo, $contentType) = $this->getExtensionInfo($context, $id = null);
				$this->pparams[$ruleNumber]->contenttype_title = $extensionInfo['Context'];

				$i = 0;

				$extensionInfo_merged = array();

				foreach ($extensionInfo as $key => $value)
				{
					if (empty($templateRows[$i]))
					{
						$extensionInfo_merged[$key] = '';
					}
					else
					{
						$extensionInfo_merged[$key] = $templateRows[$i];
					}
					$i++;
				}

				$extensionInfo = $extensionInfo_merged;

				unset($extensionInfo_merged);
			}

			if (!empty($extensionInfo['contextAliases']))
			{
				$contextAliases = explode(',', $extensionInfo['contextAliases']);
				$contextAliases = array_map('trim', $contextAliases);

				foreach ($contextAliases as $ka => $va)
				{
					$this->allowedContexts[] = $va;
					$this->context_aliases[$va] = $extensionInfo['Context'];
				}
			}

			$this->pparams[$ruleNumber]->context = $extensionInfo['Context'];
			$this->pparams[$ruleNumber]->extensionInfo = $extensionInfo;
			$this->predefinedContextTemplates[$extensionInfo['Context']] = $extensionInfo;

			unset($extensionInfo);

			// $this->allowedContexts[] = $rule->context;
			$this->allowedContexts[] = $this->pparams[$ruleNumber]->context;

			$component = explode('.', $this->pparams[$ruleNumber]->context);
			$this->allowedComponents[] = $component[0];

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
				$mb_type = $this->pparams[$ruleNumber]->{$include . '_mailbody_type'};
				$mb = $this->pparams[$ruleNumber]->{$include . '_mailbody'};

				if (!is_array($mb))
				{
					$mb = array_map('trim', explode(',', $mb));
				}

				if ($mb_type == 'inherit')
				{
					foreach ($available_options as $k => $v)
					{
						$this->pparams[$ruleNumber]->{'ausers_' . $include . 'include' . $v} = $this->pparams[$ruleNumber]->{'ausers_include' . $v};
					}
				}
				else
				{
					foreach ($available_options as $k => $v)
					{
						if (in_array($v, $mb))
						{
							$this->pparams[$ruleNumber]->{'ausers_' . $include . 'include' . $v} = true;
						}
						else
						{
							$this->pparams[$ruleNumber]->{'ausers_' . $include . 'include' . $v} = false;
						}
					}
				}
			}

			$additionalmailadresses = $this->pparams[$ruleNumber]->ausers_additionalmailadresses;
			$additionalmailadresses = array_map('trim', explode(PHP_EOL, $additionalmailadresses));

			$this->pparams[$ruleNumber]->usersAddedByEmail = array();

			foreach ($additionalmailadresses as $k => $v)
			{
				$user = self::getUserByEmail($v);

				if ($user->id)
				{
					$this->pparams[$ruleNumber]->usersAddedByEmail[] = $user;
					unset($additionalmailadresses[$k]);
				}
			}

			$this->pparams[$ruleNumber]->ausers_additionalmailadresses = implode(PHP_EOL, $additionalmailadresses);
		}

		$this->allowedContexts = array_unique($this->allowedContexts);
		$this->allowedComponents = array_unique($this->allowedComponents);
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
	 * Save rule from hash
	 *
	 * @return   void
	 */
	private function _updateRulesIfHashSaved ()
	{
		$debug = true;
		$debug = false;

		// Get extension table class
		$extensionTable = \JTable::getInstance('extension');

		$pluginId = $extensionTable->find(array('element' => $this->plgName, 'type' => 'plugin'));
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

				\JFactory::getApplication()->enqueueMessage(
					$this->plgName . ": "
					. \JText::_('PLG_SYSTEM_NOTIFICATIONARY_COULD_NOT_APPLY_CONFIGURATION_HASH')
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

		$app	= \JFactory::getApplication();
		$uri = \JFactory::getURI();
		$pageURL = $uri->toString();

		$app->redirect($pageURL);

		return;
	}



}
