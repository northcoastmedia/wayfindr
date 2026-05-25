<?php

test('rbac waypoints document roles permissions and sanity checks', function (): void {
    $path = base_path('../../docs/product/rbac-waypoints.md');

    expect($path)->toBeFile();

    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('Roles answer "what can this person do?"')
        ->toContain('Site access answers "where can this person do support work?"')
        ->toContain('`owner`')
        ->toContain('`admin`')
        ->toContain('`agent`')
        ->toContain('`manage_site_access`')
        ->toContain('`request_cobrowse`')
        ->toContain('Laravel policies and gates')
        ->toContain('owner/admin support queue access still follows site access')
        ->toContain('metadata-only')
        ->toContain('owner/admin authority plus site access')
        ->toContain('cross-account denial')
        ->toContain('wrong-site denial')
        ->toContain('role escalation denial')
        ->toContain('last-owner protection')
        ->toContain('bootstrap lockout prevention');
});
