"""Генерира API-формат workflows за ANTOINE AI VIDEO STUDIO.

Източници на настройките:
- Wan 2.2 TI2V 5B: официалния ComfyUI template `video_wan2_2_5B_ti2v.json`
  (github.com/Comfy-Org/workflow_templates): UNETLoader + CLIPLoader(wan) + VAELoader,
  ModelSamplingSD3 shift=8, KSampler 20 steps, cfg 5, uni_pc/simple, CreateVideo 24 fps.
- LTX-Video 2B 0.9.8 distilled: официалния config `configs/ltxv-2b-0.9.8-distilled.yaml`
  (github.com/Lightricks/LTX-Video): guidance_scale 1, timesteps от first/second pass.
  Lightricks НЯМА официален ComfyUI workflow за 2B 0.9.8 distilled (в README: "N/A"),
  затова се използват вградените LTXV nodes от ComfyUI template `ltxv_text_to_video.json`,
  а scheduler-ът е заменен с ManualSigmas според yaml config-а.

Стартиране:  python build_workflows.py   (записва в ../workflows/api)
След това файловете са заредени в ComfyUI frontend 1.53.6 и записани наново чрез graphToPrompt()
(= "Export (API)"), а UI версиите в ../workflows/ui са graph.serialize() от същия frontend;
проверено е, че UI -> API дава същите стойности.
"""
import json
import os

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "..", "workflows", "api")

SCENE = (
    "A cinematic exterior shot of a small boutique hotel on the French Riviera at golden hour. "
    "Warm sunlight on a cream-colored facade, palm leaves moving gently in the breeze, slow forward "
    "camera movement, realistic architecture, natural motion, no text, no logos."
)

# Официалният Wan негативен prompt от template-а (на китайски, както е обучен моделът).
WAN_NEGATIVE = (
    "色调艳丽，过曝，静态，细节模糊不清，字幕，风格，作品，画作，画面，静止，整体发灰，最差质量，低质量，"
    "JPEG压缩残留，丑陋的，残缺的，多余的手指，画得不好的手部，画得不好的脸部，畸形的，毁容的，形态畸形的肢体，"
    "手指融合，静止不动的画面，杂乱的背景，三条腿，背景人很多，倒着走"
)

# Официалният LTXV негативен prompt от ComfyUI template-а.
LTX_NEGATIVE = (
    "low quality, worst quality, deformed, distorted, disfigured, motion smear, motion artifacts, "
    "fused fingers, bad anatomy, weird hand, ugly"
)

# Timesteps от ltxv-2b-0.9.8-distilled.yaml: first_pass + последната стъпка на second_pass, завършващо с 0.
LTX_SIGMAS = "1.0, 0.9937, 0.9875, 0.9812, 0.975, 0.9094, 0.725, 0.4219, 0.0"

WAN_I2V_PROMPT = (
    "A cheerful hand-drawn cartoon girl with big yellow hair in a pink dress stands on a green hill "
    "under a blue sky, she waves her arms happily, her hair and dress sway in the breeze, clouds drift "
    "slowly, gentle camera push-in, smooth natural motion, no text, no logos."
)


def save_video(images_ref, fps, prefix):
    return {
        "57": {"class_type": "CreateVideo", "inputs": {"images": images_ref, "fps": fps}},
        "58": {
            "class_type": "SaveVideo",
            "inputs": {"video": ["57", 0], "filename_prefix": prefix, "format": "auto", "format.codec": "auto"},
        },
    }


def wan(prompt, width, height, length, seed, prefix, image=None, steps=20, cfg=5.0, fps=24):
    wf = {
        "37": {"class_type": "UNETLoader",
               "inputs": {"unet_name": "wan2.2_ti2v_5B_fp16.safetensors", "weight_dtype": "default"}},
        "38": {"class_type": "CLIPLoader",
               "inputs": {"clip_name": "umt5_xxl_fp8_e4m3fn_scaled.safetensors", "type": "wan", "device": "default"}},
        "39": {"class_type": "VAELoader", "inputs": {"vae_name": "wan2.2_vae.safetensors"}},
        "48": {"class_type": "ModelSamplingSD3", "inputs": {"model": ["37", 0], "shift": 8.0}},
        "6": {"class_type": "CLIPTextEncode", "inputs": {"text": prompt, "clip": ["38", 0]}},
        "7": {"class_type": "CLIPTextEncode", "inputs": {"text": WAN_NEGATIVE, "clip": ["38", 0]}},
        "55": {"class_type": "Wan22ImageToVideoLatent",
               "inputs": {"vae": ["39", 0], "width": width, "height": height, "length": length, "batch_size": 1}},
        "3": {"class_type": "KSampler", "inputs": {
            "model": ["48", 0], "seed": seed, "steps": steps, "cfg": cfg, "sampler_name": "uni_pc",
            "scheduler": "simple", "positive": ["6", 0], "negative": ["7", 0], "latent_image": ["55", 0],
            "denoise": 1.0}},
        "8": {"class_type": "VAEDecode", "inputs": {"samples": ["3", 0], "vae": ["39", 0]}},
    }
    if image:
        wf["56"] = {"class_type": "LoadImage", "inputs": {"image": image}}
        wf["55"]["inputs"]["start_image"] = ["56", 0]
    wf.update(save_video(["8", 0], fps, prefix))
    return wf


def ltx(prompt, width, height, length, seed, prefix, fps=24):
    wf = {
        "44": {"class_type": "CheckpointLoaderSimple", "inputs": {"ckpt_name": "ltxv-2b-0.9.8-distilled.safetensors"}},
        "38": {"class_type": "CLIPLoader",
               "inputs": {"clip_name": "t5xxl_fp16.safetensors", "type": "ltxv", "device": "default"}},
        "6": {"class_type": "CLIPTextEncode", "inputs": {"text": prompt, "clip": ["38", 0]}},
        "7": {"class_type": "CLIPTextEncode", "inputs": {"text": LTX_NEGATIVE, "clip": ["38", 0]}},
        "69": {"class_type": "LTXVConditioning",
               "inputs": {"positive": ["6", 0], "negative": ["7", 0], "frame_rate": float(fps)}},
        "70": {"class_type": "EmptyLTXVLatentVideo",
               "inputs": {"width": width, "height": height, "length": length, "batch_size": 1}},
        "73": {"class_type": "KSamplerSelect", "inputs": {"sampler_name": "euler"}},
        "71": {"class_type": "ManualSigmas", "inputs": {"sigmas": LTX_SIGMAS}},
        "72": {"class_type": "SamplerCustom", "inputs": {
            "model": ["44", 0], "add_noise": True, "noise_seed": seed, "cfg": 1.0,
            "positive": ["69", 0], "negative": ["69", 1], "sampler": ["73", 0], "sigmas": ["71", 0],
            "latent_image": ["70", 0]}},
        "8": {"class_type": "VAEDecode", "inputs": {"samples": ["72", 0], "vae": ["44", 2]}},
    }
    wf.update(save_video(["8", 0], fps, prefix))
    return wf


def wan14b(prompt, width, height, length, seed, prefix, image=None, steps=20, split=10, cfg=3.5, shift=5.0, fps=16):
    """Wan 2.2 A14B по официалния ComfyUI template (video_wan2_2_14B_t2v / _i2v), качествен режим БЕЗ
    lightx2v 4-step LoRA: high-noise експерт за стъпки 0..split, low-noise за split..край."""
    kind = "i2v" if image else "t2v"
    wf = {
        "75": {"class_type": "UNETLoader", "inputs": {
            "unet_name": f"wan2.2_{kind}_high_noise_14B_fp8_scaled.safetensors", "weight_dtype": "default"}},
        "76": {"class_type": "UNETLoader", "inputs": {
            "unet_name": f"wan2.2_{kind}_low_noise_14B_fp8_scaled.safetensors", "weight_dtype": "default"}},
        "71": {"class_type": "CLIPLoader",
               "inputs": {"clip_name": "umt5_xxl_fp8_e4m3fn_scaled.safetensors", "type": "wan", "device": "default"}},
        "73": {"class_type": "VAELoader", "inputs": {"vae_name": "wan_2.1_vae.safetensors"}},
        "82": {"class_type": "ModelSamplingSD3", "inputs": {"model": ["75", 0], "shift": shift}},
        "86": {"class_type": "ModelSamplingSD3", "inputs": {"model": ["76", 0], "shift": shift}},
        "89": {"class_type": "CLIPTextEncode", "inputs": {"text": prompt, "clip": ["71", 0]}},
        "72": {"class_type": "CLIPTextEncode", "inputs": {"text": WAN_NEGATIVE, "clip": ["71", 0]}},
    }
    if image:
        wf["97"] = {"class_type": "LoadImage", "inputs": {"image": image}}
        wf["98"] = {"class_type": "WanImageToVideo", "inputs": {
            "positive": ["89", 0], "negative": ["72", 0], "vae": ["73", 0], "start_image": ["97", 0],
            "width": width, "height": height, "length": length, "batch_size": 1}}
        pos, neg, lat = ["98", 0], ["98", 1], ["98", 2]
    else:
        wf["74"] = {"class_type": "EmptyHunyuanLatentVideo",
                    "inputs": {"width": width, "height": height, "length": length, "batch_size": 1}}
        pos, neg, lat = ["89", 0], ["72", 0], ["74", 0]
    common = {"cfg": cfg, "sampler_name": "euler", "scheduler": "simple", "steps": steps,
              "positive": pos, "negative": neg}
    wf["81"] = {"class_type": "KSamplerAdvanced", "inputs": dict(common, model=["82", 0], add_noise="enable",
                noise_seed=seed, latent_image=lat, start_at_step=0, end_at_step=split,
                return_with_leftover_noise="enable")}
    wf["78"] = {"class_type": "KSamplerAdvanced", "inputs": dict(common, model=["86", 0], add_noise="disable",
                noise_seed=0, latent_image=["81", 0], start_at_step=split, end_at_step=10000,
                return_with_leftover_noise="disable")}
    wf["87"] = {"class_type": "VAEDecode", "inputs": {"samples": ["78", 0], "vae": ["73", 0]}}
    wf.update(save_video(["87", 0], fps, prefix))
    return wf


SEED = 20260930

# Размери: кратни на 32 (Wan2.2 VAE 16x + patch 2; LTXV 32x). Кадри: Wan 4n+1, LTX 8n+1 -> 97 и за двата.
WORKFLOWS = {
    "wan22_ti2v5b_text_to_video_480p": wan(SCENE, 832, 480, 97, SEED, "wan22_t2v_480p/wan22_t2v"),
    "wan22_ti2v5b_image_to_video_480p": wan(WAN_I2V_PROMPT, 832, 480, 97, SEED, "wan22_i2v_480p/wan22_i2v",
                                            image="official_example_comfyui_wan22.png"),
    "wan22_ti2v5b_text_to_video_720p": wan(SCENE, 1280, 704, 121, SEED, "wan22_t2v_720p/wan22_t2v_720p"),
    # Wan 2.2 A14B: 16 FPS, 81 кадъра = 5 s (както в официалния template), 480p за първата проба
    "wan22_a14b_text_to_video_480p": wan14b(SCENE, 832, 480, 81, SEED, "wan22_a14b_t2v_480p/wan22_a14b_t2v"),
    "wan22_a14b_image_to_video_480p": wan14b(SCENE, 832, 480, 81, SEED, "wan22_a14b_i2v_480p/wan22_a14b_i2v",
                                             image="official_example_comfyui_wan22.png"),
    "ltxv2b_098_distilled_text_to_video_480p": ltx(SCENE, 832, 480, 97, SEED, "ltxv2b_t2v_480p/ltxv2b_t2v"),
}

if __name__ == "__main__":
    os.makedirs(OUT, exist_ok=True)
    for name, wf in WORKFLOWS.items():
        path = os.path.join(OUT, name + ".api.json")
        with open(path, "w", encoding="utf-8") as f:
            json.dump(wf, f, ensure_ascii=False, indent=2)
        print("written", os.path.relpath(path))
