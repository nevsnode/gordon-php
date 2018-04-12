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
    public function __construct(array $params = [])
    {
        // set default parameters
        $this->params = [
            'redis_server' => '127.0.0.1',
            'redis_port' => '6379',
            'queue_key' => 'taskqueue',
            'redis_timeout' => 2,
        ];

        // merge passed parameters
        $this->setParams($params);
    }

    /**
    * Merges the internal params with provided params.
    *
    * @param array $params
    * @return void
    */
    public function setParams(array $params)
    {
        $this->params = array_merge($this->params, $params);
    }

    /**
    * Creates a phpredis instance and establishes connection to a Redis-server.
    *
    * @return bool
    */
    protected function connect()
    {
        if (null === $this->redis) {
            // no instance of redis yet, so try it
            if (!class_exists('\Redis')) {
                // phpredis is not available
                throw new TaskqueueException('Redis-class is not present on this system');
            }

            $this->redis = new \Redis();
            if (!$this->redis->connect($this->params['redis_server'], $this->params['redis_port'], $this->params['redis_timeout'], null, 100)) {
                throw new TaskqueueException('Redis-connection could not be established');
            }
        }
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

        $key = sprintf('%s:%s', $this->params['queue_key'], $task->getType());
        return $this->addTaskObj($key, $task);
    }

    /**
    * Adds a failed task to redis.
    *
    * @param string|Task $task
    * @param int $ttl
    * @return bool
    */
    public function addFailedTask(Task $task, $ttl)
    {
        $key = sprintf('%s:%s:failed', $this->params['queue_key'], $task->getType());
        if (!$this->addTaskObj($key, $task)) {
            return false;
        }

        if (false === $this->redis->expire($key, $ttl)) {
            return false;
        }

        return true;
    }

    /**
    * Pushes a task entry to Redis.
    *
    * @param Task $task
    * @return bool
    */
    protected function addTaskObj($key, Task $task)
    {
        $this->connect();

        $value = $task->getJson();

        if (false === $this->redis->rPush($key, $value)) {
            return false;
        }
        return true;
    }

    /**
    * Pops a task entry from a task list, and returns it.
    *
    * @param string $type
    * @return bool|Task
    */
    public function popTask($type)
    {
        $this->connect();

        $key = sprintf('%s:%s', $this->params['queue_key'], $type);

        $value = $this->redis->lPop($key);
        if (empty($value)) {
            return false;
        }

        $task = new Task($type);
        $task->parseJson($value);
        return $task;
    }

    /**
    * Pops a task entry from a failed-task list, and returns it.
    *
    * @param string $type
    * @return bool|Task
    */
    public function popFailedTask($type)
    {
        $this->connect();

        $key = sprintf('%s:%s:failed', $this->params['queue_key'], $type);

        $value = $this->redis->lPop($key);
        if (empty($value)) {
            return false;
        }

        $task = new Task($type);
        $task->parseJson($value);
        return $task;
    }

    /**
    * Returns the first entry from a failed-task list.
    *
    * @param string $type
    * @return bool|Task
    */
    public function getFailedTask($type)
    {
        $this->connect();

        $key = sprintf('%s:%s:failed', $this->params['queue_key'], $type);

        $value = $this->redis->lIndex($key, 0);
        if (empty($value)) {
            return false;
        }

        $task = new Task($type);
        $task->parseJson($value);
        return $task;
    }
}

class TaskqueueException extends \Exception
{

}
