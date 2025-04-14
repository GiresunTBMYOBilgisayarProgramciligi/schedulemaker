let dataTable = new DataTable('.dataTable', {
    // config options...
    language: {
        url: '/assets/js/datatable_tr.json'
    }
});
/**
 * Popover
 * BAğlı derslerin hangi derse bağlı olduğunu göstermek için bu kodları ekledim
 */
document.addEventListener('DOMContentLoaded', function () {
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    const popoverList = [...popoverTriggerList].map(el => new bootstrap.Popover(el));
});