const fs = require('fs');

function applyDarkClasses(filePath) {
    let content = fs.readFileSync(filePath, 'utf8');

    // Mappings for dark mode colors
    const replacements = {
        'bg-white': 'bg-white dark:bg-slate-800',
        'bg-slate-50': 'bg-slate-50 dark:bg-slate-800/50',
        'bg-slate-100/50': 'bg-slate-100/50 dark:bg-slate-800/50',
        'border-slate-100': 'border-slate-100 dark:border-slate-700',
        'border-slate-200': 'border-slate-200 dark:border-slate-700',
        'border-slate-300': 'border-slate-300 dark:border-slate-600',
        'text-slate-800': 'text-slate-800 dark:text-slate-200',
        'text-slate-900': 'text-slate-900 dark:text-slate-100',
        'text-slate-700': 'text-slate-700 dark:text-slate-300',
        'text-slate-600': 'text-slate-600 dark:text-slate-400',
        'text-slate-500': 'text-slate-500 dark:text-slate-400',
        'text-slate-400': 'text-slate-400 dark:text-slate-500',
        'hover:bg-slate-50': 'hover:bg-slate-50 dark:hover:bg-slate-700/50',
        'hover:bg-amber-50': 'hover:bg-amber-50 dark:hover:bg-slate-700',
        'bg-amber-100': 'bg-amber-100 dark:bg-amber-900/40',
        'text-amber-600': 'text-amber-600 dark:text-amber-400',
    };

    // To prevent double-applying (e.g. bg-white dark:bg-slate-800 dark:bg-slate-800)
    // we can use regex that ensures the target is not already followed by dark:
    // But a simpler way is to just do a naive replace and fix anything that already has dark:
    
    // Clean up first if there are any existing naive matches (optional, just in case)
    Object.keys(replacements).forEach(key => {
        const value = replacements[key];
        const regex = new RegExp(key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?! dark:)', 'g');
        content = content.replace(regex, value);
    });

    fs.writeFileSync(filePath, content, 'utf8');
}

applyDarkClasses('resources/js/Pages/Dashboard.vue');
applyDarkClasses('resources/js/Pages/FormHabit.vue');
console.log('Done');
