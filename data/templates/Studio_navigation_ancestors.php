<?php
/**
 * Ancestors Navigation List
 * 
 * PHP version 7.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   1.0
 */
$e = Studio\Studio::$response['entry'];
$s = '';
while($e) {
    $title = $e['title'];
    $s = ($s)?(Studio\Studio::$breadcrumbSeparator.$s):($s);
    $s = '<a href="'.Studio::xml($e['link']).'">'.Studio::xml($title).'</a>'.$s;
    $e = $e->getParent();
}

if($s) $s = '<p class="breadcrumb">'.$s.'</p>';
echo $s;
