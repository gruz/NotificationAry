<?php
/*
 * @package Joomla
 * @copyright Copyright (C) 2005 Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 *
 * @component Phoca Component
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */
defined('_JEXEC') or die();

class PhocaGalleryControllerCategory extends PhocaGalleryControllerCategoryDefault
{
    public function save($catid, $filename, $return, &$succeeded, &$errSaveMsg, $redirect=true, $ytbData = array()) {
        $result = parent::save($catid, $filename, $return, $succeeded, $errSaveMsg, $redirect, $ytbData);
dump('here');
        return $result;

    }
}
