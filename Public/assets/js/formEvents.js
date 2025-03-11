/**
 * Bölüm Ve Program listesi düzenleme işlemleri
 */
document.addEventListener("DOMContentLoaded", function () {
    const departmentSelect = document.getElementById("department_id");
    const programSelect = document.getElementById("program_id");

    if (departmentSelect) {
        departmentSelect.addEventListener("change", function () {
            const departmentId = this.value;
            let spinner = new Spinner();
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

    const nameInput = document.getElementById("name");
    const lastNameInput = document.getElementById("last_name");

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
    let selectInputs = document.querySelectorAll(".tom-select")
    selectInputs.forEach((select) => {
        new TomSelect(select, {placeholder: "Seçmek için yazın"});
    })

});