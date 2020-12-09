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
use Joomla\CMS\Session\Session;
use Joomla\String\StringHelper;
use Joomla\CMS\Component\ComponentHelper;
use NotificationAry\HelperClasses\NotificationAryHelper;
// use NotificationAry\HelperClasses\FakeMailerClass;
use Joomla\CMS\HTML\HTMLHelper;


// No direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Plugin code
 *
 * @author  Gruz <arygroup@gmail.com>
 * @since   0.0.1
 */
trait onAfterRenderHelper
{
	private function addMenuItemToBackend() {
		$app = Factory::getApplication();
		$jinput = Factory::getApplication()->input;

		// Block JSON response, like there was an incompatibility with RockSprocket
		$format = $jinput->get('format', 'html');

		if ($app->isClient('administrator') && $this->paramGet('add_menu_item') && $format == 'html') {
			$body = $app->getBody();

			/**
			 * Get extension table class
			 *
			 * @var Joomla\CMS\Table\Extension
			 */
			$extensionTable = Table::getInstance('extension');

			$pluginId = $extensionTable->find(['element' => $this->plg_name, 'type' => 'plugin']);

			$language = Factory::getLanguage();

			// Have to load curren logged in language to show the proper menu item language, not the default backend language
			$language->load($this->plg_full_name, $this->plg_path, $language->get('tag'), true);

			$menu = '<li><a class="menu-'
				. $this->plg_name . ' " href="index.php?option=com_plugins&task=plugin.edit&extension_id=' . $pluginId . '">'
				. Text::_($this->plg_full_name . '_MENU')
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
			if (count($body) == 2) {
				$body = $body[0] . $js . '</body>' . $body[1];
				$app->setBody($body);
			}
		}
	}

	private function addAjaxPlaceHolder() {
		$app = Factory::getApplication();
		$session = Factory::getSession();

		// Is set in onAfterContentSave
		$ajaxHash = $session->get('AjaxHash', null, $this->plg_name);

		$session->clear('AjaxHash', $this->plg_name);

		if (!empty($ajaxHash)) {
			$place_debug = '';
			$user = Factory::getUser();

			// Since the _checkAllowed checks the global settings, there is no $this->rule passed and used there
			if ($this->paramGet('ajax_allow_to_cancel') && $this->_checkAllowed($user, $paramName = 'allowuser', $prefix = 'ajax')) {
				$place_debug .= '<button type="button" id="' . $this->plg_full_name . '_close">X</button>';
			}

			if ($this->paramGet('debug')) {
				// ~ $place_debug .= '<div style="position:fixed">';
				$place_debug .= '<a id="clear" class="btn btn-error">Clear</a>';
				$place_debug .= '<a id="continue" class="btn btn-warning">Continue</a>';

				// ~ $place_debug .= '</div>';
			} else {
				$place_debug .= '<small>';

				if ($this->paramGet('ajax_allow_to_cancel') && $this->_checkAllowed($user, $paramName = 'allowuser', $prefix = 'ajax')) {
					$place_debug .= Text::_('PLG_SYSTEM_NOTIFICATIONARY_AJAX_TIME_TO_CANCEL');
					$place_debug .= '. ';
				}

				$place_debug .= Text::_('PLG_SYSTEM_NOTIFICATIONARY_AJAX_SENDING_MESSAGES') . '</small>';
			}

			$ajax_place_holder = '<div class="nasplace" >' . $place_debug . '<div class="nasplaceitself" id="' . $this->plg_full_name . '" ></div>';

			$body = $app->getBody();
			$body = str_replace('</body>', $ajax_place_holder . '</body>', $body);
			$body = $app->setBody($body);
		}
	}

	private function k2SimulateOnContentPrepareForm() {
		$jinput = Factory::getApplication()->input;
		$session = Factory::getSession();

		// Prepare to imitate onContentPrepareForm {
		$this->_prepareParams();
		$context = 'com_k2.item';
		$this->allowed_contexts[] = $context;
		$this->_setContext($context);

		$shouldShowSwitchCheckFlag = false;
		$contentItem = $this->_getContentItemTable($context);
		$contentItem->load($jinput->get('cid', 0));

		jimport('joomla.form.form');
		$form = Form::getInstance('itemForm', JPATH_ADMINISTRATOR . '/components/com_k2/models/item.xml');
		$values = ['params' => json_decode($contentItem->params)];
		$form->bind($values);

		// Prepare to imitate onContentPrepareForm }

		$this->onContentPrepareForm($form, $contentItem);
		$rules = $this->_leaveOnlyRulesForCurrentItem($context, $contentItem, 'showSwitch');

		if (empty($rules)) {
			$return = true;
		} else {
			$return = false;
			$shouldShowSwitchCheckFlag = true;

			// Is set for onAfterContentSave as onContentPrepareForm is not run, but this method onAfterRender runs after onContentAfterSave.
			$session->set('shouldShowSwitchCheckFlagK2Special', true, $this->plg_name);

			// If the NS should be shown but cannot be shown due to HTML layout problems, then we need to know default value
			$rule = array_pop($rules);

			$session->set('shouldShowSwitchCheckFlagK2SpecialDefaultValue', (bool) $rule->notificationswitchdefault, $this->plg_name);
		}

		return [
			'return' => $return,
			'shouldShowSwitchCheckFlag' => $shouldShowSwitchCheckFlag,
		];
	}

	private function getReplacementHTML($params) {
		$replacement_label = $params['replacement_label'];
		$replacement_fieldset = $params['replacement_fieldset'];
		$customReplacement = $params['customReplacement'];
		$selectedyes = $params['selectedyes'];
		$selectedno = $params['selectedno'];

		$app = Factory::getApplication();
		switch ($this->context['full']) {
			// JEvents compatibility\
			case 'jevents.edit.icalevent':
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
				break;
			default:
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
				break;
		}


		switch ($this->context['option']) {
			case $customReplacement['option']:
				break;
			case 'com_jdownloads':
				if ($app->isClient('administrator')) {
					$replacement = '</li>' . $replacement . '<li>';
				} else {
					$label = NotificationAryHelper::getNotificationSwicthHtml($this->attribsField);

					$field = '
						<select id="jform_'
						. $this->attribsField . '_runnotificationary" name="jform[' . $this->attribsField . '][runnotificationary]"  size="1" class="inputbox">
								<option value="0" ' . $selectedno . '>' . Text::_('JNo') . '</option>
								<option value="1" ' . $selectedyes . '>' . Text::_('JYes') . '</option>
						</select>';

					$replacement = '</div><div class="formelm">' . $label . $field . '</div><div>';
				}

				break;
			case 'com_k2':
				$replacement = str_replace('jform[params]', 'params', $replacement);

				if (!$app->isClient('administrator')) {
					$replacement = str_replace('btn-group', '', $replacement);
					$replacement = str_replace('class="btn', 'class="', $replacement);
				}
				break;
			default:
				if (!$app->isClient('administrator') && $this->paramGet('replacement_type', 'simple') === 'simple') {
					// Do nothing
				} elseif ($this->HTMLtype == 'div') {
					$replacement = '</div></div>' . $replacement . '<div style="display:none;"><div>';
				} else {
					$replacement = '</li><li>' . $replacement . '</li><li>';
				}

				break;
		}

		return $replacement;
	}
}
