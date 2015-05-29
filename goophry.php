<?php

namespace Goophry;

class Taskqueue
{
    protected $params;
    protected $redis = null;

    public function __construct(array $params = array())
    {
        if (!empty($params)) {
            $this->mergeParams($params);
        }
    }

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

    public function addTask($task)
    {
        if (is_string($task)) {
            $type = $task;
            $task = new Task();
            $task->setType($type);

            $args = func_get_args();
            array_shift($args);
            $task->setArgs($args);
        }

        return $this->addTaskObj($task);
    }

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

    public function getFailedTask($type, $index)
    {
        return $this->popFailedTask($type);
    }
}

class Task
{
    protected $type = false;
    protected $task;

    public function __construct()
    {
        $this->task = array(
            'Args'          => array(),
            'ErrorMessage'  => '',
        );
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setArgs(array $args)
    {
        $this->task['Args'] = $this->encodeArgs($args);
    }

    public function getArgs()
    {
        return $this->task['Args'];
    }

    public function addArg($arg)
    {
        $this->task['Args'][] = $this->encodeArg($arg);
    }

    public function getArg($index = 0)
    {
        if (!isset($this->task['Args'][$index])) {
            return false;
        }
        return $this->task['Args'][$index];
    }

    public function getErrorMessage()
    {
        return $this->task['ErrorMessage'];
    }

    public function getJson()
    {
        $task = $this->task;
        if (empty($task['ErrorMessage'])) {
            unset($task['ErrorMessage']);
        }
        return json_encode($task);
    }

    public function parseJson($string)
    {
        $task = json_decode($string, true);
        if (empty($task)) {
            return false;
        }
        $this->task = array_merge($this->task, $task);
        return true;
    }

    protected function encodeArgs(array $args)
    {
        foreach ($args as &$arg) {
            $arg = $this->encodeArg($arg);
        }
        return $args;
    }

    protected function encodeArg($arg)
    {
        if (is_object($arg) || is_array($arg)) {
            return base64_encode(json_encode($arg));
        } else {
            return strval($arg);
        }
    }
}
