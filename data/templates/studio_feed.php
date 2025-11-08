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

$i=1;
$qs='';
$fetch = false;
if(!isset($template)) $template='studio_entry';
if(!isset($entries)) {
    $fetch = ($entry->id===Studio::$page);
    $q = ['type'=>'entry'];
    if($fetchAll) {
        $fetch = false;
        $q = ['|Related.Parent.Related.parent'=>$entry->id] + $q;
    }
    if(!isset($hpp)) $hpp = 100;
    $entries = $entry->getChildren($q, 'feed', true);
}
if($entries && is_object($entries)) {
    if($hpp) {
        $s .= '<div class="hfeed s-entry" id="e'.$entry->id.'">'
            . $entries->paginate($hpp, 'renderEntry', array($template), true, true)
            . '</div>';
        $entries = null;
    } else {
        $entries = $entries->getItems();
        $fetch = false;
    }
}
if($fetch) {
    $template = 'studio_feed';
    $entries = $entry->getChildren(['type'=>['page', 'feed']], 'feed', false, ['Related.position'=>'asc', 'title'=>'asc']);
}
if($entries) {
    $s .= '<div class="hfeed s-entry">';
    foreach($entries as $entry) {
        $s .= $entry->renderEntry($template, ['fetchAll'=>true, 'linkEntry'=>true, 'hpp'=>3]);
        if(isset($limit) && $i++>=$limit)break;
        else if(!isset($limit))$i++;
    }
    $s .= '</div>';
}

$s = $before.$s.$after;
//$after .= '<p class="p-published" data-published="'.S::xml($m).'">'.S::date($m).'</p>';
echo $s;