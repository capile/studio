<?php
/**
 * Default content template
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
use Studio\Model\Schema;

$schema=false;
$s = null;
$before = null;
$after = null;
$scope = ['text'=>['format'=>'markdown'], 'html'=>['format'=>'html']];
if($Content && ($S=Schema::find(['|id'=>$Content->content_type, '|aliases*='=>'"'.$Content->content_type.'"', 'enable_content'=>1],1))) {
    $scope = $S->formOverlay();
    if($S->base) {
        $schema = true;
        if(isset($entry) && $entry && $entry==Studio::$page && !Studio::$schema) {
            Studio::$schema = $S->base;
        } else if(isset($Content)) {
            $Content->attributes += ['itemscope' => null, 'itemtype'=>$S->base ];
        } else {
            $before = '<div itemscope itemtype="'.S::xml($S->base).'">';
            $after = '</div>';
        }
    }
}

foreach($scope as $fn=>$fd) {
    if(!isset($$fn) || !($v = $$fn)) continue;
    $escape = true;
    $el = 'p';
    $a = ['class'=>'p-'.$fn];
    if($schema) $a['itemprop'] = $fn;
    if(isset($fd['format'])) {
        if($fd['format']=='html') {
            $el = 'div';
            $escape = false;
            $v = trim(S::safeHtml($v));
        } else if($fd['format']=='textarea' || $fd['format']=='markdown') {
            $el = 'div';
            $v = trim(S::markdown($v));
            $escape = false;
        } else if($fd['format']=='datetime' || $fd['format']=='date') {
            $a[$fd['format']] = $v;
            $v = S::date($v);
        }
    }
    if(!$v) continue;
    if(is_array($v)) $v = implode(', ', $v);
    $s .= '<'.$el;
    foreach($a as $n=>$m) $s .= ' '.$n.'="'.S::xml($m).'"';
    $s .= '>'
        . (($escape) ?S::xml($v) :$v)
        . '</'.$el.'>';
}


echo $before.$s.$after;