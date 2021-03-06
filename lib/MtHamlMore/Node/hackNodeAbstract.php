<?php

namespace MtHaml\Node;

use MtHaml\NodeVisitor\NodeVisitorInterface;
use MtHamlMore\Node\FirstInterface;
use MtHamlMore\Node\SecondInterface;

abstract class NodeAbstract
{
    private $position;
    private $parent;
    private $nextSibling;
    private $previousSibling;

    public function __construct(array $position)
    {
        $this->position = $position;
    }

    public function getPosition()
    {
        return $this->position;
    }

    public function getLineno()
    {
        return $this->position['lineno'];
    }

    public function getColumn()
    {
        return $this->position['column'];
    }

    protected function setParent(NodeAbstract $parent = null)
    {
        $this->parent = $parent;
    }

    public function hasParent()
    {
        return null !== $this->parent;
    }

    //hack
    public function getParent()
    {
//        return $this->parent;
        $node=$this->parent;
        try{
             while ($node instanceof SecondInterface){
                $node=$node->getFirst()->parent;
             }
        }catch(\Exception $e) {}
        return $node;
    }

    abstract public function getNodeName();

    abstract public function accept(NodeVisitorInterface $visitor);

    protected function setNextSibling(NodeAbstract $node = null)
    {
        $this->nextSibling = $node;
    }

    //hack
    /*
     * CASE 1: OUTER flag
     * .box has to know his next sibling
     * haml:
     *      .box
 *          @content
     * snip:
     *      $content=<<<S
            %p> my name is ivy
            S;
     */
    public function getNextSibling()
    {
//        return $this->nextSibling;
        $node=$this;
        $next=$this->_next($this);
        // if the last child, should check if in a SecondInterface parent
        while (is_null($next) && (($parent=$node->parent) instanceof SecondInterface) && $parent->hasFirst()){
            $node=$parent->getFirst();
            $next=$this->_next($node);
        }
        return $next;
    }
    protected function _next($node)
    {
        $next=$node->nextSibling;
        while($next instanceof FirstInterface and $next->hasSecond()){
            $childs = $next->getSecond()->getChilds();
            $next=$childs[0];
        }
        return $next;
    }

    protected function setPreviousSibling(NodeAbstract $node = null)
    {
        $this->previousSibling = $node;
    }

    //hack
    public function getPreviousSibling()
    {
//        return $this->previousSibling;
        $node=$this;
        $pre=$this->_pre($this);
        // if the first child, should check if in a SecondInterface parent
        while (is_null($pre) && (($parent=$node->parent) instanceof SecondInterface) && $parent->hasFirst()){
            $node=$parent->getFirst();
            $pre=$this->_pre($node);
        }
        return $pre;
    }
    protected function _pre($node)
    {
        $pre=$node->previousSibling;
        while($pre instanceof FirstInterface && $pre->hasSecond()){
            $childs = $pre->getSecond()->getChilds();
            $pre=end($childs);
        }
        return $pre;

    }

    public function isConst()
    {
        return false;
    }
}

