<?php


namespace WebDriver\Elements;

include_once __DIR__ . '/../DriverSession.php';

use Exception;
use WebDriver\DriverSession;

class HTMLNode {
    /**
     * @var string Node ID
     */
    protected $nodeId;

    /**
     * @var DriverSession Connection
     */
    public $connection;

    public function __construct($connection, $nodeId = null) {
        $this->connection = $connection;
        $this->nodeId = is_string($nodeId) ? $nodeId : null;
    }

    /**
     * @param $id string
     * @return HTMLNode|null
     */
    public function getElementById($id) {
        $args = array("using" => "css selector", "value" => '#' . $id);
        return $this->getElementByArgs($args);
    }

    /**
     * Get all nodes with specified tag
     * @param $tag string Tag name
     * @return HTMLNode[] All <tag> nodes
     */
    public function getElementsByTagName($tag) {
        $args = array("using" => "tag name", "value" => $tag);
        return $this->getElementsByArgs($args);
    }

    /**
     * Get all nodes with specified class
     * @param $class string CSS class name
     * @return HTMLNode[] All nodes that have specified class
     */
    public function getElementsByClassName($class) {
        $args = array("using" => "css selector", "value" => '.' . $class);
        return $this->getElementsByArgs($args);
    }

    /**
     * Get single node by CSS selector
     * @param $selector string CSS selector
     * @return HTMLNode|null Node matches selector
     */
    public function querySelector($selector) {
        $args = array("using" => "css selector", "value" => $selector);
        return $this->getElementByArgs($args);
    }

    /**
     * Get nodes by CSS selector
     * @param $selector string CSS selector
     * @return HTMLNode[] All nodes match selector
     */
    public function querySelectorAll($selector) {
        $args = array("using" => "css selector", "value" => $selector);
        return $this->getElementsByArgs($args);
    }

    /**
     * Get innerHTML value
     * @return string Text value
     */
    public function innerText() {
        if($this->nodeId == null)
            return null;
        $value = $this->connection->Get("element/" . $this->nodeId . "/text");
        return $value['value'];
    }

    /**
     * Get attribute value
     * @param $attribute string Attribute name
     * @return string
     */
    public function getAttribute($attribute) {
        if($this->nodeId == null)
            return null;
        $value = $this->connection->Get("element/" . $this->nodeId . "/attribute/" . $attribute);
        return $value['value'];
    }

    /**
     * Get tag name
     * @return string
     */
    public function tagName(){
        if($this->nodeId == null)
            return null;
        $value = $this->connection->Get("element/" . $this->nodeId . "/name");
        return strtolower($value['value']);
    }

    /**
     * @param array $args
     * @return HTMLNode[]
     */
    protected function getElementsByArgs(array $args) {
        if ($this->nodeId == null) {
            $elements = $this->connection->Invoke("elements", $args);
        } else {
            $elements = $this->connection->Invoke("element/" . $this->nodeId . "/elements", $args);
        }
        if ($elements['value'] == null)
            return array();
        $nodes = array();
        $i = 0;
        foreach (array_values($elements['value']) as $element) {
            $nodes[$i++] = new HTMLNode($this->connection, array_values($element)[0]);
        }
        return $nodes;
    }

    /**
     * @param array $args
     * @return HTMLNode|null
     */
    protected function getElementByArgs(array $args) {
        if ($this->nodeId == null) {
            $element = $this->connection->Invoke("element", $args);
        } else {
            $element = $this->connection->Invoke("element/" . $this->nodeId . "/element", $args);
        }
        if ($element['value'] == null)
            return null;
        return new HTMLNode($this->connection, array_values($element['value'])[0]);
    }


}