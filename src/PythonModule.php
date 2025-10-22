<?php
namespace PyBridge;

class PythonModule
{
    protected $module;
    protected $transport;

    public function __construct(string $module, $transport)
    {
        $this->module = $module;
        $this->transport = $transport;
    }

    // استدعاء مثل Module.Class(...) أو Module.func(...)
    // إذا الميثود يعيد proxy، ننشئ PythonProxy
    public function __call($name, $arguments)
    {
        // إذا تُريد استدعاء فكتشين داخل الموديول: نرسل module.name ك function path
        $func_path = $this->module . "." . $name;
        $res = $this->transport->send([
            'function' => $func_path,
            'args' => $arguments
        ]);

        if (is_array($res) && isset($res['__proxy__']) && isset($res['id'])) {
            return new PythonProxy($res['id'], $res['class'] ?? null, $this->transport);
        }
        return $res;
    }

    // helper: import module and return module proxy (if needed)
    public function importModule()
    {
        $res = $this->transport->send(['function' => "importlib.import_module", 'args'=>[$this->module]]);
        if (is_array($res) && isset($res['__proxy__']) && isset($res['id'])) {
            return new PythonProxy($res['id'], $res['class'] ?? null, $this->transport);
        }
        return $res;
    }
}
