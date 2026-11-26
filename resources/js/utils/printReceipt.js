/**
 * Print an element (thermal receipt) in an isolated hidden iframe so
 * PosLayout headers, fixed modals, and Vue scoped CSS never interfere.
 * Tuned for 58mm thermal printers (printable width ~48mm, e.g. BP-ECO58D / RP58).
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
    @page { size: 58mm auto; margin: 1.5mm 2mm; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
        width: 100%;
        max-width: 48mm;
        margin: 0 auto;
        padding: 0 1mm 0 0;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 9.5px;
        line-height: 1.3;
        color: #000;
    }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .uppercase { text-transform: uppercase; }
    .font-black, .font-bold { font-weight: 700; }
    .whitespace-pre-line { white-space: pre-line; }
    .flex { display: flex; justify-content: space-between; align-items: flex-start; gap: 4px; }
    .flex > span:first-child { flex: 1 1 auto; min-width: 0; word-break: break-word; }
    .flex > span:last-child { flex: 0 0 auto; text-align: right; white-space: nowrap; }
    .justify-between { justify-content: space-between; }
    .space-y-0\\.5 > * + * { margin-top: 2px; }
    .space-y-1 > * + * { margin-top: 3px; }
    .space-y-2\\.5 > * + *, .space-y-3 > * + * { margin-top: 6px; }
    .pt-1 { padding-top: 3px; }
    .pt-2 { padding-top: 6px; }
    .pb-2 { padding-bottom: 6px; }
    .py-1 { padding-top: 3px; padding-bottom: 3px; }
    .py-1\\.5 { padding-top: 5px; padding-bottom: 5px; }
    .py-2 { padding-top: 6px; padding-bottom: 6px; }
    .border-t { border-top: 1px dashed #333; }
    .border-b { border-bottom: 1px dashed #333; }
    h3 { font-size: 11.5px; margin-bottom: 2px; }
    .text-sm { font-size: 10.5px; }
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
