import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { test } from 'node:test';
import vm from 'node:vm';
import { PNG } from 'pngjs';
import ts from 'typescript';
import zxing from '@zxing/library';

const source = readFileSync(
    new URL('../resources/js/lib/location-qr.ts', import.meta.url),
    'utf8',
);
const exports = {};
vm.runInNewContext(
    ts.transpileModule(source, {
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            target: ts.ScriptTarget.ES2022,
        },
    }).outputText,
    { exports, URL },
);
const { parseLocationQr, createQrScanSession } = exports;
const origin = 'https://barberflow.test';

test('location QR accepts the exact current-origin portal and preserves its code', () => {
    for (const code of ['a'.repeat(64), 'Studio-QA_01']) {
        assert.equal(
            parseLocationQr(`${origin}/antrean/lokasi/${code}`, origin),
            code,
        );
        assert.equal(parseLocationQr(`/antrean/lokasi/${code}`, origin), code);
    }
    assert.equal(
        parseLocationQr(
            'http://127.0.0.1:8000/antrean/lokasi/qa',
            'http://127.0.0.1:8000',
        ),
        'qa',
    );
});

test('foreign origins, credentials, secret/query fragments and malformed portal paths are rejected', () => {
    for (const value of [
        'https://foreign.test/antrean/lokasi/qa',
        'https://barberflow.test.foreign.test/antrean/lokasi/qa',
        'https://user:password@barberflow.test/antrean/lokasi/qa',
        'https://@barberflow.test/antrean/lokasi/qa',
        '//barberflow.test/antrean/lokasi/qa',
        'https://barberflow.test/other/../antrean/lokasi/qa',
        'http://barberflow.test/antrean/lokasi/qa',
        'https://barberflow.test:444/antrean/lokasi/qa',
        'https://barberflow.test/antrean/lokasi/qa?access=secret',
        'https://barberflow.test/antrean/lokasi/qa#access=secret',
        'https://barberflow.test/antrean/lokasi/qa?',
        'https://barberflow.test/antrean/lokasi/qa#',
        'https://barberflow.test/antrean/lokasi/qa/extra',
        'https://barberflow.test/antrean/lokasi/qa/',
        'https://barberflow.test/antrean/lokasi/%71a',
        'https://barberflow.test/antrean/lokasi/' + 'a'.repeat(65),
        'https://barberflow.test/antrean/saya/qa',
        'https://barberflow.test/antrean/lokasi/qa\\extra',
        ' https://barberflow.test/antrean/lokasi/qa',
        'javascript:alert(1)',
        'data:text/plain,qa',
        'qa',
    ])
        assert.equal(parseLocationQr(value, origin), null, value);
});

test('closing a scan releases active resources and immediately stops a late camera result', () => {
    const session = createQrScanSession();
    const pending = session.scope();
    let stopped = 0;
    pending.retain(() => stopped++);
    session.reset();
    assert.equal(pending.current(), false);
    assert.equal(stopped, 1);
    pending.retain(() => stopped++); // getUserMedia resolved after close/unmount.
    assert.equal(stopped, 2);
    session.reset();
    assert.equal(stopped, 2);
    const retry = session.scope();
    assert.equal(retry.current(), true);
    retry.retain(() => stopped++);
    session.reset();
    assert.equal(stopped, 3);
});

test('one device cleanup failure does not leak another stream or decoder timer', () => {
    const session = createQrScanSession();
    const scope = session.scope();
    let stopped = false;
    scope.retain(() => {
        throw new Error('Device removed');
    });
    scope.retain(() => {
        stopped = true;
    });
    session.reset();
    assert.equal(stopped, true);
});

test('photo decoder reads the complete valid regression QR and rotated photos, but rejects blank images', () => {
    const photoExports = {};
    vm.runInNewContext(
        ts.transpileModule(
            readFileSync(
                new URL(
                    '../resources/js/lib/qr-photo-reader.ts',
                    import.meta.url,
                ),
                'utf8',
            ),
            {
                compilerOptions: {
                    module: ts.ModuleKind.CommonJS,
                    target: ts.ScriptTarget.ES2022,
                },
            },
        ).outputText,
        { exports: photoExports, require: createRequire(import.meta.url) },
    );
    const png = PNG.sync.read(
        readFileSync(
            new URL(
                '../tests/Fixtures/qr/location-portal.png',
                import.meta.url,
            ),
        ),
    );
    const gray = new Uint8ClampedArray(png.width * png.height);
    const rotated = new Uint8ClampedArray(gray.length);
    for (let y = 0; y < png.height; y++) {
        for (let x = 0; x < png.width; x++) {
            const index = y * png.width + x;
            const offset = index * 4;
            const luminance =
                (png.data[offset] +
                    2 * png.data[offset + 1] +
                    png.data[offset + 2]) /
                4;
            gray[index] = luminance;
            rotated[x * png.width + png.width - 1 - y] = luminance;
        }
    }
    const { BinaryBitmap, HybridBinarizer, RGBLuminanceSource } = zxing;
    const bitmap = (pixels) =>
        new BinaryBitmap(
            new HybridBinarizer(
                new RGBLuminanceSource(pixels, png.width, png.height),
            ),
        );
    const reader = new photoExports.PhotoQrReader();
    const expected =
        'http://127.0.0.1:18955/antrean/lokasi/3aaba4b6cd05cbf0ef5739a24b9ad8c230646ef8475ce85d60a0961e3008b2ac';
    for (const pixels of [gray, rotated])
        assert.equal(reader.decodeBitmap(bitmap(pixels)).getText(), expected);
    assert.throws(() =>
        reader.decodeBitmap(
            bitmap(new Uint8ClampedArray(gray.length).fill(255)),
        ),
    );
});
