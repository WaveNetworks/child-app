<?php
/**
 * privacy.php — public Privacy Policy.
 *
 * Public by design: the registration checkbox links here, so it must be
 * readable BEFORE an account exists. The in-app GDPR self-service page
 * (app/?page=privacy) is the logged-in counterpart to this.
 *
 * ⚠️ BASELINE, NOT LEGAL ADVICE. Written to describe what the app genuinely
 * does — the tables it stores, the third parties data reaches, and the controls
 * that already exist. Have it reviewed before public launch, and check the
 * retention periods actually match what the cron jobs do.
 */
include(__DIR__ . '/include/common_auth.php');

$page_title = 'Privacy Policy';
$updated    = '13 August 2026';
ob_start();
?>
<div class="text-start">
    <h1 class="h3 mb-1">Privacy Policy</h1>
    <p class="text-body-secondary small">Last updated <?= h($updated) ?></p>

    <div class="alert alert-warning small" role="alert">
        This policy is a working baseline and has not yet been reviewed by a lawyer.
    </div>

    <h2 class="h5 mt-4">1. What we collect</h2>
    <ul>
        <li><strong>Account details</strong> — your email address, name, and a hashed password.
            We never store your password in a readable form.</li>
        <li><strong>Your content</strong> — everything you enter or generate: business details,
            audience notes, copy, knowledge-base sections and related assets.</li>
        <li><strong>Activity</strong> — a log of what happened in each brand and who did it,
            including work done by AI or by an agent you connected. This is what powers the
            activity timeline and lets you see how something came to be.</li>
        <li><strong>Usage and cost</strong> — token counts and cost per AI generation, so you can
            see your own spend.</li>
        <li><strong>Your API keys</strong>, if you add them — encrypted at rest, and never shown
            again in full.</li>
    </ul>

    <h2 class="h5 mt-4">2. What we do not do</h2>
    <ul>
        <li>We do not sell your data.</li>
        <li>We do not use your your content to train AI models.</li>
        <li>We do not add advertising trackers to this application.</li>
    </ul>

    <h2 class="h5 mt-4">3. Where your data goes</h2>
    <p>
        Generating content sends the relevant parts of your brand to an AI provider — Anthropic,
        OpenAI or DeepSeek, depending on the model used for that step. Only what the step needs
        is sent. Fields marked as personal information are redacted or replaced with a placeholder
        before the request leaves us, and a request is blocked outright if personal data is
        detected where none was declared.
    </p>
    <p>
        If you connect your own provider key, those requests run under your account with that
        provider and are governed by their privacy policy as well as this one.
    </p>

    <h2 class="h5 mt-4">4. How long we keep it</h2>
    <ul>
        <li><strong>Your content</strong> — until you delete it or close your account.</li>
        <li><strong>Activity log</strong> — retained as the history of your brand, because
            features like staleness tracking and replay are built from it.</li>
        <li><strong>AI request log</strong> — a short rolling debug window, holding the redacted
            prompt only, never the original.</li>
    </ul>

    <h2 class="h5 mt-4">5. Your rights</h2>
    <p>
        Once signed in, the privacy page inside the app lets you export everything we hold about
        you, withdraw optional consents, and delete your account and its data. You do not need to
        ask us to do any of it.
    </p>

    <h2 class="h5 mt-4">6. Security</h2>
    <p>
        Data is transmitted over HTTPS. Passwords are hashed, and API keys are encrypted at rest.
        Each account's brand data is stored in a separate database shard. No system is perfectly
        secure, and we will tell you promptly if we become aware of a breach affecting your data.
    </p>

    <h2 class="h5 mt-4">7. Cookies</h2>
    <p>
        We use a session cookie to keep you signed in. That is the only cookie this application
        sets, and it is required for it to work.
    </p>

    <h2 class="h5 mt-4">8. Changes</h2>
    <p>
        If we make a material change we will ask you to agree again the next time you sign in,
        and record that agreement.
    </p>

    <h2 class="h5 mt-4">9. Contact</h2>
    <p>Privacy questions can be sent through the feedback form inside the app.</p>

    <hr class="my-4">
    <p class="small">
        <a href="terms.php">Terms of Service</a> ·
        <a href="auth/login.php">Back to sign in</a>
    </p>
</div>
<?php
$page_content_html = ob_get_clean();
include(__DIR__ . '/auth/template.php');
