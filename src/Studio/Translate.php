<?php
/**
 * Studio Translation
 * 
 * Automatic translation methods.
 * 
 * PHP version 8.1+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */
namespace Studio;

use Studio as S;
use Studio\Yaml;
use Studio\Exception\AppException;


class Translate
{
    public static 
        $method=null,               // add new translators as string (className), or array (callable)
        $apiKey=null,
        $clientId=null,
        $sourceLanguage='en',
        $forceTranslation,
        $writeUntranslated;
    protected static $_t=null;
    protected $_from='en', $_lang='en', $_table=[], $_keys=[];

    public function __construct($language=null)
    {
        if(is_null($language)) {
            $language = S::$lang;
        }
        $this->_lang = $language;
        $this->_from = self::$sourceLanguage;
    }
    
    /**
     * Translator shortcut
     * 
     * @param mixed  $message message or array of messages to be translated
     * @param string $table   translation file to be used
     * @param string $to      destination language, defaults to S::$lang
     * @param string $from    original language, defaults to 'en'
     */
    public static function message($message, $table=null, $to=null, $from=null)
    {
        if(is_null($to)) {
            $to = S::$lang;
        }

        if(!isset(self::$_t[$to])) {
            if(is_null(self::$_t)) {
                self::$_t = [];
            }
            if(!isset(self::$_t[$to])) {
                self::$_t[$to] = new Translate($to);
            }
        }
        return self::$_t[$to]->getMessage($message, $table);
    }
    
    public function getMessage($message, $table=null)
    {
        if(is_array($message)) {
            foreach ($message as $mi=>$mv) {
                if(!$mv) {
                    $message[$mi]=$mv;
                } else {
                    $message[$mi]=$this->getMessage($mv, $table);
                }
            }
            return $message;
        } else if(!$message) {
            return $message;
        } else if(!is_string($message)) {
            $message = (string) $message;
        }
        if(is_null($table)) {
            $table = 'default';
        }
        if(!isset($this->_table[$table][$message])) {
            if(!isset($this->_table[$table]) || !isset($this->_table[$table][$message])) {
                $l = preg_replace('/\-.*/', '', $this->_lang);

                if(
                    file_exists($yml=S_VAR.'/translate/'.$this->_lang.'/'.$table.'.yml') ||
                    file_exists($yml=S_VAR.'/translate/'.$l.'/'.$table.'.yml') ||
                    file_exists($yml=S_VAR.'/translate/'.$table.'.'.$this->_lang.'.yml') ||
                    file_exists($yml=S_VAR.'/translate/'.$table.'.'.$l.'.yml') ||
                    file_exists($yml=S_VAR.'/studio/'.$table.'.'.$l.'.yml') ||
                    file_exists($yml=S_ROOT.'/data/translate/'.$table.'.'.$l.'.yml') || 
                    (($yml=S_VAR.'/translate/'.$this->_lang.'/'.$table.'.yml') && false)
                ) {

                } else if(self::$forceTranslation) {
                    if(!S::save($yml, '--- automatic translation index, please update', true)) {
                        $yml = null;
                    }
                } else {
                    if(S::$log>2) S::log('[DEBUG] Translation table '.$table.' was not found.');
                    $yml = null;
                }
            }
            if($yml && !isset($this->_table[$table])) {
                $this->_table[$table] = Yaml::load($yml);
                if(isset($this->_table[$table]['all']) && count($this->_table[$table])===1) {
                    $this->_table[$table] = $this->_table[$table]['all'];
                }
                if(!is_array($this->_table[$table])) $this->_table[$table] = array();
            }
            if(!isset($this->_table[$table][$message])) {
                if(S::$log>2) S::log('[DEBUG] Translation entry '.$table.'.'.$message.' was not found.');
                $text = $message;
                $w = ($yml && self::$writeUntranslated);
                if($w && self::$forceTranslation && $this->_from!=$this->_lang && ($m=self::$method)) {
                    $O = null;
                    $s = true;
                    if(is_string($m)) {
                        if(method_exists($this, $m)) {
                            $O = $this;
                            $s = false;
                        } else if(class_exists($m)) {
                            $O = $m;
                            $m = 'message';
                        } else if(method_exists($this, $m=$m.'Translate')) {
                            $O = $this;
                        }
                    } else if(is_array($m)) {
                        list($O, $m) = $m;
                    }
                    if($O) {
                        try{
                            if($s) {
                                $text = $O::$m($this->_from, $this->_lang, $text);
                            } else {
                                $text = $O->$m($this->_from, $this->_lang, $text);
                            }
                        } catch(\Exception $e) {
                            S::log($e->getMessage());
                            $w = false;
                        }
                    }
                }
                $this->_table[$table][$message]=$text;
                if($w && strpos($yml, S_VAR)===0) {
                    Yaml::append($yml, array($message=>$text), 0);
                }
            }
        }
        return $this->_table[$table][$message];
    }
}
