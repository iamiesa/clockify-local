<?php

use App\Models\User;

it('captures the mobile tracker and drawer', function () {
    $this->seed();
    $this->actingAs(User::where('email', 'demo@example.com')->sole());

    visit('/tracker')->on()->mobile()->wait(0.3)->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->screenshot(fullPage: false, filename: 'mt-tracker')
        ->click('button[aria-label="Toggle navigation"]')->wait(0.4)
        ->screenshot(fullPage: false, filename: 'mt-drawer')
        ->click('button[aria-label="Close navigation"]')->wait(0.3)
        ->click('button[aria-label="Timer options"]')->click('Manual mode')->wait(0.6)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->screenshot(fullPage: false, filename: 'mt-manual');
});
