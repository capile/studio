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
        if(!$this->title) return S::t(ucwords(preg_replace('/[\-\_\.]+/', ' ', basename($this->id))), 'model-studio_schema');
    }

    public function formOverlay($prefix=null)
    {
        return [
            $prefix.'.teste' => ['type'=>'text','label'=>'teste', 'bind'=>$prefix.'.teste'],
        ];
    }
}