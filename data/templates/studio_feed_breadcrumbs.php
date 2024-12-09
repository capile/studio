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
use Studio\Model\Entries;

$s = null;
$id = $entry->id;
$E = $entry;
$valid = false;
if(isset(Studio::$response['entry']) && Studio\Studio::$response['entry']) {
    $E = Studio\Studio::$response['entry'];
} else if(Studio::$page) {
    $E = Entries::find(['id'=>Studio::$page],1,['title', 'published', 'link']);
}
$limit = 10;
if($E) {
    $q = ['type'=>['page', 'feed']];
    if(in_array($E->type, $q['type'])) {
        $s = (($E->link) ?'<a href="'.S::xml($E->link).'">'.S::xml($E->title).'</a>' :S::xml($E->title));
    }
    while($E = $E->getParent('link', $q)) {
        if($E->id==$id) {
            $valid = true;
            break;
        }
        $s = (($E->link) ?'<a href="'.S::xml($E->link).'">'.S::xml($E->title).'</a>' :S::xml($E->title))
           . (($s) ?Studio::$breadcrumbSeparator :'')
           . $s;
    }
}

if(!$valid) $s = null;

if($s) {
    $s = '<nav class="p-breadcrumbs" id="e'.$entry->id.'">'.$s.'</nav>';
}

echo $s;