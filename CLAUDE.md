# NovoSGA v1.5.2 — Documentação do Projeto

## Visão Geral

Sistema de gerenciamento de fila de atendimento (senhas) open source, baseado no [NovoSGA v1.5.2](https://github.com/novosga/novosga/releases/tag/v1.5.2), com correções de compatibilidade e melhorias funcionais.

- **Framework**: Slim Framework 2.6 (NÃO Symfony)
- **ORM**: Doctrine ORM 2.8+ com annotations
- **Template Engine**: Twig 2.x (via slim/views)
- **Banco de Dados**: PostgreSQL (via Doctrine DBAL com driver `pdo_pgsql`)
- **Autenticação API**: OAuth2 (bshaffer/oauth2-server-php)
- **PHP**: >=7.1 (testado em 8.4)
- **Servidor**: nginx + PHP-FPM (ou PHP built-in para desenvolvimento)

---

## Instalação

### Pré-requisitos

- PHP >= 7.1 (testado com 8.4)
- PostgreSQL
- Composer
- Extensões PHP: `pdo`, `pdo_pgsql`, `json`, `gettext`, `mbstring`
- Para produção: nginx + PHP-FPM

### Passo 1 — Código e dependências

```bash
git clone git@github.com:mendesalexandre/novosgaphp-74.git novosga
cd novosga
composer install --no-scripts
```

### Passo 2 — Banco de dados

```bash
sudo -u postgres psql -c "CREATE USER novosga WITH PASSWORD 'novosga';"
sudo -u postgres psql -c "CREATE DATABASE novosga OWNER novosga ENCODING 'UTF8';"
```

### Passo 3 — Instalação automatizada

```bash
php bin/install.php
```

Isso cria tudo: schema, módulos, dados iniciais, usuário `admin`/`123456`, OAuth2.

### Passo 4 — Permissões

```bash
chmod 777 var/cache
chmod 777 config
mkdir -p modules/vetor/panel/public/uploads && chmod 777 modules/vetor/panel/public/uploads
```

### Passo 5a — Servidor de desenvolvimento (PHP built-in)

```bash
php -S 0.0.0.0:8888 -t public
```

Acesso: http://localhost:8888

### Passo 5b — Servidor de produção (nginx + PHP-FPM)

```bash
# 1. Configurar PHP-FPM para upload grande
sudo tee /etc/php.d/99-novosga.ini <<< $'upload_max_filesize = 200M\npost_max_size = 210M'
sudo systemctl restart php-fpm

# 2. Upstream PHP-FPM (se ainda não existir)
# Arquivo /etc/nginx/conf.d/php-fpm.conf:
#   upstream php-fpm { server unix:/run/php-fpm/www.sock; }

# 3. Copiar vhost do projeto
sudo cp nginx.conf.example /etc/nginx/sites-enabled/novosga.conf

# 4. Editar e ajustar os caminhos
sudo sed -i "s|/caminho/para/novosga|$(pwd)|g" /etc/nginx/sites-enabled/novosga.conf

# 5. Adicionar ao /etc/hosts
echo "127.0.0.1 novosga.local" | sudo tee -a /etc/hosts

# 6. Testar e recarregar
sudo nginx -t && sudo systemctl reload nginx
```

Acesso: http://novosga.local — Login: `admin` / `123456`

### Instalador Automatizado (bin/install.php)

Faz tudo em um único comando:
1. Gera `config/database.php` e `config/app.php`
2. Cria schema PostgreSQL + expande sigla para 3 caracteres
3. Instala todos os módulos (sga.* + vetor.panel)
4. Insere dados iniciais (prioridades, grupo, cargo, local, unidade, serviço)
5. Cria usuário admin (admin/123456 por padrão)
6. Configura cliente OAuth2 (novosga-client/novosga-secret)

```bash
# Instalação padrão
php bin/install.php

# Personalizada
php bin/install.php --db-host=10.0.0.1 --db-name=meubanco \
  --admin-user=joao --admin-pass=minhasenha --sigla=ATD --servico="Atendimento Geral"
```

O script é **idempotente** — pode rodar várias vezes sem duplicar dados.

---

## Estrutura do Projeto

```
novosga/
├── bin/
│   ├── install.php            # Instalação automatizada
│   ├── novosga.php            # CLI do NovoSGA (reset, unidades, módulos)
│   └── doctrine               # Doctrine CLI
├── bootstrap.php              # Constantes e autoloader
├── composer.json
├── cli-config.php             # Doctrine CLI helper
├── config/
│   ├── database.php           # Conexão PostgreSQL
│   ├── app.php                # Configuração do app (hooks, queue, auth)
│   └── api.php                # Rotas extras da API (vetor.panel)
├── public/                    # Web root (document root do nginx)
│   ├── index.php              # Rotas Slim (login, home, modules)
│   ├── api/index.php          # Rotas API REST (OAuth2, painel, atendimentos)
│   ├── painel/index.html      # Painel TV simples (polling via URL params)
│   ├── js/sweetalert2.min.js  # SweetAlert2 local (sem CDN)
│   └── css/login.css          # CSS do login moderno
├── src/Novosga/               # Core do sistema
│   ├── App.php                # Extends Slim\Slim
│   ├── Api/                   # ApiV1 (endpoints REST), OAuth2Server
│   ├── Auth/                  # Autenticação (Database, LDAP)
│   ├── Config/                # DatabaseConfig, AppConfig, ApiConfig
│   ├── Controller/            # Controllers base (Home, Login, Ticket, Module)
│   ├── Model/                 # Entidades Doctrine (Atendimento, PainelSenha, etc)
│   ├── Service/               # AtendimentoService, FilaService, UnidadeService, etc
│   ├── Slim/                  # Middlewares (Auth, Install)
│   ├── Twig/                  # Extensões Twig customizadas
│   └── Util/                  # Utilitários (Arrays, DateUtil, I18n, etc)
├── modules/
│   ├── sga/                   # Módulos do sistema
│   │   ├── atendimento/       # Chamar, iniciar, encerrar, codificar, cronômetro
│   │   ├── monitor/           # Visualizar fila, transferir, cancelar, reativar
│   │   ├── triagem/           # Emissão de senhas (normal/prioridade)
│   │   ├── unidade/           # Config da unidade (serviços, impressão, avançado)
│   │   ├── admin/             # Painel administrativo
│   │   ├── estatisticas/      # Relatórios e gráficos
│   │   └── (cargos, grupos, locais, prioridades, servicos, unidades, usuarios, modulos)
│   └── vetor/
│       └── panel/             # Vetor Panel (gerenciador de mídia do painel)
├── painel-web/                # Totem Digital completo (AngularJS + temas)
│   ├── index.html             # App principal
│   ├── js/speech.js           # Vocalização via Web Speech API
│   └── themes/                # Temas disponíveis
│       ├── cartorio/          # Tema para cartórios (estilo ARPEN)
│       ├── hospital/          # Tema hospitalar (protocolo Manchester)
│       ├── moderno-escuro/    # Tema moderno fundo escuro
│       ├── moderno-claro/     # Tema moderno fundo claro
│       ├── tv-fullscreen/     # Vídeo em tela cheia com overlay
│       ├── vetor2/            # Tema clássico 2 colunas
│       ├── default/           # Tema básico original
│       └── marquee/           # Tema com texto rolante
├── totem/                     # Totem de Triagem Touch (triage-app v1.4.0)
│   ├── index.html             # App de emissão de senhas por toque
│   ├── js/triagem-touch.js    # Lógica do totem (OAuth2, impressão, triagem simplificada)
│   └── layouts/default.html   # Layout padrão do totem
├── templates/                 # Templates globais Twig
│   ├── main.html.twig         # Template base (inclui SweetAlert2)
│   ├── login.html.twig        # Login moderno split-screen
│   └── (home, module, profile, print, install, error)
└── var/
    ├── cache/                 # Cache do Twig (limpar ao alterar templates)
    └── log/                   # Logs
```

---

## URLs do Sistema

| URL | Descrição |
|-----|-----------|
| http://novosga.local | Aplicação principal (login: admin/123456) |
| http://novosga.local/painel/ | Painel TV simples (`?unidade=1&servicos=1`) |
| http://novosga.local/painel-web/ | Totem Digital com temas e vocalização |
| http://novosga.local/totem/ | Totem de triagem touch |
| http://novosga.local/api/ | API REST (OAuth2) |
| http://novosga.local/modules/vetor.panel | Gerenciador de mídia do painel |

---

## Fluxo de Atendimento

```
SENHA_EMITIDA (1)  →  CHAMADO_PELA_MESA (2)  →  ATENDIMENTO_INICIADO (3)
      ↑                       ↓                          ↓
  [Triagem]              [Chamar]                   [Encerrar]
                                                        ↓
                              ┌─── se exigir_codificacao ───┐
                              ↓                             ↓
                    ATENDIMENTO_ENCERRADO (4)     ENCERRADO_CODIFICADO (8)
                              ↓                       (finalizado)
                        [Codificar]
                              ↓
                    ENCERRADO_CODIFICADO (8)

Outros status: NAO_COMPARECEU (5), SENHA_CANCELADA (6), ERRO_TRIAGEM (7)
```

---

## Configurações por Unidade (tabela uni_meta)

Gerenciadas em **Unidade > aba Avançado**:

| Chave | Tipo | Default | Descrição |
|-------|------|---------|-----------|
| `permitir_chamar_senha_direta` | 0/1 | 0 | Botão "Chamar" na fila para chamar senha específica |
| `exigir_codificacao` | 0/1 | 1 | Exige seleção de serviços ao encerrar atendimento |
| `triagem_simplificada` | 0/1 | 0 | Botão Prioridade emite direto sem escolher tipo |

---

## Melhorias Implementadas

### Compatibilidade PHP 8.x
- Vendor patches: `get_magic_quotes_gpc()`, Twig 2.x namespaces, `DateInterval`
- Symfony Console travado em ^5.4

### Geração de Senhas (anti-duplicata)
- Transação com `SELECT FOR UPDATE` no contador da unidade
- Cálculo de `numeroSenha` e `numeroSenhaServico` dentro da transação
- `flush()` antes de `commit()`

### Sigla de 3 Caracteres
- Colunas expandidas de `varchar(1)` para `varchar(3)`
- Senha formatada como `AAA0001` (sigla 3 chars + número 4 dígitos)

### Chamar Senha Específica
- Endpoint `POST /modules/sga.atendimento/chamar_especifico/{id}`
- Botão "Chamar" com title "Chamar esta senha: AAA0001"
- Configurável por unidade

### Rechamar Senha
- Endpoint `POST /modules/sga.atendimento/rechamar/{id}`
- Rechama senhas das últimas 2 horas

### API REST
- `POST /api/atendimentos/{id}/chamar` — chamar senha via API
- `GET /api/painel/{unidade}/latest` — polling do painel TV
- `GET /api/extra/vetor.panel` — config de mídia do painel
- `GET /api/extra/vetor.panel/feed?url=...` — proxy RSS

### Campo Nome do Cliente
- Endpoint `POST /modules/sga.atendimento/salvar_nome_cliente`
- Campo na tela de atendimento (status 2)

### Codificação Opcional
- Quando desativada, encerrar finaliza direto (status 8)

### Triagem Simplificada
- Botão Prioridade emite direto sem escolher tipo (Idoso, Gestante, etc.)
- Funciona no módulo de triagem e no totem

### Cronômetro de Atendimento
- Timer visual no canto superior direito
- Vermelho (espera), Verde (atendimento), Amarelo (codificação)
- Persiste ao recarregar via `tempoAtendimento` do servidor

### Login Moderno
- Layout split-screen: branding à esquerda, formulário à direita
- Campos com ícones Bootstrap, botão com gradiente
- Responsivo

---

## Vetor Panel (Gerenciador de Mídia)

Módulo admin em http://novosga.local/modules/vetor.panel

### Widgets de Mídia (aba Widgets)
Exibidos na área principal do painel (slideshow):

| Tipo | Descrição |
|------|-----------|
| Vídeo | URL ou arquivo local (MP4, WebM) — autoplay muted |
| YouTube | ID do vídeo — embed com `mute=1&autoplay=1&loop=1` |
| IPTV | Stream ao vivo (m3u8, ts) |
| Imagem | URL ou arquivo local (JPG, PNG, GIF) |
| HTML | Conteúdo HTML livre |
| Comunicado | Texto com cor e tamanho configuráveis |
| Clima | OpenWeatherMap (cidade + API key) |

### Notícias RSS (aba Rodapé / Notícias)
- Feeds RSS/Atom exibidos no ticker do rodapé
- Proxy via `/api/extra/vetor.panel/feed` (evita CORS)
- Feed padrão: G1 (`http://g1.globo.com/dynamo/rss2.xml`)

### Upload de Arquivos (aba Arquivos)
- Upload de vídeos e imagens (até 200MB)
- Arquivos servidos via nginx em `/modules/vetor.panel/resources/uploads/`

### Configuração salva em `config/vetor-panel.json`

---

## Temas do Totem Digital

Configurar em: Totem Digital > Configuração > General > Tema

| Tema | Nome | Descrição |
|------|------|-----------|
| `cartorio` | Cartório | Estilo ARPEN: senha/guichê à esquerda (azul), vídeo à direita, cliente amarelo, relógio com segundos |
| `hospital` | Hospital | Gov-CE/Albert Sabin: protocolo Manchester (cores por gravidade), relógio próprio |
| `moderno-escuro` | Moderno Escuro | Fundo escuro, senha vermelha, relógio piscante |
| `moderno-claro` | Moderno Claro | Fundo branco, azul corporativo |
| `tv-fullscreen` | TV Fullscreen | Vídeo em tela cheia com overlay transparente |
| `vetor2` | Vetor 2 | Layout clássico 2 colunas |
| `default` | Default | Tema básico original |
| `marquee` | Marquee | Texto rolante |

Todos os temas suportam: widgets do Vetor Panel, vocalização via Web Speech API, RSS no rodapé.

---

## Vocalização (Web Speech API)

O Totem Digital usa a Web Speech API nativa do navegador para vocalizar senhas.

Configurar em: Totem Digital > Configuração > Som

| Opção | Descrição |
|-------|-----------|
| Vocalizar ativo | Habilita/desabilita voz |
| Voz do navegador | Web Speech API (natural, sem arquivos) |
| Arquivos MP3 | Fallback com arquivos pré-gravados |
| Modo extenso | "Senha A A A cento e vinte e três, guichê um" |
| Modo soletrado | "Senha A A A zero um dois três, guichê um" |

Implementado em `painel-web/js/speech.js` (baseado no `useSpeech.js` do projeto Senha).

---

## Totem de Triagem

App touch para emissão de senhas em terminais. Acesso: http://novosga.local/totem/

### Configuração
- **URL**: `http://novosga.local`
- **Unidade**: selecionar
- **Acesso**: `admin` / `123456` / Client ID: `novosga-client` / Client Secret: `novosga-secret`
- **Serviços**: marcar os desejados
- **Triagem simplificada**: Convencional / Preferencial direto (sem escolher tipo)

---

## Painel TV Simples

Página standalone sem dependências. Acesso: http://novosga.local/painel/?unidade=1&servicos=1

- Config via URL params (pode salvar nos favoritos/kiosk)
- Pressionar `C` para abrir configuração manual
- Botão "Copiar URL" gera link permanente

---

## Nginx (vhost)

Arquivo de exemplo incluído no projeto: `nginx.conf.example`

```bash
# Copiar e ajustar caminhos automaticamente
sudo cp nginx.conf.example /etc/nginx/sites-enabled/novosga.conf
sudo sed -i "s|/caminho/para/novosga|$(pwd)|g" /etc/nginx/sites-enabled/novosga.conf
echo "127.0.0.1 novosga.local" | sudo tee -a /etc/hosts
sudo nginx -t && sudo systemctl reload nginx
```

O vhost configura:
- `novosga.local` na porta 80
- document root: `public/`
- `/totem/` → totem de triagem touch
- `/painel-web/` → Totem Digital com temas
- `/api/` → API REST
- `/modules/vetor.panel/resources/uploads/` → uploads de mídia
- Upload máximo: 200MB
- PHP-FPM via upstream `php-fpm`

---

## API REST

### Autenticação OAuth2

```bash
curl -X POST http://novosga.local/api/token \
  -d "grant_type=password&username=admin&password=123456&client_id=novosga-client&client_secret=novosga-secret"
```

### Endpoints

| Método | Rota | Auth | Descrição |
|--------|------|------|-----------|
| POST | `/api/token` | Não | Obter token OAuth2 |
| GET | `/api/unidades` | Não | Listar unidades |
| GET | `/api/servicos(/:unidade)` | Não | Listar serviços |
| GET | `/api/prioridades` | Não | Listar prioridades |
| GET | `/api/painel/:unidade?servicos=1,2` | Não | Senhas do painel |
| GET | `/api/painel/:unidade/latest?servicos=1&lastId=0` | Não | Polling do painel TV |
| POST | `/api/distribui` | Bearer | Emitir nova senha |
| POST | `/api/atendimentos/:id/chamar` | Bearer | Chamar senha específica |
| GET | `/api/atendimento/:id` | Bearer | Visualizar atendimento |
| GET | `/api/fila/usuario/:unidade/:usuario` | Não | Fila do usuário |
| GET | `/api/extra/vetor.panel` | Não | Config de mídia do painel |
| GET | `/api/extra/vetor.panel/feed?url=...` | Não | Proxy RSS |

---

## Correções de Compatibilidade (PHP 8.x)

### Vendor patches (reaplicar após `composer install`)

**`vendor/slim/slim/Slim/Http/Util.php` linha 60:**
```php
$strip = is_null($overrideStripSlashes) ? false : $overrideStripSlashes;
```

**`vendor/slim/views/Twig.php`:** remover Twig_Autoloader, usar `\Twig\Loader\FilesystemLoader`, `\Twig\Environment`, `$env->load()`

**`vendor/slim/views/TwigExtension.php`:** trocar `\Twig_Extension` → `\Twig\Extension\AbstractExtension`, `\Twig_SimpleFunction` → `\Twig\TwigFunction`

---

## Observações Importantes

- **Limpar cache Twig**: `sudo rm -rf var/cache/*` (ou com permissão do PHP-FPM)
- **Vendor patches**: reaplicar após `composer install`
- **Senha admin**: MD5 de `123456` = `e10adc3949ba59abbe56e057f20f883e`
- **Schema do banco**: usar `bin/install.php` ou o script SQL nativo (`src/Novosga/Install/sql/create/pgsql.sql`)
- **SweetAlert2**: incluído localmente em `public/js/sweetalert2.min.js` (funciona offline)
- **Permissões**: `var/cache/` e `config/` precisam de escrita pelo PHP-FPM, `modules/vetor/panel/public/uploads/` para uploads
