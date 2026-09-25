# ANTOINE CODEX Immersive — WordPress тема

Версия 1.0.0 · WordPress 6.2+ (тествана на 7.1.2) · PHP 7.4+ (тествана на 8.4) · лиценз GPL v2+

Класическа WordPress тема за ANTOINE CODEX: почти черен фон, индиго и метално злато, оранжев акцент `#FF5500`,
собствено WebGL поле от частици („скрита структура в данните“), разкриване при скрол, светла секция „Méthode“
с преход чрез изрязване, интерактивни карти на услугите, мобилно меню на цял екран и вграден панел за
ANTOINE CODEX CORE. Всички видими текстове по подразбиране са на френски; английските идват от `languages/en_US.mo`
само за EN страниците на Polylang/WPML.

---

## 1. Инсталиране

1. **Първо на копие (staging)** на сайта, не на живия.
2. *Apparence → Thèmes → Ajouter → Téléverser un thème* → `ANTOINE_CODEX_Immersive_WordPress_Theme.zip` → *Installer* → *Activer*.
3. *Apparence → Diagnostic de migration* (появява се известие след активиране). Страницата анализира **старата тема** и съдържанието и показва:
   - шорткодове, REST маршрути, AJAX действия, скриптове и шаблони, дефинирани в старата тема (изчезват при смяна на темата);
   - шорткодове и блокове, които се ползват в публикуваното съдържание, но вече не са налични (напр. Quick Diagnostic, ако е бил в старата тема);
   - открити ключови страници, активни плъгини (SEO, формуляри, мултиезичност, AI, кеш), състояние на CORE и менюта.
   Всичко, което е означено като „от старата тема“, трябва да се премести в mu-plugin **преди** публикуване (вж. т. 6).
4. *Apparence → Personnaliser → ANTOINE CODEX — Thème*:
   - **Pages clés**: изберете страниците „Diagnostic d’entreprise & Stratégie de croissance“, Services, Méthodologie, Fondateur, Contact и URL на Quick Diagnostic.
     Без избор темата ги открива сама по адрес/заглавие. Избраните страници автоматично получават съответния immersive шаблон
     (освен ако в редактора изрично е избран друг шаблон от тази тема).
   - **Coordonnées & réseaux**: e-mail, телефон, адрес, социални мрежи — празните полета не се показват (темата не измисля данни).
   - **Accueil**: всички текстове на началната страница са редактируеми; празно поле = текстът по подразбиране.
   - **Contenu de la page d’accueil statique**: съществуващото съдържание на началната страница не се изтрива; можете да го покажете под секциите или вместо тях.
   - **Effets visuels**: WebGL вкл./изкл., плътност на частиците, hover ефекти.
5. *Apparence → Menus*: задайте менюта за „Navigation principale“, „Pied de page“, „Liens légaux“ (без меню темата показва автоматично ключовите страници).
6. *Apparence → ANTOINE CODEX CORE*: свързване на чатбота (т. 2).

Quick Diagnostic: ако няма отделна страница или зададен URL, бутоните водят към `страница Diagnostic#quick-diagnostic`.
Добавете в редактора *HTML anchor* `quick-diagnostic` на блока, който съдържа съществуващия Quick Diagnostic.

## 2. ANTOINE CODEX CORE — какво е нужно, за да работи

**Важно:** в предоставеното хранилище `antoinecodex7/antoinecodex7-repo` нямаше нито един файл (празно, без клонове), а
мрежата на средата блокира и сайта. Затова **кодът на CORE не беше намерен и реалният бот не е свързан, нито тестван**.
Темата не съдържа демонстрационен бот: панелът показва само отговорите на вашия CORE.
Необходимо е да ми предоставите едно от следните: пътя/хранилището на кода на CORE (плъгин, тема или сървър),
неговия API endpoint и формат, или името на JS функцията на съществуващия widget.

Темата поддържа 4 режима (*Apparence → ANTOINE CODEX CORE*):

| Режим | Кога | Какво прави темата |
|---|---|---|
| **Passerelle HTTP** | CORE е отделен сървър/API (Node, Python, локален AI и т.н.) | Браузърът вика само `POST /wp-json/antoine-codex/v1/core/message`; WordPress препраща **от сървъра** към CORE с ключа. Ключът и адресът никога не стигат до HTML/JS. |
| **Extension PHP** | CORE е WordPress плъгин | Панелът вика плъгина чрез филтъра `antoine_codex_core_reply`. |
| **Widget existant** | CORE вече има собствен JS widget | Темата не рисува свой панел; нейният бутон отваря съществуващия widget (JS функция или CSS селектор) и скрива стария бутон. |
| **Désactivé** | — | Без бутон и без скриптове. |

Докато CORE не е конфигуриран, панелът е видим **само за администратори** (с предупреждение); посетителите не виждат нищо.

**Препоръчителна конфигурация** (ключът е в `wp-config.php`, а не в базата):

```php
define( 'ACX_CORE_MODE', 'proxy' );
define( 'ACX_CORE_ENDPOINT', 'https://core.example.fr/api/chat' ); // достъпен от хостинга
define( 'ACX_CORE_TOKEN', '...' );            // изпраща се като "Authorization: Bearer ..."
// define( 'ACX_CORE_AUTH_HEADER', 'X-API-Key' ); // ако CORE очаква друг header
// define( 'ACX_CORE_FORMAT', 'openai' );         // за /v1/chat/completions (Ollama, LM Studio, vLLM)
// define( 'ACX_CORE_MODEL', 'antoine-codex-core' );
```

**Формат „générique“** — WordPress изпраща:

```json
{ "message": "…", "session_id": "…", "history": [{"role":"user|assistant","content":"…"}],
  "context": { "page_url": "…", "page_title": "…", "source": "chat|quick-diagnostic", "locale": "fr_FR", "diagnostic": "…" },
  "site": "https://…" }
```

и приема `{ "reply": "…", "suggestions": ["…"], "session_id": "…" }` (също `response`, `answer`, `output`, `text`,
`message.content` на Ollama или `choices[0].message.content`; обикновен текст също се приема). Отговорът се показва с безопасен
Markdown (удебеляване, списъци, връзки). Streaming не се поддържа — отговорът се показва наведнъж.

**Локален AI / домашен компютър:** адресът се вика от сървъра на хостинга, затова `localhost`/`192.168.x.x` работи само ако CORE е
на същия сървър. Иначе е нужен защитен тунел (Cloudflare Tunnel, Tailscale Funnel и др.) с HTTPS и токен.

**Защита:** проверка на Origin/Referer, лимит на съобщенията на посетител (по подразбиране 30 за 10 мин., 429 при превишаване),
максимум 2000 знака, история до 12 съобщения, `Cache-Control: no-store`. Зад Cloudflare/CDN подайте реалния IP
чрез филтъра `acx_client_ip`, иначе всички посетители делят един лимит.
Бутонът *Tester la connexion* изпраща реално тестово съобщение от сървъра и показва отговора и времето.

**Quick Diagnostic ↔ CORE:** Quick Diagnostic и разговорът са ясно разграничени (карти A/B/C на началната страница и страницата
Diagnostic; в панела има отделна връзка към Quick Diagnostic). За да предаде резултата на CORE, съществуващият Quick Diagnostic
трябва да изпрати в края:

```js
document.dispatchEvent( new CustomEvent( 'acx:quick-diagnostic:complete', {
  detail: { summary: 'текст или JSON обект на резултата', open: true }
} ) );
```

Панелът се отваря и предлага „Analyser mon résultat avec CORE“; резултатът се изпраща в `context.diagnostic`.
JS API: `ACXCore.open()`, `ACXCore.close()`, `ACXCore.send('…')`; всяка връзка `#antoine-codex-core` или елемент `[data-acx-core-open]` отваря панела.

## 3. FR/EN, SEO, формуляри

- **FR/EN:** превключвателят се показва само ако е активен Polylang, WPML или TranslatePress. Ключовите страници се свързват към превода им.
  Текстовете от Customizer, които сте променили, се регистрират за превод в *Langues → Traductions* (Polylang) / String Translation (WPML).
  Без мултиезичен плъгин сайтът остава изцяло на френски, дори ако езикът на WordPress е English.
- **SEO:** ако е активен Yoast, Rank Math, AIOSEO, SEOPress, The SEO Framework, Slim SEO или SmartCrawl, темата **не добавя** мета тагове.
  Без SEO плъгин тя добавя description, Open Graph и JSON-LD `ProfessionalService` (Nice, Côte d’Azur) само с попълнените данни.
- **Формуляри:** съществуващите формуляри (Contact Form 7, WPForms, Gravity Forms, Fluent Forms, Formidable) остават и получават стила на темата.
  Резервен формуляр на темата `[acx_contact_form]` е изключен по подразбиране (honeypot, минимално време, лимит, RGPD съгласие,
  изпращане по e-mail + архив в *Messages*, за да не се губят запитвания).
- **Редактор:** стилове на блокове (Panneau, Section claire, Étiquette monospace, Chapeau, Contour, Étapes numérotées) и
  3 шаблона в категория „ANTOINE CODEX“ за оформяне на съществуващото съдържание. Шаблон „Pleine largeur“ за Elementor и др.

## 4. Проверки (реално изпълнени)

Среда: WordPress 7.1.2 + SQLite, PHP 8.4, Chromium (Playwright) с софтуерен WebGL (SwiftShader).
Тестов сайт с **симулирана** стара тема (шорткод `[quick_diagnostic]`, REST маршрут, AJAX, скрипт, шаблон), Contact Form 7,
тестов Quick Diagnostic (mu-plugin) и **симулиран** CORE сървър. Резултати:

| Проверка | Резултат |
|---|---|
| Инсталиране чрез *Téléverser un thème* (истинския UI) + активиране | ✅ „Theme installed successfully“, активирана; повторено на чиста инсталация |
| `php -l` на всички 35 PHP файла | ✅ |
| PHPCompatibilityWP за PHP 7.4+ | ✅ 0 грешки |
| WordPress Coding Standards — sniffs за сигурност (escaping, sanitizing, nonce, SQL, i18n) | ✅ 0 грешки |
| Theme Check (официалният инструмент) | ⚠️ остават 2 „REQUIRED“: `register_post_type` и `add_shortcode` („plugin territory“) — важи само за каталога wordpress.org (вж. ограничения) |
| `node --check` на JS | ✅ |
| Функционални тестове (desktop 1440×900 + mobile 390×844) | ✅ 43/43: навигация и вътрешни връзки (HTTP 200), skip link, CORE: изпращане/получаване, „зарежда“, markdown, история, грешка 500 + „Réessayer“, timeout, Escape/фокус, запазване на разговора между страниците, нов разговор; Quick Diagnostic → CORE с `context.diagnostic`; изпращане на CF7; мобилно меню (отваряне, inert, Escape, линк); CORE на цял екран на телефон; `prefers-reduced-motion` (без WebGL, всичко видимо); без JavaScript (всичко видимо, H1 в HTML) |
| Администрация | ✅ 16/16: диагностика на миграцията открива всички симулирани функции на старата тема; ключът не се показва; тест на връзката; ключът се запазва след запис; Customizer с преглед |
| Режими на CORE и сигурност | ✅ 16/16 + лимит: hook, widget (функция и селектор), off (503), чужд Origin 403, празно/дълго 400, тестовият маршрут изисква вход, 4-та заявка при лимит 3 → 429; резервен формуляр |
| Ключ/endpoint в публичния HTML | ✅ не присъстват |
| Хоризонтален overflow на телефон | ✅ няма (390/390) |
| Polylang (реален, версия 3.9-beta1 от GitHub) | ✅ FR/EN превключвател, EN URL на ключовите страници, EN текстове на EN страници, FR по подразбиране |
| PHP грешки/предупреждения от темата в `debug.log` | ✅ няма (само предупреждения, че wordpress.org е недостъпен от средата) |

## 5. Какво НЕ можа да се тества (честно)

- **Реалният ANTOINE CODEX CORE, реалният Quick Diagnostic, съществуващата тема, страници, форми, SEO настройки и FR/EN на живия сайт** —
  хранилището е празно, сайтът не е достъпен от средата. Интеграциите са тествани само със симулации. **Интеграцията с CORE не е завършена**,
  докато не бъде свързан и тестван реалният бот.
- **Референтният сайт zeriotic.com** не можа да бъде отворен (мрежата на средата го блокира); визията е изградена по вашето описание.
- Реални устройства, Safari/iOS, Firefox, истински GPU (тествано само в Chromium с софтуерен WebGL); реално изпращане на e-mail
  (няма sendmail — писмата са прихванати); WPML, TranslatePress, Elementor, SEO плъгините (засичането им е по константи/класове);
  WordPress по-стар от 7.1.2; PHP по-стар от 8.4 (само статичен анализ за 7.4+); cache плъгини/CDN.

## 6. Известни ограничения

- **Функции в темата:** мостът към CORE, резервният формуляр и диагностиката на миграцията са в темата, за да работят след едно качване.
  Ако по-късно смените темата, те ще изчезнат (точно проблемът, който диагностиката открива сега). По-устойчиво е да се изнесат в отделен
  плъгин — мога да го направя. Поради това темата не е за каталога wordpress.org (Theme Check „plugin territory“).
- Функции от старата тема (напр. шорткод на Quick Diagnostic) не могат да се пренесат автоматично: диагностиката ги посочва; кодът им трябва да
  се премести в `wp-content/mu-plugins/`. Ако ми дадете файловете на старата тема, ще го направя.
- Без streaming на отговорите на CORE; без запазване на разговорите на сървъра (само в `sessionStorage` на посетителя за текущата сесия).
- Лимитът на CORE е по IP (вж. `acx_client_ip` при CDN). При пълно кеширане на страниците REST маршрутът не се кешира (`no-store`).
- WebGL ефектът не се зарежда при `prefers-reduced-motion`, „Save-Data“ или липса на WebGL; на слаби устройства плътността и резолюцията
  се намаляват автоматично; анимацията спира извън екрана и в неактивен таб.
- Текстът „Fondateur“ на началната страница е общ; биографията идва само от вашата страница „Fondateur“ (темата не измисля данни).

## 7. Файлове и лицензи

```
antoine-codex-immersive/
  style.css, functions.php, theme.json, screenshot.jpg, readme.txt, README.md
  header.php, footer.php, front-page.php, page.php, single.php, index.php, 404.php, searchform.php, comments.php
  page-templates/  template-diagnostic.php, -services, -methodologie, -fondateur, -contact, -fullwidth
  template-parts/  front/*.php (hero, manifesto, services, diagnostic, method, territory, founder), content-card.php
  inc/             helpers, setup, enqueue, customizer, template-tags, multilingual, seo, blocks, forms, core-bridge, admin
  assets/css/      main.css (15.5 KB gzip), fonts.css, editor.css
  assets/js/       main.js (4.2 KB gzip), field.js (7.8 KB gzip, зарежда се отложено), core-chat.js (5.2 KB gzip)
  assets/fonts/    Syne, Manrope, JetBrains Mono (woff2, локално — без Google Fonts, RGPD) + лицензи OFL
  assets/img/      field-poster.webp/.jpg (статичен вариант на полето)
  languages/       antoine-codex-immersive.pot, en_US.po, en_US.mo
```

Без външни JS библиотеки (без Three.js/GSAP) — WebGL полето и анимациите са собствен код (GPL v2+).
Шрифтове: SIL Open Font License 1.1 (файловете `assets/fonts/OFL-*.txt`).
