<?php

require __DIR__ . '/src/Taskqueue.php';
require __DIR__ . '/src/Task.php';

use \Gordon\Taskqueue;
use \Gordon\Task;

// create a new Taskqueue instance
$taskqueue = new Taskqueue(array(
    'redis_server' => '127.0.0.1',
    'redis_port' => '6379',
    'queue_key' => 'myqueue',
));


// the parameters can also be set after initialization
$taskqueue = new Taskqueue();
$taskqueue->setParams(array(
    'redis_server' => '127.0.0.1',
    'redis_port' => '6379',
    'queue_key' => 'myqueue',
));


// add a task for type 'something' with the first argument '123' to the queue
$taskqueue->addTask('something', '123');


// or create a Task-instance and pass that instead
$task = new Task('something');
$task->addArg('123');
$taskqueue->addTask($task);


// pop off a task from a failed-task list
$failedTask = $taskqueue->popFailedTask('something');

if (false === $failedTask) {
    echo 'No failed task found.';
} else {
    echo 'Failed task error: ' . $failedTask->getErrorMessage();

    // you could also re-queue this task then
    $taskqueue->addTask($failedTask);
}
