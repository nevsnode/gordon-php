<?php

require __DIR__ . '/src/Taskqueue.php';
require __DIR__ . '/src/Task.php';


// create a new Taskqueue instance
$taskqueue = new Goophry\Taskqueue(array(
    'RedisServer' => '127.0.0.1',
    'RedisPort' => '6379',
    'RedisQueueKey' => 'myqueue',
));


// the parameters can also be read from the Goophry configuration file
$taskqueue = new Goophry\Taskqueue();
$taskqueue->readConfig('/path/to/goophry.config.json');


// add a task for type 'something' with the first argument '123' to the queue
$taskqueue->addTask('something', '123');


// or create a Task-instance and pass that instead
$task = new Goophry\Task();
$task->setType('something');
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
