/**
 * Extract Magallanes barangays from HDX phl_admin_boundaries shapefile/geodatabase zip.
 * Requires: npm install shapefile adm-zip (run from scripts/ once)
 *
 * Usage: node scripts/extract-magallanes-hdx-adm4.mjs [path-to-zip]
 */
import fs from 'node:fs';
import path from 'node:path';
import { execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import AdmZip from 'adm-zip';
import * as shapefile from 'shapefile';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const defaultZip = path.join(root, 'assets/geo/_hdx_phl_admin_boundaries.shp.zip');
const altZip = path.join(root, 'assets/geo/_hdx_phl_admin_boundaries.gdb.zip');
const outPath = path.join(root, 'assets/geo/magallanes-barangays-namria.geojson');

const expected = ['Buhang', 'Caloc-an', 'Guiasan', 'Marcos', 'Poblacion', 'Santo Niño', 'Santo Rosario', 'Taod-oy'];

const zipPath = process.argv[2] || (fs.existsSync(defaultZip) ? defaultZip : altZip);
if (!fs.existsSync(zipPath)) {
  console.error('Zip not found:', zipPath);
  process.exit(1);
}

const tmpDir = path.join(root, 'assets/geo/_hdx_extract');
fs.mkdirSync(tmpDir, { recursive: true });

console.log('Extracting', zipPath);
extractZip(zipPath, tmpDir);

function extractZip(source, dest) {
  try {
    const zip = new AdmZip(source);
    zip.extractAllTo(dest, true);
  } catch (err) {
    console.warn('adm-zip failed:', err.message);
    console.log('Trying PowerShell Expand-Archive…');
    const ps = `Expand-Archive -LiteralPath '${source.replace(/'/g, "''")}' -DestinationPath '${dest.replace(/'/g, "''")}' -Force`;
    execSync(`powershell -NoProfile -Command "${ps}"`, { stdio: 'inherit' });
  }
}

function findShp(dir) {
  const hits = [];
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, ent.name);
    if (ent.isDirectory()) {
      hits.push(...findShp(p));
    } else if (/\.shp$/i.test(ent.name) && /admin4|adm4|admbnd.*adm4|barangay/i.test(ent.name)) {
      hits.push(p);
    }
  }
  return hits;
}

const shps = findShp(tmpDir);
if (shps.length === 0) {
  console.error('No ADM4 shapefile in zip. Contents sample:');
  console.error(fs.readdirSync(tmpDir).slice(0, 20).join(', '));
  process.exit(1);
}

const shpPath = shps.sort((a, b) => fs.statSync(b).size - fs.statSync(a).size)[0];
console.log('Using', shpPath);

/** @type {Map<string, object>} */
const byName = new Map();

const source = await shapefile.open(shpPath);
while (true) {
  const result = await source.read();
  if (result.done) break;
  const feature = result.value;
  const props = feature.properties || {};
  const adm3 = String(props.adm3_name ?? props.ADM3_EN ?? props.adm3_en ?? '');
  const adm2 = String(props.adm2_name ?? props.ADM2_EN ?? props.adm2_en ?? '');
  const adm3Pcode = String(props.adm3_pcode ?? props.ADM3_PCODE ?? '');
  const pcode = String(props.adm4_pcode ?? props.ADM4_PCODE ?? props.PSGC ?? '');
  const match =
    adm3Pcode === 'PH160208' ||
    pcode.startsWith('PH160208') ||
    pcode.startsWith('160208') ||
    (adm3.toLowerCase() === 'magallanes' && adm2.toLowerCase().includes('agusan del norte'));
  if (!match) continue;

  let name = normalizeName(String(props.adm4_name ?? props.ADM4_EN ?? props.adm4_en ?? props.NAME ?? '').trim());
  name = name.replace(/\s*\(pob\.?\)\s*$/i, '').trim();
  if (name.toLowerCase() === 'magallanes') {
    name = 'Poblacion';
  }
  if (!name) continue;

  const existing = byName.get(name);
  const verts = vertexCount(feature.geometry);
  if (!existing || verts > vertexCount(existing.geometry)) {
    byName.set(name, {
      type: 'Feature',
      properties: {
        name,
        name,
        ADM4_EN: name,
        adm4_name: name,
        ADM3_EN: 'Magallanes',
        adm3_name: 'Magallanes',
        ADM2_EN: 'Agusan del Norte',
        adm2_name: 'Agusan del Norte',
        ADM4_PCODE: pcode,
        adm4_pcode: pcode,
        source: 'HDX COD-AB NAMRIA/PSA ADM4 (ITOS)',
      },
      geometry: feature.geometry,
    });
  }
}

const features = [];
for (const name of expected) {
  if (!byName.has(name)) {
    console.error('Missing barangay:', name, 'found:', [...byName.keys()].join(', '));
    process.exit(1);
  }
  features.push(byName.get(name));
}

const total = features.reduce((t, f) => t + vertexCount(f.geometry), 0);
console.log('Features:', features.length, 'vertices:', total);

features.sort((a, b) => a.properties.name.localeCompare(b.properties.name));
fs.writeFileSync(outPath, JSON.stringify({ type: 'FeatureCollection', features }, null, 2) + '\n');
console.log('Wrote', outPath);

function normalizeName(name) {
  let n = name.replace(/\uFFFD/g, 'ñ').replace(/Ã±/g, 'ñ').replace(/Ã'/g, 'ñ');
  const map = {
    'Santo Nino': 'Santo Niño',
    'Santo Niño': 'Santo Niño',
  };
  if (map[n]) {
    return map[n];
  }
  if (/^santo ni/i.test(n)) {
    return 'Santo Niño';
  }
  return n;
}

function vertexCount(geometry) {
  if (!geometry) return 0;
  let c = 0;
  const { type, coordinates: coords } = geometry;
  if (type === 'Polygon') {
    for (const ring of coords) c += ring.length;
  } else if (type === 'MultiPolygon') {
    for (const poly of coords) for (const ring of poly) c += ring.length;
  }
  return c;
}
