import { PanelCvStore, panelCvRadar, profileCvUpload, pollCvAnalysis } from './panel-cv-store';
import { panelJobMatches } from './panel-job-matches';
import { WeeklyTasksStore, dashboardWeeklyPlan } from './panel-weekly-tasks';
import { downloadPdfBlob, exportHarvardCvPdf, renderHarvardCvPdf } from './cv-pdf-export';
import {
    CV_OPTIONAL_SECTION_KEYS,
    createOptionalEntry,
    enableOptionalSectionForBothLocales,
    normalizeLocaleOptional,
    optionalEntryHasContent,
    removeOptionalSectionFromBothLocales,
} from './cv-optional-sections';
import { initCareersWizard } from './careers-wizard';
import { bootPanelShell } from './panel-shell';
import { careerTasks } from './panel-career-tasks';
import { skillPassport } from './panel-skill-passport';
import { careerAnalysisWatcher, careerPlanWatcher } from './panel-career-plan';
import { profileSocialLinks } from './panel-profile-links';
import { careerChat } from './panel-career-chat';
import { careerInterview } from './panel-career-interview';
import { careerApplications } from './panel-applications';
import { adminStudents } from './admin-students';
import { adminOrganizations } from './admin-organizations';

window.PanelCvStore = PanelCvStore;
window.panelCvRadar = panelCvRadar;
window.profileCvUpload = profileCvUpload;
window.pollCvAnalysis = pollCvAnalysis;
window.WeeklyTasksStore = WeeklyTasksStore;
window.dashboardWeeklyPlan = dashboardWeeklyPlan;
window.careerTasks = careerTasks;
window.skillPassport = skillPassport;
window.careerPlanWatcher = careerPlanWatcher;
window.careerAnalysisWatcher = careerAnalysisWatcher;
window.profileSocialLinks = profileSocialLinks;
window.careerChat = careerChat;
window.careerInterview = careerInterview;
window.careerApplications = careerApplications;
window.adminStudents = adminStudents;
window.adminOrganizations = adminOrganizations;
window.panelJobMatches = panelJobMatches;
window.exportHarvardCvPdf = exportHarvardCvPdf;
window.renderHarvardCvPdf = renderHarvardCvPdf;
window.downloadPdfBlob = downloadPdfBlob;
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
    document.addEventListener('DOMContentLoaded', bootPanelShell, { once: true });
} else {
    bootPanelShell();
}
