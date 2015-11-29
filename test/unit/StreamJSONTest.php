<?php

class StreamJSONTest extends PHPUnit_Framework_TestCase {

    public function testStructure() {
        $this->assertClassHasAttribute('testable', PaulJulio\StreamJSON\StreamJSON::class);
    }
}