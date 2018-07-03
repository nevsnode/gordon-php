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
        $this->task = [
            'args' => [],
            'env' => [],
            'error_message'  => '',
        ];
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
    * @return Gordon\Task
    */
    public function setArgs(array $args)
    {
        $this->task['args'] = $this->encodeArgs($args);
        return $this;
    }

    /**
    * Get the arguments for this task.
    *
    * @return array
    */
    public function getArgs()
    {
        return $this->task['args'];
    }

    /**
    * Adds an argument for this task.
    *
    * @param mixed $arg
    * @return Gordon\Task
    */
    public function addArg($arg)
    {
        $this->task['args'][] = $this->encodeArg($arg);
        return $this;
    }

    /**
    * Get the argument-value for this task at the given index.
    *
    * @param int $index
    * @return bool|string
    */
    public function getArg($index = 0)
    {
        if (!isset($this->task['args'][$index])) {
            return false;
        }
        return $this->task['args'][$index];
    }

    /**
     * Set the environment variables for this task.
     *
     * @param array $env
     * @return Gordon\Task
     */
    public function setEnvs(array $env)
    {
        foreach ($env as $key => $value) {
            $this->setEnv($key, $value);
        }
        return $this;
    }

    /**
    * Get the environment variables for this task.
    *
    * @return array
    */
    public function getEnvs()
    {
        return $this->task['env'];
    }

    /**
    * Sets an environment variable for this task.
    *
    * @param mixed $key
    * @param mixed $value
    * @return Gordon\Task
    */
    public function setEnv($key, $value)
    {
        $this->task['env'][(string)$key] = $value;
        return $this;
    }

    /**
    * Get an environment variable for this task and the given key.
    *
    * @param int $key
    * @return bool|string
    */
    public function getEnv($key)
    {
        if (!isset($this->task['env'][$key])) {
            return false;
        }
        return $this->task['env'][$key];
    }

    /**
    * Sets the error-message for this task.
    *
    * @param string $message
    * @return Gordon\Task
    */
    public function setErrorMessage($message)
    {
        $this->task['error_message'] = (string)$message;
        return $this;
    }

    /**
    * Get the error-message for this task.
    *
    * @return string
    */
    public function getErrorMessage()
    {
        return $this->task['error_message'];
    }

    /**
    * Get this json-encoded value for this task, as it is pushed to Redis.
    *
    * @param int $options
    * @return string
    */
    public function getJson($options = 0)
    {
        $task = $this->task;
        if (empty($task['error_message'])) {
            unset($task['error_message']);
        }
        $task['env'] = (object)$task['env'];
        return json_encode($task, $options);
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
{

}
