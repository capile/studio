<?php
/**
 * SCIM Database abstraction
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */
namespace Studio\Query;

use Studio as S;
use Studio\Cache;

class Scim extends Api
{
    public static 
        $limit='count',
        $limitCount='200',
        $offset='startIndex',
        $queryPath='/%s',
        $previewPath='/%s/%s',
        $insertPath='/%s',
        $updatePath='/%s',
        $deletePath='/%s',
        $deleteQuery='operation=delete',
        $queryTableName=false,
        $countAttribute='totalResults',
        $dataAttribute='Resources|{$_ResponseProperty}',
        $errorAttribute='Error',
        $saveToModel=true,
        $enableOffset=true
        ;

    public function __construct($s=null)
    {
        parent::__construct($s);

        if(preg_match('@^scim(\+https?)?(://.*)@', $this->_url, $m)) {
            $this->_url = (($m[1]) ?substr($m[1], 1) :'https').$m[2];
        }
        unset($m);
    }
}