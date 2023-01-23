<?php
namespace Query;

use Studio as S;
use Studio\Query\Mongo;
use Studio\Model\Entries;
use \Codeception\Test\Unit as TestCase;
use Studio\Yaml;

class MongoTest extends TestCase
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    protected $dbConfig = []; //, $host = 'http://127.0.0.1:9999', $terminate;

    protected function _before()
    {
        if (!$this->dbConfig && file_exists($fileCfg = S_ROOT . '/data/tests/_data/mongo.yml')) {
            $data = file_get_contents($fileCfg);
            $this->dbConfig = Yaml::loadString($data);
        }

        S::$database = $this->dbConfig['all']['database'];
    }

    protected function _after()
    {
    }

    public function testCreateInstance()
    {
       /* @TODO: ainda não entendi para que serve o constructor se pega uma string e retorna uma string :O
        $mongo = new Mongo('Studio\Model\Entries');
var_dump($mongo);
        $this->assertInstanceOf(Entries::class, $mongo);*/
    }

    public function testConnect()
    {
       codecept_debug($this->dbConfig);
       codecept_debug(S::$database);

       $db = new Mongo();
       $conn = $db::connect("mongotest01");
       codecept_degug($conn);
        //$conn =
    }
}