#!/usr/bin/env node

/**
 * ==============================================================================
 * Habit Application · E2E & UI Consistency Automated Test Suite Runner
 * ==============================================================================
 * Standalone Node.js ES Module runner (Zero external npm dependencies).
 * Inspects all 56+ application pages and layouts for design system conformance,
 * and orchestrates frontend build (vite build) & backend tests (php artisan test).
 *
 * Supported CLI flags:
 *   --all           : Run complete suite (static audit + vite build + php artisan test) [DEFAULT]
 *   --only-static   : Run fast static UI consistency audit only (< 300ms)
 *   --skip-build    : Skip npm run build; run static audit + php artisan test
 *   --skip-php      : Skip php artisan test; run static audit + npm run build
 *   --stage=<1-7>   : Restrict static audit (and PHPUnit) to files in milestone stage
 *   --path=<path>   : Restrict static audit to a specific file or directory
 *   --json          : Output machine-readable JSON results to stdout
 *   --verbose       : Print full violation snippets and subprocess logs
 *   --help, -h      : Show usage documentation
 *
 * Exit codes:
 *   0: All enabled checks passed (0 violations, clean build, clean tests)
 *   1: Audit violations found or subprocess failed
 *   2: CLI argument or runtime execution error
 * ==============================================================================
 */

import fs from 'node:fs';
import path from 'node:path';
import { spawn, execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import process from 'node:process';

// Resolve directory paths relative to script location
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT_DIR = path.resolve(__dirname, '..');

const PAGES_DIR = path.join(ROOT_DIR, 'resources', 'js', 'Pages');
const LAYOUTS_DIR = path.join(ROOT_DIR, 'resources', 'js', 'Layouts');
const COMPONENTS_UI_DIR = path.join(ROOT_DIR, 'resources', 'js', 'Components', 'UI');
const DOCS_DIR = path.join(ROOT_DIR, 'docs');

// ANSI Terminal Colors
const colors = {
    reset: '\x1b[0m',
    bold: '\x1b[1m',
    dim: '\x1b[2m',
    red: '\x1b[31m',
    green: '\x1b[32m',
    yellow: '\x1b[33m',
    blue: '\x1b[34m',
    magenta: '\x1b[35m',
    cyan: '\x1b[36m',
    gray: '\x1b[90m',
    bgRed: '\x1b[41m',
    bgGreen: '\x1b[42m',
};

// ==============================================================================
// 1. CLI Argument Parsing
// ==============================================================================

function parseArgs(argv) {
    const args = {
        all: false,
        onlyStatic: false,
        skipBuild: false,
        skipPhp: false,
        stage: null,
        targetPath: null,
        json: false,
        verbose: false,
        help: false,
    };

    for (let i = 2; i < argv.length; i++) {
        const arg = argv[i];

        if (arg === '--help' || arg === '-h') {
            args.help = true;
        } else if (arg === '--all') {
            args.all = true;
        } else if (arg === '--only-static') {
            args.onlyStatic = true;
        } else if (arg === '--skip-build') {
            args.skipBuild = true;
        } else if (arg === '--skip-php') {
            args.skipPhp = true;
        } else if (arg === '--json') {
            args.json = true;
        } else if (arg === '--verbose') {
            args.verbose = true;
        } else if (arg.startsWith('--stage=')) {
            args.stage = parseInt(arg.split('=')[1], 10);
        } else if (arg === '--stage' && i + 1 < argv.length) {
            args.stage = parseInt(argv[++i], 10);
        } else if (arg.startsWith('--path=')) {
            args.targetPath = arg.split('=')[1];
        } else if (arg === '--path' && i + 1 < argv.length) {
            args.targetPath = argv[++i];
        } else {
            console.error(`${colors.red}Unknown flag: ${arg}${colors.reset}`);
            showHelp();
            process.exit(2);
        }
    }

    if (args.stage !== null && (isNaN(args.stage) || args.stage < 1 || args.stage > 7)) {
        console.error(`${colors.red}Error: --stage must be an integer between 1 and 7.${colors.reset}`);
        process.exit(2);
    }

    // Default mode: if --only-static, --skip-build, or --skip-php not explicitly specified, --all is true
    if (!args.onlyStatic && !args.skipBuild && !args.skipPhp) {
        args.all = true;
    }

    return args;
}

function showHelp() {
    console.log(`
${colors.bold}HABIT APPLICATION · E2E & UI CONSISTENCY AUDIT RUNNER${colors.reset}

${colors.yellow}Usage:${colors.reset}
  node tests/e2e-ui-audit.js [flags]

${colors.yellow}Flags:${colors.reset}
  ${colors.green}--all${colors.reset}           Run complete suite (Static audit + vite build + php artisan test) [Default]
  ${colors.green}--only-static${colors.reset}   Run fast static UI consistency audit only (< 300ms)
  ${colors.green}--skip-build${colors.reset}    Skip frontend build (runs static audit + php artisan test)
  ${colors.green}--skip-php${colors.reset}      Skip backend test suite (runs static audit + vite build)
  ${colors.green}--stage=<1-7>${colors.reset}   Filter static analysis to files in a specific refactoring stage:
                    Stage 1: Layouts & UI Foundation (Sidebar, Topbar, AuthenticatedLayout)
                    Stage 2: Dashboards (Dashboard, Admin/Dashboard)
                    Stage 3: Admin Modules (Users, Divisions, Habits, Laporan, Attendance, Settings, etc.)
                    Stage 4: Academic, TU & Notulen (Disposisi, SuratMasuk, SuratKeluar, Notulen, Journals)
                    Stage 5: Employee, Work & Communication (Profile, Notifications, Chat, Attendance, Kiosk)
                    Stage 6: Personal & Islamic Features (FormHabit, Dzikir, Haid, Hpt, Kajian, Qibla, etc.)
                    Stage 7: Auth & Public Pages (Login, Welcome, Maintenance, Verifikasi)
  ${colors.green}--path=<path>${colors.reset}   Scan only a specific file or subfolder
  ${colors.green}--json${colors.reset}          Output results formatted as JSON (for CI or automated pipelines)
  ${colors.green}--verbose${colors.reset}       Display detailed violation snippets and subprocess logs
  ${colors.green}--help, -h${colors.reset}      Show this help documentation

${colors.yellow}Examples:${colors.reset}
  node tests/e2e-ui-audit.js --only-static
  node tests/e2e-ui-audit.js --stage=1
  node tests/e2e-ui-audit.js --only-static --json
  node tests/e2e-ui-audit.js --all
`);
}

// ==============================================================================
// 2. Stage Catalog & File Crawler
// ==============================================================================

/**
 * 7 Refactoring Stages Catalog
 * Maps file paths (relative to project root with forward slashes) to stages.
 */
const FILE_STAGE_MAPPING = {
    // Stage 1: Layouts & UI Foundations
    'resources/js/Layouts/AuthenticatedLayout.vue': 1,
    'resources/js/Layouts/Sidebar.vue': 1,
    'resources/js/Layouts/Topbar.vue': 1,

    // Stage 2: Dashboards
    'resources/js/Pages/Dashboard.vue': 2,
    'resources/js/Pages/Admin/Dashboard.vue': 2,

    // Stage 3: Admin Module
    'resources/js/Pages/Admin/Users/Index.vue': 3,
    'resources/js/Pages/Admin/Divisions/Index.vue': 3,
    'resources/js/Pages/Admin/Habits/Index.vue': 3,
    'resources/js/Pages/Admin/Laporan/Index.vue': 3,
    'resources/js/Pages/Admin/Laporan/Detail.vue': 3,
    'resources/js/Pages/Admin/Laporan/Unfilled.vue': 3,
    'resources/js/Pages/Admin/Attendance/Index.vue': 3,
    'resources/js/Pages/Admin/DutySchedules/Index.vue': 3,
    'resources/js/Pages/Admin/Settings/Index.vue': 3,
    'resources/js/Pages/Admin/Settings/Hr.vue': 3,
    'resources/js/Pages/Admin/LoginLogs/Index.vue': 3,
    'resources/js/Pages/Admin/System/Monitor.vue': 3,
    'resources/js/Pages/Admin/SpecialSchedules/Index.vue': 3,
    'resources/js/Pages/Admin/Documents/Index.vue': 3,
    'resources/js/Pages/Admin/Performance/Index.vue': 3,
    'resources/js/Pages/Admin/TeacherJournal/Index.vue': 3,
    'resources/js/Pages/Admin/TeacherAdministration/Index.vue': 3,

    // Stage 4: Academic, TU & Notulen
    'resources/js/Pages/TataUsaha/Disposisi/Index.vue': 4,
    'resources/js/Pages/TataUsaha/Settings/Index.vue': 4,
    'resources/js/Pages/TataUsaha/SuratKeluar/Index.vue': 4,
    'resources/js/Pages/TataUsaha/SuratKeluar/Builder.vue': 4,
    'resources/js/Pages/TataUsaha/SuratMasuk/Index.vue': 4,
    'resources/js/Pages/Notulen/Index.vue': 4,
    'resources/js/Pages/Notulen/Create.vue': 4,
    'resources/js/Pages/Notulen/Edit.vue': 4,
    'resources/js/Pages/Notulen/Show.vue': 4,
    'resources/js/Pages/TeacherJournal/Index.vue': 4,
    'resources/js/Pages/TeacherJournal/Form.vue': 4,
    'resources/js/Pages/TeacherAdministration/Index.vue': 4,

    // Stage 5: Employee, Work & Communication
    'resources/js/Pages/Employee/Documents.vue': 5,
    'resources/js/Pages/Employee/Performance/Index.vue': 5,
    'resources/js/Pages/Profile/Edit.vue': 5,
    'resources/js/Pages/Notifications/Index.vue': 5,
    'resources/js/Pages/Chat/Index.vue': 5,
    'resources/js/Pages/Attendance/Index.vue': 5,
    'resources/js/Pages/Attendance/RFIDKiosk.vue': 5,

    // Stage 6: Personal Habits & Islamic Utilities
    'resources/js/Pages/FormHabit.vue': 6,
    'resources/js/Pages/QuranRecap.vue': 6,
    'resources/js/Pages/Dzikir/Index.vue': 6,
    'resources/js/Pages/Dzikir/Partials/TasbihMode.vue': 6,
    'resources/js/Pages/Haid/Index.vue': 6,
    'resources/js/Pages/Hpt/Index.vue': 6,
    'resources/js/Pages/Kajian/Index.vue': 6,
    'resources/js/Pages/Qibla/Index.vue': 6,
    'resources/js/Pages/QuranHadis/Index.vue': 6,
    'resources/js/Pages/QuranHadis/Partials/ShareImageModal.vue': 6,
    'resources/js/Pages/System/Panduan.vue': 6,

    // Stage 7: Auth & Public Standalone
    'resources/js/Pages/Auth/Login.vue': 7,
    'resources/js/Pages/Welcome.vue': 7,
    'resources/js/Pages/Maintenance.vue': 7,
    'resources/js/Pages/Public/VerifikasiNotulen.vue': 7,
    'resources/js/Pages/Public/VerifikasiSurat.vue': 7,
};

function collectVueFiles(dir) {
    if (!fs.existsSync(dir)) return [];
    const results = [];
    const entries = fs.readdirSync(dir, { withFileTypes: true });

    for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            results.push(...collectVueFiles(fullPath));
        } else if (entry.isFile() && entry.name.endsWith('.vue')) {
            results.push(fullPath);
        }
    }
    return results;
}

function getTargetFiles(stage = null, customPath = null) {
    let allFiles = [];

    if (customPath) {
        const resolved = path.isAbsolute(customPath) ? customPath : path.resolve(ROOT_DIR, customPath);
        if (!fs.existsSync(resolved)) {
            console.error(`${colors.red}Error: Target path '${customPath}' does not exist on disk.${colors.reset}`);
            process.exit(2);
        }
        const stat = fs.statSync(resolved);
        if (stat.isDirectory()) {
            allFiles = collectVueFiles(resolved);
        } else if (resolved.endsWith('.vue')) {
            allFiles = [resolved];
        } else {
            console.error(`${colors.red}Error: Target path '${customPath}' is not a .vue file or directory.${colors.reset}`);
            process.exit(2);
        }

        if (allFiles.length === 0) {
            console.error(`${colors.red}Error: No .vue files found in target path '${customPath}'.${colors.reset}`);
            process.exit(2);
        }
    } else {
        const pageFiles = collectVueFiles(PAGES_DIR);
        const layoutFiles = collectVueFiles(LAYOUTS_DIR);
        allFiles = [...pageFiles, ...layoutFiles];
    }

    if (stage === null) {
        return allFiles;
    }

    return allFiles.filter((filePath) => {
        const relPath = path.relative(ROOT_DIR, filePath).replace(/\\/g, '/');
        const assignedStage = FILE_STAGE_MAPPING[relPath];
        return assignedStage === stage;
    });
}

// ==============================================================================
// 3. Static Consistency Rules Engine
// ==============================================================================

const RULES = [
    {
        id: 'RULE_1_PROHIBITED_COLORS',
        category: 'Color Tokens',
        name: 'Prohibited color accents (indigo, purple, violet, gray, zinc, neutral, stone)',
        severity: 'error',
        check: (line, lineNum, relPath) => {
            // Skip code comments
            const trimmed = line.trim();
            if (trimmed.startsWith('//') || trimmed.startsWith('/*') || trimmed.startsWith('*') || trimmed.startsWith('<!--')) {
                return null;
            }

            // Matches Tailwind color utility prefixes with forbidden color names
            const pattern = /\b(?:[a-z0-9_:-]+:)?(?:bg|text|border|ring|divide|placeholder|from|to|via|fill|stroke|shadow|accent|outline)-(indigo|purple|violet|gray|zinc|neutral|stone)(?:-[a-z0-9/]+)?\b/gi;
            const matches = [];
            let match;
            while ((match = pattern.exec(line)) !== null) {
                matches.push(match[0]);
            }

            if (matches.length > 0) {
                return {
                    message: `Prohibited color accent '${matches.join(', ')}' found. Use primary-* tokens or slate-* neutrals.`,
                    matched: matches.join(', '),
                };
            }
            return null;
        },
    },
    {
        id: 'RULE_2_RADIUS_STANDARD',
        category: 'Border Radius',
        name: '3-level radius standard (rounded-2xl cards/modals, rounded-xl buttons/inputs, rounded-full pills)',
        severity: 'error',
        check: (line, lineNum, relPath) => {
            const trimmed = line.trim();
            // Skip code comments
            if (trimmed.startsWith('//') || trimmed.startsWith('/*') || trimmed.startsWith('*') || trimmed.startsWith('<!--')) {
                return null;
            }

            // Whitelist intentional decorative blur backgrounds or aria-hidden elements
            if (line.includes('whitelist: decorative') || line.includes('aria-hidden="true"') || line.includes('pointer-events-none blur-')) {
                return null;
            }

            // Flag non-standard radius: rounded-3xl, rounded-lg, rounded-md, rounded-sm, rounded-xs, arbitrary rounded-[...]
            // Includes Tailwind logical border radius prefixes (s, e, ss, se, es, ee)
            const pattern = /\b(?:[a-z0-9_:-]+:)?rounded(?:-(?:s|e|ss|se|es|ee|t|b|l|r|tl|tr|bl|br))?-(3xl|lg|md|sm|xs|\[[^\]]+\])(?!\w)/gi;
            const matches = [];
            let match;
            while ((match = pattern.exec(line)) !== null) {
                matches.push(match[0]);
            }

            // Also check unadorned standalone "rounded" (excluding rounded-xl, rounded-2xl, rounded-full, rounded-none)
            // Skip JS variable declarations (const rounded, let rounded, etc.)
            if (!/^\s*(?:const|let|var|import|export)\s+rounded\b/.test(line) && !/\brounded\s*:\s*(?:Boolean|boolean|true|false)\b/.test(line)) {
                const unadornedPattern = /\b(?:[a-z0-9_:-]+:)?rounded\b(?![a-zA-Z0-9_/-])/g;
                while ((match = unadornedPattern.exec(line)) !== null) {
                    matches.push(match[0]);
                }
            }

            if (matches.length > 0) {
                return {
                    message: `Prohibited radius class '${matches.join(', ')}' detected. Conforming radii are rounded-2xl, rounded-xl, or rounded-full.`,
                    matched: matches.join(', '),
                };
            }
            return null;
        },
    },
    {
        id: 'RULE_3_HEADING_STANDARDIZATION',
        category: 'Typography',
        name: 'Uniform heading pattern (<PageHeader> instead of raw <h1>)',
        severity: 'error',
        checkFile: (content, relPath, lines) => {
            // Whitelist Welcome.vue hero h1 and PageHeader.vue itself
            if (relPath.endsWith('Welcome.vue') || relPath.endsWith('PageHeader.vue')) {
                return [];
            }

            const results = [];
            const rawH1Pattern = /<h1(?:\s+[^>]*)?>/gi;
            let match;
            while ((match = rawH1Pattern.exec(content)) !== null) {
                const lineNum = content.substring(0, match.index).split('\n').length;
                results.push({
                    line: lineNum,
                    message: `Raw <h1 found. Page titles must use <PageHeader :title="..." :subtitle="..."> component.`,
                    matched: match[0].replace(/\s+/g, ' ').trim(),
                    snippet: lines[lineNum - 1]?.trim() || match[0].trim(),
                });
            }
            return results;
        },
    },
    {
        id: 'RULE_4_MODAL_STANDARDIZATION',
        category: 'Interactive',
        name: 'Modal standardization (0 custom fixed inset-0 wrappers in Pages, must use Modal.vue)',
        severity: 'error',
        checkFile: (content, relPath, lines) => {
            // Whitelist Layouts and UI Components
            if (relPath.startsWith('resources/js/Layouts/') || relPath.startsWith('resources/js/Components/UI/')) {
                return [];
            }

            const results = [];
            // Match custom fixed inset-0 modal wrappers supporting single-line and multiline tag declarations
            const modalTagPattern = /<[a-zA-Z0-9_-]+[^>]*(?:\bfixed\b[^>]*\binset-0\b|\binset-0\b[^>]*\bfixed\b)[^>]*>/gi;
            let match;
            while ((match = modalTagPattern.exec(content)) !== null) {
                const lineNum = content.substring(0, match.index).split('\n').length;
                results.push({
                    line: lineNum,
                    message: `Custom modal wrapper 'fixed inset-0' found. Must migrate to Components/UI/Modal.vue.`,
                    matched: match[0].replace(/\s+/g, ' ').trim(),
                    snippet: lines[lineNum - 1]?.trim() || match[0].trim(),
                });
            }
            return results;
        },
    },
    {
        id: 'RULE_5_HEX_CODE_WHITELIST',
        category: 'Color Tokens',
        name: 'Hex code elimination (only #25D366 whitelisted for WhatsApp)',
        severity: 'error',
        check: (line, lineNum, relPath) => {
            const trimmed = line.trim();
            if (trimmed.startsWith('//') || trimmed.startsWith('/*') || trimmed.startsWith('*') || trimmed.startsWith('<!--')) {
                return null;
            }

            const hexPattern = /#([0-9a-fA-F]{3,8})\b/g;
            const matches = [];
            let match;

            while ((match = hexPattern.exec(line)) !== null) {
                const hex = match[0];
                // Whitelist WhatsApp official brand green (#25D366 / #25d366)
                if (hex.toUpperCase() === '#25D366') {
                    continue;
                }

                // Exclude HTML character entities like &#8203;
                if (line.includes('&' + hex)) {
                    continue;
                }

                // Exclude SVG clipPath / ID references, url(#...), href="#..."
                if (line.includes(`url(${hex})`) || line.includes(`url('${hex}')`) || line.includes(`url("${hex}")`)) {
                    continue;
                }
                if (line.includes(`href="${hex}"`) || line.includes(`href='${hex}'`)) {
                    continue;
                }

                // Exclude Vue slot shorthand (e.g. <template #actions> or hypothetical <template #fade>)
                const slotRegex = new RegExp('<template\\s+' + hex + '(?:[\\s>]|$)', 'i');
                if (slotRegex.test(line)) {
                    continue;
                }

                matches.push(hex);
            }

            if (matches.length > 0) {
                return {
                    message: `Hardcoded hex code '${matches.join(', ')}' detected. Replace with CSS variable or theme token.`,
                    matched: matches.join(', '),
                };
            }
            return null;
        },
    },
    {
        id: 'RULE_6_CONTAINER_STANDARDIZATION',
        category: 'Layout',
        name: 'Container standardization (flag max-w-9xl and double padding in authenticated pages)',
        severity: 'error',
        check: (line, lineNum, relPath) => {
            // Whitelist standalone unauthenticated pages
            if (
                relPath.endsWith('Welcome.vue') ||
                relPath.includes('Auth/') ||
                relPath.endsWith('Maintenance.vue') ||
                relPath.includes('Public/')
            ) {
                return null;
            }

            // Flag invalid max-width classes
            const maxWidthPattern = /\bmax-w-(?:9xl|\[90rem\]|\[100rem\])(?!\w)/i;
            if (maxWidthPattern.test(line)) {
                return {
                    message: `Invalid max-width class detected. Standard containers use PageContainer (size="md|lg|xl|full").`,
                    matched: line.trim(),
                };
            }

            // Flag double outer padding pattern on root/wrapper in pages inside AuthenticatedLayout
            const doublePaddingPattern = /\b(?:px-4\s+sm:px-6\s+lg:px-8(?:\s+py-[68])?|py-[68]\s+px-4\s+sm:px-6\s+lg:px-8|py-10\s+px-4|p-2\s+sm:p-4)\b/i;
            if (doublePaddingPattern.test(line)) {
                return {
                    message: `Double outer padding detected on page wrapper. AuthenticatedLayout already supplies p-4 sm:p-6.`,
                    matched: line.trim(),
                };
            }

            return null;
        },
    },
    {
        id: 'RULE_7_VIEWPORT_ANOMALIES',
        category: 'Layout',
        name: 'Viewport anomalies (flag min-h-[80vh], style="height: 85vh;", h-[calc(100vh-...)])',
        severity: 'error',
        check: (line, lineNum, relPath) => {
            // Exception: Chat/Index.vue is permitted to use standard .page-fill, but not ad-hoc calc
            const pattern = /(?:min-|max-)?h-\[(?:\d+vh|calc\([^\]]+\))\](?!\w)|style="[^"]*(?:min-|max-)?height\s*:\s*(?:8\dvh|calc\([^;"]+\))[^"]*"|height\s*:\s*(?:8\dvh|calc\([^;"]+\))/i;
            if (pattern.test(line)) {
                return {
                    message: `Ad-hoc viewport height detected. Use fluid content layout or standard .page-fill for Chat.`,
                    matched: line.trim(),
                };
            }
            return null;
        },
    },
];

/**
 * Validates Core UI Components and Documentation Contracts
 */
function checkContractsAndDocs() {
    const checks = [
        {
            file: path.join(DOCS_DIR, 'ui-audit.md'),
            name: 'docs/ui-audit.md',
            required: true,
            minSize: 1000,
        },
        {
            file: path.join(DOCS_DIR, 'ui-guidelines.md'),
            name: 'docs/ui-guidelines.md',
            required: true,
            minSize: 1000,
        },
        {
            file: path.join(COMPONENTS_UI_DIR, 'PageContainer.vue'),
            name: 'resources/js/Components/UI/PageContainer.vue',
            required: true,
            minSize: 100,
        },
        {
            file: path.join(COMPONENTS_UI_DIR, 'PageHeader.vue'),
            name: 'resources/js/Components/UI/PageHeader.vue',
            required: true,
            minSize: 100,
        },
        {
            file: path.join(COMPONENTS_UI_DIR, 'Card.vue'),
            name: 'resources/js/Components/UI/Card.vue',
            required: true,
            minSize: 100,
        },
        {
            file: path.join(COMPONENTS_UI_DIR, 'Badge.vue'),
            name: 'resources/js/Components/UI/Badge.vue',
            required: true,
            minSize: 100,
        },
        {
            file: path.join(COMPONENTS_UI_DIR, 'Button.vue'),
            name: 'resources/js/Components/UI/Button.vue',
            required: true,
            minSize: 100,
        },
        {
            file: path.join(COMPONENTS_UI_DIR, 'Modal.vue'),
            name: 'resources/js/Components/UI/Modal.vue',
            required: true,
            minSize: 100,
        },
    ];

    const violations = [];

    for (const item of checks) {
        if (!fs.existsSync(item.file)) {
            violations.push({
                rule: 'RULE_8_CONTRACT_DOCS',
                category: 'Contracts',
                file: item.name,
                line: 1,
                message: `Required contract file '${item.name}' is missing.`,
                matched: 'FILE_NOT_FOUND',
                snippet: '',
            });
        } else {
            const stat = fs.statSync(item.file);
            if (stat.size < item.minSize) {
                violations.push({
                    rule: 'RULE_8_CONTRACT_DOCS',
                    category: 'Contracts',
                    file: item.name,
                    line: 1,
                    message: `Required contract file '${item.name}' is incomplete or empty (${stat.size} bytes < ${item.minSize} bytes).`,
                    matched: `SIZE_${stat.size}_BYTES`,
                    snippet: '',
                });
            }
        }
    }

    return violations;
}

/**
 * Runs static analysis across target files.
 */
function runStaticAnalysis(files, options = {}) {
    const startTime = Date.now();
    const violations = [];
    const ruleViolationCounts = {};

    for (const r of RULES) {
        ruleViolationCounts[r.id] = 0;
    }
    ruleViolationCounts['RULE_8_CONTRACT_DOCS'] = 0;

    for (const filePath of files) {
        const relPath = path.relative(ROOT_DIR, filePath).replace(/\\/g, '/');
        let content = '';
        try {
            content = fs.readFileSync(filePath, 'utf-8');
        } catch (err) {
            violations.push({
                rule: 'FILE_READ_ERROR',
                category: 'File System',
                file: relPath,
                line: 1,
                message: `Unable to read file: ${err.message}`,
                matched: '',
                snippet: '',
            });
            continue;
        }

        const lines = content.split('\n');

        // 1. Run file-level rules (e.g. multiline tag declarations)
        for (const rule of RULES) {
            if (rule.checkFile) {
                const fileViolations = rule.checkFile(content, relPath, lines);
                for (const res of fileViolations) {
                    ruleViolationCounts[rule.id]++;
                    violations.push({
                        rule: rule.id,
                        category: rule.category,
                        file: relPath,
                        line: res.line,
                        message: res.message,
                        matched: res.matched,
                        snippet: res.snippet,
                    });
                }
            }
        }

        // 2. Run line-by-line rules
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            const lineNum = i + 1;

            for (const rule of RULES) {
                if (rule.check) {
                    const res = rule.check(line, lineNum, relPath);
                    if (res) {
                        ruleViolationCounts[rule.id]++;
                        violations.push({
                            rule: rule.id,
                            category: rule.category,
                            file: relPath,
                            line: lineNum,
                            message: res.message,
                            matched: res.matched,
                            snippet: line.trim(),
                        });
                    }
                }
            }
        }
    }

    // Check Core UI & documentation contracts (if not scoped to stage 2-7)
    if (options.stage === null || options.stage === 1) {
        const docViolations = checkContractsAndDocs();
        for (const v of docViolations) {
            ruleViolationCounts['RULE_8_CONTRACT_DOCS']++;
            violations.push(v);
        }
    }

    const durationMs = Date.now() - startTime;

    return {
        filesScanned: files.length,
        durationMs,
        violations,
        ruleViolationCounts,
        passed: violations.length === 0,
    };
}

// ==============================================================================
// 4. Subprocess Execution Engine
// ==============================================================================

function runSubprocess(command, args = [], options = {}) {
    return new Promise((resolve) => {
        const startTime = Date.now();
        const isWindows = process.platform === 'win32';
        const fullCmd = args.length > 0 ? `${command} ${args.join(' ')}` : command;

        const isJsonMode = Boolean(options.isJson);
        const shouldInherit = Boolean(options.verbose) && !isJsonMode;

        const proc = spawn(fullCmd, [], {
            shell: isWindows,
            cwd: options.cwd || ROOT_DIR,
            env: {
                ...process.env,
                RAYON_NUM_THREADS: '1',
                ...(options.env || {}),
            },
            stdio: shouldInherit ? 'inherit' : ['ignore', 'pipe', 'pipe'],
        });

        let stdout = '';
        let stderr = '';

        if (!shouldInherit) {
            proc.stdout?.on('data', (d) => {
                stdout += d.toString();
                if (options.verbose && !isJsonMode) {
                    process.stdout.write(d);
                }
            });
            proc.stderr?.on('data', (d) => {
                stderr += d.toString();
                if (options.verbose && !isJsonMode) {
                    process.stderr.write(d);
                }
            });
        }

        const timeout = setTimeout(() => {
            if (isWindows && proc.pid) {
                try {
                    execSync(`taskkill /pid ${proc.pid} /T /F`, { stdio: 'ignore' });
                } catch (_) {}
            } else {
                try {
                    proc.kill('SIGTERM');
                } catch (_) {}
            }
            resolve({
                success: false,
                code: -1,
                durationMs: Date.now() - startTime,
                stdout,
                stderr: stderr + '\n[ERROR] Command timed out after limit.',
            });
        }, options.timeoutMs || 180000);

        proc.on('close', (code) => {
            clearTimeout(timeout);
            resolve({
                success: code === 0,
                code,
                durationMs: Date.now() - startTime,
                stdout,
                stderr,
            });
        });

        proc.on('error', (err) => {
            clearTimeout(timeout);
            resolve({
                success: false,
                code: 1,
                durationMs: Date.now() - startTime,
                stdout,
                stderr: stderr + `\n${err.message}`,
            });
        });
    });
}

// ==============================================================================
// 5. Output Formatting & Summary Reporting
// ==============================================================================

function renderTerminalReport(results, options) {
    const hr = '═'.repeat(74);
    const subHr = '─'.repeat(74);

    console.log(`\n${colors.bold}${colors.cyan}${hr}${colors.reset}`);
    console.log(`${colors.bold}${colors.cyan}  HABIT APPLICATION · E2E & UI CONSISTENCY AUDIT RUNNER${colors.reset}`);
    console.log(`${colors.bold}${colors.cyan}${hr}${colors.reset}\n`);

    const stageText = options.stage ? ` (Filtered to Stage ${options.stage})` : ' (All 7 Stages)';
    console.log(`${colors.bold}[1/3] Static UI Consistency Audit${stageText}${colors.reset}`);
    console.log(`${colors.gray}${subHr}${colors.reset}`);
    console.log(`  Scanned Files: ${colors.bold}${results.staticAudit.filesScanned}${colors.reset} across resources/js/{Pages,Layouts}`);
    console.log(`  Execution Time: ${(results.staticAudit.durationMs / 1000).toFixed(3)}s\n`);

    const ruleLabels = [
        { id: 'RULE_1_PROHIBITED_COLORS', label: 'Prohibited Color Accents (indigo|purple|gray|etc)' },
        { id: 'RULE_2_RADIUS_STANDARD', label: '3-Level Radius Standards (rounded-2xl/xl/full)' },
        { id: 'RULE_3_HEADING_STANDARDIZATION', label: 'Uniform Page Headings (<PageHeader> vs raw h1)' },
        { id: 'RULE_4_MODAL_STANDARDIZATION', label: 'Modal Standardization (0 fixed inset-0)' },
        { id: 'RULE_5_HEX_CODE_WHITELIST', label: 'Hardcoded Hex Colors (excl. #25D366 WhatsApp)' },
        { id: 'RULE_6_CONTAINER_STANDARDIZATION', label: 'Container Standardization & No Double Padding' },
        { id: 'RULE_7_VIEWPORT_ANOMALIES', label: 'Viewport Height Anomalies (min-h-[80vh], etc)' },
        { id: 'RULE_8_CONTRACT_DOCS', label: 'Core UI Contracts & Documentation Files' },
    ];

    for (const item of ruleLabels) {
        const count = results.staticAudit.ruleViolationCounts[item.id] || 0;
        const icon = count === 0 ? `${colors.green}✓ 0 violations${colors.reset}` : `${colors.red}✗ ${count} violations${colors.reset}`;
        console.log(`  • ${item.label.padEnd(52)} ${icon}`);
    }

    if (results.staticAudit.violations.length > 0) {
        console.log(`\n${colors.bold}${colors.red}Violation Details (${results.staticAudit.violations.length} total):${colors.reset}`);
        const displayLimit = options.verbose ? results.staticAudit.violations.length : 15;
        const slice = results.staticAudit.violations.slice(0, displayLimit);

        for (const v of slice) {
            console.log(`  ${colors.red}✗${colors.reset} ${colors.yellow}${v.file}:${v.line}${colors.reset} [${v.rule}]`);
            console.log(`    ${colors.gray}Reason:${colors.reset} ${v.message}`);
            if (v.snippet) {
                console.log(`    ${colors.dim}Line:${colors.reset}   ${v.snippet}`);
            }
        }

        if (results.staticAudit.violations.length > displayLimit) {
            console.log(`\n  ${colors.gray}... and ${results.staticAudit.violations.length - displayLimit} more violations (run with --verbose to display all).${colors.reset}`);
        }
    }

    // Section 2: Frontend Build
    if (!options.onlyStatic && !options.skipBuild) {
        console.log(`\n${colors.bold}[2/3] Frontend Build Verification (npm run build)${colors.reset}`);
        console.log(`${colors.gray}${subHr}${colors.reset}`);
        if (results.npmBuild) {
            const status = results.npmBuild.success
                ? `${colors.green}✓ PASSED${colors.reset} (${(results.npmBuild.durationMs / 1000).toFixed(2)}s)`
                : `${colors.red}✗ FAILED${colors.reset} (${(results.npmBuild.durationMs / 1000).toFixed(2)}s, exit code ${results.npmBuild.code})`;
            console.log(`  Command: npm run build`);
            console.log(`  Status:  ${status}`);
            if (!results.npmBuild.success && results.npmBuild.stderr) {
                console.log(`  Error Output:\n${results.npmBuild.stderr.trim()}`);
            }
        }
    } else {
        console.log(`\n${colors.gray}[2/3] Frontend Build Verification: SKIPPED${colors.reset}`);
    }

    // Section 3: Backend Tests
    if (!options.onlyStatic && !options.skipPhp) {
        console.log(`\n${colors.bold}[3/3] Backend Automated Tests (php artisan test)${colors.reset}`);
        console.log(`${colors.gray}${subHr}${colors.reset}`);
        if (results.phpArtisanTest) {
            const status = results.phpArtisanTest.success
                ? `${colors.green}✓ PASSED${colors.reset} (${(results.phpArtisanTest.durationMs / 1000).toFixed(2)}s)`
                : `${colors.red}✗ FAILED${colors.reset} (${(results.phpArtisanTest.durationMs / 1000).toFixed(2)}s, exit code ${results.phpArtisanTest.code})`;
            console.log(`  Command: php artisan test`);
            console.log(`  Status:  ${status}`);
            if (!results.phpArtisanTest.success && results.phpArtisanTest.stdout) {
                console.log(`  Output:\n${results.phpArtisanTest.stdout.trim()}`);
            }
        }
    } else {
        console.log(`\n${colors.gray}[3/3] Backend Automated Tests: SKIPPED${colors.reset}`);
    }

    // Final Verdict
    const totalDuration = ((Date.now() - results.startTime) / 1000).toFixed(2);
    console.log(`\n${colors.bold}${colors.cyan}${hr}${colors.reset}`);
    if (results.overallPassed) {
        console.log(`  ${colors.bgGreen}${colors.bold} VERDICT: PASSED ${colors.reset} All enabled verification checks passed in ${totalDuration}s.`);
    } else {
        console.log(`  ${colors.bgRed}${colors.bold} VERDICT: FAILED ${colors.reset} Verification failed in ${totalDuration}s.`);
    }
    console.log(`${colors.bold}${colors.cyan}${hr}${colors.reset}\n`);
}

// ==============================================================================
// 6. Main Orchestrator
// ==============================================================================

async function main() {
    const startTime = Date.now();
    const args = parseArgs(process.argv);

    if (args.help) {
        showHelp();
        process.exit(0);
    }

    // Collect target files
    const targetFiles = getTargetFiles(args.stage, args.targetPath);

    if (targetFiles.length === 0) {
        console.error(`${colors.yellow}Warning: No .vue files matched stage ${args.stage} or path ${args.targetPath}.${colors.reset}`);
    }

    // 1. Run Static UI Consistency Audit
    const staticResult = runStaticAnalysis(targetFiles, { stage: args.stage });

    let npmBuildResult = null;
    let phpArtisanResult = null;

    // 2. Run Frontend Build (if not skipped)
    if (!args.onlyStatic && !args.skipBuild) {
        npmBuildResult = await runSubprocess('npm', ['run', 'build'], {
            verbose: args.verbose,
            isJson: args.json,
            timeoutMs: 120000,
        });
    }

    // 3. Run Backend PHP Tests (if not skipped)
    if (!args.onlyStatic && !args.skipPhp) {
        const phpEnv = {};
        if (args.stage) {
            phpEnv.UI_AUDIT_STAGE = String(args.stage);
        }
        phpArtisanResult = await runSubprocess('php', ['artisan', 'test'], {
            env: phpEnv,
            verbose: args.verbose,
            isJson: args.json,
            timeoutMs: 180000,
        });
    }

    // Evaluate overall pass/fail
    let overallPassed = staticResult.passed;

    if (npmBuildResult && !npmBuildResult.success) {
        overallPassed = false;
    }
    if (phpArtisanResult && !phpArtisanResult.success) {
        overallPassed = false;
    }

    const payload = {
        timestamp: new Date().toISOString(),
        overallPassed,
        exitCode: overallPassed ? 0 : 1,
        startTime,
        totalDurationMs: Date.now() - startTime,
        options: {
            stage: args.stage,
            targetPath: args.targetPath,
            all: args.all,
            onlyStatic: args.onlyStatic,
            skipBuild: args.skipBuild,
            skipPhp: args.skipPhp,
        },
        staticAudit: staticResult,
        npmBuild: npmBuildResult,
        phpArtisanTest: phpArtisanResult,
    };

    if (args.json) {
        console.log(JSON.stringify(payload, null, 2));
    } else {
        renderTerminalReport(payload, args);
    }

    process.exit(payload.exitCode);
}

main().catch((err) => {
    console.error(`${colors.red}Fatal execution error:${colors.reset}`, err);
    process.exit(2);
});
