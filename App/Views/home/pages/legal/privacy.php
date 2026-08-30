<?php
/**
 * @var string $page_title
 */
?>
<div class="container-xl py-4 py-lg-5">
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4 p-lg-5">
            <!-- Header -->
            <div class="border-bottom pb-4 mb-4">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-success-subtle text-success fw-semibold small mb-2">
                    <i class="bi bi-shield-check"></i> Bilgi Güvenliği Standardı
                </div>
                <h1 class="h2 fw-bold text-body mb-2">Gizlilik ve Çerez Politikası</h1>
                <p class="text-muted small mb-0">
                    <i class="bi bi-clock-history me-1"></i> Son Güncelleme: 30 Ağustos 2026 &bull; Sürüm: v1.0
                </p>
            </div>

            <!-- Content -->
            <div class="legal-content text-body-secondary lh-lg">
                <section class="mb-4">
                    <h5 class="fw-bold text-body mb-3">1. Giriş ve Genel İlkeler</h5>
                    <p>
                        Bu Gizlilik ve Çerez Politikası, <strong>Giresun Üniversitesi</strong> Ders ve Sınav Programı Bilgi Sistemi'ni kullanan tüm ziyaretçilerin, öğrencilerin ve akademik/idari personelin gizliliğini korumak amacıyla hazırlanmıştır.
                    </p>
                </section>

                <section class="mb-4">
                    <h5 class="fw-bold text-body mb-3">2. Çerezler (Cookies) ve Kullanım Amaçları</h5>
                    <p>
                        Sistemimizde kullanıcı deneyimini iyileştirmek, oturum güvenliğini sağlamak ve tercihlerinizi hatırlamak amacıyla yalnızca <strong>zorunlu teknik çerezler</strong> kullanılmaktadır:
                    </p>
                    <div class="table-responsive my-3">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Çerez Adı</th>
                                    <th>Türü</th>
                                    <th>Kullanım Amacı</th>
                                    <th>Saklama Süresi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>PHPSESSID</code></td>
                                    <td>Zorunlu / Oturum</td>
                                    <td>Giriş yapmış kullanıcıların güvenli oturumunun sürdürülmesi.</td>
                                    <td>Tarayıcı kapatılana kadar</td>
                                </tr>
                                <tr>
                                    <td><code>theme</code></td>
                                    <td>İşlevsel / Tercih</td>
                                    <td>Açık veya koyu tema (Dark/Light Mode) tercihinizin hatırlanması.</td>
                                    <td>1 Yıl</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted">
                        * Sistemimizde üçüncü taraf reklam, pazarlama veya kullanıcı takip (tracking) çerezleri <strong>kesinlikle kullanılmamaktadır</strong>.
                    </p>
                </section>

                <section class="mb-4">
                    <h5 class="fw-bold text-body mb-3">3. Veri Güvenliği Önlemleri</h5>
                    <p>Sistemimizde kişisel verilerin ve program kayıtlarının güvenliği için aşağıdaki teknik ve idari tedbirler uygulanmaktadır:</p>
                    <ul>
                        <li><strong>Şifreleme:</strong> Kullanıcı parolaları tek yönlü güvenli hash algoritmalarıyla (Bcrypt) saklanır.</li>
                        <li><strong>Erişim ve Yetki Matrisi:</strong> Rol ve yetkilendirme (Policy/Gate) sistemiyle sadece yetkili kullanıcıların işlem yapması sağlanır.</li>
                        <li><strong>Denetim ve Loglama:</strong> Veritabanında yapılan tüm kritik program değişiklikleri, silmeler ve kullanıcı onayları zaman damgası ve IP adresi ile kayıt altına alınır.</li>
                        <li><strong>XSS & CSRF Koruması:</strong> Tüm veri girişleri doğrulanmakta ve form işlemlerinde güvenlik belirteçleri (Token) kullanılmaktadır.</li>
                    </ul>
                </section>

                <section class="mb-4">
                    <h5 class="fw-bold text-body mb-3">4. Dış Dışa Aktarımlar (Excel / iCal)</h5>
                    <p>
                        Kullanıcıların takvimlerine eklemek üzere indirdiği <code>.ics</code> (iCalendar) ve <code>.xlsx</code> (Excel) dosyaları yerel cihazınızda işlenir ve bu işlemler sırasında cihazınızdan harici sunuculara herhangi bir kişisel veri aktarımı yapılmaz.
                    </p>
                </section>

                <section class="mb-4">
                    <h5 class="fw-bold text-body mb-3">5. Politika Değişiklikleri</h5>
                    <p>
                        Mevzuat değişiklikleri veya sistem güncellemeleri doğrultusunda bu politika güncellenebilir. Önemli değişiklikler yapıldığında sistem üzerinden kayıtlı kullanıcılara bilgilendirme sağlanacaktır.
                    </p>
                </section>
            </div>

            <!-- Footer / Back button -->
            <div class="border-top pt-4 mt-5 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <a href="/" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="bi bi-arrow-left me-1"></i> Ana Sayfaya Dön
                </a>
                <a href="/legal/kvkk" class="btn btn-outline-primary rounded-pill px-4">
                    KVKK Aydınlatma Metni <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
</div>
