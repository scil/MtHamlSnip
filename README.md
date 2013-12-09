MtHamlMore
==========

Add more features like snippet to MtHaml,  main purpose is “Don't Reinvent the Wheel".

Currently only php supported , no Twig.

Demo
----

haml
```
@title{"one box"}
@box
    _ this is title
    _ this is content

@title{"box with default value"}
@box_withDefault
    _body
        custom
        @@default

@title{"two columns"}
@two_columns
    _left %p hello,everyone
    _right
        @box
            _ title
            _ content
```

output
```
<h2>example 1 : one box</h2>
<div class="title">
  this is title
</div>
<div class="body">
  this is content
</div>
<h2>example 2 : box with default value</h2>
<div class="box">
  <div class="title">
    default title
  </div>
  <div class="body">
    custom
    <p>default content</p>
  </div>
</div>
<h2>example 3 : two columns</h2>
<div class="clear">
  <div class="left">
    <p>hello,everyone</p>
  </div>
  <div class="right">
    <div class="title">
      title
    </div>
    <div class="body">
      content
    </div>
  </div>
</div>
```

snips defined
```
$title=function($title){
    static $num=0;
    ++$num;
    return "%h2 example $num : $title";
};

$box=<<<S
.title
    @@@
.body
    @@@
S;

$box_withDefault=<<<S
.box
    .title
        @@@title default title
    .body
        @@@body
            %p default content
S;

$two_columns=<<<S
.clear
    .left
        @@@left
    .right
        @@@right
S;
```

please see more examples at  examples/php.haml which is parsed by examples/php.php

Hint
----

these two snippets are same:
```
@box
    _ this is title
    _ this is content
```

```
@box
_ this is title
_ this is content
```
code: \MtHaml\More\NodeVisitor\MakesurePlaceholderValue::enterSnipCaller, PlaceholderValue is appended to SnipCaller as child

Getting Started
-----

step 1: install MtHaml using composer.

step 2:  snip file mysnips.php ,defining one snip named box
```
<?php
$box=<<<S
.title
    @@@title
.body
    @@@body
S;
?>
```

step 3:  haml file callSnip.haml
```
@box
    _title an example
    _body
        %p content
```

step 4: php code
```
// ROOT_DIR is the root dir of MtHamlMore
require_once ROOT_DIR . '/lib/MtHaml/More/entry.php';
$hamlfile=__DIR__ . '/php.haml';
$compiled = compilePhpMoreHaml(
    file_get_contents($hamlfile),
    array( 'enable_escaper' => false,),
    array(
        'uses'=>array('mysnips.php'),
        'filename'=>$hamlfile,
));
echo "<h1>rendered template:</h1>\n";
echo $compiled;
```

Glossary
----
* snip : a haml snippet which could be inserted into a haml string or another snip

* PlaceHolder : one type of Node, part of snip, which allow user to insert custom content.
default values can be defined for a Placeholder, and you can call default values using '@@default',which is parsed as PlaceholderDefaultCaller.

* InlinePlaceholder :it's different with Placeholder, just like block element vs inline element in web DOM.
    * Warning: InlineSnipCaller is not a type of Node, it's parsed before parsing haml tree, see:
        MtHaml\More\Environment::parseInlinePlaceholder

* snip file : where snips live. snip file is parsed by the instance of Snip\SnipHouseInterface , which should be appointed at the first line of snip file
    * example: -# SnipParser="\MtHaml\More\Snip\SnipFileParser"
    * this is the default parser, you can ignore it.

* SnipCaller : a Node in a haml tree used to insert snip. "@box" is a SnipCaller used to insert snip "box".

* InlineSnipCaller : like InlinePlaceholder

* uses : a team of snip files used by a haml file or a snip file. snip files used by a haml is configed by option 'uses',
snip files by a snip file is configed by variable $__MtHamlMore_uses when the snip file is parsed by the default parser "\MtHaml\More\Snip\SnipFileParser"

* mixes : a team of snip files mixed with a snip file.
they are configed by variable $__MtHamlMore_mixes when the snip file is parsed by the default parser "\MtHaml\More\Snip\SnipFileParser"


Precautions
----

### Snip Attributes
1. Snip Attributes values can be supplied using SnipCaller Attributes with normal style or named argument style.
    for example there is a snip defined using closure
    ```
    $box = function($title,$body){
        return ".box\n  .title $title\n  .body $body";
    };
    ```
    you can supply attribute values using anyone of there ways:
    ```
    @box(my_title my_body)
    @box(title="my_title" body="my_body")
    @box(title="my_title" my_body)
    @box(body="my_body" my_title)
    ```
    ruby style is also allowed:
    ```
    @box{"my_title","my_body"}
    @box{:title => "my_title",:body => "my_body"}
    ```

2. SnipCaller Attributes are parsed using Tag Attributes method, so SnipCaller Attributes syntax must observe Tag Attributes syntax.
    for example , it's illegal for html style
    ```
    @box("my title" "my body"}
    ```
    you should use ruby style
    ```
    @box{"my title" "my body"}
    ```
    source code: \MtHaml\More\Parser::parseSnipCallerAttributes

### snip file order when searching snip
if your set uses
```
'uses'=>array('1.php','2.php','3.php','1.php');
```
order of there uses is : 1.php > 3.php > 2.php,  snip file added later, priority level higher


But there is a file which be searched first of all, it's the haml file or the snip file where current parsed snip lives.

for example, a haml file shiped with some snips
```
@name
-#
  <?php
  $name='MtHamlMore';
```
comppiled output is always 'MtHamlMore' regardless of any snip files are supplied using 'uses'=>array().

example 2, a snip file
```
<?php
$__MtHamlMore_uses=__DIR__.'\common1.php;' . __DIR__.'\common2.php;';
$title=<<<S
%h1
    @@@
S;
$welcometitle=function($name){
    return "@title welcome $name";
};
?>
```
if you call snip "welcomtitle"
```
@welcometitle(Jim)
```
Output always is
```
<h1>
  welcome Jim
</h1>
```
no matter there is snip named title in common1.php or common2.php.


extra feature 1 : HtmlTag
-----
html tags can be used normally,not only
```
%div
  <p> hello </p>
```
which is supported by MtHaml, but also
```
<div>
    %p hello
</div>
```
This feature enables you to copy any html code into a haml file, only make sure code apply haml indent syntax.

code: '<div>' is parse as HtmlTag, see MtHaml\More\Parser::parseHtmlTag


extra feature 2 : prepare
-----
this is a feature whic has no relation with snip.

if you set options 'prepare'=>true , MtHamlMore will first change code
```
{% $address='http://program-think.blogspot.com';$name='scil' %}
%div my name is {= $name =}, i love this IT blog {= $address =} which is blocked by GFW
```
to
```
<?php $address='http://program-think.blogspot.com';$name='scil' ; ?>
%div my name is <?php echo $name ; ?>, i love this IT blog <?php echo $address ; ?> which is blocked by GFW
```
then, to
```
%div my name is scil, i love this IT blog http://program-think.blogspot.com which is blocked by GFW
```
this is normal haml code,which will be compiled to
```
<div>my name is scil, i love this it blog http://program-think.blogspot.com which is blocked by GFW</div>

```

notice: {% .. %} must monopolize one line, because regular expression uses '^' and '$'.

code: MtHaml\More\Environment::prepare


Development Rule
-----

1. no change to MtHaml.there's one except, MtHaml is hacked using composer.json only for whitespace removel (flag < and >).

2. place some variables at MoreEnv::options.
    1. code: MtHaml\More\MoreEnv::__construct
    2. haml file name also be placed in MoreEnv, so it's easy to track the process of calling snip, see MtHaml\More\Environment::parseSnip
    3. how to access MoreEnv object?
        1. use $this->currentMoreEnv in Environment
        2. SnipCaller::getEnv(). MoreEnv obj is attached with SnipCaller to make sure snip parsed using right use files.
            see: test/MtHaml/More/Tests/fixtures/environment/4.3.use snip from other snip file 2.test


Development memorandum
--------

1. Snip called by SnipCaller is invoked by visitor ApplySnip before render stage.  code : MtHaml\More\NodeVisitor\ApplySnip::enterSnipCaller

2. Snip called by InlineSnipCaller is invokded before parse stage.  code:
    1. MtHaml\More\Environment::parseInlineSnipCaller  (InlineSnipCaller is not parsed to an instance of Node for simplify)

3. SnipCaller/InlineSnipCaller can call snip located same file, because current file is added to snipfiles array. code:
    1. MtHaml\More\Snip\SnipHouse::getSnipAndFiles

4. Output of InlineSnipCaller and InlinePlaceholder are trimed, code :
    1. MtHaml\More\Snip\SnipHouse::parseInlineSnipCaller   rtrim(x,"\n")
    2. MtHaml\More\Snip\SnipHouse::renderSnipTree   rtrim(x,"\n") and ltrim(x), does this ltrim kill any spaces that not is indent space?

5. the only use of Log is to record the process of calling snip. code:
    1. MtHaml\More\Environment::__construct  $options['log'] ; $options['debug'](enable log)
    2. MtHaml\More\NodeVisitor\PhpRenderer::enterSnipCaller

6. how indent works well? code:
    1. MtHaml\More\Environment::__construct  $options['baseIndent']
    2. MtHaml\More\NodeVisitor\ApplySnip::__construct

7. "@snipName %h1 hello", "_ %h1 hello", "@@@ %h1 hello", there are legal.see code: MtHaml\More\Parser::parseSnipCaller, parsePlaceholder, parsePlaceholderValue, they all use " $this->parseStatement($buf)) "

8. How whitespace removel (< >) work ?
	1. hack node relation (getParent/getNextSibling/getPreviousSibling/getFirstChild/getLastChild) . maybe it's not jet mature. see : hackNodeAbstract.php, hackNestAbstract.php
	2. how to hack MtHaml file? use composer.json classmap

9. SnipCaller and Snip content, PlaceHolder and Placehoder value, they are abstracted by FirstInterface and SecondInterface for whitespace removel hack.

10. PlaceholderDefaultCaller does not implement FirstInterface for simpleness, '$node->addChilds' is used.
