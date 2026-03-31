# Explorer & Canvas Experience Spec

Goal: Match or exceed the DSWADemo reference implementation.
Source of truth: `memory/project_demo_explorer_features.md`

## Current State
- Lotus canvas, petal animations, view state machine, explorer toggle, water ripples: **done**
- Explorer overview with stage tiles, dimension bars, hard walls: **done**
- Stage detail view: **placeholder only** — needs full content
- Diagnostic item interaction (expand/reflect/save): **missing**
- KPI display with mastery badges: **missing**
- Field wisdom quotes: **missing**
- Content & Training section in stage detail: **missing**
- Content detail modal: **missing**
- Explorer → chat data flow (reflections relay to AI): **missing**
- Contextual content banners mid-conversation (AI bridge messages): **API-side, partial**
- Compass rose as 3-way pie chart: **basic compass exists, pie chart missing**
- Dynamic 4-zone layout: **not yet implemented**

## Work Packages

### WP1: Stage Detail — Full Framework Content
Fill the stage detail view with real framework data instead of placeholders.
- Stage description, typical behavior, core belief, stuck/unlock text
- Source: framework JSON `stages[n].description`, `stages[n].typicalBehavior`, etc.

### WP2: Diagnostic Items in Stage Detail
Render all diagnostic items grouped by dimension (Mindset → Toolset → Skillset).
- Each item: status dot (bright green=resolved, dim=unresolved), title, subtitle, chevron
- Clickable to expand inline
- Resolved: show AI observation + "What worked well?" textarea + Save
- Unresolved: show question from matrix + example placeholder + textarea + Save
- Save persists to instance file via API

### WP3: KPI Display in Stage Detail
Render KPIs with level descriptions and current-level badges.
- Each KPI: name, Beginner/Developing/Mastery descriptions, badge showing current level
- Clickable chevron for expand (future: upload proof)

### WP4: Field Wisdom in Stage Detail
Render the `fieldWisdom` quote from the framework for each stage.
- Italicized quote block with attribution

### WP5: Content & Training in Stage Detail
Render stage-specific content items from the content catalog.
- Thumbnail, title, dimension badge, type/duration
- Clickable → content detail overlay
- "Explore more resources in the DSWA Community Catalog" link at bottom

### WP6: Content Detail Overlay
When clicking a content item (from stage detail or chat banner), show full detail:
- Thumbnail gallery
- Title, subtitle, stage badge, dimension badge, type/duration, metadata
- Description, learning objectives (mapped to diagnostic items), tags
- CTA: "View in DSWA Community" link (not download)

### WP7: Explorer → Chat Data Flow
When user saves reflections in the explorer and returns to chat:
- Collect all new reflections saved during the explorer session
- On view state change back to LOTUS_VIEW, send reflections to chat API
- AI responds with "Here's what I noticed from your self-assessment:" message
- Feed reflection text into the conversation log

### WP8: Compass Rose Pie Chart
Enhance the compass rose to show a 3-way weighted pie chart:
- Mindset (purple), Toolset (blue), Skillset (green) segments
- Faded when no data, fills as user progresses
- Reflects the user's dimensional balance

### WP9: Center Pulse on API Interaction
Ensure every client-server interaction triggers:
- Lotus center pulse (scale + brightness flash)
- Water ripple emanation from center
- Already partially implemented — verify and wire up

### WP10: Dynamic 4-Zone Layout
Make the 4 layout zones responsive to content:
- Chat messages (scrollable), chat input (fixed), interactive area (MC/banners/explorer), lotus negative space
- Zones resize based on current content type
- Lotus always visible and clickable
