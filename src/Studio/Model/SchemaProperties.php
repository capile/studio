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
use Studio\Model\Schema;

class SchemaProperties extends Model
{
    public static $schema;

    protected $schema_id, $bind, $type, $format, $title, $description, $primary, $required, $default, $serialize, $created, $updated, $Display, $Schema;

    public function __toString()
    {
        $s = $this->bind;
        if($this->title) $s .= ': '.$this->title;
        if($this->description) $s .= ': '.$this->description;

        return $s;
    }

    public static function choicesType() { return Schema::choicesType(); }
    public static function choicesFormat()
    {
        static $o;
        if(!$o) {
            $o = S::t([
                'text' => 'Text input',
                'number' => 'Number input',
                'date' => 'Date input',
                'datetime' => 'Datetime input',
                'email' => 'E-mail input',
                'checkbox' => 'Checkbox',
                'radio' => 'Radio buttons',
                'select' => 'Select',
                'select-multiple' => 'Multiple Select',
            ], 'model-studio_schema');
        }
        return $o;
    }

    public static function choicesSerialize()
    {
        return ['json'=>'JSON', 'yaml'=>'YAML', 'php'=>'PHP'];
    }
}