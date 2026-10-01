"""Реални тестове на видео генерацията през локалния ComfyUI API (само 127.0.0.1).

Изпълнява workflows ПОСЛЕДОВАТЕЛНО (никога паралелно), измерва време по етапи,
пикова GPU памет (nvidia-smi), RAM на ComfyUI процеса, проверява MP4 файла
(ffprobe ако е наличен, иначе PyAV), изчислява метрики за движение/черен кадър,
записва contact sheet PNG за визуален преглед и JSON/Markdown резултати.

Стартира се с python_embeded на ComfyUI portable (там има aiohttp, psutil, av, numpy, PIL):
  python_embeded\\python.exe tools\\run_tests.py --port 8188 --tests wan_t2v wan_i2v ltx_t2v
"""
import argparse
import asyncio
import copy
import datetime as dt
import json
import os
import shutil
import subprocess
import sys
import threading
import time
import uuid

import aiohttp
import numpy as np
import psutil

ROOT = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), ".."))
API_DIR = os.path.join(ROOT, "workflows", "api")
LOGS = os.path.join(ROOT, "logs")
HOST = "127.0.0.1"

TESTS = {
    "wan_t2v": {"file": "wan22_ti2v5b_text_to_video_480p.api.json", "title": "Wan 2.2 TI2V 5B - text-to-video 480p",
                "checkpoint": "wan2.2_ti2v_5B_fp16.safetensors (+ umt5_xxl_fp8_e4m3fn_scaled, wan2.2_vae)"},
    "wan_i2v": {"file": "wan22_ti2v5b_image_to_video_480p.api.json", "title": "Wan 2.2 TI2V 5B - image-to-video 480p",
                "checkpoint": "wan2.2_ti2v_5B_fp16.safetensors (+ umt5_xxl_fp8_e4m3fn_scaled, wan2.2_vae)"},
    "ltx_t2v": {"file": "ltxv2b_098_distilled_text_to_video_480p.api.json",
                "title": "LTX-Video 2B 0.9.8 distilled - text-to-video 480p",
                "checkpoint": "ltxv-2b-0.9.8-distilled.safetensors (+ t5xxl_fp16)"},
    "wan_t2v_720p": {"file": "wan22_ti2v5b_text_to_video_720p.api.json", "title": "Wan 2.2 TI2V 5B - text-to-video 720p",
                     "checkpoint": "wan2.2_ti2v_5B_fp16.safetensors (+ umt5_xxl_fp8_e4m3fn_scaled, wan2.2_vae)"},
    # Без модел: проверява API, websocket, запис на MP4, видео-проверката и прекъсването. НЕ е AI генерация.
    "smoke_no_model": {"file": None, "title": "Smoke test без модел (не е AI генерация)", "checkpoint": "-"},
}

LOADER_NODES = {"UNETLoader", "CLIPLoader", "VAELoader", "CheckpointLoaderSimple", "LoadImage"}
ENCODE_NODES = {"CLIPTextEncode", "LTXVConditioning", "Wan22ImageToVideoLatent", "EmptyLTXVLatentVideo", "LTXVImgToVideo"}
SAMPLER_NODES = {"KSampler", "SamplerCustom"}
DECODE_NODES = {"VAEDecode"}
SAVE_NODES = {"CreateVideo", "SaveVideo"}


def now():
    return time.perf_counter()


def stamp():
    return dt.datetime.now().strftime("%Y%m%d_%H%M%S")


def log(msg, fh=None):
    line = f"[{dt.datetime.now().strftime('%H:%M:%S')}] {msg}"
    print(line, flush=True)
    if fh:
        fh.write(line + "\n")
        fh.flush()


def smoke_workflow(prefix):
    # 49 кадъра 256x256: EmptyImage -> CreateVideo -> SaveVideo
    return {
        "1": {"class_type": "EmptyImage", "inputs": {"width": 256, "height": 256, "batch_size": 49, "color": 0x3366CC}},
        "57": {"class_type": "CreateVideo", "inputs": {"images": ["1", 0], "fps": 24}},
        "58": {"class_type": "SaveVideo", "inputs": {"video": ["57", 0], "filename_prefix": prefix,
                                                      "format": "auto", "format.codec": "auto"}},
    }


# ---------------------------------------------------------------- мониторинг на ресурси
class ResourceMonitor(threading.Thread):
    def __init__(self, comfy_pid):
        super().__init__(daemon=True)
        self.comfy_pid = comfy_pid
        self.stop_evt = threading.Event()
        self.gpu_used_peak = None
        self.gpu_used_baseline = None
        self.gpu_total = None
        self.proc_rss_peak = 0
        self.sys_ram_used_peak = 0
        self.external_connections = set()
        self.nvsmi = shutil.which("nvidia-smi")

    def gpu_query(self):
        if not self.nvsmi:
            return None
        try:
            out = subprocess.run([self.nvsmi, "--query-gpu=memory.used,memory.total", "--format=csv,noheader,nounits"],
                                 capture_output=True, text=True, timeout=5).stdout.strip().splitlines()[0]
            used, total = [int(x) for x in out.split(",")]
            return used, total
        except Exception:
            return None

    def run(self):
        q = self.gpu_query()
        if q:
            self.gpu_used_baseline, self.gpu_total = q
            self.gpu_used_peak = q[0]
        proc = psutil.Process(self.comfy_pid) if self.comfy_pid else None
        while not self.stop_evt.is_set():
            q = self.gpu_query()
            if q:
                self.gpu_used_peak = max(self.gpu_used_peak or 0, q[0])
            vm = psutil.virtual_memory()
            self.sys_ram_used_peak = max(self.sys_ram_used_peak, vm.total - vm.available)
            if proc:
                try:
                    self.proc_rss_peak = max(self.proc_rss_peak, proc.memory_info().rss)
                    for c in proc.net_connections(kind="inet"):
                        if c.raddr and c.raddr.ip not in ("127.0.0.1", "::1", "0.0.0.0", "::"):
                            self.external_connections.add(f"{c.raddr.ip}:{c.raddr.port} ({c.status})")
                except (psutil.Error, AttributeError):
                    pass
            self.stop_evt.wait(0.5)

    def stop(self):
        self.stop_evt.set()
        self.join(timeout=5)


def find_comfy_pid(port):
    for c in psutil.net_connections(kind="inet"):
        if c.laddr and c.laddr.port == port and c.status == psutil.CONN_LISTEN:
            return c.pid
    return None


# ---------------------------------------------------------------- видео проверка
def ffprobe_info(path):
    exe = shutil.which("ffprobe")
    if not exe:
        return None
    r = subprocess.run([exe, "-v", "error", "-select_streams", "v:0", "-count_frames", "-show_entries",
                        "stream=codec_name,width,height,r_frame_rate,nb_read_frames,duration,pix_fmt",
                        "-of", "json", path], capture_output=True, text=True)
    try:
        return json.loads(r.stdout)["streams"][0]
    except Exception:
        return {"error": r.stderr.strip()[:500]}


def analyze_video(path, sheet_path):
    import av
    from PIL import Image

    info = {"file": path, "size_bytes": os.path.getsize(path)}
    info["ffprobe"] = ffprobe_info(path)
    with av.open(path) as cont:
        vs = cont.streams.video[0]
        info["container"] = cont.format.name
        info["codec"] = vs.codec_context.name
        info["width"], info["height"] = vs.codec_context.width, vs.codec_context.height
        info["fps"] = float(vs.average_rate) if vs.average_rate else None
        frames = [f.to_ndarray(format="rgb24") for f in cont.decode(video=0)]
    info["decoded_frames"] = len(frames)
    info["duration_s"] = round(len(frames) / info["fps"], 3) if info["fps"] else None
    if not frames:
        info["verdict"] = "НЕВАЛИДНО: няма декодирани кадри"
        return info
    arr = np.stack([f.astype(np.float32) for f in frames])
    means = arr.mean(axis=(1, 2, 3))
    diffs = np.abs(np.diff(arr, axis=0)).mean(axis=(1, 2, 3)) if len(frames) > 1 else np.array([0.0])
    info["mean_brightness_min_max"] = [round(float(means.min()), 2), round(float(means.max()), 2)]
    info["mean_abs_frame_diff"] = round(float(diffs.mean()), 3)
    info["first_last_diff"] = round(float(np.abs(arr[-1] - arr[0]).mean()), 3)
    info["per_frame_std_mean"] = round(float(arr.std(axis=(1, 2, 3)).mean()), 2)
    problems = []
    if means.max() < 8:
        problems.append("черно видео")
    dark = [int(i) for i in np.where(means < 10)[0]]
    info["dark_frames"] = dark
    if dark and means.max() >= 8:
        problems.append(f"черни кадри {dark[0]}-{dark[-1]} ({len(dark)} бр.)")
    if info["per_frame_std_mean"] < 2:
        problems.append("еднороден/празен кадър")
    if info["mean_abs_frame_diff"] < 0.3 and info["first_last_diff"] < 1.0:
        problems.append("статично (няма движение)")
    info["verdict"] = "OK по автоматичните метрики" if not problems else "ПРОБЛЕМ: " + ", ".join(problems)
    # contact sheet: 6 равномерни кадъра за визуален преглед от човек
    idx = np.linspace(0, len(frames) - 1, num=min(6, len(frames))).astype(int)
    thumbs = [Image.fromarray(frames[i]) for i in idx]
    w, h = thumbs[0].size
    scale = 320 / w
    tw, th = int(w * scale), int(h * scale)
    sheet = Image.new("RGB", (tw * 3, th * ((len(thumbs) + 2) // 3)), "black")
    for k, t in enumerate(thumbs):
        sheet.paste(t.resize((tw, th)), ((k % 3) * tw, (k // 3) * th))
    sheet.save(sheet_path)
    info["contact_sheet"] = sheet_path
    return info


# ---------------------------------------------------------------- изпълнение
async def get_json(session, url):
    async with session.get(url) as r:
        r.raise_for_status()
        return await r.json()


async def run_prompt(session, port, wf, fh, interrupt_after=None):
    client_id = uuid.uuid4().hex
    base = f"http://{HOST}:{port}"
    node_types = {k: v["class_type"] for k, v in wf.items()}
    phases = {}
    current = None
    t_phase = None
    result = {"node_timeline": []}
    async with session.ws_connect(f"ws://{HOST}:{port}/ws?clientId={client_id}", max_msg_size=0) as ws:
        async with session.post(f"{base}/prompt", json={"prompt": wf, "client_id": client_id}) as r:
            body = await r.json()
            if r.status != 200 or body.get("node_errors"):
                result["status"] = "validation_error"
                result["error"] = body
                return result
        pid = body["prompt_id"]
        result["prompt_id"] = pid
        t0 = now()
        last_pct = -1
        interrupted_sent = False
        while True:
            if interrupt_after and not interrupted_sent and now() - t0 > interrupt_after:
                async with session.post(f"{base}/interrupt", json={"prompt_id": pid}) as _:
                    pass
                interrupted_sent = True
                log(f"  -> изпратен /interrupt след {interrupt_after}s", fh)
            try:
                msg = await ws.receive(timeout=2)
            except asyncio.TimeoutError:
                continue
            if msg.type == aiohttp.WSMsgType.BINARY:
                continue  # latent previews
            if msg.type in (aiohttp.WSMsgType.CLOSED, aiohttp.WSMsgType.ERROR):
                result["status"] = "ws_closed"
                break
            data = json.loads(msg.data)
            typ, d = data.get("type"), data.get("data", {})
            if d.get("prompt_id") not in (None, pid):
                continue
            if typ == "executing":
                t = now()
                if current is not None:
                    phases[current] = phases.get(current, 0) + (t - t_phase)
                node = d.get("node")
                if node is None:
                    current = None
                    if d.get("prompt_id") == pid:
                        result["status"] = result.get("status", "success")
                        break
                else:
                    cls = node_types.get(node, "?")
                    result["node_timeline"].append([round(t - t0, 2), node, cls])
                    current = ("load" if cls in LOADER_NODES else "encode" if cls in ENCODE_NODES else
                               "sampling" if cls in SAMPLER_NODES else "decode" if cls in DECODE_NODES else
                               "save" if cls in SAVE_NODES else "other")
                    t_phase = t
                    log(f"  node {node} {cls}", fh)
            elif typ == "progress":
                pct = int(100 * d["value"] / max(d["max"], 1))
                if pct // 10 != last_pct // 10:
                    log(f"  progress {d['value']}/{d['max']} ({pct}%)", fh)
                last_pct = pct
            elif typ == "execution_error":
                result["status"] = "error"
                result["error"] = {k: d.get(k) for k in ("node_id", "node_type", "exception_type", "exception_message")}
                result["traceback"] = "".join(d.get("traceback", []))[-4000:]
                break
            elif typ == "execution_interrupted":
                result["status"] = "interrupted"
                break
            elif typ == "execution_success":
                result["status"] = "success"
                break
        result["total_s"] = round(now() - t0, 2)
        result["phases_s"] = {k: round(v, 2) for k, v in phases.items()}
        await asyncio.sleep(0.5)
        hist = await get_json(session, f"{base}/history/{pid}")
        result["outputs"] = hist.get(pid, {}).get("outputs", {})
    return result


def find_output_file(outputs, output_dir):
    for node_out in outputs.values():
        for key in ("images", "videos", "gifs"):
            for item in node_out.get(key, []) or []:
                fn = item.get("filename", "")
                if fn.lower().endswith((".mp4", ".webm", ".mkv")):
                    return os.path.join(output_dir, item.get("subfolder", ""), fn)
    return None


def reduced_frames(wf):
    """Един обоснован резервен опит при OOM: ~половин брой кадри (запазва 4n+1 / 8n+1)."""
    wf = copy.deepcopy(wf)
    for node in wf.values():
        inp = node["inputs"]
        if node["class_type"] == "Wan22ImageToVideoLatent":
            inp["length"] = 49
        if node["class_type"] == "EmptyLTXVLatentVideo":
            inp["length"] = 49
    return wf


def params_of(wf):
    p = {}
    for node in wf.values():
        c, i = node["class_type"], node["inputs"]
        if c in ("Wan22ImageToVideoLatent", "EmptyLTXVLatentVideo"):
            p.update(width=i["width"], height=i["height"], frames=i["length"])
        elif c == "KSampler":
            p.update(seed=i["seed"], steps=i["steps"], cfg=i["cfg"], sampler=i["sampler_name"], scheduler=i["scheduler"])
        elif c == "SamplerCustom":
            p.update(seed=i["noise_seed"], cfg=i["cfg"])
        elif c == "ManualSigmas":
            p.update(sigmas=i["sigmas"], steps=len(i["sigmas"].split(",")) - 1)
        elif c == "KSamplerSelect":
            p.update(sampler=i["sampler_name"])
        elif c == "CreateVideo":
            p.update(fps=i["fps"])
        elif c == "LoadImage":
            p.update(input_image=i["image"])
        elif c == "CLIPTextEncode" and "prompt" not in p:
            p.update(prompt=i["text"])
    return p


async def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--port", type=int, required=True)
    ap.add_argument("--tests", nargs="+", default=["wan_t2v", "wan_i2v", "ltx_t2v"])
    ap.add_argument("--output-dir", default=os.path.join(ROOT, "outputs"))
    ap.add_argument("--interrupt-test", action="store_true",
                    help="стартира wan_t2v и го прекъсва след 20s, за да докаже спирането на задача")
    ap.add_argument("--no-retry", action="store_true")
    args = ap.parse_args()

    os.makedirs(LOGS, exist_ok=True)
    run_id = stamp()
    fh = open(os.path.join(LOGS, f"tests_{run_id}.log"), "w", encoding="utf-8")
    base = f"http://{HOST}:{args.port}"
    results = []
    timeout = aiohttp.ClientTimeout(total=None, sock_read=None)
    async with aiohttp.ClientSession(timeout=timeout) as session:
        stats = await get_json(session, f"{base}/system_stats")
        log(f"ComfyUI {stats['system'].get('comfyui_version')} torch {stats['system'].get('pytorch_version')} "
            f"devices {[d.get('name') for d in stats.get('devices', [])]}", fh)
        q = await get_json(session, f"{base}/queue")
        if q.get("queue_running") or q.get("queue_pending"):
            log("ОПАШКАТА НЕ Е ПРАЗНА - спирам, за да не пусна паралелна генерация.", fh)
            sys.exit(2)
        comfy_pid = find_comfy_pid(args.port)

        plan = list(args.tests)
        if args.interrupt_test:
            plan.append("interrupt")
        for name in plan:
            is_interrupt = name == "interrupt"
            spec = TESTS["wan_t2v" if is_interrupt else name]
            prefix_tag = f"tests/{run_id}_{name}"
            if spec["file"]:
                wf = json.load(open(os.path.join(API_DIR, spec["file"]), encoding="utf-8"))
                for node in wf.values():
                    if node["class_type"] == "SaveVideo":
                        node["inputs"]["filename_prefix"] = prefix_tag
            else:
                wf = smoke_workflow(prefix_tag)
            attempts = [wf] if (args.no_retry or is_interrupt or not spec["file"]) else [wf, "retry"]
            for attempt_no, attempt in enumerate(attempts, 1):
                if attempt == "retry":
                    prev = results[-1]
                    msg = json.dumps(prev.get("error", {}), ensure_ascii=False).lower()
                    if prev["status"] != "error" or ("out of memory" not in msg and "outofmemory" not in msg):
                        break
                    attempt = reduced_frames(wf)
                    log("  OOM -> един резервен опит с 49 кадъра", fh)
                log(f"=== {name}: {spec['title']} (опит {attempt_no})", fh)
                mon = ResourceMonitor(comfy_pid)
                mon.start()
                t_wall = now()
                res = await run_prompt(session, args.port, attempt, fh, interrupt_after=20 if is_interrupt else None)
                mon.stop()
                res.update(test=name, title=spec["title"] if not is_interrupt else "Тест за спиране на задача (/interrupt)",
                           checkpoint=spec["checkpoint"], workflow=spec["file"] or "(вграден smoke)",
                           attempt=attempt_no, params=params_of(attempt), wall_s=round(now() - t_wall, 2),
                           gpu_mem_mib={"baseline": mon.gpu_used_baseline, "peak": mon.gpu_used_peak,
                                        "total": mon.gpu_total,
                                        "peak_minus_baseline": (mon.gpu_used_peak - mon.gpu_used_baseline)
                                        if mon.gpu_used_peak is not None else None},
                           comfy_rss_peak_gib=round(mon.proc_rss_peak / 2**30, 2),
                           system_ram_used_peak_gib=round(mon.sys_ram_used_peak / 2**30, 2),
                           external_connections=sorted(mon.external_connections))
                res.pop("outputs", None) if res.get("status") != "success" else None
                if res.get("status") == "success":
                    path = find_output_file(res.get("outputs", {}), args.output_dir)
                    res["mp4"] = path
                    if path and os.path.exists(path):
                        res["video_check"] = analyze_video(path, os.path.splitext(path)[0] + "_contact_sheet.png")
                    else:
                        res["status"] = "no_output_file"
                    res.pop("outputs", None)
                log(f"  -> {res.get('status')} за {res.get('total_s')}s, фази {res.get('phases_s')}, "
                    f"GPU пик {res['gpu_mem_mib']}, външни връзки: {res['external_connections'] or 'няма'}", fh)
                if res.get("video_check"):
                    log(f"  -> видео: {res['video_check']['verdict']}", fh)
                results.append(res)
                if is_interrupt and res.get("status") != "interrupted":
                    log("  !! прекъсването НЕ беше потвърдено", fh)

    out_json = os.path.join(LOGS, f"test_results_{run_id}.json")
    with open(out_json, "w", encoding="utf-8") as f:
        json.dump({"run_id": run_id, "comfyui": stats["system"], "devices": stats.get("devices"), "results": results},
                  f, ensure_ascii=False, indent=2)
    write_markdown(run_id, stats, results)
    log(f"Резултати: {out_json}", fh)
    fh.close()


def write_markdown(run_id, stats, results):
    path = os.path.join(ROOT, "TEST_RESULTS.md")
    new = not os.path.exists(path)
    with open(path, "a", encoding="utf-8") as f:
        if new:
            f.write("# Резултати от реалните тестове\n\nГенерира се автоматично от `tools/run_tests.py`. "
                    "Автоматичните метрики НЕ заменят визуален преглед на MP4 от човек.\n")
        s = stats["system"]
        f.write(f"\n## Пускане {run_id}\n\nComfyUI {s.get('comfyui_version')}, PyTorch {s.get('pytorch_version')}, "
                f"Python {s.get('python_version', '').split()[0]}, устройства: "
                f"{', '.join(d.get('name', '') for d in stats.get('devices', []))}\n\n")
        for r in results:
            p = r.get("params", {})
            v = r.get("video_check", {})
            f.write(f"### {r['title']} — **{r.get('status')}** (опит {r['attempt']})\n\n")
            f.write(f"- Checkpoint: `{r['checkpoint']}`\n- Workflow: `workflows/api/{r['workflow']}`\n")
            f.write(f"- Seed {p.get('seed')}, {p.get('width')}x{p.get('height')}, {p.get('frames')} кадъра, "
                    f"{p.get('fps')} FPS, стъпки {p.get('steps')}, cfg {p.get('cfg')}, sampler {p.get('sampler')}"
                    f"{' / ' + str(p.get('scheduler')) if p.get('scheduler') else ''}\n")
            f.write(f"- Време общо: {r.get('total_s')} s; по фази: {r.get('phases_s')}\n")
            g = r["gpu_mem_mib"]
            f.write(f"- GPU памет (nvidia-smi, цялата карта): базово {g['baseline']} MiB, пик {g['peak']} MiB "
                    f"от {g['total']} MiB (прираст {g['peak_minus_baseline']} MiB)\n")
            f.write(f"- RAM: пик ComfyUI процес {r['comfy_rss_peak_gib']} GiB, пик системно използвана "
                    f"{r['system_ram_used_peak_gib']} GiB\n")
            f.write(f"- Външни мрежови връзки на ComfyUI по време на задачата: "
                    f"{', '.join(r['external_connections']) or 'няма'}\n")
            if r.get("mp4"):
                f.write(f"- MP4: `{r['mp4']}`\n- Contact sheet: `{v.get('contact_sheet')}`\n")
                f.write(f"- Видео поток: {v.get('codec')} {v.get('width')}x{v.get('height')}, {v.get('fps')} FPS, "
                        f"{v.get('decoded_frames')} кадъра, {v.get('duration_s')} s; ffprobe: "
                        f"{'наличен' if v.get('ffprobe') else 'няма (проверено с PyAV)'}\n")
                f.write(f"- Движение: средна разлика между кадри {v.get('mean_abs_frame_diff')}, "
                        f"първи/последен {v.get('first_last_diff')}; яркост {v.get('mean_brightness_min_max')} — "
                        f"**{v.get('verdict')}**\n")
            if r.get("error"):
                f.write(f"- Грешка: `{json.dumps(r['error'], ensure_ascii=False)[:800]}`\n")
            f.write("- Визуален преглед от човек: _предстои_\n\n")


if __name__ == "__main__":
    asyncio.run(main())
