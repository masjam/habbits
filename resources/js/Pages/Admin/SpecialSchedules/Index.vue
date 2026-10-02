<script setup>
import { ref, nextTick, watch } from 'vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    schedules: { type: Array, default: () => [] },
});

const isModalOpen = ref(false);
const isEditMode = ref(false);

const form = useForm({
    id: null,
    name: '',
    start_date: '',
    end_date: '',
    time_in: '',
    time_out: '',
    late_tolerance: 0,
    latitude: '',
    longitude: '',
    radius: 100,
    is_active: true,
});

const openModal = (schedule = null) => {
    isEditMode.value = !!schedule;
    if (schedule) {
        form.id = schedule.id;
        form.name = schedule.name;
        form.start_date = schedule.start_date.split('T')[0];
        form.end_date = schedule.end_date.split('T')[0];
        form.time_in = schedule.time_in.substring(0, 5);
        form.time_out = schedule.time_out.substring(0, 5);
        form.late_tolerance = schedule.late_tolerance;
        form.latitude = schedule.latitude;
        form.longitude = schedule.longitude;
        form.radius = schedule.radius;
        form.is_active = schedule.is_active;
    } else {
        form.reset();
        form.id = null;
    }
    isModalOpen.value = true;
    nextTick(() => {
        initMap();
    });
};

let mapInstance = null;
let mapMarker = null;
let mapCircle = null;

const initMap = () => {
    const mapEl = document.getElementById('special-schedule-map');
    if (!mapEl) return;

    if (mapInstance) {
        mapInstance.remove();
        mapInstance = null;
    }

    const initialLat = Number(form.latitude) || -7.7956;
    const initialLng = Number(form.longitude) || 110.3695;
    const initialRadius = Number(form.radius) || 100;

    mapInstance = L.map('special-schedule-map', {
        center: [initialLat, initialLng],
        zoom: 17,
        scrollWheelZoom: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(mapInstance);

    const markerIcon = L.divIcon({
        className: 'school-pin-marker',
        html: `
            <div style="background-color: #059669; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 16px rgba(5,150,105,0.65); border: 3px solid white; transform: translate(-50%, -50%); cursor: grab;">
                <svg style="width: 22px; height: 22px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
            </div>
        `,
        iconSize: [40, 40],
        iconAnchor: [0, 0]
    });

    mapMarker = L.marker([initialLat, initialLng], {
        icon: markerIcon,
        draggable: true
    }).addTo(mapInstance);

    mapCircle = L.circle([initialLat, initialLng], {
        radius: initialRadius,
        color: '#059669',
        fillColor: '#10B981',
        fillOpacity: 0.25,
        weight: 2
    }).addTo(mapInstance);

    mapInstance.on('click', (e) => {
        const { lat, lng } = e.latlng;
        updateCoordinates(lat, lng);
    });

    mapMarker.on('dragend', (e) => {
        const pos = e.target.getLatLng();
        updateCoordinates(pos.lat, pos.lng);
    });

    setTimeout(() => {
        mapInstance?.invalidateSize();
    }, 300);
};

const updateCoordinates = (lat, lng) => {
    form.latitude = parseFloat(lat).toFixed(7);
    form.longitude = parseFloat(lng).toFixed(7);

    if (mapMarker) mapMarker.setLatLng([lat, lng]);
    if (mapCircle) mapCircle.setLatLng([lat, lng]);
};

watch(() => form.radius, (newRadius) => {
    if (mapCircle && newRadius) {
        mapCircle.setRadius(Number(newRadius));
    }
});

const handleManualCoords = () => {
    const lat = Number(form.latitude);
    const lng = Number(form.longitude);
    if (!isNaN(lat) && !isNaN(lng)) {
        if (mapMarker) mapMarker.setLatLng([lat, lng]);
        if (mapCircle) mapCircle.setLatLng([lat, lng]);
        if (mapInstance) mapInstance.panTo([lat, lng]);
    }
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
};

const submit = () => {
    if (isEditMode.value) {
        form.put(route('admin.special-schedules.update', form.id), {
            onSuccess: () => {
                closeModal();
            },
        });
    } else {
        form.post(route('admin.special-schedules.store'), {
            onSuccess: () => {
                closeModal();
            },
        });
    }
};

const deleteSchedule = (id) => {
    if (confirm('Anda yakin ingin menghapus jadwal khusus ini? Tindakan ini tidak dapat dibatalkan.')) {
        router.delete(route('admin.special-schedules.destroy', id));
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Pengaturan Jadwal Khusus" />

        <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">
            <!-- Header Section -->
            <div class="sm:flex sm:justify-between sm:items-center space-y-4 sm:space-y-0">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-800 dark:text-slate-100 uppercase tracking-tight">
                        Jadwal Khusus
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 font-medium mt-1">
                        Atur jam masuk, pulang, dan lokasi khusus untuk tanggal-tanggal tertentu.
                    </p>
                </div>
                <button
                    @click="openModal()"
                    class="px-5 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm shadow-lg shadow-emerald-500/30 transition-all active:scale-95 flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Jadwal Khusus
                </button>
            </div>

            <!-- Table Section -->
            <div class="bg-sidebar rounded-3xl shadow-xs border border-theme overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-card-subtle text-slate-500 dark:text-slate-400 uppercase tracking-wider text-xs border-b border-subtle">
                            <tr>
                                <th class="px-6 py-4 font-black">Nama Kegiatan</th>
                                <th class="px-6 py-4 font-black">Tanggal</th>
                                <th class="px-6 py-4 font-black">Jam Kerja</th>
                                <th class="px-6 py-4 font-black text-center">Status</th>
                                <th class="px-6 py-4 font-black text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-subtle">
                            <tr v-if="schedules.length === 0">
                                <td colspan="5" class="px-6 py-8 text-center text-slate-500">
                                    Belum ada data jadwal khusus.
                                </td>
                            </tr>
                            <tr
                                v-for="sched in schedules"
                                :key="sched.id"
                                class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors"
                            >
                                <td class="px-6 py-4 font-bold text-slate-800 dark:text-slate-200">
                                    {{ sched.name }}
                                </td>
                                <td class="px-6 py-4 font-medium text-slate-600 dark:text-slate-300">
                                    {{ sched.start_date.split('T')[0] }} s/d {{ sched.end_date.split('T')[0] }}
                                </td>
                                <td class="px-6 py-4 text-sm font-mono">
                                    <span class="text-emerald-600 font-bold">{{ sched.time_in.substring(0, 5) }}</span> - 
                                    <span class="text-rose-600 font-bold">{{ sched.time_out.substring(0, 5) }}</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span
                                        :class="[
                                            'px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider',
                                            sched.is_active
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                        ]"
                                    >
                                        {{ sched.is_active ? 'Aktif' : 'Non-Aktif' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button
                                        @click="openModal(sched)"
                                        class="p-2 mr-2 rounded-xl text-emerald-600 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:hover:bg-emerald-900/60 transition-colors"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button
                                        @click="deleteSchedule(sched.id)"
                                        class="p-2 rounded-xl text-rose-600 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-900/60 transition-colors"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs" @click="closeModal"></div>

            <div class="relative z-10 w-full max-w-2xl bg-sidebar rounded-3xl shadow-2xl border border-theme overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-5 border-b border-subtle flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-black text-slate-800 dark:text-slate-100">
                            {{ isEditMode ? 'Edit Jadwal Khusus' : 'Tambah Jadwal Khusus' }}
                        </h3>
                    </div>
                    <button @click="closeModal" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-card-subtle transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submit" class="p-5 overflow-y-auto space-y-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">Nama Kegiatan</label>
                        <input type="text" v-model="form.name" class="w-full text-sm rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-100" required />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">Tanggal Mulai</label>
                            <input type="date" v-model="form.start_date" class="w-full text-sm rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-100" required />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">Tanggal Selesai</label>
                            <input type="date" v-model="form.end_date" class="w-full text-sm rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-100" required />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">Jam Masuk</label>
                            <input type="time" v-model="form.time_in" class="w-full text-sm rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-100" required />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">Jam Pulang</label>
                            <input type="time" v-model="form.time_out" class="w-full text-sm rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-100" required />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">Toleransi (Mnt)</label>
                            <input type="number" v-model="form.late_tolerance" min="0" class="w-full text-sm rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-100" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">Latitude Lokasi</label>
                            <input type="text" v-model="form.latitude" @input="handleManualCoords" placeholder="Contoh: -7.7956" class="w-full text-sm rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-100" />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">Longitude Lokasi</label>
                            <input type="text" v-model="form.longitude" @input="handleManualCoords" placeholder="Contoh: 110.3695" class="w-full text-sm rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-100" />
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300">Radius Lokasi (Meter)</label>
                        <input type="number" v-model="form.radius" class="w-full text-sm rounded-xl border border-theme bg-card-subtle px-3 py-2 text-slate-800 dark:text-slate-100" />
                        <p class="text-[11px] text-slate-400">Kosongkan latitude/longitude jika tidak menggunakan batas lokasi khusus.</p>
                    </div>

                    <!-- Peta Pemilihan Lokasi -->
                    <div class="space-y-1 mt-4">
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-2">Pilih Lokasi di Peta</label>
                        <div id="special-schedule-map" class="w-full h-64 rounded-xl border border-slate-300 shadow-inner overflow-hidden z-10"></div>
                        <p class="text-[11px] text-slate-500 mt-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Klik pada peta atau geser pin untuk menentukan koordinat secara otomatis.
                        </p>
                    </div>

                    <div class="flex items-center mt-2">
                        <input type="checkbox" id="is_active" v-model="form.is_active" class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500" />
                        <label for="is_active" class="ml-2 text-sm font-bold text-slate-700 dark:text-slate-300">Jadwal Aktif</label>
                    </div>

                    <div class="pt-4 border-t border-subtle flex justify-end gap-3 mt-4">
                        <button type="button" @click="closeModal" class="px-5 py-2.5 rounded-xl text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 font-bold text-sm transition-colors">
                            Batal
                        </button>
                        <button type="submit" :disabled="form.processing" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-md transition-all active:scale-95 disabled:opacity-50">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
