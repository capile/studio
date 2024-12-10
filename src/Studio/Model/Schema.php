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
use Studio\Model;
use Studio\Api;

class Schema extends Model
{
    public static $schema;

    protected $id, $title, $type, $description, $class_name, $database, $table_name, $view, $order_by, $group_by, $pattern_properties, $scope, $base, $enable_content, $created, $updated, $BaseSchema, $SchemaProperties, $SchemaDisplay;


    public static function choicesType()
    {
        static $types=[];

        if(!$types) {
            $types = S::t(['object'=>'Object', 'array'=>'Array', 'string'=>'String', 'number'=>'Number', 'date'=>'Date', 'datetime'=>'Datetime', 'int'=>'Integer', 'bool'=>'Boolean'], 'ui');
        }

        return $types;
    }

    public function previewTitle()
    {
        $s = ($this->title) ?$this->title :S::t(ucwords(preg_replace('/[\-\_\.]+/', ' ', basename($this->id))), 'model-studio_contents');

        return S::xml($s);
    }

    public function previewSchemaProperties()
    {
        if($L=$this->getRelation('SchemaProperties', null, 'string', false)) {
            $s = '<ul>';
            foreach($L as $i=>$o) {
                $t = ($o->title) ?$o->title :S::t($o->bind, 'model-studio_schema');
                $s .= '<li>'.S::xml($t).(($t!==$o->bind) ?' ('.S::xml($o->bind).')' :'').'</li>';
            }
            $s .= '</li>';

            return $s;
        }
    }

    public function formOverlay($prefix=null)
    {
        static $multipleType=['array', 'object'];
        $r = [];
        if($this->type==='object') {
            $P = SchemaProperties::find(['schema_id'=>$this->id],null,null,false,['position'=>'asc']);
            if($P) {
                foreach($P as $i=>$o) {
                    $s = [
                        'bind'=>($prefix) ?$prefix.'.'.$o->bind :$o->bind,
                        'id'=>$o->bind,
                        'type'=>($o->type) ?$o->type :'string',
                        'primary'=>($o->primary>0) ?true :false,
                        'required'=>($o->required>0) ?true :false,
                        'default'=>$o->default,
                    ];
                    $s['label'] = ($o->title) ?$o->title :S::t(ucfirst(str_replace(['-', '_'], ' ', trim($o->bind, '-_'))), 'labels');
                    if(in_array($o->type, $multipleType)) $s['multiple'] = true;
                    if($o->description) $s['placeholder'] = $o->description;
                    if($o->max_size>0) $s['size'] = (int)$o->max_size;
                    if(is_numeric($o->min_size)) $s['min_size'] = (int)$o->min_size;
                    if($o->serialize) $s['serialize'] = $o->serialize;
                    else if(isset($s['multiple']) && $s['multiple']) $s['serialize'] = 'json';
                    $r[$o->bind] = $s;
                    unset($P[$i], $i, $o);
                }
            }
            unset($P);
            $P = SchemaDisplay::find(['schema_id'=>$this->id],null,null,false);
            if($P) {
                $bool = ['hidden', 'disabled'];
                $j0 = ['{', '['];
                foreach($P as $i=>$o) {
                    if(!isset($r[$o->bind]) || $o->type==='unavailable') {
                        if(isset($r[$o->bind])) unset($r[$o->bind]);
                        continue;
                    } else if($o->type==='unavailable') {
                        continue;
                    } else if(in_array($o->type, $bool)) {
                        $c = (bool) $o->content;
                    } else if($o->type==='choices') {
                        if(!preg_match('/^[\-\*\{\[]|\n/', $o->content)) $c = trim($o->content);
                        else $c = S::unserialize($o->content, (in_array(substr($o->content, 0, 1), $j0)) ?'json' :'yaml');
                    } else if($o->type==='attributes') {
                        $c = S::unserialize($o->content, (in_array(substr($o->content, 0, 1), $j0)) ?'json' :'yaml');
                    } else if($o->type==='format') {
                        $c = trim($o->content);
                        if($c==='html') $r[$o->bind]['html_labels'] = true;
                    } else {
                        $c = $o->content;
                    }
                    $r[$o->bind][$o->type] = $c;
                }
            }
        }

        return $r;
    }
}