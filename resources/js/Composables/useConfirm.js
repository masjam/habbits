import { ref } from 'vue'

const isOpen = ref(false)
const dialogState = ref({
    title: 'Konfirmasi Tindakan',
    message: 'Apakah Anda yakin ingin melanjutkan tindakan ini?',
    confirmText: 'Lanjutkan',
    cancelText: 'Batal',
    type: 'primary', // 'primary' | 'danger' | 'warning'
    resolve: null,
})

export function useConfirm() {
    const confirm = ({
        title = 'Konfirmasi Tindakan',
        message = 'Apakah Anda yakin ingin melanjutkan?',
        confirmText = 'Lanjutkan',
        cancelText = 'Batal',
        type = 'primary',
    } = {}) => {
        return new Promise((resolve) => {
            dialogState.value = {
                title,
                message,
                confirmText,
                cancelText,
                type,
                resolve,
            }
            isOpen.value = true
        })
    }

    const handleConfirm = () => {
        if (dialogState.value.resolve) {
            dialogState.value.resolve(true)
        }
        isOpen.value = false
    }

    const handleCancel = () => {
        if (dialogState.value.resolve) {
            dialogState.value.resolve(false)
        }
        isOpen.value = false
    }

    return {
        isOpen,
        dialogState,
        confirm,
        handleConfirm,
        handleCancel,
    }
}
