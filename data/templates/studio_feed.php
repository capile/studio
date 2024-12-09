<?php
/**
 * Default News Feed Template
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

$s = null;
$after='';$before='';
$pub = null;
if($entry && ($meta=Studio::config('render_meta'))) {
    $s .= $entry->renderMeta($meta);
    if(!is_array($meta) || in_array('published', $meta) && ($m=$entry->published)) $pub = S::strtotime($m);
}

$s .= '<div class="hfeed s-entry" id="e'.$entry->id.'">';
$i=1;
$qs='';
if(!isset($template)) $template='studio_entry';
if(!isset($entries)) {
    $entries = $entry->getChildren(['type'=>'entry'], 'link', true);
}
if($entries && is_object($entries)) {
    if($hpp) {
        $s .= $entries->paginate($hpp, 'renderEntry', array($template), true, true);
        $entries = $entry->getChildren(); // show subpages afterwards
    } else {
        $entries = $entries->getItems();
    }
}
if($entries) {
    foreach($entries as $entry) {
        $s .= $entry->renderEntry($template);
        if(isset($limit) && $i++>=$limit)break;
        else if(!isset($limit))$i++;
    }
}
$s = $before.$s.'</div>'.$after;
//$after .= '<p class="p-published" data-published="'.S::xml($m).'">'.S::date($m).'</p>';
echo $s;