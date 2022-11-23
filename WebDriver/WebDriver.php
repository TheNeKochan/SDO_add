<?php


namespace WebDriver;


interface WebDriver {
    /**
     * Creates isolated session
     * @return DriverSession
     */
    public function CreateSession();
}