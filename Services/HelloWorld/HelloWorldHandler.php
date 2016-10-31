<?php
/**
 * HelloWorldHandler.php
 *
 * @author Janson
 * @create 2016-10-28
 */

namespace Services\HelloWorld;

class HelloWorldHandler implements HelloWorldIf {
    public function sayHello($name) {
        return "Hello $name";
    }
}