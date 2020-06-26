<?php
namespace NotificationAry;

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

// TODO include in new ALPHA this change: self::shouldShowSwitchCheckFlag

// No direct access
defined('_JEXEC') or die;

// Do not enter here if it's a com_dump call to avoid duplicate plugin call with jdump
if (\Joomla\CMS\Factory::getApplication()->input->get('option', null) === 'com_dump') {
	return;
}

// Check if the latest needed version of GJFields installed
\JLoader::registerNamespace('NotificationAry', __DIR__, false, false, 'psr4');
if (!(new HelperClasses\GJFieldsChecker('1.2.16', 'NotificationAry'))->check()) {
	return;
}

use Joomla\Registry\Registry;
use Joomla\CMS\Plugin\PluginHelper;
use NotificationAry\HelperClasses\NotificationAryHelper;

NotificationAryHelper::loadPluginDependencies();

// Generate and empty object
$plgParams = new Registry;

// Get plugin details
$plugin = PluginHelper::getPlugin('system', 'notificationary');

// Load params into our params object
$plgParams->loadString($plugin->params);

$notificationgroup = $plgParams->get('{notificationgroup');

$custom_templates = array();

if (!empty($notificationgroup->context_or_contenttype)) {
	$context_or_contenttype = $notificationgroup->context_or_contenttype;
	$enabled = $plgParams->get('{notificationgroup')->isenabled;

	foreach ($context_or_contenttype as $k => $v) {
		if ($v == 'context' && $enabled[$k] == 1) {
			// $custom_templates[] = $plgParams->get('{notificationgroup')->context[$k];
			$custom_template = $plgParams->get('{notificationgroup')->context[$k];
			$custom_template = NotificationAryHelper::_parseManualContextTemplate($custom_template);

			if (!empty($custom_template['Context'])) {
				$custom_templates[$custom_template['Context']] = $custom_template;
			}
		}
	}

	if (!isset($predefined_context_templates)) {
		include dirname(__FILE__) . '/helpers/predefined_contexts.php';
	}
}

$temp_alias_functions = [];
foreach ($custom_templates as $context => $array) {
	foreach ($functionsToBeAliased as $functionName) {
		if (empty($array[$functionName])) {
			continue;
		}

		$array[$functionName] = trim($array[$functionName]);

		if (strpos($array[$functionName], 'function ') === 0 || strpos($array[$functionName], 'static function ') === 0) {
			$temp_alias_functions[$array[$functionName]] = 'public ' . $array[$functionName];
		} else {
			// Prevent error if custom file doesn't exists'
			if (strpos($array[$functionName], '/') === false) {
				$temp_alias_functions[$array[$functionName]] = '
				public function ' . $array[$functionName] . ' ($context, $contentItem, $isNew) {
					return $this->' . $functionName . '($context, $contentItem, $isNew);
				}
				';
			}
		}
	}
}

$class_dynamic = '
class plgSystemNotificationary extends NotificationAry\HelperClasses\NotificationaryCore {
	public function __construct(& $subject, $config) {
		parent::__construct($subject, $config);
	}
	' . implode(PHP_EOL, $temp_alias_functions) . '
}';
eval($class_dynamic);
