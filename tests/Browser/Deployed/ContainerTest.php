<?php

/**
 * Runs only when DEPLOYED_URL is set: end-to-end check against a running container / deployment.
 */
beforeEach(fn () => getenv('DEPLOYED_URL') ?: $this->markTestSkipped('Set DEPLOYED_URL to run.'));

it('signs up and tracks time on the deployed app', function () {
    $base = rtrim(getenv('DEPLOYED_URL'), '/');
    $email = 'e2e-'.bin2hex(random_bytes(4)).'@example.com';

    visit("{$base}/register")
        ->fill('name', 'E2E User')->fill('email', $email)->fill('password', 'secret-pass-123')
        ->click('Create free account')
        ->wait(1.5)
        ->assertPathIs('/tracker')
        ->fill('input[placeholder="What are you working on?"]', 'Deployed smoke test')
        ->click('Start')->wait(2)->assertSee('Stop')
        ->click('Stop')->wait(1)->assertScript("[...document.querySelectorAll('input')].some(i => i.value === 'Deployed smoke test')", true)
        ->click('Dashboard')->wait(1)->assertSee('Total time')
        ->assertNoJavaScriptErrors();
});
