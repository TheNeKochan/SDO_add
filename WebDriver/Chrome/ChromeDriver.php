<?php


namespace WebDriver;

use RuntimeException;

include_once __DIR__ . '/../WebDriver.php';
include_once __DIR__ . '/ChromeDriverSession.php';

class ChromeDriver implements WebDriver {
    /**
     * @var resource Curl session
     */
    protected $curl;

    /**
     * @var integer ChromeDriver pid
     */
    protected $driver_pid;

    /**
     * @var integer ChromeDriver pid
     */
    protected $driver_handle;

    /**
     * ChromeDriver constructor.
     * @param $path_to_driver string Path to chromedriver executable
     */
    public function __construct($path_to_driver) {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_HEADER, false);
        curl_setopt($this->curl, CURLOPT_NOBODY, false);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        $this->driver_handle = popen("sh -c 'echo $$; exec " . $path_to_driver  . " --port=9515'", 'r'); //> /dev/null'
        if(!$this->driver_handle)
            throw new RuntimeException();
        $this->driver_pid = intval(fread($this->driver_handle, 5));
    }

    /**
     * Creates isolated session
     * @param $headless boolean Run chrome in headless mode
     * @return DriverSession
     */
    public function CreateSession($headless = false) {
        curl_setopt($this->curl, CURLOPT_URL, 'http://localhost:9515/session');
        curl_setopt($this->curl, CURLOPT_POST, true);
        $caps = array("null" => null);
        if($headless) {
            $caps["alwaysMatch"] = array(
                "goog:chromeOptions" => array(
                    "args" => array(
                        "--headless",
                        "--remote-debugging-port=9222"
                    )
                )
            );
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode(array("capabilities" => $caps)));
            $response = curl_exec($this->curl);
            $result = json_decode($response, true);
            if($result['value'] == null)
                throw new RuntimeException();
            $session = new ChromeDriverSession($result['value']['sessionId']);
            $ua = $session->ExecuteScript('return navigator.userAgent;');
            $ua = str_replace('Headless', '', $ua);
            $session->close();
            $caps["alwaysMatch"]["goog:chromeOptions"]["args"][2] = '--user-agent=' . $ua;
        }
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode(array("capabilities" => $caps)));
        $response = curl_exec($this->curl);
        $result = json_decode($response, true);
        if($result['value'] == null)
            throw new RuntimeException();
        sleep(1);
        return new ChromeDriverSession($result['value']['sessionId']);
    }

    public function __destruct()
    {
        posix_kill($this->driver_pid, 2);
    }

    public function close(){
        $this->__destruct();
    }
}