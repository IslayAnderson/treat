// Renders assets/img/paper.svg (the editable source) onto the "paper" colour from
// assets/scss/abstracts/_tokens.scss and saves a 2× WebP tile for the page background.
//
// Why a raster: SVG noise filters used as CSS backgrounds render inconsistently
// (Safari and Firefox clip or skip them). Why opaque: grain compresses ~8× better
// without an alpha channel. Re-run after changing the paper colour or the SVG.
//
// Usage: npm run texture
import { readFileSync, writeFileSync, statSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { execFileSync } from 'node:child_process';
import { Resvg } from '@resvg/resvg-js';

const SRC = 'assets/img/paper.svg';
const OUT = 'assets/img/paper.webp';
const TOKENS = 'assets/scss/abstracts/_tokens.scss';
const SCALE = 2;      // rendered at 2× and shown at 1× ($paper-texture-size) for sharp grain
const QUALITY = '75';

const paper = readFileSync(TOKENS, 'utf8').match(/"paper":\s*(#[0-9a-f]{3,8})/i)?.[1];
if (!paper) throw new Error(`No "paper" colour found in ${TOKENS}`);

const rendered = new Resvg(readFileSync(SRC), {
  fitTo: { mode: 'zoom', value: SCALE },
  background: paper,
}).render();

const tmp = mkdtempSync(join(tmpdir(), 'treat-texture-'));
const png = join(tmp, 'paper.png');
writeFileSync(png, rendered.asPng());
execFileSync('cwebp', ['-quiet', '-q', QUALITY, '-m', '6', png, '-o', OUT]);
rmSync(tmp, { recursive: true });

console.log(`${OUT}: ${rendered.width}×${rendered.height} on ${paper}, ${(statSync(OUT).size / 1024).toFixed(1)} KB`);
