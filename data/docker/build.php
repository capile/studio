#!/usr/bin/env php
<?php

$a = $_SERVER['argv'];
$fs = [];
$d = __DIR__.'/';
$publish = false;
$nocache = false;
$error = false;
foreach($a as $i=>$o) {
    if(substr($o, -3)==='php') {
        continue;
    }
    if(file_exists($f=$d.$o.'.dockerfile')) {
        $fs[] = $f;
    } else if($g=glob($d.'*-'.$o.'.dockerfile')) {
        $fs = array_merge($fs, $g);
    } else if($o==='--publish' || $o==='-p') {
        $publish = true;
    } else if($o==='--no-cache') {
        $nocache = true;
    } else {
        echo "- Invalid argument: $o\n";
        $error = true;
    }
}

if($error) exit(1);

if(!$fs) $fs = glob($d.'*.dockerfile');
chdir($d);

foreach($fs as $f) {
    $ln = trim(preg_replace('/^\#+ */', '', file($f)[0]));
    $tags = [$ln];
    if(preg_match('/^([^\:]+:[^\-]+)-.*$/', $ln, $m)) $tags[] = $m[1];
    else $tags[] = preg_replace('/\:.*/', ':latest', $ln);
    $cmd = "docker build -f '{$f}'  . -t ".implode(' -t ', $tags);
    if($nocache) $cmd .= ' --no-cache';
    echo "[INFO] Running: $cmd\n";
    passthru($cmd);
    if($publish) {
        foreach($tags as $tag) {
            $cmd = "docker push {$tag}";
            echo "[INFO] Running: $cmd\n";
            passthru($cmd);
        }
    }
}