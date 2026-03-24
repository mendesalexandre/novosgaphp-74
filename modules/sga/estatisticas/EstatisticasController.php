<?php

namespace modules\sga\estatisticas;

use Exception;
use Mpdf\Mpdf;
use Novosga\App;
use Novosga\Context;
use Novosga\Service\AtendimentoService;
use Novosga\Model\Modulo;
use Novosga\Service\UnidadeService;
use Novosga\Service\UsuarioService;
use Novosga\Util\DateUtil;
use Novosga\Http\JsonResponse;
use Novosga\Controller\ModuleController;
use Novosga\Util\Strings;

/**
 * EstatisticasController.
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class EstatisticasController extends ModuleController
{
    const MAX_RESULTS = 1000;

    private $graficos;
    private $relatorios;

    public function __construct(App $app, Modulo $modulo)
    {
        parent::__construct($app, $modulo);
        $this->graficos = array(
            1 => new Grafico(_('Atendimentos por status'), 'pie', 'unidade,date-range'),
            2 => new Grafico(_('Atendimentos por serviço'), 'pie', 'unidade,date-range'),
            3 => new Grafico(_('Tempo médio do atendimento'), 'bar', 'unidade,date-range'),
        );
        $this->relatorios = array(
            1 => new Relatorio(_('Serviços Disponíveis - Global'), 'servicos_disponiveis_global'),
            2 => new Relatorio(_('Serviços Disponíveis - Unidade'), 'servicos_disponiveis_unidades', 'unidade'),
            3 => new Relatorio(_('Serviços codificados'), 'servicos_codificados', 'unidade,date-range'),
            4 => new Relatorio(_('Atendimentos concluídos'), 'atendimentos_concluidos', 'unidade,date-range,atendente'),
            5 => new Relatorio(_('Atendimentos em todos os status'), 'atendimentos_status', 'unidade,date-range,atendente'),
            6 => new Relatorio(_('Tempos médios por Atendente'), 'tempo_medio_atendentes', 'date-range,atendente'),
            7 => new Relatorio(_('Lotações'), 'lotacoes', 'unidade'),
            8 => new Relatorio(_('Cargos'), 'cargos'),
            9 => new Relatorio(_('Tempo de espera por Serviço'), 'tempo_espera_servico', 'unidade,date-range'),
        );
    }

    public function index(Context $context)
    {
        $dir = MODULES_DIR.'/'.$context->getModulo()->getChave();
        $context->setParameter('js', array(__DIR__.'/js/highcharts.js', __DIR__.'/js/highcharts.exporting.js'));
        $query = $this->em()->createQuery("SELECT e FROM Novosga\Model\Unidade e WHERE e.status = 1 ORDER BY e.nome");
        $unidades = $query->getResult();
        $this->app()->view()->set('unidades', $unidades);
        $this->app()->view()->set('relatorios', $this->relatorios);
        $this->app()->view()->set('graficos', $this->graficos);
        $this->app()->view()->set('statusAtendimento', AtendimentoService::situacoes());
        $arr = array();
        foreach ($unidades as $u) {
            $arr[$u->getId()] = $u->getNome();
        }
        $this->app()->view()->set('unidadesJson', json_encode($arr));
        $this->app()->view()->set('now', DateUtil::now(_('d/m/Y')));
        // atendentes para filtro
        $queryAtendentes = $this->em()->createQuery("
            SELECT e FROM Novosga\Model\Usuario e WHERE e.status = 1 ORDER BY e.nome
        ");
        $this->app()->view()->set('atendentes', $queryAtendentes->getResult());
    }

    /**
     * Retorna os gráficos do dia a partir da unidade informada.
     */
    public function today(Context $context)
    {
        $response = new JsonResponse();
        try {
            $ini = DateUtil::now('Y-m-d');
            $fim = DateUtil::nowSQL(); // full datetime
            $unidade = (int) $context->request()->get('unidade');
            $status = $this->total_atendimentos_status($ini, $fim, $unidade);
            $response->data['legendas'] = AtendimentoService::situacoes();
            $response->data['status'] = $status[$unidade];
            $servicos = $this->total_atendimentos_servico($ini, $fim, $unidade);
            $response->data['servicos'] = $servicos[$unidade];
            $response->success = true;
        } catch (Exception $e) {
            $response->message = $e->getMessage();
        }

        return $response;
    }

    public function grafico(Context $context)
    {
        $response = new JsonResponse();
        try {
            $id = (int) $context->request()->get('grafico');
            $dataInicial = $context->request()->get('inicial');
            $dataFinal = $context->request()->get('final').' 23:59:59';
            $unidade = (int) $context->request()->get('unidade');
            $unidade = ($unidade > 0) ? $unidade : 0;
            if (!isset($this->graficos[$id])) {
                throw new Exception(_('Gráfico inválido'));
            }
            $grafico = $this->graficos[$id];
            switch ($id) {
            case 1:
                $grafico->setLegendas(AtendimentoService::situacoes());
                $grafico->setDados($this->total_atendimentos_status($dataInicial, $dataFinal, $unidade));
                break;
            case 2:
                $grafico->setDados($this->total_atendimentos_servico($dataInicial, $dataFinal, $unidade));
                break;
            case 3:
                $grafico->setDados($this->tempo_medio_atendimentos($dataInicial, $dataFinal, $unidade));
                break;
            }
            $response->data = $grafico->jsonSerialize();
            $response->success = true;
        } catch (\Exception $e) {
            $response->message = $e->getMessage();
        }

        return $response;
    }

    /**
     * Prepara os dados do relatório a partir dos parâmetros da request.
     */
    private function prepararRelatorio(Context $context)
    {
        $id = (int) $context->request()->get('relatorio');
        $dataInicial = $context->request()->get('inicial');
        $dataFinal = $context->request()->get('final');
        $unidade = (int) $context->request()->get('unidade');
        $unidade = ($unidade > 0) ? $unidade : 0;
        $atendente = (int) $context->request()->get('atendente');
        if (!isset($this->relatorios[$id])) {
            throw new Exception(_('Relatório inválido'));
        }
        $relatorio = $this->relatorios[$id];
        $dataInicialFmt = DateUtil::format($dataInicial, _('d/m/Y'));
        $dataFinalFmt = DateUtil::format($dataFinal, _('d/m/Y'));
        $dataFinalSql = $dataFinal.' 23:59:59';
        switch ($id) {
        case 1:
            $relatorio->setDados($this->servicos_disponiveis_global());
            break;
        case 2:
            $relatorio->setDados($this->servicos_disponiveis_unidade($unidade));
            break;
        case 3:
            $relatorio->setDados($this->servicos_codificados($dataInicial, $dataFinalSql, $unidade));
            break;
        case 4:
            $relatorio->setDados($this->atendimentos_concluidos($dataInicial, $dataFinalSql, $unidade, $atendente));
            break;
        case 5:
            $relatorio->setDados($this->atendimentos_status($dataInicial, $dataFinalSql, $unidade, $atendente));
            break;
        case 6:
            $relatorio->setDados($this->tempo_medio_atendentes($dataInicial, $dataFinalSql, $atendente));
            break;
        case 7:
            $servico = $context->request()->get('servico');
            $relatorio->setDados($this->lotacoes($unidade, $servico));
            break;
        case 8:
            $relatorio->setDados($this->cargos());
            break;
        case 9:
            $relatorio->setDados($this->tempo_espera_servico($dataInicial, $dataFinalSql, $unidade));
            break;
        }

        // nome do atendente filtrado
        $nomeAtendente = '';
        if ($atendente > 0) {
            $usuarioObj = $this->em()->find('Novosga\Model\Usuario', $atendente);
            if ($usuarioObj) {
                $nomeAtendente = $usuarioObj->getNome() . ' ' . $usuarioObj->getSobrenome();
            }
        }

        return [
            'relatorio' => $relatorio,
            'dataInicial' => $dataInicialFmt,
            'dataFinal' => $dataFinalFmt,
            'nomeAtendente' => $nomeAtendente,
            'page' => "relatorios/{$relatorio->getArquivo()}.html.twig",
            'isNumeracaoServico' => AtendimentoService::isNumeracaoServico(),
        ];
    }

    public function relatorio(Context $context)
    {
        $dados = $this->prepararRelatorio($context);
        $this->app()->view()->set('relatorio', $dados['relatorio']);
        $this->app()->view()->set('dataInicial', $dados['dataInicial']);
        $this->app()->view()->set('dataFinal', $dados['dataFinal']);
        $this->app()->view()->set('nomeAtendente', $dados['nomeAtendente']);
        $this->app()->view()->set('page', $dados['page']);
        $this->app()->view()->set('isNumeracaoServico', $dados['isNumeracaoServico']);
    }

    public function relatorio_pdf(Context $context)
    {
        $dados = $this->prepararRelatorio($context);
        $relatorio = $dados['relatorio'];

        // Renderizar o conteúdo do relatório via Twig
        $moduleDir = MODULES_PATH . '/sga/estatisticas/views';
        $loader = new \Twig\Loader\FilesystemLoader([$moduleDir, NOVOSGA_TEMPLATES]);
        $twig = new \Twig\Environment($loader);
        $twig->addExtension(new \Twig\Extensions\I18nExtension());
        $twig->addExtension(new \Novosga\Twig\Extensions());

        $vars = [
            'relatorio' => $relatorio,
            'dataInicial' => $dados['dataInicial'],
            'dataFinal' => $dados['dataFinal'],
            'nomeAtendente' => $dados['nomeAtendente'],
            'isNumeracaoServico' => $dados['isNumeracaoServico'],
            'version' => App::VERSION,
            'module' => $context->getModulo(),
        ];

        $bodyHtml = $twig->render($dados['page'], $vars);

        // Gerar PDF com mPDF
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_top' => 25,
            'margin_bottom' => 20,
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_header' => 5,
            'margin_footer' => 5,
            'tempDir' => NOVOSGA_ROOT . '/var/cache',
        ]);

        $mpdf->SetHTMLHeader('
            <table width="100%" style="border-bottom: 1px solid #999; margin-bottom: 5px;">
                <tr>
                    <td style="font-size: 14pt; font-weight: bold; color: #333;">
                        Novo SGA - ' . htmlspecialchars($relatorio->getTitulo()) . '
                    </td>
                    <td style="text-align: right; font-size: 9pt; color: #666;">
                        ' . date('d/m/Y H:i') . '
                    </td>
                </tr>
            </table>
        ');

        $mpdf->SetHTMLFooter('
            <table width="100%" style="border-top: 1px solid #999; padding-top: 3px;">
                <tr>
                    <td style="font-size: 8pt; color: #666;">
                        Novo SGA v' . App::VERSION . '
                    </td>
                    <td style="text-align: center; font-size: 8pt; color: #666;">
                        ' . htmlspecialchars($relatorio->getTitulo()) . '
                    </td>
                    <td style="text-align: right; font-size: 8pt; color: #666;">
                        Pág. {PAGENO}/{nbpg}
                    </td>
                </tr>
            </table>
        ');

        $css = '
            body { font-family: sans-serif; font-size: 10pt; color: #333; }
            h2 { font-size: 13pt; color: #444; margin: 10px 0 5px; }
            .header { text-align: center; margin-bottom: 10px; }
            .header p { font-size: 9pt; color: #666; }
            table.table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
            table.table th { background-color: #f5f5f5; border: 1px solid #ddd; padding: 6px 8px; font-size: 9pt; text-align: left; }
            table.table td { border: 1px solid #ddd; padding: 5px 8px; font-size: 9pt; }
            table.table tfoot td { font-weight: bold; background-color: #f9f9f9; }
            .strong { font-weight: bold; }
            .sub-table { padding: 0 !important; }
            .sub-table table { margin: 0; }
        ';

        $mpdf->WriteHTML('<style>' . $css . '</style>' . $bodyHtml);

        $filename = 'relatorio-' . $relatorio->getArquivo() . '-' . date('Y-m-d-His') . '.pdf';
        $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
        exit;
    }

    private function unidades()
    {
        $query = $this->em()->createQuery("SELECT e FROM Novosga\Model\Unidade e WHERE e.status = 1 ORDER BY e.nome");

        return $query->getResult();
    }

    private function unidadesArray($default = 0)
    {
        if ($default == 0) {
            return $this->unidades();
        } else {
            $unidade = $this->em()->find('Novosga\Model\Unidade', $default);
            if (!$unidade) {
                throw new \Exception('Invalid parameter');
            }

            return array($unidade);
        }
    }

    private function total_atendimentos_status($dataInicial, $dataFinal, $unidadeId = 0)
    {
        $unidades = $this->unidadesArray($unidadeId);
        $dados = array();
        $status = AtendimentoService::situacoes();
        $query = $this->em()->createQuery("
            SELECT
                COUNT(e) as total
            FROM
                Novosga\Model\ViewAtendimento e
            WHERE
                e.dataChegada >= :inicio AND
                e.dataChegada <= :fim AND
                e.unidade = :unidade AND
                e.status = :status
        ");
        $query->setParameter('inicio', $dataInicial);
        $query->setParameter('fim', $dataFinal);
        foreach ($unidades as $unidade) {
            $dados[$unidade->getId()] = array();
            // pegando todos os status
            foreach ($status as $k => $v) {
                $query->setParameter('unidade', $unidade->getId());
                $query->setParameter('status', $k);
                $rs = $query->getSingleResult();
                $dados[$unidade->getId()][$k] = (int) $rs['total'];
            }
        }

        return $dados;
    }

    private function total_atendimentos_servico($dataInicial, $dataFinal, $unidadeId = 0)
    {
        $unidades = $this->unidadesArray($unidadeId);
        $dados = array();
        $query = $this->em()->createQuery("
            SELECT
                s.nome as servico,
                COUNT(a) as total
            FROM
                Novosga\Model\ViewAtendimento a
                JOIN a.unidade u
                JOIN a.servico s
            WHERE
                a.status = :status AND
                a.dataChegada >= :inicio AND
                a.dataChegada <= :fim AND
                a.unidade = :unidade
            GROUP BY
                s
        ");
        $query->setParameter('status', AtendimentoService::ATENDIMENTO_ENCERRADO_CODIFICADO);
        $query->setParameter('inicio', $dataInicial);
        $query->setParameter('fim', $dataFinal);
        foreach ($unidades as $unidade) {
            $query->setParameter('unidade', $unidade->getId());
            $rs = $query->getResult();
            $dados[$unidade->getId()] = array();
            foreach ($rs as $r) {
                $dados[$unidade->getId()][$r['servico']] = $r['total'];
            }
        }

        return $dados;
    }

    private function tempo_medio_atendimentos($dataInicial, $dataFinal, $unidadeId = 0)
    {
        $unidades = $this->unidadesArray($unidadeId);
        $dados = array();
        $tempos = array(
            'espera' => _('Tempo de Espera'),
            'deslocamento' => _('Tempo de Deslocamento'),
            'atendimento' => _('Tempo de Atendimento'),
            'total' => _('Tempo Total'),
        );
        $dql = "
            SELECT
                AVG(a.dataChamada - a.dataChegada) as espera,
                AVG(a.dataInicio - a.dataChamada) as deslocamento,
                AVG(a.dataFim - a.dataInicio) as atendimento,
                AVG(a.dataFim - a.dataChegada) as total
            FROM
                Novosga\Model\ViewAtendimento a
                JOIN a.unidade u
            WHERE
                a.dataChegada >= :inicio AND
                a.dataChegada <= :fim AND
                a.unidade = :unidade
        ";
        $query = $this->em()->createQuery($dql);
        $query->setParameter('inicio', $dataInicial);
        $query->setParameter('fim', $dataFinal);
        foreach ($unidades as $unidade) {
            $query->setParameter('unidade', $unidade->getId());
            $rs = $query->getResult();
            $dados[$unidade->getId()] = array();
            foreach ($rs as $r) {
                try {
                    // se der erro tentando converter a data do banco para segundos, assume que ja esta em segundos
                    // Isso é necessário para manter a compatibilidade entre os bancos
                    foreach ($tempos as $k => $v) {
                        $dados[$unidade->getId()][$v] = DateUtil::timeToSec($r[$k]);
                    }
                } catch (\Exception $e) {
                    foreach ($tempos as $k => $v) {
                        $dados[$unidade->getId()][$v] = (int) $r[$k];
                    }
                }
            }
        }

        return $dados;
    }

    private function servicos_disponiveis_global()
    {
        $query = $this->em()->createQuery("
            SELECT
                e
            FROM
                Novosga\Model\Servico e
                LEFT JOIN e.subServicos sub
            WHERE
                e.mestre IS NULL
            ORDER BY
                e.nome
        ");

        return $query->getResult();
    }

    /**
     * Retorna todos os servicos disponiveis para cada unidade.
     *
     * @return array
     */
    private function servicos_disponiveis_unidade($unidadeId = 0)
    {
        $unidades = $this->unidadesArray($unidadeId);
        $dados = array();
        $query = $this->em()->createQuery("
            SELECT
                e
            FROM
                Novosga\Model\ServicoUnidade e
                JOIN e.servico s
                LEFT JOIN s.subServicos sub
            WHERE
                s.mestre IS NULL AND
                e.status = 1 AND
                e.unidade = :unidade
            ORDER BY
                s.nome
        ");
        foreach ($unidades as $unidade) {
            $query->setParameter('unidade', $unidade);
            $dados[$unidade->getId()] = array(
                'unidade' => $unidade->getNome(),
                'servicos' => $query->getResult(),
            );
        }

        return $dados;
    }

    private function servicos_codificados($dataInicial, $dataFinal, $unidadeId = 0)
    {
        $unidades = $this->unidadesArray($unidadeId);
        $query = $this->em()->createQuery("
            SELECT
                COUNT(s.id) as total,
                s.nome
            FROM
                Novosga\Model\ViewAtendimentoCodificado c
                JOIN c.servico s
                JOIN c.atendimento e
            WHERE
                e.unidade = :unidade AND
                e.dataChegada >= :dataInicial AND
                e.dataChegada <= :dataFinal
            GROUP BY
                s
            ORDER BY
                s.nome
        ");
        $query->setParameter('dataInicial', $dataInicial);
        $query->setParameter('dataFinal', $dataFinal);
        $query->setMaxResults(self::MAX_RESULTS);
        $dados = array();
        foreach ($unidades as $unidade) {
            $query->setParameter('unidade', $unidade);
            $dados[$unidade->getId()] = array(
                'unidade' => $unidade->getNome(),
                'servicos' => $query->getResult(),
            );
        }

        return $dados;
    }

    private function atendimentos_concluidos($dataInicial, $dataFinal, $unidadeId = 0, $atendenteId = 0)
    {
        $unidades = $this->unidadesArray($unidadeId);
        $dados = array();
        $dql = "
            SELECT
                e
            FROM
                Novosga\Model\ViewAtendimento e
            WHERE
                e.unidade = :unidade AND
                e.status = :status AND
                e.dataChegada >= :dataInicial AND
                e.dataChegada <= :dataFinal
        ";
        if ($atendenteId > 0) {
            $dql .= " AND e.usuario = :atendente";
        }
        $dql .= " ORDER BY e.dataChegada";
        $query = $this->em()->createQuery($dql);
        $query->setParameter('status', AtendimentoService::ATENDIMENTO_ENCERRADO_CODIFICADO);
        $query->setParameter('dataInicial', $dataInicial);
        $query->setParameter('dataFinal', $dataFinal);
        if ($atendenteId > 0) {
            $query->setParameter('atendente', $atendenteId);
        }
        $query->setMaxResults(self::MAX_RESULTS);
        foreach ($unidades as $unidade) {
            $query->setParameter('unidade', $unidade);
            $dados[$unidade->getId()] = array(
                'unidade' => $unidade->getNome(),
                'atendimentos' => $query->getResult(),
            );
        }

        return $dados;
    }

    private function atendimentos_status($dataInicial, $dataFinal, $unidadeId = 0, $atendenteId = 0)
    {
        $unidades = $this->unidadesArray($unidadeId);
        $dados = array();
        $dql = "
            SELECT
                e
            FROM
                Novosga\Model\ViewAtendimento e
            WHERE
                e.unidade = :unidade AND
                e.dataChegada >= :dataInicial AND
                e.dataChegada <= :dataFinal
        ";
        if ($atendenteId > 0) {
            $dql .= " AND e.usuario = :atendente";
        }
        $dql .= " ORDER BY e.dataChegada";
        $query = $this->em()->createQuery($dql);
        $query->setParameter('dataInicial', $dataInicial);
        $query->setParameter('dataFinal', $dataFinal);
        if ($atendenteId > 0) {
            $query->setParameter('atendente', $atendenteId);
        }
        $query->setMaxResults(self::MAX_RESULTS);
        foreach ($unidades as $unidade) {
            $query->setParameter('unidade', $unidade);
            $dados[$unidade->getId()] = array(
                'unidade' => $unidade->getNome(),
                'atendimentos' => $query->getResult(),
            );
        }

        return $dados;
    }

    private function tempo_medio_atendentes($dataInicial, $dataFinal, $atendenteId = 0)
    {
        $dados = array();
        $dql = "
            SELECT
                CONCAT(u.nome, CONCAT(' ', u.sobrenome)) as atendente,
                COUNT(a) as total,
                AVG(a.dataChamada - a.dataChegada) as espera,
                AVG(a.dataInicio - a.dataChamada) as deslocamento,
                AVG(a.dataFim - a.dataInicio) as atendimento,
                AVG(a.dataFim - a.dataChegada) as tempoTotal
            FROM
                Novosga\Model\ViewAtendimento a
                JOIN a.usuario u
            WHERE
                a.dataChegada >= :dataInicial AND
                a.dataChegada <= :dataFinal AND
                a.dataFim IS NOT NULL
        ";
        if ($atendenteId > 0) {
            $dql .= " AND a.usuario = :atendente";
        }
        $dql .= " GROUP BY u ORDER BY u.nome";
        $query = $this->em()->createQuery($dql);
        $query->setParameter('dataInicial', $dataInicial);
        $query->setParameter('dataFinal', $dataFinal);
        if ($atendenteId > 0) {
            $query->setParameter('atendente', $atendenteId);
        }
        $query->setMaxResults(self::MAX_RESULTS);
        $rs = $query->getResult();
        foreach ($rs as $r) {
            $d = array(
                'atendente' => $r['atendente'],
                'total' => $r['total'],
            );
            try {
                // se der erro tentando converter a data do banco para segundos, assume que ja esta em segundos
                // Isso é necessário para manter a compatibilidade entre os bancos
                $d['espera'] = DateUtil::timeToSec($r['espera']);
                $d['deslocamento'] = DateUtil::timeToSec($r['deslocamento']);
                $d['atendimento'] = DateUtil::timeToSec($r['atendimento']);
                $d['tempoTotal'] = DateUtil::timeToSec($r['tempoTotal']);
            } catch (\Exception $e) {
                $d['espera'] = $r['espera'];
                $d['deslocamento'] = $r['deslocamento'];
                $d['atendimento'] = $r['atendimento'];
                $d['tempoTotal'] = $r['tempoTotal'];
            }
            $dados[] = $d;
        }

        return $dados;
    }

    private function tempo_espera_servico($dataInicial, $dataFinal, $unidadeId = 0)
    {
        $unidades = $this->unidadesArray($unidadeId);
        $dados = array();
        $dql = "
            SELECT
                s.nome as servico,
                COUNT(a) as total,
                AVG(a.dataChamada - a.dataChegada) as espera,
                AVG(a.dataFim - a.dataInicio) as atendimento,
                AVG(a.dataFim - a.dataChegada) as tempoTotal
            FROM
                Novosga\Model\ViewAtendimento a
                JOIN a.servico s
            WHERE
                a.unidade = :unidade AND
                a.dataChegada >= :dataInicial AND
                a.dataChegada <= :dataFinal AND
                a.dataChamada IS NOT NULL AND
                a.dataFim IS NOT NULL
            GROUP BY
                s
            ORDER BY
                s.nome
        ";
        $query = $this->em()->createQuery($dql);
        $query->setParameter('dataInicial', $dataInicial);
        $query->setParameter('dataFinal', $dataFinal);
        foreach ($unidades as $unidade) {
            $query->setParameter('unidade', $unidade->getId());
            $rs = $query->getResult();
            $servicos = array();
            foreach ($rs as $r) {
                $d = array(
                    'servico' => $r['servico'],
                    'total' => $r['total'],
                );
                try {
                    $d['espera'] = DateUtil::timeToSec($r['espera']);
                    $d['atendimento'] = DateUtil::timeToSec($r['atendimento']);
                    $d['tempoTotal'] = DateUtil::timeToSec($r['tempoTotal']);
                } catch (\Exception $e) {
                    $d['espera'] = (int) $r['espera'];
                    $d['atendimento'] = (int) $r['atendimento'];
                    $d['tempoTotal'] = (int) $r['tempoTotal'];
                }
                $servicos[] = $d;
            }
            $dados[$unidade->getId()] = array(
                'unidade' => $unidade->getNome(),
                'servicos' => $servicos,
            );
        }

        return $dados;
    }

    /**
     * Retorna todos os usuarios e cargos (lotação) por unidade.
     *
     * @return array
     */
    private function lotacoes($unidadeId = 0, $nomeServico = '')
    {
        $nomeServico = trim($nomeServico);
        if (!empty($nomeServico)) {
            $nomeServico = Strings::sqlLikeParam($nomeServico);
        }

        $unidades = $this->unidadesArray($unidadeId);
        $dados = array();

        $usuarioService = new UsuarioService($this->em());
        $unidadeService = new UnidadeService($this->em());

        foreach ($unidades as $unidade) {
            $lotacoes = $unidadeService->lotacoesComServico($unidade->getId(), $nomeServico);
            $servicos = array();
            foreach ($lotacoes as $lotacao) {
                $servicos[$lotacao->getUsuario()->getId()] = $usuarioService->servicos($lotacao->getUsuario(), $unidade);
            }
            $dados[$unidade->getId()] = array(
                'unidade' => $unidade->getNome(),
                'lotacoes' => $lotacoes,
                'servicos' => $servicos,
            );
        }

        return $dados;
    }

    /**
     * Retorna todos os cargos e suas permissões.
     *
     * @return array
     */
    private function cargos()
    {
        $dados = array();
        $query = $this->em()->createQuery("SELECT e FROM Novosga\Model\Cargo e ORDER BY e.nome");
        $cargos = $query->getResult();
        foreach ($cargos as $cargo) {
            $dados[$cargo->getId()] = array(
                'cargo' => $cargo->getNome(),
                'permissoes' => $cargo->getPermissoes(),
            );
        }

        return $dados;
    }
}
