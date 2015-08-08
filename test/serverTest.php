<?php
$serv = new swoole_server("127.0.0.1", 9501);
$serv->on('receive', function ($serv, $fd, $from_id, $data) {
    $serv->send($fd, 'Swoole: '.$data);
    $serv->close($fd);
});
$serv->on('start', function ($serv) {
    echo "parent process, pid = ", posix_getpid(), ", ppid = ", posix_getppid(), "\n";
    $p1 = new swoole_process(function () {
        while (true) { 
            echo "child process, pid = ", posix_getpid(), ", ppid = ", posix_getppid(), "\n";
            sleep(3);
        }
    });
    $p1->start();
    $p2 = new swoole_process(function () {
        while (true) { 
            echo "child process, pid = ", posix_getpid(), ", ppid = ", posix_getppid(), "\n";
            sleep(3);
        }
    });
    $p2->start();
});
$serv->start();
