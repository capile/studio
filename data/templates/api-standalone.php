<?php
/**
 * Standalone API/App
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */

use Studio as S;
use Studio\App;
use Studio\Model;
use Tecnodesign_Form as Form;

$id = S::slug($url);
$cPrefix = $Api->config('attrClassPrefix');
$link = $url;
if(strpos($url, '?')!==false) list($url, $qs)=explode('?', $url, 2);
else $qs='';

if($title) App::response('title', $title);
if(!isset($action)) $action = $Api['action'];

// .s-api-app
?><div class="<?php echo $cPrefix ?>-standalone" data-base-url="<?php echo $Api->getUrl(); ?>" data-url="<?php echo $url ?>"<?php 
    if($qs) echo ' data-qs="',str_replace(',', '%2C', S::xml($qs)),'"';
    if($Api['id']) echo ' data-id="',S::xml($Api['id']),'"';
    ?>><?php

    if($title && $Api::$breadcrumbs) {
        $urls = $Api::$urls;
        if(!$urls) {
            $urls = array(array('title'=>$title));
        } else {
            array_splice($urls,0, 1);
        }
        $b = '';
        $la = ($Api::$actionAlias && isset($Api::$actionAlias['list']))?($Api::$actionAlias['list']):('list');
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
            echo str_replace('$LABEL', $b, $Api::$breadcrumbTemplate);
        }
    }

    if(isset($options['before-'.$Api['action']])) echo S::markdown($options['before-'.$Api['action']]);
    else if(isset($options['before'])) echo S::markdown($options['before']);
    $content = false;

    ?><div class="<?php echo $cPrefix, '-summary ', $cPrefix, '--', $Api['action']; ?>"><?php

        if(!$Api::$standalone && isset($summary)) {
            echo $summary;
            App::response('summary', $summary);
        }

        echo $Api->message(), (isset($app))?($app):('');

        if($buttons && $Api::$listPagesOnTop): ?><div class="<?php echo $cPrefix ?>-standalone-buttons"><?php
            echo $buttons; 
        ?></div><?php endif;

        if(isset($list)) {
            // list counter
            if(isset($searchForm)) {
                if(isset($options['before-search-form'])) echo S::markdown($options['before-search-form']);
                echo '<div class="'.$Api->config('attrSearchClass').'">'.$searchForm.'</div>';
                if(isset($options['after-search-form'])) echo S::markdown($options['after-search-form']);
                $content = true;
            }

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
                echo sprintf($Api::t('listCounter'), S::number($listOffset+1,0), S::number($end,0));
                unset($end);
            }
            echo '</span>';
        }

    ?></div><?php 

    if(isset($list)): 
        ?><div class="<?php echo $cPrefix, '-list'; ?>"><?php
            if(is_string($list)) {
                echo $list;
                $content = true;
            } else if($count>0) {
                $options['checkbox'] = false;
                $options['radio'] = false;
                if($key=$Api['key']) $options['key'] = $key;
                $listRenderer = (isset($options['list-renderer']) && $options['list-renderer']) ?$options['list-renderer'] :'renderUi';
                $listOptions = (isset($options['list-options']) && is_array($options['list-options'])) ?$options['list-options'] +$options :$options;
                $sn = S::scriptName(true);
                S::scriptName($Api->link());
                echo $list->paginate($listLimit, $listRenderer, array('options'=>$listOptions), $Api->config('listPagesOnTop'), $Api->config('listPagesOnBottom'));
                S::scriptName($sn);
                unset($sn);
                $content = true;
            }

        ?></div><?php
    endif;

    if(isset($preview)): 
        ?><div class="<?php echo $cPrefix; ?>-preview"><?php
            if(is_object($preview) && method_exists($preview, 'renderScope')) {
                $box = $preview::$boxTemplate;
                $preview::$boxTemplate = $Api::$boxTemplate;
                $excludeEmpty=(isset($options['preview-empty'])) ?!$options['preview-empty'] :null;
                $showOriginal=(isset($options['preview-original'])) ?$options['preview-original'] :null;
                echo $preview->renderScope($options['scope'], $xmlEscape, false, $Api::$previewTemplate, $Api::$headingTemplate, $excludeEmpty, $showOriginal);
                $preview::$boxTemplate = $box;
                unset($preview);
            } else if(is_object($preview) && $preview instanceof Form) {
                if($buttons && $Api::$listPagesOnTop) {
                    $preview->buttons[] = $buttons;
                    $buttons = null;
                }
                echo (string) $preview;
            } else {
                echo (string) $preview;
            }
            unset($preview);
            $content = true;
        ?></div><?php
    endif;

    // .s-api-actions
    if($buttons && $content && $Api::$listPagesOnBottom): ?><div class="s-api-standalone-buttons"><?php
        echo $buttons; 
    ?></div><?php endif;

    if(isset($options['after-'.$Api['action']])) echo S::markdown($options['after-'.$Api['action']]);
    else if(isset($options['after'])) echo S::markdown($options['after']);

?></div><?php
