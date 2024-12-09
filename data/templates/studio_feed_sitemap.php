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
$s = '<nav class="studio-feed" id="e'.$entry->id.'">';
$after='';$before='';
$i=1;
$qs='';
if(!isset($template)) $template='studio_entry';
if(isset($linkhome) && $linkhome) {
    $s .= $entry->renderEntry($template);
    $i++;
}
if($entries && is_object($entries)) {
    $entries = $entries->getItems();
}
if($entries) {
    $s .= '<ul>';
    foreach($entries as $entry) {
        $s .= $entry->renderEntry($template);
        if($limit && $i++>=$limit)break;
        else if(!isset($limit))$i++;
    }
    $s .= '</ul>';
}
$s = $before.$s.'</nav>'.$after;
echo $s;