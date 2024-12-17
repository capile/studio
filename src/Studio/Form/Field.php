<?php
/**
 * Form Field building, validation and output methods
 * 
 * This package implements applications to build HTML forms
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */
namespace Studio\Form;

use Studio as S;
use Studio\App;
use Studio\Asset\Image;
use Studio\Cache;
use Studio\Collection;
use Studio\Exception\AppException;
use Studio\Form;
use Studio\Model;
use Studio\SchemaObject;
use Studio\Query;
use Studio\Query\Api as QueryApi;
use arrayObject;
use Exception;

class Field extends SchemaObject
{
    public static
        $meta,
        $propertyAsClassName=[
            'required',
            'readonly',
            'disabled',
            'mandatory',
            'error',
        ],
        $templateProperties=[
            'required',
            'readonly',
            'disabled',
            'mandatory',
            'tooltip',
        ],
        $dateInputType='text',
        $dateInputFormat,
        $datetimeInputType='text',
        $datetimeInputFormat,
        $emailInputType='email',
        $numberInputType='number',
        $rangeInputType='range',
        $urlInputType='url',
        $searchInputType='search',
        $phoneInputType='tel',
        $enableMultipleText=true,
        $allowedProperties=array('on'),
        $tmpDir='/tmp',
        $captchaTimeout=3600,
        $captchaCaseSensitive=false,
        $defaultErrorMessage='This is not a valid value for "%s".',
        $typesNotForValidation=['button','submit'],
        $uploadSuffix='--uploader',
        $labels = [ 'blank'=>'—' ],
        $maxOptions=500
        ;

    /**
     * Common attributes to each form field. Check Studio_Form_Field.json schema
     */
    protected
        $id,
        $type = 'text',
        $form,
        $bind,
        $choices,
        $query,
        $value,
        $attributes=[];

    private $_choicesCollection=null;
    private $_choicesTranslated=false;

    public function __construct($def=array(), $form=false)
    {
        $schema = false;
        if ($form) {
            $this->setForm($form);
            $model = $form->model;
            if ($model) {
                $cn = get_class($model);
                $this->_className=$cn;
                $schema = $this->getSchema();
                if(!isset($def['bind']) && isset($schema->properties[$def['id']])) {
                    $def['bind'] = $def['id'];
                }
            }
        }
        $M = null;
        if (isset($def['bind'])) {
            $bdef = $this->setBind($def['bind'], true);
            if(is_array($bdef)) $def += $bdef;
            unset($bdef);
            unset($def['bind']);
            $M = $this->getModel();
        }
        $val = '';
        if(isset($def['value'])) {
            $val = $def['value'];
            unset($def['value']);
        }
        if($def) {
            $def = static::properties($def);
            foreach ($def as $name=>$value) {
                if(strpos($name, '-')!==false) $name = S::camelize($name);
                $this->__set($name, $value);
            }
        }
        if($M && method_exists($M, $m=S::camelize('prepare-'.$this->id).'FormField')) {
            $M->$m($this->attributes, $this);
        }

        if($val!='') {
            $this->setValue($val);
        }

        $Type = S::camelize($this->type, true);
        if($Type && method_exists($this, $m='preCheck'.$Type)) {
            $this->$m();
        }
        unset($Type, $m);
    }

    public function setForm($F)
    {
        $this->form = $F->register();
    }

    public function getForm()
    {
        return Form::instance($this->form);
    }

    public function getModel()
    {
        return Form::instance($this->form)->model;
    }

    public function getBindModel()
    {
        return $this->getModel();
    }

    public function getSchema()
    {
        $cn = false;
        if(is_null($this->_className)) {
            if($this->form && $this->getModel()) {
                $cn = get_class($this->getModel());
                if($cn instanceof Model) {
                    $this->_className = $cn;
                } else {
                    $cn = false;
                }
            }
        } else {
            $cn=$this->_className;
        }
        if($cn) {
            return $cn::$schema;
        }
        return false;
    }

    public function setMessages($msgs=array())
    {
        if(is_array($msgs)) {
            if(!is_array($this->messages)) {
                $this->messages = array();
            }
            foreach($msgs as $k=>$v) {
                $this->messages[$k]=$v;
            }
        }
    }

    /**
     * Binds field to $form->model column or relation
     */
    public function setBind($name, $return=false, $recursive=3)
    {
        $M = $this->getModel();
        if(!$M) return false;
        if(strpos($name, ' ')!==false) $name = substr($name, strrpos($name, ' ')+1);

        $schema = $this->getSchema();

        if(($p=strpos($name, '.')) && isset($schema['columns'][substr($name, 0, $p)]['serialize'])) $fd = $schema['columns'][substr($name, 0, $p)];

        if($schema && (isset($fd) || isset($schema['columns'][$name]) || isset($schema['relations'][$name]))) {
            $this->bind = $name;
            if (isset($schema['relations'][$name]) && $schema['relations'][$name]['type']=='one') {
                $this->bind = $schema['relations'][$name]['local'];
            }
            if($return) {
                $return = array();
                if(!isset($fd) && isset($schema['columns'][$name])) $fd=$schema['columns'][$name];
                $return['required']=(isset($fd['null']) && !$fd['null']);
                if(isset($fd)) {
                    $return = static::properties($fd, $M->isNew());
                } else {
                    $rel = $schema['relations'][$name];
                    if($rel['type']=='one') {
                        $return['type']='select';
                        $return['choices']=$name;
                    } else {
                        $return['type']='form';
                    }
                }
                unset($M, $name, $schema, $rel, $fd);
                return $return;
            }
        } else if(isset($M::$schema['form'][$name]['bind']) && preg_replace('/^.*\s([^\s]+)$/', '$1', $M::$schema['form'][$name]['bind'])!=$name && $recursive--) {
            return $this->setBind($M::$schema['form'][$name]['bind'], $return, $recursive);
        } else if(substr($name, 0, 1)=='_' || property_exists($M, $name) || $M::$allowNewProperties || (($cm=S::camelize($name, true)) && method_exists($M, 'get'.$cm) && method_exists($M, 'set'.$cm))) {
            $this->bind = $name;
            unset($M, $name, $schema);
            return array();
        } else {
            throw new AppException(array(S::t('Field name "%s" is bound to non-existing model'), $name));
        }
    }

    public function setScope($s)
    {
        // accept string and object scopes
        if($s) $this->scope = $s;
    }

    public function setUpdate($update)
    {
        $this->update = (bool) $update;
        if($this->bind) {
            if(!$this->update && !$this->getModel()->isNew()) {
                $this->disabled=true;
            }
        }
    }

    public function setInsert($insert)
    {
        $this->insert = (bool) $insert;
        if($this->bind) {
            if(!$this->insert && $this->getModel()->isNew()) {
                $this->disabled=true;
            }
        }
    }

    public function setPlaceholder($str) {
        if(is_string($str) && substr($str, 0, 1)=='*') {
            $tlib = ($this->bind && ($schema=$this->getSchema()))?('model-'.$schema->tableName):('form');
            $str = S::t(substr($str, 1), $tlib);
        }

        if(S::isempty($str)) $str = null; 
        $this->placeholder = $str;
    }

    public function setType($type)
    {
        if(!$type) {
            $type = 'text';
        }
        if(!method_exists($this, 'render'.S::camelize($type, true))) {
            throw new AppException(array(S::t('Field type "%s" is not available.'), $type));
        }
        $this->type = $type;
    }

    public static function id($name)
    {
        return trim(preg_replace('/[^0-9a-z\§\,]+/i', '_', $name),'_');
    }

    public function getId()
    {
        return static::id($this->getName(false));
    }

    public function getName($useAttributes=true)
    {
        $name = '';
        if (is_null($this->id)) {
            $this->id = 'f'.uniqid();
        }
        if($useAttributes && isset($this->attributes['name'])) {
            $id = $this->attributes['name'];
        } else {
            $id = $this->id;//S::slug($this->id);
        }
        if (isset($this->prefix) && $this->prefix) {
            $name = $this->prefix.'['.$id.']';
        } else {
            $name = $id;
        }
        if(substr($name, -2)!='[]' && ($this->multiple && $this->type!='form') || $this->type == 'file') {
            $name.='[]';
        }
        return $name;
    }

    public function getValue()
    {
        if(!isset($this->value) && $this->bind) {
            try {
                $M = $this->getModel();
                if(method_exists($M, $m='get'.S::camelize($this->bind, true))) {
                    $this->value = $M->$m();
                } else {
                    $this->value = $M->{$this->bind};
                    if($this->value===false && !property_exists($M, $this->bind)) {
                        $this->value = null;
                    }
                }
                if($this->value instanceof Collection) {
                    $this->value = ($this->value->count()>0)?($this->value->getItems()):(array());
                }

                if(isset($M::$schema->relations[$this->bind]) && $this->value && is_array($this->value)) {
                    foreach($this->value as $i=>$o) {
                        if(is_object($o) && $o->isDeleted()) unset($this->value[$i]);
                        unset($i, $o);
                    }
                }
            } catch(Exception $e) {
                $this->value = false;
            }
            if(($this->value===false || is_null($this->value)) && !is_null($this->default)) {
                $this->value = $this->default;
            }
        }

        return $this->value;
    }

    public function setValue($value=false, $outputError=true, $validation=null)
    {
        static $textChecks=['checkDns', 'checkEmail', 'checkIp', 'checkIpBlock'];
        if($validation && in_array($this->type, static::$typesNotForValidation)) return true;
        else if($validation===false) {
            $this->value = $value;
            return true;
        }

        $this->error=array();
        $v0 = $value = $this->parseValue($value);

        foreach($this->getRules() as $m=>$message) {
            $msg = '';
            try {
                $regex = null;
                if(substr($m, 0, 6)=='model:') {
                    $fn = substr($m, 6);
                    $tg = $this->getModel();
                } else if(substr($m, 0, 7)=='regexp:') {
                    $regex = substr($m,7);
                } else if(strpos($m, '::') && !strpos($m, '(')) {
                    list($tg, $fn) = explode('::', $m);
                } else {
                    $fn = 'check'.ucfirst($m);
                    $tg = $this;
                }
                if($regex) {
                    if(preg_match($regex, $value)) {
                        $value = false;
                    }
                } else if(method_exists($tg, $fn)) {
                    if(is_array($value) && $this->multiple && in_array($fn, $textChecks)) {
                        $r = [];
                        foreach($value as $i=>$v) {
                            if(trim($v)) {
                                $v = $this->checkDns($v, $message);
                                if(is_object($tg)) {
                                    $v = $tg->$fn($v, $message);
                                } else {
                                    $v = $tg::$fn($v, $message);
                                }
                                if(!S::isempty($v)) $r[] = $v;
                            }
                            unset($v, $i);
                        }
                        if(!$r) $r = '';
                        $value = $r;
                    } else {
                        if(is_object($tg)) {
                            $value = $tg->$fn($value, $message);
                        } else {
                            $value = $tg::$fn($value, $message);
                        }
                    }
                //} else {
                //    S::log('[DEPRECATED] is this necessary? ', "eval(\$value = {$m});");
                //    @eval("\$value = {$m};");
                }
                unset($tg, $fn);
                if($value===false && $outputError) {
                    if(count($this->error)==0) {
                        $msg = sprintf(S::t($message, 'exception'), $this->getLabel(), $v0);
                        $this->error[$msg]=$msg;
                        throw(new AppException($msg));
                    }
                    break;
                }
            } catch(Exception $e) {
                if($outputError) {
                    $msg = $e->getMessage();
                    if(S::$log) S::log('[INFO] Could not validate form: '.$e);
                    //$msg .= var_export($value, true)." {$m};";
                    $this->error[$msg] = $msg;
                }
                break;
            }
        }

        if($this->type=='form' && $this->bind) {
            if(!is_null($this->toAdd) && isset($this->toAdd[$this->bind])) {
                foreach($value as $oid=>$mvalue) {
                    foreach($this->toAdd[$this->bind] as $fn=>$fv) {
                        if($fv!='') {
                            $value[$oid][$fn]=$fv;
                        }
                    }
                }
            }
        }
        if(isset($this->filters) && is_array($this->filters)) {
            $F = $this->getForm();
            foreach($this->filters as $fn=>$w) {
                if(isset($F[$fn])) {
                    if($value) {
                        if(!is_array($F[$fn]->choicesFilter)) $F[$fn]->choicesFilter=array();
                        $F[$fn]->choicesFilter[$w]=$value;
                    } else if(isset($F[$fn]->choicesFilter[$w])) {
                        unset($F[$fn]->choicesFilter[$w]);
                    }
                }
                unset($fn, $w);
            }
            unset($F);
        }
        $this->value = $value;
        if($this->bind) {
            $o = $this->getModel();
            if(isset($o::$schema->relations[$this->bind])) {
                // map bindings
                $o->setRelation($this->bind, $value);
            }
        }

        if(isset($this->error) && count($this->error)>0) {
            return false;
        }
        $this->error = null;
        return true;
    }

    public function resetError()
    {
        $this->error=null;
    }

    public function checkRequired($value, $message='')
    {
        if($this->disabled) {
            $value = $this->getValue();
        }
        if($value=='') {
            throw new AppException(array(S::t($message, 'exception'), $this->getLabel(), $value));
        }
        return $value;
    }

    public function checkModel($value, $message='')
    {
        if(!isset($this->value)) {
            $this->getValue();
        }
        if($this->disabled) {
            return $this->value;
        } else if(!$this->bind) {
            return false;
        }
        $cn = $this->bind;
        $fn = ($cn!=$this->name)?($this->name):($cn);
        $M = $this->getModel();
        $serialize = null;
        if(isset($this->prefix) && $this->prefix) {
            $p0 = preg_replace('/[\[\.\]].+/', '', $this->prefix);
            if(isset($M::$schema['columns'][$p0]['serialize'])) {
                $serialize = $M::$schema['columns'][$p0]['serialize'];
            }
            if($serialize) {
                $cn = preg_replace('/[\[\]]+/', '.', $this->prefix).$cn;
            }
        }

        $m = 'validate'.S::camelize($fn, true);
        if(method_exists($M, $m) || method_exists($M, $m='validate'.S::camelize($cn, true))) {
            $newvalue = $M->$m($value);
            if(!is_bool($newvalue)) $value = $newvalue;
            unset($newvalue);
        }
        if($value!==$this->value || $M->$cn!==$value) {
            $value = $M->$cn = $value;
        }
        unset($cn, $M, $fn, $m);
        return $value;
    }

    public function checkChoices($value, $message='')
    {
        if($value===false) {
            return false;
        }
        if($this->type=='form') return $value;
        if(S::isempty($value) && !$this->required){
            $value=null;
            return $value;
        }
        if($this->multiple && (is_array($value) || strpos($value, ',')!==false)) {
            $join=false;
            if(!is_array($value)) {
                $value = explode(',', $value);
                $join = true;
            }
            $count=0;
            foreach($value as $k=>$v) {
                if(!S::isempty($v)) {
                    if(S::isempty($this->checkChoices($v, $message))) {
                        return false;
                    } else {
                        $count++;
                    }
                }
            }
            if($count===0 && $this->required) {
                return false;
            } else {
                if($join) {
                    $value = implode(',', $value);
                }
                return $value;
            }
        } else {
            if (!$this->getChoices($value)) {
                throw new AppException(array(S::t($message, 'exception'), $this->getLabel(), $value));
            }
        }
        return $value;
    }

    public function checkForm($value, $message='')
    {
        if(!is_array($value)) {
            $value = array();
            //throw new AppException(array(S::t($message, 'exception'), $this->getLabel(), $value));
        }
        $valid = true;
        $M = $this->getModel();
        $schema = $M::$schema;;
        $sid = $scope = (!$this->scope)?('subform'):($this->scope);
        $errors=[];

        if(!isset($schema->relations[$this->bind]) && $this->choices && is_string($this->choices) && isset($schema->relations[$this->choices]) && $schema->relations[$this->choices]['local']==$this->bind) {
            $this->bind = $this->choices;
            $this->choices=null;
        }
        if($this->bind && isset($schema->relations[$this->bind])) {
            $rel = $schema->relations[$this->bind];
            $R = $this->getValue();
            if(!$R) {
                $R = $M->getRelation($this->bind, null, null, false);
                if(!$R) $R=array();
            }
            if(is_object($R)) {
                if($R instanceof Collection) {
                    $R = $R->getItems();
                    if(!$R) $R=array();
                } else if($R instanceof Model) {
                    $R = array($R);
                    $M->setRelation($this->bind, $R);
                }
            } else if(!$R) {
                $R=array();
            }
            if(count($value)==0 && count($R)==0) {
                return $value;
            }
            $add=array();
            if($this->bind && isset($schema->relations[$this->bind])) {
                if(isset($rel['params'])) {
                    $add = $rel['params'];
                }
                $cn = (isset($rel['className']))?($rel['className']):($this->bind);
                if ($rel['type']=='one') {
                    $this->size=1;
                }
                if(!is_array($rel['foreign'])) {
                    $fk[] = $rel['foreign'];
                    $fkv = $M->{$rel['local']};
                    if(!S::isempty($fkv)) $add[$rel['foreign']] = $fkv;
                } else {
                    $fk = $rel['foreign'];
                    foreach($rel['foreign'] as $i=>$fn) {
                        $ln = $rel['local'][$i];
                        $fkv = $M->{$ln};
                        if(!S::isempty($fkv)) $add[$fn] = $fkv;
                    }
                }
                if(count($add) > 0) {
                    if(is_null($this->toAdd)) {
                        $this->toAdd=array();
                    }
                    $this->toAdd[$this->bind]=$add;
                }

                $vcount = count($value);
            }
            $new = ($M->isNew())?($rel['foreign']):(false);
            unset($M, $vcount, $schema);
            if(!is_array($scope)) $scope = $cn::columns($scope);
            if(!$scope) $scope = array_keys($cn::$schema['columns']);
            $bnull = array();
            foreach($scope as $label=>$fn) {
                if(is_array($fn)) {
                    if(isset($fn['bind'])) {
                        $fn = $fn['bind'];
                    } else {
                        continue;
                    }
                }
                if($p=strrpos($fn, ' ')) $fn = substr($fn, $p+1);
                if(!isset($add[$fn]) && ((isset($cn::$schema['columns'][$fn]) && !isset($cn::$schema['columns'][$fn]['primary'])) || substr($fn, 0, 1)=='_')) {
                    $bnull[$fn]='';
                }
                unset($fn);
            }
            foreach($value as $i=>$v) {
                if(!is_array($v)) {
                    if(!$v) continue;
                    foreach($scope as $fn) {
                        if(strpos($fn, ' ')) $fn = substr($fn, strrpos($fn, ' '));
                        $v = [$fn => $v];
                        unset($fn);
                        break;
                    }
                }
                $v += $bnull;
                if(isset($R[$i])) {
                    $O = $R[$i];
                    if(is_array($O)) {
                        $v += $O;
                        $O = new $cn($O, null, false);
                    } else {
                        // check if $pk changed, if it did, remove old record
                        if($pk = $O->getPk(true)) {
                            $pkdel = true;
                            foreach($pk as $pkf=>$pkv) {
                                if(!($pkv && isset($v[$pkf]) && $v[$pkf]!=$pkv)) {
                                    $pkdel = false;
                                    unset($pk[$pkf], $pkf, $pkv);
                                    break;
                                }
                                unset($pk[$pkf], $pkf, $pkv);
                            }
                            unset($pk);
                            if($pkdel) {
                                $R[microtime()]=$O;
                                unset($O);
                                $O = new $cn($v, null, false);
                            } else {
                                $v += $O->asArray();
                            }
                        } else {
                            $v += $O->asArray();
                        }
                    }
                    unset($R[$i]);
                } else {
                    $v += $add;
                    $O = new $cn(null, null, false);
                }
                try {
                    $fid = $this->getName().'['.$i.']';
                    if(!($F=Form::getInstance($fid))) {
                        $F = $O->getForm($sid, true, $this->form);
                        $F->prefix = $fid;
                        $F->setLimits(false);
                        $F->register($fid);
                    }
                    if(is_array($new)) {
                        foreach($new as $fn) $F[$fn]->disabled=true;
                    } else if($new) {
                        if(isset($F[$new]))
                            $F[$new]->disabled=true;
                    }
                    if(!$F->validate($v)) {
                        $valid = false;
                        if($ea = $F->getError()) {
                            $errors[] = $ea;
                        }
                    }
                    $value[$i] = $O;
                } catch(Exception $e) {
                    throw new AppException(array(S::t($message, 'exception').' '.$e->getMessage(), $this->getLabel(), $value));
                }
                unset($F, $O, $v);
            }

            unset($bnull);
            if($R) {
                foreach($R as $i=>$O) {
                    $O->delete(false);
                    $value[] = $O;
                    unset($O, $R[$i], $i);
                }
            }
        } else if($this->bind && ($fo=$this->getSubForm())) {
            if(!$value) {
                $value = array();
            } else if(!is_array($value) && !is_object($value)) {
                $value = S::unserialize($value, $this->serialize);
            }
            $p0 = $fo['prefix'];
            $fo['prefix'] = $p0;

            foreach($value as $i=>$o) {
                unset($value[$i]);
                $fo['id'] = $p0.'['.$i.']';
                $F = Form::instance($fo['id'], $fo);
                $F->setLimits(false);
                if(!$F->validate($o)) {
                    $valid = false;
                    $errors[$fo['id']] = $F->getError();
                    break;
                } else {
                    $value[$i] = $F->getData();
                }
            }
        } else {
            $valid = false;
        }
        if(!$valid) {
            $err = sprintf(S::t($message, 'exception'), $this->getLabel());
            if($errors) $err .= implode('', $errors);
            $this->setError($err);
            //throw new AppException(array(S::t($message, 'exception'), $this->getLabel(), $value));
            //return false;
        }
        return $value;
    }

    public function checkSize($value, $message='')
    {
        if($this->type=='form') {
            if(is_object($value)) {
                if($value instanceof Model) {
                    $size = 1;
                } else if(($value instanceof Model) || method_exists($value, 'count')) {
                    $size = $value->count();
                } else {
                    $size = count((array)$value);
                }
            } else {
                $size = (is_array($value))?(count($value)):(0);
            }
            $message = '%s should have at least %s items.';
        } else {
            if($this->type=='float' || $this->type=='decimal' || $this->type=='number') {
                $value = (string)(float) $value;
            } else if($this->type=='int' && abs($value)>0) {
                $value = (string)(int) $value;
            } else if(is_string($value)) {
                $value = str_replace("\r", '', $value);
            }
            if(is_array($value)) {
                $size = count($value);
            } else if(function_exists('mb_strlen')) {
                $size = mb_strlen((string)$value, 'UTF-8');
            } else {
                $size = strlen($value);
            }
        }
        if (($this->min_size && $size < $this->min_size && $size>0) || ($this->size && $size > $this->size)) {
            if(is_array($message)) {
                $message[0]=S::t($message[0], 'exception');
                $err = $message;
            } else {
                $err = array(S::t($message, 'exception'));
            }
            $err[] = $this->getLabel();
            $err[] = $this->min_size;
            $err[] = $this->size;
            $err[] = $value;
            throw new AppException($err);
        }
        return $value;
    }

    public function checkRange($value, $message='')
    {
        $r = $this->range;
        $err = null;
        if(substr($this->type, 0, 4)=='date') {
            $v = S::strtotime($value);
            if(!is_int($r[0])) $r[0] = S::strtotime($r[0]);
            if(!is_int($r[1])) $r[1] = S::strtotime($r[1]);
        } else if(is_numeric($value)) {
            $v = $value;
        } else {
            $err = array('%s should be a number.');
        }

        if(!$err && (!($v >= $r[0]) || !($v <= $r[1]))) {
            $err = $message;
        }
        if($err) {
            if(!is_array($err)) {
                $err = array(S::t($err, 'exception'));
                $err[] = $this->range[0];
                $err[] = $this->range[1];
                $err[] = $this->getLabel();
                $err[] = $value;
            } else {
                $err[0] = S::t($err[0], 'exception');
            }
            throw new AppException($err);
        }

        return $value;
    }

    public function preCheckFile()
    {
        if(!isset($this->accept['uploader']) || !$this->accept['uploader']) return;

        $uid = Form::userToken();
        if(!isset($this->attributes['data-uploader-id']) || !$this->attributes['data-uploader-id']) {
            $this->attributes['data-uploader-id'] = S::compress64($uid.':'.$this->_className.':'.$this->id);
        }

        if(App::request('headers', 'x-studio-action')==='Upload' && ($upload=App::request('post', '_upload')) && $upload['uploader']==$this->attributes['data-uploader-id']) {
            static $timeout = 300;
            // check id

            /**
             $upload = [
             'file' => file name in the client computer
             'total' => size of the file 
             ];
            */
            $err = [];
            $retry = false;
            $type = null;
            if($this->accept) {
                if(isset($this->accept['size'])) {
                    if(is_numeric($size=$this->accept['size']) && $this->accept['size'] < $upload['total']) {
                        $err[] = sprintf(S::t('Uploaded file exceeds the limit of %s.'), S::bytes($size));
                    }
                }
                if(isset($this->accept['type'])) $type = $this->accept['type'];
                else if(isset($this->accept['format'])) $type = $this->accept['format'];

                if(isset($this->accept['extension'])) {

                }
            }

            $ckey = 'upload-'.hash('sha256', $upload['uid'].':'.$uid.':'.preg_replace('/(ajax|_index|\&_retry)=[0-9]+/', '', S::requestUri()));
            $wkey = $ckey.'w';
            $size = $upload['end'] - $upload['start'];
            if(!($u=Cache::get($ckey, $timeout))) {
                $f = tempnam(self::$tmpDir, $ckey);
                $u = array(
                    'id'=>$upload['id'],
                    'name'=>$upload['file'],
                    'file'=>$f,
                    'size'=>$upload['total'],
                );
                Cache::set($ckey, $u, $timeout);
            }
            $data = $upload['data'];
            if(strpos($data, ',')!==false) $data = substr($data, strpos($data, ',')+1);
            $fp=fopen($u['file'],"r+");
            fseek($fp, $upload['start']);
            $r = fwrite($fp, base64_decode($data), $size);
            fclose($fp);
            if(!$r || $r!=$size) {
                S::log('[INFO] Problem writing '.$u['file'].'. Expected to write '.$size.' bytes, but wrote '.$r);
                $err[] = 'There was a problem writing the file upload.';
                $retry = true;
            }
            if($type && !isset($u['type'])) {
                $ftype = S::fileFormat($u['file'], false, $u['name'], ['application/octet-stream', 'text/plain']);
                if($ftype) {
                    try {
                        $this->checkFileType($ftype);
                        if(!isset($u['type'])) {
                            $u['type'] = $ftype;
                        }
                    } catch(Exception $e) {
                        $err[] = $e->getMessage();
                    }
                }
            }
            if($err) {
                App::status(400);
                $R = ['message'=>implode("\n", $err)];
                if($retry) $R['retry'] = true;
                unlink($u['file']);
                Cache::delete($ckey);
            } else {
                $w = (int)Cache::get($wkey, $timeout);
                $w += $size;
                Cache::set($wkey, $w, $timeout);
                Cache::set($ckey, $u, $timeout);
                $R = array('size'=>$size, 'total'=>$w, 'expects'=>$u['size']);
                if($w>=$u['size']) {
                    $R['id'] = $upload['id'];
                    $R['value'] = 'ajax:'.$ckey.'|'.$upload['file'];
                    $R['file'] = $upload['file'];
                }
                unset($upload);
            }

            S::output($R, 'json');
        }

    }

    public function checkFile($value=false, $message='')
    {
        // check ajax uploader
        if(is_string($value) && preg_match('/^ajax:([^\|]+)/', $value, $m)) {
            $uid = $m[1];
            unset($m);
            if(($u=Cache::get($uid)) && (file_exists($u['file']))) {
                $value = [
                    'tmp_name'  => $u['file'],
                    'error'     => 0,
                    'name'      => $u['name'],
                    'size'      => $u['size'],
                    'type'      => S::fileFormat($u['name']),
                    'ajax'      => true,
                ];
                if(!$value['type']) $value['type'] = S::fileFormat($u['file']);
                Cache::delete($uid);
            } else {
                throw new AppException(S::t('Could not read uploaded file.', 'exception'));
            }
        }

        if(is_array($value)){
            if(isset($value['name'])) {
                $value = array($value);
            }
            $uploadDir = S::uploadDir();

            $max  = false;
            $type = false;
            $size = false;
            $hash = false;
            $thumb = false;
            $ext  = false;
            if($this->accept) {
                $max = (isset($this->accept['max']))?($this->accept['max']):($max);
                $size = (isset($this->accept['size']))?($this->accept['size']):($size);
                $type = (isset($this->accept['format']))?($this->accept['format']):($type);
                $type = (isset($this->accept['type']))?($this->accept['type']):($type);
                $hash = (isset($this->accept['hash']))?($this->accept['hash']):($hash);
                $ext = (isset($this->accept['extension']))?($this->accept['extension']):($ext);
                $thumb = (isset($this->accept['thumbnail']))?($this->accept['thumbnail']):($thumb);
            }
            if(!$hash || !isset(self::$hashMethods[$hash])) {
                $hash = 'datetime';
            }

            if(!$hash || !method_exists($this, $hfn='checkFileHash'.S::camelize($hash, true))) {
                $hash = 'datetime';
                $hfn = 'checkFileHashDatetime';
            }

            try {
                if($max && count($value)>$max) {
                    throw new AppException(array(S::t('You are only allowed to upload up to %s files.', 'exception'), $max));
                }
                $new = array();
                foreach($value as $i=>$upload) {
                    if(is_array($upload) && count($upload)==1) {
                        $upload = array_shift($upload);
                    }

                    if(!is_array($upload) && substr($upload, 0, 5)=='ajax:') {
                        $upload = array('_'=>$upload);
                    } else if(!is_array($upload)) {
                        $a = explode('|', $upload);
                        $upload = ['name'=>array_pop($a), '_'=>$upload];
                        if($a) $upload['tmp_name'] = array_shift($a);
                        else $upload['tmp_name'] = $upload['name'];
                        if($a) $upload['type'] = array_shift($a);
                    }
                    if(isset($upload['_']) && is_array($upload['_'])) {
                        unset($upload['_']);
                    } else if(isset($upload['_']) && substr($upload['_'], 0, 5)=='ajax:') {
                        $uid = substr($upload['_'], 5, strpos($upload['_'], '|') -5);

                        if(($u=Cache::get($uid)) && file_exists($u['file'])) {
                            $upload['tmp_name'] = $u['file'];
                            $upload['error'] = 0;
                            $upload['name'] = $u['name'];
                            $upload['size'] = $u['size'];
                            $upload['type'] = S::fileFormat($u['name']);
                            if(!$upload['type']) $upload['type'] = S::fileFormat($u['file']);
                            $upload['ajax'] = true;
                            Cache::delete($uid);
                        } else {
                            throw new AppException(S::t('Could not read uploaded file.', 'exception'));
                        }
                    }
                    /**
                     * Result should be [disk-name]|[user-name]
                     * Multiple files are listed one per line
                     */
                    if(!isset($upload['error']) || $upload['error']==4) {
                        // no upload made, skipping
                        if(isset($upload['_']) && $upload['_']) {
                            $new[$i] = $upload['_'];
                        } else {
                            $value[$i] = false;
                        }
                        continue;
                    }
                    $name = preg_replace('#[\?\#\$\%,\|/\\\\]+#', '', preg_replace('#[\s]+#', ' ', S::encodeUTF8($upload['name'])));
                    if($upload['error']>0) {
                        throw new AppException(S::t('Could not read uploaded file.', 'exception'));
                    } else if($size && $upload['size']>$size) {
                        throw new AppException(array(S::t('Uploaded file exceeds the limit of %s.', 'exception'), S::bytes($size)));
                    }
                    $file = $dest = $upload['tmp_name'];
                    $file = $this->$hfn($name, $dest);
                    $this->checkFileType($upload['type']);

                    if($ext && strpos($dest, '.')===false) {
                        $ext = null;
                        if(strpos($upload['name'], '.') && isset(S::$formats[$ext=strtolower(substr($upload['name'], strrpos($upload['name'], '.')+1))])) {
                            if(S::$formats[$ext]!=$upload['type']) {
                                $ext = null;
                            }
                        } else {
                            $ext = null;
                        }
                        if(!$ext && !($ext=array_search($upload['type'], S::$formats))) {
                            if(preg_match('/\.([a-z0-9]){,5}$/i', $upload['name'], $m)) {
                                $ext = strtolower($m[1]);
                            }
                        }
                        if($ext) $file .= '.'.$ext;
                    }

                    if($ext && isset(S::$formats[$ext])) {
                        $this->checkFileType(S::$formats[$ext]);
                    }

                    $dest = $uploadDir.'/'.$file;
                    $dir = dirname($dest);
                    if(!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    if(isset($upload['ajax'])) {
                        @rename($upload['tmp_name'], $dest);
                        if(!file_exists($dest)) throw new AppException(S::t('Could not read uploaded file.', 'exception'));
                    } else if(!is_uploaded_file($upload['tmp_name']) || !copy($upload['tmp_name'], $dest)) {
                        throw new AppException(S::t('Could not read uploaded file.', 'exception'));
                    }
                    $new[$i]="{$file}|{$upload['type']}|{$name}";
                }
                $value = implode(",", $new);
            } catch(Exception $e) {
                $msg = $e->getMessage();
                $this->error[$msg]=$msg;
                $value = false;
            }
        }
        /*
        if(S::isempty($value) && $this->bind && ($schema=$this->getSchema()) && isset($schema['columns'][$this->bind])) {
            $value=$this->getModel()->{$this->bind};
            if(S::isempty($value)) $value=null;
        }
        */
        return $value;
    }

    public function checkFileHashDatetime($name, $file)
    {
        return date('Ymd/His_').S::slug($name,'._');
    }

    public function checkFileHashTime($name, $file)
    {
        return microtime(true);
    }

    public function checkFileHashMd5($name, $file)
    {
        return md5_file($file);
    }

    public function checkFileHashSha1($name, $file)
    {
        return sha1_file($file);
    }

    public function checkFileHashNone($name, $file)
    {
        return $name;
    }

    public function checkFileType($filetype, $message=null)
    {
        if(!$this->accept) return $filetype;

        if(isset($this->accept['type'])) $type = $this->accept['type'];
        else if(isset($this->accept['format'])) $type = $this->accept['format'];
        else $type=null;

        if(!$type) return $filetype;

        if($type && !is_array($type)) {
            $type = preg_split('/[\s\,\;]+/', $type, -1, PREG_SPLIT_NO_EMPTY);
        }
        if($type && isset($type[0])) {
            $types = array();
            foreach($type as $ts) {
                $multiple = false;
                if(substr($ts, -1)=='*') {
                    $ts = substr($ts, 0, strlen($ts)-1);
                    $multiple = true;
                } else if(substr($ts, -1)=='/') {
                    $multiple = true;
                } else if(strpos($ts, '/')===false && isset(S::$formats[$ts])) {
                    $ts = S::$formats[$ts];
                }
                if($multiple) {
                    foreach(S::$formats as $ext=>$tn) {
                        if(substr($tn, 0, strlen($ts))==$ts) {
                            $types[$tn]=$tn;
                        }
                    }
                } else {
                    $types[$ts]=$ts;
                }
            }
            $type = $types;
            unset($types);
            $this->accept['type']=$type;
        }

        if ($type && !in_array($filetype, $type) && !in_array(substr($filetype, 0, strpos($filetype, '/')),$type)) {
            if(is_null($message)) $message = S::t('This file format is not supported.', 'exception');
            throw new AppException($message);
        }

        return $filetype;

    }

    public function checkDns($value, $message='')
    {
        $value = trim($value);
        if($value && !S::checkDomain($value, array('SOA'), false)) {
            $message = S::t('This is not a valid domain.', 'exception');
            $this->error[$message]=$message;
        }
        return $value;
    }

    public function checkIp($value, $message='')
    {
        $value = trim($value);
        if($value && !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) {
            $message = S::t('This is not a IP address.', 'exception');
            $this->error[$message]=$message;
        }
        return $value;
    }

    public function checkIpBlock($value, $message='')
    {
        static $err = 'This is not a valid IP block.';
        $ip = trim($value);
        if($ip) {
            $mask = null;
            if($p=strpos($ip, '/')) {
                $ip = substr($ip, 0, $p);
                $mask = substr($ip, $p+1);
            }
            if(!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) {
                $ip = false;
            } else if(!is_numeric($mask)) {
                $ip = false;
            }
        }
        if($value && !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) {
            $message = S::t('This is not a IP address.', 'exception');
            $this->error[$message]=$message;
        }
        return $value;
    }

    public function checkEmail($value, $message=null)
    {
        $value = trim($value);
        if($value && !S::checkEmail($value, false)) {
            if(!$message) {
                $message = S::t('This is not a valid e-mail address.', 'exception');
            }
            $this->error[$message]=$message;
        }
        return $value;
    }

    public function checkDate($value, $message='')
    {

        if($value != '' && !preg_match('/^[0-9]{4}(-[0-9]{1,2}(-[0-9]{1,2}([ T][0-9]{1,2}(:[0-9]{1,2}(:[0-9]{1,2}(\.[0-9]+)?)?)?)?)?)?$/', $value)) {
            $value = date('Y-m-d H:i:s', S::strtotime($value, false));
        }
        return $value;
    }

    public function checkDatetime($value, $message='')
    {
        if($value != '' && !preg_match('/^[0-9]{4}(-[0-9]{1,2}(-[0-9]{1,2}([ T][0-9]{1,2}(:[0-9]{1,2}(:[0-9]{1,2}(\.[0-9]+)?)?)?)?)?)?$/', $value)) {
            $value = date('Y-m-d H:i:s', S::strtotime($value));
        }
        return $value;
    }

    public function getRules()
    {
        if(!isset($this->rules) || !is_array($this->rules)) {
            $this->rules = [];
        }
        $rules = [];
        $m = null;

        if($this->type && method_exists($this, S::camelize('check-'.$this->type))) {
            $rules[$this->type]=static::$defaultErrorMessage;
            $m = ucfirst($this->type);
        }

        if($m!='None') {
            if($this->required) {
                $rules['required']='"%s" is mandatory and should not be blank.';
            }
            if($this->bind) {
                $rules['model']=static::$defaultErrorMessage;
            } else if ($this->form && $this->getModel() && method_exists($this->getModel(), ($m='validate'.S::camelize($this->id, true)))) {
                $rules['model:'.$m]=static::$defaultErrorMessage;
            }
            if($this->choices) {
                $rules['choices']=static::$defaultErrorMessage;
            }
            if($this->min_size && $this->size) {
                $rules['size']=array("Should have between %s and %s characters.", $this->min_size, $this->size);
            } else {
                if($this->size) {
                    $rules['size']=array("Should be smaller than %s characters.", $this->size);
                } else if($this->min_size) {
                    $rules['size']=array("Should be greater than %s characters.", $this->min_size);
                }
            }
            if($this->range && is_array($this->range) && count($this->range)==2) {
                $rules['range'] = array("Should be between %s and %s.", $this->range[0], $this->range[1]);
            }
        }
        $rules = $this->rules + $rules;
        if(is_array($this->messages)) {
            foreach($rules as $rn=>$m) {
                if(isset($this->messages[$rn])) {
                    $rules[$rn]=$this->messages[$rn];
                }
            }
        }
        return $rules;
    }

    public static function properties($fd, $new=null)
    {
        if(is_object($fd)) {
            $fd = $fd->value();
        }
        if(isset($fd['increment'])) {
            if($fd['increment']=='auto') {
                $fd['type']='hidden';
                $fd['required']=false;
            }
            unset($fd['increment']);
        }
        if(isset($fd['format'])) {
            $fd['type'] = $fd['format'];
            unset($fd['format']);
        }
        if(!isset($fd['type'])) {
            $fd['type']='text';
        }
        if(substr($fd['type'], -3)=='int' || $fd['type']=='float' || $fd['type']=='decimal') {
            $fd['type']='number';
        } else if($fd['type']=='string' && ((isset($fd['name']) && strpos($fd['name'], 'password')!==false)||(isset($fd['id']) && strpos($fd['id'], 'password')!==false))) {
            $fd['type']='password';
            if(!$new) {
                $fd['required']=false;
            }
        }

        return $fd;
    }

    /**
     * Value adjustment
     *
     * Whenever a form is posted, the values might need adjustment, like to convert search strings to keys, arrays to strings.
     *
     */
    public function parseValue($value=false)
    {
        $type = $this->type;
        if(substr($type, 0, 4)=='date') {
            if(is_array($value)) {
                ksort($value);
                $value = implode('-', $value);
            }
            if(substr($value, 0, 10)=='0000-00-00' || substr($value, 0, 2)=='--') {
                $value = '';
            } else if(preg_match('/^0{4}|[\-]0{1,2}[\-\s]/', $value)) {
                $value = preg_split('/[\-\s\:T]/', $value);
            }
            if(is_array($value)) {
                if(!implode('', $value)) {
                    return null;
                }
                ksort($value);
                foreach($value as $k=>$v){
                    if(!(int)$v) {
                        $value[$k] = ($k==0)?('1970'):('01');
                        if($k>1) {
                            break;
                        }
                    }
                }
                if(count($value)>3) { // datetime
                    $value = implode('-', array_slice($value, 0, 3)).' '.implode(':', array_slice($value, 3));
                } else {
                    $value = implode('-', $value);
                }
            }
        }
        if(is_array($value) && $type=='file') {
            $value = $this->checkFile($value);
        }
        if($this->multiple) {
            if(is_array($value)) $value = array_filter($value, ['tdz','notEmpty']);
            if(S::isempty($value)) $value = null;
        } else if($type!='form') {
            if(is_array($value)) {
                $value = ($this->serialize)?(S::serialize($value, $this->serialize)):(S::implode($value));
            } else if($value===false) {
                $value = '';
            }
        }
        if($value===false){
            $value=null;
        }
        return $value;
    }

    public function getError()
    {
        return (isset($this->error)) ?$this->error :null;
    }

    public function getClass()
    {
        $cn = (isset($this->class)) ?$this->class :'';
        if($this->readonly) {
            $cn .= ' readonly';
        }
        if($this->disabled) {
            $cn .= ' disabled';
        }
        foreach(self::$propertyAsClassName as $n) {
            if(isset($this->$n) && $this->$n) {
                $cn .= ' p-'.$n;
            }
        }
        return trim($cn);
    }

    public function setError($msg)
    {
        if(!is_array($this->error) || !$msg) {
            $this->error = [];
        }
        if($msg) {
            if(is_array($msg)) {
                $this->error = S::mergeRecursive($msg, $this->error);
            } else {
                $this->error[(string)$msg]=$msg;
            }
        }

        return $this;
    }

    public function setChoices($s)
    {
        static $schemaProp = ['orderBy'=>'order', 'order'=>'order' ];

        $Q = false;
        if(S::isempty($s)) {
            $Q = null;
            $this->choices = null;
        } else if(is_string($s)) {
            $this->choices = null;
            if(strpos($s, '::')) {
                list($model, $method) = explode('::', $s, 2);
                if(substr($method, 0, 1)==='$' && property_exists($model, $p=substr($method, 1))) {
                    return $this->setChoices($model::${$p});
                } else if(is_a($model, 'Studio\\Model', true)) {
                    if(strpos($method, '(')!==false) $method = substr($method, 0, strpos($method, '('));
                    $Q = [ 'model' => $model, 'method' => $method ];
                } else {
                    $Q = [ 'method' => $s ];
                }
            } else if(strpos($s, ':')) {
                $Q = [ 'source' => $s ];
            } else if(is_a($s, 'Studio\\Model', true)) {
                $Q = ['model'=>$s];
            } else if($this->bind && ($M=$this->getModel()) && method_exists($M, $s)) {
                $Q = [ 'model' => $M, 'method' => $s ];
            } else if(strpos($s, ',')!==false) {
                $this->choices = preg_split('/\s*\,\s*/', $s, -1, PREG_SPLIT_NO_EMPTY);
                $Q = null;
            } else {
                $Q = [ 'method' => $s ];
            }
        } else if(is_object($s)) {
            if($s instanceof Query) {
                $Q = $s;
                $this->choices = null;
            } else if($s instanceof Collection) {
                if($q = $s->getQuery()) {
                    $Q = [ 'queryObject' => $q ];
                    $q = null;
                    if($q = $s->getClassName()) {
                        $Q['model'] = $q;
                    }
                    $q = null;
                    if($q = $s->getQueryKey()) {
                        $Q['queryKey'] = $q;
                    }
                    $q = null;
                    $this->choices = null;
                } else {
                    $this->choices = $s->getItems();
                }
            } else if($s instanceof Model) {
                $Q = [ 'model' => get_class($s) ];
                $this->choices = null;
            }
        } else if(is_array($s)) {
            $this->choices = $s;
        }

        if(is_null($Q)) {
            $this->query = null;
        } else if($Q!==false) {
            if(isset($Q['model']) && !isset($Q['method']) && property_exists($Q['model'], 'schema')) {
                $cn = $Q['model'];
                foreach($schemaProp as $n=>$t) {
                    if(isset($cn::$schema[$n])) $Q[$t] = $cn::$schema[$n];
                    unset($n, $t);
                }
            }
            $this->query = new Query($Q);
            unset($Q);
        }
    }

    public function countChoices($check=null)
    {
        return $this->getChoices($check, true);
    }

    public function getChoices($check=null, $count=false)
    {
        $noexec = null;
        if(!isset($this->choices) && !isset($this->query)) {
            if($this->bind && ($M=$this->getModel()) && method_exists($M, ($m='choices'.S::camelize($this->bind,true)))) {
                $this->query = new Query([ 'model'=> $M, 'method' => $m ]);
            }
            unset($M, $m);
        }
        if (!isset($this->choices) && !isset($this->_choicesCollection) && isset($this->query)) {
            $this->choices = [];
            $q = null;
            if(isset($this->choicesFilter) && is_array($this->choicesFilter)) {
                $q = (isset($this->choicesFilter['where'])) ?$this->choicesFilter :['where'=>$this->choicesFilter];
            }
            $this->_choicesCollection = $this->query->find($q);
            unset($q);
            if(is_array($this->_choicesCollection)) {
                $this->choices = $this->_choicesCollection;
                $this->_choicesCollection = null;
            }
        } else if(!isset($this->choices)) {
            $this->choices = [];
        }
        if(!$this->choices && $this->_choicesCollection) {
            $c = $this->_choicesCollection->count();
            if(!is_null($check)) {
                if(!$c) {
                    return false;
                } else if(!is_array($check)) {
                    return $this->_choicesCollection[$check];
                } else {
                    $this->_choicesCollection->setQuery($check);
                    $c = $this->_choicesCollection->count();
                    if(!$c) return false;
                    return $this->_choicesCollection->getItems();
                }
            } else if($count) {
                return $c;
            }
            $k = $this->_choicesCollection->getQueryKey();
            if($c && ($c < static::$maxOptions || !$k)) {
                foreach($this->_choicesCollection->getItems() as $r) {
                    $this->choices[($k) ?$r->$k :$r->getPk()]=$r;
                    unset($r);
                }
            }
            if(!$c) {
                $this->_choicesCollection = null;
            }
        }
        if(!is_null($check) && !is_array($check)) {
            if(isset($this->choices[$check])) {
                return $this->choices[$check];
            }
            return false;
        } else if($count) {
            return count($this->choices);
        }
        if(!$this->_choicesTranslated) {
            $schema=$this->getSchema();
            $tlib = ($this->bind)?('model-'.$schema['tableName']):('field');
            foreach($this->choices as $k=>$v) {
                if(is_array($v) && isset($v['value'])) {
                    $v = $v['value'];
                } else if(is_array($v)) {
                    while(is_array($v)) {
                        $v = array_shift($v);
                    }
                }
                $val = $v;
                if($val && substr($val, 0, 1)=='*') {
                    $val = S::t(substr($val, 1), $tlib);
                    if(is_array($v)) {
                        $this->choices[$k]['value']=$val;
                    } else {
                        $this->choices[$k]=$val;
                    }
                }
            }
        }

        return $this->choices;
    }

    public function getLabel()
    {
        $ttable = false;
        if (!isset($this->label) && $this->type!=='hidden') {
            $id = ($this->bind)?($this->bind):($this->id);
            $label = ucwords(strtr(S::uncamelize($id), '-_', '  '));
            $ttable = 'labels';
            if($schema=$this->getSchema()) {
                $ttable = 'model-'.$schema['tableName'];
            }
            $this->label = S::t(trim($label), $ttable);
        }
        if(isset($this->label) && substr($this->label, 0, 1)=='*' && strlen($this->label)>1) {
            if(!$ttable) {
                $ttable = 'labels';
                if($schema=$this->getSchema()) {
                    $ttable = 'model-'.$schema->tableName;
                }
            }
            $this->label = S::t(substr($this->label, 1), $ttable);
        }
        return (isset($this->label)) ?$this->label :'';
    }

    public function render($arg=array())
    {
        $arg0 = $arg;
        $M = ($this->bind && !isset($arg['no-render-model'])) ?$this->getModel() :null;

        $base = array('id', 'name', 'value', 'error', 'label', 'class');
        foreach ($base as $k) {
            if (!isset($arg[$k])) {
                $m = 'get'.ucfirst($k);
                $arg[$k]=$this->$m();
            }
            unset($k);
        }

        $prefix = (isset($this->prefix)) ?(string)$this->prefix :'';
        if($this->filters) {
            $filter = $this->filters;
            if(is_array($filter)) {
                if(!isset($filter[0])) $filter = array_keys($filter);
            }
            if($prefix) {
                $this->attributes['data-filters']=$prefix.'['
                    . ((is_array($filter))?(implode('],'.$prefix.'[',$filter)):($filter))
                    . ']';
            } else {
                $this->attributes['data-filters']=(is_array($filter))?(implode(',',$filter)):($filter);
            }
        }
        if($this->next) {
            $n = explode(',',$this->next);
            foreach($n as $k=>$v) {
                $n[$k] = (substr($v,0,1)=='!')?('!'.static::id($prefix.substr($v,1))):(static::id($prefix.$v));
                unset($k, $v);
            }
            $this->attributes['data-next']=implode(',',$n);
        }
        if($this->logic && is_array($this->logic)) {
            $this->attributes['data-logic'] = S::serialize($this->logic, 'json');
        }
        $run = array(
            'variables' => $arg,
        );
        $input=false;

        if($M && $this->attributes) {
            foreach($this->attributes as $k=>$v) {
                if(preg_match_all('#`([^`]+)`#', $v, $vm)) {
                    $r = $s = array();
                    foreach($vm[1] as $i=>$nfn) {
                        $s[]=$vm[0][$i];
                        $r[]=$M->renderField($nfn);
                        unset($i, $nfn);
                    }
                    $this->attributes[$k] = str_replace($s, $r, $v);
                    unset($r, $s, $vm);
                }
                unset($k, $v);
            }
        }

        if($M) {
            $arg['no-render-model'] = true;
            $m = S::camelize('render-'.$this->id.'FormField');
            if(method_exists($M, $m)) {
                $input = $M->$m($arg, $this);
            }
            unset($M, $m);
        }

        $Type = S::camelize($this->type, true);
        if(!$input) {
            $m = 'render' . $Type;
            if (!method_exists($this, $m)) {
                $m = 'renderText';
            }
            $input = $this->$m($arg);
        }
        if(!$input) {
            return $input;
        }
        if(is_array($input) && isset($input['input'])) {
            $run['variables'] = $input + $run['variables'];
            $input = $run['variables']['input'];
        }

        if(static::$templateProperties) {
            foreach(static::$templateProperties as $n) {
                if(isset($this->$n) && !isset($run['variables'][$n])) {
                    $run['variables'][$n] = $this->$n;
                }
                unset($n);
            }
        }
        $tpl = ($this->template) ?S::templateFile($this->template) :null;
        if (!$tpl && isset($arg['template'])) {
            if($arg['template']===false) $arg['template'] = 'input';
            $tpl = S::templateFile($arg['template'], $arg['template'].'.php');
        }
        if(!$tpl) {
            $tpl = S_ROOT.'/data/templates/field.php';
        }
        $run['script']=$tpl;
        $run['variables']['input']=$input;
        $run['variables']['field']=$this;
        $run['variables']['before']=$this->before;
        $run['variables']['after']=$this->after;

        $s = null;
        if($this->templates) {
            $tpls = $this->templates;
            $this->templates = null;
            foreach($tpls as $id=>$tpl) {
                $base = clone $this;
                if($base->templates) $base->templates=null;
                foreach($tpl as $n=>$v) {
                    if(method_exists($base, $m='set'.S::camelize($n, true))) $base->$m($v);
                    else $base->$n = $v;
                }
                $s .= '<template class="s-hidden s-template '.S::xml($id).'">'
                    . $base->render($arg0)
                    . '</template>';
                unset($base);
            }
        }

        return S::exec($run).$s;
    }

    public function renderObject(&$arg)
    {
        $input = '<input type="hidden" id="'.S::xml($arg['id']).'" name="'.S::xml($arg['name']).'" />';
        $jsinput = '';
        $prefix ='';
        /*
        // @TODO: link custom schemas for the object
        $bind = $this->bind;
        $schema=$this->getSchema();
        */

        $fo = [
            'fields'=>[
                'property'=>['type'=>'text', 'required'=>true, 'label'=>'*Property', 'class'=>'i1s2'],
                'value'=>['type'=>'text', 'label'=>'*Value', 'class'=>'i1s2'],
            ],
        ];

        if($this->scope) {
            foreach($fo['fields'] as $fn=>$fd) {
                if(isset($this->scope[$fn])) {
                    $o = $this->scope[$fn];
                    if(!is_array($o)) $o = ['label'=>$o];
                    $fo['fields'][$fn] = $o+$fd;
                }
            }
        }

        if($this->multiple) $this->multiple = false;
        $prefix = $this->getName();
        $fo['id'] = $prefix.'[§]';
        $form = Form::instance($fo['id'], $fo, true);
        $form->setLimits(false);
        $jsinput = '<div class="item">';
        foreach($form->fields as $fn=>$f) {
            $jsinput .= $f->render();
            unset($fn, $f);
        }
        $jsinput .= '</div>';

        $value = $this->getValue();

        if(!is_array($value)) {
            $value = S::unserialize($value, $this->serialize);
        }

        // loop for each entry and add to $input
        $i = 0;
        if(is_array($value) && $value) {
            foreach($value as $k=>$v) {
                $fo['id'] = $prefix.'['.$i.']';
                $form = Form::instance($fo['id'], $fo, true);
                $form->setLimits(false);
                $input .= '<div class="item '.(($i%2)?('even'):('odd')).'">';
                foreach($form->fields as $fn=>$f) {
                    $f->setValue(($fn=='property') ?$k :$v);
                    $input .= $f->render();
                }
                $input .= '</div>';
                $i++;
            }
        }

        if (!isset($arg['template'])) {
            $arg['template'] = 'subform';
        }
        $class = '';
        if($jsinput) {
            //$jsinput = ' data-template="'.S::xml($jsinput).'" data-prefix="'.$prefix.'"';
            $jsinput = ' data-template="'.htmlspecialchars($jsinput, ENT_QUOTES, 'UTF-8', true).'" data-prefix="'.$prefix.'"';
            if($this->multiple) {
                $class .= ' multiple';
            }
            if($this->min_size) {
                $jsinput .= ' data-min="'.$this->min_size.'"';
            }
            if($this->size) {
                $jsinput .= ' data-max="'.$this->size.'"';
            }
        }
        $a=array('class'=>'subform items');
        if($this->attributes){
            $a+=$this->attributes;
            if(isset($this->attributes['class'])) {
                $a['class'].=' '.$this->attributes['class'];
            }
        }
        if($input || $jsinput) {
            $attr=$jsinput;
            foreach($a as $k=>$v) {
                $attr.=' '.$k.'="'.S::xml($v).'"';
            }
            $input = '<div'.$attr.'>'.$input.'</div>';
        }

        return $input;
    }

    public function checkObject($value, $message='')
    {
        $r = null;
        if($value && is_string($value) && $this->serialize && ($a=S::unserialize($value, $this->serialize))) {
            $value = $a;
            unset($a);
        }
        if($value && is_array($value)) {
            $r = [];
            foreach($value as $i=>$o) {
                if(is_array($o) && isset($o['property'])) {
                    $v = (isset($o['value'])) ?$o['value'] :null;
                    $r[$o['property']] = $v;
                }
            }
        }

        return $r;
    }


    public function renderForm(&$arg)
    {
        $input = '<input type="hidden" id="'.S::xml($arg['id']).'" name="'.S::xml($arg['name']).'" />';
        $jsinput = '';
        $prefix ='';
        $bind = $this->bind;
        $schema=$this->getSchema();
        if($this->choices && is_string($this->choices) && isset($schema['relations'][$this->choices]) && $schema['relations'][$this->choices]['local']==$bind) {
            $bind = $this->choices;
            $this->bind = $bind;
            $this->choices=null;
        }
        if($bind && isset($schema->relations[$bind])) {
            $M = $this->getModel();
            $cn = get_class($M);
            if(!isset($arg['value'])) $arg['value'] = $M->getRelation($bind, null, null, false);
            if(!is_array($arg['value']) && !($arg['value'] instanceof Collection)) {
                if($arg['value']) $arg['value'] = array($arg['value']);
                else $arg['value']=array();
            }
            $rc = (isset($schema->relations[$bind]['className']))?($schema->relations[$bind]['className']):($bind);
            if($arg['value'] instanceof Collection) {
                $arg['value'] = $arg['value']->getItems();
            }
            foreach($arg['value'] as $id=>$value) {
                if(!is_object($value)) {
                    $arg['value'][$id] = $rc::__set_state($value);
                } else if(is_object($value) && $value->isDeleted()) {
                    unset($arg['value'][$id]);
                }
            }
            $fk=array();
            $scope = (!$this->scope)?('subform'):($this->scope);
            if(isset($schema['relations'][$bind])) {
                $rel = $schema['relations'][$bind];
                if ($rel['type']=='one') {
                    $this->size=1;
                }
                $fkvalues=array();
                if(!is_array($rel['foreign'])) {
                    $fk[] = $rel['foreign'];
                    $fkvalues[$rel['foreign']] = $M->{$rel['local']};
                } else {
                    $fk = $rel['foreign'];
                    foreach($rel['foreign'] as $i=>$fn) {
                        $ln = $rel['local'][$i];
                        $fkvalues[$fn] = $M->{$ln};
                    }
                }
                $cn = (isset($rel['className']))?($rel['className']):($bind);
                $scope = (!$this->scope)?('subform'):($this->scope);
                $model = new $cn;
                foreach($fkvalues as $fn=>$fv) {
                    if($fv) {
                        $model->$fn=$fv;
                    } else if($fv===false || $fv=='') {
                        unset($fkvalues[$fn]);
                    }
                }

                if(!$this->min_size || !$this->size) {
                    $form = $model->getForm($scope, false, $this->form);

                    // get the template for issuing new fields with js
                    $jsinput = '<div class="item">';
                    $fid = $this->getId();
                    $prefix = $this->getName();
                    foreach($form->fields as $fn=>$f) {
                        $id = ($f->bind)?($f->bind):($f->id);
                        if(in_array($id, $fk)) {
                            $f->type = 'none';
                        }
                        $f->prefix = $prefix.'[§]';
                        $jsinput .= $f->render();
                    }
                    $jsinput .= '</div>';
                    //$jsinput = json_encode($jsinput);
                    //$jsinput = '<script type="text/javascript">/*<![CDATA[*/ var f__'.$fid.'='.$jsinput.'; /*]]>*/</script>';
                    //S::set('script', $jsinput);
                }
                unset($model);
            }
            unset($M);

            if($this->min_size && count($arg['value']) < $this->min_size) {
                while(count($arg['value']) < $this->min_size) {
                    if($bind) {
                        $arg['value'][]=new $cn($fkvalues);
                    } else {
                        $arg['value'][]=array();
                    }
                }
            }
            if($this->size > count($arg['value'])) {
                if(is_object($arg['value']) && $arg['value'] instanceof Collection) {
                    $arg['value']=$arg['value']->getItem(0, $this->size, true);
                } else {
                    $arg['value']=array_slice($arg['value'], 0, $this->size);
                }
            }

            if($arg['value']) {
                foreach ($arg['value'] as $i=>$model) {
                    $form = $model->getForm($scope, !$model->isNew(), $this->form);
                    $input .= '<div class="item '.(($i%2)?('even'):('odd')).'">';
                    foreach($form->fields as $fn=>$f) {
                        $id = ($f->bind)?($f->bind):($f->id);
                        if(in_array($id, $fk)) {
                            $f->type = 'none';
                        }
                        $f->prefix = "{$this->getName()}[{$i}]";
                        $input .= $f->render();
                    }
                    $input .= '</div>';
                    unset($model, $i);
                }
            }
        } else if($fo=$this->getSubForm()) {
            // input for javascript
            $prefix = $this->getName();
            $fo['id'] = $prefix.'[§]';
            $form = Form::instance($fo['id'], $fo, true);
            $form->setLimits(false);
            $jsinput = '<div class="item">';
            foreach($form->fields as $fn=>$f) {
                $jsinput .= $f->render();
                unset($fn, $f);
            }
            $jsinput .= '</div>';

            $value = $this->getValue();

            if(!is_array($value)) {
                $value = S::unserialize($value, $this->serialize);
            }

            // loop for each entry and add to $input
            if(is_array($value)) {
                if(!isset($value[0])) {
                    foreach($value as $i=>$o) {
                        if(!is_numeric($i)) {
                            $value = [$value];
                        }
                        unset($i, $o);
                        break;
                    }
                }
                $i=-1;
                foreach($value as $k=>$o) {
                    if(is_int($k)) $i = $k;
                    else $i++;
                    $fo['id'] = $prefix.'['.$i.']';
                    $form = Form::instance($fo['id'], $fo, true);
                    $form->setLimits(false);
                    $input .= '<div class="item '.(($i%2)?('even'):('odd')).'">';
                    foreach($form->fields as $fn=>$f) {
                        if($f->bind) {
                            $id = $f->bind;
                            if(strpos($id, '.') && substr(str_replace('.', '_', $id), 0, strlen($this->id)+1)==$this->id.'_') {
                                $id = substr($id, strlen($this->id)+1);
                            }
                        } else {
                            $id = $F->id;
                        }
                        if(isset($o[$id])) {
                            $f->setValue($o[$id]);
                        }
                        $input .= $f->render();
                        unset($fn, $f, $id);
                    }
                    $input .= '</div>';
                    unset($k, $o);
                }
            }
        }

        if (!isset($arg['template'])) {
            $arg['template'] = 'subform';
        }
        $class = '';
        if($jsinput) {
            //$jsinput = ' data-template="'.S::xml($jsinput).'" data-prefix="'.$prefix.'"';
            $jsinput = ' data-template="'.htmlspecialchars($jsinput, ENT_QUOTES, 'UTF-8', true).'" data-prefix="'.$prefix.'"';
            if($this->multiple) {
                $class .= ' multiple';
            }
            if($this->min_size) {
                $jsinput .= ' data-min="'.$this->min_size.'"';
            }
            if($this->size) {
                $jsinput .= ' data-max="'.$this->size.'"';
            }
        }
        $a=array('class'=>'subform items');
        if($this->attributes){
            $a+=$this->attributes;
            if(isset($this->attributes['class'])) {
                $a['class'].=' '.S::xml($this->attributes['class']);
            }
        }
        if($input || $jsinput) {
            $attr=$jsinput;
            foreach($a as $k=>$v) {
                $attr.=' '.$k.'="'.S::xml($v).'"';
            }
            $input = '<div'.$attr.'>'.$input.'</div>';
        }
        //$input = ($input || $jsinput)?('<div class="subform items"'.$jsinput.'>'.$input.'</div>'):('');
        return $input;
    }

    public function getSubForm($scope=null)
    {
        if(!$scope && $this->scope) {
            $scope = $this->scope;
        }
        $M = $this->getModel();
        if(is_string($scope) && !isset($M::$schema['scope'][$scope])) return null;
        $columns = (is_array($scope) && $scope && is_array(array_values($scope)[0])) ?$scope :$M::columns($scope);
        foreach($columns as $fn=>$fd) {
            unset($columns[$fn]);
            if(!is_array($fd)) {
                $fd = $M::column($fd);
                if(!$fd || !is_array($fd)) {
                    continue;
                }
            }
            if(is_int($fn)) {
                if(isset($fd['label'])) {
                    $fn = $fd['label'];
                } else if(isset($fd['id'])) {
                    $fn = $M::fieldLabel($fd['id']);
                } else {
                    continue;
                }
            }
            if(!isset($fd['bind'])) {
                if(isset($fd['id'])) {
                    $fd['bind'] = $fd['id'];
                } else {
                    $fd['bind'] = $fn;
                }
            } else {
                if(!isset($fd['label'])) {
                    $fd['label'] = $fn;
                }
                $fn = $fd['bind'];
            }
            if(!isset($fd['label'])) {
                $fd['label'] = $M::fieldLabel($fd['bind']);
            }


            $columns[$fn] = $fd;
        }
        if(!$columns) return null;
        $fo = array(
            'fields'=>$columns,
            'prefix'=> (isset($this->prefix)) ?$this->prefix.$this->id :$this->id,
            'model'=>$M,
        );
        return $fo;

    }

    public function renderEmail(&$arg)
    {
        $arg['type']=self::$emailInputType;
        $arg['data-type']='email';
        return $this->renderText($arg);
    }

    public function renderUrl(&$arg)
    {
        $arg['type']=self::$urlInputType;
        $arg['data-type']='url';
        return $this->renderText($arg);
    }

    public function renderDns(&$arg)
    {
        $arg['type']='text';
        $arg['data-type']='dns';
        return $this->renderText($arg);
    }

    public function renderSearch(&$arg)
    {
        $arg['type']=self::$searchInputType;
        $arg['data-type']='search';
        return $this->renderText($arg);
    }

    public function renderFile(&$arg)
    {
        $arg['type']='file';
        if($F=$this->getForm()) {
            while($P=$F->getParentForm()) {
                $F = $P;
            }
            $F->attributes['enctype']='multipart/form-data';
        }
        if($this->multiple) {
            $this->attributes['multiple']=true;
            //$arg['name'].='[]';
        }
        $s='';
        if($this->accept && is_array($this->accept)) {
            if(isset($this->accept['uploader'])) {
                $this->attributes['data-uploader'] = (is_bool($this->accept['uploader']))?(S::requestUri()):($this->accept['uploader']);
            }
            if(isset($this->accept['type'])) {
                $type = $this->accept['type'];
                if($type && !is_array($type)) {
                    $type = preg_split('/[\s\,\;]+/', $type, -1, PREG_SPLIT_NO_EMPTY);
                }
                if($type && isset($type[0])) {
                    $aa = array();
                    foreach($type as $ts) {
                        if(substr($ts, -1)=='*' || strpos($ts, '/')) {
                            $aa[] = $ts;
                        } else if(substr($ts, -1)=='/') {
                            $aa[]= $ts.'*';
                        } else {
                            $aa[]='.'.$ts;
                        }
                    }
                    $this->attributes['accept'] = implode(',',$aa);
                }
                unset($type);
            }
            if(isset($this->accept['size']) && is_numeric($this->accept['size'])) {
                $this->attributes['data-size'] = $this->accept['size'];
            }
        }

        $hi = true;
        $a0 = $this->attributes;
        $this->attributes = [];
        if(strpos($arg['class'], 'app-file-preview')!==false) {
            if($arg['value']) {
                $s .= '<span class="text s-f-file'.($this->multiple ?' s-multiple' :'').'">'.$this->filePreview($arg['name']).'</span>';
                $hi = false;
            } else {
                $s .= '<span class="text"></span>';
            }
        }
        if(strpos($arg['class'], 'app-image-preview')!==false) {
            if($arg['value']) {
                $s .= '<span class="text s-f-file">'
                    . (($arg['value'])?($this->filePreview($arg['name'], true)):(''))
                    . '</span>';
                $hi = false;
            } else {
                $s .= '<span class="text"></span>';
            }
        }
        if(isset($arg['required'])) unset($arg['required']);
        $ha = $arg;
        $h = ($hi) ?$this->renderHidden($ha) :'';
        $this->attributes = $a0;
        unset($a0);
        $a = [];
        foreach($arg as $k=>$v) {
            if($k=='template' || $k=='required') continue;
            $a[$k] = $v;
            unset($k, $v);            
        }
        $a['value']='';
        $a['id'] .= static::$uploadSuffix;
        $s .= $h.$this->renderText($a, false, false);

        if(isset($this->suffix) && static::$uploadSuffix && substr($this->suffix, -1*strlen(static::$uploadSuffix)) === static::$uploadSuffix) {
            $this->suffix = substr($this->suffix, 0, strlen($this->suffix) - strlen(static::$uploadSuffix));
        }
        return $s;
    }

    public static function uploadedFile($s, $array=false)
    {
        $uploadDir = S::uploadDir();
        $fpart = explode('|', $s);
        $fname = array_pop($fpart);
        if(count($fpart)>=1 && ($ftmp=preg_replace('/[^a-zA-Z0-9\-\_\.\/]+/', '', $fpart[0])) && (file_exists($f=$uploadDir.'/'.$ftmp) || (file_exists($f=self::$tmpDir.'/'.$ftmp)))) {
            if($array) return array('name'=>$fname, 'file'=>$f);
            return $f;
        } else if(file_exists($f=$uploadDir.'/'.$fname)) {
            if($array) return array('name'=>$fname, 'file'=>$f);
            return $f;
        }
    }

    public function filePreview($name='', $img = false)
    {
        static $b='<span class="s-auto-remove s-file">', $a='</span>';
        $prefix = preg_replace('/_+$/', '', preg_replace('/[\[\]]+/', '_', $name));
        $s='';
        if($this->value){
            $files = explode(',', $this->value);
            $url = (App::request('query-string'))?(S::requestUri().'&'):(S::scriptName(true).'?');
            $uploadDir = S::uploadDir();
            foreach($files as $i=>$fdesc) {
                $fpart = explode('|', $fdesc);
                $fname = array_pop($fpart);
                $arg = ['id'=>$prefix, 'name'=>$name, 'value'=>$fdesc];
                $h = $this->renderHidden($arg);
                if(count($fpart)>=1 && file_exists($uploadDir.'/'.$fpart[0])) {
                    $hash = $prefix.md5($fpart[0]);
                    $link = $url.$hash.'='.urlencode($fname);
                    if(App::request('get', $hash)==$fname) {
                        S::download($uploadDir.'/'.$fpart[0], null, $fname, 0, true);
                    }
                    if ($img) {
                        $s .= $b.'<a href="'.S::xml($link).'" download="'.$fname.'"><img src="'.S::xml($link).'" title="'.S::xml($fname).'" alt="'.S::xml($fname).'" />'.$h.'</a>'.$a;
                    } else {
                        $s .= $b.'<a href="'.S::xml($link).'">'.S::xml($fname).$h.'</a>'.$a;
                    }
                } elseif (file_exists($f=$uploadDir.'/'.$fname) || ($this->bind && method_exists($M=$this->getModel(), $m='get'.S::camelize($this->bind, true).'File') && file_exists($f=$M->$m()))) { //Compatibilidade com dados de framework anteriores
                    $hash = $prefix.md5($fname);
                    $link = $url.$hash.'='.urlencode($fname);
                    if(App::request('get', $hash)==$fname) {
                        S::download($f, null, $fname, 0, true);
                    }
                    if ($img) {
                        $s .= $b.'<a href="'.S::xml($link).'" download="'.$fname.'"><img src="'.S::xml($link).'" title="'.S::xml($fname).'" alt="'.S::xml($fname).'" />'.$h.'</a>'.$a;
                    } else {
                        $s .= $b.'<a href="'.S::xml($link).'" download="'.$fname.'">'.S::xml($fname).$h.'</a>'.$a;
                    }
                } else {
                    $s .= $b.'<a>'.S::xml($fname).$h.'</a>'.$a;
                }
            }
        }
        return $s;
    }

    public function renderNumber(&$arg)
    {
        $arg['type']=self::$numberInputType;
        $arg['data-type']='number';
        return $this->renderText($arg);
    }

    public function renderTel(&$arg)
    {
        $arg['type']='tel';
        return $this->renderText($arg);
    }

    public function renderRange(&$arg)
    {
        $arg['type']=self::$rangeInputType;
        $arg['data-type']='range';
        return $this->renderText($arg);
    }

    public function renderPassword(&$arg)
    {
        $arg['type']='password';
        $arg['data-type']='password';
        $arg['value']='';
        return $this->renderText($arg);
    }

    public function renderDate(&$arg)
    {
        $arg['type']=self::$dateInputType;
        $arg['data-type']='date';
        if(isset($arg['value']) && $arg['value']) {
            if(is_array($arg['value'])) {
                $arg['value']=$this->parseValue($arg['value']);
            }
            $t = S::strtotime($arg['value']);
            if(!self::$dateInputFormat) {
                self::$dateInputFormat = S::$dateFormat;
            }
            $arg['value'] = date(self::$dateInputFormat, $t);
        }
        /*
        if(Form::$enableStyles) {
            S::$variables['style'][Form::$enableStyles]=S::$assetsUrl.'/tecnodesign/css/datepicker.less';
        }
        */
        return $this->renderText($arg);
    }

    public function renderDateSelect(&$arg)
    {
        $a = array('id'=>$arg['id'], 'name'=>$arg['name']);
        if(isset($this->placeholder)) {
            $a['placeholder'] = $this->placeholder;
        }
        $bv = array('required', 'readonly', 'disabled');
        foreach ($bv as $attr) {
            $value = $this->$attr;
            if ($value) {
                $a[$attr] = true;
            }
        }
        $a += $this->attributes;
        $values = array();
        if(isset($arg['value']) && $arg['value']) {
            if(is_array($arg['value'])) {
                $arg['value']=$this->parseValue($arg['value']);
            } else if(preg_match('/^([0-9]{4})\-([0-9]{1,2})\-([0-9]{1,2})((\s|T)([0-9]{1,2})(\:[0-9]{1,2})(\:[0-9]{1,2}))?/', $arg['value'], $m)) {
                $values['y']=$m[1];
                $values['m']=str_pad($m[2], 2, '0', STR_PAD_LEFT);
                $values['d']=str_pad($m[3], 2, '0', STR_PAD_LEFT);
                if(isset($m[6])){
                    $values['h']=str_pad($m[6], 2, '0', STR_PAD_LEFT);
                    if(isset($m[7])){
                        $values['i']=str_pad(substr($m[7],1), 2, '0', STR_PAD_LEFT);
                    }
                    if(isset($m[8])){
                        $values['s']=str_pad(substr($m[8],1), 2, '0', STR_PAD_LEFT);
                    }
                }
            } else {
                $t = S::strtotime($arg['value']);
                list($values['y'], $values['m'], $values['d'])=explode(',',date('Y,m,d', $t));
            }
        }
        $values+=array(
            'y'=>'',
            'm'=>'',
            'd'=>'',
        );
        if(!$this->range || !is_array($this->range)){
            $this->range = array();
        }
        if(!isset($this->range[0]) || is_bool($this->range[0])) {
            $this->range[0]=0;
        } else if(!is_int($this->range[0])) {
            $this->range[0] = S::strtotime($this->range[0]);
        }
        if(!isset($this->range[1]) || is_bool($this->range[1])) {
            $this->range[1]=time();
        } else if(!is_int($this->range[1])) {
            $this->range[1] = S::strtotime($this->range[1]);
        }
        if($this->range[0]>$this->range[1]) {
            $this->range = array($this->range[1], $this->range[0]);
        }
        $rd = $this->range[1] - $this->range[0];
        if(!self::$dateInputFormat) {
            self::$dateInputFormat = S::$dateFormat;
        }
        $df = self::$dateInputFormat;

        $yf = (strpos($df, 'Y'))?('Y'):('y');
        $input=array();
        $input[$yf] = '<span class="input date-year"><select ';
        $ay=$a;
        $ay['id'].='_0';
        $ay['name'].='[0]';
        foreach ($ay as $attr=>$value) {
            if (is_bool($value)) {
                $value = var_export($value, true);
            }
            $input[$yf] .= $attr . '="' . S::xml($value) . '" ';
        }
        $input[$yf].='><option value="" class="placeholder">'.S::t('Year', 'form').'</option>';
        if($this->range[1] > time()) {
            for($y=date('Y', $this->range[0]);$y<=date('Y', $this->range[1]);$y++) {
                $input[$yf] .= '<option value="'.$y.'"'.(((int)$values['y']==$y)?(' selected="selected"'):('')).'>'.(($yf=='Y')?($y):(substr($y, -2))).'</option>';
            }
        } else {
            for($y=date('Y', $this->range[1]);$y>=date('Y', $this->range[0]);$y--) {
                $input[$yf] .= '<option value="'.$y.'"'.(((int)$values['y']==$y)?(' selected="selected"'):('')).'>'.(($yf=='Y')?($y):(substr($y, -2))).'</option>';
            }
        }
        $input[$yf].='</select></span>';

        $mfs=array('F'=>true, 'm'=>false, 'M'=>true, 'n'=>false );
        $mf='m';
        $mt=false;
        foreach($mfs as $f=>$t){
            if(strpos($df, $f)) {
                $mf=$f;$mt=$t;break;
            }
        }
        $input[$mf] = '<span class="input date-month"><select ';
        $ay=$a;
        $ay['id'].='_1';
        $ay['name'].='[1]';
        foreach ($ay as $attr=>$value) {
            if (is_bool($value)) {
                $value = var_export($value, true);
            }
            $input[$mf] .= $attr . '="' . S::xml($value) . '" ';
        }
        $input[$mf].='><option value="" class="placeholder">'.S::t('Month', 'form').'</option>';
        $ms=array_fill(1,12,true);
        if($rd < 86400*365) {
            /**
             * @TODO: filter when there's less than a year
             */
        }
        foreach($ms as $m=>$use) {
            if($use){
                $mk=str_pad($m, 2, '0', STR_PAD_LEFT);
                if($mf=='m') {
                    $mv = $mk;
                } else if($mf=='n') {
                    $mv = $m;
                } else {
                    $t=mktime(0, 0, 0, $m, 1, 2011);
                    $mv=date($mf, $t);
                    if($mt) {
                        $mv = S::t($mv, 'form');
                    }
                }
                $input[$mf] .= '<option value="'.$mk.'"'.(((int)$values['m']==$m)?(' selected="selected"'):('')).'>'.$mv.'</option>';
            }
        }
        $input[$mf].='</select></span>';

        if (strpos($df, 'j')!==false) {
            $f = 'j';
        } else {
            $f = 'd';
        }
        $input[$f] = '<span class="input date-day"><select ';
        $ay=$a;
        $ay['id'].='_2';
        $ay['name'].='[2]';
        foreach ($ay as $attr=>$value) {
            if (is_bool($value)) {
                $value = var_export($value, true);
            }
            $input[$f] .= $attr . '="' . S::xml($value) . '" ';
        }
        $input[$f].='><option value="" class="placeholder">'.S::t('Day', 'form').'</option>';
        $ds=array_fill(1,31,true);
        if($rd < 86400*31) {
            /**
             * @TODO: filter when there's less than a month
             */
        }
        foreach($ds as $d=>$use) {
            if($use){
                $dk=str_pad($d, 2, '0', STR_PAD_LEFT);
                if($f=='d') {
                    $dv = $dk;
                } else {
                    $dv = $d;
                }
                $input[$f] .= '<option value="'.$dk.'"'.(((int)$values['d']==$d)?(' selected="selected"'):('')).'>'.$dv.'</option>';
            }
        }
        $input[$f].='</select></span>';

        // add time/seconds
        $uk = array();
        foreach($input as $k=>$v) {
            $uk[]='---'.$k.'---';
        }
        $df = str_replace(array_keys($input), $uk, $df);
        $input = str_replace($uk, array_values($input), $df);
        return $input;
    }

    public function renderDatetime(&$arg)
    {
        $arg['type']=self::$datetimeInputType;
        $arg['data-type']='datetime';
        if(isset($arg['value']) && $arg['value'] && ($t = S::strtotime($arg['value']))) {
            if(self::$datetimeInputType=='text') {
                if(!self::$datetimeInputFormat) {
                    self::$datetimeInputFormat = S::$dateFormat.' '.S::$timeFormat;
                }
                $arg['value'] = date(self::$datetimeInputFormat, $t);
            } else {
                $arg['value'] = date('Y-m-d\TH:i:s', $t);
            }
        }
        /*
        if(Form::$enableStyles) {
            S::$variables['style'][Form::$enableStyles]=S::$assetsUrl.'/tecnodesign/css/datepicker.less';
        }
        */
        return $this->renderText($arg);
    }

    public function renderCaptcha(&$arg)
    {
        $arg['type']='text';
        $text = null;
        $img = Image::captcha($text, ['no_session'=>true, 'use_database'=>false, 'send_headers'=>false, 'no_exit'=>true]);
        $input = null;
        $arg['value'] = $this->value = '';

        if($text && $img) {
            $salt = S::salt(40, true);
            $key = 'captcha/'.$salt;
            $timeout = 60;
            Cache::set($key, $text, static::$captchaTimeout);
            $arg['name'] .= '['.$salt.']';
            $input = '<img src="'.$img.'" />';
        }

        $input .= $this->renderText($arg);
        return $input;
    }

    public function checkCaptcha($value, $message='')
    {
        if(is_array($value) && ($post=$value) || ($post=App::request('post', $this->id))) {
            $exist = false;
            foreach($post as $k=>$v) {
                if($msg=Cache::get('captcha/'.$k)) {
                    Cache::delete('captcha/'.$k);
                    $exist = true;
                    if(static::$captchaCaseSensitive) {
                        if($msg===$v) {
                            return true;
                        }
                    } else {
                        if(strtolower($msg)===strtolower($v)) {
                            return true;
                        }
                    }
                }
            }
            if($exist) {
                $error = 'The supplied code is invalid.';
            } else {
                $error = 'The supplied code is expired or doesn\'t exist.';
            }
        } else {
            $error = 'You must supply a valid code.';
        }

        if($message===static::$defaultErrorMessage) $m = '';
        else $m = S::t($message, 'exception');
        $m .= ' '.S::t($error, 'exception');

        throw new AppException(array($m, $this->getLabel(), $value));
    }


    public function renderColor(&$arg)
    {
        $arg['type']='color';

        return $this->renderText($arg);
    }

    public function renderPhone(&$arg)
    {
        $arg['type']=self::$phoneInputType;
        $arg['data-type']='phone';
        return $this->renderText($arg);
    }

    public function renderString(&$arg, $enableChoices=true)
    {
        return $this->renderText($arg, $enableChoices);
    }

    public function renderList(&$arg, $enableChoices=true)
    {
        return $this->renderArray($arg, $enableChoices);
    }

    public function renderArray(&$arg, $enableChoices=true)
    {
        $this->multiple = true;
        return $this->renderText($arg, $enableChoices);
    }

    public function renderText(&$arg, $enableChoices=true, $enableMultiple=null)
    {
        if($this->multiple && ($enableMultiple || (is_null($enableMultiple) && static::$enableMultipleText))) {
            $v0 = $value = $arg['value'];
            if(!is_array($value) && $value) {
                if($this->serialize && ($nv=S::unserialize($value))) {
                    $value = $nv;
                    unset($nv);
                } else {
                    $value = preg_split('/\s*\,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
                }
            } else if(S::isempty($value)) {
                $value = array();
            }
            $s = '';
            if($this->min_size && $this->min_size > count($value)) {
                $value = array_fill(count($value) -1, $this->min_size - count($value), '');
            }
            foreach($value as $i=>$o) {
                $arg['value'] = $o;
                $s .= '<div class="item">'.$this->renderText($arg, $enableChoices, false).'</div>';
                unset($value[$i], $i, $o);
            }

            /*
            if(!$s) {
                $arg['value'] = '';
                $s .= '<div class="item">'.$this->renderText($arg, $enableChoices, false).'</div>';
            }
            */

            $arg['value']='';
            $jsinput = '<div class="item">'.$this->renderText($arg, $enableChoices, false).'</div>';

            $s = '<div class="input items" data-template="'.htmlspecialchars($jsinput, ENT_QUOTES, 'UTF-8', true).'" data-prefix=""'
                . (($this->min_size)?(' data-min="'.$this->min_size.'"'):(''))
                . (($this->size)?(' data-max="'.$this->size.'"'):(''))
                . '>'
                . $s
                . '</div>'
                ;

            $arg['value'] = $v0;
            return $s;
        }
        $a = [
            'type'=>(isset($arg['type']))?($arg['type']):('text'),
            'id'=>$arg['id'],
            'name'=>$arg['name'],
            'value'=>(string)$arg['value'],
        ];
        if(S::isempty($a['name'])) unset($a['name']);
        if($this->size && !isset($this->attributes['maxlength']) && !$this->choices) {
            $this->attributes['maxlength']=$this->size;
        }
        foreach($arg as $an=>$av) {
            if(substr($an, 0, 5)=='data-') $a[$an]=$av;
        }
        if(isset($this->placeholder)) {
            $a['placeholder'] = $this->placeholder;
        }
        $bv = array('required', 'readonly', 'disabled');
        foreach ($bv as $attr) {
            $value = $this->$attr;
            if ($value) {
                $a[$attr] = true;
            }
        }
        $a += $this->attributes;
        $input = '<input ';
        foreach ($a as $attr=>$value) {
            if (is_bool($value)) {
                $value = var_export($value, true);
            }
            $input .= $attr . '="' . S::xml($value) . '" ';
        }
        $dl = '';
        if($enableChoices && !is_null($this->choices)) {
            foreach ($this->getChoices() as $k=>$v) {
                $label = false;
                if(is_object($v) && $v instanceof Model) {
                    $label = (string)$v;
                } else if (is_array($v) || is_object($v)) {
                    $firstv=false;
                    foreach ($v as $vk=>$vv) {
                        if(!$firstv) {
                            $firstv = $vv;
                        }
                        if($vk=='label') {
                            $label = $vv;
                        }
                    }
                    if(!$label && $firstv) {
                        $label = $firstv;
                    }
                    unset($firstv);
                } else {
                    $label = $v;
                }
                $dl .= '<option value="'.S::xml($label).'" />';
                unset($label, $k, $v);
            }
            if ($dl) {
                $input .= 'list="l__'.$arg['id'].'" ';
                $dl = '<datalist id="l__'.$arg['id'].'">'.$dl.'</datalist>';
            }
        }
        $input .= '/>'.$dl;
        return $input;
    }

    public function renderHtml(&$arg)
    {
        $this->attributes['data-format']='html';
        return $this->renderTextarea($arg);
    }

    public function renderTextarea(&$arg)
    {
        $a = array('id'=>$arg['id'], 'name'=>$arg['name']);
        if(isset($this->placeholder)) {
            $a['placeholder'] = $this->placeholder;
        }
        if($this->size && !isset($this->attributes['maxlength'])) {
            $this->attributes['maxlength']=$this->size;
        }
        $bv = array('required', 'readonly', 'disabled');
        foreach ($bv as $attr) {
            $value = $this->$attr;
            if ($value) {
                $a[$attr] = true;
            }
        }
        $a += $this->attributes;
        $input = '<textarea ';
        foreach ($a as $attr=>$value) {
            if (is_bool($value)) {
                $value = var_export($value, true);
            }
            $input .= $attr . '="' . S::xml($value) . '" ';
        }
        $input .= '>'.S::xml($arg['value']).'</textarea>';

        return $input;
    }

    public function renderSubmit(&$arg)
    {
        $arg['type'] = 'submit';
        return $this->renderButton($arg);
    }

    public function renderButton(&$arg)
    {
        static $attr = ['id', 'class' ];
        if(!isset($arg['type']) || !$arg['type']) $arg['type'] = 'button';
        $a = ['type'=>$arg['type']];

        foreach($attr as $n) {
            if(isset($arg[$n]) && $arg[$n]) $a[$n] = $arg[$n];
        }
        $a += $this->attributes;
        $input = '<button ';
        foreach ($a as $n=>$v) {
            if (is_bool($v)) {
                $v = var_export($v, true);
            }
            $input .= $n . '="' . S::xml($v) . '" ';
        }
        $input .= '>'.((!$this->html_labels) ?S::xml($arg['value']) :$arg['value']).'</button>';
        if (!isset($arg['template'])) {
            $arg['template'] = 'input';
        }
        return $input;
    }


    public function renderNone()
    {
    }

    public function renderHiddenText(&$arg)
    {
        if (!isset($arg['template'])) {
            $arg['template'] = 'field';
        }
        $input = $this->renderHidden($arg);
        if(isset($this->placeholder)) $input .= $this->placeholder;
        return $input;
    }

    public function renderHidden(&$arg)
    {
        $a = array('type'=>'hidden', 'id'=>$arg['id'], 'name'=>$arg['name'], 'value'=>$arg['value']);
        $bv = array('required', 'readonly', 'disabled');
        foreach ($bv as $attr) {
            $value = $this->$attr;
            if ($value) {
                $a[$attr] = true;
            }
        }
        $a += $this->attributes;
        $input = '<input ';
        foreach ($a as $attr=>$value) {
            if ($attr!='value' && is_bool($value)) {
                $value = var_export($value, true);
            } else if(is_array($value) && isset($value['value'])) {
                $value = $value['value'];
            }
            $input .= $attr . '="' . S::xml($value) . '" ';
        }
        $input .= '/>';
        if (!isset($arg['template'])) {
            $arg['template'] = 'hidden';
        }
        return $input;
    }

    public function renderRadio(&$arg)
    {
        $this->multiple=false;
        return $this->renderCheckbox($arg, 'radio');
    }

    public function renderBool(&$arg)
    {
        return $this->renderCheckbox($arg, 'checkbox');
    }

    public function renderCheckbox(&$arg, $type = 'checkbox')
    {
        //$a = array('id'=>$arg['id']);
        $attributeList = array('type' => $type, 'name' => $arg['name']);
        $bv = ['readonly', 'disabled'];
        foreach ($bv as $attribute) {
            $value = $this->$attribute;
            if ($value) {
                $attributeList[$attribute] = true;
            }
        }
        $attributeList += $this->attributes;

        $input = '<input';
        foreach ($attributeList as $attribute => $value) {
            if (is_bool($value)) {
                $value = var_export($value, true);
            }
            $input .= ' ' . $attribute . '="' . S::xml($value) . '"';
        }
        //$input .= '>';

        if (!$this->getChoices()) {
            // render as a bool
            if (isset($this->placeholder)) {
                $this->choices = array(1 => $this->placeholder);
            } else {
                $this->choices = array(1 => '');
            }
        }

        $options = array();
        /*
         * get something to reset buttons
        if (!$this->required || !$this->value) {
            $blank = ($this->placeholder)?($this->placeholder):(self::$labels['blank']);
            $options[] = '<option class="placeholder" value="">'.$blank.'</option>';
        }
         */
        /**
         *  Modificação executada dia 30/10 pois o getOriginal sempre
         *  mantinha o valor original do model ao invés de setar o novo
         *  valor.
         * if($this->bind) {
            $ovalue = $this->getModel()->getOriginal($this->bind);
            if(is_null($ovalue) && $this->default) $ovalue = $this->default;
        } else {
            $ovalue = $this->value;
        }*/
        $oValue = $arg['value'];

        if (!is_array($oValue)) {
            if($this->serialize) {
                $unserialized=S::unserialize($oValue, $this->serialize);
                if(S::isempty($unserialized) && var_export($unserialized, true)!=$oValue) {
                    $oValue = preg_split('/\s*\,\s*/', $oValue);
                } else {
                    $oValue = $unserialized;
                }
            } else {
                $oValue = preg_split('/\s*\,\s*/', (string) $oValue);
            }
            if (!is_array($oValue)) {
                $oValue = array($oValue);
            }
        } else {
            $opk = null;
            foreach ($oValue as $i => $o) {
                if (is_object($o) && ($o instanceof Model)) {
                    $valueConfig = $o->getPk(true);
                    $oValue[$i] = array_pop($valueConfig);
                    unset($i, $o, $valueConfig);
                } else {
                    break;
                }
            }
        }

        $choices = $this->getChoices();
        if (count($choices) === 1 && !implode('', $choices)) {
            $choices = array();
        }
        $i=0;
        foreach ($choices as $key => $valueConfig) {
            $value = $key;
            $label = false;
            $group = false;
            $attrs = '';
            $styleClasses = '';
            if (is_object($valueConfig) && ($valueConfig instanceof Model)) {
                $value = $valueConfig->pk;
                $label = (string)$valueConfig;
                $group = isset($valueConfig->_group) ? $valueConfig->_group : $valueConfig->group;
            } elseif (is_array($valueConfig) || is_object($valueConfig)) {
                $firstValue = false;
                foreach ($valueConfig as $configKey => $configValue) {
                    if (!$firstValue) {
                        $firstValue = $configValue;
                    }
                    if ($configKey === 'value') {
                        $value = $configValue;
                    } elseif ($configKey === 'label') {
                        $label = $configValue;
                    } elseif ($configKey === 'group') {
                        $group = $configValue;
                    } elseif ($configKey === 'class') {
                        $styleClasses = ' ' . $configValue;
                    } elseif ($configKey === 'disabled' || $configKey === 'readonly') {
                        if ($configValue) {
                            $attrs .= ' disabled="disabled"';
                        }
                    } elseif (!is_int($configKey)) {
                        $attrs .= ' data-' . $configKey . '="' . S::xml($configValue) . '"';
                    }
                }
                if (!$label && $firstValue) {
                    $label = $firstValue;
                }
            } else {
                $label = $valueConfig;
            }
            if($value===0) $value = "0";
            if (in_array($value, $oValue, false)) {
                $attrs .= ' checked="checked"';
                $styleClasses = ' on';
            }

            if ($label) {
                if (!$this->html_labels) {
                    $label = S::xml($label);
                }
                $id = $arg['id'] . '-' . ($i++);
                $dl = "<label for=\"$id\"><span class=\"$type $styleClasses\">"
                    . "$input id=\"$id\" value=\"" . S::xml($value) . "\" $attrs />"
                    . '</span>' . $label . '</label>';
            } else {
                $id = $arg['id'];
                $dl = "<span class=\"$type $styleClasses\">"
                    . "$input id=\"$id\" value=\"" . S::xml($value) . "\" $attrs />"
                    . '</span>';
            }
            if ($group) {
                $options[$group][] = $dl;
            } else {
                $options[] = $dl;
            }
            unset($choices[$key], $valueConfig);
        }// endforeach ($choices as $key => $valueConfig)

        if(!isset($key)) {
            $attrs = '';
            $styleClasses = '';
            $value = ($arg['value'])?($arg['value']):('1');
            if($this->value) {
                $attrs .= ' checked="checked"';
                $styleClasses = ' on';
            }
            $id = $arg['id'];
            if(is_array($value)) $value = implode(',', $value);
            $options[] = "<span class=\"{$type}{$styleClasses}\">{$input} id=\"{$id}\" value=\"" . S::xml($value) . '"' . $attrs . ' /></span>';
        }
        $dl = '';
        foreach ($options as $key=>$valueConfig) {
            if (is_array($valueConfig)) {
                $dl .= '<div class="'.S::slug($key).'" label="'.S::xml($key).'"><span class="label">'.((!$this->html_labels) ?S::xml($key) :$key).'</span>'.implode('', $valueConfig).'</div>';
            } else {
                $dl .= $valueConfig;
            }
        }
        return $dl;
    }

    private function ajaxChoices($s)
    {
        $w = null;
        if(!$this->choices && isset($this->query) && ($cn=$this->query->model)) {
            $scope = $cn::columns((!isset($this->scope)) ?'choices':$this->scope, (is_numeric($s)) ?null:['string']);
            if($s) {
                $w = [];
                foreach($scope as $fn) {
                    if(is_array($fn) && isset($fn['bind'])) $fn=$fn['bind'];
                    if(strpos($fn, ' ')) $fn = preg_replace('/\s+(as\s+)?[a-z0-9\_]+$/i', '', $fn);
                    if(preg_match('/\[([^\]]+)\]/', $fn, $m)) $fn = $m[1];
                    $w["|{$fn}%="]=$s;
                }
                $o = $this->getChoices(['where'=>$w]);
            } else {
                $o = $this->getChoices();
            }
            if(!$o && isset($this->_choicesCollection)) {
                $o = $this->_choicesCollection->getItems(0, static::$maxOptions);
            }
            $ro = [];
            if($o) {
                foreach($o as $k=>$v) {
                    if(is_object($v) && $v instanceof Model) {
                        if(isset($v::$schema->scope['choices']['value']) && isset($v::$schema->scope['choices']['label'])) {
                            $ro[]=$v->asArray('choices');
                        } else {
                            $value=$v->pk;
                            $group = $v->group;
                            $label = $v->label;
                            if(!$label) {
                                $label = (string) $v;
                            }
                            if($group) {
                                $ro[] = [ 'value'=>$value, 'label'=>$label, 'group'=>$group ];
                            } else {
                                $ro[] = [ 'value'=>$value, 'label'=>$label ];
                            }
                        }
                    } else if(!is_string($v)) {
                        if(is_array($v) && isset($v['label'])) {
                            $v = $v['label'];
                        }
                        $ro[] = [ 'value'=>$v, 'label'=>$k ];
                    } else {
                        break;
                    }
                    unset($k, $v);
                }
            }
            unset($o);
        } else {
            $ro = $this->getChoices();
            if($s) {
                $term = S::slug($s);
                foreach($ro as $k=>$v) {
                    $value = $v;
                    if(is_array($v)) {
                        $value = $v['label'];
                    }
                    $slug = S::slug((string)$value);
                    if(strpos($slug, $term)===false) {
                        unset($ro[$k]);
                    }
                    if(!is_string($v)) {
                        $ro[$k]=(string)$value;
                    }
                }
            }
        }
        return $ro;
    }

    public function renderSelect(&$arg)
    {
        $a = array('id'=>$arg['id'], 'name'=>$arg['name']);
        $bv = array('required', 'readonly', 'disabled', 'multiple');
        foreach ($bv as $attr) {
            $value = $this->$attr;
            if ($value) {
                $a[$attr] = true;
            }
        }
        if($this->multiple) {
            $this->attributes['class']=(isset($this->attributes['class']))?($this->attributes['class'].' multiple'):('multiple');
            if(substr($a['name'], -2)!='[]') $a['name'] .= '[]';
        }
        $a += $this->attributes;
        if(App::request('headers', 'x-studio-action')=='choices') {
            $m=false;
            $tg = urldecode(App::request('headers', 'x-studio-target'));
            if(strpos($arg['id'], '§')!==false) {
                $p = '/^'.str_replace('§', '[0-9]+', $arg['id']).'$/';
                $m = preg_match($p, $tg);
                unset($p);
            } else if($tg==$arg['id']) {
                $m = true;
            }
            if($m) {
                unset($m, $tg);
                S::cacheControl('no-cache',0);
                S::output($this->ajaxChoices(urldecode((string)App::request('headers', 'x-studio-term'))), 'json');
            }
            unset($m, $tg);
        }
        if(isset($this->attributes['data-datalist-api']) || $this->countChoices() > self::$maxOptions) {
            if(isset($this->attributes['data-datalist-api']) && $this->attributes['data-datalist-api']) {
                if(substr($this->attributes['data-datalist-api'],0,1)!='/' && substr($this->attributes['data-datalist-api'],0,4)!='http') {
                    $this->attributes['data-datalist-api'] = S::scriptName(true).'/'.$this->attributes['data-datalist-api'];
                }
                if(isset($this->prefix) && $this->prefix) {
                    $p = static::id($this->prefix).'_';
                    $this->attributes['data-datalist-api'] = str_replace('$', '$'.$p, $this->attributes['data-datalist-api']);
                    $this->attributes['data-prefix'] = $p;
                    unset($p);
                }

            }
            $ia = $arg;
            $ha=$arg;
            $ia['type']='search';
            $ia['id']='q__'.$ia['id'];
            $ia['name']='q__'.$ia['name'];
            $oa = $this->attributes;
            foreach($oa as $k=>$v) {
                if(substr($k, 0, 13)=='data-datalist') unset($oa[$k]);
                unset($k, $v);
            }
            //if(isset($this->attributes['data-callback'])) unset($this->attributes['data-callback']);
            if(!isset($this->attributes['data-datalist'])) $this->attributes['data-datalist']='self';
            $input = '';
            if($arg['value']) {
                if($this->multiple) {
                    $values = $arg['value'];
                    $ha['value']=array();
                    if(!is_array($arg['value']) && !is_object($arg['value'])) {
                        $arg['value']=preg_split('/\s*\,\s*/', $arg['value'], -1, PREG_SPLIT_NO_EMPTY);
                    }
                    foreach($arg['value'] as $v) {
                        if(is_object($v) && $v instanceof Model) {
                            $value=$v->pk;
                            $group = $v->group;
                            $label = $v->label;
                            if(!$label) {
                                $label = (string) $v;
                            }
                            $label = ($group)?('<strong>'.S::xml($group).'</strong> '.S::xml($label)):(S::xml($label));
                        } else {
                            $value = $v;
                            $label = S::xml($this->getChoices($v));
                        }
                        $ha['value'][]=$value;
                        $input .= "<span class=\"ui-button selected-option\" data-value=\"{$value}\">{$label}</span>";
                    }
                    $ha['value']=implode(',', $ha['value']);
                    $ia['value']='';
                } else {
                    $ia['value']=$this->getChoices($arg['value']);
                    if($ia['value']) {
                        $ha['value']=$arg['value'];
                    }
                }
            }
            $input .= $this->renderText($ia, false);
            $this->attributes = $oa;
            $input .= $this->renderHidden($ha);
            return $input;
        }

        $input = '<select';
        foreach ($a as $attr=>$value) {
            if (is_bool($value)) {
                $value = var_export($value, true);
            }
            $input .= ' ' . $attr . '="' . S::xml($value) . '"';
        }
        $input .= '>';
        $options = array();
        if (!$this->multiple && (!$this->required || !$this->value)) {
            $blank = (isset($this->placeholder))?($this->placeholder):(self::$labels['blank']);
            $options[] = '<option class="placeholder" value="">'.$blank.'</option>';
        }
        $values = (!is_array($this->value))?(preg_split('/\s*\,\s*/', (string)$this->value, -1, PREG_SPLIT_NO_EMPTY)):($this->value);

        if($values) {
            $ref = null;
            if($this->bind && ($sc=$this->getSchema()) && isset($sc->relations[$this->bind])) {
                $rel = $sc->relations[$this->bind];
                $cn = (isset($rel['className']))?($rel['className']):($this->bind);
                if(isset($this->scope)) {
                    $scope = (is_array($this->scope)) ?$this->scope :$cn::columns($this->scope);
                    foreach($scope as $ref) break;
                } else {
                    $rpk = $cn::pk($cn::$schema, true);
                    $ref = array_pop($rpk);
                }
            }
            foreach($values as $k=>$v) {
                if(is_object($v) && $v instanceof Model) {
                    $values[$k] = ($ref && isset($v->$ref))?($v->$ref):($v->pk);
                } else {
                    break;
                }
            }
        }

        $dprop = null;
        if($this->dataprop) {
            $dprop = (!is_array($this->dataprop))?(explode(',', $this->dataprop)):($this->dataprop);
        }

        foreach ($this->getChoices() as $k=>$v) {
            $value = $k;
            $label = false;
            $group = false;
            $attrs = '';
            if(is_object($v) && $v instanceof Model && isset($v::$schema['scope']['choices']['value']) && isset($v::$schema['scope']['choices']['label'])) {
                $v = $v->asArray('choices');
            }
            if (is_array($v)) {
                $firstv=false;
                foreach ($v as $vk=>$vv) {
                    if(!$firstv) {
                        $firstv = $vv;
                    }
                    if($vk=='value') {
                        $value = $vv;
                    } else if($vk=='label') {
                        $label = $vv;
                    } else if($vk=='group') {
                        $group = $vv;
                    } else if(!is_int($vk)) {
                        $attrs.=' data-'.$vk.'="'.S::xml($vv).'"';
                    }
                }
                if(!$label && $firstv) {
                    $label = $firstv;
                }
                /*
                if($dprop) {
                    foreach($dprop as $dn) if(isset($v[$dn])) $attrs .= ' data-'.$dn.'="'.S::xml($v->{$dn}).'"';
                }
                */
            } else if(is_object($v) && $v instanceof Model) {
                $label = $v->label;
                if(!$label) {
                    $label = (string)$v;
                }
                $group = (isset($v->_group))?($v->_group):($v->group);
                if($dprop) {
                    $value = $v->getPk(true);
                    foreach($dprop as $dn) {
                        if(isset($value[$dn])) unset($value[$dn]);
                        $attrs .= ' data-'.$dn.'="'.S::xml($v->{$dn}).'"';
                    }
                    $value = implode(',', $value);
                } else {
                    $value = $v->pk;
                }
            } else {
                $label = (string) $v;
            }
            if(in_array($value, $values)){
                $attrs .= ' selected="selected"';
            }
            $dl = '<option value="' . S::xml($value) . '"' . $attrs
                . '>' . S::xml(strip_tags($label)) . '</option>';
            if ($group) {
                $options[$group][]=$dl;
            } else {
                $options[] = $dl;
            }
        }
        $dl = '';
        foreach ($options as $k=>$v) {
            if (is_array($v)) {
                $dl .= '<optgroup label="'.S::xml($k).'">'.implode('', $v).'</optgroup>';
            } else {
                $dl .= $v;
            }
        }
        $input .= $dl . '</select>';
        return $input;
    }



    /**
     * CSRF implementation (beta)
     */
    public function renderCsrf(&$arg)
    {
        $ua = (isset($_SERVER['HTTP_USER_AGENT']))?($_SERVER['HTTP_USER_AGENT']):('unknown');
        $arg['value'] = S::encrypt(md5($ua).":".S_TIME);
        if(isset($this->placeholder)) {
            $this->choices=array($arg['value'] => $this->placeholder);
            $arg['value'] = '0';
            $s = $this->renderHidden($arg);
            unset($arg['template']);
            $arg['type'] = 'checkbox';
            return $s.$this->renderCheckbox($arg, 'checkbox');
        } else {
            $arg['type']='hidden';
            return $this->renderHidden($arg);
        }
    }

    public function checkCsrf($value, $message='')
    {
        if($value && ($d=S::decrypt($value))) {
            @list($h, $t) = explode(':', $d, 2);
            $ua = (isset($_SERVER['HTTP_USER_AGENT']))?($_SERVER['HTTP_USER_AGENT']):('unknown');
            if(md5($ua)==$h && $t && $t +3600 > S_TIME) {
                return $value;
            }
        }
        throw new AppException(array(S::t($message, 'exception'), $this->getLabel(), $value));
    }

    public function setClassName($n)
    {
        $this->_className = $n;
    }


    /**
     * Magic terminator. Returns the page contents, ready for output.
     *
     * @return string page output
     */
    function __toString()
    {
        return $this->render();
    }

    /**
     * Magic setter. Searches for a set$Name method, and stores the value in $_vars
     * for later use.
     *
     * @param string $name  parameter name, should start with lowercase
     * @param mixed  $value value to be set
     *
     * @return void
    public function __set($name, $value)
    {
        $Name = S::camelize($name, true);
        $m='set'.$Name;
        if (method_exists($this, $m)) {
            $this->$m($value);
        } else if(property_exists($this,$name)) {
            $this->$name=$value;
        } else if(property_exists($this,$Name=lcfirst($Name))) {
            $this->$Name=$value;
        } else if(static::$allowedProperties && (static::$allowedProperties===true || in_array($name, static::$allowedProperties))) {
            $this->$name = $value;
        } else {
            throw new AppException(array('Method or property not available: "%s"', $name));
        }
    }
     */
}