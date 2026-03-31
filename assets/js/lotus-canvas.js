/**
 * lotus-canvas.js
 * ElevateHER Lotus Visualization System
 * Extracted from DSWADemo — LotusCanvas, FallingPetal, LotusProgressSystem
 */
(function() {
'use strict';

/* ================================================================
   SECTION 1: EASING FUNCTIONS
   ================================================================ */
const Easing = {
  linear:         t => t,
  easeOutCubic:   t => 1 - Math.pow(1 - t, 3),
  easeInOutCubic: t => t < 0.5 ? 4*t*t*t : 1 - Math.pow(-2*t + 2, 3) / 2,
  easeOutExpo:    t => t === 1 ? 1 : 1 - Math.pow(2, -10 * t),
  easeOutSine:    t => Math.sin(t * Math.PI / 2),
  easeInOutSine:  t => -(Math.cos(Math.PI * t) - 1) / 2,
  easeInOutQuad:  t => t < 0.5 ? 2*t*t : 1 - Math.pow(-2*t + 2, 2) / 2,
  easeOutQuad:    t => 1 - (1 - t) * (1 - t),
  easeOutBack:    t => {
    const c1 = 1.70158, c3 = c1 + 1;
    return 1 + c3 * Math.pow(t - 1, 3) + c1 * Math.pow(t - 1, 2);
  },
};

/* ================================================================
   SECTION 2: INTERPOLATION HELPERS
   ================================================================ */
function lerp(a, b, t) { return a + (b - a) * t; }

function cubicBezier(t, p0, p1, p2, p3) {
  const u = 1 - t;
  return u*u*u*p0 + 3*u*u*t*p1 + 3*u*t*t*p2 + t*t*t*p3;
}

/* ================================================================
   SECTION 3: LOTUS CONFIG GENERATOR (dynamic, from framework)
   ================================================================ */
function generateLotusConfig(summary) {
  const stages = summary.stages;
  const N = stages.length;

  // Petal counts per ring: enough upper-half petals for slot selection,
  // with a density floor that grows for outer rings
  const petalsPerLevel = stages.map((stage, i) => {
    const slotCount = 3 + stage.kpiCount;
    return Math.max(Math.ceil(slotCount * 2.2), 8 + i * 3);
  });

  // Radii: 0.10 to 0.78 with slight inner compression via pow(t, 0.85)
  const levelRadii = stages.map((_, i) => {
    const t = N > 1 ? i / (N - 1) : 0;
    return 0.10 + 0.68 * Math.pow(t, 0.85);
  });

  // Petal scale: width lerps 0.55→0.16, height lerps 1.12→0.82
  const petalScale = stages.map((_, i) => {
    const t = N > 1 ? i / (N - 1) : 0;
    return {
      w: lerp(0.55, 0.16, t),
      h: lerp(1.12, 0.82, t),
    };
  });

  // Colors: hue 298→235 (magenta→indigo), sat/lit shift gradually
  const levelColors = stages.map((_, i) => {
    const t = N > 1 ? i / (N - 1) : 0;
    return [
      Math.round(lerp(298, 235, t)),  // hue
      Math.round(lerp(50, 45, t)),    // satBase
      Math.round(lerp(45, 42, t)),    // satTip
      Math.round(lerp(22, 34, t)),    // litBase
      Math.round(lerp(42, 56, t)),    // litTip
    ];
  });

  // Alternating offset for stagger
  const levelOffsetFraction = stages.map((_, i) => i % 2 === 0 ? 0 : 0.5);

  // Hard wall ring indices (for wall rendering)
  const hardWallRings = [];
  stages.forEach((stage, i) => {
    if (stage.hardWallAfter) hardWallRings.push(i);
  });

  return {
    petalsPerLevel,
    levelRadii,
    petalScale,
    levelColors,
    levelOffsetFraction,
    hardWallRings,
    jitter: { angle: 2.0, radius: 0.020, scale: 0.07 },
  };
}

/* ================================================================
   SECTION 4: CANVAS LOTUS CONFIG (dynamic, set by framework)
   ================================================================ */
let LOTUS_CONFIG = null;

const DEG = Math.PI / 180;

/* ================================================================
   SECTION 5: CANVAS DRAW HELPERS
   ================================================================ */

function seededRand(seed) {
  let s = seed * 2654435761 >>> 0;
  s = ((s >>> 16) ^ s) * 0x45d9f3b >>> 0;
  s = ((s >>> 16) ^ s) >>> 0;
  return (s / 4294967296) * 2 - 1;
}

function hsl(h, s, l)       { return `hsl(${h},${s}%,${l}%)`; }
function hsla(h, s, l, a)   { return `hsla(${h},${s}%,${l}%,${a})`; }

function maxPetalExtent(R) {
  if (!LOTUS_CONFIG) return R * 0.78;
  let maxExt = 0;
  LOTUS_CONFIG.petalsPerLevel.forEach((count, lvl) => {
    const ringR = R * LOTUS_CONFIG.levelRadii[lvl];
    const pH    = ringR * LOTUS_CONFIG.petalScale[lvl].h;
    const ext   = ringR * (1 + LOTUS_CONFIG.jitter.radius) + (pH / 2) * (1 + LOTUS_CONFIG.jitter.scale);
    if (ext > maxExt) maxExt = ext;
  });
  return maxExt;
}

function computeRadius(vw, vh) {
  const testR = 1000;
  const ratio = maxPetalExtent(testR) / testR;
  const fromH = (vh * 0.46) / ratio;
  const fromW = vw * 0.55;
  return Math.max(Math.min(fromH, fromW, 700), 100);
}

function drawPetalShape(ctx, halfW, halfH) {
  const t1x = halfW * 0.15,  t1y = -halfH * 0.65;
  const t2x = halfW * 0.75,  t2y = -halfH * 0.40;
  const mx  = halfW,          my  = -halfH * 0.05;
  const b1x = halfW * 0.90,  b1y =  halfH * 0.30;
  const b2x = halfW * 0.35,  b2y =  halfH * 0.75;

  ctx.beginPath();
  ctx.moveTo(0, -halfH);
  ctx.bezierCurveTo(t1x, t1y,  t2x, t2y,  mx, my);
  ctx.bezierCurveTo(b1x, b1y,  b2x, b2y,  0, halfH);
  ctx.bezierCurveTo(-b2x, b2y, -b1x, b1y, -mx, my);
  ctx.bezierCurveTo(-t2x, t2y, -t1x, t1y, 0, -halfH);
  ctx.closePath();
}

/* ================================================================
   SECTION 6: LOTUS CANVAS RENDERER

   Framework-driven: geometry comes from LOTUS_CONFIG (generated
   from framework summary). Instance data paints state onto the
   pre-built petal geometry.

   DATA FLOW:
   Framework → generateLotusConfig → LOTUS_CONFIG → petals + slots
   Instances → merge → applyMergedState → _petalStates → draw → brightness ramp
   ================================================================ */
class LotusCanvas {
  constructor(canvas) {
    this.canvas   = canvas;
    this.ctx      = canvas.getContext('2d');
    this.petals   = [];
    this.R        = 0;
    this.cx       = 0;
    this.cy       = 0;
    this._hueOffset    = 0;
    this._animId       = null;
    this._animating    = false;

    // LMS state: Key: petal idx, Value: { progress, confidenceLevel, assessmentSource }
    this._petalStates = new Map();
    this._hasShimmeringPetals = false;
    this._shimmerTime = 0;

    // Framework reference
    this._frameworkSummary = null;

    // Slot mapping (populated by configureFromFramework + _buildSlots)
    this._stageConfig = null;
    this._slotMap = new Map();
    this._petalIdxToSlotKey = new Map();

    // Falling petal rendering
    this._fallingPetalStates  = new Map();
    this._fallingPetalNextId  = 0;

    // Wind sway
    this._windVector      = { x: 0, y: 0 };
    this._swayOffsetX     = 0;
    this._swayRot         = 0;
    this._swayIntervalId  = null;

    // Perf: gradient phase cache (Opt 2)
    this._lastQPhase   = -1;
    this._cachedBgImage = '';

    // Perf: draw batching (Opt 4)
    this._drawScheduled = false;

    // Compass rose state
    this._compassHovered = false;
    this._compassPos = { x: 0, y: 0, radius: 22 };
    this._compassNeedleAngle = 0;  // for spin animation
    this._compassPulse = 0;        // 0-1 pulse intensity (decays to 0)

    // Perf: visibility API (Opt 1)
    this._hidden = false;
    this._onVisChange = () => this._handleVisibility();
    document.addEventListener('visibilitychange', this._onVisChange);

    this._onResize = () => this.resize();
    window.addEventListener('resize', this._onResize);
    this.resize();
  }

  resize() {
    const dpr = window.devicePixelRatio || 1;
    const vw  = window.innerWidth;
    const vh  = window.innerHeight;

    this.canvas.width  = vw * dpr;
    this.canvas.height = vh * dpr;
    this.canvas.style.width  = vw + 'px';
    this.canvas.style.height = vh + 'px';
    this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    this.vw = vw;
    this.vh = vh;
    // Center lotus in content area (offset by half sidebar width)
    var sidebar = document.getElementById('sidebar');
    var sidebarW = sidebar ? sidebar.offsetWidth : 0;
    this.cx = sidebarW + (vw - sidebarW) / 2;
    this.cy = vh;

    if (LOTUS_CONFIG) {
      this.R = computeRadius(vw, vh);
      this._buildPetalData();
      if (this._stageConfig) this._buildSlots();
    }

    this.draw();
  }

  /* ── Framework Configuration ─────────────────────── */

  configureFromFramework(summary) {
    this._frameworkSummary = summary;
    LOTUS_CONFIG = generateLotusConfig(summary);

    this._stageConfig = {};
    for (const stage of summary.stages) {
      this._stageConfig[stage.id] = { kpiCount: stage.kpiCount };
    }

    this.resize();
  }

  /* ── Petal Data ─────────────────────────────────────── */

  _buildPetalData() {
    if (!LOTUS_CONFIG) { this.petals = []; return; }

    const petals = [];
    let idx = 0;

    LOTUS_CONFIG.petalsPerLevel.forEach((count, lvl) => {
      const ringR = this.R * LOTUS_CONFIG.levelRadii[lvl];
      const ps    = LOTUS_CONFIG.petalScale[lvl];
      const pW    = ringR * ps.w;
      const pH    = ringR * ps.h;
      const [hu, sB, sT, lB, lT] = LOTUS_CONFIG.levelColors[lvl];
      const angOff = LOTUS_CONFIG.levelOffsetFraction[lvl] * 360 / count;

      for (let i = 0; i < count; i++) {
        const seed = lvl * 100 + i;
        const jA   = seededRand(seed)     * LOTUS_CONFIG.jitter.angle;
        const jR   = seededRand(seed + 1) * LOTUS_CONFIG.jitter.radius;
        const jS   = seededRand(seed + 2) * LOTUS_CONFIG.jitter.scale;

        const angle = (360 / count) * i + angOff + jA;
        const rad   = angle * DEG;
        const rJit  = ringR * (1 + jR);

        const px = Math.cos(rad - Math.PI / 2) * rJit;
        const py = Math.sin(rad - Math.PI / 2) * rJit;

        const halfW = (pW * (1 + jS)) / 2;
        const halfH = (pH * (1 + jS * 0.5)) / 2;

        // Parted position: push radially outward for explorer transition
        const dist = Math.sqrt(px * px + py * py);
        const pAngle = Math.atan2(py, px);
        const partedDist = dist * 1.8 + (this.R || 300) * 0.35;
        const partedPx = Math.cos(pAngle) * partedDist;
        const partedPy = Math.sin(pAngle) * partedDist;

        petals.push({
          idx, lvl, i, angle, px, py, halfW, halfH,
          restPx: px, restPy: py,
          partedPx, partedPy,
          hue: hu, satBase: sB, satTip: sT, litBase: lB, litTip: lT,
          opacity: 1,
          scale: 1,
          brightness: 0,
          slotRole: null,
          stageId: null,
        });
        idx++;
      }
    });

    this.petals = petals;
  }

  /* ── Slot Mapping ───────────────────────────────────── */

  configureStages(config) {
    this._stageConfig = config;
    this._buildSlots();
    this.draw();
  }

  _buildSlots() {
    this._slotMap.clear();
    this._petalIdxToSlotKey.clear();

    this.petals.forEach(p => { p.slotRole = null; p.stageId = null; });

    if (!this._stageConfig) return;

    const stageIds = Object.keys(this._stageConfig).sort();

    stageIds.forEach((stageId, ringIdx) => {
      if (ringIdx >= LOTUS_CONFIG.petalsPerLevel.length) return;

      const config = this._stageConfig[stageId];
      const totalSlots = 3 + (config.kpiCount || 0);

      const visible = this.petals.filter(p => p.lvl === ringIdx && p.py < 0);
      visible.sort((a, b) => a.px - b.px);

      let selected;
      if (visible.length <= totalSlots) {
        selected = visible;
      } else {
        selected = [];
        for (let i = 0; i < totalSlots; i++) {
          const pick = Math.round(i * (visible.length - 1) / (totalSlots - 1));
          selected.push(visible[pick]);
        }
      }

      const stageSlots = new Map();
      const dims = ['mindset', 'toolset', 'skillset'];
      const dimPositions = selected.length >= 3
        ? [0, Math.floor(selected.length / 2), selected.length - 1]
        : [0, 1, 2].slice(0, selected.length);

      const kpiPositions = [];
      for (let i = 0; i < selected.length; i++) {
        if (!dimPositions.includes(i)) kpiPositions.push(i);
      }

      dims.forEach((dim, dIdx) => {
        if (dIdx >= dimPositions.length) return;
        const petal = selected[dimPositions[dIdx]];
        if (petal) {
          stageSlots.set(dim, petal.idx);
          this._petalIdxToSlotKey.set(petal.idx, { stage: stageId, dimension: dim });
          petal.slotRole = 'dimension-' + dim;
          petal.stageId = stageId;
        }
      });

      kpiPositions.forEach((pos, kIdx) => {
        const kpiId = 'kpi-' + String(kIdx + 1).padStart(2, '0');
        const petal = selected[pos];
        if (petal) {
          stageSlots.set(kpiId, petal.idx);
          this._petalIdxToSlotKey.set(petal.idx, { stage: stageId, dimension: kpiId });
          petal.slotRole = 'kpi';
          petal.stageId = stageId;
        }
      });

      this._slotMap.set(stageId, stageSlots);
    });
  }

  /* ── LMS State Methods ─────────────────────────────── */

  _getPetalIdxForSlot(stage, dimension) {
    const stageSlots = this._slotMap.get(stage);
    if (!stageSlots) return null;
    const idx = stageSlots.get(dimension);
    return idx !== undefined ? idx : null;
  }

  setPetalProgress(petalIdx, progress) {
    if (petalIdx === null) return;
    let state = this._petalStates.get(petalIdx);
    if (!state) {
      state = { progress: 0, confidenceLevel: 1.0, assessmentSource: null };
      this._petalStates.set(petalIdx, state);
    }
    state.progress = Math.max(0, Math.min(1, progress));
    if (!this._animating) this.draw();
  }

  setPetalConfidence(petalIdx, confidence, source) {
    if (petalIdx === null) return;
    let state = this._petalStates.get(petalIdx);
    if (!state) {
      state = { progress: 0, confidenceLevel: 1.0, assessmentSource: null };
      this._petalStates.set(petalIdx, state);
    }
    state.confidenceLevel = Math.max(0, Math.min(1, confidence));
    state.assessmentSource = source;
    this._updateShimmerFlag();
    if (!this._animating) this.draw();
  }

  _updateShimmerFlag() {
    this._hasShimmeringPetals = false;
    for (const state of this._petalStates.values()) {
      if (state.assessmentSource === 'ai' && state.confidenceLevel < 0.75) {
        this._hasShimmeringPetals = true;
        return;
      }
    }
  }

  _isLevelComplete(lvl) {
    for (const [stageId, stageSlots] of this._slotMap) {
      const stageIds = Object.keys(this._stageConfig).sort();
      if (stageIds[lvl] !== stageId) continue;
      for (const [dim, petalIdx] of stageSlots) {
        if (!dim.startsWith('kpi')) {
          const state = this._petalStates.get(petalIdx);
          if (!state || state.progress < 0.95) return false;
        }
      }
      return true;
    }
    return false;
  }

  resetAllState() {
    this._petalStates.clear();
    this._hasShimmeringPetals = false;
    if (!this._animating) this.draw();
  }

  getSlotInfo(stage, dimension) {
    const petalIdx = this._getPetalIdxForSlot(stage, dimension);
    if (petalIdx === null) return null;
    const p = this.petals[petalIdx];
    if (!p) return null;
    return {
      stage, dimension, petalIdx,
      absX:  this.cx + p.px,
      absY:  this.cy + p.py,
      angle: p.angle,
      petalW: p.halfW * 2,
      petalH: p.halfH * 2,
      level: p.lvl,
    };
  }

  setHue(hue) {
    this._hueOffset = hue - 270;
    if (!this._animating) this.draw();
  }

  allocateFallingPetalId() {
    return this._fallingPetalNextId++;
  }

  setActiveFallingPetal(id, state) {
    if (state === null) {
      this._fallingPetalStates.delete(id);
    } else {
      this._fallingPetalStates.set(id, state);
    }
  }

  _drawSingleFallingPetal(fp) {
    if (!fp || fp.opacity <= 0) return;
    const { ctx } = this;
    ctx.save();
    ctx.globalAlpha = fp.opacity * 0.95;
    ctx.translate(fp.x, fp.y);
    ctx.rotate(fp.rotation * DEG);
    ctx.scale(fp.scale, fp.scale);
    drawPetalShape(ctx, fp.halfW, fp.halfH);
    const grad = ctx.createLinearGradient(0, fp.halfH, 0, -fp.halfH);
    grad.addColorStop(0,   hsl(fp.hue, 40, 45));
    grad.addColorStop(1,   hsl(fp.hue, 75, 68));
    ctx.fillStyle = grad;
    ctx.fill();
    ctx.strokeStyle = hsla(fp.hue, 50, 70, 0.3);
    ctx.lineWidth = 0.5;
    ctx.stroke();
    // Draw slot label on falling petal
    if (fp.label) {
      const isAdmin = document.body.getAttribute('data-is-admin') === '1';
      const fontSize = Math.max(8, Math.min(fp.halfW * 0.8, fp.halfH * 0.35, 12));
      ctx.font = 'bold ' + fontSize + 'px monospace';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillStyle = hsla(fp.hue, 40, 90, isAdmin ? 0.9 : 0.5);
      ctx.fillText(fp.label, 0, -fp.halfH * 0.15);
    }
    ctx.restore();
  }

  // ── Rendering ───────────────────────────────────────

  draw() {
    const { ctx, vw, vh, cx, cy, R } = this;

    ctx.clearRect(0, 0, vw, vh);

    ctx.save();

    // Background glow
    const glowR = R * 1.3 || vw * 0.4;
    const glow = ctx.createRadialGradient(cx, cy, 0, cx, cy, glowR);
    glow.addColorStop(0,    'hsla(275,70%,45%,0.30)');
    glow.addColorStop(0.35, 'hsla(260,50%,30%,0.12)');
    glow.addColorStop(0.7,  'transparent');
    ctx.fillStyle = glow;
    ctx.fillRect(cx - glowR, cy - glowR, glowR * 2, glowR * 2);

    // Inner glow
    const igR = R * 0.5 || vw * 0.15;
    const ig = ctx.createRadialGradient(cx, cy, 0, cx, cy, igR);
    ig.addColorStop(0,   'hsla(290,60%,60%,0.25)');
    ig.addColorStop(0.4, 'hsla(280,50%,40%,0.08)');
    ig.addColorStop(1,   'transparent');
    ctx.fillStyle = ig;
    ctx.fillRect(cx - igR, cy - igR, igR * 2, igR * 2);

    // Base circle
    const baseR = R * 0.78 || vw * 0.25;
    const baseGrad = ctx.createRadialGradient(cx, cy, 0, cx, cy, baseR);
    baseGrad.addColorStop(0,    hsla(292 + this._hueOffset, 48, 32, 0.15));
    baseGrad.addColorStop(0.25, hsla(288 + this._hueOffset, 45, 28, 0.10));
    baseGrad.addColorStop(0.5,  hsla(282 + this._hueOffset, 40, 23, 0.06));
    baseGrad.addColorStop(0.8,  hsla(274 + this._hueOffset, 34, 18, 0.03));
    baseGrad.addColorStop(1,    'transparent');
    ctx.beginPath();
    ctx.arc(cx, cy, baseR, 0, Math.PI * 2);
    ctx.fillStyle = baseGrad;
    ctx.fill();

    // Petals: draw outer->inner so inner overlaps outer
    const fpsByLevel = new Map();
    for (const fp of this._fallingPetalStates.values()) {
      if (fp && fp.opacity > 0) {
        if (!fpsByLevel.has(fp.level)) fpsByLevel.set(fp.level, []);
        fpsByLevel.get(fp.level).push(fp);
      }
    }
    const fpDrawnLevels = new Set();
    for (let i = this.petals.length - 1; i >= 0; i--) {
      const p = this.petals[i];
      for (const [lvl, fps] of fpsByLevel) {
        if (!fpDrawnLevels.has(lvl) && p.lvl < lvl) {
          for (const fp of fps) this._drawSingleFallingPetal(fp);
          fpDrawnLevels.add(lvl);
        }
      }
      this._drawPetal(p);
    }
    for (const [lvl, fps] of fpsByLevel) {
      if (!fpDrawnLevels.has(lvl)) {
        for (const fp of fps) this._drawSingleFallingPetal(fp);
      }
    }

    // Hard wall arcs (drawn after petals, subtle overlay)
    this._drawHardWalls();

    // Center jewel
    const jewR = R || vw * 0.25;
    [
      { r: jewR * 0.065, color: hsl(292 + this._hueOffset, 42, 24) },
      { r: jewR * 0.048, color: hsl(292 + this._hueOffset, 48, 32) },
      { r: jewR * 0.030, color: hsl(292 + this._hueOffset, 52, 40) },
      { r: jewR * 0.016, color: hsl(290 + this._hueOffset, 55, 48) },
    ].forEach(({ r, color }) => {
      ctx.beginPath();
      ctx.arc(cx, cy, r, 0, Math.PI * 2);
      ctx.fillStyle = color;
      ctx.fill();
    });

    ctx.restore();

    // Compass rose (drawn outside save/restore, in viewport coords)
    this._drawCompassRose();
  }

  /* ── Compass Rose Rendering ──────────────────────────── */

  _getCompassDimensionScores() {
    const scores = { mindset: 0, toolset: 0, skillset: 0 };
    if (!this._frameworkSummary || !this._slotMap.size) return scores;

    const dims = ['mindset', 'toolset', 'skillset'];
    const counts = { mindset: 0, toolset: 0, skillset: 0 };
    const sums   = { mindset: 0, toolset: 0, skillset: 0 };

    for (const [stageId, stageSlots] of this._slotMap) {
      for (const dim of dims) {
        const petalIdx = stageSlots.get(dim);
        if (petalIdx === undefined) continue;
        const state = this._petalStates.get(petalIdx);
        sums[dim] += state ? state.progress : 0;
        counts[dim]++;
      }
    }

    for (const dim of dims) {
      scores[dim] = counts[dim] > 0 ? sums[dim] / counts[dim] : 0;
    }
    return scores;
  }

  _drawCompassRose() {
    const { ctx, vw, vh } = this;
    const size = vw < 768 ? 18 : vw < 1024 ? 22 : 25;
    const margin = vw < 768 ? 30 : 40;
    const t = this._shimmerTime;

    // Position: next to the lotus on all viewports
    // Lotus center is at (vw/2, vh). Place compass just right of outer ring.
    const R = Math.min(vw, vh) / 2;
    const outerR = R * 0.78;              // outermost petal ring
    const gap = size * 1.5;               // breathing room from petals
    const rx = this.cx + outerR + gap;
    const ry = vh - outerR * 0.38;        // sit near lotus equator
    // Clamp to viewport so it doesn't overflow off-screen
    const clampedX = Math.min(rx, vw - margin - size);
    const clampedY = Math.min(ry, vh - margin - size);
    this._compassPos = { x: clampedX, y: clampedY, radius: size + 8 };

    ctx.save();
    ctx.translate(clampedX, clampedY);

    // === Petal-matched dual-frequency pulse ===
    const shimmerFast = 0.5 + 0.5 * Math.sin(t * 1.8 * Math.PI * 2);
    const shimmerSlow = 0.5 + 0.5 * Math.sin(t * 0.4 * Math.PI * 2);
    const pulse = 0.72 + 0.10 * shimmerSlow + 0.08 * shimmerFast;
    const cp = this._compassPulse;
    const pulseScale = cp > 0.01 ? 1.0 + cp * 0.25 : 1.0;  // scale up to 1.25x during pulse
    const hoverScale = this._compassHovered ? 1.12 : 1.0;

    ctx.scale(hoverScale * pulseScale, hoverScale * pulseScale);
    ctx.globalAlpha = Math.min(1, pulse + cp * 0.3);  // brighten during pulse

    // Hover glow
    if (this._compassHovered) {
      const glow = ctx.createRadialGradient(0, 0, size * 0.3, 0, 0, size * 2);
      glow.addColorStop(0, 'rgba(27,93,171,0.25)');
      glow.addColorStop(1, 'transparent');
      ctx.fillStyle = glow;
      ctx.fillRect(-size * 2, -size * 2, size * 4, size * 4);
    }

    // Interaction pulse — expanding glow ring on message send/receive
    if (cp > 0.01) {
      const expandR = size * (1.5 + (1 - cp) * 3.0);  // expands from 1.5x to 4.5x
      const pulseGlow = ctx.createRadialGradient(0, 0, size * 0.3, 0, 0, expandR);
      pulseGlow.addColorStop(0, `rgba(255,215,100,${cp * 0.6})`);
      pulseGlow.addColorStop(0.3, `rgba(200,154,64,${cp * 0.4})`);
      pulseGlow.addColorStop(0.6, `rgba(27,93,171,${cp * 0.25})`);
      pulseGlow.addColorStop(1, 'transparent');
      ctx.save();
      ctx.globalAlpha = 1;
      ctx.fillStyle = pulseGlow;
      ctx.fillRect(-expandR, -expandR, expandR * 2, expandR * 2);
      ctx.restore();
      ctx.globalAlpha = Math.min(1, pulse + cp * 0.3);
    }

    // === Outer decorative ring with tick marks ===
    ctx.beginPath();
    ctx.arc(0, 0, size * 1.08, 0, Math.PI * 2);
    ctx.strokeStyle = 'rgba(200,154,64,0.25)';
    ctx.lineWidth = 0.8;
    ctx.stroke();

    for (let i = 0; i < 32; i++) {
      const tickAngle = (i * 11.25) * DEG;
      const isCardTick = i % 8 === 0;
      const isOrdTick = i % 4 === 0 && !isCardTick;
      const outerTickR = size * 1.08;
      const innerR = isCardTick ? size * 0.88 : isOrdTick ? size * 0.94 : size * 0.99;
      ctx.beginPath();
      ctx.moveTo(Math.sin(tickAngle) * innerR, -Math.cos(tickAngle) * innerR);
      ctx.lineTo(Math.sin(tickAngle) * outerTickR, -Math.cos(tickAngle) * outerTickR);
      ctx.strokeStyle = isCardTick ? 'rgba(200,154,64,0.5)' : 'rgba(255,255,255,0.15)';
      ctx.lineWidth = isCardTick ? 1.0 : 0.5;
      ctx.stroke();
    }

    // Rotate for needle animation (weathervane)
    ctx.rotate(this._compassNeedleAngle * DEG);

    // === 3-way pie chart (Mindset / Toolset / Skillset weighted balance) ===
    {
      const dimScores = this._getCompassDimensionScores();
      const dims = [
        { key: 'mindset',  r: 179, g: 136, b: 217 },  // purple
        { key: 'toolset',  r: 108, g: 166, b: 217 },  // blue
        { key: 'skillset', r: 107, g: 201, b: 107 },  // green
      ];
      const arcInnerR = size * 0.30;
      const arcOuterR = size * 0.85;

      // Compute weighted segment angles — scores determine arc size
      const totalScore = dims.reduce((s, d) => s + (dimScores[d.key] || 0), 0);
      const hasData = totalScore > 0;

      // Calculate angles: if no data, equal thirds (faded); otherwise weighted by score
      // Minimum arc of 30° ensures all 3 dimensions remain visible
      const MIN_ARC = 30 * DEG;
      const FULL = Math.PI * 2;
      let angles;

      if (!hasData) {
        // Equal thirds, all faded
        angles = dims.map(() => FULL / 3);
      } else {
        // Weighted by score with minimum arc
        const raw = dims.map(d => dimScores[d.key] || 0);
        const rawTotal = raw.reduce((a, b) => a + b, 0) || 1;
        // Allocate proportionally but ensure minimum
        angles = raw.map(v => Math.max(MIN_ARC, (v / rawTotal) * FULL));
        // Normalize to sum to 2π
        const aSum = angles.reduce((a, b) => a + b, 0);
        angles = angles.map(a => a * (FULL / aSum));
      }

      let curAngle = -Math.PI / 2; // start from top

      for (let i = 0; i < 3; i++) {
        const d = dims[i];
        const score = dimScores[d.key] || 0;
        const segAngle = angles[i];
        const startAngle = curAngle;
        const endAngle = curAngle + segAngle;
        curAngle = endAngle;

        // Per-segment shimmer
        const segPhase = i * 2.1;
        const segShimmer = 0.5 + 0.5 * Math.sin(t * 1.8 * Math.PI * 2 + segPhase);
        const shimmerBoost = segShimmer * 0.08;

        const baseAlpha = hasData ? 0.06 : 0.04; // fainter when no data
        const scoreAlpha = score * 0.30;
        const innerAlpha = Math.min(1, baseAlpha + scoreAlpha * 0.5 + shimmerBoost);
        const outerAlpha = Math.min(1, baseAlpha + scoreAlpha + shimmerBoost);

        // Radial gradient for the wedge
        const grad = ctx.createRadialGradient(0, 0, arcInnerR, 0, 0, arcOuterR);
        grad.addColorStop(0, `rgba(${d.r},${d.g},${d.b},${innerAlpha})`);
        grad.addColorStop(1, `rgba(${d.r},${d.g},${d.b},${outerAlpha})`);

        // Draw donut wedge (pie segment)
        ctx.save();
        ctx.globalAlpha = pulse;
        ctx.beginPath();
        ctx.arc(0, 0, arcOuterR, startAngle, endAngle);
        ctx.arc(0, 0, arcInnerR, endAngle, startAngle, true);
        ctx.closePath();
        ctx.fillStyle = grad;
        ctx.fill();
        ctx.restore();

        // Separator line between segments
        ctx.save();
        ctx.globalAlpha = pulse;
        ctx.beginPath();
        ctx.moveTo(Math.cos(startAngle) * arcInnerR, Math.sin(startAngle) * arcInnerR);
        ctx.lineTo(Math.cos(startAngle) * arcOuterR, Math.sin(startAngle) * arcOuterR);
        ctx.strokeStyle = 'rgba(255,255,255,0.10)';
        ctx.lineWidth = 0.5;
        ctx.stroke();
        ctx.restore();
      }
    }

    // === 16-point compass rose ===
    for (let i = 0; i < 16; i++) {
      const isCardinal = i % 4 === 0;
      const isOrdinal = i % 4 === 2;
      const isSub = !isCardinal && !isOrdinal;

      const len = isCardinal ? size : isOrdinal ? size * 0.55 : size * 0.32;
      const w = isCardinal ? size * 0.28 : isOrdinal ? size * 0.18 : size * 0.09;
      const angle = (i * 22.5) * DEG;

      // Per-point shimmer phase offset (petal-matched)
      const pointPhase = i * 0.4;
      const pointShimmer = 0.5 + 0.5 * Math.sin(t * 1.8 * Math.PI * 2 + pointPhase);
      const alphaBoost = pointShimmer * 0.12;

      ctx.save();
      ctx.rotate(angle);
      ctx.globalAlpha = pulse + alphaBoost;

      // Double-layer shadow for cardinal directions
      if (isCardinal) {
        ctx.beginPath();
        ctx.moveTo(0, -len * 1.02);
        ctx.bezierCurveTo(w * 0.45, -len * 0.6, w * 1.15, -len * 0.25, w * 0.6, 0);
        ctx.lineTo(0, len * 0.08);
        ctx.lineTo(-w * 0.6, 0);
        ctx.bezierCurveTo(-w * 1.15, -len * 0.25, -w * 0.45, -len * 0.6, 0, -len * 1.02);
        ctx.fillStyle = i === 0 ? 'rgba(140,100,30,0.3)' : 'rgba(15,55,110,0.2)';
        ctx.fill();
      }

      // Main point shape
      ctx.beginPath();
      ctx.moveTo(0, -len);
      ctx.bezierCurveTo(w * 0.3, -len * 0.6, w, -len * 0.25, w * 0.5, 0);
      ctx.lineTo(0, len * 0.08);
      ctx.lineTo(-w * 0.5, 0);
      ctx.bezierCurveTo(-w, -len * 0.25, -w * 0.3, -len * 0.6, 0, -len);

      // Color: north=gold, cardinal=blue gradient, ordinal=faint blue, sub=very faint
      if (i === 0) {
        const ng = ctx.createLinearGradient(0, 0, 0, -len);
        ng.addColorStop(0, 'rgba(200,154,64,0.6)');
        ng.addColorStop(0.5, 'rgba(200,154,64,0.9)');
        ng.addColorStop(1, 'rgba(255,215,100,0.95)');
        ctx.fillStyle = ng;
      } else if (isCardinal) {
        const cg = ctx.createLinearGradient(0, 0, 0, -len);
        cg.addColorStop(0, 'rgba(27,93,171,0.3)');
        cg.addColorStop(1, 'rgba(27,93,171,0.7)');
        ctx.fillStyle = cg;
      } else if (isOrdinal) {
        ctx.fillStyle = 'rgba(27,93,171,0.25)';
      } else {
        ctx.fillStyle = 'rgba(27,93,171,0.12)';
      }
      ctx.fill();

      // Warm gold shimmer overlay on non-north points
      if (!isSub && i !== 0 && pointShimmer > 0.3) {
        ctx.globalAlpha = (pointShimmer - 0.3) * 0.15;
        ctx.fillStyle = 'rgba(255,200,100,1)';
        ctx.fill();
      }

      // Stroke
      ctx.globalAlpha = pulse + alphaBoost;
      ctx.strokeStyle = isCardinal ? 'rgba(255,255,255,0.25)'
        : isOrdinal ? 'rgba(255,255,255,0.1)' : 'rgba(255,255,255,0.05)';
      ctx.lineWidth = 0.5;
      ctx.stroke();

      // Fleur-de-lis tip accent for cardinal points
      if (isCardinal) {
        const tipY = -len * 0.92;
        const fSize = w * 0.35;
        ctx.beginPath();
        ctx.arc(-fSize, tipY, fSize * 0.6, 0, Math.PI * 2);
        ctx.arc(fSize, tipY, fSize * 0.6, 0, Math.PI * 2);
        ctx.fillStyle = i === 0 ? 'rgba(255,215,100,0.4)' : 'rgba(27,93,171,0.25)';
        ctx.globalAlpha = pulse + alphaBoost;
        ctx.fill();
      }

      // North needle pulsing glow
      if (i === 0) {
        const northPulse = 0.5 + 0.5 * Math.sin(t * 2.0 * Math.PI * 2 + 1.2);
        ctx.save();
        ctx.globalAlpha = northPulse * 0.20;
        ctx.shadowColor = 'rgba(255,215,100,0.6)';
        ctx.shadowBlur = size * 0.4 * northPulse;
        ctx.beginPath();
        ctx.moveTo(0, -len);
        ctx.bezierCurveTo(w * 0.3, -len * 0.6, w, -len * 0.25, w * 0.5, 0);
        ctx.lineTo(0, len * 0.08);
        ctx.lineTo(-w * 0.5, 0);
        ctx.bezierCurveTo(-w, -len * 0.25, -w * 0.3, -len * 0.6, 0, -len);
        ctx.fillStyle = 'rgba(255,215,100,0.5)';
        ctx.fill();
        ctx.restore();
      }

      ctx.restore();
    }

    // Inner decorative ring
    ctx.globalAlpha = pulse;
    ctx.beginPath();
    ctx.arc(0, 0, size * 0.28, 0, Math.PI * 2);
    ctx.strokeStyle = 'rgba(200,154,64,0.35)';
    ctx.lineWidth = 0.6;
    ctx.stroke();

    // Center circle
    ctx.beginPath();
    ctx.arc(0, 0, size * 0.15, 0, Math.PI * 2);
    ctx.fillStyle = 'rgba(200,154,64,0.8)';
    ctx.fill();
    ctx.strokeStyle = 'rgba(255,255,255,0.3)';
    ctx.lineWidth = 0.5;
    ctx.stroke();

    // Inner dot
    ctx.beginPath();
    ctx.arc(0, 0, size * 0.06, 0, Math.PI * 2);
    ctx.fillStyle = 'rgba(255,215,100,0.9)';
    ctx.fill();

    ctx.restore();

    // === Cardinal labels (drawn outside rotation so they stay upright) ===
    ctx.save();
    ctx.translate(rx, ry);
    ctx.scale(hoverScale, hoverScale);
    ctx.globalAlpha = pulse * 0.9;

    const labels = ['N', 'E', 'S', 'W'];
    const labelDist = size * 1.28;
    const fontSize = Math.max(7, size * 0.32);
    ctx.font = `bold ${fontSize}px 'Segoe UI', system-ui, sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    for (let li = 0; li < 4; li++) {
      const labelAngle = (li * 90) * DEG + this._compassNeedleAngle * DEG;
      const lx = Math.sin(labelAngle) * labelDist;
      const ly = -Math.cos(labelAngle) * labelDist;
      ctx.fillStyle = li === 0 ? 'rgba(255,215,100,0.85)' : 'rgba(200,200,220,0.55)';
      ctx.fillText(labels[li], lx, ly);
    }

    ctx.restore();
  }

  /* ── Hard Wall Rendering ────────────────────────────── */

  _drawHardWalls() {
    if (!this._frameworkSummary || !LOTUS_CONFIG) return;
    const { ctx, cx, cy, R } = this;

    for (const wallIdx of LOTUS_CONFIG.hardWallRings) {
      const nextIdx = wallIdx + 1;
      if (nextIdx >= LOTUS_CONFIG.levelRadii.length) continue;

      const r1 = LOTUS_CONFIG.levelRadii[wallIdx];
      const r2 = LOTUS_CONFIG.levelRadii[nextIdx];
      const wallR = R * (r1 + r2) / 2;

      ctx.save();
      ctx.setLineDash([6, 4]);
      ctx.strokeStyle = 'rgba(255, 200, 100, 0.20)';
      ctx.lineWidth = 1.5;
      ctx.beginPath();
      // Upper semicircle (visible portion — center is at viewport bottom)
      ctx.arc(cx, cy, wallR, Math.PI, 2 * Math.PI);
      ctx.stroke();
      ctx.setLineDash([]);
      ctx.restore();
    }
  }

  /* ── Petal Rendering ────────────────────────────────── */

  _drawPetal(p) {
    const { ctx, cx, cy } = this;
    if (p.opacity <= 0.001) return;

    const state = this._petalStates.get(p.idx);
    const progress = state ? state.progress : 0;
    const confidence = state ? state.confidenceLevel : 1.0;
    const source = state ? state.assessmentSource : null;
    const hasProgress = progress > 0;

    const levelComplete = this._isLevelComplete(p.lvl);

    const ho = this._hueOffset;

    // Wind sway
    const numLevels = LOTUS_CONFIG ? LOTUS_CONFIG.petalsPerLevel.length : 1;
    const swayMul = 0.2 + 0.8 * (p.lvl / Math.max(numLevels - 1, 1));
    const swayAngleRad = this._swayRot * swayMul * DEG;
    const swayOffX = this._swayOffsetX * swayMul;
    const adjPx = p.px * Math.cos(swayAngleRad) - p.py * Math.sin(swayAngleRad) + swayOffX;
    const adjPy = p.px * Math.sin(swayAngleRad) + p.py * Math.cos(swayAngleRad);

    ctx.save();
    ctx.translate(cx + adjPx, cy + adjPy);
    ctx.rotate((p.angle + this._swayRot * swayMul) * DEG);
    ctx.scale(p.scale, p.scale);

    // === SHIMMER CALCULATION ===
    const shouldShimmer = source === 'ai' && confidence < 0.75 && hasProgress;
    let shimmerFactor = 0;
    if (shouldShimmer) {
      shimmerFactor = 1.0 - (confidence / 0.75);
      const shimmerFreq = 1.5 + shimmerFactor * 1.0;
      const shimmerPhase = p.idx * 0.7;
      shimmerFactor *= 0.5 + 0.5 * Math.sin(this._shimmerTime * shimmerFreq * Math.PI * 2 + shimmerPhase);
    }

    // === BRIGHTNESS RAMP ===
    const effectiveProgress = levelComplete ? Math.max(progress, 0.85) : progress;
    // Unassessed petals stay ghostly (0.22); assessed petals bloom bright (up to 0.85)
    // The contrast IS the progress — dim lotus grows vibrant as assessment fills in
    const baseAlpha = 0.22;
    const fullAlpha = 0.85 + p.lvl * 0.015;
    const petalAlpha = hasProgress || levelComplete
      ? lerp(0.35, fullAlpha, effectiveProgress)
      : baseAlpha;

    const shimmerAlphaModulation = shimmerFactor * 0.12;
    ctx.globalAlpha = p.opacity * (petalAlpha + shimmerAlphaModulation);

    drawPetalShape(ctx, p.halfW, p.halfH);

    // === COLOR: WARM vs COOL TINT ===
    let hueShift = 0;
    let satShift = 0;
    let litShift = 0;
    if (shouldShimmer) {
      hueShift = -shimmerFactor * 30;
      satShift = -shimmerFactor * 10;
      litShift = shimmerFactor * 5;
    }

    // === GRADIENT ===
    const litBoost = p.brightness + effectiveProgress * 12;
    const grad = ctx.createLinearGradient(0, p.halfH, 0, -p.halfH);

    if (hasProgress || levelComplete) {
      const mSatFactor = lerp(0.40, 1.0, effectiveProgress);
      const mLitFactor = lerp(0.60, 1.0, effectiveProgress);

      grad.addColorStop(0,   hsl(
        p.hue + ho + hueShift,
        Math.round((p.satBase + 8) * mSatFactor + satShift),
        Math.round(p.litBase * mLitFactor + litBoost + litShift)));
      grad.addColorStop(0.6, hsl(
        p.hue + ho + 5 + hueShift,
        Math.round(p.satTip * mSatFactor + satShift),
        Math.round(p.litTip * mLitFactor + litBoost + litShift)));
      grad.addColorStop(1,   hsl(
        p.hue + ho + 10 + hueShift,
        Math.round((p.satTip - 5) * mSatFactor + satShift),
        Math.round((p.litTip + 8) * mLitFactor + litBoost + litShift)));
    } else {
      grad.addColorStop(0,   hsl(p.hue + ho, Math.round(p.satBase * 0.50), Math.round(p.litBase * 0.95)));
      grad.addColorStop(0.6, hsl(p.hue + ho + 5, Math.round(p.satTip * 0.45), Math.round(p.litTip * 0.80)));
      grad.addColorStop(1,   hsl(p.hue + ho + 10, Math.round((p.satTip - 5) * 0.45), Math.round((p.litTip + 8) * 0.75)));
    }

    ctx.fillStyle = grad;
    ctx.fill();

    ctx.strokeStyle = hasProgress || levelComplete
      ? hsla(p.hue + ho + hueShift, 50, p.litTip + 18 + litBoost, 0.25)
      : hsla(p.hue + ho, Math.round(p.satTip * 0.30), 40, 0.12);
    ctx.lineWidth = Math.max(0.4, (this.R || 300) * 0.002);
    ctx.stroke();

    // Petal label: show slot info (dimension letter + stage for admin, just letter for users)
    const slotKey = this._petalIdxToSlotKey.get(p.idx);
    if (slotKey) {
      const isAdmin = document.body.getAttribute('data-is-admin') === '1';
      const dimLetter = slotKey.dimension === 'mindset' ? 'M'
                      : slotKey.dimension === 'toolset' ? 'T'
                      : slotKey.dimension === 'skillset' ? 'S'
                      : slotKey.dimension.replace('kpi-', 'K');
      // Admin sees "M03" (dimension + stage), users see "M"
      const label = isAdmin ? dimLetter + slotKey.stage : dimLetter;
      const labelFontSize = Math.max(8, Math.min(p.halfW * 0.85, p.halfH * 0.38, isAdmin ? 11 : 13));
      ctx.font = `bold ${labelFontSize}px monospace`;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      // Assessed petals: bright labels. Unassessed: faded for users, visible for admins
      const adminAlpha = hasProgress || levelComplete ? 0.95 : 0.65;
      const userAlpha = hasProgress || levelComplete ? 0.70 : 0.30;
      const alpha = isAdmin ? adminAlpha : userAlpha;
      ctx.fillStyle = hasProgress || levelComplete
        ? hsla(p.hue + ho + hueShift, isAdmin ? 60 : 40, p.litTip + 20 + litBoost, alpha)
        : hsla(p.hue + ho + 10, isAdmin ? 30 : 20, isAdmin ? 65 : 55, alpha);
      ctx.fillText(label, 0, -p.halfH * 0.15);
    }

    ctx.restore();
  }

  // ── Animation loop ──────────────────────────────────

  _startAnimLoop(tickFn) {
    if (this._animating) return;
    this._animating = true;
    const loop = () => {
      tickFn();
      if (this._animating) {
        this._animId = requestAnimationFrame(loop);
      } else {
        this._animId = null;
      }
    };
    this._animTickFn = loop;          // store for visibility resume (Opt 1)
    this._animId = requestAnimationFrame(loop);
  }

  _stopAnimLoop() {
    this._animating = false;
    if (this._animId) { cancelAnimationFrame(this._animId); this._animId = null; }
  }

  // ── Public animations ──

  showAll() {
    this._stopAnimLoop();
    this.petals.forEach(p => { p.opacity = 1; p.scale = 1; p.brightness = 0; });
    this.draw();
  }

  bloomIn() {
    if (this._animating) return;

    this.petals.forEach(p => { p.opacity = 0; p.scale = 0.1; p.brightness = 0; });

    const startTime = performance.now();
    const petalTimings = this.petals.map(p => {
      const levelDelay = p.lvl * 350;
      const petalDelay = p.i * (35 - p.lvl * 3);
      return { delay: levelDelay + petalDelay, dur: 600 + p.lvl * 80 };
    });
    const totalDur = Math.max(...petalTimings.map(t => t.delay + t.dur));

    this._startAnimLoop(() => {
      const now = performance.now() - startTime;
      this.petals.forEach((p, i) => {
        const t = petalTimings[i];
        const elapsed = now - t.delay;
        if (elapsed < 0) return;
        const progress = Math.min(elapsed / t.dur, 1);
        const ease = 1 - Math.pow(1 - progress, 3);
        p.opacity = ease;
        p.scale   = 0.1 + 0.9 * ease;
      });
      this.draw();
      if (now >= totalDur) {
        this.petals.forEach(p => { p.opacity = 1; p.scale = 1; });
        this.draw();
        this._animating = false;
      }
    });
  }

  pulseCompass() {
    this._compassPulse = 1.0;
    const start = performance.now();
    const dur = 900;
    const decay = () => {
      const elapsed = performance.now() - start;
      const t = Math.min(elapsed / dur, 1);
      this._compassPulse = 1.0 - t * t;  // ease-out decay
      if (!this._animating) this._scheduleDraw();
      if (t < 1) requestAnimationFrame(decay);
      else this._compassPulse = 0;
    };
    requestAnimationFrame(decay);
  }

  pulse() {
    if (this._animating) return;
    this.pulseCompass();

    this.petals.forEach(p => { p.opacity = 1; p.scale = 1; p.brightness = 0; });

    const startTime = performance.now();
    const petalTimings = this.petals.map(p => ({
      delay: p.lvl * 100 + p.i * 12,
      upDur: 220,
      downDur: 450,
    }));
    const totalDur = Math.max(...petalTimings.map(t => t.delay + t.upDur + t.downDur));

    this._startAnimLoop(() => {
      const now = performance.now() - startTime;
      this.petals.forEach((p, i) => {
        const t = petalTimings[i];
        const elapsed = now - t.delay;
        if (elapsed < 0) {
          p.scale = 1; p.brightness = 0;
        } else if (elapsed < t.upDur) {
          const prog = elapsed / t.upDur;
          const ease = 1 - Math.pow(1 - prog, 3);
          p.scale      = 1 + 0.14 * ease;
          p.brightness = 18 * ease;
        } else {
          const prog = Math.min((elapsed - t.upDur) / t.downDur, 1);
          const ease = 1 - Math.pow(1 - prog, 3);
          p.scale      = 1.14 - 0.14 * ease;
          p.brightness = 18 * (1 - ease);
        }
      });
      this.draw();
      if (now >= totalDur) {
        this.petals.forEach(p => { p.scale = 1; p.brightness = 0; });
        this.draw();
        this._animating = false;
      }
    });
  }

  /* ── Petals-Part Transition ────────────────────────── */

  _petalsParted = false;

  transitionPetals(direction) {
    // direction: 'part' (open) or 'close'
    const isPart = direction === 'part';
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const duration = reducedMotion ? 0 : (isPart ? 750 : 600);

    // If already in desired state, resolve immediately
    if (isPart && this._petalsParted) return Promise.resolve();
    if (!isPart && !this._petalsParted) return Promise.resolve();

    return new Promise((resolve) => {
      const startTime = performance.now();
      const numLevels = LOTUS_CONFIG ? LOTUS_CONFIG.petalsPerLevel.length : 1;

      // Snapshot current positions as start
      this.petals.forEach(p => {
        p._startPx = p.px;
        p._startPy = p.py;
      });

      const tick = () => {
        const elapsed = performance.now() - startTime;
        const rawT = Math.min(elapsed / duration, 1);
        // ease-out for part, ease-in-out for close
        const globalT = isPart
          ? 1 - Math.pow(1 - rawT, 3)
          : rawT < 0.5 ? 4 * rawT * rawT * rawT : 1 - Math.pow(-2 * rawT + 2, 3) / 2;

        this.petals.forEach(p => {
          // Stagger: inner petals move first
          const staggerDelay = p.lvl * 30;
          const petalElapsed = Math.max(0, elapsed - staggerDelay);
          const petalRawT = Math.min(petalElapsed / (duration - staggerDelay * 0.5), 1);
          const t = isPart
            ? 1 - Math.pow(1 - petalRawT, 3)
            : petalRawT < 0.5 ? 4 * petalRawT * petalRawT * petalRawT : 1 - Math.pow(-2 * petalRawT + 2, 3) / 2;

          const targetPx = isPart ? p.partedPx : p.restPx;
          const targetPy = isPart ? p.partedPy : p.restPy;

          p.px = p._startPx + (targetPx - p._startPx) * t;
          p.py = p._startPy + (targetPy - p._startPy) * t;
        });

        this.draw();

        if (rawT < 1) {
          requestAnimationFrame(tick);
        } else {
          // Snap to final positions
          this.petals.forEach(p => {
            p.px = isPart ? p.partedPx : p.restPx;
            p.py = isPart ? p.partedPy : p.restPy;
          });
          this._petalsParted = isPart;
          this.draw();
          resolve();
        }
      };
      requestAnimationFrame(tick);
    });
  }

  setWind(wv) { this._windVector = wv; }

  _startWindSway() {
    if (this._swayIntervalId) return;          // prevent double-start
    const swayLoop = () => {
      this._swayIntervalId = requestAnimationFrame(swayLoop);
      const now = performance.now();
      const w = this._windVector;
      const mag = Math.sqrt(w.x * w.x + w.y * w.y) || 0.01;
      const t = now * 0.001;

      this._swayRot     = Math.sin(t * 0.8) * 1.5 * mag;
      this._swayOffsetX = Math.sin(t * 0.6) * 4 * w.x;

      // Compass weathervane rotation: slow primary swing + organic flutter
      const primarySwing = Math.sin(t * 0.5) * 8 * mag;
      const flutter = Math.sin(t * 2.3) * 2.5 * mag + Math.sin(t * 3.7) * 1.2 * mag;
      this._compassNeedleAngle = primarySwing + flutter;

      this._shimmerTime = t;

      if (!this._animating) this._scheduleDraw();
    };
    this._swayIntervalId = requestAnimationFrame(swayLoop);
  }

  // Perf Opt 4: coalesce multiple draw() requests into one per frame
  _scheduleDraw() {
    if (this._drawScheduled) return;
    this._drawScheduled = true;
    requestAnimationFrame(() => {
      this._drawScheduled = false;
      this.draw();
    });
  }

  // Perf Opt 1: pause/resume all loops when tab is hidden
  _handleVisibility() {
    if (document.hidden) {
      this._hidden = true;
      if (this._swayIntervalId) { cancelAnimationFrame(this._swayIntervalId); this._swayIntervalId = null; }
      if (this._animId) { cancelAnimationFrame(this._animId); this._animId = null; }
      // Falling petals (< 1s) simply lose remaining frames — acceptable
    } else {
      this._hidden = false;
      this._startWindSway();
      // Resume bloom/pulse if still in progress
      if (this._animating && this._animTickFn) {
        this._animId = requestAnimationFrame(this._animTickFn);
      }
    }
  }

  destroy() {
    document.removeEventListener('visibilitychange', this._onVisChange);
    window.removeEventListener('resize', this._onResize);
    this._stopAnimLoop();
    if (this._swayIntervalId) cancelAnimationFrame(this._swayIntervalId);
  }
}


/* ================================================================
   SECTION 7: FALLING PETAL
   900ms, 3 phases: spawn, drift, lock-in.
   ================================================================ */
class FallingPetal {
  constructor(lotusCanvas, slotInfo, hue, windVector, onLockIn) {
    this._lotusCanvas = lotusCanvas;
    this._slot        = slotInfo;
    this._hue         = hue;
    this._wind        = windVector;
    this._onLockIn    = onLockIn;
    this._durationMs  = 900;
    this._rafId       = null;
    this._lockInFired = false;
    this._id          = lotusCanvas.allocateFallingPetalId();

    this.done = new Promise(resolve => { this._resolve = resolve; });
  }

  start() {
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const targetX = this._slot.absX;
    const targetY = this._slot.absY;

    const seed = this._slot.petalIdx || 0;
    const spawnOffsetX = Math.sin(seed * 7.3 + 0.5) * vw * 0.12;

    this._pathPoints = {
      x0: targetX + spawnOffsetX,
      y0: -60,
      x1: targetX + spawnOffsetX + this._wind.x * 80,
      y1: vh * 0.25,
      x2: targetX + this._wind.x * 40,
      y2: targetY - vh * 0.12,
      x3: targetX,
      y3: targetY,
    };

    const startTime = performance.now();
    const tick = (now) => {
      const elapsed  = now - startTime;
      const progress = Math.min(elapsed / this._durationMs, 1.0);
      this._applyFrame(progress);
      if (progress < 1.0) {
        this._rafId = requestAnimationFrame(tick);
      } else {
        this._finalize();
      }
    };
    this._rafId = requestAnimationFrame(tick);
  }

  _applyFrame(progress) {
    const P = this._pathPoints;
    const SPAWN_END = 0.10;
    const DRIFT_END = 0.85;

    let x, y, rotation, scale, opacity;

    if (progress <= SPAWN_END) {
      const t = progress / SPAWN_END;
      const eased = Easing.easeOutCubic(t);
      x = P.x0; y = P.y0;
      rotation = 0;
      scale   = lerp(0.6, 0.85, eased);
      opacity = eased;

    } else if (progress <= DRIFT_END) {
      const t = (progress - SPAWN_END) / (DRIFT_END - SPAWN_END);
      const eased = Easing.easeInOutCubic(t);
      x = cubicBezier(eased, P.x0, P.x1, P.x2, P.x3);
      y = cubicBezier(eased, P.y0, P.y1, P.y2, P.y3);
      const windSway  = Math.sin(t * Math.PI * 2) * 8 * (this._wind.x > 0 ? 1 : -1);
      const tiltBlend = Easing.easeInOutCubic(Math.max(0, (t - 0.5) / 0.5));
      rotation = lerp(windSway, this._slot.angle, tiltBlend);
      scale   = lerp(0.85, 1.0, eased);
      opacity = 1.0;

    } else {
      const t = (progress - DRIFT_END) / (1.0 - DRIFT_END);
      const eased = Easing.easeOutBack(t);

      if (!this._lockInFired) {
        this._lockInFired = true;
        if (this._onLockIn) this._onLockIn();
      }

      x = P.x3; y = P.y3;
      rotation = this._slot.angle;
      scale   = lerp(1.02, 1.0, eased);
      opacity = 1.0;
    }

    const halfW = (this._slot.petalW || 36) / 2;
    const halfH = (this._slot.petalH || 80) / 2;

    this._lotusCanvas.setActiveFallingPetal(this._id, {
      x, y, rotation, scale, opacity,
      halfW, halfH,
      hue:   this._hue,
      level: this._slot.level,
      label: this._label || null,
    });
    this._lotusCanvas._scheduleDraw();    // batched (Opt 4)
  }

  _finalize() {
    this._rafId = null;
    this._lotusCanvas.setActiveFallingPetal(this._id, null);
    this._lotusCanvas._scheduleDraw();    // batched (Opt 4)
    this._resolve();
  }

  destroy() {
    if (this._rafId) cancelAnimationFrame(this._rafId);
    this._lotusCanvas.setActiveFallingPetal(this._id, null);
    this._resolve();
  }
}


/* ================================================================
   SECTION 8: LOTUS PROGRESS SYSTEM (Orchestrator)

   Framework-driven API:
     const lps = new LotusProgressSystem(canvas, viewport);
     lps.init();
     lps.configureFromFramework(summary);        // geometry from framework
     lps.loadInstances([coachData, aiData]);      // composite from instances
     lps.animateProgressDrop('01', 'toolset', 0.83);
   ================================================================ */
class LotusProgressSystem {
  constructor(canvasEl, viewportEl, config = {}) {
    this._canvas   = canvasEl;
    this._viewport = viewportEl;
    this._hue      = config.levelHue    || 280;
    this._wind     = config.windVector  || { x: 0.4, y: -0.2 };
    this._lotusCanvas  = null;
    this._frameworkSummary = null;
  }

  get canvas() { return this._lotusCanvas; }

  init() {
    this._lotusCanvas = new LotusCanvas(this._canvas);
    this._lotusCanvas.setHue(this._hue);
    this._lotusCanvas.setWind(this._wind);
  }

  /* ── Framework Configuration ─────────────────────── */

  configureFromFramework(summary) {
    this._frameworkSummary = summary;
    this._lotusCanvas?.configureFromFramework(summary);
  }

  /* ── LMS API Methods ───────────────────────────────── */

  configureStages(config) {
    this._lotusCanvas?.configureStages(config);
  }

  setStageProgress(stage, dimension, progress) {
    const petalIdx = this._lotusCanvas?._getPetalIdxForSlot(stage, dimension);
    if (petalIdx !== null) {
      this._lotusCanvas.setPetalProgress(petalIdx, progress);
    }
  }

  setConfidence(stage, dimension, confidence, source) {
    const petalIdx = this._lotusCanvas?._getPetalIdxForSlot(stage, dimension);
    if (petalIdx !== null) {
      this._lotusCanvas.setPetalConfidence(petalIdx, confidence, source);
    }
  }

  setKpiLevel(stage, kpiId, level) {
    const progressMap = { beginner: 0.33, developing: 0.66, mastery: 1.0 };
    const progress = progressMap[level] || 0;
    this.setStageProgress(stage, kpiId, progress);
  }

  animateProgressDrop(stage, dimension, newProgress, label) {
    const slotInfo = this._lotusCanvas?.getSlotInfo(stage, dimension);
    if (!slotInfo) return Promise.resolve();

    const wind = this._wind;
    // Build label for the falling petal
    const isAdmin = document.body.getAttribute('data-is-admin') === '1';
    const dimLetter = dimension === 'mindset' ? 'M'
                    : dimension === 'toolset' ? 'T'
                    : dimension === 'skillset' ? 'S'
                    : dimension.replace('kpi-', 'K');
    const petalLabel = label || (isAdmin ? dimLetter + stage : dimLetter);

    return new Promise((resolve) => {
      const fp = new FallingPetal(
        this._lotusCanvas,
        slotInfo,
        this._hue,
        wind,
        () => {
          this.setStageProgress(stage, dimension, newProgress);
          resolve();
        }
      );
      fp._label = petalLabel;
      fp.start();
    });
  }

  /* ── Instance Loading (composite merge) ────────────── */

  loadInstances(instances) {
    if (!this._frameworkSummary) {
      console.warn('loadInstances: call configureFromFramework first');
      return;
    }

    this._lotusCanvas.resetAllState();
    const merged = this._mergeInstances(instances);
    this._mergedData = merged; // expose for explorer detail view
    this._applyMergedState(merged);
  }

  _mergeInstances(instances) {
    const merged = {
      responses: {},
      aiConfidence: {},
      kpiResponses: {},
      hasAiSource: false,
    };

    const kpiLevelRank = { beginner: 1, developing: 2, mastery: 3 };

    for (const inst of instances) {
      const assessorType = inst.assessor?.type || 'coach';
      const responses = inst.responses || {};
      const responseMeta = inst.responseMeta || {};
      const kpiResponses = inst.kpiResponses || {};

      // Merge responses
      for (const [itemId, rawValue] of Object.entries(responses)) {
        const val = typeof rawValue === 'object' ? rawValue.value : rawValue;
        const existingIsHuman = merged.responses[itemId] !== undefined
          && merged.aiConfidence[itemId] === undefined;

        if (assessorType === 'ai') {
          merged.hasAiSource = true;
          const newConf = responseMeta[itemId]?.confidence || 0.5;
          const prevConf = merged.aiConfidence[itemId] || 0;

          // AI only overrides non-human responses; higher confidence wins AI vs AI
          if (!existingIsHuman && newConf >= prevConf) {
            merged.responses[itemId] = val;
          }
          merged.aiConfidence[itemId] = Math.max(prevConf, newConf);
        } else {
          // Human (coach/self) always wins
          merged.responses[itemId] = val;
        }
      }

      // Merge KPIs: highest level wins
      for (const [kpiId, rawLevel] of Object.entries(kpiResponses)) {
        const lvl = typeof rawLevel === 'object' ? rawLevel.value : rawLevel;
        const existing = merged.kpiResponses[kpiId];
        if (!existing || (kpiLevelRank[lvl] || 0) > (kpiLevelRank[existing] || 0)) {
          merged.kpiResponses[kpiId] = lvl;
        }
      }
    }

    return merged;
  }

  _applyMergedState(merged) {
    const framework = this._frameworkSummary;

    for (const stage of framework.stages) {
      const stageId = stage.id;
      const sPrefix = 's' + stageId + '-';

      // Compute dimension scores from merged responses
      for (const dim of framework.dimensionKeys) {
        const dimKey = dim[0]; // 'm', 't', 's'
        const itemCount = stage.itemCounts[dim];
        const itemPrefix = sPrefix + dimKey + '-';

        // Count true responses for this stage+dimension
        let trueCount = 0;
        for (const [itemId, value] of Object.entries(merged.responses)) {
          if (itemId.startsWith(itemPrefix) && value === true) {
            trueCount++;
          }
        }

        const progress = Math.min(1.0, itemCount > 0 ? trueCount / itemCount : 0);
        this.setStageProgress(stageId, dim, progress);

        // Set confidence from AI metadata
        if (merged.hasAiSource) {
          const dimItems = Object.keys(merged.aiConfidence)
            .filter(k => k.startsWith(itemPrefix));
          if (dimItems.length > 0) {
            const minConf = Math.min(...dimItems.map(k => merged.aiConfidence[k]));
            this.setConfidence(stageId, dim, minConf, 'ai');
          } else {
            this.setConfidence(stageId, dim, 1.0, 'coach');
          }
        } else {
          this.setConfidence(stageId, dim, 1.0, 'coach');
        }
      }

      // Set KPI petals
      const kpiPrefix = sPrefix + 'kpi-';
      const kpiKeys = Object.keys(merged.kpiResponses)
        .filter(k => k.startsWith(kpiPrefix))
        .sort();
      kpiKeys.forEach((key, idx) => {
        const kpiId = 'kpi-' + String(idx + 1).padStart(2, '0');
        this.setKpiLevel(stageId, kpiId, merged.kpiResponses[key]);
      });
    }
  }

  // Backward compat: single instance delegates to loadInstances
  loadInstance(data) {
    this.loadInstances([data]);
  }

  /* ── API-driven Initialization ─────────────────────── */

  async initFromAPI() {
    // Fetch framework summary for canvas config
    return new Promise((resolve) => {
      if (typeof apiPost !== 'function') {
        console.warn('[LPS] apiPost not available, canvas will render empty');
        resolve();
        return;
      }
      apiPost('get_framework_summary', {}, (resp) => {
        if (resp.error || !resp.results || !resp.results.summary) {
          console.warn('[LPS] No framework summary available');
          resolve();
          return;
        }
        const summary = resp.results.summary;
        this.configureFromFramework(summary);

        // Now load user state
        apiPost('get_lotus_state', {}, (stateResp) => {
          if (!stateResp.error && stateResp.results) {
            const r = stateResp.results;
            if (r.responses) {
              // Apply user's current state to petals
              this.loadInstances([{
                assessor: { type: 'ai' },
                responses: r.responses,
                responseMeta: r.responseMeta || {},
                kpiResponses: r.kpiResponses || {}
              }]);
            }
          }
          this.canvas.bloomIn();
          resolve();
        });
      });
    });
  }

  /* ── State & Controls ───────────────────────────────── */

  getState() {
    const result = {};
    if (!this._lotusCanvas) return result;
    for (const [stageId, stageSlots] of this._lotusCanvas._slotMap) {
      result[stageId] = {};
      for (const [dim, petalIdx] of stageSlots) {
        const state = this._lotusCanvas._petalStates.get(petalIdx);
        result[stageId][dim] = state ? { ...state } : { progress: 0, confidenceLevel: 1.0, assessmentSource: null };
      }
    }
    return result;
  }

  setWind(windVector)  { this._wind = windVector; this._lotusCanvas?.setWind(windVector); }

  setLevelColor(hue) {
    this._hue = hue;
    this._lotusCanvas?.setHue(hue);
  }

  bloomIn()  { this._lotusCanvas?.bloomIn(); }
  pulse()    { this._lotusCanvas?.pulse(); }
  reset() {
    this._lotusCanvas?.showAll();
    this._lotusCanvas?.resetAllState();
  }

  destroy()  { this._lotusCanvas?.destroy(); }

  /* ── View State Machine ─────────────────────────────── */

  _viewState = 'LOTUS_VIEW';
  _viewStateListeners = [];

  get viewState() { return this._viewState; }

  onViewStateChange(fn) {
    this._viewStateListeners.push(fn);
    return () => { this._viewStateListeners = this._viewStateListeners.filter(f => f !== fn); };
  }

  setViewState(newState) {
    const valid = {
      'LOTUS_VIEW':        ['EXPLORER_OVERVIEW'],
      'EXPLORER_OVERVIEW': ['LOTUS_VIEW', 'STAGE_DETAIL'],
      'STAGE_DETAIL':      ['EXPLORER_OVERVIEW'],
    };
    if (!valid[this._viewState]?.includes(newState)) {
      console.warn(`Invalid view transition: ${this._viewState} → ${newState}`);
      return;
    }
    const prev = this._viewState;
    this._viewState = newState;
    this._viewStateListeners.forEach(fn => fn(newState, prev));
  }
}


// ─── Self-initialization ────────────────────────────────────────────────
function initLotus() {
  var canvasEl = document.getElementById('lotus-canvas');
  if (!canvasEl) return;

  var viewportEl = document.getElementById('lps-viewport');
  var lps = new LotusProgressSystem(canvasEl, viewportEl, {
    levelHue: 280,
    windVector: { x: 0.4, y: -0.2 }
  });
  lps.init();
  lps.canvas._startWindSway();


  // Fetch data and render
  lps.initFromAPI();

  // Expose globally for chat-overlay.js and explorer-view.js
  window.lps = lps;
  window.dispatchEvent(new CustomEvent('lps-ready', { detail: lps }));

  // Handle sidebar resize
  var sidebar = document.getElementById('sidebar');
  if (sidebar) {
    sidebar.addEventListener('transitionend', function() {
      lps.canvas.resize();
    });
  }

  // Handle visibility
  lps.canvas._handleVisibility();
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initLotus);
} else {
  initLotus();
}

})(); // end IIFE
