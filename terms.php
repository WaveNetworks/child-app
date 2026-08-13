<?php
/**
 * terms.php — public Terms of Service.
 *
 * Public by design: the registration checkbox links here, so it must be
 * readable BEFORE an account exists. common_auth.php is the same bootstrap the
 * login and register pages use and does not require a session.
 *
 * ⚠️ BASELINE, NOT LEGAL ADVICE. This describes what the app actually does with
 * data so the consent checkbox is not consent to a blank page. Have a solicitor
 * review it before you promote the product publicly — particularly the
 * liability, termination and governing-law sections, which are deliberately
 * plain and may not suit your jurisdiction or your AppSumo terms.
 */
include(__DIR__ . '/include/common_auth.php');

$page_title = 'Terms of Service';
$updated    = '13 August 2026';
ob_start();
?>
<div class="text-start">
    <h1 class="h3 mb-1">Terms of Service</h1>
    <p class="text-body-secondary small">Last updated <?= h($updated) ?></p>

    <div class="alert alert-warning small" role="alert">
        These terms are a working baseline and have not yet been reviewed by a lawyer.
        If that matters to you, please read them carefully before creating an account.
    </div>

    <h2 class="h5 mt-4">1. What this service is</h2>
    <p>
        This service is provided by the operator of this site. Replace this section with a
        plain description of what your application actually does before you launch.
    </p>

    <h2 class="h5 mt-4">2. Your account</h2>
    <p>
        You are responsible for what happens under your account, including anything done by an
        agent you have given a key to. Keep your password and your agent keys private. Tell us
        promptly if you believe either has been exposed, and revoke the key from the AI keys page.
    </p>

    <h2 class="h5 mt-4">3. Your content is yours</h2>
    <p>
        You keep ownership of everything you put into this application and everything it generates
        for you. We claim no ownership of your content. We store and
        process them only to provide the service to you.
    </p>

    <h2 class="h5 mt-4">4. AI-generated material</h2>
    <p>
        AI output can be wrong, biased, or unintentionally similar to something that already
        exists. <strong>Review anything before you publish it.</strong> You are responsible for
        what you put your name to. Where you have configured compliance rules, we check output
        against them — but that check assists you, it does not replace your judgement, and it
        cannot catch everything.
    </p>

    <h2 class="h5 mt-4">5. Your own API keys</h2>
    <p>
        If you connect your own AI provider key, requests made with it run on your account with
        that provider, at their rates, under their terms. We encrypt your key at rest and never
        display it again in full. We are not responsible for charges you incur with a provider,
        and you can remove a key at any time.
    </p>

    <h2 class="h5 mt-4">6. Acceptable use</h2>
    <p>Do not use this application to:</p>
    <ul>
        <li>break the law, or help anyone else do so;</li>
        <li>impersonate a real person or organisation, or misrepresent an affiliation;</li>
        <li>generate content designed to deceive, defraud or harass;</li>
        <li>infringe someone else's intellectual property;</li>
        <li>attack, overload or probe the service or the infrastructure it runs on.</li>
    </ul>

    <h2 class="h5 mt-4">7. Availability</h2>
    <p>
        The service is provided as-is. We do not promise it will be uninterrupted or error-free,
        and we may change or withdraw features. If we plan a change that would lose your data,
        we will give you reasonable notice and a way to export it.
    </p>

    <h2 class="h5 mt-4">8. Liability</h2>
    <p>
        To the extent the law allows, we are not liable for indirect or consequential loss,
        including lost profit, lost business or reputational harm arising from your use of the
        service or from material it generated. Nothing here limits liability that cannot legally
        be limited.
    </p>

    <h2 class="h5 mt-4">9. Ending your account</h2>
    <p>
        You can close your account at any time from the privacy page in the app, which also lets
        you export or delete your data. We may suspend an account that breaches section 6.
    </p>

    <h2 class="h5 mt-4">10. Changes to these terms</h2>
    <p>
        If we make a material change we will ask you to agree again the next time you sign in,
        and record that agreement.
    </p>

    <h2 class="h5 mt-4">11. Contact</h2>
    <p>Questions about these terms can be sent through the feedback form inside the app.</p>

    <hr class="my-4">
    <p class="small">
        <a href="privacy.php">Privacy Policy</a> ·
        <a href="auth/login.php">Back to sign in</a>
    </p>
</div>
<?php
$page_content_html = ob_get_clean();
include(__DIR__ . '/auth/template.php');
