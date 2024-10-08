<?php
/**
 * Atom/RSS Entry
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */

$indent=true;$i='';$i0='';$i1='';
if($indent){$i0="\n  ";$i="\n    ";$i1="\n      ";}

$su='';
if(!isset($figures)) $figures = $entry->getContent('media');
if($figures) {
    if(!is_array($figures)) $figures = [$figures];
    foreach($figures as $media) {
        if(is_string($media)) {
            if(substr($media, 0, 5)!='<img ') $media = '<img src="'.Studio::buildUrl($media).'" />';

            $su .= '<figure>'
                 . $media
                 . '</figure>';
        } else {
            $imgid=$media['id'];
            $fig = $media['contents'];
            if(!isset($fig['src']) || $fig['src']=='')continue;

            $su .= '<figure id="fig'.$imgid.'">';
            $fig += array('alt'=>'','src'=>'');
            $src=$fig['src'];
            if(strpos($src,'.200x100')===false) {
                if(preg_match('/^(.+)(\.[a-z]{3,4})$/',$src,$m))
                    $src=$m[1].'.200x100'.$m[2];
                else
                    $src.='.200x100';
            }
            $su .= '<img alt="'.Studio::xml($fig['alt']).'" src="'.Studio::xml(Studio::buildUrl($src)).'" border="0" />';
            if(isset($fig['title'])) $su .= '<legend>'.Studio::xml($fig['title']).'</legend>';
            $su .= '</figure>';
        }
    }
    $su = ($su)?('<div class="media-thumbnail">'.$su.'</div>'):('');
}
if(isset($summary)) $su .= $summary;


$pub = strtotime($published);
if(!$pub)$pub=time();
$mod = strtotime($updated);
if(!$mod)$mod=time();

$s = $i0.'<entry>'
   . $i.'<title type="html">'.Studio::xml($title,false).'</title>'
   . $i.'<link href="'.Studio::xml(Studio::buildUrl($link)).'" />'
   . $i.'<id>e-studio-e-'.$id.'</id>'
   . $i.'<published>'.date('c',$pub).'</published>'
   . $i.'<updated>'.date('c',$pub).'</updated>'
   . $i.'<summary type="html">'.Studio::xml($su,false).'</summary>'
   . $i0.'</entry>';

echo $s;
