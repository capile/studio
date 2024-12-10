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
use Studio as S;
use Studio\Studio;

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
        $sf .= '<img alt="'.S::xml($fig['alt']).'" src="'.S::xml($src).'" border="0" />';
        if(isset($fig['title'])) $sf .= '<legend>'.S::xml($fig['title']).'</legend>';
        $sf .= '</figure>';
    }
    if($sf!='') {
        $class=' figures';  
        $sf = '<div class="media-thumbnail">'.$sf.'</div>';
    }
}

$schema = Studio::config('entry_schema');

echo '<article'.(($schema) ?' itemscope itemtype="'.$schema.'"' :'').'>'
   .   '<div class="hentry'.$class.'" id="e'.$id.'">'
   .     '<h3 class="entry-title"'.(($schema) ?' itemprop="name"' :'').'>'.(($link)?('<a href="'.S::xml($link).'">'.S::xml($title).'</a>'):(S::xml($title))).'</h3>'
   .     '<div class="entry-content"'.(($schema) ?' itemprop="about"' :'').'>'.$sf.$summary.'</div>'
   .     (($pub = strtotime($published)) ?'<p class="entry-published"'.(($schema) ?' itemprop="datePublished" content="'.$published.'"' :'').'>'.preg_replace('/ +[0\:]+$/', '', S::date($pub)).'</p>' :'')
   .    (($entry && ($tag=$entry->getTags())) ?'<p class="entry-keywords"'.(($schema) ?' itemprop="keywords"' :'').'><span>'.implode('</span> <span>', $tag).'</span></p>' :'')
   .   '</div>'
   . '</article>'
   ;
