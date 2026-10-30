import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';
import ts from 'typescript';

function loadModule(path, overrides = {}) {
    const exports = {};
    const source = readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');
    const compiled = ts.transpileModule(source, {
        compilerOptions: {
            target: ts.ScriptTarget.ES2022,
            module: ts.ModuleKind.CommonJS,
        },
    }).outputText;
    vm.runInNewContext(compiled, { exports, ...overrides }, { filename: path });
    return exports;
}

function fixture(refresh = async () => 'updated') {
    let now = 0;
    let sequence = 0;
    let available = true;
    let blocked = false;
    let requests = 0;
    let paused = false;
    const timers = new Map();
    const { createLiveRefresh } = loadModule(
        'resources/js/lib/live-refresh.ts',
    );
    const controller = createLiveRefresh({
        available: () => available,
        blocked: () => blocked,
        refresh: () => {
            requests++;
            return refresh();
        },
        paused: (value) => {
            paused = value;
        },
        schedule: (callback, delay) => {
            const id = ++sequence;
            timers.set(id, { at: now + delay, callback });
            return id;
        },
        cancel: (id) => timers.delete(id),
    });
    async function tick(ms) {
        const target = now + ms;
        for (;;) {
            const next = [...timers.entries()].sort(
                (a, b) => a[1].at - b[1].at,
            )[0];
            if (!next || next[1].at > target) break;
            now = next[1].at;
            timers.delete(next[0]);
            next[1].callback();
            await Promise.resolve();
            await Promise.resolve();
        }
        now = target;
    }
    return {
        controller,
        tick,
        requests: () => requests,
        timers,
        available: (value) => {
            available = value;
        },
        blocked: (value) => {
            blocked = value;
        },
        paused: () => paused,
    };
}

test('bursts coalesce and an event during refetch schedules one follow-up', async () => {
    let complete;
    const env = fixture(
        () =>
            new Promise((resolve) => {
                complete = resolve;
            }),
    );
    env.controller.start();
    for (let index = 0; index < 20; index++) env.controller.invalidate();
    await env.tick(249);
    assert.equal(env.requests(), 0);
    await env.tick(1);
    assert.equal(env.requests(), 1);
    for (let index = 0; index < 20; index++) env.controller.invalidate();
    await env.tick(5_000);
    assert.equal(env.requests(), 1);
    complete('updated');
    await new Promise(setImmediate);
    await env.tick(250);
    assert.equal(env.requests(), 2);
    env.controller.dispose();
    complete('updated');
    await Promise.resolve();
    assert.equal(env.timers.size, 0);
});

test('hidden/offline work stops, reconnect refetches, editing pauses without dropping invalidation', async () => {
    const env = fixture();
    env.controller.start();
    env.blocked(true);
    env.controller.invalidate();
    await env.tick(5_000);
    assert.equal(env.requests(), 0);
    assert.equal(env.paused(), true);
    env.available(false);
    env.controller.suspend();
    await env.tick(60_000);
    assert.equal(env.requests(), 0);
    assert.equal(env.timers.size, 0);
    env.available(true);
    env.blocked(false);
    env.controller.resume();
    await env.tick(0);
    assert.equal(env.requests(), 1);
    assert.equal(env.paused(), false);
    env.controller.dispose();
});

test('fallback backs off on failures, resets after success and reconciles even with a healthy socket', async () => {
    let result = 'retry';
    const env = fixture(async () => result);
    env.controller.start();
    await env.tick(15_000);
    assert.equal(env.requests(), 1);
    await env.tick(29_999);
    assert.equal(env.requests(), 1);
    await env.tick(1);
    assert.equal(env.requests(), 2);
    result = 'updated';
    await env.tick(60_000);
    assert.equal(env.requests(), 3);
    await env.tick(15_000);
    assert.equal(env.requests(), 4);
    env.controller.transport(true);
    await env.tick(250);
    assert.equal(env.requests(), 5);
    await env.tick(59_999);
    assert.equal(env.requests(), 5);
    await env.tick(1);
    assert.equal(env.requests(), 6);
    env.controller.transport(false);
    await env.tick(15_000);
    assert.equal(env.requests(), 7);
    env.controller.dispose();
});

test('denied access stops refreshes and disposal cancels pending timers', async () => {
    const env = fixture(async () => 'stopped');
    env.controller.start();
    await env.tick(15_000);
    env.controller.invalidate();
    env.controller.resume();
    await env.tick(120_000);
    assert.equal(env.requests(), 1);
    assert.equal(env.timers.size, 0);
    const other = fixture();
    other.controller.start();
    other.controller.dispose();
    await other.tick(120_000);
    assert.equal(other.requests(), 0);
});

test('private channel auth uses same-origin session/CSRF, no secret URL or browser persistence', async () => {
    let request;
    const client = loadModule('resources/js/lib/broadcast-auth.ts', {
        document: { cookie: 'appearance=dark; XSRF-TOKEN=encrypted%2Bcsrf%3D' },
        fetch: async (url, options) => {
            request = { url, options };
            return {
                ok: true,
                json: async () => ({
                    auth: 'signed-auth',
                    unexpected: 'not-returned',
                }),
            };
        },
    });
    const controller = new AbortController();
    const result = await client.authorizeBroadcast(
        'private-staff.barber.12',
        '123.456',
        controller.signal,
    );
    assert.equal(result.auth, 'signed-auth');
    assert.deepEqual(Object.keys(result), ['auth']);
    assert.equal(request.url, '/broadcasting/auth');
    assert.equal(request.options.credentials, 'same-origin');
    assert.equal(request.options.cache, 'no-store');
    assert.equal(request.options.referrerPolicy, 'no-referrer');
    assert.equal(request.options.headers['X-XSRF-TOKEN'], 'encrypted+csrf=');
    assert.equal(
        request.options.body,
        '{"socket_id":"123.456","channel_name":"private-staff.barber.12"}',
    );
    assert.equal(request.options.signal, controller.signal);
    for (const channel of [
        'staff.admin',
        'private-guest.ticket',
        'private-staff.barber.0',
        'private-staff.barber.1/extra',
    ]) {
        await assert.rejects(
            () =>
                client.authorizeBroadcast(
                    channel,
                    '123.456',
                    controller.signal,
                ),
            (error) => error.status === 403,
        );
    }
});

test('auth failure does not read or expose debug HTML', async () => {
    const client = loadModule('resources/js/lib/broadcast-auth.ts', {
        document: { cookie: '' },
        fetch: async () => ({
            ok: false,
            status: 403,
            json: () => {
                throw new Error('must not inspect debug response');
            },
        }),
    });
    await assert.rejects(
        () =>
            client.authorizeBroadcast(
                'private-staff.admin',
                '123.456',
                new AbortController().signal,
            ),
        (error) => error.status === 403 && !error.message.includes('debug'),
    );
});
