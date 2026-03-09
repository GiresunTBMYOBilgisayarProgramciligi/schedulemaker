document.addEventListener('DOMContentLoaded', function () {
    const combineForm = document.getElementById('CombineLesson');
    if (!combineForm) return;

    let pendingFormData = null;

    combineForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const formData = new FormData(combineForm);
        const parentId = formData.get('parent_lesson_id');
        const childId  = formData.get('child_lesson_id');

        // İki accordion'dan hangisinin dolu olduğunu belirle
        const hasParent = parentId && parentId !== '0';
        const hasChild  = childId && childId !== '0';

        if (!hasParent && !hasChild) {
            new Toast().prepareToast('Uyarı', 'Lütfen bir ders seçin.', 'warning');
            return;
        }

        // Hangi yönde bağlama yapılıyor?
        // PHP'den render edilen lesson->id değeri, veri eksikse atanacak.
        // combineLesson.js harici bir dosya olduğu için PHP template etiketleri JS içinde render edilmez.
        // İlgili değeri lesson form'undan bulmamız gerekecek.
        
        let currentLessonId = document.querySelector('input[name="lesson_id"]');
        if (!currentLessonId) {
             currentLessonId = window.location.pathname.split('/').pop();
        } else {
             currentLessonId = currentLessonId.value;
        }

        const finalParentId = hasParent ? parentId : currentLessonId;
        const finalChildId  = hasChild  ? childId  : currentLessonId;

        pendingFormData = { parent_lesson_id: finalParentId, child_lesson_id: finalChildId };

        // Önizleme isteği
        try {
            const res = await fetch('/ajax/previewCombineLesson', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(pendingFormData)
            });
            const data = await res.json();

            if (!res.ok) {
                new Toast().prepareToast('Hata', data.msg || 'Bir hata oluştu.', 'danger');
                return;
            }

            if (!data.needs_confirmation) {
                // Saat farkı yok veya program yok → doğrudan birleştir
                doFinalCombine([]);
            } else {
                // Seçim modalını aç
                openSelectionModal(data);
            }
        } catch (err) {
            new Toast().prepareToast('Hata', 'Bağlantı hatası.', 'danger');
        }
    });

    function openSelectionModal(data) {
        const hoursDiff = data.hours_diff;
        document.getElementById('hoursDiffAlert').innerHTML =
            `Ana ders <strong>${data.parent_hours} saat</strong>, bağlanacak ders <strong>${data.child_hours} saat</strong>. ` +
            `Bağlanacak derse kopyalanmayacak <strong>${hoursDiff} dilim</strong> seçin.`;

        const listEl = document.getElementById('itemSelectionList');
        listEl.innerHTML = '';
        const confirmBtn = document.getElementById('confirmCombineBtn');
        confirmBtn.disabled = true;

        data.items.forEach(item => {
            const label = document.createElement('label');
            label.className = 'list-group-item list-group-item-action d-flex align-items-center gap-2';
            label.innerHTML = `
                <input class="form-check-input item-checkbox" type="checkbox" value="${item.id}">
                <span>${item.day_name} &nbsp; ${item.start_time} – ${item.end_time}</span>`;
            listEl.appendChild(label);
        });

        // Seçim sayısı kontrolü
        listEl.addEventListener('change', function () {
            const checked = listEl.querySelectorAll('.item-checkbox:checked').length;
            confirmBtn.disabled = checked !== hoursDiff;
            // hoursDiff'den fazla seçimi engelle
            if (checked >= hoursDiff) {
                listEl.querySelectorAll('.item-checkbox:not(:checked)').forEach(cb => cb.disabled = true);
            } else {
                listEl.querySelectorAll('.item-checkbox').forEach(cb => cb.disabled = false);
            }
        });

        confirmBtn.onclick = function () {
            const selectedIds = [...listEl.querySelectorAll('.item-checkbox:checked')].map(cb => cb.value);
            doFinalCombine(selectedIds);
        };

        // Birinci modalı kapat, seçim modalını aç
        bootstrap.Modal.getInstance(document.getElementById('CombineLessonModal'))?.hide();
        new bootstrap.Modal(document.getElementById('CombineLessonConfirmModal')).show();
    }

    function doFinalCombine(itemsToRemove) {
        const payload = { ...pendingFormData, items_to_remove: itemsToRemove };

        fetch('/ajax/combineLesson', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                new Toast().prepareToast('Başarılı', data.msg, 'success');
                if (data.redirect === 'self') setTimeout(() => location.reload(), 1200);
            } else {
                new Toast().prepareToast('Hata', data.msg || 'Bir hata oluştu.', 'danger');
            }
        })
        .catch(() => new Toast().prepareToast('Hata', 'Bağlantı hatası.', 'danger'));
    }
});
