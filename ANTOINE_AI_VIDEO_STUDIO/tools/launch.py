"""Стартира ComfyUI само на 127.0.0.1, показва лога в конзолата и го записва в logs/.

Извиква се от scripts/start_studio.ps1 (с python_embeded). Ctrl+C в прозореца спира сървъра.
"""
import argparse
import datetime as dt
import json
import os
import subprocess
import sys
import threading
import time
import urllib.request
import webbrowser

ROOT = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), ".."))


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--port", type=int, required=True)
    ap.add_argument("--comfy-dir", required=True)
    ap.add_argument("--no-browser", action="store_true")
    ap.add_argument("--extra", nargs=argparse.REMAINDER, default=[], help="допълнителни ComfyUI аргументи")
    a = ap.parse_args()

    logs = os.path.join(ROOT, "logs")
    for d in ("logs", "inputs", "outputs", "user"):
        os.makedirs(os.path.join(ROOT, d), exist_ok=True)
    log_path = os.path.join(logs, f"comfyui_{dt.datetime.now():%Y%m%d_%H%M%S}.log")
    cmd = [sys.executable, "-s", os.path.join(a.comfy_dir, "main.py"),
           "--listen", "127.0.0.1", "--port", str(a.port),
           "--disable-api-nodes",                        # без платени/облачни API nodes, frontend без интернет
           "--input-directory", os.path.join(ROOT, "inputs"),
           "--output-directory", os.path.join(ROOT, "outputs"),
           "--user-directory", os.path.join(ROOT, "user"),
           "--preview-method", "auto",                   # видим preview по време на семплиране
           ] + a.extra
    env = dict(os.environ, HF_HUB_OFFLINE="1", TRANSFORMERS_OFFLINE="1", DO_NOT_TRACK="1",
               PYTHONUTF8="1", PYTHONIOENCODING="utf-8")
    url = f"http://127.0.0.1:{a.port}"
    print(f"Стартиране: {' '.join(cmd)}\nЛог: {log_path}\nURL: {url}\n", flush=True)
    with open(log_path, "w", encoding="utf-8") as lf:
        proc = subprocess.Popen(cmd, cwd=a.comfy_dir, env=env, stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                                text=True, encoding="utf-8", errors="replace", bufsize=1)
        with open(os.path.join(logs, "comfyui_server.json"), "w", encoding="utf-8") as pf:
            json.dump({"pid": proc.pid, "port": a.port, "url": url, "log": log_path,
                       "started": dt.datetime.now().isoformat()}, pf)

        def opener():
            for _ in range(600):
                try:
                    urllib.request.urlopen(url + "/system_stats", timeout=2)
                    print(f"\n*** ComfyUI е готов: {url} ***\n", flush=True)
                    if not a.no_browser:
                        webbrowser.open(url)
                    return
                except Exception:  # noqa: BLE001
                    if proc.poll() is not None:
                        return
                    time.sleep(1)
        threading.Thread(target=opener, daemon=True).start()
        try:
            for line in proc.stdout:
                sys.stdout.write(line)
                lf.write(line)
                lf.flush()
        except KeyboardInterrupt:
            print("Спиране...", flush=True)
            proc.terminate()
        code = proc.wait()
    print(f"ComfyUI завърши с код {code}. Лог: {log_path}")
    sys.exit(code)


if __name__ == "__main__":
    main()
