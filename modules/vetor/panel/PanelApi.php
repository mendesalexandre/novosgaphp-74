<?php

namespace modules\vetor\panel;

/**
 * PanelApi.
 *
 * Endpoint da API que o tema Vetor do painel-web consome.
 * Retorna a configuração de widgets (vídeos, imagens, HTML).
 */
class PanelApi
{
    const CONFIG_FILE = 'vetor-panel.json';

    /**
     * GET /api/extra/vetor.panel
     * Retorna a configuração de widgets para o painel.
     */
    public function api()
    {
        $config = $this->carregarConfig();

        // ajustar URLs relativas para absolutas
        if (isset($config['widgets'])) {
            foreach ($config['widgets'] as &$widget) {
                if (isset($widget['content']) && strpos($widget['content'], '/') === 0) {
                    $widget['content'] = $this->getBaseUrl() . $widget['content'];
                }
            }
        }

        echo json_encode($config);
    }

    /**
     * GET /api/extra/vetor.panel/feed?url=...
     * Proxy para feeds RSS (evita problemas de CORS).
     */
    public function feed($url)
    {
        if (empty($url)) {
            echo json_encode(array('error' => 'URL não informada'));
            return;
        }

        $content = @file_get_contents($url);
        if ($content === false) {
            echo json_encode(array('error' => 'Não foi possível carregar o feed'));
            return;
        }

        header('Content-Type: application/xml; charset=utf-8');
        echo $content;
    }

    private function carregarConfig()
    {
        $path = NOVOSGA_CONFIG . '/' . self::CONFIG_FILE;
        if (file_exists($path)) {
            $json = file_get_contents($path);
            $config = json_decode($json, true);
            if (is_array($config)) {
                return $config;
            }
        }

        return array(
            'timestamp' => 0,
            'widgets' => array(),
            'news' => array(
                'sources' => array(),
                'interval' => 10000,
            ),
        );
    }

    private function getBaseUrl()
    {
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

        return $scheme . '://' . $host;
    }
}
