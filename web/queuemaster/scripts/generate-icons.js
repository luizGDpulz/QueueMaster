// Script para gerar ícones PNG a partir do logo SVG
// Execute com: node scripts/generate-icons.js

import sharp from 'sharp';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const svgPath = path.join(__dirname, '../src/assets/logo.svg');
const iconsDir = path.join(__dirname, '../public/icons');

// Tamanhos dos ícones
const sizes = [
  { size: 16, name: 'favicon-16x16.png' },
  { size: 32, name: 'favicon-32x32.png' },
  { size: 96, name: 'favicon-96x96.png' },
  { size: 128, name: 'favicon-128x128.png' },
  { size: 180, name: 'apple-icon-180x180.png' },
  { size: 192, name: 'android-chrome-192x192.png' },
  { size: 512, name: 'android-chrome-512x512.png' },
];

async function generateIcons() {
  // Ler o SVG
  const svgBuffer = fs.readFileSync(svgPath);
  
  console.log('Gerando ícones a partir do logo.svg...\n');
  
  for (const { size, name } of sizes) {
    const outputPath = path.join(iconsDir, name);
    
    await sharp(svgBuffer)
      .resize(size, size)
      .png()
      .toFile(outputPath);
    
    console.log(`✓ ${name} (${size}x${size})`);
  }
  
  console.log('\n✅ Todos os ícones foram gerados!');
}

generateIcons().catch(console.error);
