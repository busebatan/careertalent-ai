import { PanelCvStore, panelCvRadar, profileCvUpload } from './panel-cv-store';
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

window.PanelCvStore = PanelCvStore;
window.panelCvRadar = panelCvRadar;
window.profileCvUpload = profileCvUpload;
window.WeeklyTasksStore = WeeklyTasksStore;
window.dashboardWeeklyPlan = dashboardWeeklyPlan;
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
