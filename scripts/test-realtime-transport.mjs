// Wire-level companion for test-realtime-transport.php. Credentials arrive only on stdin.
// ws is already installed by the existing Vite toolchain; its Origin option is needed here.
import WebSocket from 'ws';
import { createHmac } from 'node:crypto';
import { createInterface } from 'node:readline';

const input = createInterface({ input: process.stdin });
const send = (value) => process.stdout.write(JSON.stringify(value) + '\n');
const sockets = new Set();
let socket;
let configured = false;
let records = [];
const timer = setTimeout(() => fail('Wire smoke deadline exceeded.'), 70000);
function fail(message) {
    send({ kind: 'failure', message });
    cleanup();
    process.exit(1);
}
function cleanup() {
    clearTimeout(timer);
    for (const item of sockets) item.terminate();
    input.close();
}
function parseData(message) {
    return typeof message.data === 'string'
        ? JSON.parse(message.data)
        : message.data;
}

input.on('line', async (line) => {
    try {
        const command = JSON.parse(line);
        if (!configured) {
            if (
                !/^[a-f0-9]{32}$/.test(command.key) ||
                !/^[a-f0-9]{64}$/.test(command.secret) ||
                !Number.isInteger(command.port) ||
                command.port < 19010 ||
                command.port > 19110 ||
                !Number.isInteger(command.barber_id) ||
                command.barber_id < 1
            )
                throw new Error();
            configured = true;
            const url =
                'ws://127.0.0.1:' +
                command.port +
                '/app/' +
                command.key +
                '?protocol=7&client=smoke&version=1';
            const channels = [
                'queue.summary',
                'private-staff.admin',
                'private-staff.barber.' + command.barber_id,
            ];
            const subscribed = new Set();
            socket = new WebSocket(url, {
                origin: 'http://127.0.0.1',
                handshakeTimeout: 10000,
            });
            sockets.add(socket);
            socket.on('error', () =>
                fail('Primary WebSocket connection failed.'),
            );
            socket.on('message', (raw) => {
                try {
                    const message = JSON.parse(raw.toString());
                    const data = parseData(message);
                    if (message.event === 'pusher:connection_established') {
                        for (const channel of channels)
                            socket.send(
                                JSON.stringify({
                                    event: 'pusher:subscribe',
                                    data: {
                                        channel,
                                        ...(channel.startsWith('private-')
                                            ? {
                                                  auth:
                                                      command.key +
                                                      ':' +
                                                      createHmac(
                                                          'sha256',
                                                          command.secret,
                                                      )
                                                          .update(
                                                              data.socket_id +
                                                                  ':' +
                                                                  channel,
                                                          )
                                                          .digest('hex'),
                                              }
                                            : {}),
                                    },
                                }),
                            );
                    } else if (
                        message.event ===
                        'pusher_internal:subscription_succeeded'
                    ) {
                        subscribed.add(message.channel);
                        if (subscribed.size === channels.length)
                            send({ kind: 'ready' });
                    } else if (message.event === 'QueueCreated') {
                        if (
                            Object.keys(data).sort().join(',') !==
                            'barber_id,queue_date,type'
                        )
                            return fail('Unexpected broadcast payload fields.');
                        records.push({ channel: message.channel, data });
                        if (records.length === channels.length)
                            send({ kind: 'delivered', records });
                    } else if (message.event === 'pusher:error') {
                        if (data.code === 4301)
                            send({ kind: 'client_events_disabled' });
                        else
                            fail(
                                'Primary socket received an unexpected protocol error.',
                            );
                    } else if (message.event.startsWith('client-'))
                        fail('A forged client event was delivered.');
                } catch {
                    fail('Invalid WebSocket response.');
                }
            });
            const rejected = new WebSocket(url, {
                origin: 'https://disallowed.example',
                handshakeTimeout: 10000,
            });
            sockets.add(rejected);
            rejected.on('error', () =>
                fail('Origin rejection did not return a protocol result.'),
            );
            rejected.on('message', (raw) => {
                const message = JSON.parse(raw.toString());
                if (message.event === 'pusher:connection_established')
                    return fail('Disallowed origin was accepted.');
                if (
                    message.event === 'pusher:error' &&
                    parseData(message).code === 4009
                ) {
                    send({ kind: 'origin_rejected' });
                    rejected.close();
                }
            });
        } else if (command.action === 'quiet') {
            setTimeout(
                () =>
                    records.length === 0
                        ? send({ kind: 'quiet' })
                        : fail('A rolled back ticket was broadcast.'),
                500,
            );
        } else if (command.action === 'spoof') {
            socket.send(
                JSON.stringify({
                    event: 'client-forged',
                    channel: 'queue.summary',
                    data: {},
                }),
            );
        } else if (command.action === 'finish') {
            cleanup();
            process.exit(0);
        } else throw new Error();
    } catch {
        fail('Invalid transport smoke command.');
    }
});
