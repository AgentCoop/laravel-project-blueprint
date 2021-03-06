<?php

namespace App\Component\Exception;

/**
 *
 */
abstract class AbstractException extends \Exception
{
    private $type;

    /**
     * @return string
     */
    final public function getType()
    {
        return $this->type;
    }

    /**
     * @return static
     */
    final public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return $this
     */
    public static function create($errMessage, $errCode)
    {
        return new static($errMessage, $errCode);
    }

    /**
     * @return $this
     */
    public static function createFromException(\Exception $e)
    {
        return new static($e->getMessage(), $e->getCode(), $e);
    }

    /**
     *
     */
    abstract public function _throw();
}
