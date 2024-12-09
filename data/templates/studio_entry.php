<?php
/**
 * Default entry template
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */
$class='';$sf='';$dim='.200x100';
$figures = $entry->getContents(array('content_type'=>'media'));
if($figures && count($figures)>0) {
    foreach($figures as $content){
        $imgid=$content->id;
        $fig = $content->getContents();
        if(!isset($fig['src']))continue;
        $src=$fig['src'];
        if(strpos($src,$dim)===false) {
            if(preg_match('/^(.+)(\.[a-z]{3,4})$/',$src,$m))
              $src=$m[1].$dim.$m[2];
            else
              $src.=$dim;
        }
        $sf .= '<figure id="fig'.$imgid.'">';
        $fig += array('alt'=>'');
        $sf .= '<img alt="'.Studio::xml($fig['alt']).'" src="'.Studio::xml($src).'" border="0" />';
        if(isset($fig['title'])) $sf .= '<legend>'.Studio::xml($fig['title']).'</legend>';
        $sf .= '</figure>';
    }
    if($sf!='') {
        $class=' figures';  
        $sf = '<div class="media-thumbnail">'.$sf.'</div>';
    }
}

$s = '<article><div class="hentry'.$class.'" id="e'.$id.'">';
$s .= '<h3 class="entry-title">'.(($link)?('<a href="'.Studio::xml($link).'" rel="bookmark" title="'.Studio::xml($title).'">'.Studio::xml($title).'</a>'):(Studio::xml($title))).'</h3>';
$s .= '<div class="entry-content">'.$sf.$summary.'</div>';
$pub = strtotime($published);
$s .= '<p class="date">';
if($pub)
  $s .= '<span class="published">Publicado em <abbr class="published" title="'.date('c',$pub).'">'.date('d/m/Y H:i',$pub).'</abbr></span> ';
$mod = strtotime($updated);
if($mod)
  $s .= '<span class="updated">Última atualização <abbr class="updated" title="'.date('c',$mod).'">'.date('d/m/Y H:i',$mod).'</abbr></span>';

$s .= '</p></div></article>';
echo $s;
