<?php

namespace modules\vetor\panel;

use Exception;
use Novosga\Context;
use Novosga\Controller\ModuleController;
use Novosga\Http\JsonResponse;

/**
 * PanelController.
 *
 * Gerenciador de mídia do painel de senhas.
 * Permite configurar vídeos (URL ou upload), imagens e conteúdo HTML
 * que serão exibidos no tema Vetor do painel-web.
 */
class PanelController extends ModuleController
{
    const CONFIG_FILE = 'vetor-panel.json';

    public function index(Context $context)
    {
        $config = $this->carregarConfig();
        $this->app()->view()->set('config', $config);
        $this->app()->view()->set('widgets', isset($config['widgets']) ? $config['widgets'] : array());
    }

    /**
     * Retorna a configuração atual como JSON.
     */
    public function get_config(Context $context)
    {
        $response = new JsonResponse();
        try {
            $config = $this->carregarConfig();
            $response->data = $config;
            $response->success = true;
        } catch (Exception $e) {
            $response->message = $e->getMessage();
        }

        return $response;
    }

    /**
     * Salva a configuração dos widgets.
     */
    public function salvar(Context $context)
    {
        $response = new JsonResponse();
        try {
            if (!$context->request()->isPost()) {
                throw new Exception(_('Somente via POST'));
            }

            $widgetsJson = $context->request()->post('widgets', '[]');
            $widgets = json_decode($widgetsJson, true);
            if (!is_array($widgets)) {
                throw new Exception(_('Formato inválido'));
            }

            $config = $this->carregarConfig();
            $config['widgets'] = $widgets;
            $config['timestamp'] = time();
            $this->salvarConfig($config);

            $response->success = true;
        } catch (Exception $e) {
            $response->message = $e->getMessage();
        }

        return $response;
    }

    /**
     * Upload de arquivo de mídia (vídeo ou imagem).
     */
    public function upload(Context $context)
    {
        $response = new JsonResponse();
        try {
            if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception(_('Nenhum arquivo enviado ou erro no upload'));
            }

            $arquivo = $_FILES['arquivo'];
            $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
            $permitidos = array('mp4', 'webm', 'ogg', 'ogv', 'mp3', 'jpg', 'jpeg', 'png', 'gif');

            if (!in_array($extensao, $permitidos)) {
                throw new Exception(sprintf(_('Tipo de arquivo não permitido: %s'), $extensao));
            }

            // tamanho máximo 200MB
            if ($arquivo['size'] > 200 * 1024 * 1024) {
                throw new Exception(_('Arquivo muito grande (máximo 200MB)'));
            }

            $nomeArquivo = uniqid('media_') . '.' . $extensao;
            $destino = $this->getUploadDir() . '/' . $nomeArquivo;

            if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
                throw new Exception(_('Erro ao salvar o arquivo'));
            }

            // URL relativa para acessar o arquivo
            $url = $this->getUploadUrl() . '/' . $nomeArquivo;

            $response->data = array(
                'url' => $url,
                'nome' => $nomeArquivo,
                'tipo' => $this->detectarTipo($extensao),
            );
            $response->success = true;
        } catch (Exception $e) {
            $response->message = $e->getMessage();
        }

        return $response;
    }

    /**
     * Lista arquivos no diretório de uploads.
     */
    public function listar_arquivos(Context $context)
    {
        $response = new JsonResponse();
        try {
            $dir = $this->getUploadDir();
            $arquivos = array();
            if (is_dir($dir)) {
                foreach (glob($dir . '/*') as $arquivo) {
                    if (is_file($arquivo)) {
                        $nome = basename($arquivo);
                        $extensao = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
                        $arquivos[] = array(
                            'nome' => $nome,
                            'url' => $this->getUploadUrl() . '/' . $nome,
                            'tipo' => $this->detectarTipo($extensao),
                            'tamanho' => filesize($arquivo),
                        );
                    }
                }
            }
            $response->data = $arquivos;
            $response->success = true;
        } catch (Exception $e) {
            $response->message = $e->getMessage();
        }

        return $response;
    }

    /**
     * Remove um arquivo de upload.
     */
    public function remover_arquivo(Context $context)
    {
        $response = new JsonResponse();
        try {
            if (!$context->request()->isPost()) {
                throw new Exception(_('Somente via POST'));
            }
            $nome = $context->request()->post('nome', '');
            $nome = basename($nome); // segurança: só o nome do arquivo
            $caminho = $this->getUploadDir() . '/' . $nome;
            if (!file_exists($caminho)) {
                throw new Exception(_('Arquivo não encontrado'));
            }
            unlink($caminho);
            $response->success = true;
        } catch (Exception $e) {
            $response->message = $e->getMessage();
        }

        return $response;
    }

    // --- Métodos auxiliares ---

    private function getConfigPath()
    {
        return NOVOSGA_CONFIG . '/' . self::CONFIG_FILE;
    }

    private function carregarConfig()
    {
        $path = $this->getConfigPath();
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

    private function salvarConfig($config)
    {
        $path = $this->getConfigPath();
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($path, $json);
    }

    private function getUploadDir()
    {
        $dir = NOVOSGA_ROOT . '/modules/vetor/panel/public/uploads';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    private function getUploadUrl()
    {
        return '/modules/vetor.panel/resources/uploads';
    }

    private function detectarTipo($extensao)
    {
        $tipos = array(
            'mp4' => 'video', 'webm' => 'video', 'ogg' => 'video', 'ogv' => 'video',
            'mp3' => 'audio',
            'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image',
        );

        return isset($tipos[$extensao]) ? $tipos[$extensao] : 'unknown';
    }
}
