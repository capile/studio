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

    protected $schema_id, $bind, $type, $item, $title, $description, $primary, $required, $default, $serialize, $created, $updated, $Display, $Schema;

    public function __toString()
    {
        $s = $this->bind;
        if($this->title) $s .= ': '.$this->title;
        if($this->description) $s .= ': '.$this->description;

        return $s;
    }

    public static function choicesType() { return Schema::choicesType(); }
    public function choicesItem()
    {
        static $o;
        if(!$o) {
            $o = [];
            $q = [];
            if($this->schema_id) $q['id!=']=$this->schema_id;
            if($L=Schema::find($q, null, ['id', 'title'],false)) {
                foreach($L as $a=>$b) {
                    $o[$b->id] = $b->title;
                    unset($L[$a], $a, $b);
                }
            }
        }
        return $o;
    }

    public static function choicesSerialize()
    {
        return ['json'=>'JSON', 'yaml'=>'YAML', 'php'=>'PHP'];
    }
}