<?php
/**
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */
namespace Studio\Model;

use Studio as S;
use Studio\App;
use Studio\Cache;
use Studio\Model;
use Studio\Studio;

class Tasks extends Model
{
    public static $schema, $schemaClass='Studio\\Schema\\Model', $worker, $batchInterval=60, $harakiri=10;
    protected $id, $title, $code, $starts, $ends, $interval, $schedule, $executed, $created, $updated;

    public static function check($enableBackground=true)
    {
        if(($w=Cache::get('tasks/worker')) && $w!==self::$worker) return true;
        if(is_null(self::$worker)) self::$worker = S::salt(10);
        Cache::set('tasks/worker', self::$worker, self::$batchInterval * 1.1);

        if(S_BACKGROUND && ($sleep=App::request('argv', 0)) && is_numeric($sleep) && $sleep>0) {
            if(S::$log) S::log('[INFO] Sleeping new worker for '.$sleep.' seconds...');
            sleep((int)$sleep);
        }

        if(S::$log) S::log('[INFO] Checking background tasks on worker '.self::$worker);

        $ts = date('Y-m-d\TH:i:s');
        $L = Tasks::find(['starts<='=>$ts,['|ends'=>'', '|ends<'=>$ts]],null,null,false);
        $next = null;
        if($L) {
            if(S::$log) S::log('[INFO] Found '.count($L).' possible tasks.');
            foreach($L as $i=>$o) {
                $t = $o->nextRun();
                if($t && $t<=time()) {
                    if(S::$log) S::log('[INFO] Running '.$o->id);
                    $o->run($ts);
                    $t = $o->nextRun();
                }
                if($t) {
                    if(!$next || $t < $next) $next = $t;
                }
                unset($L[$i], $i, $o);
            }
        }

        if(!$enableBackground) {
            if(($w=Cache::get('tasks/worker')) && $w===self::$worker) {
                Cache::delete('tasks/worker');
            }
            return true;
        }

        if($o = Tasks::find(['starts>'=>$ts,['|ends'=>'', '|ends<'=>$ts]],1,['starts'],false,['starts'=>'asc'])) {
            if(($t=S::strtotime($o->starts)) && (!$next || $t < $next)) {
                $next = $t;
            }
        }

        if($next) {
            if(S::$log) S::log('[INFO] Next task should be run at '.S::date($next, true));
            Cache::set('tasks/next', S::date($next, true), $next);

            $sleep = $next - time();
            if($sleep > self::$batchInterval) $sleep = self::$batchInterval;

            if(S_BACKGROUND && self::$harakiri--) {
                if(S::$log) S::log('[INFO] Sleeping for '.$sleep.' seconds...');
                if($sleep>0) sleep($sleep);
                self::check();
            } else {
                Cache::delete('tasks/worker');
                if($sleep<=0) $sleep='';
                self::backgroundExec(S_ROOT.'/studio :task '.$sleep);
            }
        } else {
            if(S::$log) S::log('[INFO] No more tasks to run, retiring...');
            Cache::delete('tasks/next');
        }

        /*
        if(S_BACKGROUND) sleep(2);
        S::log(__METHOD__, (!S_BACKGROUND) ?'not in bkg' :'hidden in bkg', false);
        if(!S_BACKGROUND) {
            self::backgroundExec();
        }
        */
    }

    public function nextRun()
    {
        $t = null;
        if($this->interval) {
            if(!$this->executed) {
                $t = time();
            } else if($t=S::strtotime($this->executed)) {
                $t += (int) $this->interval;
            }
        }

        // organize scheduled times

        return $t;
    }

    public function run($timestamp=null)
    {
        $code = $this->code;
        if(is_string($code)) $code = S::unserialize($code, 'json');

        if($code) {
            if(S::$log) S::log('[INFO] Running code: ', $code);
            $this->__skip_timestamp_updated = true;
            $this->executed = (!$timestamp) ?date('Y-m-d\TH:i:s') :$timestamp;
            $this->save();
            return S::exec($code);
        }
    }

    public static function backgroundExec($exec=null)
    {
        if(is_null($exec)) $exec = S_ROOT.'/studio :task';
        if(strtolower(substr(PHP_OS, 0, 3))=='win'){
            $exec = 'set S_BACKGROUND=1 & start "" '. $exec;
        } else {
            $exec = 'S_BACKGROUND=1 ' . $exec . ' &';
        }
        $handle = popen($exec, 'r');
        if($handle!==false) {
            pclose($handle);
            return true;
        } else {
            return false;
        }
    }
}