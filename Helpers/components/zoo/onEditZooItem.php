<?php
/**
 * Bridge to tie NotificationAry and Zoo
 *
 * @package		NotificationAry
 * @author Gruz <arygroup@gmail.com>
 * @copyright	0000 Copyleft - All rights reversed
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

use NotificationAry;
/**
 * `static` before function name is a must
 *
 * @param   object $event Event object
 *
 * @return void
 */
static function onEditZooItem($event)
{
	$contentItem = $event->getSubject();

	if (!NotificationAry\PlgSystemNotificationaryCore::isZooEditPage($contentItem))
	{
		return;
	}

	$session = \JFactory::getSession();
	$session->clear('CustomReplacement', "notificationary");

	$context = 'com_zoo.item';

	/*
			\JLoader::register('ItemTable', JPATH_ROOT . JPATH_ADMINISTRATOR . '/components/com_zoo/tables/item.php');
			$app = App::getInstance('zoo');
			// ~ $app = $event->getApplication();

			$dataModel = new ItemTable($app);
	dump($contentItem->id,'$contentItem->id]');
			// ~ $options = array('conditions' => ['id' => $contentItem->id]);
			// ~ $dataModel->find('first', $options);
			$a = $dataModel->getByIds($contentItem->id);

	dump($a,'$dataModel');
	*/

	// ~ $jinput = \JFactory::getApplication()->input;
	// ~ $cid = $jinput->get('cid', []);

	if (isset($event['new']))
	{
		$isNew = $event['new'];
	}
	elseif (empty($contentItem->id))
	{
		$isNew = true;
	}
	else
	{
		$isNew = false;
	}

	if (isset($contentItem->params) && isset($contentItem->params->{'config.custom_runnotificationary'}))
	{
		$contentItem->params->runnotificationary = $contentItem->params->{'config.custom_runnotificationary'};
	}

	// Include buttons defined by published quickicon plugins
	\JPluginHelper::importPlugin('system', 'notificationary');

	$app = \JFactory::getApplication();

	// ~ JEventDispatcher::getInstance()->trigger('onContentPrepare', [$context, &$contentItem, $params = $contentItem->params, $page=null]);

	// ~ static public function getHTMLElementById($html,$attributeValue,$tagname = 'div', $attributeName = 'id')
	$possibleTagIds = array (
		array('div', 'uk-form-row  uk-form-horizontal element element-itemname', 'class'),
		array('div', 'element element-published', 'class'),
		// ~ array('select', 'categories')
	);

	$customReplacement = [
		'context' => $context,
		'possible_tag_ids' => $possibleTagIds,
		'option' => 'com_zoo',
		// ~ 'contentItem' => (object) $contentItem,
	];

		$switchSelector = '[name=\"params[config][custom_runnotificationary]\"]:checked';

		// Cannot find a way to save the notification switch state.
		// MAYBE onAfterSaveZooItem or onBeforeSaveZooItem set the state
		$replacementFieldset = '
			<fieldset id="jform_' . '{{$this->attribsField}}' . '_runnotificationary" class="radio btn-group btn-group-yesno nswitch" >
				<input type="radio" ' . '{{$checkedyes}}' . ' value="1" name="params[config][custom_runnotificationary]" id="jform_' . '{{$this->attribsField}}' . '_runnotificationary1">
				<label for="jform_' . '{{$this->attribsField}}' . '_runnotificationary1" class="btn ' . '{{$active_yes}}' . '">' . \JText::_('JYES') . '</label>
				<input type="radio" ' . '{{$checkedno}}' . ' value="0" name="params[config][custom_runnotificationary]" id="jform_' . '{{$this->attribsField}}' . '_runnotificationary0">
				<label for="jform_' . '{{$this->attribsField}}' . '_runnotificationary0" class="btn' . '{{$active_no}}' . '">' . \JText::_('JNO') . '</label>
			</fieldset>
		';

	if ($app->isAdmin())
	{
		$customReplacement['form_selector'] = 'adminForm';
	}
	else
	{
		$customReplacement['form_selector'] = 'submissionForm';
	}

	$customReplacement['replacement_fieldset'] = $replacementFieldset;
	$customReplacement['switch_selector'] = $switchSelector;

		$path = __DIR__ . '/helpers/components/zoo/tables/';
		\JTable::addIncludePath($path);
		$table = \JTable::getInstance('Item', 'ZooTable');
		$table->load($contentItem->id);

	// ~ dump($contentItemId,'$contentItemId');
		$table->elements = json_decode($table->elements);

		$textsOld = [];
		$texts = [];

	foreach ($contentItem->getElements() as $id => $element)
	{
		$elObj = $contentItem->elements->{$element->identifier};

		if (is_array($elObj))
		{
			foreach ($elObj as $k => $v)
			{
				$contentItem->{$element->identifier . '|' . $k } = $v;
			}
		}
		else
		{
			$contentItem->{$element->identifier} = $elObj;
		}

		if (get_class($element) == 'ElementTextarea')
		{
			foreach ($table->elements->{$element->identifier} as $k => $v)
			{
				if (is_array($v))
				{
					$textsOld[] = $v['value'];
				}
				else
				{
					$textsOld[] = $v->value;
				}
			}

			foreach ($contentItem->elements->{$element->identifier} as $k => $v)
			{
				if (is_array($v))
				{
					$texts[] = $v['value'];
				}
				else
				{
					$texts[] = $v->value;
				}
			}
		}
	}

	$db = \JFactory::getDbo();
	$query = $db->getQuery(true);
	$query->select('category_id');
	$query->from($db->quoteName('#__zoo_category_item'));
	$query->where($db->quoteName('item_id') . " = " . $db->quote($contentItem->id));

	$db->setQuery($query);
	$categories = $db->loadColumn();

	if (!empty($categories))
	{
		array_unshift($categories, $contentItem->params->{'config.primary_category'});
		$contentItem->catid = $categories;
	}
	else
	{
		$contentItem->catid = $contentItem->params->{'config.primary_category'};
	}

	if (isset($textsOld[0]))
	{
		$table->introtext = array_shift($textsOld);
	}

	if (!empty($textsOld))
	{
		$table->fulltext = implode(PHP_EOL, $textsOld);
	}

	$previousObject = new \stdClass;
	$previousObject->id = $table->id;
	$previousObject->title = $table->name;
	$previousObject->introtext = $table->introtext;
	$previousObject->fulltext = $table->fulltext;

	$customReplacement['previous_item'] = $previousObject;
	$customReplacement['previous_state'] = $contentItem->state;

	if (isset($texts[0]))
	{
		$customReplacement['introtext'] = array_shift($texts);
	}

	if (!empty($texts))
	{
		$customReplacement['fulltext'] = implode(PHP_EOL, $texts);
	}

	$session->set('CustomReplacement', $customReplacement,  "notificationary");

	$form = new \JForm($context);

	\JEventDispatcher::getInstance()->trigger(
		'onContentPrepareForm',
		array(
			$form,
			$contentItem
		)
	);

	return;
}

