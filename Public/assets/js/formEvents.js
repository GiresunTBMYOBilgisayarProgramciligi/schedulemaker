/**
 * Bölüm Ve Program listesi düzenleme işlemleri
 */
/**
 * Her kelimenin ilk harfini büyük yapma fonksiyonu (Türkçe destekli)
 * @param value
 * @returns {string}
 */
function capitalizeWordsTR(value) {
    if (!value) return "";

    // Roman rakamları listesi (I'den XII'ye kadar sık kullanılanlar)
    const romanNumerals = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    // Kelime parçalarını formatlayan iç yardımcı fonksiyon
    const formatPart = (part) => {
        if (!part) return "";

        // Roman rakamı kontrolü (noktalama temizlenmiş haliyle)
        const cleanPart = part.replace(/[.,;:/]$/, "");
        const upperPart = cleanPart.replace(/i/g, 'İ').replace(/ı/g, 'I').toUpperCase();

        if (romanNumerals.includes(upperPart)) {
            // Kelime içindeki roman rakamı kısmını büyük yap, gerisini (noktalama) koru
            return part.replace(cleanPart, upperPart);
        } else {
            // Türkçe Title Case (Her kelimenin ilk harfi büyük)
            const firstChar = part.charAt(0).toLocaleUpperCase('tr-TR');
            const rest = part.slice(1).toLocaleLowerCase('tr-TR');
            return firstChar + rest;
        }
    };

    let words = String(value).split(" ");

    // Map ile her kelimeyi düzenle
    words = words.map((word) => {
        if (!word) return "";

        // Parantez içindeki grup belirteçlerini kontrol et: (A), (B), (ME) vb.
        const groupMatch = word.match(/^\((.+)\)$/);
        if (groupMatch) {
            const inner = groupMatch[1];
            return "(" + inner.replace(/i/g, 'İ').replace(/ı/g, 'I').toUpperCase() + ")";
        }

        // Kelime içinde tire (-) varsa parçalara ayırıp her parçayı formatla
        if (word.includes("-")) {
            return word.split("-").map(formatPart).join("-");
        } else {
            return formatPart(word);
        }
    });

    return words.join(" ");
}

/**
 * Tüm harfleri büyük yapma fonksiyonu (Türkçe destekli)
 * @param value
 * @returns {string}
 */
function toUpperCaseTR(value) {
    return value.toLocaleUpperCase('tr-TR');
}
// Input elementi için maskeleme fonksiyonu
function initializeCodeMask(inputElement) {
    return; // MAske devre dışı bırakıldı
    // Değişiklikleri dinle
    inputElement.addEventListener('input', function (e) {
        let value = toUpperCaseTR(e.target.value); // Otomatik büyük harfe çevir
        // Sadece harf ve rakamları al (tire ve noktayı kaldır)
        value = value.replace(/[^A-ZĞÜŞİÖÇI0-9]/g, '');

        let formattedValue = '';

        // İlk kısım (harf kısmı)
        const letters = value.match(/[A-ZĞÜŞİÖÇI]+/)?.[0] || '';
        formattedValue = letters;

        // Eğer harf kısmından sonra rakam varsa, otomatik tire ekle
        const numbers = value.slice(letters.length);
        if (numbers.length > 0) {
            formattedValue += '-' + numbers;
        }

        // Nokta ekleme kontrolü (eğer son kısımda nokta ve rakam varsa)
        if (formattedValue.includes('-') && numbers.length > 3) {
            const mainPart = numbers.slice(0, 3);
            const decimal = numbers.slice(3);
            formattedValue = letters + '-' + mainPart;
            if (decimal) {
                formattedValue += '.' + decimal;
            }
        }

        // Maksimum uzunluk kontrolü
        formattedValue = formattedValue.slice(0, 10);

        // Değeri güncelle
        e.target.value = formattedValue;
    });

    // Blur olduğunda format kontrolü
    inputElement.addEventListener('input', function (e) {
        const value = e.target.value;
        const isValid = validateCourseCode(value);

        // Geçerlilik durumuna göre görsel geri bildirim
        if (isValid) {
            inputElement.classList.remove('is-invalid');
            inputElement.classList.add('is-valid');
        } else {
            inputElement.classList.remove('is-valid');
            inputElement.classList.add('is-invalid');
        }
    });
}

// Ders kodu formatını doğrula
function validateCourseCode(code) {
    // Format: AAA-NNN veya AAA-NNN.N
    const pattern = /^[A-ZĞÜŞİÖÇI]{2,4}-\d{3}(?:\.\d)?$/;
    //return pattern.test(code);
    return true;// Validasyon devre dışı bırakıldı
}

document.addEventListener("DOMContentLoaded", function () {
    const unitSelect = document.getElementById("unit_id");
    const departmentSelect = document.getElementById("department_id");
    const programSelect = document.getElementById("program_id");
    const chairpersonSelect = document.getElementById("chairperson_id");
    const buildingSelect = document.getElementById("building_id");

    if (unitSelect && buildingSelect) {
        unitSelect.addEventListener("change", function () {
            const unitId = this.value;

            if (buildingSelect.tomselect) {
                buildingSelect.tomselect.clear();
                buildingSelect.tomselect.clearOptions();
                buildingSelect.tomselect.addOption({value: "", text: "Bina Seçiniz"});
                buildingSelect.tomselect.setValue("", true);
                buildingSelect.tomselect.refreshOptions(false);
            } else {
                buildingSelect.innerHTML = "<option value=''>Bina Seçiniz</option>";
            }

            if (!unitId || unitId === "0" || unitId === "") return;

            // AJAX isteği gönder
            const actionParam = unitSelect.dataset.action ? `?action=${unitSelect.dataset.action}` : '';
            fetch(`/ajax/getBuildingsList/${unitId}${actionParam}`, {
                method: "POST",
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
            .then(response => response.json())
            .then(data => {
                const bldgList = data['buildings'] || [];
                if (buildingSelect.tomselect) {
                    buildingSelect.tomselect.clearOptions();
                    buildingSelect.tomselect.addOption({value: "", text: "Bina Seçiniz"});
                    bldgList.forEach(bldg => {
                        buildingSelect.tomselect.addOption({value: bldg.id, text: bldg.name});
                    });

                    if (bldgList.length === 1) {
                        buildingSelect.tomselect.setValue(bldgList[0].id, true);
                    } else {
                        buildingSelect.tomselect.setValue("", true);
                    }
                    buildingSelect.tomselect.refreshOptions(false);
                } else {
                    buildingSelect.innerHTML = "<option value=''>Bina Seçiniz</option>";
                    bldgList.forEach(bldg => {
                        const option = document.createElement("option");
                        option.value = bldg.id;
                        option.textContent = bldg.name;
                        if (bldgList.length === 1) {
                            option.selected = true;
                        }
                        buildingSelect.appendChild(option);
                    });
                }

                const selectedBuildingId = buildingSelect.getAttribute('data-selected');
                if (selectedBuildingId && selectedBuildingId !== "0" && selectedBuildingId !== "") {
                    if (buildingSelect.tomselect) {
                        buildingSelect.tomselect.setValue(selectedBuildingId, true);
                    } else {
                        buildingSelect.value = selectedBuildingId;
                    }
                    buildingSelect.removeAttribute('data-selected');
                }

                buildingSelect.dispatchEvent(new Event("change"));
            })
            .catch(error => {
                new Toast().prepareToast("Hata", "Binaları alırken hata oluştu.", "danger");
                console.error(error);
            });
        });
    }

    if (unitSelect && chairpersonSelect) {
        unitSelect.addEventListener("change", function () {
            const unitId = this.value;

            if (chairpersonSelect.tomselect) {
                chairpersonSelect.tomselect.clear();
                chairpersonSelect.tomselect.clearOptions();
                chairpersonSelect.tomselect.addOption({value: 0, text: "Bölüm Başkanı Seçiniz"});
                chairpersonSelect.tomselect.setValue(0, true);
                chairpersonSelect.tomselect.refreshOptions(false);
            } else {
                chairpersonSelect.innerHTML = "<option value='0'>Bölüm Başkanı Seçiniz</option>";
            }

            if (!unitId || unitId === "0" || unitId === "") return;

            // AJAX isteği gönder
            fetch(`/ajax/getLecturersList/${unitId}`, {
                method: "POST",
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (chairpersonSelect.tomselect) {
                    chairpersonSelect.tomselect.clearOptions();
                    chairpersonSelect.tomselect.addOption({value: 0, text: "Bölüm Başkanı Seçiniz"});
                    if (data['lecturers'] && data['lecturers'].length > 0) {
                        data['lecturers'].forEach(lecturer => {
                            chairpersonSelect.tomselect.addOption({value: lecturer.id, text: lecturer.name});
                        });
                    }
                    chairpersonSelect.tomselect.refreshOptions(false);
                } else {
                    chairpersonSelect.innerHTML = "<option value='0'>Bölüm Başkanı Seçiniz</option>";
                    if (data['lecturers'] && data['lecturers'].length > 0) {
                        data['lecturers'].forEach(lecturer => {
                            const option = document.createElement("option");
                            option.value = lecturer.id;
                            option.textContent = lecturer.name;
                            chairpersonSelect.appendChild(option);
                        });
                    }
                }

                const selectedChairpersonId = chairpersonSelect.getAttribute('data-selected');
                if (selectedChairpersonId && selectedChairpersonId !== "0") {
                    if (chairpersonSelect.tomselect) {
                        chairpersonSelect.tomselect.setValue(selectedChairpersonId, true); // true = silent
                    } else {
                        chairpersonSelect.value = selectedChairpersonId;
                    }
                    chairpersonSelect.removeAttribute('data-selected');
                }
                
                chairpersonSelect.dispatchEvent(new Event("change"));
            })
            .catch(error => {
                new Toast().prepareToast("Hata", "Hocaları alırken hata oluştu.", "danger");
                console.error(error);
            });
        });
    }

    const btnLoadAllChairpersons = document.getElementById("btn-load-all-chairpersons");
    if (btnLoadAllChairpersons && chairpersonSelect) {
        btnLoadAllChairpersons.addEventListener("click", function () {
            const originalText = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Yükleniyor...';
            this.disabled = true;

            fetch(`/ajax/getAllLecturersList`, {
                method: "POST",
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                const lectList = data['lecturers'] || [];
                if (chairpersonSelect.tomselect) {
                    chairpersonSelect.tomselect.clearOptions();
                    chairpersonSelect.tomselect.addOption({value: 0, text: "Bölüm Başkanı Seçiniz"});
                    lectList.forEach(lect => {
                        chairpersonSelect.tomselect.addOption({value: lect.id, text: lect.name});
                    });
                    chairpersonSelect.tomselect.refreshOptions(false);
                } else {
                    chairpersonSelect.innerHTML = "<option value='0'>Bölüm Başkanı Seçiniz</option>";
                    lectList.forEach(lect => {
                        const option = document.createElement("option");
                        option.value = lect.id;
                        option.textContent = lect.name;
                        chairpersonSelect.appendChild(option);
                    });
                }
                new Toast().prepareToast("Başarılı", "Tüm üniversite hocaları listeye eklendi.", "success");
            })
            .catch(error => {
                new Toast().prepareToast("Hata", "Tüm hocaları alırken hata oluştu.", "danger");
                console.error(error);
            })
            .finally(() => {
                this.innerHTML = originalText;
                this.disabled = false;
            });
        });
    }


    if (unitSelect && departmentSelect) {
        unitSelect.addEventListener("change", function () {
            const unitId = this.value;
            if (programSelect) {
                if (programSelect.tomselect) {
                    programSelect.tomselect.clear();
                    programSelect.tomselect.clearOptions();
                    programSelect.tomselect.addOption({value: 0, text: "İlk olarak Bölüm Seçiniz"});
                    programSelect.tomselect.setValue(0, true);
                    programSelect.tomselect.refreshOptions(false);
                } else {
                    programSelect.innerHTML = "<option value='0'>İlk olarak Bölüm Seçiniz</option>";
                }
            }

            if (departmentSelect.tomselect) {
                departmentSelect.tomselect.clear();
                departmentSelect.tomselect.clearOptions();
                departmentSelect.tomselect.addOption({value: 0, text: "Bölüm Seçiniz"});
                departmentSelect.tomselect.setValue(0, true);
                departmentSelect.tomselect.refreshOptions(false);
            } else {
                departmentSelect.innerHTML = "<option value='0'>Bölüm Seçiniz</option>";
            }

            if (!unitId || unitId === "0" || unitId === "") return;

            // AJAX isteği gönder
            const actionParam = unitSelect.dataset.action ? `?action=${unitSelect.dataset.action}` : '';
            fetch(`/ajax/getDepartmentsList/${unitId}${actionParam}`, {
                method: "POST",
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
            .then(response => response.json())
            .then(data => {
                const deptList = data['departments'] || [];
                if (departmentSelect.tomselect) {
                    departmentSelect.tomselect.clearOptions();
                    departmentSelect.tomselect.addOption({value: 0, text: "Bölüm Seçiniz"});
                    deptList.forEach(dept => {
                        departmentSelect.tomselect.addOption({value: dept.id, text: dept.name});
                    });

                    if (deptList.length === 1) {
                        departmentSelect.tomselect.setValue(deptList[0].id, true); // Tek seçenek varsa otomatik seç
                    } else {
                        departmentSelect.tomselect.setValue(0, true);
                    }
                    departmentSelect.tomselect.refreshOptions(false);
                } else {
                    departmentSelect.innerHTML = "<option value='0'>Bölüm Seçiniz</option>";
                    deptList.forEach(dept => {
                        const option = document.createElement("option");
                        option.value = dept.id;
                        option.textContent = dept.name;
                        if (deptList.length === 1) {
                            option.selected = true; // Tek seçenek varsa otomatik seç
                        }
                        departmentSelect.appendChild(option);
                    });
                }

                const selectedDeptId = departmentSelect.getAttribute('data-selected');
                if (selectedDeptId && selectedDeptId !== "0") {
                    if (departmentSelect.tomselect) {
                        departmentSelect.tomselect.setValue(selectedDeptId, true); // true = silent
                    } else {
                        departmentSelect.value = selectedDeptId;
                    }
                    departmentSelect.removeAttribute('data-selected');
                }
                
                // Tüm işlemler bittikten sonra tek bir change eventi fırlat
                departmentSelect.dispatchEvent(new Event("change"));
            })
            .catch(error => {
                new Toast().prepareToast("Hata", "Bölümleri alırken hata oluştu.", "danger");
                console.error(error);
            });
        });
    }

    if (departmentSelect && programSelect) {
        departmentSelect.addEventListener("change", function () {
            const departmentId = this.value;
            
            if (!departmentId || departmentId === "0" || departmentId === "") {
                if (programSelect) {
                    if (programSelect.tomselect) {
                        programSelect.tomselect.clear();
                        programSelect.tomselect.clearOptions();
                        programSelect.tomselect.addOption({value: 0, text: "İlk olarak Bölüm Seçiniz"});
                        programSelect.tomselect.setValue(0, true);
                        programSelect.tomselect.refreshOptions(false);
                    } else {
                        programSelect.innerHTML = "<option value='0'>İlk olarak Bölüm Seçiniz</option>";
                    }
                }
                return;
            }
            
            if (programSelect.tomselect) {
                programSelect.tomselect.clearOptions();
                programSelect.tomselect.addOption({value: 0, text: "Yükleniyor..."});
                programSelect.tomselect.setValue(0, true);
                programSelect.tomselect.refreshOptions(false);
            } else {
                if (programSelect.querySelector('option')) {
                    programSelect.querySelector('option').innerText = "";
                    spinner.showSpinner(programSelect.querySelector('option'));
                } else {
                    programSelect.innerHTML = "<option value='0'>Yükleniyor...</option>";
                    spinner.showSpinner(programSelect.querySelector('option'));
                }
            }
            
            // AJAX isteği gönder
            const actionParam = departmentSelect.dataset.action ? `?action=${departmentSelect.dataset.action}` : '';
            fetch(`/ajax/getProgramsList/${departmentId}${actionParam}`, {
                method: "POST",
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
                .then(response => response.json())
                .then(data => {
                    const progList = data['programs'] || [];

                    if (programSelect.tomselect) {
                        programSelect.tomselect.clearOptions();
                        programSelect.tomselect.addOption({value: 0, text: "Program Seçiniz"});
                        progList.forEach(program => {
                            programSelect.tomselect.addOption({value: program.id, text: program.name});
                        });

                        if (progList.length === 1) {
                            programSelect.tomselect.setValue(progList[0].id, true); // Tek seçenek varsa otomatik seç (sessiz)
                        } else {
                            programSelect.tomselect.setValue(0, true);
                        }
                        programSelect.tomselect.refreshOptions(false);
                    } else {
                        programSelect.innerHTML = "";
                        const defaultOption = document.createElement("option");
                        defaultOption.value = 0;
                        defaultOption.textContent = "Program Seçiniz";
                        programSelect.appendChild(defaultOption);

                        progList.forEach(program => {
                            const option = document.createElement("option");
                            option.value = program.id;
                            option.textContent = program.name;
                            if (progList.length === 1) {
                                option.selected = true; // Tek seçenek varsa otomatik seç
                            }
                            programSelect.appendChild(option);
                        });
                    }

                    // Select elementinin change olayını kabarcıklanarak (bubbles) tek bir kez tetikle
                    programSelect.dispatchEvent(new Event("change", { bubbles: true }));

                   
                    const selectedProgramId = programSelect.getAttribute('data-selected');
                    if (selectedProgramId && selectedProgramId !== "0") {
                        if (programSelect.tomselect) {
                            programSelect.tomselect.setValue(selectedProgramId, true);
                        } else { // Eğer önceden seçilmesi gereken bir program varsa
                            programSelect.value = selectedProgramId;
                        }
                        programSelect.removeAttribute('data-selected'); // Bir kez seçilmesi yeterli
                        programSelect.dispatchEvent(new Event("change", { bubbles: true }));

                        const programScheduleBtn = document.getElementById('departmentAndProgramScheduleButton');
                        if (programScheduleBtn) {
                            programScheduleBtn.click();
                        }
                    }
                })
                .catch(error => {
                    new Toast().prepareToast("Hata", "Programları alırken bir hata oluştu. Detaylar için geliştirici konsoluna bakın", "danger");
                    console.error(error);
                });
        });
    }

    const classroomUnitSelect = document.getElementById("classroom_unit_id");
    const classroomBuildingSelect = document.getElementById("classroom_building_id");
    const classroomSelect = document.getElementById("classroom_id");

    if (classroomUnitSelect && classroomBuildingSelect) {
        classroomUnitSelect.addEventListener("change", function () {
            const unitId = this.value;
            if (classroomSelect) {
                if (classroomSelect.tomselect) {
                    classroomSelect.tomselect.clear();
                    classroomSelect.tomselect.clearOptions();
                    classroomSelect.tomselect.addOption({value: 0, text: "İlk olarak Bina Seçiniz"});
                    classroomSelect.tomselect.setValue(0, true);
                    classroomSelect.tomselect.refreshOptions(false);
                } else {
                    classroomSelect.innerHTML = "<option value='0'>İlk olarak Bina Seçiniz</option>";
                }
            }

            if (classroomBuildingSelect.tomselect) {
                classroomBuildingSelect.tomselect.clear();
                classroomBuildingSelect.tomselect.clearOptions();
                classroomBuildingSelect.tomselect.addOption({value: 0, text: "Bina Seçiniz"});
                classroomBuildingSelect.tomselect.setValue(0, true);
                classroomBuildingSelect.tomselect.refreshOptions(false);
            } else {
                classroomBuildingSelect.innerHTML = "<option value='0'>Bina Seçiniz</option>";
            }

            if (!unitId || unitId === "0" || unitId === "") return;

            const actionParam = this.dataset.action ? `?action=${this.dataset.action}` : '';
            fetch(`/ajax/getBuildingsList/${unitId}${actionParam}`, {
                method: "POST",
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                const bldgList = data['buildings'] || [];
                if (classroomBuildingSelect.tomselect) {
                    classroomBuildingSelect.tomselect.clearOptions();
                    classroomBuildingSelect.tomselect.addOption({value: 0, text: "Bina Seçiniz"});
                    bldgList.forEach(bldg => {
                        classroomBuildingSelect.tomselect.addOption({value: bldg.id, text: bldg.name});
                    });
                    classroomBuildingSelect.tomselect.refreshOptions(false);
                } else {
                    classroomBuildingSelect.innerHTML = "<option value='0'>Bina Seçiniz</option>";
                    bldgList.forEach(bldg => {
                        const option = document.createElement("option");
                        option.value = bldg.id;
                        option.textContent = bldg.name;
                        classroomBuildingSelect.appendChild(option);
                    });
                }
                classroomBuildingSelect.dispatchEvent(new Event("change"));
            })
            .catch(error => {
                new Toast().prepareToast("Hata", "Binaları alırken hata oluştu.", "danger");
                console.error(error);
            });
        });
    }

    if (classroomBuildingSelect && classroomSelect) {
        classroomBuildingSelect.addEventListener("change", function () {
            const buildingId = this.value;
            if (!buildingId || buildingId === "0" || buildingId === "") {
                if (classroomSelect.tomselect) {
                    classroomSelect.tomselect.clear();
                    classroomSelect.tomselect.clearOptions();
                    classroomSelect.tomselect.addOption({value: 0, text: "İlk olarak Bina Seçiniz"});
                    classroomSelect.tomselect.setValue(0, true);
                    classroomSelect.tomselect.refreshOptions(false);
                } else {
                    classroomSelect.innerHTML = "<option value='0'>İlk olarak Bina Seçiniz</option>";
                }
                return;
            }

            const actionParam = this.dataset.action ? `?action=${this.dataset.action}` : '';
            fetch(`/ajax/getClassroomsList/${buildingId}${actionParam}`, {
                method: "POST",
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                const roomList = data['classrooms'] || [];
                if (classroomSelect.tomselect) {
                    classroomSelect.tomselect.clearOptions();
                    classroomSelect.tomselect.addOption({value: 0, text: "Derslik Seçiniz"});
                    roomList.forEach(room => {
                        classroomSelect.tomselect.addOption({value: room.id, text: room.name});
                    });
                    classroomSelect.tomselect.refreshOptions(false);
                } else {
                    classroomSelect.innerHTML = "<option value='0'>Derslik Seçiniz</option>";
                    roomList.forEach(room => {
                        const option = document.createElement("option");
                        option.value = room.id;
                        option.textContent = room.name;
                        classroomSelect.appendChild(option);
                    });
                }
                classroomSelect.dispatchEvent(new Event("change"));
            })
            .catch(error => {
                new Toast().prepareToast("Hata", "Derslikleri alırken hata oluştu.", "danger");
                console.error(error);
            });
        });
    }

    const lecturerUnitSelect = document.getElementById("lecturer_unit_id");
    const lecturerSelect = document.getElementById("lecturer_id");

    if (lecturerUnitSelect && lecturerSelect) {
        lecturerUnitSelect.addEventListener("change", function () {
            const unitId = this.value;
            if (lecturerSelect.tomselect) {
                lecturerSelect.tomselect.clear();
                lecturerSelect.tomselect.clearOptions();
                lecturerSelect.tomselect.addOption({value: 0, text: "İlk olarak Birim Seçiniz"});
                lecturerSelect.tomselect.setValue(0, true);
                lecturerSelect.tomselect.refreshOptions(false);
            } else {
                lecturerSelect.innerHTML = "<option value='0'>İlk olarak Birim Seçiniz</option>";
            }

            if (!unitId || unitId === "0" || unitId === "") return;

            const actionParam = this.dataset.action ? `?action=${this.dataset.action}` : '';
            fetch(`/ajax/getLecturersList/${unitId}${actionParam}`, {
                method: "POST",
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                const lectList = data['lecturers'] || [];
                if (lecturerSelect.tomselect) {
                    lecturerSelect.tomselect.clearOptions();
                    lecturerSelect.tomselect.addOption({value: 0, text: "Öğretim Üyesi / Görevlisi Seçiniz"});
                    lectList.forEach(lect => {
                        lecturerSelect.tomselect.addOption({value: lect.id, text: lect.name});
                    });
                    lecturerSelect.tomselect.refreshOptions(false);
                } else {
                    lecturerSelect.innerHTML = "<option value='0'>Öğretim Üyesi / Görevlisi Seçiniz</option>";
                    lectList.forEach(lect => {
                        const option = document.createElement("option");
                        option.value = lect.id;
                        option.textContent = lect.name;
                        lecturerSelect.appendChild(option);
                    });
                }
                const selectedLecturerId = lecturerSelect.getAttribute('data-selected');
                if (selectedLecturerId && selectedLecturerId !== "0") {
                    if (lecturerSelect.tomselect) {
                        lecturerSelect.tomselect.setValue(selectedLecturerId, true);
                    } else {
                        lecturerSelect.value = selectedLecturerId;
                    }
                    lecturerSelect.removeAttribute('data-selected');

                    const lecturerScheduleBtn = document.getElementById('lecturerScheduleButton');
                    if (lecturerScheduleBtn) {
                        lecturerScheduleBtn.click();
                    }
                }

                lecturerSelect.dispatchEvent(new Event("change"));
            })
            .catch(error => {
                new Toast().prepareToast("Hata", "Hocaları alırken hata oluştu.", "danger");
                console.error(error);
            });
        });
    }

    const btnLoadAllLecturers = document.getElementById("btn-load-all-lecturers");
    if (btnLoadAllLecturers && lecturerSelect) {
        btnLoadAllLecturers.addEventListener("click", function () {
            // Butona tıklandığında yükleniyor efekti verilebilir
            const originalText = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Yükleniyor...';
            this.disabled = true;

            fetch(`/ajax/getAllLecturersList`, {
                method: "POST",
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                const lectList = data['lecturers'] || [];
                if (lecturerSelect.tomselect) {
                    lecturerSelect.tomselect.clearOptions();
                    lecturerSelect.tomselect.addOption({value: 0, text: "Öğretim Üyesi / Görevlisi Seçiniz"});
                    lectList.forEach(lect => {
                        lecturerSelect.tomselect.addOption({value: lect.id, text: lect.name});
                    });
                    lecturerSelect.tomselect.refreshOptions(false);
                } else {
                    lecturerSelect.innerHTML = "<option value='0'>Öğretim Üyesi / Görevlisi Seçiniz</option>";
                    lectList.forEach(lect => {
                        const option = document.createElement("option");
                        option.value = lect.id;
                        option.textContent = lect.name;
                        lecturerSelect.appendChild(option);
                    });
                }
                new Toast().prepareToast("Başarılı", "Tüm üniversite hocaları listeye eklendi.", "success");
            })
            .catch(error => {
                new Toast().prepareToast("Hata", "Tüm hocaları alırken hata oluştu.", "danger");
                console.error(error);
            })
            .finally(() => {
                this.innerHTML = originalText;
                this.disabled = false;
            });
        });
    }

    const btnLoadFilteredLecturers = document.getElementById("btn-load-filtered-lecturers");
    if (btnLoadFilteredLecturers && lecturerSelect) {
        btnLoadFilteredLecturers.addEventListener("click", function () {
            const unitId = document.getElementById("unit_id")?.value || 0;
            const departmentId = document.getElementById("department_id")?.value || 0;
            const programId = document.getElementById("program_id")?.value || 0;

            const originalText = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            this.disabled = true;

            fetch(`/ajax/getFilteredLecturersList`, {
                method: "POST",
                headers: { 
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    unit_id: unitId,
                    department_id: departmentId,
                    program_id: programId
                })
            })
            .then(response => response.json())
            .then(data => {
                const lectList = data['lecturers'] || [];
                if (lecturerSelect.tomselect) {
                    lecturerSelect.tomselect.clearOptions();
                    lecturerSelect.tomselect.addOption({value: 0, text: "Öğretim Üyesi / Görevlisi Seçiniz"});
                    lectList.forEach(lect => {
                        lecturerSelect.tomselect.addOption({value: lect.id, text: lect.name});
                    });
                    lecturerSelect.tomselect.refreshOptions(false);
                } else {
                    lecturerSelect.innerHTML = "<option value='0'>Öğretim Üyesi / Görevlisi Seçiniz</option>";
                    lectList.forEach(lect => {
                        const option = document.createElement("option");
                        option.value = lect.id;
                        option.textContent = lect.name;
                        lecturerSelect.appendChild(option);
                    });
                }
                new Toast().prepareToast("Başarılı", "Hoca listesi güncellendi.", "success");
            })
            .catch(error => {
                new Toast().prepareToast("Hata", "Hocaları alırken hata oluştu.", "danger");
                console.error(error);
            })
            .finally(() => {
                this.innerHTML = originalText;
                this.disabled = false;
            });
        });
    }

    const nameInput = document.getElementById("name");
    const lastNameInput = document.getElementById("last_name");
    const codeInput = document.querySelector("input#code");
    /**
     * CodeIput varsa maske uygula
     */
    if (codeInput) {
        initializeCodeMask(codeInput);// DEVRE DIŞI
        codeInput.addEventListener("input", (event) => {
            event.target.value = toUpperCaseTR(event.target.value);
        });
    }
    // Name alanını her kelimenin ilk harfi büyük olacak şekilde düzenle
    if (nameInput) {
        nameInput.addEventListener("input", (event) => {
            event.target.value = capitalizeWordsTR(event.target.value);
        });
    }
    if (lastNameInput) {
        lastNameInput.addEventListener("input", (event) => {
            event.target.value = toUpperCaseTR(event.target.value);
        });
    }
    /*
    Tüm select elemanlarına arama özelliği eklemek için
*/
    let selectInputs = document.querySelectorAll(".tom-select");

    selectInputs.forEach((select) => {
        let placeholder = select.getAttribute("placeholder") || "Seçmek için yazın";

        new TomSelect(select, {
            placeholder: placeholder,
            allowEmptyOption: true
        });
    });

    // Eğer sayfa yüklendiğinde birim seçili gelmişse ve bölüm, hoca veya bina seçili değilse / data-selected varsa
    if (unitSelect && unitSelect.value !== "0" && unitSelect.value !== "") {
        if (departmentSelect && (!departmentSelect.value || departmentSelect.value === "0")) {
            unitSelect.dispatchEvent(new Event("change"));
        } else if (chairpersonSelect && (!chairpersonSelect.value || chairpersonSelect.value === "0" || chairpersonSelect.value === "")) {
            unitSelect.dispatchEvent(new Event("change"));
        } else if (buildingSelect && (!buildingSelect.value || buildingSelect.value === "0" || buildingSelect.value === "" || buildingSelect.getAttribute('data-selected'))) {
            unitSelect.dispatchEvent(new Event("change"));
        }
    }

    // Eğer sayfa yüklendiğinde bölüm seçili gelmişse ve program seçili değilse (Yönlendirme ile gelmişse)
    if (departmentSelect && departmentSelect.value !== "0" && departmentSelect.value !== "" && programSelect && (!programSelect.value || programSelect.value === "0")) {
        departmentSelect.dispatchEvent(new Event("change"));
    }

    if (lecturerUnitSelect && lecturerUnitSelect.value !== "0" && lecturerUnitSelect.value !== "") {
        if (lecturerSelect && (!lecturerSelect.value || lecturerSelect.value === "0" || lecturerSelect.value === "")) {
            lecturerUnitSelect.dispatchEvent(new Event("change"));
        }
    }

});