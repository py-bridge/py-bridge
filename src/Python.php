<?php
namespace PyBridge;

use PyBridge\Exception\PythonBridgeException;
use PyBridge\Transport\HTTPTransport;

class Python
{
    protected static $transport;
    protected static $venvPath;

    public static function useVenv(string $path)
    {
        if (!is_dir($path)) {
            throw new PythonBridgeException("Invalid venv path: $path");
        }
        self::$venvPath = $path;
    }

    public static function connect($config = [])
    {
        self::$transport = new HTTPTransport(self::$venvPath, $config);
        self::$transport->ensureDaemonRunning();
    }

    public static function run(string $function, array $args = [])
    {
        return self::$transport->send([
            'function' => $function,
            'args' => $args
        ]);
    }

    public static function import(string $module)
    {
        return new PythonModule($module, self::$transport);
    }
}
