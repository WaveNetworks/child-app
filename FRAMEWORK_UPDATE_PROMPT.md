# Framework Update Prompt: Growth Path Choice Questions

> **This is an ElevateHER-specific example.** It documents the prompt used to evolve the ElevateHER assessment framework. If your child app doesn't use the inherited assessment engine, you can delete this file.

Copy everything below the line into a new Claude session along with your current framework JSON files.

---

## Context

I have an ElevateHER assessment framework (spec v2.3) for direct sales professionals. The assessment uses an AI chat that walks users through phases: **profile > floor > ceiling > zone > score**. A question matrix drives the conversation.

**Current state:**
- 11 stages (00-10), each with mindset/toolset/skillset items and KPIs
- 55 questions total: 8 floor, 9 ceiling, 38 zone
- 67 content catalog items (short-courses, articles, challenges, etc.)
- Hard walls between stages gate advancement until KPIs reach "developing" level
- When matrix questions are exhausted but items remain unresolved, the AI asks free-form questions (gap detection)

**Problem:** When a user hits a hard wall (KPIs not yet at "developing"), they see a "Your Growth Path" card showing which KPIs need work. But the conversation has nowhere productive to go — we need questions that:

1. Ask the user **what they want to work on** (multiple choice from their unresolved dimensions/areas)
2. Based on their choice, surface a **targeted short list of content** (2-3 items max)
3. Keep the conversation feeling like coaching, not a dead end

## What I Need You To Create

### 1. New Question Tier: "growth" Questions

Add a new tier called `"growth"` to the question matrix. These questions fire when:
- The user is at a hard wall (KPIs blocking advancement)
- Floor/ceiling/zone questions for the current stage are exhausted

**Growth questions should:**
- Be multiple-choice format (`answerFormat: "multiple-choice"`)
- Present 3-4 options based on the user's unresolved dimensions (mindset, toolset, skillset)
- Each choice maps to specific content catalog items via a new `contentMapping` field
- Feel encouraging and coaching-oriented, not diagnostic

### 2. Growth Question Structure

Each growth question should follow this JSON structure:

```json
{
  "id": "qm-growth-s00-01",
  "tier": "growth",
  "priority": 1,
  "stage": "00",
  "prompt": "I can see you're building a great foundation! Which area would you most like to grow in right now?",
  "promptNotes": "Presented when user hits wall-00-01. Choices should map to the three dimensions of Stage 00.",
  "answerFormat": "multiple-choice",
  "choices": [
    {
      "label": "Getting comfortable talking to new people",
      "value": "s00-mindset-comfort",
      "dimension": "mindset",
      "contentIds": ["cat-s00-m-fear-to-curiosity", "cat-s00-m-rewire-strangers"]
    },
    {
      "label": "Learning how to start conversations naturally",
      "value": "s00-skillset-conversations",
      "dimension": "skillset",
      "contentIds": ["cat-s00-s-hello-stranger", "cat-s00-s-7day-hi"]
    },
    {
      "label": "Setting up a simple daily routine",
      "value": "s00-toolset-routine",
      "dimension": "toolset",
      "contentIds": ["cat-s00-t-micro-habit", "cat-s00-t-tracking-101"]
    },
    {
      "label": "I'd like help with all of these!",
      "value": "s00-all",
      "dimension": "all",
      "contentIds": ["cat-s00-m-fear-to-curiosity", "cat-s00-s-hello-stranger", "cat-s00-t-micro-habit"]
    }
  ],
  "targetStages": ["00"],
  "tags": ["growth", "wall-gate", "content-routing"],
  "resolvableItems": [],
  "resolvableKpis": []
}
```

### 3. Questions Needed

Create growth questions for **each stage that has a hard wall after it** (stages 00 through 09). Each stage needs at least 2 growth questions:

1. **Dimension picker** — "Which area would you like to focus on?" (mindset/toolset/skillset choices)
2. **Specific skill picker** — Based on common unresolved items at that stage, offer specific topics

The choices MUST reference actual content catalog item IDs from the attached content catalog JSON.

### 4. Content Catalog Gap Analysis

While creating growth questions, check whether the content catalog has adequate coverage for each stage + dimension combination. If a stage/dimension has no content items, flag it as a gap and create placeholder content catalog entries with:

```json
{
  "id": "cat-sXX-d-slug",
  "type": "short-course",
  "title": "[NEEDED] Title describing what this content should cover",
  "description": "Placeholder — content needed for Stage XX [dimension] growth path",
  "dimensions": ["dimension"],
  "stages": ["XX"],
  "estimatedDuration": "15 min",
  "status": "placeholder"
}
```

### 5. Version the Framework

- Bump the question matrix to include the new growth tier
- Update any schema version references
- Add a changelog entry noting the growth questions addition
- Keep all existing floor/ceiling/zone questions unchanged

## Files I'm Attaching

1. **Question Matrix JSON** — current 55 questions (paste the full questionMatrix JSON)
2. **Content Catalog JSON** — current 67 items (paste the full contentCatalog JSON)
3. **Stage files** — stage_00 through stage_10 (paste all stage JSONs, or at minimum stages 00-03)
4. **Framework header** — the header JSON with hard wall definitions

## Output Format

Give me back:
1. **Updated question matrix JSON** — with all existing questions + new growth questions added
2. **Updated content catalog JSON** — with any new placeholder items added
3. **Gap report** — a markdown table showing stage/dimension content coverage
4. **Summary** — what was added and any recommendations

Do NOT modify existing questions — only ADD new growth-tier questions.
