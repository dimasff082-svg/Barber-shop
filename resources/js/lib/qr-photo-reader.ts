import { BrowserQRCodeReader } from '@zxing/browser';
import {
    BinaryBitmap,
    DecodeHintType,
    QRCodeReader,
    Result,
} from '@zxing/library';

/** Normal detection handles photos; pure decoding also supports clean QR exports. */
export function decodeQrPhotoBitmap(bitmap: BinaryBitmap): Result {
    const reader = new QRCodeReader();
    try {
        return reader.decode(bitmap);
    } catch {
        // Some valid, aligned QR matrices confuse finder-pattern detection.
        // This fallback still decodes and verifies the complete QR payload.
        return reader.decode(
            bitmap,
            new Map([[DecodeHintType.PURE_BARCODE, true]]),
        );
    }
}

export class PhotoQrReader extends BrowserQRCodeReader {
    override decodeBitmap(bitmap: BinaryBitmap): Result {
        return decodeQrPhotoBitmap(bitmap);
    }
}
