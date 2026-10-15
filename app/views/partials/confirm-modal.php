<div class="modal" id="confirm-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
  <div class="modal-backdrop" data-modal-close></div>
  <div class="modal-panel max-w-md">
    <div class="p-6">
      <div class="flex items-start gap-4">
        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-rose-50 text-2xl text-rose-600" data-confirm-icon><i class="bx bx-error"></i></span>
        <div>
          <h3 id="confirm-title" class="text-base font-semibold text-slate-900">Bitte bestätigen</h3>
          <p class="mt-1.5 text-sm leading-relaxed text-slate-600" data-confirm-text></p>
        </div>
      </div>
    </div>
    <div class="flex flex-col-reverse gap-2 rounded-b-2xl bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end">
      <button type="button" class="btn btn-secondary" data-modal-close>Abbrechen</button>
      <button type="button" class="btn btn-danger" data-confirm-ok>Bestätigen</button>
    </div>
  </div>
</div>
