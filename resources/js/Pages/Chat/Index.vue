<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import axios from 'axios'
import dayjs from 'dayjs'
import 'dayjs/locale/id'
import relativeTime from 'dayjs/plugin/relativeTime'

dayjs.extend(relativeTime)
dayjs.locale('id')

const props = defineProps({
    conversations: {
        type: Array,
        default: () => []
    },
    activeConversation: {
        type: Object,
        default: null
    },
    initialMessages: {
        type: Array,
        default: () => []
    },
    contacts: {
        type: Array,
        default: () => []
    },
    divisions: {
        type: Array,
        default: () => []
    }
})

const page = usePage()
const authUser = computed(() => page.props.auth?.user || {})

// ─── State ───────────────────────────────────────────────────────────────────
const conversationList = ref([...props.conversations])
const activeChat = ref(props.activeConversation ? { ...props.activeConversation } : null)
const messages = ref([...props.initialMessages])
const searchQuery = ref('')
const activeTab = ref('all') // 'all' | 'direct' | 'group'

// Message compose state
const messageText = ref('')
const selectedFile = ref(null)
const filePreview = ref(null)
const isSending = ref(false)
const fileInputRef = ref(null)
const messagesContainer = ref(null)

// Modals
const showNewChatModal = ref(false)
const showCreateGroupModal = ref(false)
const showImageLightbox = ref(false)
const lightboxImageUrl = ref('')

// Group creation form
const newGroupName = ref('')
const newGroupDescription = ref('')
const selectedGroupMembers = ref([])
const groupSearchQuery = ref('')
const isCreatingGroup = ref(false)

// Polling timers
let messagePollTimer = null
let conversationPollTimer = null
let isDocumentVisible = true

// ─── Computed ────────────────────────────────────────────────────────────────
const filteredConversations = computed(() => {
    let list = conversationList.value

    if (activeTab.value === 'direct') {
        list = list.filter(c => c.type === 'direct')
    } else if (activeTab.value === 'group') {
        list = list.filter(c => c.type === 'group' || c.type === 'channel')
    }

    if (!searchQuery.value.trim()) {
        return list
    }

    const query = searchQuery.value.toLowerCase()
    return list.filter(c =>
        (c.display_name && c.display_name.toLowerCase().includes(query)) ||
        (c.last_message_snippet && c.last_message_snippet.toLowerCase().includes(query))
    )
})

const filteredContacts = computed(() => {
    const q = searchQuery.value.toLowerCase().trim()
    return props.contacts.filter(contact => {
        if (contact.id === authUser.value.id) return false
        if (!q) return true
        return (
            (contact.name && contact.name.toLowerCase().includes(q)) ||
            (contact.divisi && contact.divisi.toLowerCase().includes(q))
        )
    })
})

const filteredGroupContacts = computed(() => {
    const q = groupSearchQuery.value.toLowerCase().trim()
    return props.contacts.filter(c => {
        if (c.id === authUser.value.id) return false
        if (!q) return true
        return (
            (c.name && c.name.toLowerCase().includes(q)) ||
            (c.divisi && c.divisi.toLowerCase().includes(q))
        )
    })
})

const totalUnread = computed(() => {
    return conversationList.value.reduce((acc, c) => acc + (c.unread_count || 0), 0)
})

// ─── Scrolling ───────────────────────────────────────────────────────────────
const scrollToBottom = (behavior = 'smooth') => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTo({
                top: messagesContainer.value.scrollHeight,
                behavior
            })
        }
    })
}

// ─── Selection & Navigation ──────────────────────────────────────────────────
const selectConversation = async (conv) => {
    if (activeChat.value?.id === conv.id) return

    activeChat.value = conv
    messages.value = []
    messageText.value = ''
    selectedFile.value = null
    filePreview.value = null

    // Update URL shallowly without reload
    window.history.replaceState({}, '', route('chat.index', { conversation: conv.id }))

    try {
        const res = await axios.get(route('chat.messages', conv.id))
        messages.value = res.data.messages || []
        
        // Zero out unread count in UI
        const found = conversationList.value.find(c => c.id === conv.id)
        if (found) {
            found.unread_count = 0
        }
        scrollToBottom('auto')
    } catch (e) {
        console.error('Gagal memuat pesan:', e)
    }
}

const backToList = () => {
    activeChat.value = null
    window.history.replaceState({}, '', route('chat.index'))
}

// ─── Sending Message ─────────────────────────────────────────────────────────
const handleFileSelect = (e) => {
    const file = e.target.files[0]
    if (!file) return

    if (file.size > 5 * 1024 * 1024) {
        alert('Ukuran file maksimal adalah 5MB.')
        e.target.value = ''
        return
    }

    selectedFile.value = file

    if (file.type.startsWith('image/')) {
        const reader = new FileReader()
        reader.onload = (ev) => {
            filePreview.value = ev.target.result
        }
        reader.readAsDataURL(file)
    } else {
        filePreview.value = null
    }
}

const removeSelectedFile = () => {
    selectedFile.value = null
    filePreview.value = null
    if (fileInputRef.value) {
        fileInputRef.value.value = ''
    }
}

const sendMessage = async () => {
    if (!activeChat.value) return
    const text = messageText.value.trim()
    if (!text && !selectedFile.value) return
    if (isSending.value) return

    isSending.value = true
    const formData = new FormData()
    if (text) formData.append('message', text)
    if (selectedFile.value) formData.append('attachment', selectedFile.value)

    try {
        const res = await axios.post(route('chat.send', activeChat.value.id), formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })

        if (res.data?.message) {
            messages.value.push(res.data.message)
            messageText.value = ''
            removeSelectedFile()
            scrollToBottom()

            // Update conversation list preview
            const current = conversationList.value.find(c => c.id === activeChat.value.id)
            if (current) {
                current.last_message_snippet = text || (res.data.message.attachment_type === 'image' ? '📷 [Gambar]' : '📎 [Lampiran]')
                current.last_message_time = 'Baru saja'
                // Re-sort list to top
                conversationList.value = [
                    current,
                    ...conversationList.value.filter(c => c.id !== current.id)
                ]
            }
        }
    } catch (err) {
        console.error('Gagal mengirim pesan:', err)
        alert(err.response?.data?.message || 'Gagal mengirim pesan. Silakan coba lagi.')
    } finally {
        isSending.value = false
    }
}

const handleKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault()
        sendMessage()
    }
}

// ─── Contact & Group Modals ──────────────────────────────────────────────────
const startDirectChat = async (contact) => {
    showNewChatModal.value = false
    try {
        const res = await axios.post(route('chat.direct', contact.id))
        if (res.data?.conversation) {
            const conv = res.data.conversation
            // If not in list, add it
            if (!conversationList.value.some(c => c.id === conv.id)) {
                conversationList.value.unshift(conv)
            }
            selectConversation(conv)
        }
    } catch (e) {
        console.error('Gagal membuka chat:', e)
        // Fallback to Inertia visit
        router.post(route('chat.direct', contact.id))
    }
}

const toggleGroupMember = (userId) => {
    const idx = selectedGroupMembers.value.indexOf(userId)
    if (idx > -1) {
        selectedGroupMembers.value.splice(idx, 1)
    } else {
        selectedGroupMembers.value.push(userId)
    }
}

const submitCreateGroup = async () => {
    if (!newGroupName.value.trim() || selectedGroupMembers.value.length === 0) {
        alert('Nama grup dan minimal 1 anggota harus dipilih.')
        return
    }

    isCreatingGroup.value = true
    try {
        const res = await axios.post(route('chat.group.create'), {
            name: newGroupName.value.trim(),
            description: newGroupDescription.value.trim() || null,
            member_ids: selectedGroupMembers.value
        })

        if (res.data?.conversation) {
            conversationList.value.unshift(res.data.conversation)
            selectConversation(res.data.conversation)
            showCreateGroupModal.value = false
            newGroupName.value = ''
            newGroupDescription.value = ''
            selectedGroupMembers.value = []
        }
    } catch (e) {
        console.error('Gagal membuat grup:', e)
        alert('Gagal membuat grup baru. Silakan coba lagi.')
    } finally {
        isCreatingGroup.value = false
    }
}

const openLightbox = (url) => {
    lightboxImageUrl.value = url
    showImageLightbox.value = true
}

const formatFileSize = (bytes) => {
    if (!bytes) return ''
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

// ─── Polling System ──────────────────────────────────────────────────────────
const pollNewMessages = async () => {
    if (!isDocumentVisible || !activeChat.value) return

    const lastId = messages.value.length > 0 ? messages.value[messages.value.length - 1].id : null

    try {
        const url = route('chat.messages', {
            conversation: activeChat.value.id,
            after_id: lastId || undefined
        })
        const res = await axios.get(url)
        const incoming = res.data.messages || []

        if (incoming.length > 0) {
            // Check if user is scrolled near bottom
            let isNearBottom = true
            if (messagesContainer.value) {
                const threshold = 150
                const position = messagesContainer.value.scrollHeight - messagesContainer.value.scrollTop - messagesContainer.value.clientHeight
                isNearBottom = position < threshold
            }

            // Append unique messages
            const existingIds = new Set(messages.value.map(m => m.id))
            const filtered = incoming.filter(m => !existingIds.has(m.id))

            if (filtered.length > 0) {
                messages.value.push(...filtered)
                if (isNearBottom) {
                    scrollToBottom()
                }
            }
        }
    } catch (e) {
        // Silent error on poll
    }
}

const pollConversations = async () => {
    if (!isDocumentVisible) return

    try {
        const res = await axios.get(route('chat.conversations'))
        if (res.data?.conversations) {
            conversationList.value = res.data.conversations
        }
    } catch (e) {
        // Silent error
    }
}

const startPolling = () => {
    stopPolling()
    if (!document.hidden) {
        // Polling pesan setiap 6 detik saat tab aktif
        messagePollTimer = setInterval(pollNewMessages, 6000)
        // Polling daftar percakapan setiap 15 detik saat tab aktif
        conversationPollTimer = setInterval(pollConversations, 15000)
    }
}

const stopPolling = () => {
    if (messagePollTimer) {
        clearInterval(messagePollTimer)
        messagePollTimer = null
    }
    if (conversationPollTimer) {
        clearInterval(conversationPollTimer)
        conversationPollTimer = null
    }
}

const handleVisibilityChange = () => {
    isDocumentVisible = !document.hidden
    if (isDocumentVisible) {
        pollNewMessages()
        pollConversations()
        startPolling()
    } else {
        stopPolling()
    }
}

onMounted(() => {
    scrollToBottom('auto')
    document.addEventListener('visibilitychange', handleVisibilityChange)
    startPolling()
})

onUnmounted(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange)
    stopPolling()
})
</script>

<template>
    <Head title="Chat Pegawai" />

    <AuthenticatedLayout>
        <div class="h-[calc(100vh-6rem)] md:h-[calc(100vh-6.5rem)] flex flex-col w-full">
            
            <!-- Main Chat Box Container -->
            <div class="flex-1 bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 flex overflow-hidden">
                
                <!-- ───────────────────────────────────────────────────────────── -->
                <!-- LEFT SIDEBAR: Conversation List & Actions                    -->
                <!-- ───────────────────────────────────────────────────────────── -->
                <div
                    :class="[
                        'w-full md:w-80 lg:w-96 flex flex-col border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 transition-all',
                        activeChat ? 'hidden md:flex' : 'flex'
                    ]"
                >
                    <!-- Sidebar Header -->
                    <div class="p-3.5 border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/50">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-bold shadow-sm shadow-emerald-500/30">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                </div>
                                <div>
                                    <h1 class="text-base font-bold text-slate-800 dark:text-slate-100 leading-tight">Chat Pegawai</h1>
                                    <p class="text-[11px] text-slate-500">Komunikasi internal &amp; tim</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-1.5">
                                <button
                                    @click="showNewChatModal = true"
                                    title="Mulai Chat Baru"
                                    class="p-1.5 rounded-lg text-slate-600 dark:text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-slate-800 transition"
                                >
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                                <button
                                    @click="showCreateGroupModal = true"
                                    title="Buat Grup Baru"
                                    class="p-1.5 rounded-lg text-slate-600 dark:text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-slate-800 transition"
                                >
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Search Filter -->
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Cari obrolan atau pesan..."
                                class="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            />
                        </div>

                        <!-- Filter Tabs -->
                        <div class="flex items-center gap-1 mt-2.5 text-xs">
                            <button
                                @click="activeTab = 'all'"
                                :class="[
                                    'flex-1 py-1 px-2 rounded-md font-medium transition text-center',
                                    activeTab === 'all'
                                        ? 'bg-emerald-600 text-white shadow-xs'
                                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800'
                                ]"
                            >
                                Semua
                            </button>
                            <button
                                @click="activeTab = 'direct'"
                                :class="[
                                    'flex-1 py-1 px-2 rounded-md font-medium transition text-center',
                                    activeTab === 'direct'
                                        ? 'bg-emerald-600 text-white shadow-xs'
                                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800'
                                ]"
                            >
                                Pribadi
                            </button>
                            <button
                                @click="activeTab = 'group'"
                                :class="[
                                    'flex-1 py-1 px-2 rounded-md font-medium transition text-center',
                                    activeTab === 'group'
                                        ? 'bg-emerald-600 text-white shadow-xs'
                                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 dark:hover:bg-slate-800'
                                ]"
                            >
                                Grup &amp; Kanal
                            </button>
                        </div>
                    </div>

                    <!-- Conversation Scrollable List -->
                    <div class="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/60">
                        <div
                            v-if="filteredConversations.length === 0"
                            class="p-6 text-center text-slate-400 text-xs flex flex-col items-center justify-center h-full"
                        >
                            <svg class="w-10 h-10 mb-2 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            <p>Tidak ada percakapan ditemukan.</p>
                            <button
                                @click="showNewChatModal = true"
                                class="mt-3 px-3 py-1.5 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 text-xs font-semibold rounded-lg hover:bg-emerald-100 transition"
                            >
                                + Mulai Chat Baru
                            </button>
                        </div>

                        <button
                            v-for="conv in filteredConversations"
                            :key="conv.id"
                            @click="selectConversation(conv)"
                            :class="[
                                'w-full text-left p-3 flex items-start gap-3 transition-colors',
                                activeChat?.id === conv.id
                                    ? 'bg-emerald-50/80 dark:bg-emerald-950/30 border-l-4 border-emerald-600'
                                    : 'hover:bg-slate-50 dark:hover:bg-slate-800/50'
                            ]"
                        >
                            <!-- Avatar / Icon -->
                            <div class="relative flex-shrink-0">
                                <template v-if="conv.type === 'direct'">
                                    <img
                                        v-if="conv.display_avatar"
                                        :src="conv.display_avatar"
                                        :alt="conv.display_name"
                                        class="w-10 h-10 rounded-full object-cover border border-slate-200 dark:border-slate-700"
                                    />
                                    <div
                                        v-else
                                        class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 font-bold flex items-center justify-center text-sm border border-emerald-200 dark:border-emerald-800"
                                    >
                                        {{ (conv.display_name || 'U').charAt(0).toUpperCase() }}
                                    </div>
                                </template>
                                <template v-else-if="conv.type === 'channel'">
                                    <div class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900/60 text-indigo-600 dark:text-indigo-300 font-bold flex items-center justify-center text-sm border border-indigo-200 dark:border-indigo-800">
                                        #
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/60 text-amber-600 dark:text-amber-300 font-bold flex items-center justify-center text-sm border border-amber-200 dark:border-amber-800">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </div>
                                </template>

                                <!-- Type badge dot -->
                                <span
                                    v-if="conv.type === 'channel'"
                                    class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full bg-indigo-500 text-white flex items-center justify-center text-[9px] font-bold ring-2 ring-white dark:ring-slate-900"
                                >
                                    📢
                                </span>
                            </div>

                            <!-- Text & Snippet -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-1 mb-0.5">
                                    <h4 class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate">
                                        {{ conv.display_name }}
                                    </h4>
                                    <span v-if="conv.last_message_time" class="text-[10px] text-slate-400 flex-shrink-0">
                                        {{ conv.last_message_time }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                        {{ conv.last_message_snippet || (conv.type === 'channel' ? 'Kanal umum SDAM' : 'Belum ada pesan') }}
                                    </p>
                                    <span
                                        v-if="conv.unread_count > 0"
                                        class="flex-shrink-0 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-rose-500 text-white"
                                    >
                                        {{ conv.unread_count }}
                                    </span>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- ───────────────────────────────────────────────────────────── -->
                <!-- RIGHT PANE: Active Chat & Messages                            -->
                <!-- ───────────────────────────────────────────────────────────── -->
                <div
                    :class="[
                        'flex-1 flex flex-col bg-slate-50/50 dark:bg-slate-950 transition-all',
                        activeChat ? 'flex' : 'hidden md:flex'
                    ]"
                >
                    <template v-if="activeChat">
                        <!-- Chat Header -->
                        <div class="px-4 py-3 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                            <div class="flex items-center gap-3 min-w-0">
                                <!-- Mobile back button -->
                                <button
                                    @click="backToList"
                                    class="md:hidden p-1.5 -ml-1 text-slate-500 hover:text-slate-800 dark:hover:text-slate-200"
                                    title="Kembali ke daftar"
                                >
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                    </svg>
                                </button>

                                <!-- Header Avatar -->
                                <div class="relative flex-shrink-0">
                                    <img
                                        v-if="activeChat.display_avatar"
                                        :src="activeChat.display_avatar"
                                        :alt="activeChat.display_name"
                                        class="w-9 h-9 rounded-full object-cover border border-slate-200 dark:border-slate-700"
                                    />
                                    <div
                                        v-else
                                        :class="[
                                            'w-9 h-9 rounded-full font-bold flex items-center justify-center text-sm',
                                            activeChat.type === 'channel'
                                                ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-300'
                                                : activeChat.type === 'group'
                                                    ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300'
                                                    : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300'
                                        ]"
                                    >
                                        {{ activeChat.type === 'channel' ? '#' : (activeChat.display_name || 'U').charAt(0).toUpperCase() }}
                                    </div>
                                </div>

                                <div class="min-w-0">
                                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate flex items-center gap-2">
                                        <span>{{ activeChat.display_name }}</span>
                                        <span
                                            v-if="activeChat.type === 'channel'"
                                            class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 font-medium"
                                        >
                                            Kanal
                                        </span>
                                        <span
                                            v-else-if="activeChat.type === 'group'"
                                            class="text-[10px] px-1.5 py-0.5 rounded bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800 font-medium"
                                        >
                                            Grup ({{ activeChat.members_count }} anggota)
                                        </span>
                                    </h3>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                        {{ activeChat.description || (activeChat.other_user?.divisi ? 'Divisi ' + activeChat.other_user.divisi : 'Obrolan Pegawai') }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-1 text-slate-400">
                                <span class="text-[11px] text-emerald-600 dark:text-emerald-400 flex items-center gap-1 font-medium bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Terhubung
                                </span>
                            </div>
                        </div>

                        <!-- Messages Scroll Area -->
                        <div
                            ref="messagesContainer"
                            class="flex-1 overflow-y-auto p-4 space-y-3"
                        >
                            <div
                                v-if="messages.length === 0"
                                class="h-full flex flex-col items-center justify-center text-center text-slate-400 text-xs py-10"
                            >
                                <div class="w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-2">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                </div>
                                <p class="font-medium text-slate-600 dark:text-slate-300">Belum ada percakapan</p>
                                <p class="text-[11px] text-slate-400 mt-0.5">Tulis pesan pertama Anda di bawah untuk memulai obrolan.</p>
                            </div>

                            <template v-for="(msg, index) in messages" :key="msg.id">
                                <!-- Date Separator if first or day changed -->
                                <div
                                    v-if="index === 0 || msg.date !== messages[index - 1].date"
                                    class="flex items-center justify-center my-3"
                                >
                                    <span class="px-2.5 py-1 text-[10px] font-semibold text-slate-500 dark:text-slate-400 bg-slate-200/60 dark:bg-slate-800 rounded-full shadow-2xs">
                                        {{ msg.date }}
                                    </span>
                                </div>

                                <!-- Message Bubble -->
                                <div
                                    :class="[
                                        'flex items-end gap-2',
                                        msg.user_id === authUser.id ? 'justify-end' : 'justify-start'
                                    ]"
                                >
                                    <!-- Received message sender avatar (in group/channel) -->
                                    <div
                                        v-if="msg.user_id !== authUser.id && activeChat.type !== 'direct'"
                                        class="flex-shrink-0 mb-1"
                                    >
                                        <img
                                            v-if="msg.sender?.avatar"
                                            :src="msg.sender.avatar"
                                            :alt="msg.sender.name"
                                            class="w-7 h-7 rounded-full object-cover border border-slate-200 dark:border-slate-700"
                                        />
                                        <div
                                            v-else
                                            class="w-7 h-7 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold flex items-center justify-center text-[11px]"
                                        >
                                            {{ (msg.sender?.name || 'U').charAt(0).toUpperCase() }}
                                        </div>
                                    </div>

                                    <div
                                        :class="[
                                            'max-w-[85%] sm:max-w-md md:max-w-lg rounded-2xl p-3 shadow-xs',
                                            msg.user_id === authUser.id
                                                ? 'bg-emerald-600 text-white rounded-br-xs'
                                                : 'bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 border border-slate-200/80 dark:border-slate-800 rounded-bl-xs'
                                        ]"
                                    >
                                        <!-- Sender Name in Group -->
                                        <div
                                            v-if="msg.user_id !== authUser.id && activeChat.type !== 'direct'"
                                            class="text-[11px] font-bold text-emerald-700 dark:text-emerald-400 mb-1 flex items-center gap-1.5"
                                        >
                                            <span>{{ msg.sender?.name }}</span>
                                            <span v-if="msg.sender?.divisi" class="text-[9px] font-normal text-slate-400">({{ msg.sender.divisi }})</span>
                                        </div>

                                        <!-- Image Attachment Preview -->
                                        <div v-if="msg.attachment_url && msg.attachment_type === 'image'" class="mb-2">
                                            <img
                                                :src="msg.attachment_url"
                                                :alt="msg.attachment_name"
                                                @click="openLightbox(msg.attachment_url)"
                                                class="rounded-lg max-h-60 w-auto object-cover cursor-pointer hover:opacity-95 transition border border-black/10 shadow-xs"
                                            />
                                        </div>

                                        <!-- Document/File Attachment -->
                                        <div
                                            v-else-if="msg.attachment_url"
                                            :class="[
                                                'mb-2 p-2 rounded-lg flex items-center gap-2 border text-xs',
                                                msg.user_id === authUser.id
                                                    ? 'bg-emerald-700/60 border-emerald-500/50 text-white'
                                                    : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200'
                                            ]"
                                        >
                                            <div class="p-2 rounded bg-black/10 dark:bg-white/10 flex-shrink-0">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="font-medium truncate">{{ msg.attachment_name }}</p>
                                                <p class="text-[10px] opacity-75">{{ formatFileSize(msg.attachment_size) }}</p>
                                            </div>
                                            <a
                                                :href="msg.attachment_url"
                                                :download="msg.attachment_name"
                                                target="_blank"
                                                class="p-1.5 rounded hover:bg-black/15 transition flex-shrink-0"
                                                title="Unduh Berkas"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                </svg>
                                            </a>
                                        </div>

                                        <!-- Message Text -->
                                        <p v-if="msg.message" class="text-xs sm:text-sm whitespace-pre-wrap break-words leading-relaxed">
                                            {{ msg.message }}
                                        </p>

                                        <!-- Timestamp -->
                                        <div
                                            :class="[
                                                'text-[10px] text-right mt-1 flex items-center justify-end gap-1',
                                                msg.user_id === authUser.id ? 'text-emerald-100' : 'text-slate-400'
                                            ]"
                                        >
                                            <span>{{ msg.created_at }}</span>
                                            <svg v-if="msg.user_id === authUser.id" class="w-3 h-3 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Selected File Preview Bar (Before Sending) -->
                        <div
                            v-if="selectedFile"
                            class="px-4 py-2 bg-emerald-50 dark:bg-emerald-950/40 border-t border-emerald-200 dark:border-emerald-800 flex items-center justify-between text-xs"
                        >
                            <div class="flex items-center gap-2 min-w-0">
                                <img
                                    v-if="filePreview"
                                    :src="filePreview"
                                    class="w-8 h-8 rounded object-cover border border-emerald-300"
                                    alt="Preview"
                                />
                                <div v-else class="p-1.5 rounded bg-emerald-200 dark:bg-emerald-800 text-emerald-800 dark:text-emerald-200">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                </div>
                                <div class="truncate">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100 truncate">{{ selectedFile.name }}</p>
                                    <p class="text-[10px] text-slate-500">{{ formatFileSize(selectedFile.size) }}</p>
                                </div>
                            </div>
                            <button
                                @click="removeSelectedFile"
                                class="p-1 text-slate-400 hover:text-rose-500 rounded-full transition"
                                title="Batal Lampiran"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Message Input Form -->
                        <div class="p-3 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800">
                            <form @submit.prevent="sendMessage" class="flex items-center gap-2">
                                <!-- Attachment Button -->
                                <input
                                    ref="fileInputRef"
                                    type="file"
                                    @change="handleFileSelect"
                                    accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt"
                                    class="hidden"
                                />
                                <button
                                    type="button"
                                    @click="fileInputRef?.click()"
                                    class="p-2 text-slate-500 hover:text-emerald-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition flex-shrink-0"
                                    title="Lampirkan File / Foto"
                                >
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                </button>

                                <!-- Text Input -->
                                <input
                                    v-model="messageText"
                                    @keydown="handleKeyDown"
                                    type="text"
                                    placeholder="Ketik pesan di sini... (Enter untuk kirim)"
                                    class="flex-1 py-2 px-3.5 text-xs sm:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                                />

                                <!-- Send Button -->
                                <button
                                    type="submit"
                                    :disabled="isSending || (!messageText.trim() && !selectedFile)"
                                    class="p-2.5 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed transition shadow-xs flex-shrink-0"
                                    title="Kirim Pesan"
                                >
                                    <svg v-if="!isSending" class="w-4 h-4 transform rotate-90" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                                    </svg>
                                    <svg v-else class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </template>

                    <!-- Empty State When No Chat Selected (Desktop) -->
                    <div
                        v-else
                        class="h-full flex flex-col items-center justify-center p-6 text-center text-slate-400"
                    >
                        <div class="w-16 h-16 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-3 shadow-inner">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-700 dark:text-slate-200">Pilih Obrolan untuk Memulai</h3>
                        <p class="text-xs text-slate-500 max-w-sm mt-1">
                            Pilih salah satu kontak atau grup dari daftar di sebelah kiri, atau buat obrolan baru dengan rekan kerja.
                        </p>
                        <div class="mt-4 flex gap-2">
                            <button
                                @click="showNewChatModal = true"
                                class="px-3.5 py-2 rounded-xl bg-emerald-600 text-white font-medium text-xs hover:bg-emerald-700 transition shadow-sm"
                            >
                                + Mulai Chat Pribadi
                            </button>
                            <button
                                @click="showCreateGroupModal = true"
                                class="px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-medium text-xs hover:bg-slate-200 transition"
                            >
                                Buat Grup Baru
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ───────────────────────────────────────────────────────────────── -->
        <!-- MODAL: Start New Direct Chat                                      -->
        <!-- ───────────────────────────────────────────────────────────────── -->
        <div
            v-if="showNewChatModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
        >
            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-800 w-full max-w-md max-h-[85vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Mulai Chat Pribadi</h3>
                        <p class="text-[11px] text-slate-500">Pilih rekan kerja yang ingin dihubungi</p>
                    </div>
                    <button @click="showNewChatModal = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-3 border-b border-slate-100 dark:border-slate-800">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari nama atau divisi..."
                        class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    />
                </div>

                <div class="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/60 p-2">
                    <div v-if="filteredContacts.length === 0" class="p-6 text-center text-xs text-slate-400">
                        Tidak ada kontak ditemukan.
                    </div>
                    <button
                        v-for="contact in filteredContacts"
                        :key="contact.id"
                        @click="startDirectChat(contact)"
                        class="w-full p-2.5 rounded-xl hover:bg-emerald-50/70 dark:hover:bg-slate-800 flex items-center gap-3 transition text-left"
                    >
                        <img
                            v-if="contact.avatar"
                            :src="contact.avatar"
                            :alt="contact.name"
                            class="w-9 h-9 rounded-full object-cover border border-slate-200"
                        />
                        <div
                            v-else
                            class="w-9 h-9 rounded-full bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 font-bold flex items-center justify-center text-xs"
                        >
                            {{ (contact.name || 'U').charAt(0).toUpperCase() }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate">{{ contact.name }}</h4>
                            <p class="text-[11px] text-slate-500 truncate">{{ contact.divisi || 'Pegawai' }}</p>
                        </div>
                        <svg class="w-4 h-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- ───────────────────────────────────────────────────────────────── -->
        <!-- MODAL: Create New Group Chat                                      -->
        <!-- ───────────────────────────────────────────────────────────────── -->
        <div
            v-if="showCreateGroupModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
        >
            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-800 w-full max-w-md max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">Buat Grup Obrolan</h3>
                        <p class="text-[11px] text-slate-500">Buat ruang diskusi untuk tim atau proyek</p>
                    </div>
                    <button @click="showCreateGroupModal = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-4 space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Nama Grup <span class="text-rose-500">*</span></label>
                        <input
                            v-model="newGroupName"
                            type="text"
                            placeholder="Contoh: Panitia PPDB, Tim Kurikulum"
                            class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Deskripsi (Opsional)</label>
                        <input
                            v-model="newGroupDescription"
                            type="text"
                            placeholder="Tujuan atau topik obrolan grup"
                            class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Pilih Anggota ({{ selectedGroupMembers.length }} dipilih) <span class="text-rose-500">*</span>
                        </label>
                        <input
                            v-model="groupSearchQuery"
                            type="text"
                            placeholder="Cari anggota..."
                            class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 mb-2 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        />
                    </div>
                </div>

                <!-- Member selection list -->
                <div class="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800 px-4 max-h-48">
                    <div
                        v-for="contact in filteredGroupContacts"
                        :key="contact.id"
                        @click="toggleGroupMember(contact.id)"
                        class="py-2 flex items-center justify-between cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 rounded-lg px-2"
                    >
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-7 h-7 rounded-full bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 font-bold flex items-center justify-center text-[10px]">
                                {{ (contact.name || 'U').charAt(0).toUpperCase() }}
                            </div>
                            <div class="truncate">
                                <p class="text-xs font-medium text-slate-800 dark:text-slate-200 truncate">{{ contact.name }}</p>
                                <p class="text-[10px] text-slate-400 truncate">{{ contact.divisi || 'Pegawai' }}</p>
                            </div>
                        </div>
                        <input
                            type="checkbox"
                            :checked="selectedGroupMembers.includes(contact.id)"
                            class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                        />
                    </div>
                </div>

                <div class="p-3 border-t border-slate-200 dark:border-slate-800 flex justify-end gap-2 bg-slate-50/50 dark:bg-slate-900/50">
                    <button
                        @click="showCreateGroupModal = false"
                        type="button"
                        class="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-400 hover:bg-slate-200/60 rounded-lg transition"
                    >
                        Batal
                    </button>
                    <button
                        @click="submitCreateGroup"
                        :disabled="isCreatingGroup || !newGroupName.trim() || selectedGroupMembers.length === 0"
                        type="button"
                        class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed transition shadow-xs"
                    >
                        {{ isCreatingGroup ? 'Membuat...' : 'Buat Grup' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ───────────────────────────────────────────────────────────────── -->
        <!-- LIGHTBOX: Full Image Preview                                      -->
        <!-- ───────────────────────────────────────────────────────────────── -->
        <div
            v-if="showImageLightbox"
            @click="showImageLightbox = false"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm cursor-zoom-out"
        >
            <div class="relative max-w-4xl max-h-[90vh] flex items-center justify-center" @click.stop>
                <img
                    :src="lightboxImageUrl"
                    class="max-w-full max-h-[85vh] rounded-xl shadow-2xl object-contain border border-white/20"
                    alt="Preview"
                />
                <button
                    @click="showImageLightbox = false"
                    class="absolute -top-3 -right-3 w-8 h-8 rounded-full bg-black/70 text-white flex items-center justify-center hover:bg-black transition shadow-md"
                >
                    ✕
                </button>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
