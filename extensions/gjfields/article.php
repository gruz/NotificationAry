<?php
/**
 * @package     GJFileds
 *
 * @copyright   Copyright (C) All rights reversed.
 * @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL or later
 */

defined('JPATH_BASE') or die;
// Legacy support for AutoReadMore. Should be removed together with the next autoreadmore versions
if (!class_exists('JFormFieldModalArticle'))  {	include ('modalarticle.php'); }
class JFormFieldModal_Article extends JFormFieldModalArticle { }
