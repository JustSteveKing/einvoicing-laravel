<?php

declare(strict_types=1);

it('exits zero for a valid document', function (): void {
    fakeTransport([json(['data' => ['valid' => true, 'ruleset' => ['id' => 'peppol-bis-billing-3.0.21'], 'findings' => []]])]);

    $path = tempnam(sys_get_temp_dir(), 'ubl').'.xml';
    file_put_contents($path, '<Invoice/>');

    runArtisan('einvoicing:validate', ['file' => $path])
        ->expectsOutputToContain('Valid against peppol-bis-billing-3.0.21')
        ->assertSuccessful();

    unlink($path);
});

it('exits one for an invalid document, naming the rule', function (): void {
    fakeTransport([json(['data' => [
        'valid' => false,
        'findings' => [[
            'rule_id' => 'PEPPOL-EN16931-R003',
            'layer' => 'peppol',
            'severity' => 'error',
            'message' => 'A buyer reference or purchase order reference MUST be provided.',
            'fix' => 'Set BT-10 or BT-13.',
        ]],
    ]])]);

    $path = tempnam(sys_get_temp_dir(), 'ubl').'.xml';
    file_put_contents($path, '<Invoice/>');

    runArtisan('einvoicing:validate', ['file' => $path])
        ->expectsOutputToContain('PEPPOL-EN16931-R003')
        ->assertFailed();

    unlink($path);
});

it('says so when the file is not there', function (): void {
    runArtisan('einvoicing:validate', ['file' => '/no/such/invoice.xml'])
        ->expectsOutputToContain('No such file')
        ->assertFailed();
});

it('reports an unregistered participant as a failure', function (): void {
    fakeTransport([json(['data' => ['id' => '9932:gb999999999', 'registered' => false, 'capabilities' => []]])]);

    runArtisan('einvoicing:participant', ['id' => '9932:GB999999999'])
        ->expectsOutputToContain('not registered')
        ->assertFailed();
});

it('lists what a participant accepts', function (): void {
    fakeTransport([json(['data' => [
        'id' => '9932:gb123456789',
        'registered' => true,
        'capabilities' => [['name' => 'Peppol BIS Billing 3.0 Invoice', 'document_type_id' => 'Invoice-2::Invoice', 'process_id' => 'p']],
        'checked_at' => '2026-09-18T09:00:00.000Z',
    ]])]);

    runArtisan('einvoicing:participant', ['id' => '9932:GB123456789'])
        ->expectsOutputToContain('is registered')
        ->assertSuccessful();
});

it('shows usage', function (): void {
    fakeTransport([json(['data' => [
        'plan' => 'developer',
        'period_start' => '2026-09-01T00:00:00.000Z',
        'period_end' => '2026-10-01T00:00:00.000Z',
        'documents' => ['included' => 1000, 'used' => 250, 'overage' => 0],
        'lookups' => ['included' => 500, 'used' => 0, 'overage' => 0],
    ]])]);

    runArtisan('einvoicing:usage')
        ->expectsOutputToContain('developer')
        ->assertSuccessful();
});
