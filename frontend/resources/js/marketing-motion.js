import {
    ArrowLeft,
    ArrowRight,
    Bell,
    BookOpen,
    BookUser,
    Briefcase,
    BriefcaseBusiness,
    Building2,
    ChartNoAxesColumnIncreasing,
    Check,
    CircleCheckBig,
    ChevronDown,
    ClipboardClock,
    ClipboardList,
    ClipboardPaste,
    createIcons,
    FileText,
    Files,
    GraduationCap,
    Gauge,
    History,
    Languages,
    LayoutDashboard,
    Lightbulb,
    ListChecks,
    ListTodo,
    LoaderCircle,
    LogOut,
    Map,
    Menu,
    MessageCircle,
    MessagesSquare,
    Moon,
    Play,
    Plus,
    Radar,
    ScanSearch,
    Settings,
    Shield,
    Sparkles,
    Sun,
    Target,
    TrendingUp,
    UserRound,
    UserPlus,
    UsersRound,
    X,
} from 'lucide';

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

document.documentElement.classList.add('motion-ready');

function initLucideIcons() {
    createIcons({
        icons: {
            ArrowLeft,
            ArrowRight,
            Bell,
            BookOpen,
            BookUser,
            Briefcase,
            BriefcaseBusiness,
            Building2,
            ChartNoAxesColumnIncreasing,
            Check,
            CircleCheckBig,
            ChevronDown,
            ClipboardClock,
            ClipboardList,
            ClipboardPaste,
            FileText,
            Files,
            GraduationCap,
            Gauge,
            History,
            Languages,
            LayoutDashboard,
            Lightbulb,
            ListChecks,
            ListTodo,
            LoaderCircle,
            LogOut,
            Map,
            Menu,
            MessageCircle,
            MessagesSquare,
            Moon,
            Play,
            Plus,
            Radar,
            ScanSearch,
            Settings,
            Shield,
            Sparkles,
            Sun,
            Target,
            TrendingUp,
            UserRound,
            UserPlus,
            UsersRound,
            X,
        },
        attrs: {
            'aria-hidden': 'true',
            'stroke-width': 1.7,
        },
        inTemplates: true,
    });
}

function initRevealMotion() {
    const elements = [...document.querySelectorAll('[data-reveal]')];

    if (!elements.length || reduceMotion.matches || !('IntersectionObserver' in window)) {
        elements.forEach((element) => element.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;

            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });

    elements.forEach((element) => observer.observe(element));
}

function initHeader() {
    const header = document.querySelector('[data-marketing-header]');

    if (!header) return;

    const syncHeader = () => header.classList.toggle('is-scrolled', window.scrollY > 18);
    syncHeader();
    window.addEventListener('scroll', syncHeader, { passive: true });
}

function initTrajectoryParallax() {
    const visual = document.querySelector('[data-trajectory-visual]');
    const stage = visual?.querySelector('.trajectory-stage');

    if (!visual || !stage || reduceMotion.matches) return;

    visual.addEventListener('pointermove', (event) => {
        const bounds = visual.getBoundingClientRect();
        const x = ((event.clientX - bounds.left) / bounds.width - 0.5) * 18;
        const y = ((event.clientY - bounds.top) / bounds.height - 0.5) * 18;

        stage.style.setProperty('--pointer-x', x.toFixed(2));
        stage.style.setProperty('--pointer-y', y.toFixed(2));
    });

    visual.addEventListener('pointerleave', () => {
        stage.style.setProperty('--pointer-x', '0');
        stage.style.setProperty('--pointer-y', '0');
    });
}

function initMobileNavigation() {
    const menu = document.querySelector('[data-mobile-nav]');

    if (!menu) return;

    menu.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => menu.removeAttribute('open'));
    });
}

export function initMarketingMotion() {
    initLucideIcons();
    initRevealMotion();
    initHeader();
    initTrajectoryParallax();
    initMobileNavigation();
}
