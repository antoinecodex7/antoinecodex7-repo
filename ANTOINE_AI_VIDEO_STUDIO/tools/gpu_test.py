"""Проверка, че PyTorch реално изпълнява операции на GPU (не само cuda.is_available()).

Стартира се с python_embeded на ComfyUI portable. Записва JSON в logs/.
"""
import datetime as dt
import json
import os
import sys
import time

import torch

ROOT = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), ".."))
res = {"time": dt.datetime.now().isoformat(), "python": sys.version.split()[0], "torch": torch.__version__,
       "torch_cuda_build": torch.version.cuda, "cuda_available": torch.cuda.is_available()}
ok = False
try:
    if not torch.cuda.is_available():
        raise RuntimeError("torch.cuda.is_available() == False")
    dev = torch.device("cuda:0")
    p = torch.cuda.get_device_properties(0)
    res.update(device=p.name, capability=f"{p.major}.{p.minor}", vram_total_gib=round(p.total_memory / 2**30, 2),
               arch_list=torch.cuda.get_arch_list(), cudnn=torch.backends.cudnn.version())
    cap = f"sm_{p.major}{p.minor}"
    res["arch_supported_by_build"] = cap in res["arch_list"] or any(
        a.startswith("compute_") and int(a.split("_")[1]) <= p.major * 10 + p.minor for a in res["arch_list"])
    free, total = torch.cuda.mem_get_info()
    res["vram_free_gib_at_start"] = round(free / 2**30, 2)

    # 1) matmul fp32 срещу CPU референция
    g = torch.Generator().manual_seed(0)
    a = torch.randn(1024, 1024, generator=g)
    b = torch.randn(1024, 1024, generator=g)
    ref = a @ b
    out = (a.to(dev) @ b.to(dev)).cpu()
    res["fp32_matmul_max_rel_err"] = float(((out - ref).abs().max() / ref.abs().max()).item())

    # 2) голям bf16/fp16 matmul с измерване на TFLOPS
    for dtype in (torch.float16, torch.bfloat16):
        x = torch.randn(8192, 8192, device=dev, dtype=dtype)
        y = torch.randn(8192, 8192, device=dev, dtype=dtype)
        torch.cuda.synchronize()
        t = time.perf_counter()
        for _ in range(10):
            z = x @ y
        torch.cuda.synchronize()
        el = time.perf_counter() - t
        res[f"{str(dtype).split('.')[-1]}_matmul_tflops"] = round(10 * 2 * 8192**3 / el / 1e12, 1)
        assert torch.isfinite(z).all().item()
        del x, y, z

    # 3) conv3d (като във video VAE) + scaled dot product attention
    v = torch.randn(1, 16, 9, 64, 64, device=dev, dtype=torch.bfloat16)
    conv = torch.nn.Conv3d(16, 16, 3, padding=1).to(dev, torch.bfloat16)
    res["conv3d_ok"] = bool(torch.isfinite(conv(v)).all().item())
    q = torch.randn(1, 8, 2048, 64, device=dev, dtype=torch.bfloat16)
    res["sdpa_ok"] = bool(torch.isfinite(torch.nn.functional.scaled_dot_product_attention(q, q, q)).all().item())
    res["peak_alloc_mib"] = round(torch.cuda.max_memory_allocated() / 2**20)
    ok = res["fp32_matmul_max_rel_err"] < 1e-3 and res["conv3d_ok"] and res["sdpa_ok"]
except Exception as e:  # noqa: BLE001
    res["error"] = f"{type(e).__name__}: {e}"
res["result"] = "PASS" if ok else "FAIL"
os.makedirs(os.path.join(ROOT, "logs"), exist_ok=True)
path = os.path.join(ROOT, "logs", f"gpu_test_{dt.datetime.now():%Y%m%d_%H%M%S}.json")
json.dump(res, open(path, "w", encoding="utf-8"), indent=2)
print(json.dumps(res, indent=2))
print("GPU TEST:", res["result"], "->", path)
sys.exit(0 if ok else 1)
