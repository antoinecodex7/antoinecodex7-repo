"""AI агент за сценарий: генерира всяка сцена от storyboard JSON последователно с Wan 2.2 TI2V 5B
(снимка -> видео, или текст -> видео, ако снимката липсва), проверява всеки клип за черни/статични
кадри, повтаря само проблемна сцена веднъж с друг seed и накрая слепва всички клипове в един MP4.

  python_embeded\\python.exe tools\\run_storyboard.py --port 8188 --storyboard storyboards\\video1_mechtata.json
      --quality draft   (832x480, ~1,5 мин/сцена)  |  --quality final (1280x704, ~4,5 мин/сцена)
      --only 03_namira_saita 05_zaedno   (само избрани сцени)
      --resume outputs\\storyboards\\video1_mechtata_<stamp>   (продължава прекъснато пускане)
"""
import argparse
import asyncio
import copy
import datetime as dt
import json
import os
import sys

import aiohttp

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import run_tests as rt  # noqa: E402  (run_prompt, analyze_video, find_output_file, log)

ROOT = rt.ROOT
QUALITY = {"draft": (832, 480), "final": (1280, 704)}


def frames_for(seconds, fps):
    n = round(seconds * fps)
    return max(5, (n + 1) // 4 * 4 + 1)  # Wan: най-близкото 4n+1 (3 s -> 73, 5 s -> 121)


def crop_box(image_path, frac):
    """crop_frac [x, y, w, h] в части от размера на снимката -> пиксели (за вертикални снимки)."""
    from PIL import Image
    W, H = Image.open(image_path).size
    x, y, w, h = frac
    return int(x * W), int(y * H), max(64, int(w * W)), max(64, int(h * H))


def reference_frame(image_path, crop_frac, width, height):
    """Повтаря обработката на ComfyUI: crop_frac -> централно изрязване до width:height -> bilinear resize.
    Връща (картина width x height като float32 масив, функция за преобразуване на правоъгълник)."""
    import numpy as np
    from PIL import Image
    im = Image.open(image_path).convert("RGB")
    W0, H0 = im.size
    cx, cy, cw, ch = crop_box(image_path, crop_frac) if crop_frac else (0, 0, W0, H0)
    if cw / ch > width / height:
        nw, nh = ch * width / height, ch
    else:
        nw, nh = cw, cw * height / width
    ox, oy = cx + (cw - nw) / 2, cy + (ch - nh) / 2
    sx, sy = width / nw, height / nh
    ref = np.asarray(im.crop((round(ox), round(oy), round(ox + nw), round(oy + nh))).resize(
        (width, height), Image.BILINEAR), dtype=np.float32)

    def map_rect(frac):
        fx, fy, fw, fh = frac
        x0, y0 = (fx * W0 - ox) * sx, (fy * H0 - oy) * sy
        x1, y1 = ((fx + fw) * W0 - ox) * sx, ((fy + fh) * H0 - oy) * sy
        x0, y0, x1, y1 = max(0, int(x0)), max(0, int(y0)), min(width, int(x1 + 0.999)), min(height, int(y1 + 0.999))
        return (x0, y0, x1, y1) if x1 - x0 > 4 and y1 - y0 > 4 else None
    return ref, map_rect


def text_lock_mask(rects, width, height, feather=6):
    import numpy as np
    m = np.zeros((height, width), np.float32)
    for x0, y0, x1, y1 in rects:
        yy = np.arange(y0, y1)[:, None]
        xx = np.arange(x0, x1)[None, :]
        d = np.minimum(np.minimum(yy - y0 + 1, y1 - yy), np.minimum(xx - x0 + 1, x1 - xx)).astype(np.float32)
        m[y0:y1, x0:x1] = np.maximum(m[y0:y1, x0:x1], np.clip(d / feather, 0, 1))
    return m[..., None]


def apply_text_lock(clip, image_path, crop_frac, rect_fracs, fps):
    """Залепва надписите от оригиналната снимка върху всеки кадър (камерата трябва да е неподвижна).
    Яркостта на залепения участък следва кадъра (gain по околния пръстен), за да няма видим шев."""
    import av
    import numpy as np
    from fractions import Fraction
    with av.open(clip) as src:
        frames = [f.to_ndarray(format="rgb24").astype(np.float32) for f in src.decode(video=0)]
    H, W = frames[0].shape[:2]
    ref, map_rect = reference_frame(image_path, crop_frac, W, H)
    rects = [r for r in (map_rect(f) for f in rect_fracs) if r]
    if not rects:
        return 0
    mask = text_lock_mask(rects, W, H)
    ring = (text_lock_mask([(max(0, x0 - 12), max(0, y0 - 12), min(W, x1 + 12), min(H, y1 + 12))
                            for x0, y0, x1, y1 in rects], W, H, feather=1) > 0)[..., 0] & (mask[..., 0] == 0)
    ref_ring = ref[ring].mean(axis=0) if ring.any() else None
    tmp = clip + ".lock.mp4"
    with av.open(tmp, "w") as out:
        st = out.add_stream("h264", rate=fps)
        st.width, st.height, st.pix_fmt, st.options = W, H, "yuv420p", {"crf": "16"}
        for n, fr in enumerate(frames):
            gain = np.clip(fr[ring].mean(axis=0) / np.maximum(ref_ring, 1), 0.7, 1.3) if ref_ring is not None else 1
            out_fr = fr * (1 - mask) + np.clip(ref * gain, 0, 255) * mask
            vf = av.VideoFrame.from_ndarray(out_fr.astype(np.uint8), format="rgb24")
            vf.pts, vf.time_base = n, Fraction(1, fps)
            for p in st.encode(vf):
                out.mux(p)
        for p in st.encode():
            out.mux(p)
    os.replace(tmp, clip)
    return len(rects)


def preview_locks(sb, run_dir, width, height):
    """PNG за всяка сцена: какво вижда моделът (след изрязване) + зелени рамки на заключените надписи."""
    from PIL import Image, ImageDraw
    out = os.path.join(run_dir, "locks_preview")
    os.makedirs(out, exist_ok=True)
    for sc in sb["scenes"]:
        img, _ = resolve_image(sc)
        if not img:
            continue
        ref, map_rect = reference_frame(os.path.join(ROOT, "inputs", img), sc.get("crop_frac"), width, height)
        pic = Image.fromarray(ref.astype("uint8"))
        d = ImageDraw.Draw(pic)
        for f in (sc.get("text_lock") or {}).get(img, []):
            r = map_rect(f)
            if r:
                d.rectangle(r, outline=(0, 255, 0), width=3)
        path = os.path.join(out, f"{sc['id']}_{img}.png")
        pic.save(path)
        print("preview:", path)


def resolve_image(scene):
    img = scene.get("image")
    if img and os.path.exists(os.path.join(ROOT, "inputs", img)):
        return img, None
    fb = scene.get("fallback_image")
    if img and fb and os.path.exists(os.path.join(ROOT, "inputs", fb)):
        return fb, f"inputs\\{img} липсва -> използвам {fb}"
    return None, (f"ВНИМАНИЕ: inputs\\{img} липсва -> генерирам само от текст" if img else None)


def build_scene_wf(base_i2v, base_t2v, scene, style, image_ok, width, height, fps, seed, prefix, image=None, crop=None):
    wf = copy.deepcopy(base_i2v if image_ok else base_t2v)
    for node in wf.values():
        c, i = node["class_type"], node["inputs"]
        if c == "Wan22ImageToVideoLatent":
            i.update(width=width, height=height, length=frames_for(scene["seconds"], fps))
        elif c == "KSampler":
            i["seed"] = seed
        elif c == "LoadImage":
            i["image"] = image
        elif c == "CreateVideo":
            i["fps"] = fps
        elif c == "SaveVideo":
            i["filename_prefix"] = prefix
    if image_ok and crop:
        x, y, w, h = crop
        load = next(k for k, n in wf.items() if n["class_type"] == "LoadImage")
        wf["900"] = {"class_type": "ImageCrop", "inputs": {"image": [load, 0], "width": w, "height": h, "x": x, "y": y}}
        lat = next(n for n in wf.values() if n["class_type"] == "Wan22ImageToVideoLatent")
        lat["inputs"]["start_image"] = ["900", 0]
    # положителният prompt е CLIPTextEncode, свързан с positive на KSampler
    ks = next(n for n in wf.values() if n["class_type"] == "KSampler")
    wf[ks["inputs"]["positive"][0]]["inputs"]["text"] = f"{scene['prompt']} {style}"
    return wf


def concat(clips, out_path, fps):
    import av
    from fractions import Fraction
    n = 0
    with av.open(out_path, "w") as out:
        stream = None
        for clip in clips:
            with av.open(clip) as src:
                for frame in src.decode(video=0):
                    if stream is None:
                        stream = out.add_stream("h264", rate=fps)
                        stream.width, stream.height = frame.width, frame.height
                        stream.pix_fmt = "yuv420p"
                        stream.options = {"crf": "17"}
                    if (frame.width, frame.height) != (stream.width, stream.height):
                        frame = frame.reformat(width=stream.width, height=stream.height)
                    frame.pts, frame.time_base = n, Fraction(1, fps)
                    n += 1
                    for p in stream.encode(frame):
                        out.mux(p)
        for p in stream.encode():
            out.mux(p)


async def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--port", type=int, required=True)
    ap.add_argument("--storyboard", required=True)
    ap.add_argument("--quality", choices=sorted(QUALITY), default="draft")
    ap.add_argument("--only", nargs="*", default=None)
    ap.add_argument("--resume", default=None)
    ap.add_argument("--validate-only", action="store_true", help="само валидира workflows, без генерация")
    ap.add_argument("--preview-locks", action="store_true", help="само PNG преглед на изрязването и заключените надписи")
    a = ap.parse_args()

    sb = json.load(open(a.storyboard, encoding="utf-8"))
    fps, seed0 = sb.get("fps", 24), sb.get("seed", 20261001)
    width, height = QUALITY[a.quality]
    stamp = dt.datetime.now().strftime("%Y%m%d_%H%M%S")
    run_dir = os.path.abspath(a.resume) if a.resume else os.path.join(
        ROOT, "outputs", "storyboards", f"{sb['output_name']}_{a.quality}_{stamp}")
    os.makedirs(run_dir, exist_ok=True)
    rel_prefix_dir = os.path.relpath(run_dir, os.path.join(ROOT, "outputs")).replace("\\", "/")
    fh = open(os.path.join(run_dir, "agent.log"), "a", encoding="utf-8")
    api = os.path.join(ROOT, "workflows", "api")
    base_i2v = json.load(open(os.path.join(api, "wan22_ti2v5b_image_to_video_480p.api.json"), encoding="utf-8"))
    base_t2v = json.load(open(os.path.join(api, "wan22_ti2v5b_text_to_video_480p.api.json"), encoding="utf-8"))

    if a.preview_locks:
        preview_locks(sb, run_dir, width, height)
        return
    rt.log(f"АГЕНТ: {sb['title']} | {len(sb['scenes'])} сцени | {a.quality} {width}x{height} @ {fps} FPS | {run_dir}", fh)
    base = f"http://{rt.HOST}:{a.port}"
    results = []
    async with aiohttp.ClientSession(timeout=aiohttp.ClientTimeout(total=None)) as session:
        q = await rt.get_json(session, f"{base}/queue")
        if q.get("queue_running") or q.get("queue_pending"):
            rt.log("Опашката на ComfyUI не е празна - изчакай да свърши или натисни STOP_TASK.bat. Спирам.", fh)
            sys.exit(2)
        for idx, scene in enumerate(sb["scenes"]):
            if a.only and scene["id"] not in a.only:
                continue
            final_clip = os.path.join(run_dir, f"{scene['id']}.mp4")
            if os.path.exists(final_clip):
                rt.log(f"[{scene['id']}] вече е готова - пропускам", fh)
                results.append({"id": scene["id"], "status": "existing", "clip": final_clip})
                continue
            img, note = resolve_image(scene)
            image_ok = img is not None
            if note:
                rt.log(f"[{scene['id']}] {note}", fh)
            crop = crop_box(os.path.join(ROOT, "inputs", img), scene["crop_frac"]) \
                if image_ok and scene.get("crop_frac") else None
            res = None
            for attempt, seed in enumerate((seed0 + idx, seed0 + idx + 1000), 1):
                wf = build_scene_wf(base_i2v, base_t2v, scene, sb.get("style", ""), image_ok, width, height, fps,
                                    seed, f"{rel_prefix_dir}/raw/{scene['id']}_a{attempt}", image=img, crop=crop)
                if a.validate_only:
                    async with session.post(f"{base}/prompt", json={"prompt": wf}) as r:
                        body = await r.json()
                    errs = body.get("node_errors")
                    await session.post(f"{base}/queue", json={"clear": True})
                    await session.post(f"{base}/interrupt", json={})
                    rt.log(f"[{scene['id']}] валидация: {'OK' if not errs and r.status == 200 else body}", fh)
                    res = {"status": "validated"}
                    break
                rt.log(f"[{scene['id']}] опит {attempt}: {'снимка ' + img if image_ok else 'текст'} -> "
                       f"{scene['seconds']} s, seed {seed}", fh)
                res = await rt.run_prompt(session, a.port, wf, fh)
                if res.get("status") != "success":
                    rt.log(f"[{scene['id']}] грешка: {res.get('error')}", fh)
                    break
                path = rt.find_output_file(res.get("outputs", {}), os.path.join(ROOT, "outputs"))
                check = rt.analyze_video(path, os.path.splitext(path)[0] + "_contact_sheet.png")
                res.update(clip=path, check=check)
                rt.log(f"[{scene['id']}] {res['total_s']} s -> {check['verdict']}", fh)
                locks = (scene.get("text_lock") or {}).get(img) if image_ok else None
                if locks and check["verdict"].startswith("OK"):
                    n = apply_text_lock(path, os.path.join(ROOT, "inputs", img), scene.get("crop_frac"), locks, fps)
                    rt.log(f"[{scene['id']}] заключени надписи: {n} област(и) от {img}", fh)
                    res["text_locked"] = n
                if check["verdict"].startswith("OK"):
                    os.replace(path, final_clip)
                    res["clip"] = final_clip
                    break
                if attempt == 1:
                    rt.log(f"[{scene['id']}] автоматичната проверка откри проблем -> ЕДИН нов опит с друг seed", fh)
            res = res or {}
            res.pop("outputs", None)
            res.update(id=scene["id"], image=img if image_ok else None, seconds=scene["seconds"])
            if not os.path.exists(final_clip) and res.get("clip") and os.path.exists(res["clip"]):
                os.replace(res["clip"], final_clip)  # пазим последния опит, но го маркираме
                res["clip"], res["warning"] = final_clip, "проблем по метриките - прегледай ръчно"
            results.append(res)

    if a.validate_only:
        return
    clips = [os.path.join(run_dir, f"{s['id']}.mp4") for s in sb["scenes"]
             if os.path.exists(os.path.join(run_dir, f"{s['id']}.mp4"))]
    final = os.path.join(run_dir, f"{sb['output_name']}_FINAL.mp4")
    if clips:
        concat(clips, final, fps)
        chk = rt.analyze_video(final, os.path.splitext(final)[0] + "_contact_sheet.png")
        rt.log(f"ГОТОВО: {final} ({chk['duration_s']} s, {chk['decoded_frames']} кадъра) -> {chk['verdict']}", fh)
    with open(os.path.join(run_dir, "REPORT.md"), "w", encoding="utf-8") as f:
        f.write(f"# {sb['title']}\n\n{sb.get('audience', '')}\n\nКачество: {a.quality} {width}x{height}, {fps} FPS\n\n")
        f.write("| Сцена | Вход | Сек | Статус | Време | Проверка |\n|---|---|---|---|---|---|\n")
        for r in results:
            f.write(f"| {r['id']} | {r.get('image') or 'текст'} | {r.get('seconds', '')} | {r.get('status')} "
                    f"{r.get('warning', '')} | {r.get('total_s', '')} s | {(r.get('check') or {}).get('verdict', '')} |\n")
        f.write(f"\nФинален клип: `{final if clips else 'няма'}`\n\nБез звук: гласът зад кадър и музиката се "
                f"добавят отделно (извън обхвата на тази среда).\n")
    rt.log(f"Отчет: {os.path.join(run_dir, 'REPORT.md')}", fh)


if __name__ == "__main__":
    asyncio.run(main())
