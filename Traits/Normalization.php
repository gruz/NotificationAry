<?php
/**
 * Normalization
 *
 * @package     NotificationAry
 *
 * @author      Gruz <arygroup@gmail.com>
 * @copyright   Copyleft (є) 2018 - All rights reversed
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */


namespace NotificationAry\Traits;

/**
 * A helper trait
 *
 * @since 0.2.17
 */
trait Normalization
{
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

		$session = \JFactory::getSession();
		$CustomReplacement = $session->get('CustomReplacement', null, $this->plgName);

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
	public function contextAliasReplace($context, $contentItem = false)
	{
		$this->realContext = $context;
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

			/** ##mygruz20180405023855 {
			 * It seems, that $contentItem->extension is never used used. May oudated code
			 * Let it stay here for a while
			It was:
			if ($contentItem && !empty($contentItem->extension) && $context == 'com_categories.category')
			{
				$context = $contentItem->extension . '.category';
			}
			It became: */
			/** ##mygruz20180405023855 } */

			if (strpos($context, 'com_categories.categorycom_') === 0)
			{
				return str_replace('com_categories.category', '', $context) . '.category';
			}

			$session = \JFactory::getSession();
			$formContext = $session->get('FormContext', null, $this->plgName);

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

}
