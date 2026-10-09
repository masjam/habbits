<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [\App\Http\Controllers\WelcomeController::class, 'index'])->name('welcome');
Route::get('/verifikasi-surat/{uuid}', [\App\Http\Controllers\PublicVerificationController::class, 'verifySuratKeluar'])->name('public.verifikasi-surat');

// Authentication Routes
Route::get('/login', [\App\Http\Controllers\AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('login.attempt');

// Kiosk RFID
Route::get('/kiosk-rfid', [\App\Http\Controllers\AttendanceRFIDController::class, 'kiosk'])->name('attendance.rfid.kiosk');
Route::post('/kiosk-rfid/scan', [\App\Http\Controllers\AttendanceRFIDController::class, 'processScan'])->name('attendance.rfid.scan');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout'])->name('logout');
    
    // Multi Account Routes
    Route::get('/multi-account/add', [\App\Http\Controllers\MultiAccountController::class, 'addAccount'])->name('multi-account.add');
    Route::get('/multi-account/cancel', [\App\Http\Controllers\MultiAccountController::class, 'cancelAddAccount'])->name('multi-account.cancel');
    Route::post('/multi-account/switch/{id}', [\App\Http\Controllers\MultiAccountController::class, 'switchAccount'])->name('multi-account.switch');
    Route::post('/multi-account/remove/{id}', [\App\Http\Controllers\MultiAccountController::class, 'removeAccount'])->name('multi-account.remove');

    // ─── Route Pegawai (semua role auth) ───────────────────────────────────────
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [\App\Http\Controllers\ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [\App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/habit/form', [\App\Http\Controllers\FormHabitController::class, 'index'])->name('habit.form');
    Route::post('/habit/form', [\App\Http\Controllers\FormHabitController::class, 'store'])->name('habit.form.store');
    
    Route::get('/rekap-quran', [\App\Http\Controllers\QuranRecapController::class, 'index'])->name('quran.recap');
    
    Route::get('/haid', [\App\Http\Controllers\MenstruationController::class, 'index'])->name('haid.index');
    Route::post('/haid/toggle', [\App\Http\Controllers\MenstruationController::class, 'toggle'])->name('haid.toggle');
    Route::put('/haid/{log}', [\App\Http\Controllers\MenstruationController::class, 'update'])->name('haid.update');
    
    Route::get('/kajian', [\App\Http\Controllers\KajianController::class, 'index'])->name('kajian.index');
    Route::get('/quran-hadis', [\App\Http\Controllers\QuranHadisController::class, 'index'])->name('quran.hadis');
    Route::get('/dzikir', [\App\Http\Controllers\DzikirController::class, 'index'])->name('dzikir.index');
    Route::get('/qiblat', function () { return Inertia::render('Qibla/Index'); })->name('qibla.index');

    // Jurnal Harian Guru
    Route::resource('teacher-journals', \App\Http\Controllers\TeacherJournalController::class)->parameters([
        'teacher-journals' => 'teacherJournal'
    ]);

    // Notifikasi / Pesan
    Route::get('/pesan', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/pesan/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
    Route::post('/pesan/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');

    // Presensi Pegawai
    Route::get('/presensi', [\App\Http\Controllers\AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/presensi/check-in', [\App\Http\Controllers\AttendanceController::class, 'checkIn'])->name('attendance.check-in');
    Route::post('/presensi/check-out', [\App\Http\Controllers\AttendanceController::class, 'checkOut'])->name('attendance.check-out');
    Route::post('/presensi/izin', [\App\Http\Controllers\AttendanceController::class, 'storePermit'])->name('attendance.store-permit');
    
    // Kiosk RFID (Bisa diakses public atau admin khusus, ditaruh di dalam auth sbg fallback admin Kiosk, namun lebih baik ditaruh di luar middleware auth jika Kiosk dibiarkan tanpa login. Mari biarkan di auth dulu jika kiosK di-login pakai akun dummy). Tapi wait, Kiosk RFID idealnya terbuka tanpa auth.
    // Mari saya letakkan di luar auth agar bisa dipakai tanpa login.
    

    // Web Push Subscriptions & Test
    Route::post('/push-subscriptions', [\App\Http\Controllers\PushSubscriptionController::class, 'store'])->name('push.store');
    Route::post('/push-subscriptions/destroy', [\App\Http\Controllers\PushSubscriptionController::class, 'destroy'])->name('push.destroy');
    Route::get('/push-subscriptions/status', [\App\Http\Controllers\PushSubscriptionController::class, 'status'])->name('push.status');
    Route::get('/push-subscriptions/vapid-public-key', [\App\Http\Controllers\PushSubscriptionController::class, 'vapidPublicKey'])->name('push.vapid-key');
    Route::post('/push-subscriptions/test', [\App\Http\Controllers\PushSubscriptionController::class, 'sendTest'])->name('push.test');
    Route::post('/push-subscriptions/broadcast', [\App\Http\Controllers\PushSubscriptionController::class, 'sendBroadcast'])->name('push.broadcast')->middleware('role:admin|superadmin');

    // ─── Arsip Kepegawaian (E-Filing) ─────────────────────────────────────────
    Route::get('/employee-documents', [\App\Http\Controllers\EmployeeDocumentController::class, 'index'])->name('employee-documents.index');
    Route::post('/employee-documents', [\App\Http\Controllers\EmployeeDocumentController::class, 'store'])->name('employee-documents.store');
    Route::get('/employee-documents/{employeeDocument}/download', [\App\Http\Controllers\EmployeeDocumentController::class, 'download'])->name('employee-documents.download');
    Route::delete('/employee-documents/{employeeDocument}', [\App\Http\Controllers\EmployeeDocumentController::class, 'destroy'])->name('employee-documents.destroy');
    Route::patch('/employee-documents/{employeeDocument}/verify', [\App\Http\Controllers\EmployeeDocumentController::class, 'verify'])->name('employee-documents.verify')->middleware('role:admin|superadmin');

    // ─── Penilaian Kinerja (KPI) ──────────────────────────────────────────────
    Route::get('/performance-evaluations', [\App\Http\Controllers\PerformanceEvaluationController::class, 'userIndex'])->name('performance.index');

    // ─── Notulen Rapat ──────────────────────────────────────────────
    Route::get('/notulen/create', [\App\Http\Controllers\NotulenController::class, 'create'])->name('notulen.create');
    Route::get('/notulen', [\App\Http\Controllers\NotulenController::class, 'index'])->name('notulen.index');
    Route::post('/notulen', [\App\Http\Controllers\NotulenController::class, 'store'])->name('notulen.store');
    Route::get('/notulen/{notulen}', [\App\Http\Controllers\NotulenController::class, 'show'])->name('notulen.show');
    Route::get('/notulen/{notulen}/edit', [\App\Http\Controllers\NotulenController::class, 'edit'])->name('notulen.edit');
    Route::put('/notulen/{notulen}', [\App\Http\Controllers\NotulenController::class, 'update'])->name('notulen.update');
    Route::post('/notulen/{notulen}/hadir', [\App\Http\Controllers\NotulenController::class, 'markHadir'])->name('notulen.hadir');
    Route::post('/notulen/{notulen}/approve', [\App\Http\Controllers\NotulenController::class, 'approve'])->name('notulen.approve');
    Route::delete('/notulen/{notulen}', [\App\Http\Controllers\NotulenController::class, 'destroy'])->name('notulen.destroy');

    // ─── Admin & Superadmin Routes ─────────────────────────────────────────────
    Route::middleware('role:admin|superadmin')->prefix('admin')->group(function () {
        
        // Arsip & KPI
        Route::get('/arsip-pegawai', [\App\Http\Controllers\EmployeeDocumentController::class, 'manage'])->name('admin.employee-documents.index');
        Route::get('/performance-evaluations', [\App\Http\Controllers\PerformanceEvaluationController::class, 'adminIndex'])->name('admin.performance.index');
        Route::post('/performance-evaluations', [\App\Http\Controllers\PerformanceEvaluationController::class, 'store'])->name('admin.performance.store');

        Route::get('/presensi', [\App\Http\Controllers\Admin\AttendanceReportController::class, 'index'])->name('admin.attendance.index');
        Route::post('/presensi/approval', [\App\Http\Controllers\Admin\AttendanceReportController::class, 'updateApproval'])->name('admin.attendance.approval');
        Route::post('/presensi/location', [\App\Http\Controllers\Admin\AttendanceReportController::class, 'updateLocation'])->name('admin.attendance.location');
        Route::post('/presensi/record', [\App\Http\Controllers\Admin\AttendanceReportController::class, 'updateRecord'])->name('admin.attendance.update-record');
        Route::delete('/presensi/record/{id}', [\App\Http\Controllers\Admin\AttendanceReportController::class, 'deleteRecord'])->name('admin.attendance.delete-record');
        Route::get('/presensi/export', [\App\Http\Controllers\Admin\AttendanceReportController::class, 'export'])->name('admin.attendance.export');

        Route::get('/laporan', [\App\Http\Controllers\Admin\LaporanController::class, 'index'])->name('admin.laporan');
        Route::get('/laporan/export', [\App\Http\Controllers\Admin\LaporanController::class, 'export'])->name('admin.laporan.export');
        Route::post('/laporan/target', [\App\Http\Controllers\Admin\LaporanController::class, 'updateTarget'])->name('admin.laporan.target');
        
        // Letakkan rute statis sebelum wildcard {user}
        Route::get('/laporan/unfilled', [\App\Http\Controllers\Admin\LaporanController::class, 'unfilled'])
            ->name('admin.laporan.unfilled')
            ->middleware('role:superadmin');
            
        Route::get('/laporan/unfilled/export', [\App\Http\Controllers\Admin\LaporanController::class, 'unfilledExport'])
            ->name('admin.laporan.unfilled.export')
            ->middleware('role:superadmin');
            
        Route::get('/laporan/{user}', [\App\Http\Controllers\Admin\LaporanController::class, 'detail'])->name('admin.laporan.detail');

        Route::get('/teacher-journals', [\App\Http\Controllers\Admin\TeacherJournalController::class, 'index'])->name('admin.teacher-journals.index');

        Route::get('/habits', [\App\Http\Controllers\Admin\HabitController::class, 'index'])->name('admin.habits.index');
        Route::post('/habits/reorder', [\App\Http\Controllers\Admin\HabitController::class, 'reorder'])->name('admin.habits.reorder');
        Route::post('/habits', [\App\Http\Controllers\Admin\HabitController::class, 'store'])->name('admin.habits.store');
        Route::put('/habits/{habit}', [\App\Http\Controllers\Admin\HabitController::class, 'update'])->name('admin.habits.update');

        Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('admin.users.index');
        Route::post('/users', [\App\Http\Controllers\Admin\UserController::class, 'store'])->name('admin.users.store');
        Route::put('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('admin.users.update');
        Route::get('/users/template', [\App\Http\Controllers\Admin\UserController::class, 'downloadTemplate'])->name('admin.users.template');
        Route::post('/users/import', [\App\Http\Controllers\Admin\UserController::class, 'import'])->name('admin.users.import');
        Route::post('/users/{user}/reset-password', [\App\Http\Controllers\Admin\UserController::class, 'resetPassword'])->name('admin.users.reset-password');
        Route::delete('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('admin.users.destroy');
        Route::post('/users/{user}/badges', [\App\Http\Controllers\Admin\UserController::class, 'assignBadge'])->name('admin.users.badges.assign');
        Route::delete('/users/{user}/badges', [\App\Http\Controllers\Admin\UserController::class, 'removeBadge'])->name('admin.users.badges.remove');

        // Jadwal Piket & Jam Kerja Harian
        Route::get('/duty-schedules', [\App\Http\Controllers\Admin\DutyScheduleController::class, 'index'])->name('admin.duty-schedules.index');
        Route::post('/duty-schedules', [\App\Http\Controllers\Admin\DutyScheduleController::class, 'store'])->name('admin.duty-schedules.store');
        Route::delete('/duty-schedules/{dutySchedule}', [\App\Http\Controllers\Admin\DutyScheduleController::class, 'destroy'])->name('admin.duty-schedules.destroy');
        Route::post('/duty-schedules/daily-work', [\App\Http\Controllers\Admin\DutyScheduleController::class, 'saveDailyWorkSchedules'])->name('admin.duty-schedules.daily-work');
        
        // Jadwal Khusus
        Route::resource('special-schedules', \App\Http\Controllers\Admin\SpecialScheduleController::class)->except(['create', 'show', 'edit'])->names([
            'index' => 'admin.special-schedules.index',
            'store' => 'admin.special-schedules.store',
            'update' => 'admin.special-schedules.update',
            'destroy' => 'admin.special-schedules.destroy',
        ]);

        Route::resource('divisions', \App\Http\Controllers\Admin\DivisionController::class)->except(['create', 'show', 'edit'])->names([
            'index' => 'admin.divisions.index',
            'store' => 'admin.divisions.store',
            'update' => 'admin.divisions.update',
            'destroy' => 'admin.divisions.destroy',
        ]);

        Route::get('/settings', [\App\Http\Controllers\Admin\SettingController::class, 'index'])->name('admin.settings');
        Route::get('/settings/hr', [\App\Http\Controllers\Admin\SettingController::class, 'hrIndex'])->name('admin.settings.hr');
        Route::post('/settings', [\App\Http\Controllers\Admin\SettingController::class, 'update'])->name('admin.settings.update');
    });

    // ─── Superadmin Only Routes ─────────────────────────────────────────────
    Route::middleware('role:superadmin')->prefix('admin')->group(function () {
        Route::get('/login-logs', [\App\Http\Controllers\Admin\LoginLogController::class, 'index'])->name('admin.login-logs');
        Route::post('/settings/clean-photos', [\App\Http\Controllers\Admin\SettingController::class, 'cleanPhotos'])->name('admin.settings.clean-photos');
    });

    // ─── Maintenance Mode Controls (Khusus Super Admin) ─────────────────────
    Route::post('/admin/maintenance/switch-role', [\App\Http\Controllers\MaintenanceController::class, 'switchRole'])->name('admin.maintenance.switch-role');
    Route::post('/admin/maintenance/toggle', [\App\Http\Controllers\MaintenanceController::class, 'toggle'])->name('admin.maintenance.toggle');

    // Print disposisi (bisa diakses pegawai penerima, pemberi, dan tata usaha)
    Route::get('/tata-usaha/disposisi/{disposisi}/print', [\App\Http\Controllers\TataUsaha\DisposisiController::class, 'print'])->name('tata-usaha.disposisi.print');

    // ─── Tata Usaha & Kepala Sekolah Routes ─────────────────────────────────────────────
    Route::middleware('role:tata_usaha|superadmin|kepala_sekolah')->prefix('tata-usaha')->name('tata-usaha.')->group(function () {
        Route::get('/surat-masuk', [\App\Http\Controllers\TataUsaha\SuratMasukController::class, 'index'])->name('surat-masuk.index');
        Route::post('/surat-masuk', [\App\Http\Controllers\TataUsaha\SuratMasukController::class, 'store'])->name('surat-masuk.store');
        Route::put('/surat-masuk/{suratMasuk}', [\App\Http\Controllers\TataUsaha\SuratMasukController::class, 'update'])->name('surat-masuk.update');
        Route::delete('/surat-masuk/{suratMasuk}', [\App\Http\Controllers\TataUsaha\SuratMasukController::class, 'destroy'])->name('surat-masuk.destroy');

        Route::get('/pengaturan-surat', [\App\Http\Controllers\TataUsaha\SettingController::class, 'index'])->name('settings.index');
        Route::post('/pengaturan-surat', [\App\Http\Controllers\TataUsaha\SettingController::class, 'update'])->name('settings.update');

        Route::get('/surat-keluar/builder', [\App\Http\Controllers\TataUsaha\SuratKeluarController::class, 'builder'])->name('surat-keluar.builder');
        Route::get('/surat-keluar/{suratKeluar}/edit-builder', [\App\Http\Controllers\TataUsaha\SuratKeluarController::class, 'editBuilder'])->name('surat-keluar.edit-builder');
        Route::post('/surat-keluar/{suratKeluar}/update-builder', [\App\Http\Controllers\TataUsaha\SuratKeluarController::class, 'updateBuilder'])->name('surat-keluar.update-builder');
        Route::post('/surat-keluar/{suratKeluar}/approve', [\App\Http\Controllers\TataUsaha\SuratKeluarController::class, 'approve'])->name('surat-keluar.approve');
        Route::post('/surat-keluar/preview', [\App\Http\Controllers\TataUsaha\SuratKeluarController::class, 'preview'])->name('surat-keluar.preview');
        Route::post('/surat-keluar/generate', [\App\Http\Controllers\TataUsaha\SuratKeluarController::class, 'generate'])->name('surat-keluar.generate');
        Route::get('/surat-keluar', [\App\Http\Controllers\TataUsaha\SuratKeluarController::class, 'index'])->name('surat-keluar.index');
        Route::post('/surat-keluar', [\App\Http\Controllers\TataUsaha\SuratKeluarController::class, 'store'])->name('surat-keluar.store');
        Route::delete('/surat-keluar/{suratKeluar}', [\App\Http\Controllers\TataUsaha\SuratKeluarController::class, 'destroy'])->name('surat-keluar.destroy');

        Route::get('/disposisi', [\App\Http\Controllers\TataUsaha\DisposisiController::class, 'index'])->name('disposisi.index');
        Route::post('/disposisi', [\App\Http\Controllers\TataUsaha\DisposisiController::class, 'store'])->name('disposisi.store');
        Route::put('/disposisi/{disposisi}', [\App\Http\Controllers\TataUsaha\DisposisiController::class, 'update'])->name('disposisi.update');
        Route::put('/disposisi/{disposisi}/status', [\App\Http\Controllers\TataUsaha\DisposisiController::class, 'updateStatus'])->name('disposisi.update-status');
        Route::delete('/disposisi/{disposisi}', [\App\Http\Controllers\TataUsaha\DisposisiController::class, 'destroy'])->name('disposisi.destroy');
    });
});

// ─── Halaman Maintenance Publik ─────────────────────────────────────────────
Route::get('/maintenance', [\App\Http\Controllers\MaintenanceController::class, 'index'])->name('maintenance');
