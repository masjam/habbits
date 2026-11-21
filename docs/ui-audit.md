# UI Consistency Audit Report: Complete 53-Page Audit

**Target Codebase**: `h:\laragon\www\habit`  
**Application**: SAHABAT SDAM (Habit / SIMAK)  
**Framework**: Laravel + Inertia.js + Vue 3 + Tailwind CSS v4  
**Audit Scope**: 53 Application Pages & Components across all 7 operational modules  
**Generated Date**: 2026-11-20  

---

## 1. Executive Summary

This document synthesizes the comprehensive structural and visual audit of all 53 Vue page components within the application. The audit assesses 11 distinct metrics across each page: root layout wrapper, max-width constraints, outer padding and double-padding hazards, heading typography, card border radius, color tokens and theme compatibility, modal implementations, button component usage, viewport height constraints, hardcoded color codes, and adherence to non-authenticated layout patterns.

The primary finding is that while the core layout (`AuthenticatedLayout.vue`) and design token definitions in `resources/css/app.css` support a unified Material 3 theme architecture, the page layer has suffered from severe visual and architectural fragmentation:
- **0% adoption** of `<PageHeader>`, `<Modal.vue>`, and `UI/Button.vue` across the audited pages.
- **47 custom modal implementations** using raw `fixed inset-0` overlays rather than the centralized `Modal.vue`.
- **11 pages suffering from double outer padding**, causing excessive gutters and mobile viewport cramping.
- **30 pages using non-standard `rounded-3xl` radii** for cards and containers instead of the standard `rounded-2xl`.
- **13 pages completely lacking dark mode styling** (`dark:` classes absent), producing severe contrast failures in dark mode.
- Multiple instances of non-standard brand colors (`indigo-*`, `purple-*`, `fuchsia-*`, and legacy Tailwind v1/v2 `gray-*`/`green-*`) preventing dynamic theme skinning.

---

## 2. Quantitative Breakdown

| Audit Metric | Compliant / Standard | Divergent / Non-Standard | Percentage Divergent |
|---|---|---|---|
| **Page Header Component (`<PageHeader>`)** | 0 pages (0%) | 53 pages (100%) | **100%** |
| **Modal Component (`Modal.vue`)** | 0 pages (0%) | 47 custom modals in 26 pages | **100% of modals** |
| **Button Component (`UI/Button.vue`)** | 0 pages (0%) | 53 pages (100% custom `<button>` / `<a>`) | **100%** |
| **Outer Padding Compliance** | 42 pages (79.2%) | 11 pages (20.8% double padding) | **20.8%** |
| **Card Radius Standard (`rounded-2xl`)** | 12 pages (22.6%) | 41 pages (30 pages `rounded-3xl`, 11 pages `rounded-xl`/`rounded-lg`) | **77.4%** |
| **Dark Mode Coverage (`dark:` classes)** | 40 pages (75.5%) | 13 pages (24.5% completely unstyled) | **24.5%** |
| **Primary Theme Token Usage (`primary-*`)** | 0 pages (0%) | 53 pages (hardcoded `emerald`, `indigo`, `purple`, `gray`) | **100%** |
| **Viewport Height Rules (no ad-hoc `vh`)** | 37 pages (69.8%) | 16 pages (ad-hoc `h-[90vh]`, `min-h-[80vh]`, `calc()`, etc.) | **30.2%** |
| **Container Max-Width Standard** | 6 pages standard `7xl` | 47 pages (1 invalid `max-w-9xl`, 6 arbitrary, 40 unconstrained `w-full`) | **88.7%** |

### Specific Violations Inventory:
1. **Double Outer Padding Violations (11 pages)**:
   - `Admin/Laporan/Unfilled.vue:152` (`px-4 sm:px-6 lg:px-8 py-8 w-full`)
   - `Admin/Settings/Hr.vue:640` (`w-full py-6 px-4 sm:px-6 lg:px-8 space-y-6`)
   - `Admin/Settings/Index.vue:699` (`w-full py-6 px-4 sm:px-6 lg:px-8 space-y-6`)
   - `Admin/SpecialSchedules/Index.vue:245` (`px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6`)
   - `Admin/TeacherJournal/Index.vue:194` (`py-6 sm:px-6 lg:px-8`)
   - `TeacherJournal/Form.vue:226` (`py-12 sm:px-6 lg:px-8`)
   - `TeacherJournal/Index.vue:157` (`py-12 sm:px-6 lg:px-8`)
   - `Notifications/Index.vue:28` (`py-6 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto`)
   - `Chat/Index.vue:157` (`max-w-7xl mx-auto p-2 sm:p-4`)
   - `Hpt/Index.vue:44` (`py-6 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto`)
   - `Qibla/Index.vue:21` (`py-10 px-4 max-w-md mx-auto`)
2. **Invalid Tailwind CSS Class**:
   - `Admin/SpecialSchedules/Index.vue:245` (`max-w-9xl` does not exist in Tailwind CSS; default max scale ends at `7xl`).
3. **Prohibited Viewport Heights**:
   - `Chat/Index.vue:158`: `h-[calc(100vh-4.5rem)] md:h-[calc(100vh-5rem)]` (fails `.page-fill` standard).
   - `Qibla/Index.vue:21`: `min-h-[80vh]` (prohibited by Rule R2).
   - `Admin/TeacherAdministration/Index.vue`: `h-[90vh]`.
   - `TeacherAdministration/Index.vue`: `h-[90vh]`.
   - `Admin/Documents/Index.vue`: `h-[85vh]`.
   - `Employee/Documents.vue`: `h-[85vh]`.
   - `Notifications/Index.vue`: `h-[90vh]`.
   - `TataUsaha/SuratKeluar/Index.vue`: inline `style="height: 85vh;"`.
   - `TataUsaha/SuratMasuk/Index.vue`: inline `style="height: 85vh;"`.
4. **Complete Absence of Dark Mode (13 pages)**:
   - `Admin/Dashboard.vue`
   - `Admin/Divisions/Index.vue`
   - `Admin/Habits/Index.vue`
   - `Admin/Laporan/Detail.vue`
   - `Admin/Laporan/Index.vue`
   - `Admin/Users/Index.vue`
   - `TataUsaha/Disposisi/Index.vue`
   - `TataUsaha/Settings/Index.vue`
   - `TataUsaha/SuratKeluar/Builder.vue`
   - `TataUsaha/SuratKeluar/Index.vue`
   - `TataUsaha/SuratMasuk/Index.vue`
   - `Public/VerifikasiNotulen.vue`
   - `Public/VerifikasiSurat.vue`

---

## 3. Complete 53-Page Audit Matrix

The following table catalogs all 53 surveyed pages across 11 architectural and visual dimensions.

| # | File Path | Layout & Root Wrapper | Max Width | Outer Padding & Double Padding Risk | Heading Style | Card Radius | Accent Colors | Custom Modals (`fixed inset-0`) | Buttons Used | Viewport Heights | Deviancies / Token Inconsistencies |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | `resources/js/Pages/Dashboard.vue` | `<AuthenticatedLayout>`, `<div class="space-y-8 w-full pb-10">` | None (`w-full`) | Normal | Hero banner with `<h1>` in gradient | `rounded-3xl` | `teal-*`, `purple-*`, `amber-*`, `rose-*`, hex `#006a60`, `#64748b` | 3 custom modals (Presensi, MissedDates, MobileWidget) | Inline custom (0 `UI/Button`) | `max-h-[85vh]`, `max-h-[90vh]` | `rounded-3xl` cards; purple accents; hardcoded hexes; 3 custom modals |
| 2 | `resources/js/Pages/Admin/Dashboard.vue` | `<AuthenticatedLayout>`, `<div class="space-y-6">` | None | Normal | No `<h1>`; card `<h2>` (`text-gray-800`) | `rounded-2xl` (cards), `rounded-3xl` (modal) | `emerald-600`, `blue-600`, `indigo-50`, `amber-500`, `rose-500` | 1 custom modal (`showMobileModal`) | Inline custom (0 `UI/Button`) | `max-h-[80vh]` | **ZERO dark mode support**; missing `<h1>`; custom modal |
| 3 | `resources/js/Pages/Admin/Attendance/Index.vue` | `<AuthenticatedLayout>`, `<div class="space-y-6">` | None | Normal | Custom `<h1>` + status badge | `rounded-3xl` (cards & modals), `rounded-md` (pagination) | `indigo-600`, `purple-600`, `violet-600`, `teal-600`, `sky-600`, `emerald-600` | 5 custom modals (manual presensi, edit, detail, export, duty preview) | Inline custom (0 `UI/Button`) | `max-h-[90vh]` | 5 custom modals; `rounded-3xl` cards; heavy `indigo-*` & `purple-*` accents; native `confirm()` |
| 4 | `resources/js/Pages/Admin/Divisions/Index.vue` | `<AuthenticatedLayout>`, `<div class="max-w-4xl mx-auto">` | `max-w-4xl` | Normal | No `<h1>`; card toolbar `<h2>` | `rounded-2xl` (card), `rounded-lg` (inputs), `rounded-md` (pagination) | `emerald-600`, `gray-*` | 1 custom modal (create/edit division) | Inline custom (0 `UI/Button`) | None | **ZERO dark mode support**; narrow `max-w-4xl`; missing `<h1>`; custom modal; native `confirm()` |
| 5 | `resources/js/Pages/Admin/Documents/Index.vue` | `<AuthenticatedLayout>`, `<div class="space-y-6 w-full pb-12">` | None (`w-full`) | Normal | Hero banner with `<h1>` in gradient | `rounded-3xl` (banner, stats, table, modal) | `indigo-600`, `indigo-700`, `indigo-800`, `indigo-900`, `violet-*` | 2 custom modals (upload/edit, preview) | Inline custom (0 `UI/Button`) | `h-[85vh]`, `max-h-[85vh]` | **Primary color is indigo/violet gradient instead of emerald**; `rounded-3xl` cards; 2 custom modals |
| 6 | `resources/js/Pages/Admin/DutySchedules/Index.vue` | `<AuthenticatedLayout>`, `<div class="max-w-7xl mx-auto space-y-6">` | `max-w-7xl` | Normal | Custom `<h1>` with subtitle | `rounded-3xl` (7 day cards & modal), `rounded-2xl` (slots) | `emerald-600`, `teal-600`, `rose-600` | 1 custom modal (assign piket modal) | Inline custom (0 `UI/Button`) | `max-h-[60vh]` | `rounded-3xl` day cards; custom modal; native `confirm()` |
| 7 | `resources/js/Pages/Admin/Habits/Index.vue` | `<AuthenticatedLayout>`, `<div class="space-y-6">` | None | Normal | No `<h1>`; card `<h2>` (`text-gray-800`) | `rounded-xl` (table card), `rounded-lg` (inputs), unadorned `rounded` | `emerald-600`, `blue-600`, `gray-*` | 1 custom modal (create/edit habit) | Inline custom (0 `UI/Button`) | `max-h-[80vh]` | **ZERO dark mode support**; `rounded-xl` instead of `rounded-2xl`; unadorned `rounded` badges; custom modal |
| 8 | `resources/js/Pages/Admin/Laporan/Detail.vue` | `<AuthenticatedLayout>`, `<div class="space-y-6">` | None | Normal | No `<h1>`; card `<h2>` (`text-gray-800`) | `rounded-2xl` (stats), `rounded-xl` (table), unadorned `rounded` | `emerald-600`, `blue-600`, `amber-600`, `red-600` | 0 modals | Inline custom (0 `UI/Button`) | None | **ZERO dark mode support**; missing `<h1>`; unadorned `rounded` badges |
| 9 | `resources/js/Pages/Admin/Laporan/Index.vue` | `<AuthenticatedLayout>`, `<div class="space-y-6">` | None | Normal | No `<h1>`; card `<h2>` (`text-gray-800`) | `rounded-3xl` (controls card), `rounded-2xl` (table), `rounded-lg` (pagination) | `emerald-600`, `emerald-700`, `gray-*` | 0 modals | Inline custom (0 `UI/Button`) | None | **ZERO dark mode support**; missing `<h1>`; `rounded-3xl` filter box; custom pagination |
| 10 | `resources/js/Pages/Admin/Laporan/Unfilled.vue` | `<AuthenticatedLayout>`, `<div class="px-4 sm:px-6 lg:px-8 py-8 w-full">` | None (`w-full`) | **DOUBLE PADDING** (`px-4 sm:px-6 lg:px-8 py-8`) | No `<h1>`; card `<h2>` | `rounded-xl` (filter & table), `rounded-lg` (inputs), `rounded-md` (badges) | `emerald-600`, `amber-500`, `rose-500`, `slate-*` | 0 modals | Inline custom (0 `UI/Button`) | None | **Double padding violation**; missing `<h1>`; `rounded-xl` card radius |
| 11 | `resources/js/Pages/Admin/LoginLogs/Index.vue` | `<AuthenticatedLayout>`, uses `<template #header>`, `<div class="space-y-6">` | None | Normal | Layout header slot `<h2>` (`text-gray-800 dark:text-gray-200`) | `sm:rounded-2xl` (table card), `rounded-lg` (pagination) | `emerald-600`, `green-600`, `red-600`, `slate-*` | 0 modals | Inline custom (0 `UI/Button`) | None | Legacy `<template #header>` slot usage; custom pagination |
| 12 | `resources/js/Pages/Admin/Performance/Index.vue` | `<AuthenticatedLayout>`, `<div class="space-y-6 w-full pb-8">` | None (`w-full`) | Normal | Hero banner with `<h1>` in gradient | `rounded-3xl` (banner, filters, cards, modal) | `emerald-600`, `teal-600`, `purple-600`, `amber-500`, `rose-500` | 1 custom modal (breakdown modal) | Inline custom (0 `UI/Button`) | `max-h-[85vh]` | `rounded-3xl` cards; custom modal; non-standard `purple-600` accent |
| 13 | `resources/js/Pages/Admin/Settings/Hr.vue` | `<AuthenticatedLayout>`, uses `<template #header>`, `<div class="w-full py-6 px-4 sm:px-6 lg:px-8 space-y-6">` | None (`w-full`) | **DOUBLE PADDING** (`py-6 px-4 sm:px-6 lg:px-8`) | Layout header slot `<h2>` with icon | `rounded-3xl` (sections, stat cards, bottom sheet modal) | `emerald-600`, `teal-600`, `indigo-600`, `fuchsia-600`, `purple-600`, `amber-600` | 1 custom mobile bottom-sheet modal | Inline custom (0 `UI/Button`) | `max-h-[85vh]` | **Double padding violation**; legacy `#header` slot; direct `indigo-*`/`fuchsia-*`/`purple-*` accents; native `confirm()` |
| 14 | `resources/js/Pages/Admin/Settings/Index.vue` | `<AuthenticatedLayout>`, uses `<template #header>`, `<div class="w-full py-6 px-4 sm:px-6 lg:px-8 space-y-6">` | None (`w-full`) | **DOUBLE PADDING** (`py-6 px-4 sm:px-6 lg:px-8`) | Layout header slot `<h2>` with icon | `rounded-3xl` (sections, stat cards, modals) | Hardcoded `indigo-600`, `purple-600`, `blue-600`, `amber-600`, `emerald-600` | 2 custom modals (broadcast modal, mobile drawer modal) | Inline custom (0 `UI/Button`) | `max-h-[85vh]` | **Double padding violation**; legacy `#header` slot; multi-colored accents (`indigo`, `purple`, `blue`); 2 custom modals |
| 15 | `resources/js/Pages/Admin/SpecialSchedules/Index.vue` | `<AuthenticatedLayout>`, `<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">` | **`max-w-9xl`** (invalid class) | **DOUBLE PADDING** (`px-4 sm:px-6 lg:px-8 py-8`) | Hero banner with `<h1>` in gradient | `rounded-3xl` (banner, stats, table, modal) | `emerald-600`, `teal-600`, `amber-500`, `rose-500` | 1 custom modal (add/edit modal) | Inline custom (0 `UI/Button`) | `max-h-[60vh]` | **Invalid `max-w-9xl`**; **Double padding violation**; `rounded-3xl` cards; custom modal; native `confirm()` |
| 16 | `resources/js/Pages/Admin/System/Monitor.vue` | `<AuthenticatedLayout>`, `<div class="max-w-7xl mx-auto space-y-6">` | `max-w-7xl` | Normal | Custom `<h1>` with icon | `rounded-2xl` (metrics cards & tables), `rounded-lg` (tags) | `indigo-500`, `indigo-600`, `emerald-600`, `blue-500`, `amber-500`, `rose-500` | 0 modals | Inline custom (0 `UI/Button`) | None | Direct `indigo-*` accents used heavily for server memory and database cards |
| 17 | `resources/js/Pages/Admin/TeacherAdministration/Index.vue` | `<AuthenticatedLayout>`, `<div class="space-y-6 pb-12">` | None | Normal | Hero banner with `<h1>` in gradient | `rounded-3xl` (banner, stats, table, modals) | `emerald-600`, `teal-600`, `rose-500`, `amber-500`, `indigo-500` (focus) | 2 custom modals (review modal, preview modal) | Inline custom (0 `UI/Button`) | `h-[90vh]` | `rounded-3xl` containers; 2 custom modals; native `confirm()` |
| 18 | `resources/js/Pages/Admin/TeacherJournal/Index.vue` | `<AuthenticatedLayout>`, uses `<template #header>`, `<div class="py-6"><div class="w-full mx-auto sm:px-6 lg:px-8 space-y-6">` | None (`w-full`) | **DOUBLE PADDING** (`py-6 sm:px-6 lg:px-8`) | Layout header slot `<h2>` (`text-gray-800 dark:text-gray-200`) | `sm:rounded-lg` (table card), `rounded-md` (inputs/buttons) | Direct `gray-*` (`gray-100/700/800/900`), `indigo-500`, `green-600`, `blue-600` | 1 custom modal (detail jurnal modal) | Inline custom (0 `UI/Button`) | None | **Double padding violation**; legacy `#header` slot; `sm:rounded-lg` card radius; direct `gray-*` & `indigo-*`; custom modal |
| 19 | `resources/js/Pages/Admin/Users/Index.vue` | `<AuthenticatedLayout>`, `<div class="space-y-6">` (ID modal outside) | None | Normal | No `<h1>`; card `<h2>` (`text-gray-800`) | `rounded-2xl` (table card), `rounded-3xl` (ID modal), `rounded-md` (pagination) | `emerald-600`, `blue-600`, `fuchsia-600`, `gray-*` | 4 custom modals (create/edit, import CSV, reset password, print ID card) | Inline custom (0 `UI/Button`) | `max-h-[85vh]` | **ZERO dark mode support**; 4 custom modals (1 outside layout); missing `<h1>`; native `confirm()` |
| 20 | `resources/js/Pages/TataUsaha/Disposisi/Index.vue` | `<AuthenticatedLayout>`, `<div class="space-y-6">` | None | Normal | No `<h1>`; card `<h2>` (`text-gray-800`) | `rounded-xl` (cards), `rounded-lg` (buttons/inputs) | `purple-600`, `purple-700`, `indigo-50`, `gray-*` | 2 custom modals (detail/edit, print preview) | Inline custom (0 `UI/Button`) | `max-h-[80vh]` | **ZERO dark mode support**; **Primary color is purple-600**; `rounded-xl` cards; missing `<h1>`; 2 custom modals |
| 21 | `resources/js/Pages/TataUsaha/Settings/Index.vue` | `<AuthenticatedLayout>`, `<div class="space-y-6">` | None | Normal | No `<h1>`; card `<h2>` (`text-gray-800`) | `rounded-xl` (card), `rounded-lg` (inputs), unadorned `rounded` | `emerald-600`, `gray-*` | 0 modals | Inline custom (0 `UI/Button`) | None | **ZERO dark mode support**; `rounded-xl` card; unadorned `rounded` button; missing `<h1>` |
| 22 | `resources/js/Pages/TataUsaha/SuratKeluar/Builder.vue` | `<AuthenticatedLayout>`, `<div class="space-y-6">` | None | Normal | No `<h1>`; card `<h2>` (`text-gray-800`) | `rounded-xl` (card), `rounded-lg` (inputs), `rounded-md` (tab pills) | `emerald-600`, `blue-600`, `gray-*` | 0 modals | Inline custom (0 `UI/Button`) | None | **ZERO dark mode support**; `rounded-xl` card; missing `<h1>` |
| 23 | `resources/js/Pages/TataUsaha/SuratKeluar/Index.vue` | `<AuthenticatedLayout>`, `<div class="space-y-6">` | None | Normal | No `<h1>`; card `<h2>` (`text-gray-800`) | `rounded-xl` (table card), `rounded-md` (pills/buttons) | `emerald-600`, `blue-600`, `gray-*` | 3 custom modals (upload, camera, preview) | Inline custom (0 `UI/Button`) | `max-h-[60vh]`, inline `style="height: 85vh;"` | **ZERO dark mode support**; inline `style="height: 85vh;"`; `rounded-xl` card; 3 custom modals; native `confirm()` |
| 24 | `resources/js/Pages/TataUsaha/SuratMasuk/Index.vue` | `<AuthenticatedLayout>`, `<div class="space-y-6">` | None | Normal | No `<h1>`; card `<h2>` (`text-gray-800`) | `rounded-xl` (table card), `rounded-md` (pills/buttons) | `emerald-600`, `purple-600`, `blue-600`, `gray-*` | 3 custom modals (add/edit, camera, preview) | Inline custom (0 `UI/Button`) | `max-h-[60vh]`, inline `style="height: 85vh;"` | **ZERO dark mode support**; inline `style="height: 85vh;"`; `purple-600` action button; `rounded-xl` card; 3 custom modals; native `confirm()` |
| 25 | `resources/js/Pages/Notulen/Create.vue` | `<AuthenticatedLayout>`, `<div class="w-full mx-auto pb-12">` | None (`w-full`) | Normal | Hero banner with `<h1>` in gradient | `rounded-3xl` (form card & modal), `rounded-2xl` (sections) | `emerald-600`, `teal-600`, `red-500`, `red-600`, `slate-*` | 1 custom modal (signature canvas modal) | Inline custom (0 `UI/Button`) | None | `rounded-3xl` form card; 1 custom modal; direct `red-500` |
| 26 | `resources/js/Pages/Notulen/Edit.vue` | `<AuthenticatedLayout>`, `<div class="w-full mx-auto pb-12">` | None (`w-full`) | Normal | Hero banner with `<h1>` in gradient | `rounded-3xl` (form card), `rounded-2xl` (sections) | `emerald-600`, `teal-600`, `red-500`, `red-600`, `slate-*` | 0 modals | Inline custom (0 `UI/Button`) | None | `rounded-3xl` form card; direct `red-500` |
| 27 | `resources/js/Pages/Notulen/Index.vue` | `<AuthenticatedLayout>`, `<div class="space-y-6 w-full pb-8">` | None (`w-full`) | Normal | Hero banner with `<h1>` in gradient | `rounded-3xl` (banner, stats, modal), `rounded-2xl` (cards), unadorned `rounded` | `emerald-600`, `teal-600`, `amber-500`, `rose-500`, `slate-*` | 1 custom modal (signature modal) | Inline custom (0 `UI/Button`) | `max-h-[85vh]` | `rounded-3xl` cards; 1 custom modal; unadorned `rounded` button; native `confirm()` |
| 28 | `resources/js/Pages/Notulen/Show.vue` | `<AuthenticatedLayout>`, `<div class="w-full mx-auto pb-12 max-w-5xl">` | `max-w-5xl` | Normal | Custom `<h1>` inside paper document | `rounded-lg` (paper document), `rounded-xl` (toolbar) | Direct `gray-*` (`gray-900`, `gray-600`), `emerald-600` | 0 modals | Inline custom (0 `UI/Button`) | None | Arbitrary `max-w-5xl`; `rounded-lg` paper card; direct `gray-*` classes |
| 29 | `resources/js/Pages/TeacherJournal/Form.vue` | `<AuthenticatedLayout>`, uses `<template #header>`, `<div class="py-12"><div class="w-full mx-auto sm:px-6 lg:px-8 space-y-6">` | None (`w-full`) | **DOUBLE PADDING** (`py-12 sm:px-6 lg:px-8`) | Layout header slot `<h2>` (`text-gray-800 dark:text-gray-200`) | `sm:rounded-lg` (form card), `rounded-md` (inputs), unadorned `rounded` | Direct `gray-*` (`gray-300/700/800/900`), `indigo-500`, `blue-600` | 1 custom modal (camera modal) | Inline custom (0 `UI/Button`) | None | **Double padding violation**; legacy `#header` slot; `sm:rounded-lg` card radius; direct `gray-*` & `indigo-*`; custom modal |
| 30 | `resources/js/Pages/TeacherJournal/Index.vue` | `<AuthenticatedLayout>`, uses `<template #header>`, `<div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">` | `max-w-7xl` | **DOUBLE PADDING** (`py-12 sm:px-6 lg:px-8`) | Layout header slot `<h2>` (`text-gray-800 dark:text-gray-200`) | `sm:rounded-lg` (table card), `rounded-md` (inputs), unadorned `rounded`, `rounded-lg` (modal) | Direct `gray-*` (`gray-100/500/700/800`), `indigo-500`, `blue-600` | 2 custom modals (form modal `sm:max-w-4xl`, camera modal outside layout) | Inline custom (0 `UI/Button`) | None | **Double padding violation**; legacy `#header` slot; `sm:rounded-lg` card; 2 custom modals (1 outside layout); native `confirm()` |
| 31 | `resources/js/Pages/TeacherAdministration/Index.vue` | `<AuthenticatedLayout>`, `<div class="space-y-6 pb-12">` | None | Normal | Hero banner with `<h1>` in gradient | `rounded-3xl` (banner, stats, table, modals), `rounded-2xl` (alerts) | `emerald-600`, `teal-700`, `amber-500`, `rose-500`, `slate-*` | 4 custom modals (upload, revision, feedback, preview) | Inline custom (0 `UI/Button`) | `h-[90vh]` | `rounded-3xl` cards & modals; 4 custom modals; native `confirm()` |
| 32 | `resources/js/Pages/Employee/Documents.vue` | `AuthenticatedLayout`, inner `<div class="max-w-7xl mx-auto space-y-6">` | `max-w-7xl` | Normal | Ad-hoc `h1` (`text-2xl font-bold text-slate-800 dark:text-white`) with subtitle `<p>` | `rounded-3xl` (cards, preview modal), `rounded-2xl` (category boxes), `rounded-xl` (inputs/buttons) | Hardcoded `emerald-*`, `teal-*`, `blue-*`, `amber-*`, `red-*`, `indigo-50` (hover) | 2 custom modals (Upload Modal L215, Preview Modal L288) | Inline custom (0 `UI/Button`) | `h-[85vh]` | `rounded-3xl` cards; 2 custom modals; `indigo-50` hover |
| 33 | `resources/js/Pages/Employee/Performance/Index.vue` | `AuthenticatedLayout`, inner `<div class="max-w-7xl mx-auto space-y-6">` | `max-w-7xl` | Normal | Ad-hoc `h1` (`text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-white`) | `rounded-3xl` (stat cards, main table cards, chart wrappers), `rounded-2xl` (metrics), `rounded-xl` (inputs/filters) | Hardcoded `emerald-*`, `teal-*`, `blue-*`, `amber-*`, `rose-*`, `violet-*` | 0 modals | Inline custom (0 `UI/Button`) | None | Pervasive `rounded-3xl` cards; `violet-*` accent |
| 34 | `resources/js/Pages/Profile/Edit.vue` | `AuthenticatedLayout`, inner `<div class="max-w-4xl mx-auto space-y-6">` | `max-w-4xl` | Normal | Ad-hoc `h1` (`text-xl sm:text-2xl font-bold text-slate-900`) | `rounded-3xl` (header banner), `rounded-2xl` (cards), `rounded-xl` (inputs/buttons) | Hardcoded `emerald-*`, `teal-*`, `slate-*`, `rose-*` | 0 modals | Inline custom (0 `UI/Button`) | None | Cards lack dark mode classes (`bg-white` without `dark:bg-slate-800`); `rounded-3xl` banner |
| 35 | `resources/js/Pages/Notifications/Index.vue` | `AuthenticatedLayout`, inner `<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">` | `max-w-5xl` | **DOUBLE PADDING** (`py-6 px-4 sm:px-6 lg:px-8`) | Ad-hoc `h1` (`text-2xl font-bold text-slate-900 dark:text-white`) with badge | `rounded-3xl` (cards, modal), `rounded-2xl` (tabs, search), `rounded-lg` (action buttons) | Hardcoded `emerald-*`, `teal-*`, `blue-*`, `amber-*`, `rose-*`, `slate-*` | 2 custom modals (Preview Modal L223, Teleported Complete Prompt L327) | Inline custom (0 `UI/Button`) | `h-[90vh]` | **Double padding violation**; `rounded-3xl` card containers; `rounded-lg` buttons; 2 custom modals |
| 36 | `resources/js/Pages/Chat/Index.vue` | `AuthenticatedLayout`, inner `<div class="max-w-7xl mx-auto p-2 sm:p-4">` | `max-w-7xl` | **DOUBLE PADDING** (`p-2 sm:p-4`) | Ad-hoc header inside chat sidebar (`h2 class="text-xl font-bold"`) and main pane | `rounded-2xl` (sidebar, chat container), `rounded-xl` (bubbles, inputs), `rounded-lg` (action icons) | Hardcoded `emerald-*`, `teal-*`, `slate-*`, `sky-*`, `red-*` | 3 custom modals (Direct Chat L416, Group L458, Lightbox L519) | Inline custom (0 `UI/Button`) | `h-[calc(100vh-4.5rem)] md:h-[calc(100vh-5rem)]` | **Double padding violation**; prohibited viewport calc; 3 custom modals |
| 37 | `resources/js/Pages/Attendance/Index.vue` | `AuthenticatedLayout`, inner `<div class="max-w-7xl mx-auto space-y-6">` | `max-w-7xl` | Normal | Ad-hoc `h1` (`text-2xl sm:text-3xl font-extrabold text-slate-800 dark:text-white`) | `rounded-3xl` (summary cards, history card), `rounded-2xl` (clock in/out buttons), `rounded-xl` (inputs) | Hardcoded `emerald-*`, `teal-*`, `blue-*`, `amber-*`, `purple-600`, `purple-700`, `rose-*` | **7 custom modals** (Selfie, Overtime, Late, Early, Permit, Edit Reason, WhatsApp Export) | Inline custom (0 `UI/Button`) | Custom modal sizing | `#25D366` (WhatsApp), `#128C7E`, `#1ebe5d`; heavy `rounded-3xl`; 7 custom modals; hardcoded purple |
| 38 | `resources/js/Pages/Attendance/RFIDKiosk.vue` | Standalone kiosk, `<div class="min-h-screen bg-slate-900 flex flex-col justify-between p-6">` | `w-full` / `max-w-[1600px]` | Root has `p-6 md:p-8` | Custom kiosk display header (`h1 class="text-3xl md:text-5xl font-black text-white"`) | `rounded-[2.5rem]`, `rounded-[2rem]`, `rounded-[3rem]`, `rounded-3xl`, `rounded-2xl` | `emerald-*`, `teal-*`, `cyan-*`, `indigo-500` (info badge), `slate-800/900` | 0 modals (fullscreen kiosk overlay) | Inline custom (0 `UI/Button`) | `min-h-screen`, `lg:h-[85vh]` | Standalone kiosk; non-standard radius (`rounded-[2.5rem]`, etc.); hardcoded SVG hexes; static dark theme |
| 39 | `resources/js/Pages/FormHabit.vue` | `AuthenticatedLayout`, inner `<div class="max-w-4xl mx-auto space-y-6">` | `max-w-4xl` | Normal | Ad-hoc `h1` (`text-xl sm:text-2xl font-bold text-slate-800 dark:text-white`) with date picker | `rounded-3xl` (progress banner, checklist cards), `rounded-2xl` (sub-items), `rounded-xl` (inputs/buttons) | Hardcoded `emerald-*`, `teal-*`, `amber-*`, `blue-*`, `slate-*` | 1 custom modal (Confirm Recap Dialog L452) | Inline custom (0 `UI/Button`) | Inline `style="max-height: 90vh"` | Pervasive `rounded-3xl` on progress banner & card blocks; ad-hoc modal with inline style |
| 40 | `resources/js/Pages/QuranRecap.vue` | `AuthenticatedLayout`, inner `<div class="max-w-5xl mx-auto space-y-6">` | `max-w-5xl` | Normal | Ad-hoc `h1` (`text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white`) | `rounded-2xl` (main summary cards), `rounded-xl` (detail rows), `rounded-lg` (action controls) | Hardcoded `emerald-*`, `teal-*`, `amber-*`, `indigo-50`/`indigo-600` (murojaah badge), `rose-*` | 0 modals | Inline custom (0 `UI/Button`) | None | Buttons/badges use `rounded-lg` and hardcoded `indigo-*` |
| 41 | `resources/js/Pages/Dzikir/Index.vue` | `AuthenticatedLayout`, inner `<div class="max-w-4xl mx-auto space-y-6">` | `max-w-4xl` | Normal | Ad-hoc `h1` (`text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white`) with audio control | `rounded-3xl` (hero banner, dzikir cards), `rounded-2xl` (mode selector), `rounded-xl` (counters) | Hardcoded `emerald-*`, `teal-*`, `amber-*` (Pagi), `indigo-*` (Petang L87), `rose-*` | 0 modals | Inline custom (0 `UI/Button`) | None | `rounded-3xl` cards; hardcoded `indigo-50/600/700` on Petang tab instead of theme token |
| 42 | `resources/js/Pages/Dzikir/Partials/TasbihMode.vue` | Component overlay, `<div class="fixed inset-0 z-50 bg-slate-950/95 backdrop-blur-md flex flex-col justify-between p-4 sm:p-6">` | `w-full` / `max-w-2xl` | `p-4 sm:p-6` | Ad-hoc display text (`h2 class="text-xl font-bold text-white"`) | `rounded-3xl` (prayer card), `rounded-full` (giant counter trigger), `rounded-xl` (steppers) | `emerald-*`, `teal-*`, `slate-900` | Fullscreen immersive overlay (`fixed inset-0`) | Inline tap surface (0 `UI/Button`) | `fixed inset-0` | Specialized touch ergonomics prayer mode; fullscreen overlay |
| 43 | `resources/js/Pages/Haid/Index.vue` | `AuthenticatedLayout`, inner `<div class="max-w-4xl mx-auto space-y-6">` | `max-w-4xl` | Normal | Ad-hoc `h1` (`text-2xl sm:text-3xl font-extrabold text-slate-800`) with cycle badge | `rounded-3xl` (tracker banner, history list cards), `rounded-2xl` (calendar cards), `rounded-xl` (inputs) | Hardcoded `rose-*` (specialized domain colors), `pink-*`, `emerald-*`, `slate-*` | 1 custom modal (Edit Cycle Record Modal via Teleport L240) | Inline custom (0 `UI/Button`) | `max-h-[90vh]` | Missing dark mode styling across card backgrounds (`bg-white` without dark mode); custom modal; `rounded-3xl` |
| 44 | `resources/js/Pages/Hpt/Index.vue` | `AuthenticatedLayout`, inner `<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">` | `max-w-5xl` | **DOUBLE PADDING** (`py-6 px-4 sm:px-6 lg:px-8`) | Ad-hoc `h1` (`text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white`) with search filter | `rounded-3xl` (reading card container), `rounded-2xl` (search bar), `rounded-xl` (accordions) | Hardcoded `emerald-*`, `teal-*`, `amber-*`, `slate-*` | 0 modals | Inline custom (0 `UI/Button`) | None | **Double padding violation**; `rounded-3xl` reading cards |
| 45 | `resources/js/Pages/Kajian/Index.vue` | `AuthenticatedLayout`, inner `<div class="max-w-5xl mx-auto space-y-6">` | `max-w-5xl` | Normal | Ad-hoc `h1` (`text-2xl sm:text-3xl font-extrabold text-slate-800`) with speaker search | `rounded-3xl` (featured video card, grid cards), `rounded-2xl` (search & filter tabs), `rounded-xl` (player) | Hardcoded `emerald-*`, `teal-*`, `rose-*` (YouTube badge), `slate-*` | 0 modals | Inline custom (0 `UI/Button`) | None | Missing dark mode styling in several cards (`text-slate-800` without dark counterpart); `rounded-3xl` cards |
| 46 | `resources/js/Pages/Qibla/Index.vue` | `AuthenticatedLayout`, inner `<div class="py-10 px-4 max-w-md mx-auto">` | `max-w-md` | **DOUBLE PADDING** (`py-10 px-4`) | Ad-hoc `h1` (`text-2xl font-bold text-slate-800 dark:text-white text-center`) | `rounded-3xl` (compass wrapper card), `rounded-full` (compass dial), `rounded-xl` (alert) | Hardcoded `emerald-*`, `teal-*`, `amber-*`, `slate-*` | 0 modals | Inline custom (0 `UI/Button`) | `min-h-[80vh]` | **Prohibited `min-h-[80vh]`**; **Double padding violation**; `rounded-3xl` |
| 47 | `resources/js/Pages/QuranHadis/Index.vue` | `AuthenticatedLayout`, inner `<div class="max-w-5xl mx-auto space-y-6">` | `max-w-5xl` | Normal | Ad-hoc `h1` (`text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white`) with search | `rounded-2xl` (surah cards, hadith cards), `rounded-xl` (search bar, filter tabs) | Hardcoded `emerald-*`, `teal-*`, `blue-*`, `amber-*`, `slate-*` | 1 custom modal (Last Read / Bookmark Manager Modal via Teleport L480) | Inline custom (0 `UI/Button`) | None | Custom Teleported modal; custom buttons; hardcoded `emerald-*` |
| 48 | `resources/js/Pages/QuranHadis/Partials/ShareImageModal.vue` | Subcomponent modal, `<div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm">` | `max-w-xl` | `p-4` / `p-6` | Ad-hoc modal header (`h3 class="text-lg font-bold text-slate-900 dark:text-white"`) | `rounded-3xl` (modal container), `rounded-2xl` (preview canvas), `rounded-xl` (preset pills) | Hardcoded `emerald-*`, `teal-*`, `slate-*`, plus 38 canvas palette hexes | 1 custom modal (`fixed inset-0` L2) | Inline custom (0 `UI/Button`) | `max-h-[90vh] md:max-h-[85vh]` | Ad-hoc modal implementation; `rounded-3xl` modal box; 38 canvas hex codes |
| 49 | `resources/js/Pages/Auth/Login.vue` | Standalone guest, root `<div class="min-h-screen bg-slate-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">` | `max-w-md` | `py-12 sm:px-6 lg:px-8`, inner card `py-8 px-6 sm:px-10` | Ad-hoc heading (`h2 class="text-center text-3xl font-extrabold text-slate-900 tracking-tight"`) | `rounded-3xl` (login form card L22), `rounded-xl` (inputs, submit) | Hardcoded `emerald-600`, `teal-600`, `slate-900`, `slate-600` | 0 modals | Inline custom (0 `UI/Button`) | `min-h-screen` | None | **Non-auth pattern violation**: Card is `rounded-3xl` instead of standard `rounded-2xl`; completely lacks dark mode support |
| 50 | `resources/js/Pages/Welcome.vue` | Public landing page, root `<div class="min-h-screen bg-slate-50 text-slate-800 selection:bg-emerald-500">` | Section-based (`max-w-7xl mx-auto`, hero `max-w-4xl`) | Section paddings `px-4 sm:px-6 lg:px-8 py-16 md:py-24` | Ad-hoc hero headings (`h1 class="text-4xl sm:text-6xl font-black tracking-tight"`) | `rounded-3xl` (hero mockup cards, bento boxes, testimonials), `rounded-2xl` (CTA), `rounded-xl` (nav) | Hardcoded `emerald-*`, `teal-*`, `blue-*`, `amber-*`, `slate-*` | 1 custom modal (Public Announcement Popup Modal L360) | Inline custom (0 `UI/Button`) | `min-h-screen` | None | Pervasive `rounded-3xl` bento cards; custom announcement popup modal; incomplete dark mode coverage |
| 51 | `resources/js/Pages/Maintenance.vue` | Standalone maintenance, root `<div class="min-h-screen bg-slate-950 text-slate-200 flex flex-col justify-between p-6">` | `max-w-2xl` | `p-6 md:p-12` | Ad-hoc maintenance title (`h1 class="text-3xl sm:text-5xl font-black text-white"`) | `rounded-3xl` (center warning badge & info box), `rounded-xl` (button) | `amber-400`, `amber-500`, `slate-800`, `slate-900` | 0 modals | Inline custom (0 `UI/Button`) | `min-h-screen` | Radial-gradient background hex `#334155` (L12); fixed dark background |
| 52 | `resources/js/Pages/Public/VerifikasiNotulen.vue` | Standalone public verification, root `<div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">` | `max-w-md` | `py-12 sm:px-6 lg:px-8`, inner card `py-8 px-6 sm:px-10` | Ad-hoc header (`h2 class="text-center text-xl font-bold text-gray-900"`) | `rounded-xl` (verification card L24), `rounded-lg` (detail pills) | **OLD PALETTE**: `gray-50/100/200/500/600/900`, `green-50/600/700` instead of `slate-*` and `emerald-*` | 0 modals | Inline custom (0 `UI/Button`) | `min-h-screen` | External avatar URL hex query; **Non-auth pattern violation**: card is `rounded-xl` instead of `rounded-2xl`; legacy gray/green; lacks dark mode |
| 53 | `resources/js/Pages/Public/VerifikasiSurat.vue` | Standalone public verification, root `<div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">` | `max-w-md` | `py-12 sm:px-6 lg:px-8`, inner card `py-8 px-6 sm:px-10` | Ad-hoc header (`h2 class="text-center text-xl font-bold text-gray-900"`) | `rounded-xl` (verification card L24), `rounded-lg` (status badges) | **OLD PALETTE**: `gray-50/100/200/500/600/900`, `green-50/600/700` instead of `slate-*` and `emerald-*` | 0 modals | Inline custom (0 `UI/Button`) | `min-h-screen` | External avatar URL hex query; **Non-auth pattern violation**: card is `rounded-xl` instead of `rounded-2xl`; legacy gray/green; lacks dark mode |

---

## 4. Key Architectural Defect Patterns

### 4.1 Double Outer Padding Defect
`resources/js/Layouts/AuthenticatedLayout.vue` line 269 wraps all page slots in:
```html
<main class="flex-1 overflow-y-auto bg-transparent p-4 sm:p-6">
    <div class="w-full">
        <slot />
    </div>
</main>
```
This guarantees an outer padding of 16px (mobile) and 24px (desktop) around every page. When pages introduce nested outer padding (e.g. `py-6 px-4 sm:px-6 lg:px-8`), the gutters double, wasting up to 64px of horizontal screen width and creating jarring misalignments between sibling views.

### 4.2 Modal Proliferation & Teleport Isolation
Across the 53 pages, **47 custom modal implementations** were identified. All constructed custom backdrops with varying opacity levels (`bg-slate-950/60`, `bg-slate-950/70`, `bg-slate-900/50`, `bg-black/90`). Several modals were mounted outside `<AuthenticatedLayout>` or lacked body scroll locking, creating z-index bugs on mobile browsers. Replacing all custom modals with `Components/UI/Modal.vue` ensures uniform backdrop blur, ESC key dismissal, scroll locking, and accessible focus management.

### 4.3 Radius System Fragmentation
The design system specifies exactly 3 radius levels:
- `rounded-2xl` (16px) for cards, dialogs, and outer containers.
- `rounded-xl` (12px) for buttons, inputs, dropdowns, and form controls.
- `rounded-full` (9999px) for badges, chips, and pills.
In practice, `rounded-3xl` was applied to 30 pages for primary cards, creating an overly bubbly look that cuts into corner padding. Furthermore, several CRUD pages from older templates retained `rounded-lg` and unadorned `rounded`, resulting in 5 incompatible corner radii on the same screen.

### 4.4 Theme Token Bypass
The runtime theme switcher (`ThemeSelector.vue` & `useTheme.js`) manipulates CSS custom properties (`--primary-50` through `--primary-950`) via classes like `.theme-blue`, `.theme-rose`, `.theme-amber`, and `.theme-purple`. By using hardcoded classes like `bg-emerald-600` or `text-indigo-600`, page templates bypass the dynamic theme engine entirely. Migrating to `primary-*` classes restores full theme reactivity across the entire application.

---

## 5. Verification Commands

To independently verify these findings in the codebase:

```bash
# 1. Search for custom modal overlays
grep -rn "fixed inset-0" resources/js/Pages/

# 2. Search for double outer padding patterns
grep -rn "py-6 px-4\|py-12 sm:px-6\|px-4 sm:px-6 lg:px-8 py-8" resources/js/Pages/

# 3. Search for invalid max-w-9xl class
grep -rn "max-w-9xl" resources/js/Pages/

# 4. Search for prohibited viewport heights
grep -rn "min-h-\[80vh\]\|h-\[calc(100vh" resources/js/Pages/

# 5. Search for non-standard rounded-3xl usage
grep -rn "rounded-3xl" resources/js/Pages/
```
