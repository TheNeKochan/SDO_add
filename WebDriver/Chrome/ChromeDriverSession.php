<?php


namespace WebDriver;
include_once __DIR__ . '/../DriverSession.php';
include_once __DIR__ . '/../Elements/HTMLNode.php';
use WebDriver\Elements\HTMLNode;

class ChromeDriverSession implements DriverSession {
    /**
     * @var string Session id
     */
    protected $session_id;

    /**
     * @var resource Curl session
     */
    protected $curl;

    /**
     * @var HTMLNode Document representing current page
     */
    protected $doc;

    /**
     * ChromeDriverSession constructor.
     * @param $session_id
     */
    public function __construct($session_id) {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_HEADER, false);
        curl_setopt($this->curl, CURLOPT_NOBODY, false);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        $this->session_id = $session_id;
        $this->doc = new HTMLNode($this);
    }

    public function Document(){
        return $this->doc;
    }

    public function Invoke($command, $args){
        curl_setopt($this->curl, CURLOPT_URL, 'http://localhost:9515/session/' . $this->session_id . '/' . $command);
        curl_setopt($this->curl, CURLOPT_POST, true);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($args));
        $response = curl_exec($this->curl);
        return json_decode($response, true);
    }

    public function Get($command){
        curl_setopt($this->curl, CURLOPT_URL, 'http://localhost:9515/session/' . $this->session_id . '/' . $command);
        curl_setopt($this->curl, CURLOPT_POST, false);
        $response = curl_exec($this->curl);
        return json_decode($response, true);
    }

    public function __destruct() {
        curl_setopt($this->curl, CURLOPT_URL, 'http://localhost:9515/session/' . $this->session_id);
        curl_setopt($this->curl, CURLOPT_POST, false);
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_exec($this->curl);
        curl_close($this->curl);
    }

    /**
     * Execute JavaScript string on page
     * @param $script string JavaScript string
     * @return mixed Execution result
     */
    public function ExecuteScript($script) {
        $args = array("args" => [], "script" => "{ " . $script . " }");
        $result = $this->Invoke('execute/sync', $args);
        return $result['value'];
    }

    /**
     * JavaScript fetch command
     * @param $url string Url to fetch
     * @param $options array Options
     * @return array Fetch result
     */
    public function Fetch($url, $options = null) {
        $fetch_result = array();

        $fetch_options = array("null" => null);
        if($options != null){
            if(array_key_exists('method', $options))
                $fetch_options['method'] = $options['method'];
            if(array_key_exists('headers', $options))
                $fetch_options['headers'] = $options['headers'];
            if(array_key_exists('body', $options))
                $fetch_options['body'] = $options['body'];
            if(array_key_exists('referrer', $options))
                $fetch_options['referrer'] = $options['referrer'];
            if(array_key_exists('referrerPolicy', $options))
                $fetch_options['referrerPolicy'] = $options['referrerPolicy'];
            if(array_key_exists('mode', $options))
                $fetch_options['mode'] = $options['mode'];
            if(array_key_exists('credentials', $options))
                $fetch_options['credentials'] = $options['credentials'];
            if(array_key_exists('cache', $options))
                $fetch_options['cache'] = $options['cache'];
            if(array_key_exists('redirect', $options))
                $fetch_options['redirect'] = $options['redirect'];
            if(array_key_exists('integrity', $options))
                $fetch_options['integrity'] = $options['integrity'];
            if(array_key_exists('keepalive', $options))
                $fetch_options['keepalive'] = $options['keepalive'];
        }

        $fetch = $this->ExecuteScript('let fr = await fetch("' . $url . '", ' . json_encode($fetch_options) . '); return {ok: fr.ok, redirected: fr.redirected, status: fr.status, url: fr.url, body: await fr.text()};');

        $fetch_result['ok'] = $fetch['ok'];
        $fetch_result['redirected'] = $fetch['redirected'];
        $fetch_result['status'] = $fetch['status'];
        $fetch_result['url'] = $fetch['url'];
        $fetch_result['body'] = $fetch['body'];

        return $fetch_result;
    }

    /**
     * Open URL
     * @param $url string URL
     * @return void
     */
    public function GoTo($url) {
        $this->Invoke('url', array('url' => $url));
    }

    /**
     * Closes session
     * @return void
     */
    public function close(){
        $this->__destruct();
    }
}