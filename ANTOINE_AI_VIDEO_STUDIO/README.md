# ANTOINE AI VIDEO STUDIO — локално AI видео (ComfyUI)

Пакет за **изцяло локално** генериране на кратки видеа от текст и от снимка за ART STUDIO AZUR и ANTOINE CODEX.
Интерфейс: официалният **ComfyUI Windows Portable (NVIDIA)**. Модели: **Wan 2.2 TI2V 5B** (основен) и
**LTX-Video 2B 0.9.8 distilled** (бързи чернови). Без платени API, без облак, само `127.0.0.1`.

> **Статус:** скриптовете и workflows са подготвени и проверени в облачна среда без GPU (виж `SETUP_REPORT.md`).
> **Реалните видео генерации още НЕ са изпълнени** — те стават на твоя компютър със стъпки 1–4 по-долу.

## Инсталиране (≈ 1–2 часа, основно изтегляне ~40 GB)

1. Изтегли пакета: в GitHub отвори `antoinecodex7/antoinecodex7-repo`, клон `claude/inspiring-edison-a74cl3`
   → **Code → Download ZIP**. Разархивирай и отвори папката `ANTOINE_AI_VIDEO_STUDIO`.
2. Пусни **`0_COPY_KIT_TO_C_AI.bat`** — копира пакета в `C:\AI\ANTOINE_AI_VIDEO_STUDIO`.
   Съществуващи файлове не се презаписват (новата версия става `*.kit_new`).
3. От `C:\AI\ANTOINE_AI_VIDEO_STUDIO` пусни по ред:

| Файл | Какво прави | Променя ли системата? |
|---|---|---|
| `1_CHECK_SYSTEM.bat` | GPU, драйвер, свободна VRAM, RAM, диск, други ComfyUI, заети портове, Ollama, Docker, WSL | Не, само чете |
| `2_INSTALL_COMFYUI.bat` | Тегли официалния portable от GitHub release, проверява SHA256, разархивира, записва версиите, **реален GPU тест** | Само в студио папката |
| `3_DOWNLOAD_MODELS.bat` | Wan 2.2 5B, после LTX 2B, от официалните HF хранилища, с проверка на място и SHA256 | Само в студио папката |
| `4_RUN_TESTS.bat` | Wan T2V → Wan I2V → LTX T2V (480p) → тест за спиране → Wan 720p | Пита преди да разтовари Ollama модел |

Ако нещо спре, логовете са в `logs\`. Изпрати ми `logs\*.log`, `logs\*.json` и `TEST_RESULTS.md`.

## Wan 2.2 A14B (по-високо качество, по-бавно)

1. `5_DOWNLOAD_WAN14B.bat`: изтегля ~57 GB, T2V и I2V.
2. `6_TEST_WAN14B.bat`: реален тест 480p. Въведи името на снимката от `inputs\`, например `DOkDu.jpg`,
   или натисни Enter за официалния пример.
3. В интерфейса: workflows `wan22_a14b_text_to_video_480p` и `wan22_a14b_image_to_video_480p`.

## Видео по сценарий (AI агент)

`7_VIDEO1_AGENT.bat`: генерира 6-те сцени на „Мечтата, която зацикли“ от снимките `inputs\1.JPG`–`6.JPG`,
проверява ги и ги слепва в един MP4. Пълни инструкции: **`docs\STORYBOARD_STEPS.md`**.

## Ежедневна употреба

- **Старт:** `START_STUDIO.bat` → отваря `http://127.0.0.1:8188` (или следващия свободен порт; точният URL е в заглавието на прозореца и в `logs\comfyui_server.json`).
- **Workflows:** лявото меню **Workflows** показва:
  - `wan22_ti2v5b_text_to_video_480p` — текст → видео (бърз тест)
  - `wan22_ti2v5b_image_to_video_480p` — снимка → видео
  - `wan22_ti2v5b_text_to_video_720p` — текст → видео 720p (1280×704, 121 кадъра, 5 s)
  - `ltxv2b_098_distilled_text_to_video_480p` — бърза чернова с LTX
- **Текст → видео:** отвори workflow → смени текста в горния *CLIP Text Encode* → **Run**. Прогресът и preview се виждат на KSampler, а готовото видео се показва в *Save Video* и в панела Queue/Assets без refresh (стандартно поведение на ComfyUI; ще се потвърди при първия реален тест).
- **Снимка → видео:** сложи снимката в `inputs\` (или *choose file to upload* в *Load Image*) → опиши движението в prompt-а → **Run**. Wan 2.2 5B използва същия модел за T2V и I2V; без снимка е T2V.
- **Резултати:** `outputs\` (MP4).
- **Спиране на задача:** бутонът ✕ до Run в интерфейса или `STOP_TASK.bat` (`/interrupt`). Сървърът остава да работи.
- **Спиране на студиото:** Ctrl+C в прозореца на ComfyUI или `STOP_STUDIO.bat`. Спира **само** нашия процес. Ollama, Docker и Open WebUI не се пипат.
- **API за бъдеща интеграция:** `workflows\api\*.api.json` — POST към `http://127.0.0.1:<порт>/prompt` с `{"prompt": <json>}`. Пример за клиент: `tools\run_tests.py`.

## Правила за параметрите

| | Wan 2.2 TI2V 5B | LTX-Video 2B 0.9.8 distilled |
|---|---|---|
| Размери | кратни на 32 (832×480, 1280×704) | кратни на 32 |
| Кадри | 4n+1 (49, 97, 121) | 8n+1 (49, 97, 121) |
| FPS | 24 | 24 |
| Семплер | 20 стъпки, cfg 5, uni_pc / simple, shift 8 | 8 стъпки (ManualSigmas от официалния config), cfg 1, euler |
| Prompt | подробно описание на сцената и движението | дълго, подробно описание (моделът го изисква) |

При недостиг на памет: първо намали кадрите (97 → 49), после резолюцията. Не се пипат pagefile или системни настройки.

## Безопасност

- Слуша само на `127.0.0.1`. Няма `--listen 0.0.0.0`, няма firewall правила, няма тунели.
- `--disable-api-nodes`: платените облачни nodes са изключени и frontend-ът не комуникира с интернет.
  Сървърът работи с `HF_HUB_OFFLINE=1`.
- Не се използва глобалният Python. Всичко е в `ComfyUI_windows_portable\python_embeded`.
- Няма custom nodes, само вградените в ComfyUI.

Пълни подробности, версии, лицензи и статус: **`SETUP_REPORT.md`**.
