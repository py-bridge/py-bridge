#!/usr/bin/env python3
import importlib
from flask import Flask, request
import msgpack
from concurrent.futures import ThreadPoolExecutor
import types, threading
import numpy as np

app = Flask(__name__)
executor = ThreadPoolExecutor(max_workers=20)

session_modules = {}      # cache imported modules
object_registry = {}      # id -> python object
object_counter = 0
registry_lock = threading.Lock()

# ---------------- registry helpers ----------------
def register_object(obj):
    global object_counter
    with registry_lock:
        obj_id = object_counter
        object_registry[obj_id] = obj
        object_counter += 1
    return obj_id

def get_object(obj_id):
    return object_registry.get(obj_id)

# ---------------- serializer (safe) ----------------
def serialize(obj, _visited=None, _depth=0, _max_depth=6):
    """
    Convert Python objects to msgpackable primitives.
    For complex objects, register as proxy {__proxy__: True, id: N, class: Name}
    """
    import types
    if _visited is None:
        _visited = set()

    # depth/visited guard
    if _depth > _max_depth:
        return repr(obj)

    oid = id(obj)
    if oid in _visited:
        return f"<Circular {type(obj).__name__}>"
    _visited.add(oid)

    try:
        if obj is None:
            return None
        if isinstance(obj, (bool, int, float, str)):
            return obj
        if isinstance(obj, np.generic):
            return obj.item()
        if isinstance(obj, (list, tuple, set)):
            return [serialize(x, _visited, _depth+1, _max_depth) for x in obj]
        if isinstance(obj, dict):
            return {k: serialize(v, _visited, _depth+1, _max_depth) for k, v in obj.items()}
        if isinstance(obj, types.FunctionType):
            return f"<function {obj.__name__}>"
        # For classes/instances/modules: create proxy
        if hasattr(obj, "__dict__") or hasattr(obj, "__class__") or isinstance(obj, types.ModuleType):
            obj_id = register_object(obj)
            return {"__proxy__": True, "id": obj_id, "class": getattr(obj, "__name__", obj.__class__.__name__)}
        # fallback
        return str(obj)
    finally:
        _visited.remove(oid)

# ---------------- main API ----------------
@app.route("/call", methods=["POST"])
def call_function():
    try:
        payload = msgpack.unpackb(request.data, raw=False)
        func_path = payload.get("function")
        args = payload.get("args", []) or []
        obj_id = payload.get("object_id", None)
        kwargs = payload.get("kwargs", {})

        # If calling on an existing proxy object
        if obj_id is not None:
            obj = get_object(obj_id)
            if obj is None:
                raise Exception(f"Object id {obj_id} not found")
            # treat func_path as attribute/method name
            func = getattr(obj, func_path)
        else:
            # module.function or bare function
            if not func_path:
                raise Exception("Missing 'function' in payload")
            if "." in func_path:
                module_name, func_name = func_path.rsplit(".", 1)
                module = session_modules.get(module_name)
                if module is None:
                    module = importlib.import_module(module_name)
                    session_modules[module_name] = module
                func = getattr(module, func_name)
            else:
                # global function within daemon
                func = globals().get(func_path)
                if func is None:
                    raise Exception(f"Function '{func_path}' not found in daemon")

        # execute
        future = executor.submit(func, *args, **kwargs)
        result = future.result()
        packed = msgpack.packb({"result": serialize(result)}, use_bin_type=True)
        return packed
    except Exception as e:
        import traceback
        traceback.print_exc()
        return msgpack.packb({"error": str(e)}, use_bin_type=True), 500

if __name__ == "__main__":
    print("Python Daemon Proxy Dynamic Ready...")
    app.run(host="127.0.0.1", port=5050)
