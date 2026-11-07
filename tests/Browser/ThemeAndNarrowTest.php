<?php

use App\Models\User;

/**
 * Narrow-phone (320px) layout and dark theme checks, with screenshots for visual review.
 */
it('keeps the timer bar on two rows at 320px and supports dark mode', function () {
    $this->seed();
    $this->actingAs(User::where('email', 'demo@example.com')->sole());

    $page = visit('/tracker')->resize(320, 700)->wait(0.4)->assertNoJavaScriptErrors();
    $page->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
    // START must sit on the same row as the tag picker.
    $page->assertScript("Math.abs(document.querySelector('button[wire\\\\:click=\"startTimer\"]').getBoundingClientRect().top - document.querySelector('button[title=\"Tags\"]').getBoundingClientRect().top) < 12", true);
    $page->screenshot(fullPage: false, filename: 'n-tracker-light');

    $page->click('button[aria-label="Account menu"]')->click('Dark')->wait(0.3)
        ->assertScript("document.documentElement.classList.contains('dark')", true)
        ->assertScript("localStorage.getItem('theme')", 'dark')
        ->click('button[aria-label="Account menu"]')
        ->screenshot(fullPage: false, filename: 'n-tracker-dark');

    // Theme survives a wire:navigate page change, and charts rebuild without errors.
    $page->resize(1280, 900)->click('Dashboard')->wait(0.8)
        ->assertScript("document.documentElement.classList.contains('dark')", true)
        ->assertNoJavaScriptErrors()->screenshot(fullPage: false, filename: 'd-dashboard-dark');
    $page->click('Reports')->wait(0.8)->assertNoJavaScriptErrors()->screenshot(fullPage: false, filename: 'd-reports-dark');
    $page->click('Projects')->wait(0.5)->screenshot(fullPage: false, filename: 'd-projects-dark');
    $page->click('Time Tracker')->wait(0.5)->screenshot(fullPage: false, filename: 'd-tracker-dark');

    // Switching back live re-themes the charts.
    $page->click('Dashboard')->wait(0.6)->click('button[aria-label="Account menu"]')->click('Light')->wait(0.4)
        ->assertScript("document.documentElement.classList.contains('dark')", false)
        ->assertNoJavaScriptErrors();
});
