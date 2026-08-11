import fs from 'node:fs/promises';
import { createReadStream } from 'node:fs';
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

function sharpFromRawFile(region) {
    const pipeline = sharp({
        raw: { width: region.raw_width, height: region.raw_height, channels: 3 },
        failOn: 'error',
        sequentialRead: true,
    });
    const input = createReadStream(region.raw_path);
    input.on('error', error => pipeline.destroy(error));
    input.pipe(pipeline);

    return pipeline;
}

async function readRow(file, buffer, row, rowBytes) {
    const { bytesRead } = await file.read(buffer, 0, rowBytes, row * rowBytes);
    if (bytesRead !== rowBytes) {
        throw new Error('The bounded raw resampler could not read a complete source row.');
    }
}

async function boundedRawRegion(region, maximumPixels) {
    const sourceWidth = region.raw_width;
    const sourceHeight = region.raw_height;
    const original = {
        ...region,
        original_width: region.width,
        original_height: region.height,
        original_safety_pixels: manifest.final_safety_pixels,
        working_safety_pixels: manifest.final_safety_pixels,
        working_scale_x: 1,
        working_scale_y: 1,
    };
    if (sourceWidth * sourceHeight <= maximumPixels) {
        return original;
    }

    const scale = Math.sqrt(maximumPixels / (sourceWidth * sourceHeight));
    const targetWidth = Math.max(1, Math.floor(sourceWidth * scale));
    const targetHeight = Math.max(1, Math.floor(sourceHeight * scale));
    const targetPath = `${region.raw_path}.bounded.rgb`;
    const source = await fs.open(region.raw_path, 'r');
    const target = await fs.open(targetPath, 'w');
    const sourceRowBytes = sourceWidth * 3;
    const targetRowBytes = targetWidth * 3;
    const rowA = Buffer.allocUnsafe(sourceRowBytes);
    const rowB = Buffer.allocUnsafe(sourceRowBytes);
    const outputRow = Buffer.allocUnsafe(targetRowBytes);
    const x0 = new Int32Array(targetWidth);
    const x1 = new Int32Array(targetWidth);
    const xWeight = new Float64Array(targetWidth);

    for (let x = 0; x < targetWidth; x += 1) {
        const sourceX = Math.max(0, Math.min(sourceWidth - 1, ((x + 0.5) * sourceWidth / targetWidth) - 0.5));
        x0[x] = Math.floor(sourceX);
        x1[x] = Math.min(sourceWidth - 1, x0[x] + 1);
        xWeight[x] = sourceX - x0[x];
    }

    try {
        for (let y = 0; y < targetHeight; y += 1) {
            const sourceY = Math.max(0, Math.min(sourceHeight - 1, ((y + 0.5) * sourceHeight / targetHeight) - 0.5));
            const y0 = Math.floor(sourceY);
            const y1 = Math.min(sourceHeight - 1, y0 + 1);
            const yWeight = sourceY - y0;
            await readRow(source, rowA, y0, sourceRowBytes);
            if (y1 === y0) {
                rowA.copy(rowB);
            } else {
                await readRow(source, rowB, y1, sourceRowBytes);
            }
            for (let x = 0; x < targetWidth; x += 1) {
                const left = x0[x] * 3;
                const right = x1[x] * 3;
                const destination = x * 3;
                const horizontalWeight = xWeight[x];
                for (let channel = 0; channel < 3; channel += 1) {
                    const top = rowA[left + channel] * (1 - horizontalWeight) + rowA[right + channel] * horizontalWeight;
                    const bottom = rowB[left + channel] * (1 - horizontalWeight) + rowB[right + channel] * horizontalWeight;
                    outputRow[destination + channel] = Math.round(top * (1 - yWeight) + bottom * yWeight);
                }
            }
            const { bytesWritten } = await target.write(outputRow, 0, targetRowBytes, y * targetRowBytes);
            if (bytesWritten !== targetRowBytes) {
                throw new Error('The bounded raw resampler could not write a complete output row.');
            }
        }
    } finally {
        await source.close();
        await target.close();
    }

    const scaleX = targetWidth / sourceWidth;
    const scaleY = targetHeight / sourceHeight;
    const regionX = Math.max(0, Math.min(targetWidth - 1, Math.round(region.raw_region_x * scaleX)));
    const regionY = Math.max(0, Math.min(targetHeight - 1, Math.round(region.raw_region_y * scaleY)));

    return {
        ...original,
        raw_path: targetPath,
        raw_width: targetWidth,
        raw_height: targetHeight,
        raw_region_x: regionX,
        raw_region_y: regionY,
        raw_destination_x: Math.max(0, Math.round(region.raw_destination_x * scaleX)),
        raw_destination_y: Math.max(0, Math.round(region.raw_destination_y * scaleY)),
        width: Math.max(1, Math.min(targetWidth - regionX, Math.round(region.width * scaleX))),
        height: Math.max(1, Math.min(targetHeight - regionY, Math.round(region.height * scaleY))),
        working_safety_pixels: Math.max(0, Math.round(manifest.final_safety_pixels * Math.min(scaleX, scaleY))),
        working_scale_x: scaleX,
        working_scale_y: scaleY,
    };
}

function rotatedDimensions(width, height, degrees) {
    const radians = Math.abs(degrees) * Math.PI / 180;
    const cosine = Math.abs(Math.cos(radians)) < 1e-12 ? 0 : Math.abs(Math.cos(radians));
    const sine = Math.abs(Math.sin(radians)) < 1e-12 ? 0 : Math.abs(Math.sin(radians));

    return {
        width: Math.ceil(width * cosine + height * sine),
        height: Math.ceil(width * sine + height * cosine),
    };
}

const results = [];
for (const manifestRegion of manifest.regions) {
    const region = await boundedRawRegion(manifestRegion, manifest.maximum_working_pixels);
    const preview = await sharpFromRawFile(region)
        .extract({ left: region.raw_region_x, top: region.raw_region_y, width: region.width, height: region.height })
        .resize({ width: 1200, height: 1200, fit: 'inside', withoutEnlargement: true })
        .removeAlpha()
        .raw()
        .toBuffer({ resolveWithObject: true });
    const skew = detectSkew(preview.data, preview.info.width, preview.info.height, preview.info.channels);
    const deskewDegrees = manifest.apply_deskew
        && skew.confidence >= manifest.minimum_deskew_confidence
        && Math.abs(skew.degrees) >= manifest.minimum_deskew_degrees
        && Math.abs(skew.degrees) <= manifest.maximum_deskew_degrees
        ? -skew.degrees
        : 0;
    const gdRotation = -region.manual_rotation_degrees + deskewDegrees;
    const clockwiseRotation = -gdRotation;
    const normalizedRotation = ((Math.round(clockwiseRotation) % 360) + 360) % 360;
    const safety = region.working_safety_pixels;
    const copyWidth = region.raw_width;
    const copyHeight = region.raw_height;
    const destinationX = region.raw_destination_x;
    const destinationY = region.raw_destination_y;
    const unrotatedWidth = region.width + safety * 2;
    const unrotatedHeight = region.height + safety * 2;
    const target = rotatedDimensions(unrotatedWidth, unrotatedHeight, normalizedRotation);
    const targetWidth = target.width;
    const targetHeight = target.height;
    const outputScale = Math.min(1, Math.sqrt(manifest.maximum_output_pixels / (targetWidth * targetHeight)));
    const scaledCanvasWidth = Math.max(1, Math.floor(unrotatedWidth * outputScale));
    const scaledCanvasHeight = Math.max(1, Math.floor(unrotatedHeight * outputScale));
    const scaledCopyWidth = Math.max(1, Math.floor(copyWidth * outputScale));
    const scaledCopyHeight = Math.max(1, Math.floor(copyHeight * outputScale));
    const scaledDestinationX = Math.max(0, Math.floor(destinationX * outputScale));
    const scaledDestinationY = Math.max(0, Math.floor(destinationY * outputScale));
    let pipeline = sharpFromRawFile(region);
    if (outputScale < 1) {
        pipeline = pipeline.resize({ width: scaledCopyWidth, height: scaledCopyHeight, fit: 'fill' });
    }
    pipeline = pipeline.extend({
        top: scaledDestinationY,
        bottom: Math.max(0, scaledCanvasHeight - scaledCopyHeight - scaledDestinationY),
        left: scaledDestinationX,
        right: Math.max(0, scaledCanvasWidth - scaledCopyWidth - scaledDestinationX),
        background: { r: 0, g: 0, b: 0, alpha: 0 },
    });
    if (normalizedRotation !== 0) {
        pipeline = pipeline.rotate(normalizedRotation, { background: { r: 0, g: 0, b: 0, alpha: 0 } });
    }
    const output = await pipeline
        .webp({ quality: manifest.webp_quality, effort: 4 })
        .toFile(region.output_path);
    await sharp(region.output_path, { failOn: 'error', limitInputPixels: manifest.maximum_source_pixels })
        .resize({ width: 640, height: 640, fit: 'inside', withoutEnlargement: true })
        .png()
        .toFile(region.quality_path);
    const originalUnrotatedWidth = region.original_width + region.original_safety_pixels * 2;
    const originalUnrotatedHeight = region.original_height + region.original_safety_pixels * 2;
    const originalTarget = rotatedDimensions(originalUnrotatedWidth, originalUnrotatedHeight, normalizedRotation);
    const originalTargetWidth = originalTarget.width;
    const originalTargetHeight = originalTarget.height;
    results.push({
        index: region.index,
        width: output.width,
        height: output.height,
        skew,
        deskew_degrees: deskewDegrees,
        gd_rotation: gdRotation,
        final_x: 0,
        final_y: 0,
        unscaled_width: originalTargetWidth,
        unscaled_height: originalTargetHeight,
        output_scale: Math.sqrt((output.width * output.height) / (originalTargetWidth * originalTargetHeight)),
    });
}

process.stdout.write(JSON.stringify({ results }));
