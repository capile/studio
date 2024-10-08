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
$class='';$sf='';$dim='';//'.200x100';
if(is_array($figures) && count($figures)>0)
{
  foreach($figures as $imgid=>$fig)
  {
    if(!isset($fig['src']))continue;
    $src=$fig['src'];
    if(strpos($src,$dim)===false)
    {
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
  if($sf!='')
  {
    $class=' figures';  
    $sf = '<div class="media-thumbnail preview">'.$sf.'</div>';
  }
}
$s = '<div class="hentry tdze'.$class.'" id="e'.$id.'">';
$s .= '<h2 class="entry-title">'.Studio::xml($title).'</h2>';
$s .= '<div class="entry-content">'.$sf.$summary.'</div>';
if(is_array($contents) && count($contents)>0)
{
  foreach($contents as $content)
  {
    if($content['content_type']=='html')
    {
      $content->getLatestVersion();
      $result=$content->render();
      if(isset($result['content']))
        $s .= $result['content'];
    }
  }
}


$pub = strtotime($published);
$s .= '<p class="date">';
if($pub)
  $s .= '<span class="published">Publicado em <abbr class="published" title="'.date('c',$pub).'">'.date('d/m/Y H:i',$pub).'</abbr></span> ';
$mod = strtotime($updated);
if($mod)
  $s .= '<span class="updated">Última atualização <abbr class="updated" title="'.date('c',$mod).'">'.date('d/m/Y H:i',$mod).'</abbr></span>';

$s .= '</p></div></article>';
echo $s;
