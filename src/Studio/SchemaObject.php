<?php
/**
 * Schema-based Generic Object
 * 
 * This base class implements ArrayAccess and automatic property validation using Schemas
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
use Studio\Schema;
use Studio\Exception\AppException;
use arrayObject;
use ArrayAccess;

#[\AllowDynamicProperties]
class SchemaObject implements ArrayAccess
{
    const SCHEMA_PROPERTY='meta';
    const SCHEMA_CLASS='Studio\\Schema';
    const AUTOLOAD_CALLBACK='staticInitialize';

    /**
     * Object initialization can receive an array as the initial values
     */
    public function __construct($o=null)
    {
        $schema = static::SCHEMA_PROPERTY;
        if(!is_null($o) && property_exists(get_called_class(), $schema)) {
            if(is_object($o) && ($o instanceof ArrayAccess)) $o = (array) $o;
            if(is_array($o)) {
                $schemaClass = (static::${$schema})?(get_class(static::${$schema})):(static::SCHEMA_CLASS);
                $schemaClass::apply($this, $o, static::${$schema});
            }
        }
    }

    public static function staticInitialize()
    {
        $schema = static::SCHEMA_PROPERTY;
        if(property_exists(get_called_class(), $schema)) {
            $schemaClass = (static::${$schema})?(get_class(static::${$schema})):(static::SCHEMA_CLASS);
            static::${$schema} = $schemaClass::loadSchema(get_called_class());
        }
    }

    public function resolveAlias($name)
    {
        if(($schema = static::SCHEMA_PROPERTY) && is_object($Schema=static::${$schema}) && property_exists($Schema, 'properties')) {
            $i = 10;
            $oname = $name;
            while(isset($Schema->properties[$name]['alias']) && $i--) {
                $name = $Schema->properties[$name]['alias'];
            }
        }
        unset($Schema);
        return $name;
    }

    public function value($serialize=null)
    {
        $schema = static::SCHEMA_PROPERTY;
        $r = null;
        if(property_exists(get_called_class(), $schema)) {
            $Schema = static::${$schema};
            $type = $Schema->type;
            if(!$type && $Schema->properties) {
                $type = 'object';
            } else if(!$type) {
                $type = 'string';
            }
            if($type==='object') {
                $r = [];
                if($Schema->properties) {
                    foreach($Schema->properties as $name=>$def) {
                        if(isset($this->$name)) $r[$name] = $this->$name;
                    }
                }
            } else {
                $r = array_values((array)$this);
                if($type==='string') {
                    $r = (string) array_shift($r);
                } else if($type==='int') {
                    $r = (int) array_shift($r);
                }
            }
        }
        if($serialize) {
            return S::serialize($r, $serialize);
        }

        return $r;
    }

    /**
     * ArrayAccess abstract method. Gets stored parameters.
     *
     * @param string $name parameter name, should start with lowercase
     *
     * @return mixed the stored value, or method results
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($name)
    {
        $name = $this->resolveAlias($name);
        $neg = false;
        if(substr($name, 0, 1)==='!') {
            $name = substr($name, 1);
            $neg = true;
        }
        if (method_exists($this, $m='get'.ucfirst(S::camelize($name)))) {
            return ($neg) ?!$this->$m() :$this->$m();
        } else if (isset($this->$name)) {
            return ($neg) ?!$this->$name :$this->$name;
        }
        $n = null;
        return $n;
    }

    public function __get($name)
    {
        return $this->offsetGet($name);
    }

    public function __set($name, $value)
    {
        return $this->offsetSet($name, $value);
    }

    public function batchSet($values, $skipValidation=false)
    {
        if(is_array($values) || is_object($values)) {
            foreach($values as $name=>$value) {
                if($skipValidation) $this->$name = $value;
                else $this->__set($name, $value);
            }
        }
        return $this;
    }

    /**
     * ArrayAccess abstract method. Sets parameters to the PDF.
     *
     * @param string $name  parameter name, should start with lowercase
     * @param mixed  $value value to be set
     *
     * @return void
     */
    public function offsetSet($name, $value): void
    {
        $name = $this->resolveAlias($name);
        if(substr($name, 0, 1)==='!') {
            $name = substr($name, 1);
            $value = !$value;
        }
        if (method_exists($this, $m='set'.S::camelize($name))) {
            $this->$m($value);
        } else if(property_exists(get_called_class(), $schema = static::SCHEMA_PROPERTY)) {
            // validate schema, when available
            $Schema = static::${$schema};
            if($Schema) {
                if(isset($Schema->properties[$name])) {
                    $value = $Schema::validateProperty($Schema->properties[$name], $value, $name);
                } else if(!isset($Schema->patternProperties) || !preg_match($Schema->patternProperties, $name)) {
                    throw new AppException(array(S::t('Column "%s" is not available at %s.','exception'), $name, get_class($this)));
                }
            }
            $this->$name = $value;
        } else if(!property_exists($this, $name)) {
            throw new AppException(array(S::t('Column "%s" is not available at %s.','exception'), $name, get_class($this)));
        } else {
            $this->$name = $value;
        }
        unset($m);
    }

    /**
     * ArrayAccess abstract method. Searches for stored parameters.
     *
     * @param string $name parameter name, should start with lowercase
     *
     * @return bool true if the parameter exists, or false otherwise
     */
    public function offsetExists($name): bool
    {
        $name = $this->resolveAlias($name);
        if(substr($name, 0, 1)==='!') {
            $name = substr($name, 1);
        }
        return isset($this->$name);
    }

    /**
     * ArrayAccess abstract method. Unsets parameters to the PDF. Not yet implemented
     * to the PDF classes — only unsets values stored in $_vars
     *
     * @param string $name parameter name, should start with lowercase
     */
    public function offsetUnset($name): void
    {
        $this->offsetSet($name, null);
    }
}