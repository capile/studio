<?php
/**
 * Subform field template
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */

use Studio as S;

if(isset(S::$variables['form-field-f__'.$id])) {
    $tpl = S::$variables['form-field-f__'.$id];
} else if(isset(S::$variables['form-field-template'])) {
    $tpl = S::$variables['form-field-template'];
} else {
    $tpl = '<div id="f__$ID" class="field $CLASS">$ERROR<p class="label subform">$LABEL</p><div class="input">$INPUT</div></div>';
}

if(isset($tooltip)) {
    $class .= ' s-has-tooltip';
    $label = '<span class="s-tooltip" data-tooltip="'.S::xml($tooltip).'">'.$label.'</span>';
}

if($error) {
    if(!is_array($error)) $error=array($error);
    $err = '<span class="error">'
          . implode('</span><span class="error">', $error)
          . '</span>';
} else {
    $err = '';
}

$class .= ' i-subform';

echo 
    $before,
    str_replace(
        array('$UID', '$ID', '$CLASS', '$LABEL', '$INPUT', '$ERROR'),
        array($id, preg_replace('/.*_[0-9§]+_/', '', $id), $class, $label, $input, $err),
        $tpl), 
    $after;
