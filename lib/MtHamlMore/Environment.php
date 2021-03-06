<?php

namespace MtHamlMore;

use MtHaml\Exception;
use MtHamlMore\Exception\MoreException;
use MtHamlMore\NodeVisitor\ApplySnip;
use MtHamlMore\Target\PhpMore;
use MtHaml\Target\Php;
use MtHaml\Target\Twig;
use MtHaml\NodeVisitor\Escaping as EscapingVisitor;
use MtHamlMore\NodeVisitor\ApplyPlaceholderValue;
use MtHamlMore\NodeVisitor\MakesurePlaceholderValue;
use MtHamlMore\Snip\SnipHouse;
use MtHamlMore\Log\Log;
use MtHamlMore\Log\LogInterface;
use MtHamlMore\Target\TwigMore;

class Environment extends \MtHaml\Environment
{

    public $currentMoreEnv;
    public $noReduceRuntime=true;
    public $reduceRuntimeArrayTolerant =false;

    public function compileString($string, $moreOption=array(),$returnRoot=false)
    {
        if(!empty($moreOption['reduce_runtime'])) $this->noReduceRuntime=false;
        if (!empty($moreOption['reduce_runtime_array_tolerant'])) $this->reduceRuntimeArrayTolerant = true;

        if(empty( $moreOption['filename'])){
            return  parent::compileString($string, '[unnamed]');
        }

        $prepareWork = false;

        if(is_string($moreOption)) $moreOption=array('filename'=>$moreOption);
        $this->currentMoreEnv=new MoreEnv($moreOption,$this);

        $filename= $moreOption['filename'];
        if ($this->currentMoreEnv['prepare']) {
            list($string, $filename, $prepareWork) = $this->prepare($string, $filename);
        }

        $string = $this->parseInlineSnipCaller($string);
        $string = $this->parseInlinePlaceholder($string,$this->currentMoreEnv['globalDefaultPlaceholderValue']);

        if ($returnRoot){
            // copied from parent::compileString
            // run until PhpRenderer
            $target = $this->getTarget();
            $node = $target->parse($this, $string, $filename);
            foreach($this->getVisitors() as $visitor) {
                $node->accept($visitor);
            }
            $compiled = $node;
        }else{
            $compiled = parent::compileString($string, $filename);
        }

        if ($prepareWork && !$this->currentMoreEnv['debug']) {
            unlink($filename);
        }
        if ( ($parent=$this->currentMoreEnv['parentenv'] ) instanceof MoreEnv){
            $this->currentMoreEnv = $parent;
        }
        return $compiled;

    }

    protected function prepare($string, $filename)
    {
        $prepareWork = false;

        //  There seems to be some unexpected behavior when using the /m modifier when the line terminators are win32 or mac format.
        //  http://www.php.net/manual/en/function.preg-replace.php#85416
        $string = str_replace(array("\r\n", "\r"), "\n", $string);

        // parse {= =} and {% %} , and protect <?php which maybe used by snips
        $changed = preg_replace(array(
                '/<\?php\s/',
                '/\{=\s*([^}]+)\s*=\}/',
                '/^\{%\s*([^}]+)\s*%\}$/m',
            ),
            array(
                '<<<php',
                '<?php echo \1; ?>',
                '<?php \1; ?>',
            ), $string, -1, $count);
        if ($count > 0) {
            $prepareWork = true;
            $filename = $filename . '.prepare.haml';
            file_put_contents($filename, $changed);
            ob_start();
            try {
                include $filename;
                $string = ob_get_clean();
            } catch (\Exception $e) {
                ob_end_clean();
                throw new  Exception("prepare file $filename : $e");
            }

            // restore <?php
            $string = str_replace('<<<php', '<?php ', $string);


        }

        return array($string, $filename, $prepareWork);

    }

    /* @{} -> X
     * \@{} -> @{}
     * \\@{} -> \X
     * \\\@{} -> \@{}
     * \\\\@{} -> \\X
     * \\\\\@{} -> \\@{}
     */
    protected function parseInlineSnipCaller($string)
    {
        return preg_replace_callback('/(?<escape>\\\\*)@\{(\w+)\}/', function ($matches) {
            $number = strlen($matches['escape']);
            if ($number % 2 == 0) {
                $front = str_repeat('\\', $number / 2);
                $options = array(
                    'level' => $this->currentMoreEnv['level'] + 1,
                );
                // trim any break line or indent space
                $parsedSnip =  rtrim(
                        self::parseSnip($matches[2], array(),array(), $options, $this->currentMoreEnv),
                        "\n") ;
                return $front . $parsedSnip;
            } else {
                return str_repeat('\\', ($number - 1) / 2) . '@{' . $matches[2] . '}';
            }
        }, $string);

    }

    protected function parseInlinePlaceholder($string,$globalDefaultPlaceholderValue=null)
    {

        if ($this->currentMoreEnv->hasPlaceholdervalues()) {
            $values = $this->currentMoreEnv->getPlaceholdervalues();
            $nextMaybeUnnamedPlaceholderIndex = 0;

            $string = preg_replace_callback(
                '/
                (?<block>
                    (?m:
                        ^
                        \s*
                        @@@
                        (?:\s*|\s+.*)
                        $ # un-named Placeholder
                     )
                 )
                |
                (?:
                    (?<escape>\\\\*)
                    \{@
                        (
                            :
                            (?<default>.*)
                         )?
                     @\}
                  ) # un-named InlinePlaceholder
                |
                (?:
                    (?<escape2>\\\\*)
                    \{@
                        (
                            (?<name>\w+)
                            (
                                :
                                (?<default2>.*)
                             )?
                         )?
                   @\}
                ) # named InlinePlaceholder
                /x',
                function ($matches) use (&$values, &$nextMaybeUnnamedPlaceholderIndex,$globalDefaultPlaceholderValue) {
                    //  un-named Placeholder
                    if (!empty($matches['block'])) {
                        ++$nextMaybeUnnamedPlaceholderIndex;
                        return $matches['block'];
                    }

                    //  un-named InlinePlaceholder
                    if (empty($matches['name'])) {
                        $number = isset($matches['escape'])?strlen($matches['escape']):0;
                        if ($number % 2 == 0) {
                            $front = str_repeat('\\', $number / 2);
                            $name = $nextMaybeUnnamedPlaceholderIndex;
                            if (isset($values[1][$name])) {
                                list($v) = array_splice($values[1], $name, 1);
                                return $front . $this->renderSnipTree($v);
                            } elseif (!empty($matches['default']))
                                return $front . $matches['default'];
                        } else {
                            return str_repeat('\\', ($number - 1) / 2) . '{@' . (array_key_exists(3,$matches)?$matches[3]:'') . '@}';
                        }
                        //  named InlinePlaceholder
                    } else {
                        $number = isset($matches['escape2'])?strlen($matches['escape2']):0;
                        if ($number % 2 == 0) {
                            $front = str_repeat('\\', $number / 2);
                            $name = $matches['name'];
                            if (isset($values[0][$name])) {
                                return $front . $this->renderSnipTree($values[0][$name]);
                            } elseif (!empty($matches['default2'])) {
                                return $front . $matches['default2'];
                            }
                        } else {
                            return str_repeat('\\', ($number - 1) / 2) . '{@' . (array_key_exists(6,$matches)?$matches[6]:'') . '@}';
                        }
                    }

                    if(is_string($globalDefaultPlaceholderValue))
                        return $front.$globalDefaultPlaceholderValue;

                    throw new MoreException('plz supply value for inlinePlaceholder ' . $name);
                }, $string);

            $this->currentMoreEnv->setPlaceholdervalues($values);
        }

        return $string;
    }

    protected function renderSnipTree($nodes,$trim=true)
    {

        if (!is_array($nodes))
            $nodes = array($nodes);

        $target = $this->getTarget();

        $outputs = array();

        foreach ($nodes as $node) {
            foreach ($this->getVisitors() as $visitor) {
                $node->accept($visitor);
            }

            $outputs[] = $target->compile($this, $node);
        }

        $outputs = implode('', $outputs);
        return $trim? ltrim(rtrim($outputs,"\n")) :outputs;
    }


    public function getTarget()
    {
        $target = $this->target;
        if (is_string($target)) {
            switch ($target) {
                case 'php_more':
                    $target = new PhpMore;
                    break;
                case 'twig_more':
                    $target = new TwigMore;
                    break;
                case 'php':
                    $target = new Php;
                    break;
                case 'twig':
                    $target = new Twig;
                    break;
                default:
                    throw new Exception(sprintf('Unknown target language: %s', $target));
            }
            $this->target = $target;
        }
        return $target;
    }

    public function getVisitors()
    {
        $visitors = array();

        if($more_env=$this->currentMoreEnv){
            // visitor order is important
            $visitors[] = $this->getMakesurePlaceholderValueVisitor();
            // if is useless and harmful, because ApplyPlaceholderValueVisitor also apply placehodler default value
            //  if($this->hasPlaceholdervalues())
            $visitors[] = $this->getApplyPlaceholderValueVisitor($this->currentMoreEnv->getPlaceholdervalues(),$this->currentMoreEnv['globalDefaultPlaceholderValue']);
            $visitors[] = $this->getApplySnipVisitor($this->currentMoreEnv['baseIndent']);
        }
        $visitors[] = $this->getAutoclosevisitor();
        $visitors[] = $this->getAutoclosevisitor();
        $visitors[] = $this->getMidblockVisitor();
        $visitors[] = $this->getMergeAttrsVisitor();

        if ($this->getOption('enable_escaper')) {
            $visitors[] = $this->getEscapingVisitor();
        }

        return $visitors;
    }

    public function getApplySnipVisitor($indent)
    {
        return new ApplySnip($indent);
    }
    public function getApplyPlaceholderValueVisitor($values,$globalDefaultValue)
    {
        return new ApplyPlaceholderValue($values,$globalDefaultValue);
    }

    public function getMakesurePlaceholderValueVisitor()
    {
        return new MakesurePlaceholderValue();
    }

/*
 * @param options:
             array(
                'placeholdervalues' => array(array(),array()),
                'baseIndent' => 0,
                'level' => 0,
            )
* @param $parentEnv : the key reason of this argument is at README.md::Development Rule 2.3.3
*/
    public static function parseSnip($snipName, array $attributes = array(),array $inlineContent=array(), $options = array(), MoreEnv $parentMoreEnv,$returnRoot=false)
    {


        $options = $options + array(
                'placeholdervalues' => array(array(),array()),
                'baseIndent' => 0,
                'level' => 0,
            );

        $snipHouse = $parentMoreEnv->getSnipHouse();
        $front = str_repeat("....", $options['level'] - 1);

        if($parentMoreEnv['debug']){
            $log = $parentMoreEnv->getLog();
            $log->info($front . 'HAML : ' . $parentMoreEnv['filename']);
            $log->info($front . "call snip : [$snipName]");
        }

        list($snipHaml, $fileName, $snips) = $snipHouse->getSnipAndFiles($snipName, $attributes, $inlineContent);

        if($parentMoreEnv['debug']){
            $log->info($front . "located at file $fileName");
            $log->info();
        }


        $moreOptions =
            array(
                'snipname' => $snipName,
                'uses' => $snips,
                'filename' => $fileName,
                'prepare' => false,
                'parentenv'=> $parentMoreEnv,
            )
            + $options
            + $parentMoreEnv->getOptions()
        ;

        $haml= $parentMoreEnv->getBelongTo();
        return $haml->compileString($snipHaml, $moreOptions, $returnRoot);


    }
}

class MoreEnv implements \ArrayAccess
{
    private $belongTo;
    protected $options;
    protected $snipHouse;

    function __construct(array $options,Environment $env)
    {
        $this->options = $options + array(
                'filename' => '',
                'uses' => array(),
                'snipname' => '',
                'placeholdervalues' => null,
                'globalDefaultPlaceholderValue'=>null,
                'prepare' => false,
                'baseIndent' => 0,
                'level' => 0,
                'log' => null,
                'debug' => false,
                'snipcallerNode'=>null,
                'parentenv'=>null,
            );
        if (isset($options['log']))
            $this->setLog($options['log']);

        if (isset($options['placeholdervalues']))
            $this->setPlaceholdervalues($options['placeholdervalues']);

        $this->belongTo=$env;

    }
    function offsetExists($offset){
        return isset($this->options[$offset]);
    }
    function offsetGet($offset){
        return $this->options[$offset];
    }
    function offsetSet($offset, $value){}
    function offsetUnset($offset){}
    public function getOptions()
    {
        return $this->options;
    }

    function getBelongTo()
    {
        return $this->belongTo;
    }

    function getLog()
    {
        if (empty($this->options['log'])) {
            $log = new Log($this->options['debug']);
            $this->setLog($log);
        }
        $log = $this->options['log'];
        return $log;
    }

    function setLog(LogInterface $log)
    {
        $this->options['log'] = $log;
    }

    function getSnipHouse()
    {
        if (!($this->snipHouse instanceof SnipHouse)) {
            $this->setSnipHouse($this->options['uses'], $this->options['filename']);
        }
        return $this->snipHouse;
    }

    protected function setSnipHouse($S, $mainFile)
    {
        // instanceof is nessesary, because Environment maybe instansed in NodeVisitor\PhpRenderer
        if ($S instanceof SnipHouse) {
        } elseif (gettype($S) == 'string' || gettype($S) == 'array'){
            $S = new SnipHouse($S, $mainFile);
        }else{
            throw new SnipException('require str or array or SnipHouse instance to setSnips');
        }
        $this->snipHouse = $S;
    }

    function hasPlaceholdervalues()
    {
        return !empty($this->options['placeholdervalues']);
    }

    function getPlaceholdervalues()
    {
        return $this->options['placeholdervalues'];
    }

    /*
     * @param $v :array(array namedValues, array unnamedValues)
     */
    function setPlaceholdervalues(array $v)
    {
        if(count($v) == 2)
            $this->options['placeholdervalues'] = $v;
        else{
            throw new SnipException(sprintf("there should two elements in array %s to set placeholder values",print_r($v,true)));
        }
    }

}
