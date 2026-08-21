<?php

namespace App\Helpers;

use App\Models\Log;

class LogViewHelper
{
    /**
     * Log seviyesi için Bootstrap badge HTML çıktısı üretir.
     *
     * @param Log|string $logOrLevel Log nesnesi veya seviye string'i (örn: 'ERROR', 'INFO', 'DEBUG')
     * @return string
     */
    public static function renderLevelBadge(Log|string $logOrLevel): string
    {
        $level = $logOrLevel instanceof Log ? (string)$logOrLevel->level : $logOrLevel;
        $cleanLevel = htmlspecialchars($level);

        $badgeClass = match (strtoupper($level)) {
            'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY' => 'danger',
            'WARNING' => 'warning text-dark',
            'DEBUG' => 'secondary',
            'INFO' => 'info text-dark',
            default => 'primary'
        };

        return '<span class="badge bg-' . $badgeClass . '">' . $cleanLevel . '</span>';
    }

    /**
     * Log kaynağını (dosya, satır, sınıf, metot) biçimlendirip döner.
     *
     * @param Log $log
     * @return string
     */
    public static function renderSource(Log $log): string
    {
        $src = [];
        if (!empty($log->file)) {
            $src[] = basename($log->file) . ':' . $log->line;
        }
        if (!empty($log->class)) {
            $src[] = $log->class;
        }
        if (!empty($log->method)) {
            $src[] = $log->method;
        }

        return htmlspecialchars(implode(' | ', $src));
    }

    /**
     * Log context detaylarını gösteren Bootstrap modal ve tetikleyici buton HTML'ini üretir.
     *
     * @param Log $log
     * @return string
     */
    public static function renderContextModal(Log $log): string
    {
        $modalId = 'contextModal-' . $log->id;
        $contextData = !empty($log->context) ? json_decode($log->context, true) : [];

        if (empty($contextData)) {
            return '<span class="text-muted small">-</span>';
        }

        $bodyHtml = '';
        if (is_array($contextData)) {
            foreach ($contextData as $key => $value) {
                $formattedValue = is_scalar($value) || is_null($value)
                    ? var_export($value, true)
                    : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $bodyHtml .= '<div class="mb-2">';
                $bodyHtml .= '<strong>' . htmlspecialchars((string)$key) . ':</strong>';
                $bodyHtml .= '<pre class="bg-light p-2 border rounded mt-1 mb-0" style="font-size: 0.85rem; max-height: 200px; overflow-y: auto;">' . htmlspecialchars($formattedValue) . '</pre>';
                $bodyHtml .= '</div>';
            }
        }

        return '<!-- Context Modal Trigger -->
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#' . $modalId . '">
                  <i class="bi bi-code-square"></i> Göster
                </button>
                
                <!-- Context Modal -->
                <div class="modal fade" id="' . $modalId . '" tabindex="-1" aria-labelledby="' . $modalId . 'Label" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title fs-6" id="' . $modalId . 'Label">
                          Log Context (ID: ' . $log->id . ' - ' . htmlspecialchars((string)$log->level) . ')
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                      </div>
                      <div class="modal-body">
                        ' . $bodyHtml . '
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Kapat</button>
                      </div>
                    </div>
                  </div>
                </div>';
    }
}
