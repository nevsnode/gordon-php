Goophry PHP
===

Example library for Goophry written in PHP.

Use with composer
---
Install with composer:
```sh
composer require nevsnode/goophry
```

Use it in your code:
```php
<?php
$taskqeue = new \Goophry\Taskqueue();
$taskqeue->readConfig('/path/to/goophry.config.json');

$task = new \Goophry\Task();
$task->setType('something');
$task->addArg('123');

$taskqeue->addTask($task);
```

Take a look at `example.php` for more example code.


License
===

The MIT License (MIT)

Copyright (c) 2015 Sven Weintuch

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
