<?php
/**
 * @var \App\Models\User|null $currentUser
 */

use App\Middlewares\AuthMiddleware;
use App\Repositories\UserConsentRepository;

$user = $currentUser ?? AuthMiddleware::user();
if (!$user) {
    return;
}

$consentRepo = new UserConsentRepository();
$hasAccepted = $consentRepo->hasAcceptedAll($user->id);
if ($hasAccepted) {
    return;
}
?>
<!--begin::KVKK & Privacy Consent Modal-->
<div class="modal fade" id="userConsentModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="userConsentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white border-0 py-3 rounded-top-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-white bg-opacity-25 rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bi bi-shield-check fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="userConsentModalLabel">Sistem Kullanım ve Yasal Bilgilendirme</h5>
                        <span class="small opacity-75">6698 Sayılı KVKK & Bilgi Güvenliği Bildirimi</span>
                    </div>
                </div>
            </div>
            <div class="modal-body p-4">
                <p class="text-secondary mb-3">
                    Sayın <strong><?= htmlspecialchars($user->getFullName()) ?></strong>,
                </p>
                <p class="text-body-secondary small lh-base mb-3">
                    Ders ve Sınav Programı Bilgi Sistemi'ni güvenle kullanabilmeniz ve eğitim-öğretim planlama süreçlerinin mevzuata uygun yürütülebilmesi amacıyla hazırlanan yasal bilgilendirme metinlerini incelemeniz gerekmektedir:
                </p>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <div class="p-3 border rounded-3 bg-body-tertiary h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-bold">
                                    <i class="bi bi-shield-lock-fill"></i> KVKK Aydınlatma Metni
                                </div>
                                <p class="text-muted small mb-3">
                                    Ders, sınav görevlendirmeleri, zaman tercihleri ve sistem loglarınızın işlenme kapsamı ve haklarınız hakkında bilgi içerir.
                                </p>
                            </div>
                            <a href="/legal/kvkk" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill w-100">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Metni Yeni Sekmede Oku
                            </a>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="p-3 border rounded-3 bg-body-tertiary h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-2 text-success fw-bold">
                                    <i class="bi bi-shield-check"></i> Gizlilik & Çerez Politikası
                                </div>
                                <p class="text-muted small mb-3">
                                    Sistemde oturum güvenliği ve kullanıcı tercihleri için kullanılan teknik zorunlu çerezler ve güvenlik tedbirlerini açıklar.
                                </p>
                            </div>
                            <a href="/legal/privacy" target="_blank" class="btn btn-sm btn-outline-success rounded-pill w-100">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Metni Yeni Sekmede Oku
                            </a>
                        </div>
                    </div>
                </div>

                <div class="form-check p-3 bg-light-subtle border rounded-3 mb-2">
                    <input class="form-check-input ms-0 me-2" type="checkbox" id="acceptConsentCheckbox" style="cursor: pointer;">
                    <label class="form-check-label small fw-semibold text-body user-select-none" for="acceptConsentCheckbox" style="cursor: pointer;">
                        Yukarıda bağlantısı verilen <strong>KVKK Aydınlatma Metni</strong> ile <strong>Gizlilik ve Çerez Politikası</strong>'nı okudum, anladım ve bilgilendirildim.
                    </label>
                </div>
                <div id="consentErrorMessage" class="text-danger small mt-2 d-none">
                    <i class="bi bi-exclamation-circle me-1"></i> Lütfen devam etmek için metinleri okuduğunuzu onaylayın.
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" id="btnSubmitConsent" class="btn btn-primary w-100 rounded-pill py-2 fw-semibold" disabled>
                    <i class="bi bi-check2-circle me-1"></i> Okudum, Bilgilendirildim ve Kabul Ediyorum
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const modalEl = document.getElementById('userConsentModal');
    if (!modalEl) return;

    const consentModal = new bootstrap.Modal(modalEl, {
        backdrop: 'static',
        keyboard: false
    });
    consentModal.show();

    const checkbox = document.getElementById('acceptConsentCheckbox');
    const submitBtn = document.getElementById('btnSubmitConsent');
    const errorMsg = document.getElementById('consentErrorMessage');

    checkbox.addEventListener('change', function () {
        submitBtn.disabled = !this.checked;
        if (this.checked) {
            errorMsg.classList.add('d-none');
        }
    });

    submitBtn.addEventListener('click', function () {
        if (!checkbox.checked) {
            errorMsg.classList.remove('d-none');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Kaydediliyor...';

        const formData = new FormData();
        formData.append('version', 'v1.0');

        fetch('/ajax/acceptConsent', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                consentModal.hide();
                if (typeof Toast !== 'undefined') {
                    new Toast().prepareToast('Bilgilendirme', data.msg, 'success');
                }
            } else {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Okudum, Bilgilendirildim ve Kabul Ediyorum';
                alert(data.msg || 'Onay kaydedilirken bir hata oluştu.');
            }
        })
        .catch(err => {
            console.error('Consent save error:', err);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Okudum, Bilgilendirildim ve Kabul Ediyorum';
            alert('Sistemsel bir hata oluştu, lütfen tekrar deneyin.');
        });
    });
});
</script>
<!--end::KVKK & Privacy Consent Modal-->
