<?php
/**
 * Form template
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */

use Studio as S;

$fieldsets=array();
$hasFieldset = false;
$fs = '';
foreach($fields as $fn=>$fo) {
    if($s=$fo->render()) {
        if((string)$fo->fieldset!=='1') $fs = (string)$fo->fieldset;
        if(!$hasFieldset && $fs) $hasFieldset = true;

        if(!$fs) {
            $fieldsets[] = $s;
        } else {
            if(!isset($fieldsets[$fs])) $fieldsets[$fs]='';
            $fieldsets[$fs] .= $s;
        }
    }
}

if(!isset($before)) $before = '';
if(!isset($after)) $after = '';
if(isset($limits) && $limits) {
    if(isset($limits['error']) && $limits['error']) {
        $before .= '<p class="s-msg s-msg-error">'.S::xml($limits['error']).'</p>';
    } else if(isset($limits['warn']) && $limits['warn']) {
        $before .= '<p class="s-msg s-msg-warn">'.S::xml($limits['warn']).'</p>';
    }
    if(isset($limits['fields'])) {
        foreach($limits['fields'] as $fn=>$fo) {
            if($s=$fo->render()) {
                if(isset($fo->fieldset)) {
                    $fs = (string) $fo->fieldset;
                    if(!$hasFieldset && $fs) $hasFieldset = true;
                }
                if(!$fs) {
                    $fieldsets[] = $s;
                } else {
                    if(!isset($fieldsets[$fs])) $fieldsets[$fs]='';
                    $fieldsets[$fs] .= $s;
                }
            }
        }
    }
}

if($hasFieldset) {
    $attributes['class'] .= ' s-fieldset';
}

?><form<?php if($id): ?> id="<?php echo $id ?>"<?php endif; ?> action="<?php echo S::xml($action) ?>" method="<?php echo $method ?>"<?php
if(isset($Form) && $Form) $attributes = $Form->attributes;
foreach($attributes as $an=>$av) echo ' '.S::xml($an).'="'.S::xml($av).'"';
?>><?php

foreach($fieldsets as $fn=>$fv) {
    if(!$fv) continue;
    if(!is_int($fn) && !S::isempty($fn)) {
        $fl = (substr($fn, 0, 1)=='*')?(S::t(substr($fn,1), 'form')):($fn);
        echo '<fieldset id="'.S::slug($fn).'"><legend>'.$fl.'</legend>'.$before.$fv.'</fieldset>';
    } else {
        echo $before.$fv;
    }
    if($before) $before = '';
}

if(isset($limits['recaptcha']) && $limits['recaptcha']) {
    $rc = $limits['recaptcha'];
    $rckey = null;
    if(is_array($rc) && isset($rc['site-key'])) $rckey = $rc['site-key'];
    else if(isset(S::$variables['recaptcha-site-key'])) $rckey = S::$variables['recaptcha-site-key'];
    if($rckey) {
        if(!isset($rc['submit']) || !$rc['submit']) {
            echo '<div class="s-recaptcha" data-sitekey="'.S::xml($rckey).'"></div>';
        } else if(isset($buttons['submit'])) {
            echo '<script src="https://www.google.com/recaptcha/api.js"></script>';
            if(!is_array($buttons['submit'])) $buttons['submit'] = ['label'=>$buttons['submit'], 'attributes'=>[]];
            else if(!isset($buttons['submit']['attributes'])) $buttons['submit']['attributes'] = [];

            if(isset($buttons['submit']['attributes']['class'])) $buttons['submit']['attributes']['class'] .= ' g-recaptcha';
            else $buttons['submit']['attributes']['class'] = 'g-recaptcha';
            $buttons['submit']['attributes']['data-sitekey'] = S::xml($rckey);
        }
    }
}

if($buttons): ?>
<p class="ui-buttons"><?php
foreach($buttons as $bn=>$label) {
    $bt = ($bn=='submit')?('submit'):('button');
    $a = ' type="'.$bt.'"';
    if(is_array($label)) {
        if(isset($label['attributes'])) {
            foreach($label['attributes'] as $n=>$v) {
                $a .= ' '.S::xml($n).'="'.S::xml($v).'"';
                unset($label['attributes'][$n], $n, $v);
            }
        }
        if(isset($label['label'])) $label = $label['label'];
        else $label = $bn;
    }
    if(substr($label, 0, 1)=='*') {
        $label = S::t(substr($label, 1), 'form');
    }
    if(strpos($bn, '/')!==false) {
        echo '<a href="'.$bn.'">'.$label.'</a>';
    } else if($bn!=='submit' && preg_match('#\<(a|button|input)[\s/]#', $label)) {
        echo $label;
    } else {
        echo '<button class="'.$bn.'"'.$a.'>'.$label.'</button>';
    }
}
?></p><?php endif; echo $after; ?></form>