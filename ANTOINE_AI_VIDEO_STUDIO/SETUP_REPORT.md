# SETUP_REPORT — ANTOINE AI VIDEO STUDIO

Дата: 2026-09-30. Изготвен в облачна сесия на Claude Code.

## 0. Статус (обновено 2026-10-01 след реалните тестове на твоя компютър)

Скриптовете бяха пуснати на машината: RTX 5070 Ti 16 GB, драйвер 616.92 (Game Ready), Ryzen 9 9950X, 64 GB RAM.
Пълните резултати са в `docs/TEST_RESULTS_20261001.md`.

| Задача | Статус |
|---|---|
| Проверка на машината (GPU, драйвер, VRAM, диск, портове, Ollama, Docker) | ✅ Изпълнена |
| ComfyUI 0.38.0 portable, Python 3.13.14, PyTorch 2.14.0+cu130 в `C:\AI\ANTOINE_AI_VIDEO_STUDIO` | ✅ Инсталиран |
| Реален GPU тест (sm_120, fp32 сравнение с CPU, fp16 99,6 / bf16 100,1 TFLOPS, conv3d, attention) | ✅ PASS |
| Модели: 5 файла, 34 GB, всички с проверен SHA256 | ✅ Изтеглени |
| Тест 1: Wan 2.2 T2V 480p | ✅ Генериран, валиден MP4 с движение |
| Тест 2: Wan 2.2 I2V 480p | ⚠ Генериран, валиден MP4 с движение. **Минималната яркост е 0,02 (има почти черен кадър)**, причината се изяснява |
| Тест 3: LTX 2B T2V 480p | ✅ Генериран, валиден MP4 с движение |
| Wan 2.2 T2V 720p | ✅ Генериран, валиден MP4 с движение |
| Спиране на задача (/interrupt) | ✅ Потвърдено: прекъсната на стъпка 5/20 |
| Генерация без външен AI сървър | ✅ 0 външни мрежови връзки на ComfyUI във всички тестове |
| Визуален преглед на качеството от човек | ⏳ Предстои. Метриките не заменят прегледа |

### Измервания

| Тест | Размери / кадри | Общо | Семплиране | VAE decode | Пик VRAM (цялата карта) | Пик RAM ComfyUI |
|---|---|---|---|---|---|---|
| Wan T2V 480p | 832×480 / 97 (4,0 s) | 97,0 s* | 67,3 s (20 × 3,3 s) | 11,9 s | 14 973 MiB | 3,4 GiB |
| Wan I2V 480p | 832×480 / 97 | 80,8 s | 67,4 s | 11,6 s | 15 666 MiB | 3,4 GiB |
| LTX 2B T2V 480p | 832×480 / 97 | **9,7 s** | 3,5 s (8 стъпки) | 2,9 s | 15 682 MiB | 5,2 GiB |
| Wan T2V 720p | 1280×704 / 121 (5,0 s) | 268,9 s | 238,4 s (20 × 11,9 s) | 29,1 s | 14 141 MiB | 12,3 GiB |

\* включва първото зареждане на моделите и кодирането на текста, около 17 s.
Пикът на VRAM е близо до 16 GB, защото динамичното управление на паметта на ComfyUI запълва свободната VRAM
с кеширани тегла, а не защото тя не стига. Нямаше out-of-memory и нито един резервен опит.

## 1. Какво ще бъде инсталирано и къде

```
C:\AI\ANTOINE_AI_VIDEO_STUDIO\
├─ ComfyUI_windows_portable\   официален portable (python_embeded + ComfyUI), създава се от стъпка 2
│  └─ ComfyUI\models\{diffusion_models,text_encoders,vae,checkpoints}   теглата
├─ inputs\      входни снимки (+ official_example_comfyui_wan22.png)
├─ outputs\     MP4 резултати (тестовете са в outputs\tests\)
├─ workflows\ui\   именувани workflows за интерфейса (копират се и в user\default\workflows)
├─ workflows\api\  API exports за бъдеща интеграция
├─ logs\        всички логове, system check, install_info.json, models_manifest.json, test_results_*.json
├─ user\        ComfyUI потребителски настройки и workflows
├─ downloads\   архивът на portable
├─ scripts\ tools\   скриптовете
└─ *.bat        стартови файлове
```

Нищо не се инсталира извън тази папка. Не се пипат глобалният Python, PATH, драйверите, firewall-ът,
pagefile-ът, Docker, Ollama моделите, Open WebUI и проектите ANTOINE AI BUSINESS ARCHITECT, MARKETING DEPARTMENT
и CODEX CORE. Скриптът за проверка само чете от тях. Ако `C:\AI\ANTOINE_AI_VIDEO_STUDIO` вече съществува,
`0_COPY_KIT_TO_C_AI.bat` показва съдържанието и не презаписва нищо.

## 2. Версии и източници (проверени на 2026-09-30)

| Компонент | Версия / файл | Източник |
|---|---|---|
| ComfyUI | 0.38.0 (commit `b65d1ff`, 2026-09-30). Стъпка 2 тегли **последния** release и записва реалната версия в `logs\install_info.json` | github.com/Comfy-Org/ComfyUI, `ComfyUI_windows_portable_nvidia.7z` |
| Python / PyTorch в portable | Според README: Python 3.13 + PyTorch **CUDA 13.0 (cu130)**. Реалните версии се записват от стъпка 2 | README на ComfyUI, раздел „Windows Portable“ |
| Frontend | comfyui-frontend-package 1.53.6 | `requirements.txt` на ComfyUI |
| Workflow templates | comfyui-workflow-templates 0.11.73 | github.com/Comfy-Org/workflow_templates |

**Защо cu130 за RTX 5070 Ti (Blackwell, sm_120):** README на ComfyUI препоръчва за серия 20 и по-нова
PyTorch с CUDA 13.0 и изрично забранява варианта cu126 за тези карти. Старите инструкции за RTX 30xx
(cu118/cu121) не са ползвани. CUDA 13.0 изисква NVIDIA драйвер от клона **R580 или по-нов**.
`1_CHECK_SYSTEM.bat` го проверява. Ако драйверът е по-стар, **скриптовете НЕ го обновяват**: това изисква
твое решение. `gpu_test.py` проверява, че `sm_120` е в arch list-а на PyTorch. После изпълнява реални fp32
(сравнен с CPU), fp16 и bf16 matmul, conv3d и attention на GPU.

## 3. Модели и лицензи

| Модел | Файлове (папка) | Хранилище | Лиценз на теглата |
|---|---|---|---|
| **Wan 2.2 TI2V 5B** | `wan2.2_ti2v_5B_fp16.safetensors` (diffusion_models), `wan2.2_vae.safetensors` (vae) | Comfy-Org/Wan_2.2_ComfyUI_Repackaged (препакетирано от Wan-AI/Wan2.2-TI2V-5B, посочено в официалния ComfyUI template) | Apache 2.0 (Wan-AI) |
| Text encoder за Wan | `umt5_xxl_fp8_e4m3fn_scaled.safetensors` (text_encoders) | Comfy-Org/Wan_2.1_ComfyUI_repackaged | Apache 2.0 (UMT5, Google) |
| **LTX-Video 2B 0.9.8 distilled** | `ltxv-2b-0.9.8-distilled.safetensors` (checkpoints, съдържа VAE) | Lightricks/LTX-Video | **LTXV Open Weights License 0.X** (15.04.2025, важи за v0.9.6 и по-новите). Безплатен за компании с годишен приход **под $10 млн.**; над тази граница е нужен платен търговски лиценз. Ограничения в Attachment A, вкл. **(e): машинно генерираното съдържание трябва изрично да се обозначава като такова**, без deepfakes без съгласие. Потвърдено от `LTX-Video-Open-Weights-License-0.X.txt` на 2026-10-01 |
| Text encoder за LTX | `t5xxl_fp16.safetensors` (text_encoders) | comfyanonymous/flux_text_encoders (посочен в официалния ComfyUI LTXV template) | Apache 2.0 (T5 v1.1, Google) |
| Резерва: Wan 2.1 T2V 1.3B | само с `02_download_models.ps1 -Set wan21_fallback` | Comfy-Org/Wan_2.1_ComfyUI_repackaged | Apache 2.0 |

Лицензите на **кода** са отделно: ComfyUI е GPL-3.0, workflow_templates е MIT, LTX-Video е Apache-2.0.
Те не определят лиценза на теглата.

**Място на диска (приблизително, точните размери се показват от стъпка 3 преди изтеглянето):**
portable архив ~2 GB и разархивиран ~7 GB; Wan ~18 GB; LTX ~16 GB; резерв за кеш и изходи 20 GB.
Общо **~60 GB**. Скриптът спира, ако свободното място е по-малко от нужното плюс 20 GB.
Файл, който вече го има в друга ComfyUI инсталация, не се тегли повторно. Проверява се, че размерът и
SHA256 съвпадат, и той се ползва само за четене чрез `extra_model_paths.yaml` на студиото.

**Бележки за точността:**
- Text encoder-ът на Wan е fp8 (`umt5_xxl_fp8_e4m3fn_scaled`), защото **официалният template** използва
  точно него. Самият Wan 5B модел е fp16. Не е добавена допълнителна quantization и няма ускорители
  (Sage/Triton/TeaCache).
- **LTX 2B 0.9.8 distilled няма официален ComfyUI workflow.** В README на Lightricks колоната
  „ComfyUI workflow“ за този модел е „N/A“. Официалните workflows в ComfyUI-LTXVideo вече са само за
  LTX-2 / 2.3 / 2.5 (22B, Gemma 3), които **не** се използват тук. Затова workflow-ът е изграден от
  вградените LTXV nodes на ComfyUI (както в официалния template `ltxv_text_to_video`). Сигмите са от
  официалния `configs/ltxv-2b-0.9.8-distilled.yaml`: 8 стъпки, guidance 1.
  **Разлика:** официалният inference pipeline е двуетапен (multi-scale със spatial upscaler). Тук се
  ползва **едноетапна** версия без upscaler. Ако резултатът е лош или checkpoint-ът не се зареди,
  тестът ще покаже точната грешка. Моделът **няма** да бъде подменен тихомълком с друг.

## 4. Какво е проверено в облачната среда (без GPU)

- ComfyUI 0.38.0 и frontend 1.53.6 са инсталирани в изолиран venv и пуснати на CPU.
- Всичките 4 API workflows минават валидацията на `/prompt` без `node_errors`. Изпълнението стига до
  зареждането на моделите: тук са празни файлове-заместители, затова спира там, както се очаква.
- UI workflows са генерирани от истинския frontend (headless Chromium). Round-trip UI → API дава същите
  стойности. Workflow-ите се виждат в менюто Workflows и се отварят без грешки.
- `run_tests.py` е пуснат end-to-end с тест без модел: websocket прогрес, време по етапи, история,
  MP4 файл, ffprobe/PyAV проверка, contact sheet. Детекторът правилно маркира статичното видео като
  „статично“. Липсата на GPU се отчита коректно.
- `gpu_test.py`: без GPU връща FAIL с ясна причина, както трябва.
- `download_models.py` е тестван с имитиран HuggingFace сървър. Потвърдени са: изтегляне; продължаване на
  прекъснато изтегляне; повторно пускане без повторно теглене; отказ при повреден наличен файл без да го
  пипа; повторно ползване на файл от друга инсталация без копиране; спиране при недостиг на място.
- `launch.py` слуша **само на 127.0.0.1**, записва pid файл и лог.
- Всички PowerShell скриптове минават parse проверка (PowerShell 7.5). Записани са в UTF-8 с BOM, за да
  се чете правилно кирилицата в Windows PowerShell 5.1.
- **Не е проверено тук:** реалното поведение на Windows cmdlet-ите (Get-CimInstance, Get-NetTCPConnection),
  разархивирането, CUDA, реалните модели, качеството на видеото, `ollama stop`.

## 5. Реални тестове — план и попълване

`4_RUN_TESTS.bat` пуска **последователно**:

1. smoke тест без модел (проверява API, запис на MP4 и проверката на видеото);
2. **Wan 2.2 T2V**: 832×480, 97 кадъра, 24 FPS (~4 s), 20 стъпки, cfg 5, uni_pc/simple, seed 20260930;
3. **Wan 2.2 I2V**: същите параметри, с официалната примерна снимка от ComfyUI templates (MIT). Prompt-ът
   описва движение за нея: тя е рисунка, не хотел;
4. **LTX 2B T2V**: 832×480, 97 кадъра, 24 FPS, 8 стъпки, cfg 1, euler, същият seed и сцена;
5. тест за спиране: стартира Wan T2V и го прекъсва след 20 s;
6. **Wan 720p**: 1280×704, 121 кадъра (5 s). Пуска се само ако тест 2 е успешен.

За всеки тест се записват: checkpoint, workflow, seed, размери, кадри, FPS, стъпки, общо време и време
по фази (зареждане, кодиране, семплиране, декодиране), пикова GPU памет, RAM, външни мрежови връзки,
MP4 път, ffprobe/PyAV данни, метрики за движение и contact sheet PNG.
При out-of-memory има **един** резервен опит с 49 кадъра, после спира.
Пиковата GPU памет е от `nvidia-smi` за цялата карта, включително други програми. Отчита се и прирастът
спрямо началото.

Тестова сцена (T2V): *„A cinematic exterior shot of a small boutique hotel on the French Riviera at golden hour.
Warm sunlight on a cream-colored facade, palm leaves moving gently in the breeze, slow forward camera movement,
realistic architecture, natural motion, no text, no logos.“*

**Резултати:** предстоят. Ще се появят в `TEST_RESULTS.md` и `logs\test_results_*.json`.

## 6. Препоръка (предварителна, преди измервания)

За RTX 5070 Ti 16 GB **основен модел е Wan 2.2 TI2V 5B**:
- един модел покрива и текст → видео, и снимка → видео;
- 720p при 24 FPS е родният му режим;
- 5B параметри във fp16 (~10 GB) влизат в 16 GB с вграденото динамично управление на VRAM и offload,
  без да са нужни 24 GB;
- лицензът Apache 2.0 позволява търговска употреба.

**LTX 2B 0.9.8 distilled** е подходящ за бързи чернови: 8 стъпки срещу 20. Интеграцията за този
checkpoint обаче е неофициална (виж т. 3), а лицензът трябва да се провери.
**Препоръката ще бъде потвърдена или коригирана след реалните измервания.**

## 7. Известни ограничения

- Нищо не е тествано на реалната машина. Възможни са дребни корекции в Windows скриптовете при първото пускане.
- LTX 2B 0.9.8: неофициален едноетапен workflow; лицензът не е потвърден.
- Wan 2.2 5B при 720p и 121 кадъра е бавен: вероятно минути на клип, точното време ще се измери.
  Offload към RAM забавя, но не чупи.
- Визуалното качество се оценява само от човек. Скриптът дава метрики и contact sheet, но **не** обявява
  качеството за проверено.
- Извън обхвата: глас, музика, lip-sync, обучение, 4K upscaling, монтаж, интеграция с
  Business Architect / Marketing Department.

## 8. Безопасно спиране

- Текуща задача: ✕ в интерфейса или `STOP_TASK.bat` (с `-ClearQueue` изчиства и опашката).
- Сървърът: Ctrl+C в прозореца или `STOP_STUDIO.bat`. Спира само процеса с команден ред
  `...\ANTOINE_AI_VIDEO_STUDIO\ComfyUI_windows_portable\ComfyUI\main.py`.
- Ollama модел, разтоварен преди тестовете, се зарежда отново автоматично при следващата заявка към Ollama.
