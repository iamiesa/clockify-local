<?php

use App\Models\Project;
use App\Models\Tag;

/**
 * Every picker / filter panel must stay fully inside the viewport on phones.
 */
it('keeps dropdown panels inside the screen on phones', function (int $width) {
    [, $workspace] = signIn();
    Project::factory()->for($workspace)->create(['name' => 'A fairly long project name for testing']);
    Tag::factory()->for($workspace)->create(['name' => 'Urgent']);

    $inside = "(() => { const open = Array.from(document.querySelectorAll('.dropdown-panel')).filter(e => e.getClientRects().length > 0); if (open.length === 0) { return 'no panel'; } return open.every(e => { const r = e.getBoundingClientRect(); return r.left >= 0 && r.right <= window.innerWidth; }); })()";

    $checks = [
        ['/tracker', 'Project'],
        ['/tracker', 'button[title="Tags"]'],
        ['/reports', 'Project'],
        ['/reports', 'This week'],
    ];

    foreach ($checks as $i => [$uri, $trigger]) {
        visit($uri)->resize($width, 740)->wait(0.3)->click($trigger)->wait(0.3)
            ->assertScript($inside, true)
            ->assertNoJavaScriptErrors()
            ->screenshot(fullPage: false, filename: "dd-{$width}-{$i}");
    }
})->with([320, 375, 414]);
