"""Изтегляне на теглата от официалните / официално препоръчаните Hugging Face хранилища.

- Размерът и SHA256 идват от HF API (LFS oid) ПРЕДИ изтеглянето; файлът се проверява след това.
- Проверява свободното място (размер на липсващите файлове + резерв за кеш/временни файлове/изходи).
- Не дублира: ако файлът вече е в папката на студиото или в друга ComfyUI инсталация (--also-search),
  със същия размер и SHA256, НЕ се тегли. Чуждите файлове само се четат, нищо не се мести/трие;
  използват се чрез extra_model_paths.yaml на НАШАТА инсталация.
- Възобновява прекъснато изтегляне (.part + HTTP Range).
- Записва лицензите (от model card метаданните) и хешовете в logs/models_manifest.json.

Използване (с python_embeded на ComfyUI portable):
  python tools\\download_models.py --comfy-dir <...>\\ComfyUI --set wan
  python tools\\download_models.py --comfy-dir <...>\\ComfyUI --set ltx
  python tools\\download_models.py --comfy-dir <...>\\ComfyUI --set wan21_fallback   (само при доказан проблем)
  добави --dry-run, за да видиш само плана и нужното място.
"""
import argparse
import datetime as dt
import hashlib
import json
import os
import shutil
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), ".."))
HF = os.environ.get("HF_ENDPOINT", "https://huggingface.co").rstrip("/")
UA = {"User-Agent": "antoine-ai-video-studio/1.0"}

# (repo, път в repo, папка в ComfyUI/models, бележка за произхода)
SETS = {
    "wan": [
        ("Comfy-Org/Wan_2.2_ComfyUI_Repackaged", "split_files/diffusion_models/wan2.2_ti2v_5B_fp16.safetensors",
         "diffusion_models", "Wan 2.2 TI2V 5B (Wan-AI/Wan2.2-TI2V-5B), препакетиран от Comfy-Org за официалния workflow"),
        ("Comfy-Org/Wan_2.1_ComfyUI_repackaged", "split_files/text_encoders/umt5_xxl_fp8_e4m3fn_scaled.safetensors",
         "text_encoders", "UMT5-XXL text encoder, посочен в официалния Wan 2.2 5B ComfyUI template"),
        ("Comfy-Org/Wan_2.2_ComfyUI_Repackaged", "split_files/vae/wan2.2_vae.safetensors",
         "vae", "Wan 2.2 VAE (задължителен за 5B модела)"),
    ],
    "ltx": [
        ("Lightricks/LTX-Video", "ltxv-2b-0.9.8-distilled.safetensors",
         "checkpoints", "официален checkpoint от Lightricks (съдържа и VAE)"),
        ("comfyanonymous/flux_text_encoders", "t5xxl_fp16.safetensors",
         "text_encoders", "T5-XXL encoder, посочен в официалния ComfyUI LTXV template"),
    ],
    "wan21_fallback": [
        ("Comfy-Org/Wan_2.1_ComfyUI_repackaged", "split_files/diffusion_models/wan2.1_t2v_1.3B_fp16.safetensors",
         "diffusion_models", "Wan 2.1 T2V 1.3B - САМО резервен вариант"),
        ("Comfy-Org/Wan_2.1_ComfyUI_repackaged", "split_files/vae/wan_2.1_vae.safetensors", "vae", "Wan 2.1 VAE"),
        ("Comfy-Org/Wan_2.1_ComfyUI_repackaged", "split_files/text_encoders/umt5_xxl_fp8_e4m3fn_scaled.safetensors",
         "text_encoders", "UMT5-XXL"),
    ],
}
# Лицензи на ОРИГИНАЛНИТЕ тегла (препакетираните хранилища само ги преразпространяват).
UPSTREAM = {
    "wan2.2_ti2v_5B_fp16.safetensors": "Wan-AI/Wan2.2-TI2V-5B",
    "wan2.2_vae.safetensors": "Wan-AI/Wan2.2-TI2V-5B",
    "umt5_xxl_fp8_e4m3fn_scaled.safetensors": "Wan-AI/Wan2.1-T2V-14B",
    "wan2.1_t2v_1.3B_fp16.safetensors": "Wan-AI/Wan2.1-T2V-1.3B",
    "wan_2.1_vae.safetensors": "Wan-AI/Wan2.1-T2V-1.3B",
    "ltxv-2b-0.9.8-distilled.safetensors": "Lightricks/LTX-Video",
    "t5xxl_fp16.safetensors": "google/t5-v1_1-xxl",
}
RESERVE_GB = 20  # кеш, временни файлове, изходни видеа


def api_json(url):
    with urllib.request.urlopen(urllib.request.Request(url, headers=UA), timeout=60) as r:
        return json.load(r)


def hf_file_meta(repo, path):
    d = os.path.dirname(path)
    url = f"{HF}/api/models/{repo}/tree/main" + (f"/{urllib.parse.quote(d)}" if d else "")
    for item in api_json(url):
        if item.get("path") == path:
            lfs = item.get("lfs") or {}
            return {"size": item.get("size") or lfs.get("size"), "sha256": lfs.get("oid")}
    raise FileNotFoundError(f"{path} не е намерен в {repo} (проверено {url})")


def hf_license(repo):
    try:
        info = api_json(f"{HF}/api/models/{repo}")
    except Exception as e:  # noqa: BLE001
        return {"repo": repo, "error": str(e)}
    card = info.get("cardData") or {}
    lic = card.get("license") or next((t.split(":", 1)[1] for t in info.get("tags", []) if t.startswith("license:")), None)
    files = [s["rfilename"] for s in info.get("siblings", []) if "licen" in s["rfilename"].lower()]
    return {"repo": repo, "license": lic, "license_name": card.get("license_name"),
            "license_link": card.get("license_link"), "license_files": files, "gated": info.get("gated")}


def sha256_of(path):
    h = hashlib.sha256()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(16 * 2**20), b""):
            h.update(chunk)
    return h.hexdigest()


def fmt_gb(n):
    return f"{n / 1e9:.2f} GB"


def download(url, dest, size):
    part = dest + ".part"
    have = os.path.getsize(part) if os.path.exists(part) else 0
    if have > size:
        os.remove(part)
        have = 0
    headers = dict(UA)
    if have:
        headers["Range"] = f"bytes={have}-"
        print(f"  възобновяване от {fmt_gb(have)}")
    req = urllib.request.Request(url, headers=headers)
    with urllib.request.urlopen(req, timeout=120) as r:
        if have and r.status != 206:
            have = 0  # сървърът не поддържа Range -> отначало
        mode = "ab" if have else "wb"
        t0, last = time.time(), 0
        with open(part, mode) as f:
            got = have
            while True:
                chunk = r.read(8 * 2**20)
                if not chunk:
                    break
                f.write(chunk)
                got += len(chunk)
                if time.time() - last > 5:
                    speed = (got - have) / max(time.time() - t0, 1e-6)
                    print(f"  {fmt_gb(got)} / {fmt_gb(size)}  ({100 * got / size:.1f}%, {speed / 1e6:.1f} MB/s)",
                          flush=True)
                    last = time.time()
    return part


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--comfy-dir", required=True, help="папката ComfyUI вътре в portable инсталацията")
    ap.add_argument("--set", required=True, choices=sorted(SETS))
    ap.add_argument("--also-search", nargs="*", default=[],
                    help="models папки на други ComfyUI инсталации - само за четене, за да не се дублират файлове")
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--reserve-gb", type=float, default=RESERVE_GB)
    args = ap.parse_args()

    models_dir = os.path.join(args.comfy_dir, "models")
    manifest_path = os.path.join(ROOT, "logs", "models_manifest.json")
    os.makedirs(os.path.dirname(manifest_path), exist_ok=True)
    manifest = json.load(open(manifest_path, encoding="utf-8")) if os.path.exists(manifest_path) else {"files": {}}

    plan, need = [], 0
    for repo, path, folder, note in SETS[args.set]:
        name = os.path.basename(path)
        meta = hf_file_meta(repo, path)
        dest = os.path.join(models_dir, folder, name)
        entry = {"repo": repo, "path": path, "folder": folder, "note": note, "size": meta["size"],
                 "sha256": meta["sha256"], "url": f"{HF}/{repo}/resolve/main/{path}", "dest": dest}
        status, found = "download", None
        candidates = [dest] + [os.path.join(d, folder, name) for d in args.also_search]
        for c in candidates:
            if os.path.exists(c) and os.path.getsize(c) == meta["size"]:
                found = c
                break
        if found:
            status = "exists" if found == dest else "reuse_external"
        entry.update(status=status, found=found)
        if status == "download":
            part = dest + ".part"
            need += meta["size"] - (os.path.getsize(part) if os.path.exists(part) else 0)
        plan.append(entry)
        print(f"{status:15s} {fmt_gb(meta['size']):>10s}  {folder}/{name}  <- {repo}" + (f"  [{found}]" if found else ""))

    free = shutil.disk_usage(models_dir if os.path.exists(models_dir) else ROOT).free
    print(f"\nЗа изтегляне: {fmt_gb(need)}; резерв: {args.reserve_gb} GB; свободно: {fmt_gb(free)}")
    if need and free < need + args.reserve_gb * 1e9:
        print("НЕДОСТАТЪЧНО МЯСТО - спирам без да тегля.")
        sys.exit(3)

    print("\nЛицензи (от метаданните на Hugging Face):")
    licenses = {}
    for e in plan:
        for repo in {e["repo"], UPSTREAM.get(os.path.basename(e["path"]), e["repo"])}:
            if repo not in licenses:
                licenses[repo] = hf_license(repo)
                print(f"  {repo}: {licenses[repo]}")
    if args.dry_run:
        return

    extra_dirs = {}
    for e in plan:
        name = os.path.basename(e["path"])
        if e["status"] == "download":
            os.makedirs(os.path.dirname(e["dest"]), exist_ok=True)
            print(f"\nИзтегляне {name} ({fmt_gb(e['size'])}) от {e['url']}")
            for attempt in range(1, 4):
                try:
                    part = download(e["url"], e["dest"], e["size"])
                    break
                except (urllib.error.URLError, TimeoutError, ConnectionError) as ex:
                    print(f"  мрежова грешка ({ex}); опит {attempt}/3 - продължавам след 10s")
                    time.sleep(10)
            else:
                print("  неуспешно изтегляне - спирам.")
                sys.exit(4)
            print("  проверка SHA256 ...")
            digest = sha256_of(part)
            if e["sha256"] and digest != e["sha256"]:
                bad = e["dest"] + ".sha_mismatch"
                os.replace(part, bad)
                print(f"  SHA256 НЕ СЪВПАДА ({digest} != {e['sha256']}). Файлът е преименуван на {bad} "
                      "за анализ; следващото пускане тегли наново.")
                sys.exit(5)
            os.replace(part, e["dest"])
            e["verified_sha256"] = digest
            e["status"] = "downloaded"
        else:
            print(f"\nПроверка SHA256 на наличния {e['found']} ...")
            digest = sha256_of(e["found"])
            if e["sha256"] and digest != e["sha256"]:
                print("  наличният файл е със същото име, но РАЗЛИЧНО съдържание - не го пипам. "
                      "Премести/преименувай го ръчно или го изтегли в друга инсталация.")
                sys.exit(6)
            e["verified_sha256"] = digest
            if e["status"] == "reuse_external":
                extra_dirs.setdefault(e["folder"], set()).add(os.path.dirname(e["found"]))
        manifest["files"][name] = {**e, "time": dt.datetime.now().isoformat()}

    if extra_dirs:
        # Само НАШИЯТ extra_model_paths.yaml; чуждите инсталации се ползват само за четене.
        yaml_path = os.path.join(args.comfy_dir, "extra_model_paths.yaml")
        existing = open(yaml_path, encoding="utf-8").read() if os.path.exists(yaml_path) else ""
        block = ["", f"antoine_reuse_{args.set}:", "  base_path: /"]
        for folder, dirs in extra_dirs.items():
            block.append(f"  {folder}: |")
            block += [f"    {d}" for d in sorted(dirs)]
        if f"antoine_reuse_{args.set}:" not in existing:
            with open(yaml_path, "a", encoding="utf-8") as f:
                f.write("\n".join(block) + "\n")
            print(f"Добавени външни (само за четене) папки в {yaml_path}")

    manifest["licenses"] = {**manifest.get("licenses", {}), **licenses}
    json.dump(manifest, open(manifest_path, "w", encoding="utf-8"), ensure_ascii=False, indent=2)
    print(f"\nГотово. Манифест: {manifest_path}")


if __name__ == "__main__":
    main()
