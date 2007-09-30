<?php
/**
 * Multi-Error Error aggregator
 *
 * This class is designed to be extended for specific use.  It codifies easy
 * ways of aggregating error conditions that don't necessarily require an exception
 * to be thrown, but do need an easy way to retrieve them.
 * 
 * Usage:
 * 
 * <code>
 * $multi = new PEAR2_MultiErrors();
 * $multi->E_WARNING[] = new Exception('test');
 * $multi->E_ERROR[] = new Exception('test 2');
 * foreach ($multi as $error) {
 *     echo $error;
 * }
 * foreach ($multi->E_WARNING as $error) {
 *     echo $error;
 * }
 * foreach ($multi->E_ERROR as $error) {
 *     echo $error;
 * }
 * if (count($multi->E_ERROR)) {
 *     throw new PEAR2_Exception('Failure to do something', $multi);
 * }
 * </code>
 * @copyright 2007 Gregory Beaver
 * @package PEAR2_MultiErrors
 * @license http://www.php.net/license/3_0.txt PHP License
 */
class PEAR2_MultiErrors implements Iterator, Countable, ArrayAccess {

    private $_allowedLevels = array('E_NOTICE' => 0, 'E_WARNING' => 1, 'E_ERROR' => 2);
    /**
     * Errors are stored in the order that they are declared
     * @var array
     */
    private $_errors = array();

    /**
     * To allow $this->E_WARNING[] = new BlahException;
     *
     * @var int
     */
    private $_requestedLevel = false;

    /**
     * Internal PEAR2_MultiError objects for error levels
     * @var array
     */
    private $_subMulti = array();

    /**
     * Parent PEAR2_MultiErrors for an error level tracker
     *
     * @var PEAR2_MultiErrors
     */
    private $_parent;

    public function __construct($mylevel = false,
                                array $allowed = array('E_NOTICE', 'E_WARNING', 'E_ERROR'),
                                PEAR2_MultiErrors $parent = null)
    {
        foreach ($allowed as $level) {
            if (!is_string($level) || strpos($level, 'E_') !== 0) {
                throw new PEAR2_MultiErrors_Exception('Invalid level ' . (string) $level);
            }
        }
        $this->_allowedLevels = array_flip($allowed);
        $this->_requestedLevel = $mylevel;
        if ($level) {
            $this->_parent = $parent;
        }
    }

    public function current()
    {
        return current($this->_errors);
    }

    public function key()
 	{
 	    return key($this->_errors);
 	}

 	public function next()
 	{
 	    return next($this->_errors);
 	}

 	public function rewind()
 	{
 	    return reset($this->_errors);
 	}

 	public function valid()
 	{
 	    return false !== current($this->_errors);
 	}

 	/**
 	 * Merge in errors from an existing PEAR2_MultiErrors
 	 * 
 	 * This also merges in any new error levels not supported in this instance.
 	 * @param PEAR2_MultiErrors $error
 	 */
 	public function merge(PEAR2_MultiErrors $error)
 	{
 	    $levels = $error->level;
 	    foreach ($error->levels as $level) {
 	        if (!isset($this->_allowedLevels[$level])) {
 	            $this->_allowedLevels[$level] = 1;
 	        }
 	        foreach ($error->$level as $e) {
 	            // we get fatal error if [] is put on $this->$level line
 	            $a = $this->$level;
 	            $a[] = $e;
 	        }
 	    }
 	}

 	public function count()
 	{
 	    return count($this->_errors);
 	}

 	public function offsetExists($offset)
 	{
 	    return isset($this->_errors[$offset]);
 	}

 	public function offsetGet ($offset)
 	{
 	    if (isset($this->_errors[$offset])) {
 	        return $this->_errors[$offset];
 	    }
 	    return null;
 	}

 	public function offsetSet ($offset, $value)
 	{
 	    if ($offset === null && !$this->_requestedLevel &&
 	          $value instanceof PEAR2_MultiErrors ) {
 	        $this->merge($value);
 	        return;
 	    }
 	    if (!($value instanceof Exception)) {
 	        throw new PEAR2_MultiErrors_Exception('offsetSet: $value is not an Exception object');
 	    }
 	    if ($this->_requestedLevel) {
     	    if ($offset === null) {
     	        // called with $a->E_BLAH[] = new Exception('hi');
     	        $offset = count($this->_errors);
     	    }
     	    if (!is_int($offset)) {
     	        throw new PEAR2_MultiErrors_Exception('offsetSet: $offset is not an integer');
     	    }
     	    $this->_errors[$offset] = $value;
 	        $this->_parent[$this->_requestedLevel . '-' . $offset] = $value;
 	    } else {
 	        if (!is_string($offset)) {
 	            throw new PEAR2_MultiErrors_Exception('Cannot add an error directly ' .
 	                'to a PEAR2_MultiErrors with $a[] = new Exception, use an ' .
 	                ' E_* constant like $a->E_WARNING[] = new Exception');
 	        }
 	        $offset = explode('-', $offset);
 	        $level = $offset[0];
 	        $offset = $offset[1];
 	        // this is called when the "$this->_parent[] = $value" line is executed.
 	        if (!isset($this->_subMulti[$level]) ||
 	              $this->_subMulti[$level][$offset] !== $value) {
                // must be in a child or it'll throw off the whole thingy
 	            throw new PEAR2_MultiErrors_Exception('Cannot add an error directly ' .
 	                'to a PEAR2_MultiErrors with $a[] = new Exception, use an ' .
 	                ' E_* constant like $a->E_WARNING[] = new Exception');
 	        }
 	        $this->_errors[] = $value;
 	    }
 	}

 	public function offsetUnset ($offset)
 	{
 	    if (isset($this->_errors[$offset])) {
 	        unset($this->_errors[$offset]);
 	    }
 	}

 	public function __get($level)
 	{
 	    if ($level === 'levels') {
 	        return $this->_allowedLevels;
 	    }
 	    if (!count($this->_allowedLevels)) {
 	        throw new PEAR2_MultiErrors_Exception('Cannot nest requests ' .
 	          '(like $multi->E_WARNING->E_ERROR[] = new Exception(\'\');)');
 	    }
 	    if (isset($this->_allowedLevels[$level])) {
 	        if (!isset($this->_subMulti[$level])) {
     	        $this->_subMulti[$level] = new PEAR2_MultiErrors($level,
     	          array(), $this);
 	        }
 	        return $this->_subMulti[$level];
 	    }
 	    throw new PEAR2_MultiErrors_Exception('Requested error level must be one of ' .
 	      implode(', ', $this->_allowedLevels));
 	}

 	public function toArray()
 	{
 	    return $this->_errors;
 	}
}
?>