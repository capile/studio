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
use Studio\Api;
use Studio\App;
use Studio\Model;
use Studio\Schema\Model as ModelSchema;
use Studio\Schema;
use Studio\Model\Tokens;
use Studio\OAuth2\Storage;
use Studio\OAuth2\Client;
use Studio\Studio;
use Studio\Yaml;
use Studio\Query;
use Studio\Query\Api as QueryApi;

class Interfaces extends Model
{
    public static $schema, $pkids=['id', 'uid', 'Id', 'ID', 'pkId', 'UUID', 'uuid'];
    protected $id, $title, $model, $connection, $source, $schema_source, $schema_data, $credential, $index_interval, $indexed, $created, $updated;

    public function model()
    {
        $S = $this->loadSchema(false);
        if(!$this->model) {
            $cn = 'Studio_Interfaces_'.S::camelize($this->id, true);
            $S->className = $cn;
            $cacheDir = S::getApp()->config('app', 'cache-dir');
            if(!$cacheDir) $cacheDir = S_VAR.'/cache';
            $d = $cacheDir.'/apis';
            if($d) {
                $f = $d.'/'.$cn.'.php';
                if(!isset($this->updated) && $this->id) $this->refresh(['updated']);
                $lmod = ($this->updated) ?S::strtotime($this->updated) :null;
                if(!file_exists($f) || !$lmod || filemtime($f)<$lmod) {
                    $fns = ($S->properties) ?array_keys($S->properties) :[];
                    if($S->relations) $fns = array_merge($fns, array_keys($S->relations));
                    $pf = ($fns) ?'protected $'.implode(', $', $fns).';' :'';
                    S::save($f, '<?'.'php class '.$cn.' extends Studio\\Model { public static $schema='.var_export($S->value(), true).', $allowNewProperties=true; };', true);
                }
                require_once $f;
            }
            $S->patternProperties = '/.*/';
            $cn::$schema = $S;
        } else {
            $cn = $this->model;
        }
        $S = null;

        return $cn;
    }

    public function loadSchema($asArray=true)
    {
        $S = [];
        $this->refresh(['schema_data', 'schema_source', 'model']);
        if($this->model && method_exists($this->model, 'schema')) {
            $cn = $this->model;
            $sc = $cn::schema();
            $S = ($asArray) ?(array) $sc :$sc;
        } else if($this->schema_source) {
            $sc = Schema::import($this->schema_source);
            $S = $sc;//->properties;
        }

        if($this->schema_data) {
            $d = (is_string($this->schema_data)) ?S::unserialize($this->schema_data) :$this->schema_data;

            if($d) {
                $S = S::mergeRecursive($d, $S);
            }
        }
        if(!$asArray) {
            $S = new ModelSchema($S);
            $pk = [];
            if($S->properties) {
                foreach($S->properties as $fn=>$fd) {
                    if($fd['primary']) $pk[] = $fn;
                }
            }
            if(!$pk) {
                foreach(static::$pkids as $k) {
                    if(isset($S->properties[$k])) {
                        $S->properties[$k]->primary = true;
                        $pk[] = $k;
                        break;
                    }
                }
            }

            if($pk) {
                $scope = ($S->scope) ?$S->scope :[];
                $scope['uid'] = $pk;
                $S->scope = $scope;
                unset($scope);
            }

            if($this->connection) {
                $S->database = $this->connection();
            }
            if($this->source) $S->tableName = $this->source;
        }

        return $S;
    }

    public function connection()
    {
        if(!$this->connection) return;

        if(!isset(S::$database[$this->connection])) {
            if(preg_match('/^server:/', $this->connection)) {
                list($type, $sid) = explode(':', $this->connection);
                $Server = new Client(Storage::fetch($type, $sid));
                if($a=$Server->api_endpoint) {
                    $o = $Server->api_options;
                    if(!is_array($o) && $o) $o = S::unserialize($o, 'json');
                    if(!$o) $o=[];
                    $o['connectionCallback'] = [$Server, 'connectApi'];
                    S::$database[$this->connection] = [
                        'dsn' => $a,
                        'options'=>$o,
                    ];
                }
            }
        }

        return $this->connection;
    }

    public function previewSchemaData()
    {
        return '<code style="white-space:pre">'.S::xml(preg_replace('#^---\n#', '', S::serialize($this->loadSchema(), 'yaml'))).'</code>';
    }

    public function cacheFile()
    {
        static $n;
        static $d;
        static $i=1;

        if(is_null($n)) $n = (isset(Studio::$apiListParent['apis'])) ?Studio::$apiListParent['apis'] :'apis';
        if(is_null($d)) {
            $cacheDir = S::getApp()->config('app', 'cache-dir');
            if(!$cacheDir) $cacheDir = S_VAR.'/cache';
            $d = $cacheDir.'/apis';
        }

        $id = S::slug($this->id, '_', true);
        $f =  $d.'/'.$id.'.yml';
        $f0 = Api::configFile($id, [$f]);
        $lmod = false;
        if($this->updated) $lmod = strtotime($this->updated);
        if($f0 && file_exists($f0) && ($t=filemtime($f0)) && $t>$lmod) $lmod = $t;
        if(!file_exists($f) || $lmod>filemtime($f)) {
            $a = ['all'=>[
                'api'=>$id,
                'model'=>'Studio\\Model\\Index',
                'search'=>['interface'=>$this->id],
                'key'=>'id',
                'options'=>[
                    'scope'=>['uid'=>'id'],
                    'link-encode'=>true,
                ],
                'prepare'=>'prepareApi',
            ]];
            $addParent = true;
            if($f0 && $f0!==$f) {
                //$a['all']['base'] = $id;
                if(($a0 = Yaml::loadFile($f0)) && (!isset($a['all']['api']) || $a['all']['api']==$id)) {
                    $addParent = false;
                    $a = $a0;
                }
                unset($a0);
            }
            $a['all'] += $this->asArray('interface');
            if(!isset($a['all']['options'])) $a['all']['options'] = [];
            if($addParent) {
                $a['all']['options'] += ['list-parent'=>$n, 'priority'=>$i++];
            }
            $a['all']['options'] += ['index'=>($this->index_interval > 0)];

            if(!S::save($f, S::serialize($a, 'yaml'), true)) {
                $f = null;
            }
        }

        return $f;
    }

    public static function findCacheFile($file)
    {
        static $avoidRecursive;
        if(!$avoidRecursive && ($A = self::find(['id'=>$file],1))) {
            $avoidRecursive = true;
            $r = $A->cacheFile();
            $avoidRecursive = false;
            return $r;
        }
    }

    public static function executeImport($Interface=null)
    {
        if(!($p=S::urlParams()) && ($route = App::response('route'))) {
            S::scriptName($route['url']);
            $p = S::urlParams();
        }

        self::$boxTemplate     = $Interface::$boxTemplate;
        self::$headingTemplate = $Interface::$headingTemplate;
        self::$previewTemplate = $Interface::$previewTemplate;
        S::$variables['form-field-template'] = self::$previewTemplate;

        $S = new Interfaces();
        $F = $S->getForm('import');
        $s = '';
        if(($post=App::request('post')) && $F->validate($post)) {
            $d = $F->getData();
            try {
                $m = 'import'.S::camelize($d['_schema_source_type'], true);
                $msg = '';
                if($R = QueryApi::runStatic($d['schema_source'])) {
                    $S->$m($R, $msg);
                    $s .= $msg;
                }
            } catch(\Exception $e) {
                S::log('[ERROR] Could not import '.S::serialize($d, 'json').': '.$e->getMessage()."\n{$e}");
                $msg = '<div class="s-msg s-msg-error">'.S::t(Api::$importError).'<br />'.S::xml($e->getMessage()).'</div>';
            }
        }

        $s .= (string) $F;

        $r = $Interface['text'];
        $r['preview'] = $s;

        $Interface['text'] = $r;
    }

    public function importSwagger($d, &$msg='')
    {
        $url = $this->schema_source;
        if(isset($d['basePath'])) {
            $surl = parse_url($url);
            if(isset($d['host'])) $surl['host'] = $d['host'];
            $url = S::buildUrl($d['basePath'], $surl);
        }

        // api options
        $api = [];
        if(isset($d['parameters']['perPage'])) {
            $api['limit'] = $d['parameters']['perPage']['name'];
            if(isset($d['parameters']['perPage']['default'])) $api['limitCount'] = (int)$d['parameters']['perPage']['default'];
        }
        if(isset($d['parameters']['page'])) {
            $api['pageOffset'] = $d['parameters']['page']['name'];
            $api['startPage'] = (isset($d['parameters']['perPage']['default'])) ?(int)$d['parameters']['perPage']['default'] :1;
        }
        $api['schema'] = $this->schema_source;

        // import connections
        $cid = null;
        if(isset($d['securityDefinitions'])) {
            foreach($d['securityDefinitions'] as $i=>$o) {
                if(isset($o['type']) && $o['type']!='oauth2') continue;
                $b = ['id'=>$i, 'type'=>'server'];
                $cid = 'server:'.$i;
                if(!($T=Tokens::find($b,1))) {
                    $T = new Tokens($b, true, false);
                }

                $options = $T->options;
                if($options && is_string($options)) $options = S::unserialize($options);
                $options['api_endpoint'] = $url;
                if(isset($o['authorizationUrl'])) $options['authorization_endpoint'] = $o['authorizationUrl'];
                if(isset($o['tokenUrl'])) $options['token_endpoint'] = $o['tokenUrl'];
                if(isset($o['userinfoUrl'])) $options['userinfo_endpoint'] = $o['userinfoUrl'];
                if(isset($o['scopes'])) $api['scopes'] = $o['scopes'];
                $options['api_options'] = $api;

                $T->options = $options;
                $T->save();
                $msg .= '<div class="s-msg s-msg-success">'.sprintf(S::t(Api::$importSuccess), $T::label(), (string)$T).'</div>';
            }
        }
        // loop through paths and import APIs
        if(isset($d['paths'])) {
            foreach($d['paths'] as $i=>$o) {
                foreach($o as $m=>$ad) {
                    $aid = (isset($ad['operationId'])) ?$ad['operationId'] :$m.':'.$i;
                    $aid = $this->id.':'.$aid;
                    $sc = [];
                    if(isset($ad['responses'][200]['schema'])) $sc = Schema::import($ad['responses'][200]['schema']);
                    if(!isset($sc['properties']) && isset($sc['items']['$ref']['properties'])) {
                        $sc['properties'] = $sc['items']['$ref']['properties'];
                        unset($sc['items']);
                    }
                    if(!is_array($sc)) $sc = [];
                    $sc = ['_options' => ['methods' => [$m] ] ] + $sc;
                    $a = [
                        'id' => $aid,
                        'connection'=>$cid,
                        'source'=>$i,
                    ];
                    if(isset($ad['summary'])) $a['title'] = $ad['summary'];
                    if(isset($ad['parameters'])) $sc['_options']['args'] = $ad['parameters'];
                    $a['schema_data'] = S::serialize($sc, 'json');

                    $A = self::replace($a);
                    $msg .= '<div class="s-msg s-msg-success">'.sprintf(S::t(Api::$importSuccess), $A::label(), (string)$A).'</div>';
                }
            }
        }

        // combine models?
        return true;
    }

    public static function choicesConnection()
    {
        static $r;
        if(is_null($r)) {
            $r = [];
            $L = Tokens::find(['type'=>'server'],null,['id'],false);
            $sources = 0;
            if($L) {
                $sources++;
                foreach($L as $i=>$o) {
                    $r['server:'.$o->id] = $o->id;
                    unset($L[$i], $i, $o);
                }
            }
            $L = null;
            $L = Query::database();
            if($L) {
                $sources++;
                foreach($L as $i=>$o) {
                    $n = (isset($o['name'])) ?$o['name'] :$i;
                    $r[$i] = $n;
                    unset($L[$i], $i, $o, $n);
                }
            }
            $L = null;
        }

        if($sources > 1) asort($r);
        return $r;
    }

    public function previewModel()
    {
        if(!isset($this->model) && $this->id) $this->refresh(['model']);
        $cn = $this->model;
        if(!$cn) {
            $cn = $this->model();
            if(Api::format()==='html') $cn = '<em>'.S::xml($cn).'</em>';
        }

        return $cn;
    }
}