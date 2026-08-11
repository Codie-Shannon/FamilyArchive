import fs from 'node:fs/promises';
import { createRequire } from 'node:module';

let sharp;
try {
    ({ default: sharp } = await import('sharp'));
} catch {
    const require = createRequire(import.meta.url);
    sharp = require('./node-runtime/node_modules/sharp');
}

sharp.cache(false);
sharp.concurrency(1);

const manifestPath = process.argv[2];
if (!manifestPath) {
    throw new Error('A split-render manifest path is required.');
}

const manifest = JSON.parse(await fs.readFile(manifestPath, 'utf8'));

function detectSkew(data, width, height, channels) {
    const step = Math.max(2, Math.ceil(height / 120));
    const cornerX = Math.min(2, width - 1);
    const cornerY = Math.min(2, height - 1);
    const cornerOffset = (cornerY * width + cornerX) * channels;
    const background = [data[cornerOffset], data[cornerOffset + 1], data[cornerOffset + 2]];
    const points = [];

    for (let y = 0; y < height; y += step) {
        for (let x = 0; x < Math.floor(width * 0.45); x += step) {
            const offset = (y * width + x) * channels;
            const distance = Math.abs(data[offset] - background[0])
                + Math.abs(data[offset + 1] - background[1])
                + Math.abs(data[offset + 2] - background[2]);
            if (distance >= 100) {
                points.push([y, x]);
                break;
            }
        }
    }
    if (points.length < 12) {
        return { degrees: 0, confidence: 0 };
    }
    const meanY = points.reduce((sum, point) => sum + point[0], 0) / points.length;
    const meanX = points.reduce((sum, point) => sum + point[1], 0) / points.length;
    let numerator = 0;
    let denominator = 0;
    for (const [pointY, pointX] of points) {
        numerator += (pointY - meanY) * (pointX - meanX);
        denominator += (pointY - meanY) ** 2;
    }
    const degrees = Math.atan(denominator > 0 ? numerator / denominator : 0) * 180 / Math.PI;

    return {
        degrees: Math.round(degrees * 100) / 100,
        confidence: Math.round(Math.min(0.9, points.length / 100) * 100) / 100,
    };
}

const results = [];
for (const region of manifest.regions) {
    const previewScale = Math.min(1, 1200 / manifest.source_width, 1200 / manifest.source_height);
    const previewSourceWidth = Math.max(1, Math.round(manifest.source_width * previewScale));
    const previewSourceHeight = Math.max(1, Math.round(manifest.source_height * previewScale));
    const previewLeft = Math.max(0, Math.floor(region.x * previewScale));
    const previewTop = Math.max(0, Math.floor(region.y * previewScale));
    const previewWidth = Math.max(1, Math.min(
        previewSourceWidth - previewLeft,
        Math.ceil(region.width * previewScale),
    ));
    const previewHeight = Math.max(1, Math.min(
        previewSourceHeight - previewTop,
        Math.ceil(region.height * previewScale),
    ));
    const preview = await sharp(manifest.input_path, {
        failOn: 'error',
        limitInputPixels: manifest.maximum_source_pixels,
        sequentialRead: false,
    })
        .resize({ width: previewSourceWidth, height: previewSourceHeight, fit: 'fill' })
        .extract({ left: previewLeft, top: previewTop, width: previewWidth, height: previewHeight })
        .removeAlpha()
        .raw()
        .toBuffer({ resolveWithObject: true });
    const skew = detectSkew(preview.data, preview.info.width, preview.info.height, preview.info.channels);
    const deskewDegrees = skew.confidence >= manifest.minimum_deskew_confidence
        && Math.abs(skew.degrees) >= manifest.minimum_deskew_degrees
        && Math.abs(skew.degrees) <= manifest.maximum_deskew_degrees
        ? -skew.degrees
        : 0;
    const gdRotation = -region.manual_rotation_degrees + deskewDegrees;
    const clockwiseRotation = -gdRotation;
    const radians = Math.abs(gdRotation) * Math.PI / 180;
    const rotatedWidth = Math.ceil(
        Math.abs(region.working_width * Math.cos(radians))
        + Math.abs(region.working_height * Math.sin(radians)),
    );
    const rotatedHeight = Math.ceil(
        Math.abs(region.working_width * Math.sin(radians))
        + Math.abs(region.working_height * Math.cos(radians)),
    );
    const targetWidth = Math.min(rotatedWidth, Math.max(1, Math.ceil(
        Math.abs(region.width * Math.cos(radians))
        + Math.abs(region.height * Math.sin(radians)),
    ) + manifest.final_safety_pixels * 2));
    const targetHeight = Math.min(rotatedHeight, Math.max(1, Math.ceil(
        Math.abs(region.width * Math.sin(radians))
        + Math.abs(region.height * Math.cos(radians)),
    ) + manifest.final_safety_pixels * 2));
    const finalX = Math.max(0, Math.floor((rotatedWidth - targetWidth) / 2));
    const finalY = Math.max(0, Math.floor((rotatedHeight - targetHeight) / 2));
    let pipeline = sharp(manifest.input_path, {
        failOn: 'error',
        limitInputPixels: manifest.maximum_source_pixels,
        sequentialRead: false,
    })
        .extract({
            left: region.copy_left,
            top: region.copy_top,
            width: region.copy_width,
            height: region.copy_height,
        })
        .extend({
            top: region.destination_y,
            bottom: region.working_height - region.copy_height - region.destination_y,
            left: region.destination_x,
            right: region.working_width - region.copy_width - region.destination_x,
            background: { r: 0, g: 0, b: 0, alpha: 0 },
        });
    if (Math.abs(clockwiseRotation) >= 0.01) {
        pipeline = pipeline.rotate(clockwiseRotation, { background: { r: 0, g: 0, b: 0, alpha: 0 } });
    }
    const output = await pipeline
        .extract({ left: finalX, top: finalY, width: targetWidth, height: targetHeight })
        .webp({ quality: manifest.webp_quality, effort: 4 })
        .toFile(region.output_path);
    await sharp(region.output_path, { failOn: 'error', limitInputPixels: manifest.maximum_source_pixels })
        .resize({ width: 640, height: 640, fit: 'inside', withoutEnlargement: true })
        .png()
        .toFile(region.quality_path);
    results.push({
        index: region.index,
        width: output.width,
        height: output.height,
        skew,
        deskew_degrees: deskewDegrees,
        gd_rotation: gdRotation,
        final_x: finalX,
        final_y: finalY,
    });
}

process.stdout.write(JSON.stringify({ results }));
