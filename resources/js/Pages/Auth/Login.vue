<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const submit = () => {
    form.post(route('login.attempt'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Login - Sistem Pantauan Habit" />

    <div class="min-h-screen flex flex-col justify-center items-center py-10 px-4 sm:px-0 relative overflow-hidden">
        
        <!-- Background Decorations -->
        <div class="absolute -top-32 -left-32 w-96 h-96 rounded-full bg-emerald-200/40 blur-3xl pointer-events-none" aria-hidden="true" />
        <div class="absolute bottom-0 right-0 w-80 h-80 rounded-full bg-teal-200/30 blur-3xl pointer-events-none" aria-hidden="true" />
        
        <!-- Logo / Branding Area -->
        <div class="mb-8 z-10 flex flex-col items-center">
            <Link :href="route('welcome')" class="flex flex-col items-center gap-4 group text-center">
                <img src="/logo.png" alt="Logo SDAM" class="w-16 h-16 sm:w-20 sm:h-20 object-contain group-hover:scale-105 transition-transform drop-shadow-md" />
                <div class="flex flex-col items-center">
                    <span class="text-3xl font-extrabold text-slate-800 tracking-tight leading-none group-hover:text-emerald-700 transition-colors">Gobit SDAM</span>
                    <span class="text-xs sm:text-sm font-semibold text-slate-500 mt-2 max-w-xs leading-relaxed">Sistem Pantauan Golden Habbits <br>SD Al Mujahidin Wonosari</span>
                </div>
            </Link>
        </div>

        <!-- Login Card -->
        <div class="w-full max-w-md bg-white/90 backdrop-blur-xl border border-slate-100 shadow-2xl shadow-emerald-900/5 rounded-3xl overflow-hidden z-10">
            
            <div class="px-6 sm:px-8 pt-8 pb-6 border-b border-slate-100/60 text-center sm:text-center">
                <h2 class="text-xl sm:text-2xl font-bold text-slate-800 tracking-tight">Selamat Datang</h2>
                <p class="text-sm text-slate-500 mt-1.5">Silakan masuk dengan akun kepegawaian Anda.</p>
            </div>

            <div class="px-6 sm:px-8 py-6 sm:py-8">
                
                <!-- Error Alert -->
                <div v-if="form.errors.email || form.errors.password" class="mb-5 p-3 rounded-xl bg-red-50 border border-red-100 flex items-start gap-3">
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-red-800">Gagal masuk</p>
                        <p class="text-xs text-red-600 mt-0.5">{{ form.errors.email || form.errors.password }}</p>
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    
                    <!-- Email Input -->
                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-700 mb-1.5">Alamat Email</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <input 
                                id="email" 
                                type="email" 
                                v-model="form.email" 
                                required 
                                autofocus 
                                autocomplete="username"
                                class="block w-full pl-11 pr-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:bg-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20 transition-all outline-none"
                                placeholder="nama@sdam.sch.id"
                            />
                        </div>
                    </div>

                    <!-- Password Input -->
                    <div>
                        <label for="password" class="block text-sm font-semibold text-slate-700 mb-1.5">Kata Sandi</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input 
                                id="password" 
                                type="password" 
                                v-model="form.password" 
                                required 
                                autocomplete="current-password"
                                class="block w-full pl-11 pr-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:bg-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20 transition-all outline-none"
                                placeholder="••••••••"
                            />
                        </div>
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="flex items-center justify-between mt-4">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <div class="relative flex items-center justify-center">
                                <input 
                                    type="checkbox" 
                                    v-model="form.remember"
                                    class="peer appearance-none w-4 h-4 border border-slate-300 rounded focus:ring-2 focus:ring-emerald-500 focus:outline-none checked:bg-emerald-600 checked:border-emerald-600 transition-colors"
                                />
                                <svg class="w-3 h-3 text-white absolute pointer-events-none opacity-0 peer-checked:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-slate-600 group-hover:text-slate-800 transition-colors">Ingat Saya</span>
                        </label>

                        <a href="#" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700 transition-colors">
                            Lupa sandi?
                        </a>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4">
                        <button 
                            type="submit" 
                            :disabled="form.processing"
                            class="w-full inline-flex justify-center items-center gap-2 px-6 py-3 bg-emerald-600 border border-transparent rounded-xl font-bold text-white shadow-sm hover:bg-emerald-700 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 active:bg-emerald-800 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                        >
                            <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 w-5 h-5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span v-else>Masuk ke Akun</span>
                            
                            <svg v-if="!form.processing" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </button>
                    </div>

                </form>
            </div>
            
            <div class="px-6 sm:px-8 py-5 bg-slate-50/50 border-t border-slate-100 text-center">
                <p class="text-xs text-slate-500">
                    Gukar baru? <a href="https://wa.link/bh9pkl" class="font-bold text-slate-700 hover:text-emerald-600 transition-colors">Hubungi Waka SDM </a>
                </p>
            </div>
        </div>

    </div>
</template>
