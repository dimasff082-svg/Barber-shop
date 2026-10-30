import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';
import ts from 'typescript';

const ticketId = '01k00000000000000000000000';
const otherId = '01k00000000000000000000001';
const fixtureToken = 'a'.repeat(64);

function loadClientModule(path, overrides = {}) {
    const exports = {};
    const context = {
        exports,
        URL,
        URLSearchParams,
        window: { location: new URL('http://barberflow.test/') },
        document: { cookie: '' },
        ...overrides,
    };
    const source = readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');
    const compiled = ts.transpileModule(source, {
        compilerOptions: {
            target: ts.ScriptTarget.ES2022,
            module: ts.ModuleKind.CommonJS,
        },
    }).outputText;
    vm.runInNewContext(compiled, context, { filename: path });
    return exports;
}

test('recovery accepts the matching-origin fragment or identifier, not a query token', () => {
    const client = loadClientModule('resources/js/lib/queue-request.ts');
    const recovery = client.parseTicketRecovery(
        `http://barberflow.test/antrean/saya/${ticketId}#access=${fixtureToken}`,
    );
    assert.equal(recovery.id, ticketId);
    assert.equal(recovery.token, fixtureToken);
    assert.equal(
        client.parseTicketRecovery(ticketId.toUpperCase()).id,
        ticketId,
    );
    assert.equal(client.parseTicketRecovery(ticketId).token, null);
    for (const value of [
        `https://other.test/antrean/saya/${ticketId}#access=${fixtureToken}`,
        `http://barberflow.test/antrean/saya/${ticketId}?access=${fixtureToken}`,
        `http://barberflow.test/antrean/saya/${ticketId}?other=1#access=${fixtureToken}`,
        `http://barberflow.test/antrean/saya/${ticketId}/extra#access=${fixtureToken}`,
        `http://barberflow.test/antrean/saya/${ticketId}#access=short`,
        'javascript:alert(1)',
    ])
        assert.equal(client.parseTicketRecovery(value), null);
});

test('capture removes the secret before router initialization and binds memory to the ticket', () => {
    const location = new URL(
        `http://barberflow.test/antrean/saya/${ticketId}#access=${fixtureToken}&qr=studio-code`,
    );
    let rewritten;
    const client = loadClientModule('resources/js/lib/ticket-fragment.ts', {
        window: {
            location,
            history: {
                state: { route: 'unchanged' },
                replaceState(state, title, cleanUrl) {
                    rewritten = cleanUrl;
                    assert.equal(state.route, 'unchanged');
                    location.hash = '';
                },
            },
        },
    });
    client.captureTicketFragment();
    assert.equal(rewritten, `/antrean/saya/${ticketId}`);
    assert.equal(location.hash, '');
    assert.equal(client.getTicketFragment(ticketId).token, fixtureToken);
    assert.equal(client.getTicketFragment(ticketId).qr, 'studio-code');
    assert.equal(client.getTicketFragment(otherId), undefined);
    // Reading twice is safe during React StrictMode's initialization checks.
    assert.equal(client.getTicketFragment(ticketId).token, fixtureToken);
    client.clearTicketFragment(ticketId);
    assert.equal(client.getTicketFragment(ticketId), undefined);
});

test('requests use CSRF and an authorization header, never a secret URL or persistent cache', async () => {
    let calledUrl;
    let calledOptions;
    const client = loadClientModule('resources/js/lib/queue-request.ts', {
        document: { cookie: 'appearance=dark; XSRF-TOKEN=encrypted%2Bcsrf%3D' },
        fetch: async (url, options) => {
            calledUrl = url.toString();
            calledOptions = options;
            return { ok: true, json: async () => ({ ok: true }) };
        },
    });
    const result = await client.queueRequest(
        `/antrean/saya/${ticketId}/akses`,
        { method: 'POST', token: fixtureToken, data: { confirm: true } },
    );
    assert.equal(result.ok, true);
    assert.equal(
        calledUrl,
        `http://barberflow.test/antrean/saya/${ticketId}/akses`,
    );
    assert.equal(calledUrl.includes(fixtureToken), false);
    assert.equal(calledOptions.headers.Authorization, `Bearer ${fixtureToken}`);
    assert.equal(calledOptions.headers['X-XSRF-TOKEN'], 'encrypted+csrf=');
    assert.equal(calledOptions.headers.Accept, 'application/json');
    assert.equal(calledOptions.credentials, 'same-origin');
    assert.equal(calledOptions.cache, 'no-store');
    assert.equal(calledOptions.referrerPolicy, 'no-referrer');
    assert.equal(calledOptions.body, '{"confirm":true}');
    await assert.rejects(
        () =>
            client.queueRequest('https://other.test/access', {
                token: fixtureToken,
            }),
        (failure) => failure.status === 400,
    );
});

test('private errors do not display raw debug responses and throttling honors bounded retry time', async () => {
    let status = 404;
    let jsonReads = 0;
    const client = loadClientModule('resources/js/lib/queue-request.ts', {
        fetch: async () => ({
            ok: false,
            status,
            headers: { get: () => '9999' },
            json: async () => {
                jsonReads++;
                return { message: 'Sensitive debug data must not render' };
            },
        }),
    });
    await assert.rejects(
        () => client.queueRequest('/status'),
        (failure) =>
            failure.status === 404 && !failure.message.includes('Sensitive'),
    );
    assert.equal(jsonReads, 0);
    status = 429;
    await assert.rejects(
        () => client.queueRequest('/status'),
        (failure) => failure.status === 429 && failure.retryAfter === 300,
    );
});
