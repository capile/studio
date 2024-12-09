<?php
/**
 * Studio default layout
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */

use Studio as S;
use Studio\App;
use Studio\Studio;

if(($accept=App::request('headers', 'accept')) && preg_match('#^(text|application)/json\b#', $accept)) {
    $r = [];
    if(!isset($error)) {
        if(isset($title)) $error = $title;
        else $error = null;
    }
    if($error) $r['error'] = $title;

    if(!isset($message)) {
        if(isset($data)) $message = $data;
        else if(isset($content)) $message = $content;
        else $message = null;
    }

    if($message) {
        $r['message'] = $message;
    }

    S::output($r, 'json');
    exit();
}

if((!isset($script) || !$script || (count($script)==1 && isset($script[700]))) && isset($variables['script'])) $script = ($script) ?$script+$variables['script'] :$variables['script'];
if((!isset($style) || !$style || (count($style)==1 && isset($style[700]))) && isset($variables['style']))  $style  = $variables['style'];

if(isset($script)) {
    $js = '';
    if(!is_array($script)) $script = explode(',', $script);
    foreach($script as $k=>$v) {
        if(is_string($k)) {
            $js .= S::minify($v, S_DOCUMENT_ROOT, true, true, false, S::$assetsUrl.'/'.$k.'.js');
            unset($script[$k]);
        }
        unset($k, $v);
    }
    if($script) {
        $js .= S::minify($script);
    }
    if(Studio::config('enable-csp-nonce')) {
        $nonce = S::salt(10);
        header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline' https:; img-src 'self' https: data:; font-src 'self' data:; script-src 'nonce-{$nonce}' 'strict-dynamic' 'self'; form-action 'self'; media-src 'self'; connect-src 'self'; object-src 'none'; frame-src https:; frame-ancestors 'none'; base-uri 'self'");
        $js = str_replace('<script', '<script nonce="'.$nonce.'"', $js);
    } else {
        header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline' https:; img-src 'self' https: data:; font-src 'self' data:; script-src 'self' 'unsafe-inline'; form-action 'self'; media-src 'self'; connect-src 'self'; object-src 'none'; frame-src https:; frame-ancestors 'none'; base-uri 'self'");
    }
    $script = $js;
    unset($js);
}

if(isset($style)) {
    $css = '';
    if(!is_array($style)) $style = explode(',', $style);
    foreach($style as $k=>$v) {
        if(is_string($k)) {
            $css .= S::minify($v, S_DOCUMENT_ROOT, true, true, false, '/_/'.$k.'.css');
            unset($style[$k]);
        }
        unset($k, $v);
    }
    if($style) {
        $css .= S::minify($style);
    }
    $style = $css;
    unset($css);
}

?><!doctype html><html lang="<?php echo (S::$lang) ?S::$lang :'en'; ?>"<?php if(isset(S::$variables['html-layout'])) echo ' class="', S::xml(S::$variables['html-layout']), '"';?>><head><meta charset="utf-8" /><title><?php if(isset($title)) echo $title ?></title><?php echo Studio::languageHeaders(), isset($meta)?$meta:'', isset($style)?$style:''; ?><script<?php if(isset($nonce)) echo ' "', $nonce, '"'; ?>>let FF_FOUC_FIX;</script></head><body class="no-js"><?php echo isset($data)?$data:'', isset($content)?$content:'', isset($script)?$script:''; ?></body></html>
