import { PanelCvStore, panelCvRadar, profileCvUpload, pollCvAnalysis } from './panel-cv-store';
import { JobMatchesStore, panelJobMatches } from './panel-job-matches';
import { WeeklyTasksStore, dashboardWeeklyPlan } from './panel-weekly-tasks';
import { exportHarvardCvPdf } from './cv-pdf-export';
import {
    CV_OPTIONAL_SECTION_KEYS,
    createOptionalEntry,
    enableOptionalSectionForBothLocales,
    normalizeLocaleOptional,
    optionalEntryHasContent,
    removeOptionalSectionFromBothLocales,
} from './cv-optional-sections';
import { initCareersWizard } from './careers-wizard';
import { initMarketingMotion } from './marketing-motion';
import { careerTasks } from './panel-career-tasks';
import { skillPassport } from './panel-skill-passport';

window.PanelCvStore = PanelCvStore;
window.panelCvRadar = panelCvRadar;
window.profileCvUpload = profileCvUpload;
window.pollCvAnalysis = pollCvAnalysis;
window.WeeklyTasksStore = WeeklyTasksStore;
window.dashboardWeeklyPlan = dashboardWeeklyPlan;
window.careerTasks = careerTasks;
window.skillPassport = skillPassport;
window.JobMatchesStore = JobMatchesStore;
window.panelJobMatches = panelJobMatches;
window.exportHarvardCvPdf = exportHarvardCvPdf;
window.initCareersWizard = initCareersWizard;
window.CvOptionalSections = {
    keys: CV_OPTIONAL_SECTION_KEYS,
    createOptionalEntry,
    enableOptionalSectionForBothLocales,
    normalizeLocaleOptional,
    optionalEntryHasContent,
    removeOptionalSectionFromBothLocales,
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMarketingMotion, { once: true });
} else {
    initMarketingMotion();
}
