<?php

namespace Gordon;

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
    * Reads a Gordon configuration file and merges it with the internal params.
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

        // extract values that might come from the gordon configuration file
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
