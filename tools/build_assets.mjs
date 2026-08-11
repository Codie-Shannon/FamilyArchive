import { build } from 'vite';

await build();
await import('./copy_sharp_runtime.mjs');
