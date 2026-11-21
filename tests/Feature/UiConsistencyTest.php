<?php

namespace Tests\Feature;

use Tests\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Class UiConsistencyTest
 *
 * Feature test suite to enforce UI consistency, design token adherence,
 * 3-level radius standards, modal uniformity, and container contracts
 * across all Vue pages and layouts in the Habit application.
 */
class UiConsistencyTest extends TestCase
{
    private string $pagesDir;
    private string $layoutsDir;
    private string $componentsUiDir;
    private string $docsDir;

    /**
     * Complete mapping of all 57 Vue pages and layouts to their milestone refactoring stage (1-7).
     * @var array<string, int>
     */
    private const FILE_STAGE_MAPPING = [
        // Stage 1: Layouts & UI Foundations
        'resources/js/Layouts/AuthenticatedLayout.vue' => 1,
        'resources/js/Layouts/Sidebar.vue' => 1,
        'resources/js/Layouts/Topbar.vue' => 1,

        // Stage 2: Dashboards
        'resources/js/Pages/Dashboard.vue' => 2,
        'resources/js/Pages/Admin/Dashboard.vue' => 2,

        // Stage 3: Admin Module
        'resources/js/Pages/Admin/Users/Index.vue' => 3,
        'resources/js/Pages/Admin/Divisions/Index.vue' => 3,
        'resources/js/Pages/Admin/Habits/Index.vue' => 3,
        'resources/js/Pages/Admin/Laporan/Index.vue' => 3,
        'resources/js/Pages/Admin/Laporan/Detail.vue' => 3,
        'resources/js/Pages/Admin/Laporan/Unfilled.vue' => 3,
        'resources/js/Pages/Admin/Attendance/Index.vue' => 3,
        'resources/js/Pages/Admin/DutySchedules/Index.vue' => 3,
        'resources/js/Pages/Admin/Settings/Index.vue' => 3,
        'resources/js/Pages/Admin/Settings/Hr.vue' => 3,
        'resources/js/Pages/Admin/LoginLogs/Index.vue' => 3,
        'resources/js/Pages/Admin/System/Monitor.vue' => 3,
        'resources/js/Pages/Admin/SpecialSchedules/Index.vue' => 3,
        'resources/js/Pages/Admin/Documents/Index.vue' => 3,
        'resources/js/Pages/Admin/Performance/Index.vue' => 3,
        'resources/js/Pages/Admin/TeacherJournal/Index.vue' => 3,
        'resources/js/Pages/Admin/TeacherAdministration/Index.vue' => 3,

        // Stage 4: Academic, TU & Notulen
        'resources/js/Pages/TataUsaha/Disposisi/Index.vue' => 4,
        'resources/js/Pages/TataUsaha/Settings/Index.vue' => 4,
        'resources/js/Pages/TataUsaha/SuratKeluar/Index.vue' => 4,
        'resources/js/Pages/TataUsaha/SuratKeluar/Builder.vue' => 4,
        'resources/js/Pages/TataUsaha/SuratMasuk/Index.vue' => 4,
        'resources/js/Pages/Notulen/Index.vue' => 4,
        'resources/js/Pages/Notulen/Create.vue' => 4,
        'resources/js/Pages/Notulen/Edit.vue' => 4,
        'resources/js/Pages/Notulen/Show.vue' => 4,
        'resources/js/Pages/TeacherJournal/Index.vue' => 4,
        'resources/js/Pages/TeacherJournal/Form.vue' => 4,
        'resources/js/Pages/TeacherAdministration/Index.vue' => 4,

        // Stage 5: Employee, Work & Communication
        'resources/js/Pages/Employee/Documents.vue' => 5,
        'resources/js/Pages/Employee/Performance/Index.vue' => 5,
        'resources/js/Pages/Profile/Edit.vue' => 5,
        'resources/js/Pages/Notifications/Index.vue' => 5,
        'resources/js/Pages/Chat/Index.vue' => 5,
        'resources/js/Pages/Attendance/Index.vue' => 5,
        'resources/js/Pages/Attendance/RFIDKiosk.vue' => 5,

        // Stage 6: Personal Habits & Islamic Utilities
        'resources/js/Pages/FormHabit.vue' => 6,
        'resources/js/Pages/QuranRecap.vue' => 6,
        'resources/js/Pages/Dzikir/Index.vue' => 6,
        'resources/js/Pages/Dzikir/Partials/TasbihMode.vue' => 6,
        'resources/js/Pages/Haid/Index.vue' => 6,
        'resources/js/Pages/Hpt/Index.vue' => 6,
        'resources/js/Pages/Kajian/Index.vue' => 6,
        'resources/js/Pages/Qibla/Index.vue' => 6,
        'resources/js/Pages/QuranHadis/Index.vue' => 6,
        'resources/js/Pages/QuranHadis/Partials/ShareImageModal.vue' => 6,
        'resources/js/Pages/System/Panduan.vue' => 6,

        // Stage 7: Auth & Public Standalone
        'resources/js/Pages/Auth/Login.vue' => 7,
        'resources/js/Pages/Welcome.vue' => 7,
        'resources/js/Pages/Maintenance.vue' => 7,
        'resources/js/Pages/Public/VerifikasiNotulen.vue' => 7,
        'resources/js/Pages/Public/VerifikasiSurat.vue' => 7,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->pagesDir = resource_path('js/Pages');
        $this->layoutsDir = resource_path('js/Layouts');
        $this->componentsUiDir = resource_path('js/Components/UI');
        $this->docsDir = base_path('docs');
    }

    /**
     * Retrieve current stage filter from environment, or null if all stages should be tested.
     */
    private function getCurrentStageFilter(): ?int
    {
        $stage = env('UI_AUDIT_STAGE') ?: ($_ENV['UI_AUDIT_STAGE'] ?? ($_SERVER['UI_AUDIT_STAGE'] ?? null));
        if ($stage !== null && is_numeric($stage)) {
            return (int) $stage;
        }
        return null;
    }

    /**
     * Recursively retrieve all .vue files within a directory.
     *
     * @param string $dir
     * @return array<string>
     */
    private function getVueFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'vue') {
                $files[] = $file->getRealPath();
            }
        }

        $stageFilter = $this->getCurrentStageFilter();
        if ($stageFilter !== null) {
            $files = array_filter($files, function ($path) use ($stageFilter) {
                $rel = $this->relative($path);
                return isset(self::FILE_STAGE_MAPPING[$rel]) && self::FILE_STAGE_MAPPING[$rel] === $stageFilter;
            });
        }

        return array_values($files);
    }

    /**
     * Rule 1: Zero prohibited color classes (indigo, purple, violet, gray, zinc, neutral, stone).
     */
    public function test_no_prohibited_color_classes_in_pages_and_layouts(): void
    {
        $files = array_merge(
            $this->getVueFiles($this->pagesDir),
            $this->getVueFiles($this->layoutsDir)
        );

        $pattern = '/\b(?:[a-z0-9_:-]+:)?(?:bg|text|border|ring|divide|placeholder|from|to|via|fill|stroke|shadow|accent|outline)-(indigo|purple|violet|gray|zinc|neutral|stone)(?:-[a-z0-9\/]+)?\b/i';
        $violations = [];

        foreach ($files as $file) {
            $lines = file($file);
            foreach ($lines as $lineIndex => $line) {
                $trimmed = trim($line);
                if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '<!--')) {
                    continue;
                }

                if (preg_match_all($pattern, $line, $matches, PREG_SET_ORDER)) {
                    $matchedClasses = array_map(fn($m) => $m[0], $matches);
                    $violations[] = sprintf(
                        "%s:%d: Found prohibited color class(es) '%s'\n  Line: %s",
                        $this->relative($file),
                        $lineIndex + 1,
                        implode(', ', $matchedClasses),
                        $trimmed
                    );
                }
            }
        }

        $this->assertEmpty(
            $violations,
            sprintf("Prohibited color classes detected in %d file locations:\n\n%s", count($violations), implode("\n\n", array_slice($violations, 0, 20)))
        );
    }

    /**
     * Rule 2: Zero prohibited radius classes (rounded-3xl, rounded-md, rounded-lg, rounded-sm, arbitrary rounded-[...]) outside whitelist.
     */
    public function test_no_prohibited_radius_classes(): void
    {
        $files = array_merge(
            $this->getVueFiles($this->pagesDir),
            $this->getVueFiles($this->layoutsDir)
        );

        $pattern = '/\b(?:[a-z0-9_:-]+:)?rounded(?:-(?:s|e|ss|se|es|ee|t|b|l|r|tl|tr|bl|br))?-(3xl|lg|md|sm|xs|\[[^\]]+\])(?!\w)/i';
        $unadornedPattern = '/\b(?:[a-z0-9_:-]+:)?rounded\b(?![a-zA-Z0-9_\/-])/';
        $violations = [];

        foreach ($files as $file) {
            $lines = file($file);
            foreach ($lines as $lineIndex => $line) {
                $trimmed = trim($line);
                if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '<!--')) {
                    continue;
                }

                if (
                    str_contains($line, 'whitelist: decorative') ||
                    str_contains($line, 'aria-hidden="true"') ||
                    str_contains($line, 'pointer-events-none blur-')
                ) {
                    continue;
                }

                $matched = [];
                if (preg_match_all($pattern, $line, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $m) {
                        $matched[] = $m[0];
                    }
                }
                if (!preg_match('/^\s*(?:const|let|var|import|export)\s+rounded\b/', $line) && !preg_match('/\brounded\s*:\s*(?:Boolean|boolean|true|false)\b/', $line)) {
                    if (preg_match_all($unadornedPattern, $line, $unadornedMatches, PREG_SET_ORDER)) {
                        foreach ($unadornedMatches as $m) {
                            $matched[] = $m[0];
                        }
                    }
                }

                if (!empty($matched)) {
                    $violations[] = sprintf(
                        "%s:%d: Found prohibited radius class '%s' (use rounded-2xl for cards/modals, rounded-xl for buttons/inputs, rounded-full for pills)\n  Line: %s",
                        $this->relative($file),
                        $lineIndex + 1,
                        implode(', ', $matched),
                        trim($line)
                    );
                }
            }
        }

        $this->assertEmpty(
            $violations,
            sprintf("Prohibited radius classes detected in %d file locations:\n\n%s", count($violations), implode("\n\n", array_slice($violations, 0, 20)))
        );
    }

    /**
     * Rule 3: Single uniform heading pattern via <PageHeader> across pages.
     */
    public function test_heading_standardization_uses_page_header(): void
    {
        $files = $this->getVueFiles($this->pagesDir);
        $violations = [];

        foreach ($files as $file) {
            $rel = $this->relative($file);
            // Allow Welcome.vue hero landing heading
            if (str_ends_with($rel, 'Welcome.vue')) {
                continue;
            }

            $content = file_get_contents($file);
            if (preg_match_all('/<h1(?:\s+[^>]*)?>/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $offset = $match[1];
                    $line = substr_count(substr($content, 0, $offset), "\n") + 1;
                    $violations[] = sprintf(
                        "%s:%d: Raw <h1 found. Page must use <PageHeader :title=\"...\" :subtitle=\"...\"> component.",
                        $rel,
                        $line
                    );
                }
            }
        }

        $this->assertEmpty(
            $violations,
            sprintf("Heading violations detected in %d files:\n\n%s", count($violations), implode("\n\n", array_slice($violations, 0, 20)))
        );
    }

    /**
     * Rule 4: Zero custom fixed inset-0 modal wrappers in Pages (all must use Modal.vue).
     */
    public function test_no_custom_fixed_inset_modal_wrappers_in_pages(): void
    {
        $files = $this->getVueFiles($this->pagesDir);
        $pattern = '/<[a-zA-Z0-9_-]+[^>]*(?:\bfixed\b[^>]*\binset-0\b|\binset-0\b[^>]*\bfixed\b)[^>]*>/i';
        $violations = [];

        foreach ($files as $file) {
            $rel = $this->relative($file);
            $content = file_get_contents($file);

            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $offset = $match[1];
                    $line = substr_count(substr($content, 0, $offset), "\n") + 1;
                    $snippet = trim(explode("\n", substr($content, $offset, 200))[0]);
                    $violations[] = sprintf(
                        "%s:%d: Custom modal wrapper 'fixed inset-0' found. Must migrate to Components/UI/Modal.vue\n  Line: %s",
                        $rel,
                        $line,
                        $snippet
                    );
                }
            }
        }

        $this->assertEmpty(
            $violations,
            sprintf("Custom modal wrappers detected in %d file locations:\n\n%s", count($violations), implode("\n\n", array_slice($violations, 0, 20)))
        );
    }

    /**
     * Rule 5: Zero hardcoded hex codes in Pages except #25D366 (WhatsApp).
     */
    public function test_no_hardcoded_hex_codes_except_whatsapp(): void
    {
        $files = $this->getVueFiles($this->pagesDir);
        $pattern = '/#([0-9a-fA-F]{3,8})\b/';
        $violations = [];

        foreach ($files as $file) {
            $rel = $this->relative($file);
            $lines = file($file);

            foreach ($lines as $lineIndex => $line) {
                if (preg_match('/^\s*(?:\/\/|\*|\/\*|<!--)/', $line)) {
                    continue;
                }

                if (preg_match_all($pattern, $line, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $m) {
                        $hex = $m[0];
                        if (strcasecmp($hex, '#25D366') === 0) {
                            continue; // Whitelisted WhatsApp green
                        }
                        // Skip HTML character entities like &#8203;
                        if (str_contains($line, '&' . $hex)) {
                            continue;
                        }
                        // Skip SVG clip/url references or fragment anchors
                        if (str_contains($line, "url({$hex})") || str_contains($line, "url('{$hex}')") || str_contains($line, "url(\"{$hex}\")")) {
                            continue;
                        }
                        if (str_contains($line, "href=\"{$hex}\"") || str_contains($line, "href='{$hex}'")) {
                            continue;
                        }
                        // Skip Vue slot shorthand (e.g. <template #actions> or hypothetical <template #fade>)
                        if (preg_match('/<template\s+' . preg_quote($hex, '/') . '(?:\s|>|$)/i', $line)) {
                            continue;
                        }

                        $violations[] = sprintf(
                            "%s:%d: Hardcoded hex '%s' detected. Replace with design token or CSS variable.\n  Line: %s",
                            $rel,
                            $lineIndex + 1,
                            $hex,
                            trim($line)
                        );
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            sprintf("Hardcoded hex codes detected in %d file locations:\n\n%s", count($violations), implode("\n\n", array_slice($violations, 0, 20)))
        );
    }

    /**
     * Rule 6: No double padding and no max-w-9xl in authenticated pages.
     */
    public function test_container_standardization_and_no_double_padding(): void
    {
        $files = $this->getVueFiles($this->pagesDir);
        $violations = [];

        foreach ($files as $file) {
            $rel = $this->relative($file);
            // Skip unauthenticated standalone pages
            if (
                str_ends_with($rel, 'Welcome.vue') ||
                str_contains($rel, 'Auth/') ||
                str_ends_with($rel, 'Maintenance.vue') ||
                str_contains($rel, 'Public/')
            ) {
                continue;
            }

            $content = file_get_contents($file);
            if (str_contains($content, 'max-w-9xl') || str_contains($content, 'max-w-[90rem]') || str_contains($content, 'max-w-[100rem]')) {
                $violations[] = sprintf(
                    "%s: Invalid max-w container found (max-w-9xl / max-w-[90rem] / max-w-[100rem]). Use PageContainer size=\"xl\".",
                    $rel
                );
            }

            if (preg_match('/\b(?:px-4\s+sm:px-6\s+lg:px-8(?:\s+py-[68])?|py-[68]\s+px-4\s+sm:px-6\s+lg:px-8|py-10\s+px-4|p-2\s+sm:p-4)\b/i', $content, $m)) {
                $violations[] = sprintf(
                    "%s: Redundant double outer padding '%s' detected inside AuthenticatedLayout.",
                    $rel,
                    $m[0]
                );
            }
        }

        $this->assertEmpty(
            $violations,
            sprintf("Container standardization violations detected in %d files:\n\n%s", count($violations), implode("\n\n", array_slice($violations, 0, 20)))
        );
    }

    /**
     * Rule 7: Zero ad-hoc viewport heights (min-h-[80vh], style="height: 85vh;").
     */
    public function test_no_adhoc_viewport_height_anomalies(): void
    {
        $files = $this->getVueFiles($this->pagesDir);
        $pattern = '/(?:min-|max-)?h-\[(?:\d+vh|calc\([^\]]+\))\](?!\w)|style="[^"]*(?:min-|max-)?height\s*:\s*(?:8\dvh|calc\([^;"]+\))[^"]*"|height\s*:\s*(?:8\dvh|calc\([^;"]+\))/i';
        $violations = [];

        foreach ($files as $file) {
            $rel = $this->relative($file);
            $lines = file($file);

            foreach ($lines as $lineIndex => $line) {
                if (preg_match($pattern, $line, $matches)) {
                    $violations[] = sprintf(
                        "%s:%d: Ad-hoc viewport height '%s' found. Remove or use .page-fill if Chat.\n  Line: %s",
                        $rel,
                        $lineIndex + 1,
                        $matches[0],
                        trim($line)
                    );
                }
            }
        }

        $this->assertEmpty(
            $violations,
            sprintf("Viewport height anomalies detected in %d file locations:\n\n%s", count($violations), implode("\n\n", array_slice($violations, 0, 20)))
        );
    }

    /**
     * Rule 8: Core UI Component Contracts and Documentation Existence.
     */
    public function test_core_ui_contracts_and_documentation_exist(): void
    {
        $contracts = [
            'docs/ui-audit.md' => 1000,
            'docs/ui-guidelines.md' => 1000,
            'resources/js/Components/UI/PageContainer.vue' => 100,
            'resources/js/Components/UI/PageHeader.vue' => 100,
            'resources/js/Components/UI/Card.vue' => 100,
            'resources/js/Components/UI/Badge.vue' => 100,
            'resources/js/Components/UI/Button.vue' => 100,
            'resources/js/Components/UI/Modal.vue' => 100,
        ];

        $missingOrIncomplete = [];

        foreach ($contracts as $relPath => $minSize) {
            $fullPath = base_path($relPath);
            if (!file_exists($fullPath)) {
                $missingOrIncomplete[] = "Missing contract file: {$relPath}";
            } else {
                $size = filesize($fullPath);
                if ($size < $minSize) {
                    $missingOrIncomplete[] = "Contract file {$relPath} is incomplete ({$size} bytes < {$minSize} bytes)";
                }
            }
        }

        $this->assertEmpty(
            $missingOrIncomplete,
            "Core UI components or documentation contracts failed:\n" . implode("\n", $missingOrIncomplete)
        );
    }

    /**
     * Normalize path to relative path with forward slashes.
     */
    private function relative(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedBase = rtrim(str_replace('\\', '/', base_path()), '/') . '/';
        return str_replace($normalizedBase, '', $normalizedPath);
    }
}
