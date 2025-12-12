<div class="schedule-table-container">
    <table class="schedule-table">
        <thead>
            <tr>
                <th class="time-slot">Saat</th>
                <th>Pazartesi</th>
                <th>Salı</th>
                <th>Çarşamba</th>
                <th>Perşembe</th>
                <th>Cuma</th>
            </tr>
        </thead>
        <tbody>
            <!-- 08:00 - 09:00 Bloğu -->
            <tr>
                <td class="time-slot">08:00 - 08:50</td>

                <!-- Pazartesi -->
                <td class="drop-zone">
                    <div class="lesson-card lesson-type-normal">
                        <span class="lesson-name">Matematik-101</span>
                        <div class="lesson-meta">
                            <span class="lesson-lecturer"><i class="fas fa-user-tie"></i> Dr. A. Yılmaz</span>
                            <span class="lesson-classroom"><i class="fas fa-door-open"></i> D-101</span>
                        </div>
                    </div>
                </td>

                <!-- Salı (Boş) -->
                <td class="drop-zone">
                    <div class="empty-slot">-</div>
                </td>

                <!-- Çarşamba (Parent Örneği) -->
                <td class="drop-zone">
                    <div class="lesson-card lesson-type-parent">
                        <span class="lesson-name">Fizik-101 (Ana Ders)</span>
                        <div class="lesson-meta">
                            <span class="lesson-lecturer">Dr. B. Kaya</span>
                            <span class="lesson-classroom">LB-02</span>
                        </div>
                    </div>
                </td>

                <!-- Perşembe -->
                <td class="drop-zone">
                    <div class="lesson-group-container">
                        <!-- Grup A (Lab) -->
                        <div class="lesson-card lesson-type-lab lesson-group-a">
                            <span class="lesson-name" title="Fizik Lab - Grup A">Fizik Lab (A)</span>
                            <div class="lesson-meta">
                                <span class="lesson-lecturer">Dr. B. Kaya</span>
                                <span class="lesson-classroom">Lab-1</span>
                            </div>
                        </div>
                        <!-- Grup B (Farklı Renk - Normal/Group) -->
                        <div class="lesson-card lesson-type-normal lesson-group-b">
                            <span class="lesson-name" title="Fizik Lab - Grup B">Fizik Lab (B)</span>
                            <div class="lesson-meta">
                                <span class="lesson-lecturer">Arş. Gör. Ali</span>
                                <span class="lesson-classroom">Lab-2</span>
                            </div>
                        </div>
                    </div>
                </td>

                <!-- Cuma (UZEM Örneği) -->
                <td class="drop-zone">
                    <div class="lesson-card lesson-type-uzem">
                        <span class="lesson-name">Tarih (UZEM)</span>
                        <div class="lesson-meta">
                            <span class="lesson-lecturer">Dr. C. Demir</span>
                            <span class="lesson-classroom">Online</span>
                        </div>
                    </div>
                </td>
            </tr>

            <!-- 09:00 - 10:00 Bloğu -->
            <tr>
                <td class="time-slot">09:00 - 09:50</td>

                <!-- Pazartesi (Blok Ders Örneği) -->
                <td class="drop-zone">
                    <div class="lesson-card lesson-type-normal">
                        <span class="lesson-name">Matematik-101</span>
                        <div class="lesson-meta">
                            <span class="lesson-lecturer">Dr. A. Yılmaz</span>
                            <span class="lesson-classroom">D-101</span>
                        </div>
                    </div>
                </td>

                <!-- Diğer günler... -->
                <td class="drop-zone">
                    <div class="empty-slot">
                        -
                        <!-- Not İkonu (Örnek) -->
                        <div class="note-icon" data-note="Bu saatte Lab temizliği yapılacak." title="Notu Oku">
                            <i class="bi bi-chat-square-text-fill"></i>
                        </div>
                    </div>
                </td>
                <td class="drop-zone">
                    <div class="empty-slot slot-preferred">-</div>
                </td>
                <td class="drop-zone">
                    <div class="empty-slot">-</div>
                </td>
                <td class="drop-zone">
                    <div class="empty-slot slot-unavailable">-</div>
                </td>
            </tr>

            <!-- Öğle Arası -->
            <tr style="background-color: #fcfcfc;">
                <td class="time-slot">12:00 - 13:00</td>
                <td colspan="5" style="text-align: center; color: #888; font-weight: bold; letter-spacing: 2px;">ÖĞLE
                    ARASI
                </td>
            </tr>
        </tbody>
    </table>
</div>