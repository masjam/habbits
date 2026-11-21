# UI Guidelines and Design System Standards

**Application**: SAHABAT SDAM (Habit / SIMAK)  
**Version**: 2.0 (Material 3 Edukatif Modern)  
**Target Codebase**: `h:\laragon\www\habit`  
**Core Technologies**: Laravel + Inertia.js + Vue 3 + Tailwind CSS v4  

---

## 1. Design Tokens and Theme System

### 1.1 Primary Accent Palette (`primary-*`)
The application features a runtime multi-palette theme engine managed by `useTheme.js` and `ThemeSelector.vue`. The theme engine dynamically switches CSS custom properties (`--primary-50` through `--primary-950`) based on theme classes (`default` Teal, `.theme-blue`, `.theme-rose`, `.theme-amber`, `.theme-purple`).

**Rule**: All interactive accents, brand badges, active links, primary buttons, focus rings, and highlighted borders must use `primary-*` classes.
- **Allowed**: `bg-primary-50`, `bg-primary-100`, `bg-primary-500`, `bg-primary-600`, `text-primary-600`, `text-primary-700`, `border-primary-500`, `focus:ring-primary-500`.
- **Strictly Prohibited as Accents**: Hardcoded color families such as `emerald-*`, `teal-*`, `indigo-*`, `blue-*`, `purple-*`, `violet-*`, `fuchsia-*`.

### 1.2 Neutral Color Palette (`slate-*`)
The neutral scale provides visual structure for backgrounds, borders, dividers, subtle text, and surfaces.

**Rule**: All neutral surfaces, text colors, and borders must strictly use `slate-*` tokens.
- Light mode backgrounds: `bg-slate-50` (canvas base), `bg-white` (card surface), `bg-slate-100` (hover / subtle pills).
- Light mode borders: `border-slate-200` (card borders), `border-slate-300` (input borders), `border-slate-100` (subtle dividers).
- Light mode text: `text-slate-900` (headings), `text-slate-700` (body text), `text-slate-500` (subtitles/muted).
- Dark mode counterparts:
  - Canvas base: `dark:bg-slate-950`
  - Card surfaces: `dark:bg-slate-800` (or `dark:bg-slate-900` for modals)
  - Borders: `dark:border-slate-700` (or `dark:border-slate-800`)
  - Text: `dark:text-slate-100` (headings), `dark:text-slate-300` (body), `dark:text-slate-400` (subtitles/muted)
- **Strictly Prohibited**: `gray-*`, `zinc-*`, `neutral-*`, `stone-*`.

### 1.3 Semantic Status Colors
Semantic colors are reserved exclusively for conveying status, feedback, validation, and alerts. They must never be used as general decorative brand accents.

| Semantic Purpose | Light Mode Tokens | Dark Mode Tokens |
|---|---|---|
| **Success** (Active, Completed, Present) | `bg-emerald-50 text-emerald-700 border-emerald-200` | `dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800` |
| **Danger / Error** (Failed, Absent, Delete) | `bg-rose-50 text-rose-700 border-rose-200` | `dark:bg-rose-950/50 dark:text-rose-300 dark:border-rose-800` |
| **Warning** (Pending, Revision, Late) | `bg-amber-50 text-amber-700 border-amber-200` | `dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800` |
| **Info** (Notice, Under Review, Information) | `bg-sky-50 text-sky-700 border-sky-200` | `dark:bg-sky-950/50 dark:text-sky-300 dark:border-sky-800` |

### 1.4 Hardcoded Hex Codes Policy
- **Strictly Prohibited**: Hardcoding raw hex color values (e.g. `#006a60`, `#ffffff`, `#64748b`) in Vue templates or CSS utility declarations.
- **Single Documented Exception**: The official WhatsApp brand color `#25D366` is permitted exclusively for external WhatsApp integration links and icons.
- **Canvas Graphic Exception**: Constants defined in JavaScript data structures specifically for HTML5 Canvas 2D rendering (such as `ShareImageModal.vue` template presets) are permitted as JS constants because HTML5 Canvas cannot evaluate CSS class tokens.

### 1.5 Dark Mode Pairing Requirement
Every background, text, and border class declared in template markup must have a corresponding `dark:` pair. Single-mode classes (e.g., bare `bg-white` or bare `text-slate-900`) cause contrast failures in dark mode.

---

## 2. Corner Radius Standards (Exactly 3 Levels)

The application enforces a strict 3-tier border radius hierarchy to eliminate visual dissonance:

```
┌────────────────────────────────────────────────────────┐
│ Level 1: rounded-2xl (16px / 1rem)                     │
│ Containers, Cards, Modals, Banner Blocks               │
│                                                        │
│  ┌──────────────────────────────────────────────────┐  │
│  │ Level 2: rounded-xl (12px / 0.75rem)             │  │
│  │ Buttons, Form Inputs, Dropdown Menus, Tables     │  │
│  │                                                  │  │
│  │    [ Level 3: rounded-full (9999px) ]            │  │
│  │    Badges, Status Pills, Avatars, Toggles        │  │
│  └──────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────┘
```

1. **Level 1 — `rounded-2xl` (`1rem` / `16px`)**:
   - Used for: `<Card>`, `.card`, `<Modal>`, dialog boxes, hero banners, outer containers.
   - Prohibits: `rounded-3xl` (24px) which was overly inflated and eroded corner padding.
2. **Level 2 — `rounded-xl` (`0.75rem` / `12px`)**:
   - Used for: `<Button>`, `.input` (inputs, selects, textareas), `.icon-btn`, dropdown popovers, `<Pagination>` items, toast notifications, search filter boxes.
   - Prohibits: `rounded-lg` (8px) and `rounded-md` (6px) on interactive elements.
3. **Level 3 — `rounded-full` (`9999px`)**:
   - Used for: `<Badge>`, status pills, avatar chips, toggle switches, circle buttons, floating actions.
   - Prohibits: unadorned `rounded` on status pills.

---

## 3. Container Mappings (`<PageContainer>`)

`AuthenticatedLayout.vue` provides global outer padding `p-4 sm:p-6` around the `<main>` content canvas. To prevent double-padding bugs and ensure consistent horizontal alignments, all pages must wrap their content in `<PageContainer>`.

```html
<PageContainer size="xl">
    <!-- Page Content -->
</PageContainer>
```

### 3.1 Container Size Specifications

| Size Option | Max Width Class | Target Page Types / Modules |
|---|---|---|
| `md` | `max-w-3xl` | Single forms, Profile settings, Change password, Narrow configuration wizards |
| `lg` | `max-w-5xl` | Reading content, Documentation/Panduan, Notulen Detail, Dzikir, HPT, Quran Hadis |
| `xl` *(Default)* | `max-w-7xl` | User & Admin Dashboards, Admin CRUD tables, Laporan, Employee Lists, Teacher Administrations |
| `full` | `w-full` | Chat Pegawai, Live RFID Kiosk, Server Monitor, Visual Letter Builder |

- **Outer Margins & Spacing**: `<PageContainer>` automatically applies `mx-auto w-full space-y-6 pb-8`.
- **Elimination of Double Padding**: Pages using `<PageContainer>` must **never** add `p-*` or `py-*` or `px-*` to their root element.
- **Prohibited**: Invalid classes such as `max-w-9xl`.

---

## 4. Viewport & Height Standards

To prevent vertical clipping and secondary scrollbars on mobile and tablet devices:
1. **Topbar Height Variable**:
   Declared in `:root`:
   ```css
   :root {
       --topbar-h: 4rem;
   }
   ```
2. **Full Page Fill Helper (`.page-fill`)**:
   ```css
   @layer utilities {
       .page-fill {
           height: calc(100vh - var(--topbar-h, 4rem));
       }
   }
   ```
3. **Prohibited Viewport Heights**:
   - Do NOT use arbitrary calculations: `h-[calc(100vh-4.5rem)]`, `h-[calc(100vh-5rem)]`.
   - Do NOT use ad-hoc minimum heights: `min-h-[80vh]`.
   - Only communication panes (such as `Chat/Index.vue`) or full-height display consoles may apply `.page-fill`.

---

## 5. Core UI Components & Usage Guide

### 5.1 `<PageContainer>`
**Path**: `resources/js/Components/UI/PageContainer.vue`  
**Purpose**: Standardizes page width, horizontal auto-centering, and consistent vertical spacing.

```html
<script setup>
import PageContainer from '@/Components/UI/PageContainer.vue'
</script>

<template>
    <AuthenticatedLayout>
        <PageContainer size="xl">
            <!-- Content here -->
        </PageContainer>
    </AuthenticatedLayout>
</template>
```

### 5.2 `<PageHeader>`
**Path**: `resources/js/Components/UI/PageHeader.vue`  
**Purpose**: Replaces all ad-hoc `h1` and `h2` page titles with a consistent typography hierarchy, subtitle support, and status/action slots.

**Props**:
- `title` (String, required): The main page heading.
- `subtitle` (String, optional): Descriptive subtitle text.

**Slots**:
- `#badge`: Slot for `<Badge>` or status pill directly beside the title.
- `#actions`: Slot for primary actions, buttons, date filters, or dropdown triggers.

```html
<script setup>
import PageHeader from '@/Components/UI/PageHeader.vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
</script>

<template>
    <PageHeader 
        title="Daftar Pengguna" 
        subtitle="Kelola akun pegawai, peran akses, dan status verifikasi."
    >
        <template #badge>
            <Badge variant="primary">Aktif</Badge>
        </template>
        <template #actions>
            <Button variant="outline" size="sm">Ekspor CSV</Button>
            <Button variant="primary" size="sm">+ Tambah Pengguna</Button>
        </template>
    </PageHeader>
</template>
```

### 5.3 `<Card>` and `.card` Utility
**Component**: `resources/js/Components/UI/Card.vue`  
**Utility Class**: `.card` (in `resources/css/app.css`)

**Standard Styling**:
`bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xs overflow-hidden`

```html
<!-- Using Card Component -->
<Card :padding="true">
    <template #header>
        <h3 class="font-bold text-slate-800 dark:text-slate-100">Statistik Kehadiran</h3>
    </template>

    <p class="text-slate-600 dark:text-slate-300">Konten kartu...</p>

    <template #footer>
        <span class="text-xs text-slate-400">Diperbarui 5 menit lalu</span>
    </template>
</Card>

<!-- Or using .card utility class -->
<div class="card p-4 sm:p-6">
    <h3 class="font-bold text-slate-800 dark:text-slate-100">Statistik Kehadiran</h3>
    <p class="text-slate-600 dark:text-slate-300">Konten kartu...</p>
</div>
```

### 5.4 `<Badge>`
**Path**: `resources/js/Components/UI/Badge.vue`  
**Props**:
- `variant`: `'primary'` | `'success'` | `'danger'` | `'warning'` | `'info'` | `'neutral'` (default: `'neutral'`)
- `size`: `'sm'` | `'md'` (default: `'sm'`)

```html
<Badge variant="success">Disetujui</Badge>
<Badge variant="warning">Menunggu Verifikasi</Badge>
<Badge variant="danger">Ditolak</Badge>
<Badge variant="primary">Super Admin</Badge>
<Badge variant="neutral">Draft</Badge>
```

### 5.5 `<Button>` and `.icon-btn`
**Component**: `resources/js/Components/UI/Button.vue`  
**Props**:
- `variant`: `'primary'` | `'secondary'` | `'danger'` | `'warning'` | `'outline'` | `'ghost'` (default: `'primary'`)
- `size`: `'sm'` | `'md'` | `'lg'` (default: `'md'`)
- `loading`: Boolean (default: `false`)
- `disabled`: Boolean (default: `false`)

**Utility Class**: `.icon-btn` for square/circular icon triggers (e.g. close buttons, ellipsis menus).

```html
<Button variant="primary" :loading="isSubmitting" @click="save">Simpan Perubahan</Button>
<Button variant="outline" size="sm" @click="cancel">Batal</Button>

<!-- Icon Button -->
<button type="button" class="icon-btn" @click="close">
    <XMarkIcon class="w-5 h-5" />
</button>
```

### 5.6 `<Modal>`
**Path**: `resources/js/Components/UI/Modal.vue`  
**Props**:
- `show`: Boolean (required)
- `maxWidth`: `'sm'` | `'md'` | `'lg'` | `'xl'` | `'2xl'` | `'3xl'` | `'4xl'` (default: `'md'`)
- `closeable`: Boolean (default: `true`)
**Events**:
- `@close`: Emitted when backdrop is clicked or ESC is pressed.

```html
<Modal :show="isModalOpen" maxWidth="lg" @close="isModalOpen = false">
    <div class="p-6">
        <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100 mb-4">Tambah Data</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400">Formulir isian data...</p>
        <div class="mt-6 flex justify-end gap-3">
            <Button variant="outline" @click="isModalOpen = false">Batal</Button>
            <Button variant="primary" @click="submit">Simpan</Button>
        </div>
    </div>
</Modal>
```

### 5.7 Form Controls (`.input`)
Inputs, selects, and textareas must use the centralized `.input` utility class:
```html
<input type="text" class="input" placeholder="Masukkan nama..." />
<select class="input">
    <option value="1">Pilihan 1</option>
</select>
<textarea class="input" rows="4"></textarea>
```

### 5.8 Confirmations (`useConfirm()`)
Replace native `window.confirm()` with the reactive confirmation dialog:
```javascript
import { useConfirm } from '@/Composables/useConfirm'

const { confirm } = useConfirm()

const handleDelete = async (id) => {
    const ok = await confirm({
        title: 'Hapus Data?',
        message: 'Tindakan ini tidak dapat dibatalkan.',
        confirmText: 'Hapus',
        confirmVariant: 'danger',
    })
    if (ok) {
        router.delete(route('users.destroy', id))
    }
}
```

### 5.9 Toasts (`useToast()`)
```javascript
import { useToast } from '@/Composables/useToast'

const toast = useToast()
toast.success('Data berhasil disimpan.')
toast.error('Gagal memproses data.')
```

---

## 6. Non-Authenticated Pages Pattern

Non-authenticated pages (`Auth/Login.vue`, `Public/VerifikasiNotulen.vue`, `Public/VerifikasiSurat.vue`) do not use `AuthenticatedLayout`. They must adhere to the centered card standard:

```html
<div class="min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 selection:bg-primary-500 selection:text-white transition-colors">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <!-- Logo / Brand Header -->
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="card p-6 sm:p-8">
            <!-- Form or Verification Content -->
        </div>
    </div>
</div>
```

- Card radius must be strictly `rounded-2xl` (not `rounded-3xl` or `rounded-xl`).
- Neutral palette must be strictly `slate-*` (not `gray-*`).
- Buttons must use `UI/Button.vue` or `.input`.

---

## 7. The 8-Point "Before Creating a New Page" Checklist

Before introducing a new page or marking a refactored page as complete, verify each of the following 8 criteria:

- [ ] **1. Layout Container**: Is the page wrapped in `<AuthenticatedLayout>` (or standard standalone centered layout for Auth/Public) and utilizing `<PageContainer :size="'md'|'lg'|'xl'|'full'">` without double outer paddings or invalid `max-w-9xl`?
- [ ] **2. Standard Header**: Is the page title rendered via `<PageHeader :title="title" :subtitle="subtitle">` with action buttons placed in the `#actions` slot (0 custom `h1` variations)?
- [ ] **3. Cards & Surfaces**: Are all cards styled with `<Card>` or `.card` (`bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xs`)?
- [ ] **4. Radius Standard**: Does every element strictly follow the 3 levels: `rounded-2xl` for cards/modals, `rounded-xl` for buttons/inputs/dropdowns, and `rounded-full` for badges/pills? (0 `rounded-3xl` / `rounded-lg`).
- [ ] **5. Dynamic Primary Tokens**: Are all primary accents using `primary-*` classes (e.g. `bg-primary-600`, `text-primary-600`), and NOT hardcoded `emerald-*`, `teal-*`, `indigo-*`, `blue-*`, `purple-*`?
- [ ] **6. Neutrals and Dark Mode**: Are all neutrals strictly `slate-*` (no `gray-*` or `zinc-*`) and does EVERY color class have a corresponding `dark:` pair?
- [ ] **7. Modals & Dialogs**: Are modal dialogs rendered via `Components/UI/Modal.vue` (0 custom `fixed inset-0` wrappers) and delete/critical actions using `useConfirm()`?
- [ ] **8. Inputs & Tables**: Are inputs styled with `.input` and tables wrapped in `overflow-x-auto` with `<Pagination>`?
