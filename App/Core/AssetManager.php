<?php

namespace App\Core;

/**
 * Genişletilmiş Asset yönetimi için yardımcı sınıf
 */
class AssetManager
{
    /**
     * @var array CSS dosyaları ve özellikleri
     * [
     *    [
     *      'path' => string,
     *      'attributes' => ['attr' => 'value']
     *    ]
     * ]
     */
    private array $css = [];

    /**
     * @var array JavaScript dosyaları ve özellikleri
     * [
     *    [
     *      'path' => string,
     *      'attributes' => ['attr' => 'value']
     *    ]
     * ]
     */
    private array $js = [];

    // Genel olarak her sayfada kullanılacak dosyalar
    private array $globalCss = [
        [//Fonts
            'path' => 'https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css',
            'attributes' => [
                'integrity' => 'sha256-tXJfXfp6Ewt1ilPzLDtQnJV4hclT9XuaZUKyUvmyr+Q=',
                'crossorigin' => 'anonymous'
            ]
        ],
        [ //Third Party Plugin(OverlayScrollbars)
            'path' => 'https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/styles/overlayscrollbars.min.css',
            'attributes' => [
                'integrity' => 'sha256-tZHrRjVqNSRyWg2wbppGnT833E/Ys0DHWGwT04GiqQg=',
                'crossorigin' => 'anonymous'
            ]
        ],
        [//Third Party Plugin(Bootstrap Icons)
            'path' => '/assets/node_modules/bootstrap-icons/font/bootstrap-icons.min.css'
        ],
        [//Required Plugin(AdminLTE)
            'path' => 'https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta3/dist/css/adminlte.min.css',
            'attributes' => [
                'crossorigin' => 'anonymous'
            ]
        ],
        [
            'path' => '/assets/css/adminlte3to4.css'
        ]
    ];

    private array $globalJs = [
        [//Third Party Plugin(OverlayScrollbars)
            'path' => 'https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/browser/overlayscrollbars.browser.es6.min.js',
            'attributes' => [
                'integrity' => 'sha256-dghWARbRe2eLlIJ56wNB+b760ywulqK3DzZYEpsg2fQ=',
                'crossorigin' => 'anonymous'
            ]
        ],
        [//Required Plugin(popperjs for Bootstrap 5)
            'path' => '/assets/node_modules/@popperjs/core/dist/umd/popper.min.js'
        ],
        [
            'path' => '/assets/node_modules/bootstrap/dist/js/bootstrap.min.js'
        ],
        [
            'path' => 'https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta3/dist/js/adminlte.min.js',
            'attributes' => [
                'crossorigin' => 'anonymous'
            ]
        ],
        [
            'path' => '/assets/js/gettext.php'
        ],
        [
            'path' => '/assets/js/myHTMLElements.js'
        ],
    ];

    // Sayfa özel assetleri
    private array $pageAssets = [
        /*
         * */
        'listpages' => [
            'css' => [
                [//dataTables
                    'path' => 'https://cdn.datatables.net/v/bs5/jq-3.7.0/dt-2.2.1/datatables.min.css'
                ]
            ],
            'js' => [
                [//dataTables
                    'path' => 'https://cdn.datatables.net/v/bs5/jq-3.7.0/dt-2.2.1/datatables.min.js'
                ],
                [
                    'path' => '/assets/js/data_table.js'
                ],
                [
                    'path' => '/assets/js/ajax.js'
                ]
            ]
        ],
        'formpages' => [
            'js' => [
                [
                    'path' => '/assets/js/ajax.js'
                ],
                [//Select arama işlemi için
                    'path' => '/assets/node_modules/tom-select/dist/js/tom-select.base.min.js'
                ],
                [
                    'path' => '/assets/js/formEvents.js'
                ],

            ],
            'css' => [
                [
                    'path' => "/assets/node_modules/tom-select/dist/css/tom-select.bootstrap5.min.css"
                ]
            ]
        ],
        'editschedule' => [
            'js' => [
                [
                    'path' => '/assets/js/schedule.js'
                ],
                [
                    'path' => '/assets/js/formEvents.js'
                ]
            ]
        ],
        'exportschedule' => [
            'js' => [
                [
                    'path' => '/assets/js/exportSchedule.js'
                ],
                [
                    'path' => '/assets/js/formEvents.js'
                ]
            ]
        ],
        'profilepage' => [
            'js' => [
                [
                    'path' => '/assets/js/userSchedule.js'
                ],
            ]
        ],
        'singlepages' => [
            'js' => [
                [
                    'path' => '/assets/js/ajax.js'
                ],
            ]
        ]
    ];

    public function __construct()
    {
        // Global assetleri ekle
        $this->css = $this->globalCss;
        $this->js = $this->globalJs;
    }

    /**
     * CSS dosyası ekler
     *
     * @param string $path CSS dosya yolu
     * @param array $attributes Ek öznitelikler (integrity, crossorigin vb.)
     * @return void
     */
    public function addCss(string $path, array $attributes = []): void
    {
        // Aynı dosyanın daha önce eklenip eklenmediğini kontrol et
        foreach ($this->css as $css) {
            if ($css['path'] === $path) {
                return;
            }
        }

        $this->css[] = [
            'path' => $path,
            'attributes' => $attributes
        ];
    }

    /**
     * JavaScript dosyası ekler
     *
     * @param string $path JavaScript dosya yolu
     * @param array $attributes Ek öznitelikler (async, defer, integrity vb.)
     * @return void
     */
    public function addJs(string $path, array $attributes = []): void
    {
        // Aynı dosyanın daha önce eklenip eklenmediğini kontrol et
        foreach ($this->js as $js) {
            if ($js['path'] === $path) {
                return;
            }
        }

        $this->js[] = [
            'path' => $path,
            'attributes' => $attributes
        ];
    }

    /**
     * Sayfa için gerekli assetleri yükler
     *
     * @param string $page Sayfa adı (view dosyasının adı)
     * @return void
     */
    public function loadPageAssets(string $page): void
    {
        // Sayfa özel assetleri ekle
        if (isset($this->pageAssets[$page])) {
            if (isset($this->pageAssets[$page]['css'])) {
                $this->css = array_merge($this->css, $this->pageAssets[$page]['css']);
            }
            if (isset($this->pageAssets[$page]['js'])) {
                $this->js = array_merge($this->js, $this->pageAssets[$page]['js']);
            }
        }
    }

    /**
     * CSS linklerini render eder
     *
     * @return string HTML CSS linkleri
     */
    public function renderCss(): string
    {
        $output = '';
        foreach ($this->css as $css) {
            $attributes = '';
            // Temel öznitelikler
            $attributes .= ' rel="stylesheet"';
            $attributes .= ' href="' . $css['path'] . '"';

            // Ek öznitelikler
            if (isset($css['attributes']) && is_array($css['attributes'])) {
                foreach ($css['attributes'] as $key => $value) {
                    $attributes .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
                }
            }

            $output .= '<link' . $attributes . ' />' . PHP_EOL;
        }
        return $output;
    }

    /**
     * JavaScript scriptlerini render eder
     *
     * @return string HTML script tagları
     */
    public function renderJs(): string
    {
        $output = '';
        foreach ($this->js as $js) {
            $attributes = '';
            // Temel öznitelikler
            $attributes .= ' src="' . $js['path'] . '"';

            // Ek öznitelikler
            if (isset($js['attributes']) && is_array($js['attributes'])) {
                foreach ($js['attributes'] as $key => $value) {
                    $attributes .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
                }
            }

            $output .= '<script' . $attributes . '></script>' . PHP_EOL;
        }
        return $output;
    }
}