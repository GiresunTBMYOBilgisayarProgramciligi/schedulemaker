// Wizard State
let currentStep = 1;
let wizardData = {
    user_id: null,
    unit_id: null,
    department_id: null,
    program_id: null,
    permissions: []
};

// Hedef seviyesi (unit | department | program)
let targetScope = '';
let targetId = null;

// Geliştirilmiş formEvent.js mantığını unit/department/program select'leri için global'den kullanıyoruz,
// ancak burada özel durumlar varsa yakalamak için event listener'lar ekleyebiliriz.

document.addEventListener('DOMContentLoaded', () => {
    // TomSelect initialization (if not done globally)
    // Global.js usually initializes .tom-select
});

function wizardNext(toStep) {
    if (toStep === 2) {
        // Validation for step 1
        const userSelect = document.getElementById('wizard_user_id');
        if (!userSelect.value) {
            new Toast().prepareToast('Hata', 'Lütfen bir kullanıcı seçin.', 'warning');
            return;
        }
        wizardData.user_id = userSelect.value;
        loadUserPermissions(wizardData.user_id);
    } else if (toStep === 3) {
        // Validation for step 2
        const unitId = document.getElementById('unit_id').value;
        const deptId = document.getElementById('department_id').value;
        const progId = document.getElementById('program_id').value;
        
        if (!unitId) {
            new Toast().prepareToast('Hata', 'Lütfen en az bir Birim seçin.', 'warning');
            return;
        }
        
        wizardData.unit_id = unitId;
        wizardData.department_id = deptId;
        wizardData.program_id = progId;

        if (progId && progId !== "0") {
            targetScope = 'programs';
            targetId = progId;
        } else if (deptId && deptId !== "0") {
            targetScope = 'departments';
            targetId = deptId;
        } else {
            targetScope = 'units';
            targetId = unitId;
        }

        // Seçilen hedefe uygun olmayan yetkileri gizle ve unchecked yap
        document.querySelectorAll('.permission-item').forEach(item => {
            const allowedScopes = item.getAttribute('data-allowed-scopes').split(',');
            if (allowedScopes.includes(targetScope)) {
                item.classList.remove('d-none');
            } else {
                item.classList.add('d-none');
                const checkbox = item.querySelector('.permission-checkbox');
                if (checkbox) checkbox.checked = false;
            }
        });

    } else if (toStep === 4) {
        // Validation for step 3
        const checkboxes = document.querySelectorAll('.permission-checkbox:checked');
        wizardData.permissions = Array.from(checkboxes).map(cb => cb.value);
        
        if (wizardData.permissions.length === 0) {
            // İzinler boş olabilir (yetki kaldırma), uyarı verip devam edilebilir
            // new Toast().prepareToast('Bilgi', 'Hiç yetki seçmediniz, bu işlem varsa mevcut yetkileri silecektir.', 'info');
        }
        
        updateSummary();
    }

    // Step geçişi
    document.querySelectorAll('.bs-stepper-content .content').forEach(el => el.classList.add('d-none'));
    document.getElementById(`step-${getStepName(toStep)}`).classList.remove('d-none');
    
    // Header güncellemesi
    document.querySelectorAll('.step-trigger').forEach(el => {
        el.classList.remove('active');
    });
    
    const trigger = document.getElementById(`step-${getStepName(toStep)}-trigger`);
    trigger.disabled = false;
    trigger.classList.add('active');
    
    currentStep = toStep;
}

function wizardPrev(toStep) {
    document.querySelectorAll('.bs-stepper-content .content').forEach(el => el.classList.add('d-none'));
    document.getElementById(`step-${getStepName(toStep)}`).classList.remove('d-none');
    
    document.querySelectorAll('.step-trigger').forEach(el => el.classList.remove('active'));
    document.getElementById(`step-${getStepName(toStep)}-trigger`).classList.add('active');
    
    currentStep = toStep;
}

function getStepName(step) {
    const names = { 1: 'user', 2: 'target', 3: 'permissions', 4: 'summary' };
    return names[step];
}

function updateSummary() {
    const userText = document.getElementById('wizard_user_id').options[document.getElementById('wizard_user_id').selectedIndex].text;
    document.getElementById('summary-user').textContent = userText;
    
    let targetText = "Birim: " + document.getElementById('unit_id').options[document.getElementById('unit_id').selectedIndex].text;
    
    const deptSelect = document.getElementById('department_id');
    if(deptSelect.value && deptSelect.value !== "0") {
        targetText += " > Bölüm: " + deptSelect.options[deptSelect.selectedIndex].text;
    }
    
    const progSelect = document.getElementById('program_id');
    if(progSelect.value && progSelect.value !== "0") {
        targetText += " > Program: " + progSelect.options[progSelect.selectedIndex].text;
    }
    
    document.getElementById('summary-target').textContent = targetText;
    
    const ul = document.getElementById('summary-permissions');
    ul.innerHTML = '';
    wizardData.permissions.forEach(perm => {
        const li = document.createElement('li');
        li.textContent = perm;
        ul.appendChild(li);
    });
    
    if(wizardData.permissions.length === 0) {
        ul.innerHTML = '<li><i>Hiç yetki seçilmedi (varsa olanlar silinecek)</i></li>';
    }
}

let existingUserPermissions = {};

function loadUserPermissions(userId) {
    // Kullanıcının mevcut izinlerini yükle
    fetch('/ajax/getUserPermissions', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({ user_id: userId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            existingUserPermissions = data.permissions || {};
            // Seçilen hedef değiştiğinde checkbox'ları doldurmak için event ekleyelim
            document.getElementById('unit_id').addEventListener('change', autoFillPermissions);
            document.getElementById('department_id').addEventListener('change', autoFillPermissions);
            document.getElementById('program_id').addEventListener('change', autoFillPermissions);
        }
    })
    .catch(err => console.error(err));
}

function autoFillPermissions() {
    const unitId = document.getElementById('unit_id').value;
    const deptId = document.getElementById('department_id').value;
    const progId = document.getElementById('program_id').value;
    
    let scope = '';
    let id = null;
    
    if (progId && progId !== "0") {
        scope = 'programs';
        id = progId;
    } else if (deptId && deptId !== "0") {
        scope = 'departments';
        id = deptId;
    } else if (unitId && unitId !== "") {
        scope = 'units';
        id = unitId;
    }
    
    // Checkbox'ları temizle
    document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = false);
    
    if (scope && id && existingUserPermissions[scope] && existingUserPermissions[scope][id]) {
        const perms = existingUserPermissions[scope][id];
        perms.forEach(p => {
            const cb = document.getElementById('perm_' + p);
            if (cb) cb.checked = true;
        });
    }
}

function savePermissions() {
    const btn = document.getElementById('btnSavePermissions');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Kaydediliyor...';
    
    fetch('/ajax/savePermissions', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            user_id: wizardData.user_id,
            scope: targetScope,
            target_id: targetId,
            permissions: JSON.stringify(wizardData.permissions)
        })
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = 'Yetkileri Kaydet';
        
        if (data.status === 'success') {
            new Toast().prepareToast('Başarılı', 'Yetkiler başarıyla güncellendi.', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            new Toast().prepareToast('Hata', data.msg || 'Bir hata oluştu.', 'danger');
        }
    })
    .catch(err => {
        console.error(err);
        btn.disabled = false;
        btn.textContent = 'Yetkileri Kaydet';
        new Toast().prepareToast('Hata', 'Sunucu ile iletişim kurulamadı.', 'danger');
    });
}
