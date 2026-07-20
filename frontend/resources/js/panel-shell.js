import { initMarketingMotion } from './marketing-motion';
import { bootFlashToasts } from './flash-toast';

export function bootPanelShell() {
    initMarketingMotion();
    bootFlashToasts();
}

document.addEventListener('livewire:navigated', () => {
    initMarketingMotion();
    bootFlashToasts();
});
