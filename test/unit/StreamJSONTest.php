<?php

class StreamJSONTest extends PHPUnit_Framework_TestCase {

    /* @var \PaulJulio\StreamJSON\StreamJSON */
    private $sj;

    public function setUp() {
        parent::setUp();
        $this->createInstance();
    }

    protected function createInstance() {
        $this->sj = new \PaulJulio\StreamJSON\StreamJSON();
    }

    public function testStructure() {
        $this->assertClassHasAttribute('stream', PaulJulio\StreamJSON\StreamJSON::class);
        $this->assertClassHasAttribute('flen', PaulJulio\StreamJSON\StreamJSON::class);
        $this->assertClassHasAttribute('cursor', PaulJulio\StreamJSON\StreamJSON::class);
        $this->assertClassHasAttribute('offsetList', PaulJulio\StreamJSON\StreamJSON::class);
    }

    public function testEmpty() {
        $this->assertEquals(1, $this->sj->tell(), 'empty object pointer is at correct position');
        $this->assertEquals('{}', (string) $this->sj, 'empty object casts to string as empty json object');
        $this->assertTrue($this->sj->eof(), 'empty object is at end of file after read');
        $this->assertEquals(2, $this->sj->getSize(), 'empty object has correct size');
        $this->sj->rewind();
        $this->assertEquals(0, $this->sj->tell(), 'empty object pointer is at correct position after rewind');
        $fp = $this->sj->detach();
        $this->assertEquals('{}', fread($fp, 2), 'reading results of detach results in the json empty object');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testDetachExceptionTell() {
        $this->assertTrue($this->sj->isSeekable());
        $this->sj->detach();
        $this->assertFalse($this->sj->isSeekable());
        $this->sj->tell();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testSeekEnd() {
        $this->sj->seek(0, SEEK_END);
        $this->assertEquals(2, $this->sj->tell());
        $this->createInstance();
        $this->sj->seek(-1, SEEK_END);
        $this->assertEquals(1, $this->sj->tell());
        $fp = $this->sj->detach();
        $this->assertEquals('}', fread($fp,1));
        $this->sj->seek(0, SEEK_END);
    }
    /**
     * @expectedException \RuntimeException
     */
    public function testSeekCur() {
        $this->sj->seek(1, SEEK_CUR);
        $this->assertEquals(2, $this->sj->tell());
        $this->sj->seek(-1, SEEK_CUR);
        $this->assertEquals(1, $this->sj->tell());
        $fp = $this->sj->detach();
        $this->assertEquals('}', fread($fp,1));
        $this->sj->seek(0, SEEK_CUR);
    }

    public function testIsWritable() {
        $this->assertTrue($this->sj->isWritable());
        $this->sj->detach();
        $this->assertFalse($this->sj->isWritable());
    }

    public function testIsReadable() {
        $this->assertTrue($this->sj->isReadable());
        $this->sj->detach();
        $this->assertFalse($this->sj->isReadable());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testWrite() {
        $compare = '"foo":"bar"';
        $this->sj->seek(1);
        $retval = $this->sj->write($compare);
        $this->assertEquals(strlen($compare), $retval);
        $this->assertEquals(1 + $retval, $this->sj->tell());
        $compare = (string) $this->sj;
        $this->assertEquals('{"foo":"bar"}', $compare);
        $this->assertEquals(strlen($compare), $this->sj->getSize());
        $this->sj->detach();
        $this->sj->write('failure');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testRead() {
        $compare = '"foo":"bar"';
        $this->sj->seek(1);
        $this->sj->write($compare);
        $this->sj->seek(1);
        $val = $this->sj->read(strlen($compare));
        $this->assertEquals($compare, $val);
        $this->assertEquals(1 + strlen($compare), $this->sj->tell());
        $this->sj->seek(0);
        $this->sj->read(50);
        $this->assertEquals($this->sj->getSize(), $this->sj->tell());
        $this->sj->detach();
        $this->sj->read(1);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGetContents() {
        $compare = '"foo":"bar"';
        $this->sj->seek(1);
        $this->sj->write($compare);
        $this->sj->seek(-6, SEEK_END);
        $this->assertEquals('"bar"}', $this->sj->getContents());
        $this->sj->detach();
        $this->sj->getContents();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testRewind() {
        $this->sj->write('test');
        $this->sj->rewind();
        $this->assertEquals(0, $this->sj->tell());
        $this->sj->detach();
        $this->sj->rewind();
    }

    public function testOffsetExists() {
        $this->assertFalse($this->sj->offsetExists('test'));
        $this->sj->offsetSet('test', 'testvalue');
        $this->assertTrue($this->sj->offsetExists('test'));
        $this->sj->detach();
        $this->assertFalse($this->sj->offsetExists('test'));
    }

    /**
     * @expectedException \Exception
     */
    public function testOffsetSet() {
        $this->sj->offsetSet('test', 'value1');
        $this->assertEquals(json_encode(['test'=>'value1']), (string) $this->sj);
        $this->sj->offsetSet('test', 'value');
        $this->assertEquals(json_encode(['test'=>'value']), (string) $this->sj);
        $this->sj->offsetSet('foo', 'bar');
        $this->assertEquals(json_encode(['test'=>'value','foo'=>'bar']), (string) $this->sj);
        $this->sj->offsetSet('fizz','buzz');
        $this->assertEquals(json_encode(['test'=>'value','foo'=>'bar','fizz'=>'buzz']), (string) $this->sj);
        $this->sj->offsetSet('foo', 'barbaz');
        $this->assertEquals(json_encode(['test'=>'value','fizz'=>'buzz','foo'=>'barbaz']), (string) $this->sj);
        $this->assertEquals(strlen($this->sj), $this->sj->getSize());
        $this->sj->detach();
        $this->sj->offsetSet('exception', 'exception');
    }

    /**
     * @expectedException \Exception
     */
    public function testOffsetGet() {
        $this->sj->offsetSet('test','value');
        $this->assertEquals('value', $this->sj->offsetGet('test'));
        $this->sj->offsetSet('test2', ['a'=>1,2]);
        $this->assertEquals(['a'=>1,2], $this->sj->offsetGet('test2'));
        $this->sj->offsetSet('test', json_decode(json_encode(['b'=>2,3,1.2])));
        $this->assertEquals(json_decode(json_encode(['b'=>2,3,1.2])), $this->sj->offsetGet('test'));
        $this->assertNull($this->sj->offsetGet('null'));
        $this->sj->detach();
        $this->assertEquals('test', $this->sj->offsetGet('exception'));
    }

    public function testAsVariable() {
        $this->sj->asVariable('test');
        $this->assertEquals('test={}', (string) $this->sj);
        $this->sj->offsetSet('key', 'label');
        $this->assertEquals('test='.json_encode(['key'=>'label']), (string) $this->sj);
        $this->assertEquals('label', $this->sj->offsetGet('key'));
        $this->sj->asVariable('diff');
        $this->assertEquals('diff='.json_encode(['key'=>'label']), (string) $this->sj);
        $this->assertEquals('label', $this->sj->offsetGet('key'));
        $this->sj->asVariable('nowlonger');
        $this->assertEquals('nowlonger='.json_encode(['key'=>'label']), (string) $this->sj);
        $this->assertEquals('label', $this->sj->offsetGet('key'));
        $this->sj->asVariable('shorter');
        $this->assertEquals('shorter='.json_encode(['key'=>'label']), (string) $this->sj);
        $this->assertEquals('label', $this->sj->offsetGet('key'));
        $this->sj->asVariable(null);
        $this->assertEquals(json_encode(['key'=>'label']), (string) $this->sj);
        $this->assertEquals('label', $this->sj->offsetGet('key'));
    }
}