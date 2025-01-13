<?php header('Content-Type: application/javascript');
/**
 * todo projede gettext desteği henüz sağlanmıyor.
 * Çoklu Dil desteği için gettext kullanımını javascript içerisinde kullanımı için.
 */
?>
var gettext= {
'close'         : "<?= _('Kapat') ?>",
'yes'           : "<?= _('Evet') ?>",
'no'            : "<?= _('Hayır') ?>",
'ok'            : "<?= _('Tamam') ?>",
'confirmDelete' : "<?= _('Silme onayı') ?>",
'deleteMessage' : "<?= _('Bu işlem geri  alınamaz. Devam etmek istiyormusunuz?') ?>",
'delete'        : "<?= _('Sil') ?>",
'trashMessage'  : "<?= _('Bu içeriği çöpe göndermek istediğinize emin misiniz?') ?>",
'subject'       : "<?= _('Konu') ?>",
'submit'        : "<?= _('Gönder') ?>",
'reply'         : "<?= _('Yanıtla') ?>",
'answer'        : "<?= _('Cevapla') ?>",
'save'          : "<?= _('Kaydet') ?>",
'saving'        : "<?= _('Kaydediliyor...') ?>",
'saved'         : "<?= _('Kaydedildi') ?>",
'add'           : "<?= _('Ekle') ?>",
'pay'           : "<?= _('Öde') ?>"
};