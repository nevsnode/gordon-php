<?php

namespace Gordon;

class Task
{

    /**
    * The Task-type of this instance.
    *
    * @var string
    */
    protected $type = '';

    /**
    * The Task properties of this instance.
    *
    * @var array
    */
    protected $task;

    /**
    * Creates a new Task instance.
    *
    * @return \Gordon\Task
    */
    public function __construct($type = '')
    {
        $this->type = $type;
        $this->task = array(
            'Args' => array(),
            'ErrorMessage'  => '',
        );
        return $this;
    }

    /**
    * Set the type of this task.
    *
    * @param string $type
    * @return \Gordon\Task
    */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
    * Get the type of this task.
    *
    * @return string
    */
    public function getType()
    {
        return $this->type;
    }

    /**
    * Set the arguments for this task.
    *
    * @param array $args
    * @return void
    */
    public function setArgs(array $args)
    {
        $this->task['Args'] = $this->encodeArgs($args);
    }

    /**
    * Get the arguments for this task.
    *
    * @return array
    */
    public function getArgs()
    {
        return $this->task['Args'];
    }

    /**
    * Adds an argument for this task.
    *
    * @param mixed $arg
    * @return \Gordon\Task
    */
    public function addArg($arg)
    {
        $this->task['Args'][] = $this->encodeArg($arg);
        return $this;
    }

    /**
    * Get the argument-value for at the given index.
    *
    * @param int $index
    * @return bool|string
    */
    public function getArg($index = 0)
    {
        if (!isset($this->task['Args'][$index])) {
            return false;
        }
        return $this->task['Args'][$index];
    }

    /**
    * Get the ErrorMessage for this task.
    *
    * @return string
    */
    public function getErrorMessage()
    {
        return $this->task['ErrorMessage'];
    }

    /**
    * Get this json-encoded value for this task, as it is pushed to Redis.
    *
    * @return string
    */
    public function getJson()
    {
        $task = $this->task;
        if (empty($task['ErrorMessage'])) {
            unset($task['ErrorMessage']);
        }
        return json_encode($task);
    }

    /**
    * Sets the properties for this task, from the json-encoded value given from Redis.
    *
    * @param string $string
    * @return bool
    */
    public function parseJson($string)
    {
        $task = json_decode($string, true);
        if (empty($task)) {
            throw new TaskException('Invalid JSON to parse');
        }
        $this->task = array_merge($this->task, $task);
        return true;
    }

    /**
    * Encodes the given array of arguments.
    *
    * @param array $args
    * @return array
    */
    protected function encodeArgs(array $args)
    {
        foreach ($args as &$arg) {
            $arg = $this->encodeArg($arg);
        }
        return $args;
    }

    /**
    * Encodes a single argument.
    *
    * @param mixed $arg
    * @return string
    */
    protected function encodeArg($arg)
    {
        if (is_object($arg) || is_array($arg)) {
            return base64_encode(json_encode($arg));
        } else {
            return (string)$arg;
        }
    }
}

class TaskException extends \Exception
{}
