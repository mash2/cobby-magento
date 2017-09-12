<?php
/*
 * Copyright 2013 mash2 GbR http://www.mash2.com
 *
 * ATTRIBUTION NOTICE
 * This work is derived from Andreas von Studnitz
 * Original title AvS_FastSimpleImport
 * The work can be found https://github.com/avstudnitz/AvS_FastSimpleImport
 *
 * ORIGINAL COPYRIGHT INFO
 *
 * category   AvS
 * package    AvS_FastSimpleImport
 * author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */

/**
 * Array adapter
 */
class Mash2_Cobby_Model_ArrayAdapter implements SeekableIterator
{
    /**
     * Current array position
     *
     * @var int
     */
    protected $_position = 0;

    /**
     * The data for each row.
     *
     * @var array
     */
    protected $_array = array();

    /**
     * Take the Iterator to position $position
     * Required by interface SeekableIterator.
     *
     * @param int $position the position to seek to
     * @throws OutOfBoundsException
     */
    public function seek($position)
    {
        $this->_position = $position;

        if (!$this->valid()) {
            throw new OutOfBoundsException("invalid seek position ($position)");
        }
    }

    /**
     * Constructor.
     *
     * @param array $data
     */
    public function __construct($data)
    {
        $this->_array = $data;
        $this->_position = 0;
    }

    /**
     * Rewind the Iterator to the first element.
     * Similar to the reset() function for arrays in PHP.
     * Required by interface Iterator.
     */
    public function rewind()
    {
        $this->_position = 0;
    }

    /**
     * Return the current element.
     * Similar to the current() function for arrays in PHP
     * Required by interface Iterator.
     *
     * @return mixed
     */
    public function current()
    {
        return $this->_array[$this->_position];
    }

    /**
     * Return the identifying key of the current element.
     * Similar to the key() function for arrays in PHP.
     * Required by interface Iterator.
     *
     * @return int
     */
    public function key()
    {
        return $this->_position;
    }

    /**
     * Move forward to next element.
     * Similar to the next() function for arrays in PHP.
     * Required by interface Iterator.
     *
     * @return void
     */
    public function next()
    {
        ++$this->_position;
    }

    /**
     * Check if there is a current element after calls to rewind() or next().
     * Used to check if we've iterated to the end of the collection.
     * Required by interface Iterator.
     *
     * @return bool False if there's nothing more to iterate over
     */
    public function valid()
    {
        return isset($this->_array[$this->_position]);
    }

    /**
     * Retrieve column names
     *
     * @return array
     */
    public function getColNames()
    {
        $colNames = array();
        foreach ($this->_array as $row) {
            foreach (array_keys($row) as $key) {
                if (!is_numeric($key) && !isset($colNames[$key])) {
                    $colNames[$key] = $key;
                }
            }
        }
        return $colNames;
    }

    /**
     * Set the value for key
     *
     * @param $key
     * @param $value
     */
    public function setValue($key, $value)
    {
        if (!$this->valid()) return;
        $this->_array[$this->_position][$key] = $value;
    }
}
