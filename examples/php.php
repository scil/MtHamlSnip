<?php


require_once __DIR__ . '/../lib/MtHaml/More/entry.php';

$hamlfile=__DIR__ . '/php.haml';

echo <<<S
<h1>MtHamlMore examples </h1>
<p>please watch in web browser</p>
<h4>snip call process logs outputed by option 'debug'=>true  :</h4>\n<p style='height:200px;overflow:scroll;margin:30px;'>
S;

$compiled = compilePhpMoreHaml( file_get_contents($hamlfile),array(),
    array(
        'uses'=>array(__DIR__.'/snips/php.php'),
        'filename'=>$hamlfile,
        'prepare'=>true,
        'debug'=>true,
        'enable_escaper' => false,
));

echo "</p>\n<h2>rendered html :</h2>\n";

echo $compiled;

