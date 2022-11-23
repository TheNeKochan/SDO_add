<?php


namespace WebDriver;

include_once __DIR__ . '/Elements/HTMLNode.php';

use WebDriver\Elements\HTMLNode;

interface DriverSession
{
    /**
     * Open URL
     * @param $url string URL
     * @return void
     */
    function GoTo($url);

    /**
     * Get HTML document for current session
     * @return HTMLNode Document
     */
    function Document();

    /**
     * Execute JavaScript string on page
     * @param $script string JavaScript string
     * @return mixed Execution result
     */
    function ExecuteScript($script);

    /**
     * JavaScript fetch command
     * @param $url string Url to fetch
     * @param $options array Options
     * @return array Fetch result
     */
    function Fetch($url, $options=null);

    /**
     * Invoke command for current session
     * @param $command string Command
     * @param $args array Arguments for command
     * @return array Command invocation result
     */
    function Invoke($command, $args);

    /**
     * Get field value for current session
     * @param $field string Field to be queried
     * @return array Field value
     */
    function Get($field);
}