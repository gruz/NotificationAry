<?php
/**
 * Bridge to tie NotificationAry and Zoo
 *
 * @package		NotificationAry
 * @author Gruz <arygroup@gmail.com>
 * @copyright	Copyleft - All rights reversed
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );
static function onEditZooItem($event)
{
	$contentItem = $event->getSubject();

	if (!\NotificationAry\HelperClasses\NotificationAryHelper::isZooEditPage($contentItem))
	{
		return;
	}

// ~ dump($event);
// ~ dump($event->getName(), 'getName');
// ~ dump($event->getParameters(), 'getParameters');
// ~ dump($event->getReturnValue(), 'getReturnValue');
// ~ dump($event->getSubject(), 'getSubject');

// ~ dump($event->isProcessed(), 'isProcessed');

// ~ dump($event->getSubject(),'$contentItem');
// ~ dump($event->getSubject()->app->table->item->first(),'table');



	$session = \JFactory::getSession();
	$session->clear('CustomReplacement', "notificationary");

	$context = 'com_zoo.item';

/*
			JLoader::register('ItemTable', JPATH_ROOT . JPATH_ADMINISTRATOR . '/components/com_zoo/tables/item.php');
			$app = App::getInstance('zoo');
			// ~ $app = $event->getApplication();

			$dataModel = new ItemTable($app);
dump($contentItem->id,'$contentItem->id]');
			// ~ $options = array('conditions' => ['id' => $contentItem->id]);
			// ~ $dataModel->find('first', $options);
			$a = $dataModel->getByIds($contentItem->id);

dump($a,'$dataModel');
*/

	// ~ $jinput = JFactory::getApplication()->input;
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

	$app = JFactory::getApplication();


	// ~ JDispatcher::getInstance()->trigger('onContentPrepare', [$context, &$contentItem, $params = $contentItem->params, $page=null]);


// ~ static public function getHTMLElementById($html,$attributeValue,$tagname = 'div', $attributeName = 'id')
	$possible_tag_ids = array (
		array('div', 'uk-form-row  uk-form-horizontal element element-itemname', 'class'),
		array('div', 'element element-published', 'class'),
		// ~ array('select', 'categories')
	);



	$CustomReplacement = [
		'context' => $context,
		'possible_tag_ids' => $possible_tag_ids,
		'option' => 'com_zoo',
		// ~ 'contentItem' => (object) $contentItem,
	];


		$switch_selector = '[name=\"params[config][custom_runnotificationary]\"]:checked';

		// Cannot find a way to save the notification switch state.
		// MAYBE onAfterSaveZooItem or onBeforeSaveZooItem set the state
		$replacement_fieldset = '
			<fieldset id="jform_' . '{{$this->attribsField}}' . '_runnotificationary" class="radio btn-group btn-group-yesno nswitch" >
				<input type="radio" ' . '{{$checkedyes}}' . ' value="1" name="params[config][custom_runnotificationary]" id="jform_' . '{{$this->attribsField}}' . '_runnotificationary1">
				<label for="jform_' . '{{$this->attribsField}}' . '_runnotificationary1" class="btn ' . '{{$active_yes}}' . '">' . JText::_('JYES') . '</label>
				<input type="radio" ' . '{{$checkedno}}' . ' value="0" name="params[config][custom_runnotificationary]" id="jform_' . '{{$this->attribsField}}' . '_runnotificationary0">
				<label for="jform_' . '{{$this->attribsField}}' . '_runnotificationary0" class="btn' . '{{$active_no}}' . '">' . JText::_('JNO') . '</label>
			</fieldset>
		';

	if ($app->isAdmin())
	{
		$CustomReplacement['form_selector'] = 'adminForm';
	}
	else
	{
		$CustomReplacement['form_selector'] = 'submissionForm';
	}

	$CustomReplacement['replacement_fieldset'] = $replacement_fieldset;
	$CustomReplacement['switch_selector'] = $switch_selector;



// ~ dump($contentItem,'$contentItem');
// ~ dump($contentItem->getCoreElements(),'$contentItem getCoreElements');
// ~ dump($contentItem->getElements(),'$contentItem getElements');
// ~ dump($contentItem->getParams(),'$contentItem getParams');
// ~ dump($contentItem->getSubmittableElements(),'$contentItem getSubmittableElements');


		$path = __DIR__ . '/helpers/components/zoo/tables/';
		JTable::addIncludePath($path);
		$table = JTable::getInstance('Item', 'ZooTable');
		$table->load($contentItem->id);
// ~ dump($contentItemId,'$contentItemId');
		$table->elements = json_decode($table->elements);

		$texts_old = [];
		$texts = [];
		foreach ($contentItem->getElements() as $id => $element)
		{
			$el_obj = $contentItem->elements->{$element->identifier};

			if (is_array($el_obj))
			{
				foreach ($el_obj as $k => $v)
				{
					$contentItem->{$element->identifier . '|' . $k } = $v;
				}
			}
			else
			{
				$contentItem->{$element->identifier} = $el_obj;
			}

			if (get_class($element) == 'ElementTextarea')
			{
				foreach ($table->elements->{$element->identifier} as $k => $v)
				{
					if (is_array($v))
					{
						$texts_old[] = $v['value'];
					}
					else
					{
						$texts_old[] = $v->value;
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
		$query->where($db->quoteName('item_id')." = ".$db->quote($contentItem->id));

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


		if (isset($texts_old[0]))
		{
			$table->introtext = array_shift($texts_old);
		}

		if (!empty($texts_old))
		{
			$table->fulltext = implode(PHP_EOL, $texts_old);
		}

		$previousObject = new stdClass;
		$previousObject->id = $table->id;
		$previousObject->title = $table->name;
		$previousObject->introtext = $table->introtext;
		$previousObject->fulltext = $table->fulltext;

		$CustomReplacement['previous_item'] = $previousObject;
		$CustomReplacement['previous_state'] = $contentItem->state;

		if (isset($texts[0]))
		{
			$CustomReplacement['introtext'] = array_shift($texts);
		}

		if (!empty($texts))
		{
			$CustomReplacement['fulltext'] = implode(PHP_EOL, $texts);
		}

	$session->set('CustomReplacement', $CustomReplacement,  "notificationary");

	$form = new JForm($context);

	\JDispatcher::getInstance()->trigger(
			'onContentPrepareForm',
			array(
					$form,
					$contentItem
			)
		);

return;

	if (count($cid) == 1 && $cid[0] == $contentItemId)
	{

		return;
dump($event, 'event');

		$context = 'com_zoo.item';
		self::_setContext($context);

		$jinput = \JFactory::getApplication()->input;


		// Prepare to imitate onContentPrepareForm {
		$this->_prepareParams();
		$context = 'com_k2.item';
		$this->allowed_contexts[] = $context;
		self::_setContext($context);

		self::$shouldShowSwitchCheckFlag = false;
		$contentItem = $this->_getContentItemTable($context);
		$contentItem->load($jinput->get('cid', 0));

		jimport('joomla.form.form');
		$form = \JForm::getInstance('itemForm', JPATH_ADMINISTRATOR . '/components/com_k2/models/item.xml');
		$values = array('params' => json_decode($contentItem->params));
		$form->bind($values);

		// Prepare to imitate onContentPrepareForm }

		$this->onContentPrepareForm($form, $contentItem);
		$rules = $this->_leaveOnlyRulesForCurrentItem($context, $contentItem, 'showSwitch');

		if (empty($rules))
		{
			return;
		}

		self::$shouldShowSwitchCheckFlag = true;

		// Is set for onAfterContentSave as onContentPrepareForm is not run, but this method onAfterRender runs after onContentAfterSave.
		$session->set('shouldShowSwitchCheckFlagK2Special', true, $this->plg_name);

		// If the NS should be shown but cannot be shown due to HTML layout problems, then we need to know default value
		$rule = array_pop($rules);

		$session->set('shouldShowSwitchCheckFlagK2SpecialDefaultValue', (bool) $rule->notificationswitchdefault, $this->plg_name);
		//return $this->onContentPrepareForm($this->form, $contentItem);
	}


return;

	$contentItem = $event->getSubject();
dump($contentItem,'$contentItem');
}

