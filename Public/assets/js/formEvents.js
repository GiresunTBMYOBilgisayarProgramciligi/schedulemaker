/**
 * Bölüm Ve Program listesi düzenleme işlemleri
 */
/**
 * Her kelimenin ilk harfini büyük yapma fonksiyonu (Türkçe destekli)
 * @param value
 * @returns {string}
 */
function capitalizeWordsTR(value) {
    let words = String(value).split(" ");

    // Map ile her kelimeyi düzenle
    words = words.map((word) => {
        return String(word).charAt(0).toLocaleUpperCase('tr-TR') + String(word).slice(1).toLocaleLowerCase('tr-TR');
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
    return pattern.test(code);
}

document.addEventListener("DOMContentLoaded", function () {
    const departmentSelect = document.getElementById("department_id");
    const programSelect = document.getElementById("program_id");

    if (departmentSelect) {
        departmentSelect.addEventListener("change", function () {
            const departmentId = this.value;
            programSelect.querySelector('option').innerText = ""
            spinner.showSpinner(programSelect.querySelector('option'))
            // AJAX isteği gönder
            fetch(`/ajax/getProgramsList/${departmentId}`, {
                method: "POST",
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
                .then(response => response.json())
                .then(data => {
                    // Program select kutusunu temizle
                    programSelect.innerHTML = "";
                    console.log(data)
                    if (data['programs'].length > 1) {
                        const option = document.createElement("option");
                        option.value = 0;
                        option.textContent = "Program Seçiniz";
                        programSelect.appendChild(option);
                        // Gelen programları ekle
                        data['programs'].forEach(program => {
                            const option = document.createElement("option");
                            option.value = program.id;
                            option.textContent = program.name;
                            programSelect.appendChild(option);
                        });
                    } else if (data['programs'].length === 1) {
                        let program = data['programs'][0];
                        const option = document.createElement("option");
                        option.value = program.id;
                        option.textContent = program.name;
                        option.selected = true
                        programSelect.appendChild(option);
                    }

                    // Select elementinin change olayını tetikle
                    programSelect.dispatchEvent(new Event("change"));
                })
                .catch(error => {
                    new Toast().prepareToast("Hata", "Programları alırken bir hata oluştu. Detaylar için geliştirici konsoluna bakın", "danger");
                    console.error(error);
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
        initializeCodeMask(codeInput);
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
            placeholder: placeholder
        });
    });

});