document.addEventListener('DOMContentLoaded', function () {
    const combineExamForm = document.getElementById('CombineExamLesson');
    if (!combineExamForm) return;

    combineExamForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const formData = new FormData(combineExamForm);
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
        let currentLessonId = document.querySelector('#CombineExamLesson input[name="lesson_id"]');
        if (!currentLessonId) {
             currentLessonId = window.location.pathname.split('/').pop();
        } else {
             currentLessonId = currentLessonId.value;
        }

        const finalParentId = hasParent ? parentId : currentLessonId;
        const finalChildId  = hasChild  ? childId  : currentLessonId;

        try {
            const res = await fetch('/ajax/combineExamLesson', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({
                    parent_lesson_id: parseInt(finalParentId),
                    child_lesson_id: parseInt(finalChildId)
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
