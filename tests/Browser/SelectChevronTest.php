<?php

use App\Models\User;

it('renders select chevrons inside the control in both themes', function () {
    $this->seed();
    $this->actingAs(User::where('email', 'demo@example.com')->sole());

    $page = visit('/team')->on()->mobile()->wait(0.3);
    $page->assertScript("[...document.querySelectorAll('select')].every(s => getComputedStyle(s).appearance === 'none' && parseFloat(getComputedStyle(s).paddingRight) >= 30)", true)
        ->screenshot(fullPage: false, filename: 'sel-team-light');
    $page->click('button[aria-label="Account menu"]')->click('Dark')->click('button[aria-label="Account menu"]')->wait(0.3)
        ->screenshot(fullPage: false, filename: 'sel-team-dark');
    $page->resize(1280, 800)->click('Projects')->wait(0.4)->screenshot(fullPage: false, filename: 'sel-projects-dark');
    $page->click('Reports')->wait(0.6)->screenshot(fullPage: false, filename: 'sel-reports-dark');
});
