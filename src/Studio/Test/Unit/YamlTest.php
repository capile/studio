<?php
/**
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   3.0
 */
namespace Studio\Test\Unit;

use Studio as S;
use Studio\Yaml;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class YamlTest extends TestCase
{

    public function setUp():void
    {
        Yaml::$cache = false;
    }

    public function testParser()
    {
        /**
         * The default parser should be the PHP-YAML
         * but there is a call to this class file that the autoloader executes and setup
         * It should be removed soon
         */
        foreach([Yaml::PARSE_NATIVE, Yaml::PARSE_SPYC] as $parser) {
            if($currentParser = Yaml::parser($parser)) {
                $this->assertEquals($parser, $currentParser);
                $this->assertEquals($parser, Yaml::parser());
            }
        }
    }

    public function testParserException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid parser: I do not exist');
        Yaml::parser('I do not exist');
    }

    public function testLoadDump()
    {
        $yamlFilePath = S_ROOT . '/data/tests/assets/sample.yml';
        $yamlFileContent = file_get_contents($yamlFilePath);

        foreach([Yaml::PARSE_NATIVE, Yaml::PARSE_SPYC] as $parser) {
            if(Yaml::parser($parser)) {
                $loadFile = Yaml::load($yamlFilePath);
                $loadContent = Yaml::load($yamlFileContent);
                $loadString = Yaml::loadString($yamlFileContent);
                $this->assertEquals($loadContent, $loadFile);
                $this->assertEquals($loadContent, $loadString);

                $yaml = $loadContent;

                // A simple keys test because what matters is tha both parser gives the same answers
                $this->assertIsArray($yaml);
                $this->assertArrayHasKey('all', $yaml);
                $this->assertArrayHasKey('title', $yaml['all']);
                $this->assertArrayHasKey('auth', $yaml['all']);
                $this->assertArrayHasKey('credential', $yaml['all']['auth']);
                $this->assertEquals(['first one', 'second one'], $yaml['all']['auth']['credential']);

                /**
                 * Testing the dump()
                 */
                $yamlString = Yaml::dump($yaml);
                $this->assertEquals($yaml, Yaml::loadString($yamlString));
            }
        }
    }

    /*
     redo this
    public function testAppend()
    {
        $resetFile = function () {
            copy(__DIR__ . '/../assets/sample-translate.yml', __DIR__ . '/../assets/sample-temp.yml');
        };
        $resetFile();
        $yamlFilePath = __DIR__ . '/../assets/sample-temp.yml';
        $yamlFileOriginalContent = file_get_contents($yamlFilePath);

        Tecnodesign_Yaml::parser(Tecnodesign_Yaml::PARSE_NATIVE);
        $yaml = Tecnodesign_Yaml::load($yamlFilePath);
        $append = [
            'nome' => 'Nombre',
            'aniversário' => 'Birthday'
        ];
        $yamlAppended = array_replace_recursive($yaml, ['all' => $append]);
        Tecnodesign_Yaml::append($yamlFilePath, $append);
        sleep(1);
        $yamlFileNewContent = file_get_contents($yamlFilePath);
        $yamlNew = Tecnodesign_Yaml::load($yamlFilePath);
        $this->assertNotEquals($yamlFileOriginalContent, $yamlFileNewContent);
        $this->assertEquals($yamlNew, $yamlAppended);
        $this->assertArraySubset($append, $yamlNew['all']);

        $resetFile();
        $append = ['all' => $append];
        Tecnodesign_Yaml::append($yamlFilePath, $append);
        sleep(1);
        $yamlFileNewContent = file_get_contents($yamlFilePath);
        $yamlNew = Tecnodesign_Yaml::load($yamlFilePath);
        $this->assertNotEquals($yamlFileOriginalContent, $yamlFileNewContent);
        $this->assertEquals($yamlNew, $yamlAppended);
        $this->assertArraySubset($append, $yamlNew);
    }
    */

    public function testAppendException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$append must be an array');
        Yaml::append('whatever', 'I should be an array');
    }

    /*
     redo this
    public function testSave()
    {
        $loadSampleFile = function () {
            copy(__DIR__ . '/../assets/sample.yml', __DIR__ . '/../assets/sample-temp.yml');
        };
        $loadTranslateFile = function () {
            copy(__DIR__ . '/../assets/sample-translate.yml', __DIR__ . '/../assets/sample-temp.yml');
        };
        $loadSampleFile();
        $yamlFilePath = __DIR__ . '/../assets/sample-temp.yml';
        Tecnodesign_Yaml::$cache = true;
        $yaml = Tecnodesign_Yaml::load($yamlFilePath,0);
        // change the contents
        $loadTranslateFile();
        $yamlCached = Tecnodesign_Yaml::load($yamlFilePath,0);
        $this->assertEquals($yaml, $yamlCached);
        $this->markTestIncomplete('Needs to define better how cache works');
        $yamlCached = Tecnodesign_Yaml::load($yamlFilePath,100);
        $this->assertNotEquals($yaml, $yamlCached);
    }
    */

}
