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
declare(strict_types=1);
namespace Studio\Schema;

use Studio as S;
use Studio\Schema;
use arrayObject;
use ArrayAccess;
use Exception;

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

    public static function staticInitialize(): void
    {
        $schema = static::SCHEMA_PROPERTY;
        if(property_exists(get_called_class(), $schema)) {
            $schemaClass = (static::${$schema})?(get_class(static::${$schema})):(static::SCHEMA_CLASS);
            static::${$schema} = $schemaClass::loadSchema(get_called_class());
        }
    }

    public function resolveAlias(string $name): string
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

    public function value(?bool $serialize=null): mixed
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

    #[\ReturnTypeWillChange]
    public function &offsetGet(mixed $name): mixed
    {
        $name = $this->resolveAlias($name);
        if (method_exists($this, $m='get'.ucfirst(S::camelize($name)))) {
            return $this->$m();
        } else if (isset($this->$name)) {
            return $this->$name;
        }
        $n = null;
        return $n;
    }

    public function __get(mixed $name): mixed
    {
        return $this->offsetGet($name);
    }

    public function __set(mixed $name, mixed $value): mixed
    {
        return $this->offsetSet($name, $value);
    }

    public function batchSet(array $values, bool $skipValidation=false): SchemaObject
    {
        foreach($values as $name=>$value) {
            if($skipValidation) $this->$name = $value;
            else $this->__set($name, $value);
        }
        return $this;
    }

    public function offsetSet(mixed $name, mixed $value): void
    {
        $name = $this->resolveAlias($name);
        if (method_exists($this, $m='set'.S::camelize($name))) {
            $this->$m($value);
        } else if(property_exists(get_called_class(), $schema = static::SCHEMA_PROPERTY)) {
            // validate schema, when available
            $Schema = static::$$schema;
            if($Schema) {
                if(isset($Schema->properties[$name])) {
                    $value = $Schema::validateProperty($Schema->properties[$name], $value, $name);
                } else if(!isset($Schema->patternProperties) || !preg_match($Schema->patternProperties, $name)) {
                    throw new Exception(array(S::t('Column "%s" is not available at %s.','exception'), $name, get_class($this)));
                }
            }
            $this->$name = $value;
        } else if(!property_exists($this, $name)) {
            throw new Exception(array(S::t('Column "%s" is not available at %s.','exception'), $name, get_class($this)));
        } else {
            $this->$name = $value;
        }
        unset($m);
    }

    public function offsetExists(mixed $name): bool
    {
        $name = $this->resolveAlias($name);
        return isset($this->$name);
    }

    public function offsetUnset(mixed $name): void
    {
        $schema = static::SCHEMA_PROPERTY;
        if(isset(static::${$schema}[$name]['alias'])) $name = static::${$schema}[$name]['alias'];
        $this->offsetSet($name, null);
    }
}