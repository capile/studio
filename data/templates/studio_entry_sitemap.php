<?php
/**
 * Sitemap/Page hierarchy template
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */
use Studio as S;
use Studio\Studio;
$s = '<li id="e'.$id.'"'.((Studio::$page===$id) ?' class="current"' :'').'>'
   . (($link)?('<a href="'.S::xml($link).'">'.S::xml($title).'</a>'):(S::xml($title)));

if($C=$entry->getChildren()) {
    if(!isset($template)) $template = basename(__FILE__, '.php');
    $s .= '<ul>';
    foreach($C as $i=>$o) {
        $s .= $o->renderEntry($template);
    }
    $s .= '</ul>';
}

$s .= '</li>';

echo $s;
