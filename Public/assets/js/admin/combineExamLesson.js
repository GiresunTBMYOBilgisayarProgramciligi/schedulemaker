document.addEventListener('DOMContentLoaded', function () {
    const combineExamForm = document.getElementById('CombineExamLesson');
    if (!combineExamForm) return;

    combineExamForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const formData = new FormData(combineExamForm);
        const parentId = formData.get('parent_lesson_id');
        const childId  = formData.get('child_lesson_id');

        if (!parentId || !childId || childId === '0') {
            new Toast().prepareToast('Uyarı', 'Lütfen bir ders seçin.', 'warning');
            return;
        }

        try {
            const res = await fetch('/ajax/combineExamLesson', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({
                    parent_lesson_id: parseInt(parentId),
                    child_lesson_id: parseInt(childId)
                })
            });
            const data = await res.json();

            if (data.status === 'success') {
                new Toast().prepareToast('Başarılı', data.msg, 'success');
                if (data.redirect === 'self') setTimeout(() => location.reload(), 1200);
            } else {
                new Toast().prepareToast('Hata', data.msg || 'Bir hata oluştu.', 'danger');
            }
        } catch (err) {
            new Toast().prepareToast('Hata', 'Bağlantı hatası.', 'danger');
        }
    });
});
