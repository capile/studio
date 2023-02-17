<?php
/**
 * Gitt
 *
 * Perform Git operations
 *
 * PHP version 7.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   1.0
 */
namespace Studio;

use Studio as S;
use Studio\Exception\AppException;

class Git
{
    public static 
        $gitExecutable='git',
        $throwExceptions;
    public $config=[];

    public function __construct($o=[])
    {
        if($o && is_array($o)) {
            $this->config = $o;
        }
    }

    public function config($n=null)
    {
        if($n) {
            if($this->config && isset($this->config[$n])) $r = $this->config[$n];
            else if(property_exists($this, $n)) $r = $this::$$n;
            else return null;

            $a = func_get_args();
            array_shift($a);
            while($r && $a) {
                $n = array_shift($a);
                if($r && is_array($r) && isset($r[$n])) $r = $r[$n];
                else $r = null;
            }

            return $r;
        }

        return $this->config;
    }

    public function clone($repo, $dest, $branch=null)
    {
        $err = null;
        if(!is_dir($dest)) {
            if(!is_dir($pd=dirname($dest))) $err = 'There\'s no path to the destination folder "'.$dest.'"';
            else if(!is_writable($pd)) $err = 'Parent folder "'.$pd.'" is not writable.';
        } else if(!S::isEmptyDir($dest)) {
            $err = 'Destination folder "'.$pd.'" is not empty.';
        }

        if($err) {
            S::log($err='[ERROR] Cannot git clone: '.$err);
            if($this->config('throwExceptions')) throw new AppException($err);
            return false;
        }

        $cmd = 'clone '.escapeshellarg($repo).' '.escapeshellarg($dest);
        if($branch) {
            $cmd .= ' -b '.escapeshellarg($branch);
        }

        return $this->run($cmd);
    }

    public function run($cmd)
    {
        $r = S::exec(['shell'=>$this->config('gitExecutable').' '.$cmd]);
        if(S::$variables['execResult']!==0) {
            S::log($err='[ERROR] Error executing git '.$cmd, $r);
            if($this->config('throwExceptions')) throw new AppException($err);
            return false;
        }

        if($r || S::$log) S::log('[INFO] Git '.$cmd.':', $r);
        return true;
    }
}