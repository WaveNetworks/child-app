# JavaScript System

> **RE-ENGINEERING REQUIRED: Replace with PWT Game Loop Architecture**
>
> The current JS system is the weakest part of the application. It must be rebuilt using the game loop pattern from the [PWT app](https://github.com/WaveNetworks/pwt). The key problems and their PWT solutions:
>
> 1. **Three independent RAF loops (lotus-canvas, water-ripples, pong-game)** — PWT uses a single `requestAnimationFrame` chain (`game-loop.js`) that processes subsystems, levels, and particles in one tick. Every canvas/animation module registers as a subsystem with a `draw(dt, now)` method. No component runs its own RAF.
>
> 2. **No level lifecycle** — PWT has `registerLevel('pageName', {enter, exit})` so each view gets clean setup/teardown. On SPA navigation, `exitLevel()` auto-clears level-scoped timers and particles, then `enterLevel()` starts the new page. ElevateHER has no equivalent — the chat, explorer, and lotus views don't have enter/exit hooks, creating zombie timers and orphaned event listeners.
>
> 3. **No managed timer system** — PWT wraps `setTimeout`/`setInterval` with `GameLoop.setTimeout(fn, delay, levelScoped)`. Level-scoped timers are bulk-cleared on navigation. ElevateHER uses raw `setTimeout` everywhere with no cleanup.
>
> 4. **No particle system** — PWT has `GameLoop.addParticle({life, update, onDeath, levelScoped})` for ephemeral animated elements. ElevateHER manages falling petals and ripples with ad-hoc arrays.
>
> 5. **Module coupling via window globals** — `window.lps` is the single point of coupling. chat-overlay.js and explorer-view.js reach into `lps.canvas._petalStates`, etc. PWT uses CustomEvents for inter-module communication. **Caveat:** water-ripples.js reads `lps.canvas._windVector` at ~30fps synchronously for parallax sway — CustomEvents are the wrong abstraction for continuous animation data. When migrating to the game loop, water-ripples should read the wind vector from the subsystem context (shared state within the tick), not via events. Explorer-view's discrete calls (`lps.setViewState()`, `lps.pulse()`) are safe to convert to events.
>
> 6. **Monolithic files** — lotus-canvas.js (1,833 lines), chat-overlay.js (895 lines), and explorer-view.js (902 lines) each mix API calls, DOM manipulation, state management, and animation. PWT keeps subsystems small and focused.
>
> 7. **console.log spam** — chat-overlay has 50+ unguarded console.log statements. No debug flag.
>
> 8. **Dimension color duplication** — `DIM_COLORS` is defined identically in chat-overlay.js and explorer-view.js. Should be a shared constant.
>
> **Target Architecture (from PWT):**
> ```
> game-loop.js          — Single RAF tick: subsystems → active level → particles
>   ├── registerSubsystem('name', {draw})     — Persistent (survives navigation)
>   ├── registerLevel('page', {enter, exit})  — Per-view lifecycle
>   ├── addParticle({life, update, onDeath})  — Managed ephemeral entities
>   ├── setTimeout(fn, delay, levelScoped)    — Auto-cleared on level exit
>   └── setInterval(fn, delay, levelScoped)   — Auto-cleared on level exit
> ```
>
> **Migration plan:** Create `game-loop.js` first. Convert lotus-canvas, water-ripples, bg-canvas, and pong-game to subsystems. Convert chat-overlay and explorer-view to levels. Replace all raw setTimeout/setInterval with managed versions. Replace window.lps coupling with CustomEvents.

---

## File Inventory

### Core Application JS (assets/js/)

| File | Lines | Purpose |
|------|-------|---------|
| lotus-canvas.js | 1,833 | Lotus flower visualization: radial petal rings, LMS state, falling petals, wind sway, LotusProgressSystem |
| chat-overlay.js | 895 | Assessment chat UI: session management, message rendering, MC choices, content banners, admin debug |
| explorer-view.js | 902 | Stage exploration compass: stage tiles, dimension bars, hard wall dividers, KPI accordion, WP7 relay |
| bg-canvas.js | 194 | Full-viewport background: nebula gradient, grid overlay, theme-aware via MutationObserver |
| water-ripples.js | 209 | Water animation: radial ripple rings, caustic stripe shimmer, parallax sway tied to lotus wind |
| pong-game.js | 379 | Self-playing Pong overlay: ball physics, paddle AI, particle effects, rally counter |
| spa-nav.js | 299 | SPA navigation: AJAX view loading, history API, script re-execution, `spa:contentLoaded` event |
| modal.js | 97 | AJAX modal loader: `modalView()`, `reloadModalThisForm()` |
| toast.js | 109 | Bootstrap 5 toast wrapper: `showToast()`, session flash integration |
| search.js | 115 | Live nav search: 300ms debounce, `apiPost('liveSearch')` |
| page-nav.js | 94 | Secondary sidebar toggle: desktop inline / mobile drawer, localStorage state |
| tabdrop.js | 178 | Responsive tab overflow: measures widths, moves to "More" dropdown |
| theme.js | 32 | Forces DSWA theme: sets cookie, removes theme selector |
| sw.js | 7 | Service worker proxy for push notifications |

**Total custom JS:** ~5,342 lines across 14 files

### Admin-Provided JS (loaded from /admin/)

| File | Purpose |
|------|---------|
| bs-init.js | Bootstrap init, `apiPost()` helper |
| sidebar.js | Main sidebar collapse/expand |
| color-mode.js | Dark/light mode toggle |
| error-reporter.js | Client error reporting |
| notifications.js | Push notification registration |

---

## Load Order (views/template.php)

```
1.  Bootstrap 5.3.2 bundle (CDN)
2.  bs-init.js (admin)         — provides apiPost()
3.  error-reporter.js (admin)
4.  sidebar.js (admin)
5.  color-mode.js (admin)
6.  modal.js                   — modalView(), reloadModalThisForm()
7.  theme.js                   — forces DSWA theme
8.  toast.js                   — DOMContentLoaded init
9.  page-nav.js                — DOMContentLoaded init
10. notifications.js (admin)
11. lotus-canvas.js            — DOMContentLoaded → initLotus() → dispatches 'lps-ready'
12. water-ripples.js           — DOMContentLoaded → startSwayLoop()
13. chat-overlay.js            — mounts chat, auto-starts or resumes session
14. explorer-view.js           — createToggleButton(), waits for 'lps-ready'
15. spa-nav.js                 — binds click handlers (loaded last)
```

---

## Initialization Sequence (DOMContentLoaded)

1. `theme.js` — forces DSWA cookie, removes theme selector
2. `lotus-canvas.js` → `initLotus()`:
   - Creates LotusProgressSystem (LPS) and LotusCanvas
   - Calls `lps.init()`, `lps.initFromAPI()` (fetches get_framework_summary + get_lotus_state)
   - Sets `window.lps` globally
   - Dispatches `CustomEvent('lps-ready')`
   - Starts wind sway animation loop
3. `water-ripples.js` → `startSwayLoop()` — starts RAF loop for ripple/caustic gradients
4. `chat-overlay.js` — mounts to `#chat-overlay-mount`
   - If no session: auto-starts `assessment_start` after 1500ms
   - If session: loads chat history via `get_chat_history`
5. `explorer-view.js` — creates toggle button, waits for `lps-ready` event
6. `spa-nav.js` — sets up click interception on `a[href*="index.php?page="]`

---

## State Management

### Global State Objects

| Object | Location | Contains |
|--------|----------|----------|
| `window.lps` | lotus-canvas.js | LotusProgressSystem: canvas, framework summary, merged data |
| `window.lps.canvas` | lotus-canvas.js | LotusCanvas: petalStates, fallingPetalStates, windVector, slotMap |
| `window.pongGame` | pong-game.js | Game state: active, settings, start/stop methods |

### Module-Local State

| Module | State Variables |
|--------|----------------|
| chat-overlay.js | `sessionId`, `phase`, `hasSession`, typing indicator |
| explorer-view.js | `frameworkSummary`, `userState`, `pendingReflections`, `explorerOpen` |
| water-ripples.js | ripple phase, caustic time, sway offset |

### Storage

| Key | Store | Purpose |
|-----|-------|---------|
| `wn_sidebar_state` | localStorage | Sidebar collapsed/expanded |
| `wn_page_nav_state` | localStorage | Secondary nav state |
| `wn_theme` | cookie + localStorage | Active theme slug |

---

## Event System

### Custom Events

| Event | Source | Consumers |
|-------|--------|-----------|
| `lps-ready` | lotus-canvas.js | explorer-view.js (init) |
| `spa:contentLoaded` | spa-nav.js | Various (reinit after view load) |
| `hidden.bs.toast` | Bootstrap | toast.js (auto-cleanup) |
| `visibilitychange` | document | lotus-canvas.js, water-ripples.js (pause when hidden) |

### Module Communication (Current — Needs Refactoring)

| Pattern | Example | Problem |
|---------|---------|---------|
| Window globals | `window.lps.canvas._windVector` | Breaks encapsulation |
| Direct property access | `lps._mergedData` | Private internals exposed |
| Callback chains | `chatApiPost(action, data, cb)` | No error propagation |

**Target:** Replace with CustomEvent dispatching on `document` (PWT pattern).

---

## Animation Loops (Current — 3 Independent)

### 1. LotusCanvas.draw() — ~60fps
- Petal shimmer, brightness ramps, compass pulse
- Falling petal animation (FallingPetal class)
- Compass rose rendering
- Pauses via visibility API

### 2. Water Ripples sway loop — ~60fps
- Radial ripple gradient (phase-based)
- Caustic stripe gradients (3 overlaid)
- Background-size breathing animation
- Parallax tied to lotus wind vector

### 3. Pong Game loop — ~60fps (optional)
- Ball physics, paddle AI, collisions
- Particle spawn/decay
- Score/rally rendering
- Layers on top of bg-canvas

**Target:** Single RAF orchestrator (PWT `game-loop.js` pattern). All three become subsystems with `draw(dt, now)` methods called from one tick function.

---

## API Communication

All modules use wrappers around `fetch()`:

| Module | Wrapper | Endpoint |
|--------|---------|----------|
| chat-overlay.js | `chatApiPost(action, data, cb)` | `../api/index.php` |
| explorer-view.js | `explorerApiPost(action, data, cb)` | `../api/index.php` |
| lotus-canvas.js | `initFromAPI()` | `../api/index.php` |
| search.js | `apiPost('liveSearch', ...)` | via admin's bs-init.js |
| spa-nav.js | `fetch(url?reloadView=true)` | `app/index.php` |

### Response Shape
```json
{
  "error": "...",
  "success": "...",
  "results": { "data": "..." }
}
```

---

## Major Module Details

### lotus-canvas.js (1,833 lines)

**Classes:**
- `LotusCanvas` — 360-degree radial flower with petal rings per stage/dimension
- `FallingPetal` — Cascading petal drop animation from assessment choices
- `LotusProgressSystem` — Orchestrates canvas + state merge + API coordination

**Key internals:**
- `_petalStates` (Map) — petal index → {progress, confidenceLevel, assessmentSource}
- `_fallingPetalStates` (Map) — active falling petals
- `_slotMap` — petal index → stage+dimension mapping
- `_windVector` — shared with water-ripples for coordinated sway
- Easing functions, interpolation helpers, gradient caching, DPR scaling

### chat-overlay.js (895 lines)

**Responsibilities:**
- Auto-start vs resume flow with session management
- Message rendering (user/bot bubbles with debug info)
- Multiple choice rendering with dimension colors
- Content banner display with thumbnails
- Chat history compression (last N visible, older collapsed)
- Admin debug modal (token/cost tracking)

**Dimension colors (duplicated in explorer-view.js):**
```javascript
DIM_COLORS = { mindset: '#e040fb', toolset: '#448aff', skillset: '#69f0ae' }
```

### explorer-view.js (902 lines)

**Responsibilities:**
- `toggleExplorer()` — switches lotus ↔ stage grid view
- Stage tile rendering with M/T/S dimension bars
- Hard wall divider rendering (blocking/crossed/ahead)
- Stage detail drilldown with KPI accordion
- Pending reflections relay to chat (WP7 integration)

### spa-nav.js (299 lines)

**SPA cycle:**
1. Click intercept on `a[href*="index.php?page="]` (unless `data-no-spa`)
2. Parallel: AJAX fetch + CSS fade-out (600ms, blur + translateY)
3. Content swap: replace `#main-content` innerHTML
4. Script re-execution: clone `<script>` tags (external sequential, then inline)
5. Fire `spa:contentLoaded` custom event
6. Fade in after images load (2s timeout)
7. Update URL, title, sidebar active state, badge counts

### bg-canvas.js (194 lines)

- Dark: deep indigo/violet nebula with colored glows
- Light: grey-white with faint color washes
- 80px grid with directional arrows at intersections
- `MutationObserver` on `data-bs-theme` for live theme switching

### water-ripples.js (209 lines)

- Radial ripple rings from lotus center (phase-based)
- 3 overlaid caustic stripe gradients
- Background-size breathing animation
- Parallax sway via `lps.canvas._windVector` (direct coupling — should be event)

### pong-game.js (379 lines)

- Self-playing Pong on `#bgCanvas` overlay
- Ball physics with configurable speed/angle
- Two AI paddles with reaction time slider (0-1)
- Particle effects on collisions
- API: `window.pongGame.start()` / `window.pongGame.stop()`
- **Demo code** — can be removed or converted to a game-loop subsystem

---

## Utility Modules

### modal.js (97 lines)
```javascript
modalView({modal: 'item_detail', id: 123})     // Load modal via AJAX
reloadModalThisForm('formId', {extra: 'vars'})  // Reload with form data
```

### toast.js (109 lines)
```javascript
showToast('success', 'Item saved!', {autohide: true, delay: 5000})
// Types: success, danger, warning, info
// Session flash: reads #toast-data div attributes on DOMContentLoaded
```

### search.js (115 lines)
- 300ms debounce, min 2 chars
- `apiPost('liveSearch')` → dropdown with title, date, status badge
- Binds both `#navSearchInput` (desktop) and `#navSearchInputMobile`

### tabdrop.js (178 lines)
- Auto-init on `[data-tabdrop]` elements
- Measures natural widths, recalculates on resize (100ms debounce)
- Overflowing tabs → "More" dropdown

### page-nav.js (94 lines)
- Desktop (>=992px): inline toggle, localStorage persistence
- Mobile (<992px): overlay drawer with backdrop
- 150ms debounced resize for breakpoint crossing

### theme.js (32 lines)
- Forces `wn_theme=dswa` cookie
- Removes theme selector UI element
- Sets sidebar/navbar to glass mode

---

## PWT Game Loop Reference Architecture

The PWT app (`github.com/WaveNetworks/pwt`) implements a game engine pattern for web apps. This is the target for ElevateHER's JS rebuild.

### Core: game-loop.js

Single `requestAnimationFrame` chain:

```javascript
function tick(now) {
    rafId = requestAnimationFrame(tick);
    var dt = (now - lastTime) / 1000;
    if (dt > 0.1) dt = 0.016;  // clamp large gaps (tab switch)
    lastTime = now;
    if (paused) return;

    // 1. Persistent subsystems (canvas layers, audio, etc.)
    for (var name in subsystems) {
        if (subsystems[name].draw) {
            try { subsystems[name].draw(dt, now); } catch (e) {}
        }
    }

    // 2. Current level tick (active page logic)
    if (currentLevelObj && currentLevelObj.tick) {
        try { currentLevelObj.tick(dt, now); } catch (e) {}
    }

    // 3. Particles (managed ephemeral entities)
    for (var i = particles.length - 1; i >= 0; i--) {
        var p = particles[i];
        p.life -= dt;
        if (p.life <= 0) { p.onDeath && p.onDeath(p); particles.splice(i, 1); }
        else if (p.update) { p.update(p, dt); }
    }
}
```

### Subsystem Registration (persistent across navigation)

```javascript
// In bg-canvas.js
GameLoop.registerSubsystem('bgCanvas', {
    draw: function(dt, now) { draw(); },
    resize: resize
});

// In water-ripples.js (converted)
GameLoop.registerSubsystem('waterRipples', {
    draw: function(dt, now) { updateSway(dt); }
});

// In lotus-canvas.js (converted)
GameLoop.registerSubsystem('lotusCanvas', {
    draw: function(dt, now) { lotusCanvas.draw(dt); },
    resize: function() { lotusCanvas.resize(); }
});
```

### Level Registration (per-view lifecycle)

```javascript
// In assessment view (chat + explorer)
GameLoop.registerLevel('assessment', {
    enter: function() {
        chatOverlay.mount();
        explorerView.init();
    },
    exit: function() {
        chatOverlay.unmount();
        explorerView.destroy();
        // GameLoop auto-clears all level-scoped timers and particles
    }
});
```

### Managed Timers

```javascript
// Level-scoped: auto-cleared on navigation
var _setTimeout = window.GameLoop
    ? function(fn, d) { return GameLoop.setTimeout(fn, d, true); }
    : setTimeout;

// Global: persists across navigation
GameLoop.setTimeout(fn, delay, false);
```

### SPA Integration

PWT's `spa-nav.js` fires two events:
1. `spa:navigate` — before content swap → GameLoop calls `exitLevel()`
2. `spa:contentLoaded` — after new HTML injected → GameLoop calls `enterLevel(getPageName())`

### Fallback Pattern

Subsystems that may run on pages without the full template (e.g., auth pages):
```javascript
if (window.GameLoop) {
    GameLoop.registerSubsystem('bgCanvas', { draw: function() { draw(); } });
} else {
    (function loop() { draw(); requestAnimationFrame(loop); })();
}
```
