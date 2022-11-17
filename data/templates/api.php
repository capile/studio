<?php
/**
 * Api App Template
 * 
 * PHP version 7.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   1.0
 */

use Studio as S;
use Studio\App;

$id = S::slug($url);
$cPrefix = $Api->config('attrClassPrefix');
$link = $url;
if(strpos($url, '?')!==false) list($url, $qs)=explode('?', $url, 2);
else $qs='';

if(isset($title)) App::response('title', $title);
if(!isset($action)) $action = $Api['action'];

$nav = null;
if($Api->config('navigation')) {
    if(!App::request('ajax') || App::request('headers', 'x-studio-navigation')) {
        $nav = $Api::listInterfaces();
    }
}

$a = [
    'class' => $cPrefix.'-app'.((isset($active) && $active) ?' '.$cPrefix.'-active' :''),
    'data-action'=>$Api['action'],
    'data-url'=>$url,
];
if($qs) $a['data-qs'] = str_replace(',', '%2C', $qs);
if($Api['id']) $a['data-id'] = $Api['id'];
if(isset($ui)) $a['data-ui'] = base64_encode(S::serialize($ui, 'json'));

if(isset($attributes) && is_array($attributes)) {
    if(isset($attributes['class'])) $a['class'] .= ' '.S::xml($attributes['class']);
    $a += $attributes;
}


// .s-api-header
?><div class="s-api-header"<?php 
    if($nav) echo ' data-toggler="off"';
    if($Api->config('headerOverflow')) echo ' data-overflow="1"';
    echo '>'; 
    if($nav) echo '<a href="'.S::xml($Api::base()).'" class="s-spacer s-left s-nav" data-draggable-style="width:{w0}"></a>';
    $urls = $Api::$urls;
    if(App::request('ajax')) {
        foreach(array_reverse($urls) as $iurl=>$t) {
            if($iurl!='/' && (!isset($t['interface']) || $t['interface'])) {
                $urls = [$iurl=>$t];
                break;
            }
        }
    }
    foreach($urls as $iurl=>$t) {
        $tclass = 's-api-title';
        $iqs='';
        if(strpos($iurl, '?')!==false) list($iurl, $iqs)=explode('?', $iurl, 2);
        if($iurl==$url) $tclass .= ' s-api-title-active';
        $taction = (isset($t['action']) && $t['action']) ?$t['action'] :'text';
        $tclass .= ' '.$cPrefix.'--'.$taction;
        if($iurl!='/' && (!isset($t['interface']) || $t['interface'])):
            ?><a href="<?php echo $iurl ?>" class="<?php echo $tclass; ?>" data-url="<?php echo $iurl ?>"<?php if($iqs) echo 'data-qs="', str_replace(',', '%2C', S::xml($iqs)), '"' ?>><span class="s-text"><?php echo S::xml($t['title']); ?></span></a><?php
        endif;
        unset($tclass, $taction);
    }

?></div><?php

// .s-api-body
?><div class="s-api-body"><?php

    if($nav) {
        $nclass = 's-api-nav s-toggle-active';
        echo '<div id="s-nav" data-draggable-style="width:{w0}" data-draggable-default=style="width:{w1}" class="', $nclass, '" data-base-url="', $Api::base(), '" data-toggler-attribute-target=".s-api-header" data-toggler-drag-target=".s-api-body" data-toggler-drag=".s-nav,.s-api-nav,.s-api-app.s-api-active" data-toggler-options="child,sibling,storage,draggable" data-toggler-default="800">', $nav, '</div>'; 
    }

    // .s-api-app
    ?><div<?php foreach($a as $k=>$v) echo ' '.S::slug($k, '-_').'="'.S::xml($v).'"'; ?>><?php
        // .s-api-actions
        if(!isset($buttons)) $buttons = null;
        if($buttons): ?><div class="<?php echo trim('s-api-actions '.$Api->config('attrButtonsClass')); ?>"><?php
            ?><input type="checkbox" id="s-api-b-<?php echo $id; ?>" class="s-switch s-api-actions" /><label for="s-api-b-<?php echo $id; ?>"><?php
            echo $Api::t('labelActions'); ?></label><div class="s-buttons s-switched"><?php
                echo $buttons; 
        ?></div></div><?php endif; 

        // .s-api-container
        ?><div class="s-api-container"><?php 

            if($title && $Api->config('breadcrumbs')) {
                $urls = $Api::$urls;
                if(!$urls) {
                    $urls = array(array('title'=>$title));
                }
                $b = '';
                $la = $Api->config('actionAlias', 'list');
                if(!$la) $la = 'list';
                foreach($urls as $iurl=>$t) {
                    $ltitle = (isset($t['icon'])) ?'<img src="'.S::xml($t['icon']).'" title="'.S::xml($t['title']).'" />' :S::xml($t['title']);
                    if($iurl && $iurl!=$link && !($t['title']==$title && $link=$iurl.'/'.$la)) {
                        $b .= '<a href="'.$iurl.'">'.$ltitle.'</a>';
                    } else {
                        $b .= '<span>'.$ltitle.'</span>';
                        break;
                    }
                }

                if($b) {
                    echo str_replace('$LABEL', $b, $Api->config('breadcrumbTemplate'));
                }
            }

            if(isset($options['before-'.$action])) echo S::markdown($options['before-'.$action]);
            else if(isset($options['before'])) echo S::markdown($options['before']);


            ?><div class="s-api-summary <?php echo $cPrefix, '--', $Api['action']; ?>"><?php

                if(isset($summary)) {
                    echo $summary;
                    App::response('summary', $summary);
                }

                echo $Api->message();

                if(isset($app)) echo $app;

                if(isset($list) && ($g=$Api->renderGraph())):
                    ?><div class="<?php echo $Api->config('attrGraphClass'); ?>"><?php
                        echo $g;
                    ?></div><?php
                endif;


                if(isset($list)) {
                    if(isset($searchForm))
                        if(isset($options['before-search-form'])) echo S::markdown($options['before-search-form']);
                        echo '<div class="'.$cPrefix.'-search">'.$searchForm.'</div>';
                        if(isset($options['after-search-form'])) echo S::markdown($options['after-search-form']);
                    // list counter
                    echo '<span class="'.$Api->config('attrCounterClass').'">';
                    if(isset($searchCount)) {
                        if($searchCount<=0) {
                            echo sprintf($Api::t('listNoSearchResults'), S::number($count,0), $searchTerms);
                        } else if($searchCount==1) {
                            echo sprintf($Api::t('listSearchResult'), S::number($count,0), $searchTerms);
                        } else { 
                            echo sprintf($Api::t('listSearchResults'), S::number($searchCount,0), S::number($count,0), $searchTerms);
                        }
                        $count = $searchCount;
                    } else if($count) {
                        echo sprintf($Api::t(($count>1)?('listResults'):('listResult')), S::number($count,0));
                    } else {
                        echo $Api::t('listNoResults');
                    }

                    if($count>1) {
                        $end = $listOffset + $listLimit;
                        if($end>$count) $end = $count;
                        echo ' ',sprintf($Api::t('listCounter'), S::number($listOffset+1,0), S::number($end,0));
                        unset($end);
                    }
                    echo '</span>';

                }

            ?></div><?php 

            if(isset($list)): 
                ?><div class="<?php echo $cPrefix, '-list'; ?>"><?php
                    if(is_string($list)) {
                        echo $list;
                    } else if($count>0) {
                        $listRenderer = (isset($options['list-renderer']) && $options['list-renderer']) ?$options['list-renderer'] :'renderUi';
                        $sn = S::scriptName(true);
                        S::scriptName($Api->link());
                        if(!is_object($list)) {
                            $list = new Tecnodesign_Collection($list, $Api->getModel());
                        }
                        echo $list->paginate($listLimit, $listRenderer, array('options'=>$options), $Api->config('listPagesOnTop'), $Api->config('listPagesOnBottom'));
                        S::scriptName($sn);
                        unset($sn);
                    }

                ?></div><?php
            endif;

            if(isset($preview)): 
                ?><div class="<?php echo $cPrefix, '-preview'; ?>"><?php
                    $next = null;
                    if(in_array($Api['action'], ['update', 'preview', 'new']) && !$Api->config('standalone')) {
                        $next = ($Api['action']=='update')?('preview'):('update');
                        if(!isset($Api['actions'][$next]) || (isset($Api['actions'][$next]['auth']) && !$Api::checkAuth($Api['actions'][$next]['auth']))) {
                            $next = null;
                        }
                    }
                    if($next) {
                        echo '<div data-action-schema="'.$next.'" data-action-url="'.$Api->link($next).'" class="i--'.$api.((isset($class))?(' '.$class):('')).'">';
                    } else {
                        echo '<div class="i--'.$api.((isset($class))?(' '.$class):('')).'">';
                    }

                    if(is_object($preview) && method_exists($preview, 'renderScope')) {
                        $box = $preview::$boxTemplate;
                        $preview::$boxTemplate = $Api->config('boxTemplate');
                        $excludeEmpty=(isset($options['preview-empty'])) ?!$options['preview-empty'] :null;
                        $showOriginal=(isset($options['preview-original'])) ?$options['preview-original'] :null;
                        echo $preview->renderScope($options['scope'], $xmlEscape, false, $Api->config('previewTemplate'), $Api->config('headingTemplate'), $excludeEmpty, $showOriginal);
                        $preview::$boxTemplate = $box;
                        unset($preview);
                    } else {
                        echo (string) $preview;
                    }
                    unset($preview);
                    echo '</div>';
                ?></div><?php
            endif;


            if(isset($options['after-'.$action])) echo S::markdown($options['after-'.$action]);
            else if(isset($options['after'])) echo S::markdown($options['after']);

            // .s-api-actions
            ?></div><div class="<?php echo $cPrefix, '-footer'; ?>"><div class="s-buttons"><?php
                echo $buttons; 
            ?></div></div><?php 

?></div></div>