<?php

namespace MtHamlMore\NodeVisitor;

use MtHaml\NodeVisitor\NodeVisitorAbstract;
use MtHamlMore\Node\SnipCaller;
use MtHamlMore\Node\PlaceholderValue;
use MtHamlMore\Node\Placeholder;

class VisitorAbstract extends NodeVisitorAbstract implements VisitorInterface
{
    public function enterSnipCaller(SnipCaller $node)
    {  }
    public function enterSnipCallerContent(SnipCaller $node)
    { }
    public function leaveSnipCallerContent(SnipCaller $node)
    { }
    public function enterSnipCallerChilds(SnipCaller $node)
    { }
    public function leaveSnipCallerChilds(SnipCaller $node)
    { }
    public function leaveSnipCaller(SnipCaller $node)
    {  }







    public function enterPlaceholderValue(PlaceholderValue $node){}
    public function enterPlaceholderValueContent(PlaceholderValue $node){}
    public function leavePlaceholderValueContent(PlaceholderValue $node){}
    public function enterPlaceholderValueChilds(PlaceholderValue $node){}
    public function leavePlaceholderValueChilds(PlaceholderValue $node){}
    public function leavePlaceholderValue(PlaceholderValue $node){}

    public function enterPlaceholder(Placeholder $node){}
    public function enterPlaceholderContent(Placeholder $node){}
    public function leavePlaceholderContent(Placeholder $node){}
    public function enterPlaceholderChilds(Placeholder $node){}
    public function leavePlaceholderChilds(Placeholder $node){}
    public function leavePlaceholder(Placeholder $node){}





}
