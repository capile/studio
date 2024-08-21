<?php
/**
 * Default media template
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */

if(isset($href))
    echo '<a href="'.Studio::xml($href).'">';

echo "<img src=", Studio::xml($src), '"';
if(isset($alt)) 
    echo ' alt="', Studio::xml($alt), '"';

if(isset($title))
    echo ' title="', Studio::xml($title), '"';

if(isset($id))
    echo ' id="', Studio::xml($id), '"';


echo ' />';

if(isset($href)) 
    echo '</a>';
