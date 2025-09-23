<footer class="border-t border-gray-200 bg-white/70 py-6 text-center text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-900/60 dark:text-gray-400">
  © <script>document.write(new Date().getFullYear())</script> RSUD Matraman
</footer>

<!-- TOASTS -->
<div x-data x-cloak aria-live="polite" class="fixed right-4 top-16 z-50 space-y-2">
  <template x-for="t in $store.toast.list" :key="t.id">
    <div x-transition.opacity.duration.200ms x-transition:enter.scale.90 x-transition:leave.scale.90
         class="pointer-events-auto w-80 rounded-xl border p-3 shadow-lg dark:border-gray-800"
         :class="{
           'bg-white dark:bg-gray-900 border-green-200 text-green-700 dark:text-green-400': t.type==='success',
           'bg-white dark:bg-gray-900 border-red-200 text-red-600 dark:text-red-400': t.type==='error',
           'bg-white dark:bg-gray-900 border-blue-200 text-blue-700 dark:text-blue-400': t.type==='info',
           'bg-white dark:bg-gray-900 border-yellow-200 text-yellow-700 dark:text-yellow-400': t.type==='warn',
         }"
         role="status">
      <div class="flex items-start gap-2">
        <div class="mt-0.5 text-lg" x-text="t.icon || (t.type==='success'?'✓':t.type==='error'?'✕':t.type==='warn'?'!':'ℹ')"></div>
        <div class="flex-1">
          <p class="font-semibold" x-text="t.title || (t.type==='success'?'Berhasil':'Informasi')"></p>
          <p class="text-sm mt-0.5" x-text="t.message"></p>
        </div>
        <button class="rounded-md border px-2 py-0.5 text-xs dark:border-gray-700" @click="$store.toast.remove(t.id)">Tutup</button>
      </div>
    </div>
  </template>
</div>
