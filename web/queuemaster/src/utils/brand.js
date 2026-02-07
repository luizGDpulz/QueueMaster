// =============================================
// QueueMaster - Brand Color System
// Gerencia a cor da marca globalmente via CSS custom properties.
// Persiste no localStorage e é aplicada antes de qualquer renderização.
// =============================================

/**
 * Cores padrão da marca por tema
 * Light → #1a1a1a (escuro), Dark → #ffffff (claro)
 */
const DEFAULT_BRAND_LIGHT = '#1a1a1a'
const DEFAULT_BRAND_DARK = '#ffffff'

/**
 * Presets de cores disponíveis no seletor da SettingsPage
 */
const BRAND_PRESETS = [
  { color: '#c21818', label: 'Vermelho' },
  { color: '#cb0c9f', label: 'Magenta' },
  { color: '#5e72e4', label: 'Índigo' },
  { color: '#3b82f6', label: 'Azul' },
  { color: '#17c1e8', label: 'Ciano' },
  { color: '#2dce89', label: 'Esmeralda' },
  { color: '#82d616', label: 'Verde Lima' },
  { color: '#f5365c', label: 'Rosa' },
  { color: '#fb6340', label: 'Laranja' },
  { color: '#f59e0b', label: 'Âmbar' },
  { color: '#8b5cf6', label: 'Violeta' },
  { color: '#344767', label: 'Azul Escuro' },
]

/**
 * Converte HEX para componentes RGB
 */
function hexToRgb(hex) {
  const clean = hex.replace('#', '')
  return {
    r: parseInt(clean.substring(0, 2), 16),
    g: parseInt(clean.substring(2, 4), 16),
    b: parseInt(clean.substring(4, 6), 16),
  }
}

/**
 * Calcula luminância relativa (0 = escuro, 1 = claro)
 * Fórmula ITU-R BT.601
 */
function luminance(r, g, b) {
  return (0.299 * r + 0.587 * g + 0.114 * b) / 255
}

/**
 * Valida se uma string é um HEX de cor válido (3 ou 6 dígitos)
 * @param {string} hex
 * @returns {boolean}
 */
function isValidHex(hex) {
  return /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/i.test(hex)
}

/**
 * Normaliza HEX de 3 dígitos para 6 dígitos
 * @param {string} hex
 * @returns {string}
 */
function normalizeHex(hex) {
  const clean = hex.replace('#', '')
  if (clean.length === 3) {
    return '#' + clean.split('').map(c => c + c).join('')
  }
  return '#' + clean.toLowerCase()
}

/**
 * Aplica a cor da marca em todas as CSS custom properties necessárias
 * @param {string} color - Cor HEX (ex: '#1a1a1a')
 */
function applyBrandColor(color) {
  const { r, g, b } = hexToRgb(color)
  const lum = luminance(r, g, b)
  const contrast = lum > 0.55 ? '#1a1a1a' : '#ffffff'

  const root = document.documentElement
  root.style.setProperty('--qm-brand', color)
  root.style.setProperty('--qm-brand-light', `rgba(${r}, ${g}, ${b}, 0.1)`)
  root.style.setProperty('--qm-brand-contrast', contrast)

  // Sincroniza com o Quasar (sobrescreve --q-primary em runtime)
  root.style.setProperty('--q-primary', color)
}

/**
 * Retorna a cor padrão adequada ao tema atual
 * @returns {string} HEX
 */
function getDefaultBrand() {
  const theme = document.documentElement.getAttribute('data-theme')
  return theme === 'dark' ? DEFAULT_BRAND_DARK : DEFAULT_BRAND_LIGHT
}

/**
 * Carrega a cor salva no localStorage e aplica.
 * Se nenhuma cor customizada estiver salva, usa o padrão por tema.
 * Retorna a cor atual.
 * @returns {string} HEX da cor da marca ativa
 */
function loadBrandColor() {
  const saved = localStorage.getItem('brandColor')
  const color = saved || getDefaultBrand()
  applyBrandColor(color)
  return color
}

/**
 * Salva e aplica uma nova cor da marca.
 * @param {string} color - Cor HEX
 */
function saveBrandColor(color) {
  localStorage.setItem('brandColor', color)
  applyBrandColor(color)
}

/**
 * Remove a cor customizada e volta ao padrão do tema atual.
 * @returns {string} A cor padrão aplicada
 */
function resetBrandColor() {
  localStorage.removeItem('brandColor')
  const color = getDefaultBrand()
  applyBrandColor(color)
  return color
}

export {
  DEFAULT_BRAND_LIGHT,
  DEFAULT_BRAND_DARK,
  BRAND_PRESETS,
  applyBrandColor,
  loadBrandColor,
  saveBrandColor,
  resetBrandColor,
  isValidHex,
  normalizeHex,
}
