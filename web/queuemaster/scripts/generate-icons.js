// Script para gerar ícones PNG a partir dos logos SVG (light e dark)
// Execute com: node scripts/generate-icons.js

import sharp from 'sharp';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const lightSvgPath = path.join(__dirname, '../src/assets/logo_light.svg');
const darkSvgPath = path.join(__dirname, '../src/assets/logo_dark.svg');
const iconsDir = path.join(__dirname, '../public/icons');
const publicDir = path.join(__dirname, '../public');

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
  // Garantir que o diretório existe
  if (!fs.existsSync(iconsDir)) {
    fs.mkdirSync(iconsDir, { recursive: true });
  }

  const lightSvg = fs.readFileSync(lightSvgPath);
  const darkSvg = fs.readFileSync(darkSvgPath);

  console.log('Gerando ícones a partir dos logos...\n');

  // === PNGs baseados no tema claro (padrão para formatos que não suportam tema) ===
  for (const { size, name } of sizes) {
    const outputPath = path.join(iconsDir, name);
    await sharp(lightSvg)
      .resize(size, size)
      .png()
      .toFile(outputPath);
    console.log(`✓ ${name} (${size}x${size}) [light]`);
  }

  // === favicon.ico baseado no tema claro (ICO não suporta media query) ===
  const ico32 = await sharp(lightSvg)
    .resize(32, 32)
    .png()
    .toBuffer();

  // Salva como PNG renomeado (browsers modernos aceitam)
  fs.writeFileSync(path.join(publicDir, 'favicon.ico'), ico32);
  console.log('✓ favicon.ico (32x32) [light]');

  console.log('\n✅ Todos os ícones foram gerados!');
  console.log('ℹ️  favicon.svg usa @media (prefers-color-scheme) para alternar light/dark automaticamente.');
}

generateIcons().catch(console.error);
