
let table = new DataTable('.dataTable', {
    // config options...
    language: {
        url: '/assets/js/datatable_tr.json'
    }
});

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