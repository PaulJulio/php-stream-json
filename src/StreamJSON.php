<?php
namespace PaulJulio\StreamJSON;

final class StreamJSON implements \Psr\Http\Message\StreamInterface, \ArrayAccess {
    /* todo: most likely breaks horribly for multi-byte character sets */

    /* @var resource */
    private $stream;
    /* @var int the length of the internal json string */
    private $flen;
    /* @var int the current pointer position */
    private $cursor = 0;
    /* @var array a dictionary of offsets to their cursor position */
    private $offsetList = [];
    /* @var string|null the key used for variable assignment, or null if not used */
    private $asVariableKey = null;

    public function __construct() {
        $this->stream = $this->createStream();
        fwrite($this->stream, '{}');
        $this->flen = 2;
        $this->seek(1);
    }

    protected function createStream() {
        $fn = tempnam(sys_get_temp_dir(), 'stream');
        return fopen('php://temp/' . $fn, 'w');
    }
    /**
     * Reads all data from the stream into a string, from the beginning to end.
     *
     * This method MUST attempt to seek to the beginning of the stream before
     * reading data and read the stream until the end is reached.
     *
     * Warning: This could attempt to load a large amount of data into memory.
     *
     * This method MUST NOT raise an exception in order to conform with PHP's
     * string casting operations.
     *
     * @see http://php.net/manual/en/language.oop5.magic.php#object.tostring
     * @return string
     */
    public function __toString() {
        $this->rewind();
        $this->cursor = $this->flen;
        return fread($this->stream, $this->flen);
    }
    /**
     * Closes the stream and any underlying resources.
     *
     * @return void
     */
    public function close() {
        fclose($this->stream);
        $this->stream = null;
    }
    /**
     * Separates any underlying resources from the stream.
     *
     * After the stream has been detached, the stream is in an unusable state.
     *
     * @return resource|null Underlying PHP stream, if any
     */
    public function detach() {
        $stream = $this->stream;
        $this->stream = null;
        $this->flen = null;
        $this->offsetList = null;
        $this->cursor = null;
        return $stream;
    }
    /**
     * Get the size of the stream if known.
     *
     * @return int|null Returns the size in bytes if known, or null if unknown.
     */
    public function getSize() {
        return $this->flen;
    }
    /**
     * Returns the current position of the file read/write pointer
     *
     * @return int Position of the file pointer
     * @throws \RuntimeException on error.
     */
    public function tell() {
        if (!isset($this->stream)) {
            throw new \RuntimeException('No stream, no cursor position to report.');
        }
        return $this->cursor;
    }
    /**
     * Returns true if the stream is at the end of the stream.
     *
     * @return bool
     */
    public function eof() {
        return ($this->cursor == $this->flen);
    }
    /**
     * Returns whether or not the stream is seekable.
     *
     * @return bool
     */
    public function isSeekable() {
        return isset($this->stream);
    }
    /**
     * Seek to a position in the stream.
     *
     * @link http://www.php.net/manual/en/function.fseek.php
     * @param int $offset Stream offset
     * @param int $whence Specifies how the cursor position will be calculated
     *     based on the seek offset. Valid values are identical to the built-in
     *     PHP $whence values for `fseek()`.  SEEK_SET: Set position equal to
     *     offset bytes SEEK_CUR: Set position to current location plus offset
     *     SEEK_END: Set position to end-of-stream plus offset.
     * @throws \RuntimeException on failure.
     */
    public function seek($offset, $whence = SEEK_SET) {
        if (!$this->isSeekable()) {
            throw new \RuntimeException('No stream to seek');
        }
        fseek($this->stream, $offset, $whence);
        switch ($whence) {
            case SEEK_SET:
                $this->cursor = $offset;
                break;
            case SEEK_CUR:
                $this->cursor += $offset;
                break;
            case SEEK_END:
                $this->cursor = $this->flen + $offset;
                break;
            default:
                throw new \RuntimeException('Incorrect constant passed for whence');
        }
    }
    /**
     * Seek to the beginning of the stream.
     *
     * If the stream is not seekable, this method will raise an exception;
     * otherwise, it will perform a seek(0).
     *
     * @see seek()
     * @link http://www.php.net/manual/en/function.fseek.php
     * @throws \RuntimeException on failure.
     */
    public function rewind() {
        $this->seek(0);
    }
    /**
     * Returns whether or not the stream is writable.
     *
     * @return bool
     */
    public function isWritable() {
        return isset($this->stream);
    }
    /**
     * Write data to the stream via an insert.
     *
     * This stream is meant to be used as an in-memory json object, overwriting a portion of the stream that has
     * already been written does not appear to have a meaningful use case.
     *
     * @param string $string The string that is to be written.
     * @return int Returns the number of bytes written to the stream.
     * @throws \RuntimeException on failure.
     */
    public function write($string) {
        if (!$this->isWritable()) {
            throw new \RuntimeException('Stream is not writable');
        }
        if (!$this->eof()) {
            // copy the data after the cursor to another stream so that it can be written back
            $tempStream = $this->createStream();
            stream_copy_to_stream($this->stream, $tempStream);
            // reset cursors to their positions prior to the reading
            fseek($tempStream, 0);
            $this->seek($this->cursor);
        }
        $retval = fwrite($this->stream, $string);
        if ($retval === false) {
            throw new \RuntimeException('Write failed (fwrite returned false)');
        }
        $this->flen += $retval;
        $this->cursor += $retval;
        if (isset($tempStream)) {
            stream_copy_to_stream($tempStream, $this->stream);
            fclose($tempStream);
            $this->seek($this->cursor);
        }
        return $retval;
    }
    /**
     * Returns whether or not the stream is readable.
     *
     * @return bool
     */
    public function isReadable() {
        return isset($this->stream);
    }
    /**
     * Read data from the stream.
     *
     * @param int $length Read up to $length bytes from the object and return
     *     them. Fewer than $length bytes may be returned if underlying stream
     *     call returns fewer bytes.
     * @return string Returns the data read from the stream, or an empty string
     *     if no bytes are available.
     * @throws \RuntimeException if an error occurs.
     */
    public function read($length) {
        if (!$this->isReadable()) {
            throw new \RuntimeException('Stream is not readable');
        }
        $this->cursor += $length;
        if ($this->cursor > $this->flen) {
            $this->cursor = $this->flen;
        }
        return fread($this->stream, $length);
    }
    /**
     * Returns the remaining contents in a string
     *
     * @return string
     * @throws \RuntimeException if unable to read or an error occurs while
     *     reading.
     */
    public function getContents() {
        return $this->read($this->flen - $this->cursor);
    }
    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     *
     * The keys returned are identical to the keys returned from PHP's
     * stream_get_meta_data() function.
     *
     * @link http://php.net/manual/en/function.stream-get-meta-data.php
     * @param string $key Specific metadata to retrieve.
     * @return array|mixed|null Returns an associative array if no key is
     *     provided. Returns a specific key value if a key is provided and the
     *     value is found, or null if the key is not found.
     */
    public function getMetadata($key = null) {
        $md = stream_get_meta_data($this->stream);
        if (isset($key)) {
            if (isset($md[$key])) {
                return $md[$key];
            }
            return null;
        }
        return $md;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists ($offset) {
        return (isset($this->offsetList[$offset]));
    }

    /**
     * @param mixed $offset
     * @return mixed
     * @throws \Exception if stream is not readable
     */
    public function offsetGet ($offset) {
        if (!$this->isReadable()) {
            throw new \Exception('Stream is not readable');
        }
        if ($this->offsetExists($offset)) {
            $joffset = json_encode($offset);
            $this->seek($this->offsetList[$offset]['cursor']);
            $raw = $this->read($this->offsetList[$offset]['length']);
            $json = substr($raw, strlen($joffset) + 1);
            return json_decode($json, $this->offsetList[$offset]['assoc']);
        }
        return null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value) {
        $this->offsetUnset($offset);
        $this->seek(-1, SEEK_END);
        $joffset = json_encode($offset);
        $jvalue = json_encode($value);
        if (count($this->offsetList) > 0) {
            $this->write(',');
        }
        $priorCursor = $this->cursor;
        $written = $this->write($joffset . ':' . $jvalue);
        $this->offsetList[$offset] = ['cursor' => $priorCursor, 'length' => $written, 'assoc' => is_array($value)];
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset) {
        if ($this->offsetExists($offset) && count($this->offsetList) === 1 && !isset($this->asVariableKey)) {
            // resetting the only set key, just reset the stream
            fclose($this->stream);
            $this->offsetList = [];
            $this->stream = $this->createStream();
            fwrite($this->stream, '{}');
            $this->flen = 2;
        } elseif ($this->offsetExists($offset)) {
            // copy the current stream into another stream that contains all but the rewritten info
            $newStream = $this->createStream();
            fwrite($newStream, '{');
            $this->flen = 1;
            $this->seek(1);
            $count = 0;
            foreach ($this->offsetList as $currentOffset => $info) {
                if ($currentOffset == $offset) {
                    $this->seek($info['length'] + 1, SEEK_CUR);
                    continue;
                }
                if ($count++ > 0) {
                    fwrite($newStream, ',');
                    $this->seek(1, SEEK_CUR);
                    ++$this->flen;
                }
                stream_copy_to_stream($this->stream, $newStream, $info['length']);
                $this->flen += $info['length'];
            }
            fwrite($newStream, '}');
            ++$this->flen;
            unset($this->offsetList[$offset]);
            fclose($this->stream);
            $this->stream = $newStream;
        }
    }

    /**
     * @param $key
     */
    public function asVariable($key) {
        if (count($this->offsetList) === 0) {
            // empty list, just quickly recreate the stream
            fclose($this->stream);
            $this->stream = $this->createStream();
            if (isset($key)) {
                fwrite($this->stream, $key . '={}');
                $this->flen = strlen($key) + 3;
            } else {
                fwrite($this->stream, '{}');
                $this->flen = 2;
            }
        } elseif (isset($key) || isset($this->asVariableKey)) {
            /* need to get the contents of the current stream after the key and put them after the new key,
                then adjust the cursor references */
            if (isset($key)) {
                if (!isset($this->asVariableKey)) {
                    $key .= '=';
                }
                $keyLen = strlen($key);
            } else {
                $keyLen = 0;
            }
            if (isset($this->asVariableKey)) {
                $asVarKeyLen = strlen($this->asVariableKey);
            } else {
                $asVarKeyLen = 0;
            }
            if (isset($this->asVariableKey) && !isset($key)) {
                // account for the fact that we are trimming the equals sign
                $asVarKeyLen++;
            }
            $adjust = $keyLen - $asVarKeyLen;
            $this->seek($asVarKeyLen);
            $newStream = $this->createStream();
            fwrite($newStream, $key);
            stream_copy_to_stream($this->stream, $newStream);
            if ($asVarKeyLen != $keyLen) {
                foreach ($this->offsetList as $offset => $info) {
                    $this->offsetList[$offset]['cursor'] += $adjust;
                }
                $this->flen += $adjust;
            }
            $this->stream = $newStream;
        }
        $this->asVariableKey = $key;
    }
}
