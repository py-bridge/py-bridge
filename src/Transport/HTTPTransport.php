<?php
namespace PyBridge\Transport;

use PyBridge\PythonProxy;
use PyBridge\Exception\PythonBridgeException;

class HTTPTransport
{
    protected $venvPath;
    protected $config;

    public function __construct($venvPath = null, $config = [])
    {
        $this->venvPath = $venvPath;
        $this->config = $config;
    }

    public function ensureDaemonRunning()
    {
        $port = $this->config['port'] ?? 5050;
        $alive = @file_get_contents("http://127.0.0.1:$port/");
        if ($alive === false) {
            $python = $this->venvPath ? escapeshellarg($this->venvPath . "/bin/python") : "python3";
            $daemon = __DIR__ . "/../../python_daemon/daemon.py";
            shell_exec("$python $daemon > /dev/null 2>&1 &");
            sleep(1);
        }
    }

public function send2(array $data)
{
    $port = $this->config['port'] ?? 5050;
    $packed = msgpack_pack($data);

    $opts = [
        "http" => [
            "method"  => "POST",
            "header"  => "Content-Type: application/octet-stream",
            "content" => $packed
        ]
    ];
    $context = stream_context_create($opts);
    $response = file_get_contents("http://127.0.0.1:$port/call", false, $context);

    if ($response === false) {
        throw new \PyBridge\Exception\PythonBridgeException("Failed to communicate with Python Daemon");
    }

    $result = msgpack_unpack($response);

    if (isset($result['error'])) {
        throw new \PyBridge\Exception\PythonBridgeException("Python Error: " . $result['error']);
    }

    // تحويل object إلى PythonProxy إذا كان proxy
    if (is_array($result['result']) && isset($result['result']['__pybridge_object__'])) {
        return new PythonProxy($result['result']['__pybridge_object__'], $this);
    }

    return $result['result'] ?? null;
}

    public function send(array $data)
    {
        $port = $this->config['port'] ?? 5050;
        $packed = msgpack_pack($data);

        $opts = [
            "http" => [
                "method"  => "POST",
                "header"  => "Content-Type: application/octet-stream",
                "content" => $packed
            ]
        ];
        $context = stream_context_create($opts);
        $response = file_get_contents("http://127.0.0.1:$port/call", false, $context);

        if ($response === false) {
            throw new PythonBridgeException("Failed to communicate with Python Daemon");
        }

        $result = msgpack_unpack($response);

        if (isset($result['error'])) {
            throw new PythonBridgeException("Python Error: " . $result['error']);
        }
        return $result['result'] ?? null;
    }
}
