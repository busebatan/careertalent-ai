import { initMarketingMotion } from './marketing-motion';

export function bootPanelShell() {
    initMarketingMotion();
}

document.addEventListener('livewire:navigated', () => {
    initMarketingMotion();
});
