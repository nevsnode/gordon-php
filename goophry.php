<?php

namespace Goophry;

class Taskqueue
{

    /**
    * The parameters for this Taskqueue instance.
    *
    * @var array
    */
    protected $params;

    /**
    * The phpredis-instance.
    *
    * @var Redis
    */
    protected $redis = null;

    /**
    * Creates a new Taskqueue instance.
    *
    * @param array $params
    * @return void
    */
    public function __construct(array $params = array())
    {
        if (!empty($params)) {
            $this->mergeParams($params);
        }
    }

    /**
    * Reads a Goophry configuration file and merges it with the internal params.
    *
    * @param string $file
    * @return bool
    */
    public function readConfig($file)
    {
        if (!is_string($file) || !file_exists($file)) {
            return false;
        }

        $config = json_decode(file_get_contents($file), true);
        if (empty($config)) {
            return false;
        }

        $this->mergeParams($config);
        return true;
    }

    /**
    * Merges the internal params with provided params.
    *
    * @param array $params
    * @return void
    */
    private function mergeParams(array $params)
    {
        $defaults = array(
            'RedisServer'   => '127.0.0.1',
            'RedisPort'     => '6379',
            'RedisQueueKey' => 'taskqueue',
            'RedisTimeout'  => 2,
        );

        // extract values that might come from the goophry configuration file
        if (!empty($params['RedisAddress'])) {
            list($server, $port) = explode(':', $params['RedisAddress']);
            if (!empty($server)) {
                $defaults['RedisServer'] = $server;
            }
            if (!empty($port)) {
                $defaults['RedisPort'] = $port;
            }
        }

        $this->params = array_merge($defaults, $params);
    }

    /**
    * Creates a phpredis instance and establishes connection to a Redis-server.
    *
    * @return bool
    */
    protected function connect()
    {
        if (false === $this->redis) {
            // there was already an attempt to create a connection, but it failed
            return false;
        }

        if (null === $this->redis) {
            // no instance of redis yet, so try it
            if (!class_exists('\Redis')) {
                // phpredis is not available
                $this->redis = false;
                return false;
            }

            try {
                $this->redis = new \Redis();
                if (!$this->redis->connect($this->params['RedisServer'], $this->params['RedisPort'], $this->params['RedisTimeout'])) {
                    throw new \RedisException('redis connect returned FALSE');
                }
            } catch (\RedisException $e) {
                $this->redis = false;
                return false;
            }
        }

        return true;
    }

    /**
    * Adds a task.
    *
    * @param string|Task $task
    * @param mixed $args,...
    * @return bool
    */
    public function addTask($task)
    {
        if (is_string($task)) {
            // When the first argument was a string, it must be the task-type.
            // So we create a Task-instance and set the passed arguments for it.
            $type = $task;
            $task = new Task();
            $task->setType($type);

            $args = func_get_args();
            array_shift($args);
            $task->setArgs($args);
        }

        return $this->addTaskObj($task);
    }

    /**
    * Pushes a task entry to Redis.
    *
    * @param Task $task
    * @return bool
    */
    protected function addTaskObj(Task $task)
    {
        if (false === $this->connect()) {
            return false;
        }

        $key = sprintf('%s:%s', $this->params['RedisQueueKey'], $task->getType());
        $value = $task->getJson();

        if (false === $this->redis->rPush($key, $value)) {
            return false;
        }
        return true;
    }

    /**
    * Pops a task entry from a failed-task list, and returns it.
    *
    * @param string $type
    * @return bool|Task
    */
    public function popFailedTask($type)
    {
        if (false === $this->connect()) {
            return false;
        }

        $key = sprintf('%s:%s:failed', $this->params['RedisQueueKey'], $type);

        $value = $this->redis->lPop($key);
        if (empty($value)) {
            return false;
        }

        $task = new Task();
        $task->setType($type);
        if (false === $task->parseJson($value)) {
            return false;
        }
        return $task;
    }

    /**
    * Alias for popFailedTask, for backwards compatibility.
    * Deprecated - do not use any more.
    *
    * @param string $type
    * @return bool|Task
    */
    public function getFailedTask($type)
    {
        return $this->popFailedTask($type);
    }
}


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
    * @return void
    */
    public function __construct()
    {
        $this->task = array(
            'Args'          => array(),
            'ErrorMessage'  => '',
        );
    }

    /**
    * Set the type of this task.
    *
    * @param string $type
    * @return void
    */
    public function setType($type)
    {
        $this->type = $type;
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
    * @return void
    */
    public function addArg($arg)
    {
        $this->task['Args'][] = $this->encodeArg($arg);
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
            return false;
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
            return strval($arg);
        }
    }
}
