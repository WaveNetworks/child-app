# What this app is for  (copy to docs/OBJECTIVES.md and rewrite for the app)

A coverage manifest answers "was every listed unit built". It cannot answer "does
the app do its job", because the job is not in the list. That gap is how an app
ships with a placeholder dashboard, no sign-in link and dead source links while
reporting 239 of 255 units built.

This file is the other half: what the app exists to DO, in its own domain's terms.
Each objective carries one or more `Verify:` lines that scripts/acceptance.sh
resolves against the running site, so an objective is evidence, not an assertion.

State the app's bias explicitly. Most apps have two plausible centres of gravity
and only one of them is the point — say which wins when they compete for a screen.
(e.g. a campaign app: "an organising tool, not a bookkeeping tool"; the treasury machinery is
legally required but serves the committee, not the campaign.)

## Verify forms

    `Verify: /some/path contains some-marker`   page renders its DATA, not just chrome
    `Verify: links /some/path`                  every href on the page resolves
    `Verify: og /some/path`                     og:image present and an actual image
    `Verify: no-placeholders /some/path`        no scaffold or placeholder text
    `Verify: staff ?page=x`                     behind auth — reported for a human

Prefer markers that prove real content (a CSS class only emitted when rows exist)
over numbers, which change.

---

## O1 — <what a first-time visitor must understand, and how fast>

`Verify: / contains <marker>`

## O2 — <the promise the app makes that it must not break>

`Verify: links /`

## O3 — <the single action the app exists to make easy>

`Verify: og /`

---

## For whoever builds here next

Before marking a task done: which objective does this serve, and can you see it
working on the live site? If the app cannot do one of these, that is the work —
build it and add a coverage row so it becomes visible.

If you find an objective this file is missing, ADD IT. An app whose purpose is not
written down gets built toward whatever is easiest to measure.
