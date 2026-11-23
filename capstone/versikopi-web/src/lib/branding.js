import defaultLogo from '../assets/logo.png';

export function getStoreLogo(version) {
  const apiBase = (import.meta.env.VITE_API_URL || '/api').replace(/\/$/, '');
  return version ? `${apiBase}/branding/logo?v=${encodeURIComponent(version)}` : defaultLogo;
}
