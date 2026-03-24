# Novo SGA v1.5.2 (Fork com melhorias)

Sistema de Gerenciamento de Atendimento adaptável para grandes e pequenas organizações.

**Projeto original**: [NovoSGA](https://github.com/novosga/novosga) por [Rogério Alencar Lino Filho](http://rogeriolino.com/)

Este fork é baseado na versão [v1.5.2](https://github.com/novosga/novosga/releases/tag/v1.5.2) do NovoSGA, com correções de compatibilidade para PHP 7.1+ / 8.x e melhorias funcionais.

## Ajustes e melhorias

- **Compatibilidade PHP 8.x** — patches em Slim 2.6, Twig 2.x e Doctrine para funcionar em PHP 8.4
- **Geração de senhas anti-duplicata** — transação com `SELECT FOR UPDATE`
- **Sigla de 3 caracteres** — senhas no formato `AAA0001`
- **Chamar senha específica** — botão na fila para chamar senha direta
- **Rechamar senha** — rechamar senhas das últimas 2 horas
- **Campo nome do cliente** — campo na tela de atendimento
- **Codificação opcional** — configurável por unidade
- **Triagem simplificada** — emissão direta de senha preferencial
- **Cronômetro de atendimento** — timer visual por status (espera, atendimento, codificação)
- **Login moderno** — layout split-screen responsivo
- **Exportação PDF com mPDF** — relatórios com cabeçalho e rodapé padrão
- **Filtro por atendente** — nos relatórios de atendimentos e tempos médios
- **Relatório: Tempo de espera por Serviço** — identifica gargalos por serviço
- **Vetor Panel** — gerenciador de mídia para o painel (vídeos, imagens, YouTube, IPTV, RSS)
- **Totem Digital** — painel TV com temas, vocalização via Web Speech API
- **Totem de Triagem** — app touch para emissão de senhas
- **API REST** — endpoints OAuth2 para integração externa

## Tecnologia

- **PHP** >= 7.1 (testado em 8.4)
- **Framework**: Slim Framework 2.6
- **ORM**: Doctrine ORM 2.8+ com annotations
- **Templates**: Twig 2.x
- **Banco de dados**: PostgreSQL
- **Autenticação API**: OAuth2 (bshaffer/oauth2-server-php)
- **Relatórios PDF**: mPDF 8.3
- **Gráficos**: Highcharts
- **Frontend**: jQuery, Bootstrap 3

## Instalação

```bash
git clone git@github.com:mendesalexandre/novosgaphp-74.git novosga
cd novosga
composer install --no-scripts
php bin/install.php
php -S 0.0.0.0:8888 -t public
```

Acesso: http://localhost:8888 — Login: `admin` / `123456`

Documentação completa em [CLAUDE.md](CLAUDE.md).

## Créditos

**Autor original**: [Rogério Alencar Lino Filho](http://rogeriolino.com/) — https://github.com/novosga/novosga

**Colaboradores originais**: https://github.com/novosga/novosga/contributors

**Ajustes e melhorias**: Alexandre Mendes
