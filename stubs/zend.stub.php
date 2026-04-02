<?php

class Zend_Config
{
    /** @return array<string, mixed> */
    public function toArray() {}
}

class Zend_Log
{
    const EMERG   = 0;
    const ALERT   = 1;
    const CRIT    = 2;
    const ERR     = 3;
    const WARN    = 4;
    const NOTICE  = 5;
    const INFO    = 6;
    const DEBUG   = 7;

    public function __construct($writer = null) {}
    public function addFilter($filter) {}
    public function log($message, $priority, $extras = array()) {}
}

class Zend_Log_Writer_Stream
{
    public function __construct($streamOrUrl, $mode = 'a', $logSeparator = null) {}
}

class Zend_Log_Filter_Priority
{
    public function __construct($priority, $operator = null) {}
}
