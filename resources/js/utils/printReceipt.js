/**
 * Print an element (thermal receipt) in an isolated hidden iframe so
 * PosLayout headers, fixed modals, and Vue scoped CSS never interfere.
 */
export function printReceiptElement(elementId = 'receipt-print-area') {
    const el = document.getElementById(elementId)
    if (!el) return

    const iframe = document.createElement('iframe')
    iframe.style.position = 'fixed'
    iframe.style.right = '0'
    iframe.style.bottom = '0'
    iframe.style.width = '0'
    iframe.style.height = '0'
    iframe.style.border = '0'
    document.body.appendChild(iframe)

    const doc = iframe.contentWindow?.document
    if (!doc) {
        iframe.remove()
        return
    }

    doc.open()
    doc.write(`<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>Struk Belanja</title>
<style>
    @page { size: auto; margin: 4mm; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 11px;
        line-height: 1.35;
        color: #000;
        width: 100%;
        max-width: 76mm;
        margin: 0 auto;
    }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .uppercase { text-transform: uppercase; }
    .font-black, .font-bold { font-weight: 700; }
    .whitespace-pre-line { white-space: pre-line; }
    .flex { display: flex; justify-content: space-between; gap: 6px; }
    .justify-between { justify-content: space-between; }
    .space-y-0\\.5 > * + * { margin-top: 2px; }
    .space-y-1 > * + * { margin-top: 4px; }
    .space-y-2\\.5 > * + *, .space-y-3 > * + * { margin-top: 8px; }
    .pt-1 { padding-top: 4px; }
    .pt-2 { padding-top: 8px; }
    .pb-2 { padding-bottom: 8px; }
    .py-1 { padding-top: 4px; padding-bottom: 4px; }
    .py-1\\.5 { padding-top: 6px; padding-bottom: 6px; }
    .py-2 { padding-top: 8px; padding-bottom: 8px; }
    .border-t { border-top: 1px dashed #666; }
    .border-b { border-bottom: 1px dashed #666; }
    h3 { font-size: 13px; margin-bottom: 2px; }
</style>
</head>
<body>${el.innerHTML}</body>
</html>`)
    doc.close()

    const cleanup = () => setTimeout(() => iframe.remove(), 500)
    iframe.contentWindow.onafterprint = cleanup
    iframe.contentWindow.focus()
    iframe.contentWindow.print()
    setTimeout(cleanup, 3000)
}
