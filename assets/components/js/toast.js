(function (w, d) {
    const wrapId = 'toast-wrap';
    function ensureWrap() {
      let el = d.getElementById(wrapId);
      if (!el) {
        el = d.createElement('div');
        el.id = wrapId;
        el.className = 'fixed right-4 top-16 z-50 flex w-auto max-w-[92vw] flex-col gap-2';
        d.body.appendChild(el);
      }
      return el;
    }
  
    function clsByType(type) {
      switch (type) {
        case 'success': return 'border-green-200 text-green-700 dark:text-green-400';
        case 'error':   return 'border-red-200 text-red-600 dark:text-red-400';
        case 'warn':    return 'border-yellow-200 text-yellow-700 dark:text-yellow-400';
        default:        return 'border-blue-200 text-blue-700 dark:text-blue-400';
      }
    }
    function iconByType(type) {
      return type==='success'?'✓':type==='error'?'✕':type==='warn'?'!':'ℹ';
    }
  
    function show(message, { title='', type='info', timeout=3500 } = {}) {
      const wrap = ensureWrap();
      const node = d.createElement('div');
      node.role = 'status';
      node.className = [
        // card
        'pointer-events-auto w-80 rounded-xl border bg-white p-3 shadow-lg',
        'dark:border-gray-800 dark:bg-gray-900',
        // color
        clsByType(type),
        // animation (PenguinUI style)
        'transform transition duration-200 ease-out translate-y-2 opacity-0',
      ].join(' ');
  
      node.innerHTML = `
        <div class="flex items-start gap-2">
          <div class="mt-0.5 text-lg">${iconByType(type)}</div>
          <div class="flex-1">
            ${title ? `<p class="font-semibold">${title}</p>` : ''}
            <p class="mt-0.5 text-sm">${message}</p>
          </div>
          <button type="button"
                  class="rounded-md border px-2 py-0.5 text-xs dark:border-gray-700">Tutup</button>
        </div>
      `;
  
      wrap.appendChild(node);
  
      // enter animation
      requestAnimationFrame(() => {
        node.classList.remove('translate-y-2', 'opacity-0');
        node.classList.add('translate-y-0', 'opacity-100');
      });
  
      const remove = () => {
        node.classList.remove('opacity-100', 'translate-y-0');
        node.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => node.remove(), 180);
      };
      node.querySelector('button').addEventListener('click', remove);
      if (timeout > 0) setTimeout(remove, timeout);
  
      return node;
    }
  
    // API global (mirip PenguinUI “positioned toast” + helper)
    w.toast = {
      success: (msg, o={}) => show(msg, { type: 'success', title: 'Berhasil', ...o }),
      error:   (msg, o={}) => show(msg, { type: 'error',   title: 'Gagal',    ...o }),
      info:    (msg, o={}) => show(msg, { type: 'info',    title: 'Info',     ...o }),
      warn:    (msg, o={}) => show(msg, { type: 'warn',    title: 'Perhatian',...o }),
      show,
    };
  
    // Bridge: kalau kamu sudah pakai Alpine store 'toast', notify.* akan tetap jalan
    w.notify = {
      success: (m,o) => (w.Alpine?.store?.('toast')?.success?.(m,o), w.toast.success(m,o)),
      error:   (m,o) => (w.Alpine?.store?.('toast')?.error?.(m,o),   w.toast.error(m,o)),
      info:    (m,o) => (w.Alpine?.store?.('toast')?.info?.(m,o),    w.toast.info(m,o)),
      warn:    (m,o) => (w.Alpine?.store?.('toast')?.warn?.(m,o),    w.toast.warn(m,o)),
    };
  })(window, document);
  