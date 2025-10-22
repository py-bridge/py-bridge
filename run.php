<?php

require 'vendor/autoload.php';
use PyBridge\Python;

// استخدام venv
Python::useVenv('/home/kali/venv');

// الاتصال والتشغيل التلقائي للdaemon
Python::connect();

// استدعاء دوال Python مباشرة
echo Python::run('math.sqrt', [196]); // 14

// استدعاء مكتبة كاملة
$np = Python::import('numpy');
echo $np->sum([5,10,15]); // 30
