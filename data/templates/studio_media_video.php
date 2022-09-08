<?php
/**
 * Default media template
 * 
 * PHP version 7.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   1.0
 */

$meta = '';
if(is_array($src)) {
    if(isset($title))
        echo "<h3 class=\"video-label\">", Studio::xml($title), "</h3>";

    echo '<video';
    if(isset($id))
        echo ' id="', Studio::xml($id), '"';
    if(isset($attributes)) {
        foreach($attributes as $k=>$v)
            echo ' ', Studio::xml($k), '="', Studio::xml($v), '"';
    }
    echo '>';

    foreach($src as $i=>$o) {
        echo '<source';
        if(!is_array($o)) {
            echo ' src="', Studio::xml($o), '"';
            $meta .= '<meta property="og:video" content="'.Studio::xml($o).'" />';
        } else {
            if(isset($o['src'])) {
                $meta .= '<meta property="og:video" content="'.Studio::xml($o['src']).'" />';
                if(isset($o['type']))
                    $meta .= '<meta property="og:video:type" content="'.Studio::xml($o['type']).'" />';
            }
            foreach($o as $k=>$v)
                echo ' ', Studio::xml($k), '="', Studio::xml($v), '"';
        }
        echo '></source>';
    }
    if(isset($alt)) 
        echo Studio::xml($alt);

    echo '</video>';

} else {
    echo '<video src="', Studio::xml($src), '"';
    if(isset($alt)) 
        echo ' alt="', Studio::xml($alt), '"';

    if(isset($title))
        echo ' title="', Studio::xml($title), '"';

    if(isset($id))
        echo ' id="', Studio::xml($id), '"';

    if(isset($attributes)) {
        foreach($attributes as $k=>$v)
            echo ' ', Studio::xml($k), '="', Studio::xml($v), '"';
    }
    echo '></video>';
    $meta .= '<meta property="og:video" content="'.Studio::xml($src).'" />';
}

Studio::meta('', true);
Studio::meta($meta);
