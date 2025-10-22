<?php
namespace PyBridge;

class PythonProxy
{
    protected $transport;
    public $id;
    public $class;

    public function __construct($id, $class, $transport)
    {
        $this->id = $id;
        $this->class = $class;
        $this->transport = $transport;
    }

    // أي استدعاء ميثود يُرسل للـ daemon مع object_id
    public function __call($name, $arguments)
    {
/*
    $args = $arguments[0] ?? [];
    $kwargs = $arguments[1] ?? [];
*/

$args = $arguments;
    $kwargs = end($args);
    
    if (is_array($kwargs)) {
        array_pop($args);
    } else {
        $kwargs = [];
    }

        $payload = [
            'function' => $name,
            'args' => $args,
            'kwargs'     => $kwargs,
            'object_id' => $this->id
        ];
        $res = $this->transport->send($payload);

        // إذا كانت النتيجة نفسها proxy، نعيد PythonProxy تلقائيًا
        if (is_array($res) && isset($res['__proxy__']) && isset($res['id'])) {
            return new PythonProxy($res['id'], $res['class'] ?? null, $this->transport);
        }
        return $res;
    }
}
