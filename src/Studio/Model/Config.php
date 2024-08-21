<?php
/**
 * Configuration files updater
 *
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */

namespace Studio\Model;

use Studio as S;
use Studio\Api;
use Studio\App;
use Studio\Crypto;
use Studio\Exception\AppException;
use Studio\Model;
use Studio\Query;
use Studio\Studio;
use Studio\User;
use Studio\Yaml;

class Config extends Model
{
    public static $schema, $webRepoClient=['ssh'=>'*SSH using public keys', 'http'=>'*HTTP using token'];

    protected $app, $studio, $database, $user;

    public function choicesStudioVersion()
    {
        return ["1.0"];
    }

    public function choicesLanguage()
    {
        return ["en"=>"English", "pt"=>"Português"];
    }

    public function choiceswebRepoClient()
    {
        static $options;
        if(is_null($options)) {
            $options=[];
            foreach(self::$webRepoClient as $n=>$v) {
                $options[$n] = S::t($v, 'interface');
            }
        }

        return $options;
    }

    public function renderTitle()
    {
        $title = (isset($this->__source_uid)) ?$this->__source_uid :(string)$this;
        if(($tt = S::t('title', 'model-'.$title)) && $tt!=='title') $title = $tt;
        unset($tt);

        return $title;
    }

    public function checkConfiguration()
    {
        if(is_null($this->app)) $this->app = [];
        else if(isset($this->app['api-dir'])) unset($this->app['api-dir']);

        if(isset($this->app['languages']) && $this->app['languages']) {
            if(count($this->app['languages'])==1) {
                $this->app['language'] = array_shift($this->app['languages']);
                unset($this->app['languages']);
            } else {
                $langs = $this->choicesLanguage();
                $l = [];
                foreach($this->app['languages'] as $lang) {
                    if(isset($langs[$lang])) $l[$langs[$lang]] = $lang;
                }
                $this->app['languages'] = $l;
            }
        }

        $this->user = [];
        $cfgs = [];
        if(isset($this->studio['enable_api_credential']) && $this->studio['enable_api_credential']) {
            $cfgs = Yaml::load(S_ROOT.'/data/config/config-credential.yml-example');
        }
        if(isset($this->studio['enable_api_index']) && $this->studio['enable_api_index']) {
            $n = Yaml::load(S_ROOT.'/data/config/config-index.yml-example');
            if($cfgs) $cfgs = S::mergeRecursive($n, $cfgs);
            else $cfgs = $n;
            unset($n);
        }

        if($cfgs) {
            foreach($cfgs['all'] as $k=>$v) {
                if($k!='studio') {
                    if($this->$k) {
                        $this->$k += $v;
                    } else {
                        $this->$k = $v;
                    }
                }
            }
        }

        return true;
    }

    public function reloadConfiguration($run=['check'])
    {
        // reload config
        @touch(S_ROOT.'/app.yml');

        $cmd = S_ROOT.'/studio';

        // check database tables
        foreach($run as $a) {
            S::exec(['shell'=>$cmd.' :'.$a]);
        }

        // import admin password (if set)
        $import = [];
        if(isset($this->_admin_password) && $this->_admin_password) {
            $import += [
                'Studio\Model\Users!'=>[[
                    '__key' => [ 'username' ],
                    '__set' => [ 'USERID' => 'id' ],
                    'username' => 'admin',
                    'password' => $this->_admin_password,
                    'name' => 'Administrator',
                ]],
                'Studio\Model\Groups!' => [[
                    '__key' => [ 'name' ],
                    '__set' => [ 'GROUPID' => 'id' ],
                    'name' => 'Administrators',
                    'priority' => 1,
                ]],
                'Studio\Model\Credentials!' => [[
                    '__key' => [ 'userid', 'groupid' ],
                    'userid' => '$USERID',
                    'groupid' => '$GROUPID',
                ]],
            ];
        }

        $task = false;
        if(isset($this->studio['enable_api_index']) && $this->studio['enable_api_index'] && isset(Studio::$cliApps['index'])) {
            $task = true;
            $import += [
                'Studio\Model\Tasks!'=>[[
                    'id' => 'studio-api-index',
                    'code' => S::serialize(['callback'=>Studio::$cliApps['index']], 'json'),
                    'starts'=> S_TIMESTAMP,
                    'interval' => ($t=Studio::config('index_timeout')) ?$t :600,
                ]],
            ];
        }

        if($import) {
            Query::import($import);
            unset($import);
        }

        if($task) {
            Tasks::backgroundExec();
        }

        if($I=Api::current()) {
            return $I->redirect(S::scriptName(true));
        }
        return true;
    }

    public static function standaloneConfig()
    {
        if(S_ROOT!=S_APP_ROOT) S::debug('[ERROR] '.S::t('This action is only available on standalone installations.', 'exception'));

        // load data/config/config.yml-example, reload configuration, remove the file and forward user to http://127.0.0.1:9999/_studio
        $docker  = file_exists('/.dockerenv');
        $appmode = (getenv('STUDIO_MODE')=='app');
        $c0 = S_ROOT.'/data/config/config.yml-example';
        $c = S_ROOT.'/data/config/config.yml';
        $d = S_ROOT.'/data/config/defaults.yml';
        $add = null;

        if($appmode) {
            $rdata = $data = (isset($_ENV['STUDIO_DATA']) && $_ENV['STUDIO_DATA']) ?$_ENV['STUDIO_DATA'] :'/data';
            $cf = $data.'/studio.yml';
            if(!file_exists($c)) symlink($cf, $c);
            $c = $cf;
            if($data!=='data/') {
                S::save(S_ROOT.'/data/config/00-overwrite.yml', str_replace(['sqlite:data/', ' data/'], ['sqlite:'.$data.'/', " {$data}/"], file_get_contents($d)));
            }
        } else {
            $data = S_VAR;
            $rdata = 'data/';
        }

        if(!file_exists($c)) {
            if(!$docker) {
                copy($c0, $c);
            } else {
                $hs = file('/etc/hosts');
                $h = preg_replace('/\.[0-9]+\s.*/', '.1', trim(array_pop($hs)));
                S::save($c, str_replace('127.0.0.1', $h, file_get_contents($c0)).$add);
            }
        }

        if(!$docker && !$appmode) {
            // (re)load server
            S::exec(['shell'=>S_ROOT.'/studio-server']);
        } else {
            @touch(S_ROOT.'/app.yml');
        }

        $C = new Config();
        $C->reloadConfiguration(['check', 'index', 'assets']);

        if(!$docker) {
            $os = strtolower(substr(PHP_OS, 0, 3));
            if($os==='win') {
                $cmd = 'explorer';
            } else if($os==='dar') {
                $cmd = 'open';
            } else {
                $cmd = 'xdg-open';
            }

            S::exec(['shell'=>$cmd.' '.escapeshellarg('http://127.0.0.1:9999/_studio')]);
        } else if(exec('whoami')==='root') {
            $ds = [S_VAR, S_VAR.'/log', S_VAR.'/web/_', S_VAR.'/cache', S_VAR.'/studio.db', dirname($c), $c, ];
            foreach($ds as $d) if(file_exists($d)) chown($d, 'www-data'); else echo "where's {$d}?";
            unset($d, $ds);
        }

        if($docker) {
            echo "\n\nPlease visit:\n\n    http://127.0.0.1:9999/_studio\n\nto finish configuration.\n";
        }
    }

    public static function resetStudio()
    {
        if(S_ROOT!=S_APP_ROOT) S::debug('[ERROR] '.S::t('This action is only available on standalone installations.', 'exception'));
        $r = ['data/cache/*','data/studio.db','data/config/*.yml'];
        while($r) {
            $f = array_shift($r);
            $g = false;
            if(strpos($f, '*')!==false) $g = true;
            else if(is_dir($f)) {
                $g = true;
                $f .= '/*';
            }
            if($g) {
                $G = glob($f);
                if($G) $r = array_merge($r, $G);
            } else {
                @unlink($f);
            }
        }
        @touch(S_ROOT.'/app.yml');
    }

    public static function executePreview($Api, $args=[])
    {
        $Api->getButtons();
        $r = $Api->text;
        if(isset($r['text'])) {
            $r['preview'] = S::markdown($r['text']).'<p>Version '.S::VERSION.'</p>';
            $Api->text = $r;
        }
    }

    public function validateStudioWebRepos($v)
    {
        if($v && is_array($v)) {
            foreach($v as $i=>$o) {
                if(!$this->syncRepo($v[$i])) {
                    $n = (isset($o['id'])) ?$o['id'] :$i+1;
                    throw new AppException(sprintf(S::t('The repository %s could not be synchronized.', 'exception'), $n));
                }
            }
        }

        return $v;
    }

    public static function syncRepo(&$repo, $push=null)
    {
        if(!isset($repo['id']) || !$repo['id'] || !isset($repo['src']) || !$repo['src']) return false;

        $rr = S_REPO_ROOT;

        if(!isset($repo['client']) || !$repo['client']) $repo['client'] = null;
        if(!isset($repo['secret']) || !$repo['secret']) $repo['secret'] = null;

        $o = [];
        if($repo['client']) {
            $o[] = '-c '.escapeshellarg('credential.'.$repo['src'].'.username='.$repo['client']);
        }
        if($repo['secret']) {
            $o[] = '-c '.escapeshellarg('credential.'.$repo['src'].'.password='.$repo['secret']);
        }

        $d = $rr.'/'.$repo['id'];
        $clone = null;
        if(!is_dir($d)) {
            if(!mkdir($d, 0777, true)) return false;
            $clone = true;
        } else if(S::isEmptyDir($d)) {
            $clone = true;
        } else if(!file_exists($d.'/.git')) {
            // not a git repo
            return false;
        }

        if($clone && isset($repo['mount-src']) && $repo['mount-src'] && strpos($repo['mount-src'], ':')) {
            $o[] = '--branch '.escapeshellarg(substr($repo['mount-src'], 0, strpos($repo['mount-src'], ':')));
        }

        if($clone) $a = 'git -C '.escapeshellarg($d).' clone '.implode(' ', $o).' '.escapeshellarg($repo['src']).' .';
        else if($push) $a = 'git -C '.escapeshellarg($d).' push '.implode(' ', $o);
        else $a = 'git -C '.escapeshellarg($d).' pull '.implode(' ', $o);

        if(!S::exec(['shell'=>$a])) return false;

        return true;
    }
}