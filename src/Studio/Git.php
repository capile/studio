<?php
/**
 * Gitt
 *
 * Perform Git operations
 *
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */
namespace Studio;

use Studio as S;
use Studio\Exception\AppException;

class Git
{
    public static 
        $gitExecutable='git',
        $sshKey,
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

    public function pull($dest, $options=[])
    {
        $err = null;
        $pwd = getcwd();
        if(!is_dir($dest)) {
            if(!is_dir($pd=dirname($dest))) $err = 'There\'s no path to the destination folder "'.$dest.'"';
            else if(!is_writable($pd)) $err = 'Parent folder "'.$pd.'" is not writable.';
        } else if(S::isEmptyDir($dest)) {
            $err = 'Destination folder "'.$pd.'" is empty.';
        } else if(!chdir($dest)) {
            $err = 'Could not change to destination folder "'.$pd.'"';
        }

        if($err) {
            S::log($err='[ERROR] Cannot git pull: '.$err);
            if($this->config('throwExceptions')) throw new AppException($err);
            return false;
        }

        $cmd = 'pull';
        if($options) {
            if(is_array($options)) {
                foreach($options as $o) {
                    $cmd .= ' '.escapeshellarg($o);
                    unset($o);
                }
            } else {
                $cmd .= ' '.escapeshellarg($options);
            }
        }

        $r = $this->run($cmd);
        chdir($pwd);

        return $r;
    }

    public function run($cmd)
    {
        $options = '';
        if(($k=$this->config('sshKey')) && file_exists(realpath($k)) && ($k=escapeshellarg($k))) {
            $options = " -c core.sshCommand=\"ssh -i {$k} -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new\"";
        }
        $r = S::exec(['shell'=>$this->config('gitExecutable').$options.' '.$cmd]);
        if(S::$variables['execResult']!==0) {
            S::log($err='[ERROR] Error executing git'.$options.' '.$cmd, $r);
            if($this->config('throwExceptions')) throw new AppException($err);
            return false;
        }

        if($r && S::$log>1) S::log('[DEBUG] Git '.$cmd.': '.$r);
        return true;
    }
}