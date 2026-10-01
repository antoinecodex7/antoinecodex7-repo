# Резултати от реалните тестове

Генерира се автоматично от `tools/run_tests.py`. Автоматичните метрики НЕ заменят визуален преглед на MP4 от човек.

## Пускане 20261001_153258

ComfyUI 0.38.0, PyTorch 2.14.0+cu130, Python 3.13.14, устройства: cuda:0 NVIDIA GeForce RTX 5070 Ti : cudaMallocAsync

### Smoke test без модел (не е AI генерация) — **success** (опит 1)

- Checkpoint: `-`
- Workflow: `workflows/api/(вграден smoke)`
- Seed None, NonexNone, None кадъра, 24 FPS, стъпки None, cfg None, sampler None
- Време общо: 0.07 s; по фази: {'other': 0.02, 'save': 0.0}
- GPU памет (nvidia-smi, цялата карта): базово 1209 MiB, пик 1209 MiB от 16303 MiB (прираст 0 MiB)
- RAM: пик ComfyUI процес 1.02 GiB, пик системно използвана 21.92 GiB
- Външни мрежови връзки на ComfyUI по време на задачата: няма
- MP4: `C:\AI\ANTOINE_AI_VIDEO_STUDIO\outputs\tests\20261001_153258_smoke_no_model_00001_.mp4`
- Contact sheet: `C:\AI\ANTOINE_AI_VIDEO_STUDIO\outputs\tests\20261001_153258_smoke_no_model_00001__contact_sheet.png`
- Видео поток: h264 256x256, 24.0 FPS, 49 кадъра, 2.042 s; ffprobe: няма (проверено с PyAV)
- Движение: средна разлика между кадри 0.0, първи/последен 0.0; яркост [117.67, 117.67] — **ПРОБЛЕМ: статично (няма движение)**
- Визуален преглед от човек: _предстои_

### Wan 2.2 TI2V 5B - text-to-video 480p — **success** (опит 1)

- Checkpoint: `wan2.2_ti2v_5B_fp16.safetensors (+ umt5_xxl_fp8_e4m3fn_scaled, wan2.2_vae)`
- Workflow: `workflows/api/wan22_ti2v5b_text_to_video_480p.api.json`
- Seed 20260930, 832x480, 97 кадъра, 24 FPS, стъпки 20, cfg 5, sampler uni_pc / simple
- Време общо: 97.0 s; по фази: {'load': 1.14, 'encode': 16.0, 'other': 0.0, 'sampling': 67.27, 'decode': 11.88, 'save': 0.0}
- GPU памет (nvidia-smi, цялата карта): базово 1359 MiB, пик 14973 MiB от 16303 MiB (прираст 13614 MiB)
- RAM: пик ComfyUI процес 3.4 GiB, пик системно използвана 27.17 GiB
- Външни мрежови връзки на ComfyUI по време на задачата: няма
- MP4: `C:\AI\ANTOINE_AI_VIDEO_STUDIO\outputs\tests\20261001_153258_wan_t2v_00001_.mp4`
- Contact sheet: `C:\AI\ANTOINE_AI_VIDEO_STUDIO\outputs\tests\20261001_153258_wan_t2v_00001__contact_sheet.png`
- Видео поток: h264 832x480, 24.0 FPS, 97 кадъра, 4.042 s; ffprobe: няма (проверено с PyAV)
- Движение: средна разлика между кадри 2.673, първи/последен 9.454; яркост [157.31, 162.41] — **OK по автоматичните метрики**
- Визуален преглед от човек: _предстои_

### Wan 2.2 TI2V 5B - image-to-video 480p — **success** (опит 1)

- Checkpoint: `wan2.2_ti2v_5B_fp16.safetensors (+ umt5_xxl_fp8_e4m3fn_scaled, wan2.2_vae)`
- Workflow: `workflows/api/wan22_ti2v5b_image_to_video_480p.api.json`
- Seed 20260930, 832x480, 97 кадъра, 24 FPS, стъпки 20, cfg 5, sampler uni_pc / simple
- Време общо: 80.78 s; по фази: {'load': 0.05, 'encode': 0.96, 'sampling': 67.43, 'decode': 11.64, 'save': 0.0}
- GPU памет (nvidia-smi, цялата карта): базово 8144 MiB, пик 15666 MiB от 16303 MiB (прираст 7522 MiB)
- RAM: пик ComfyUI процес 3.42 GiB, пик системно използвана 27.16 GiB
- Външни мрежови връзки на ComfyUI по време на задачата: няма
- MP4: `C:\AI\ANTOINE_AI_VIDEO_STUDIO\outputs\tests\20261001_153258_wan_i2v_00001_.mp4`
- Contact sheet: `C:\AI\ANTOINE_AI_VIDEO_STUDIO\outputs\tests\20261001_153258_wan_i2v_00001__contact_sheet.png`
- Видео поток: h264 832x480, 24.0 FPS, 97 кадъра, 4.042 s; ffprobe: няма (проверено с PyAV)
- Движение: средна разлика между кадри 12.405, първи/последен 51.417; яркост [0.02, 184.02] — **OK по автоматичните метрики**
- Визуален преглед от човек: _предстои_

### LTX-Video 2B 0.9.8 distilled - text-to-video 480p — **success** (опит 1)

- Checkpoint: `ltxv-2b-0.9.8-distilled.safetensors (+ t5xxl_fp16)`
- Workflow: `workflows/api/ltxv2b_098_distilled_text_to_video_480p.api.json`
- Seed 20260930, 832x480, 97 кадъра, 24 FPS, стъпки 8, cfg 1, sampler euler
- Време общо: 9.74 s; по фази: {'load': 1.52, 'encode': 1.26, 'other': 0.0, 'sampling': 3.5, 'decode': 2.85, 'save': 0.0}
- GPU памет (nvidia-smi, цялата карта): базово 7993 MiB, пик 15682 MiB от 16303 MiB (прираст 7689 MiB)
- RAM: пик ComfyUI процес 5.16 GiB, пик системно използвана 28.66 GiB
- Външни мрежови връзки на ComfyUI по време на задачата: няма
- MP4: `C:\AI\ANTOINE_AI_VIDEO_STUDIO\outputs\tests\20261001_153258_ltx_t2v_00001_.mp4`
- Contact sheet: `C:\AI\ANTOINE_AI_VIDEO_STUDIO\outputs\tests\20261001_153258_ltx_t2v_00001__contact_sheet.png`
- Видео поток: h264 832x480, 24.0 FPS, 97 кадъра, 4.042 s; ffprobe: няма (проверено с PyAV)
- Движение: средна разлика между кадри 1.396, първи/последен 37.953; яркост [122.7, 125.54] — **OK по автоматичните метрики**
- Визуален преглед от човек: _предстои_

### Тест за спиране на задача (/interrupt) — **interrupted** (опит 1)

- Checkpoint: `wan2.2_ti2v_5B_fp16.safetensors (+ umt5_xxl_fp8_e4m3fn_scaled, wan2.2_vae)`
- Workflow: `workflows/api/wan22_ti2v5b_text_to_video_480p.api.json`
- Seed 20260930, 832x480, 97 кадъра, 24 FPS, стъпки 20, cfg 5, sampler uni_pc / simple
- Време общо: 22.48 s; по фази: {'load': 1.02, 'encode': 2.42, 'other': 0.0}
- GPU памет (nvidia-smi, цялата карта): базово 11221 MiB, пик 14662 MiB от 16303 MiB (прираст 3441 MiB)
- RAM: пик ComfyUI процес 3.86 GiB, пик системно използвана 27.52 GiB
- Външни мрежови връзки на ComfyUI по време на задачата: няма
- Визуален преглед от човек: _предстои_


## Пускане 20261001_153633

ComfyUI 0.38.0, PyTorch 2.14.0+cu130, Python 3.13.14, устройства: cuda:0 NVIDIA GeForce RTX 5070 Ti : cudaMallocAsync

### Wan 2.2 TI2V 5B - text-to-video 720p — **success** (опит 1)

- Checkpoint: `wan2.2_ti2v_5B_fp16.safetensors (+ umt5_xxl_fp8_e4m3fn_scaled, wan2.2_vae)`
- Workflow: `workflows/api/wan22_ti2v5b_text_to_video_720p.api.json`
- Seed 20260930, 1280x704, 121 кадъра, 24 FPS, стъпки 20, cfg 5, sampler uni_pc / simple
- Време общо: 268.93 s; по фази: {'encode': 0.0, 'sampling': 238.44, 'decode': 29.08, 'save': 0.0}
- GPU памет (nvidia-smi, цялата карта): базово 11870 MiB, пик 14141 MiB от 16303 MiB (прираст 2271 MiB)
- RAM: пик ComfyUI процес 12.34 GiB, пик системно използвана 33.24 GiB
- Външни мрежови връзки на ComfyUI по време на задачата: няма
- MP4: `C:\AI\ANTOINE_AI_VIDEO_STUDIO\outputs\tests\20261001_153633_wan_t2v_720p_00001_.mp4`
- Contact sheet: `C:\AI\ANTOINE_AI_VIDEO_STUDIO\outputs\tests\20261001_153633_wan_t2v_720p_00001__contact_sheet.png`
- Видео поток: h264 1280x704, 24.0 FPS, 121 кадъра, 5.042 s; ffprobe: няма (проверено с PyAV)
- Движение: средна разлика между кадри 5.122, първи/последен 17.745; яркост [153.15, 163.79] — **OK по автоматичните метрики**
- Визуален преглед от човек: _предстои_

