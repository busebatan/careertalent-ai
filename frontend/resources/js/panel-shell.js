import { Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { initMarketingMotion } from './marketing-motion';

window.Alpine = Alpine;

export function bootPanelShell() {
    initMarketingMotion();
}

document.addEventListener('livewire:navigated', () => {
    initMarketingMotion();
});
