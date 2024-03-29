<?php
/**
 * Studio Index
 * 
 * PHP version 7.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   1.0
 */
namespace Studio\Model;

use Studio\Model as Model;

class IndexNumber extends Model
{
    public static $schema;
    protected $interface, $id, $name, $value, $created, $updated, $Index;

    public function getValue()
    {
        if(isset($this->value) && is_string($this->value) && is_numeric($this->value)) {
            return (strpos($this->value, '.')!==false) ?(float) $this->value :(int)$this->value;
        }

        return $this->value;
    }
}
