/**
 * Bölüm Ve Program listesi düzenleme işlemleri
 */
document.addEventListener("DOMContentLoaded", function () {
    const departmentSelect = document.getElementById("department_id");
    const programSelect = document.getElementById("program_id");

    departmentSelect.addEventListener("change", function () {
        const departmentId = this.value;

        // AJAX isteği gönder
        fetch(`/ajax/getProgramsList/${departmentId}`)
            .then(response => response.json())
            .then(data => {
                // Program select kutusunu temizle
                programSelect.innerHTML = "";

                // Gelen programları ekle
                data.forEach(program => {
                    const option = document.createElement("option");
                    option.value = program.id;
                    option.textContent = program.name;
                    programSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error("Programları alırken bir hata oluştu:", error);
            });
    });
});

/**
 * Formlarda name ve last_name
 *
 */
document.addEventListener("DOMContentLoaded", () => {
    /**
     * Her kelimenin ilk harfini büyük yapma fonksiyonu
     * @param value
     * @returns {*}
     */
    function capitalizeWords(value) {
        return value.replace(/\b\w/g, (char) => char.toUpperCase());
    }

    /**
     * Tüm harfleri büyük yapma fonksiyonu
     * @param value
     * @returns {string}
     */
    function toUpperCase(value) {
        return value.toUpperCase();
    }

    const nameInput = document.getElementById("name");
    const lastNameInput = document.getElementById("last_name");
    const codeInput = document.getElementById("code");

    // Name alanını her kelimenin ilk harfi büyük olacak şekilde düzenle
    if (nameInput) {
        nameInput.addEventListener("input", (event) => {
            event.target.value = capitalizeWords(event.target.value);
        });
    }

    // Last Name ve Code alanlarını tamamen büyük harflere dönüştür
    const upperCaseInputs = [lastNameInput, codeInput];
    upperCaseInputs.forEach((input) => {
        if (input) {
            input.addEventListener("input", (event) => {
                event.target.value = toUpperCase(event.target.value);
            });
        }
    });
});