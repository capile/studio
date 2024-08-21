<?php
/**
 * Button form field template
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */

if($error) {
    if(!is_array($error)) $error=array($error);
    $err = '<span class="error">'
          . implode('</span><span class="error">', $error)
          . '</span>';
} else {
    $err = '';
}

echo 
    $before,
    $input,
    $err,
    $after;

