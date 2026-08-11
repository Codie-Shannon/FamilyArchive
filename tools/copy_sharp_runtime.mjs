import fs from 'node:fs/promises';
import path from 'node:path';

const root = process.cwd();
const sourceModules = path.join(root, 'node_modules');
const targetModules = path.join(root, 'tools', 'node-runtime', 'node_modules');
const packages = [
    'sharp',
    'detect-libc',
    'semver',
    '@img/colour',
    `@img/sharp-${process.platform}-${process.arch}`,
    `@img/sharp-libvips-${process.platform}-${process.arch}`,
];

await fs.rm(path.dirname(targetModules), { recursive: true, force: true });
for (const packageName of packages) {
    const source = path.join(sourceModules, ...packageName.split('/'));
    const target = path.join(targetModules, ...packageName.split('/'));
    try {
        await fs.access(source);
    } catch {
        continue;
    }
    await fs.mkdir(path.dirname(target), { recursive: true });
    await fs.cp(source, target, { recursive: true, force: true });
}
