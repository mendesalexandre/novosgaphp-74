# NovoSGA v1.5.2 — Documentação do Projeto

## Visão Geral

Sistema de gerenciamento de fila de atendimento (senhas) open source, baseado no [NovoSGA v1.5.2](https://github.com/novosga/novosga/releases/tag/v1.5.2), com correções de compatibilidade e melhorias funcionais.

- **Framework**: Slim Framework 2.6 (NÃO Symfony)
- **ORM**: Doctrine ORM 2.8+ com annotations
- **Template Engine**: Twig 2.x (via slim/views)
- **Banco de Dados**: PostgreSQL (via Doctrine DBAL com driver `pdo_pgsql`)
- **Autenticação API**: OAuth2 (bshaffer/oauth2-server-php)
- **PHP**: >=7.1 (testado em 8.4)

---

## Estrutura do Projeto

```
novosga/
├── bootstrap.php              # Constantes e autoloader
├── composer.json
├── cli-config.php             # Doctrine CLI helper
├── config/
│   ├── database.php           # Conexão PostgreSQL (driver, host, porta, dbname, user, password)
│   └── app.php                # Configuração do app (hooks, queue, auth)
├── public/                    # Web root (entry point do servidor)
│   ├── index.php              # Rotas Slim (login, home, modules)
│   ├── api/index.php          # Rotas API REST (OAuth2, painel, atendimentos)
│   └── painel/index.html      # Painel TV de senhas (polling)
├── src/Novosga/               # Core do sistema
│   ├── App.php                # Extends Slim\Slim
│   ├── Api/                   # ApiV1 (endpoints REST), OAuth2Server
│   ├── Auth/                  # Autenticação (Database, LDAP)
│   ├── Config/                # DatabaseConfig, AppConfig (arquivos PHP em config/)
│   ├── Controller/            # Controllers base (Home, Login, Ticket, Module)
│   ├── Model/                 # Entidades Doctrine (Atendimento, PainelSenha, etc)
│   ├── Service/               # AtendimentoService, FilaService, UnidadeService, etc
│   ├── Slim/                  # Middlewares (Auth, Install)
│   ├── Twig/                  # Extensões Twig customizadas
│   └── Util/                  # Utilitários (Arrays, DateUtil, I18n, etc)
├── modules/sga/               # Módulos do sistema
│   ├── atendimento/           # Chamar, iniciar, encerrar, codificar senhas
│   ├── monitor/               # Visualizar fila, transferir, cancelar, reativar
│   ├── triagem/               # Emissão de senhas
│   ├── unidade/               # Configuração da unidade (serviços, impressão, avançado)
│   ├── admin/                 # Painel administrativo
│   ├── estatisticas/          # Relatórios e gráficos
│   ├── cargos/                # Gerenciamento de cargos
│   ├── grupos/                # Gerenciamento de grupos
│   ├── locais/                # Locais de atendimento
│   ├── prioridades/           # Tipos de prioridade
│   ├── servicos/              # Serviamento de serviços globais
│   ├── unidades/              # Gerenciamento de unidades
│   ├── usuarios/              # Gerenciamento de usuários
│   └── modulos/               # Instalação de módulos
├── templates/                 # Templates globais (main, login, home, install, etc)
└── var/
    ├── cache/                 # Cache do Twig (limpar ao alterar templates)
    └── log/                   # Logs da aplicação
```

---

## Como Subir o Projeto

### Pré-requisitos

- PHP >= 7.1 (testado com 8.4)
- PostgreSQL
- Composer
- Extensões PHP: pdo, pdo_pgsql, json, gettext, mbstring

### Instalação

```bash
# 1. Instalar dependências
composer install --no-scripts

# 2. Criar banco de dados PostgreSQL
sudo -u postgres psql -c "CREATE USER novosga WITH PASSWORD 'novosga';"
sudo -u postgres psql -c "CREATE DATABASE novosga OWNER novosga ENCODING 'UTF8';"

# 3. Criar schema (usar o script SQL nativo, NÃO o Doctrine schema:create)
PGPASSWORD=novosga psql -h 127.0.0.1 -U novosga -d novosga -f src/Novosga/Install/sql/create/pgsql.sql

# 4. Instalar módulos
php -r "
require 'bootstrap.php';
\$db = \Novosga\Config\DatabaseConfig::getInstance();
\$em = \$db->createEntityManager();
\$service = new \Novosga\Service\ModuloService(\$em);
foreach (glob(MODULES_PATH.'/sga/*', GLOB_ONLYDIR) as \$dir) {
    \$service->install(\$dir, 'sga.' . basename(\$dir), 1);
}
"

# 5. Inserir dados iniciais (prioridades, grupo, cargo, unidade, serviço, admin)
PGPASSWORD=novosga psql -h 127.0.0.1 -U novosga -d novosga << 'EOSQL'
INSERT INTO prioridades (nome, descricao, peso, status) VALUES ('Normal', 'Atendimento normal', 0, 1);
INSERT INTO prioridades (nome, descricao, peso, status) VALUES ('Idoso', 'Prioritário idosos', 1, 1);
INSERT INTO prioridades (nome, descricao, peso, status) VALUES ('Gestante', 'Prioritário gestantes', 1, 1);
INSERT INTO grupos (nome, descricao, esquerda, direita, nivel) VALUES ('Raiz', 'Grupo raiz', 1, 4, 0);
INSERT INTO cargos (nome, descricao, esquerda, direita, nivel) VALUES ('Administrador', 'Administrador do sistema', 1, 2, 0);
INSERT INTO cargos_mod_perm (cargo_id, modulo_id, permissao) SELECT (SELECT id FROM cargos LIMIT 1), id, 3 FROM modulos;
INSERT INTO unidades (grupo_id, codigo, nome, status, stat_imp, msg_imp) VALUES ((SELECT id FROM grupos LIMIT 1), '1', 'Unidade Padrão', 1, 0, '');
INSERT INTO servicos (descricao, nome, status, peso) VALUES ('Atendimento geral', 'Atendimento', 1, 1);
INSERT INTO uni_serv (unidade_id, servico_id, local_id, sigla, status, peso) VALUES (
  (SELECT id FROM unidades LIMIT 1), (SELECT id FROM servicos LIMIT 1),
  (SELECT id FROM locais LIMIT 1), 'AAA', 1, 1);
INSERT INTO config (chave, valor, tipo) VALUES ('version', '1.5.2', 1);
INSERT INTO usuarios (login, nome, sobrenome, senha, ult_acesso, status, session_id)
  VALUES ('admin', 'Admin', 'Istrador', 'e10adc3949ba59abbe56e057f20f883e', NULL, 1, '');
INSERT INTO usu_grup_cargo (usuario_id, grupo_id, cargo_id)
  SELECT id, (SELECT id FROM grupos LIMIT 1), (SELECT id FROM cargos LIMIT 1) FROM usuarios;
INSERT INTO usu_serv (unidade_id, servico_id, usuario_id)
  SELECT (SELECT id FROM unidades LIMIT 1), (SELECT id FROM servicos LIMIT 1), id FROM usuarios;
INSERT INTO oauth_clients (client_id, client_secret, redirect_uri, grant_types)
  VALUES ('novosga-client', 'novosga-secret', '', 'password refresh_token');
EOSQL

# 6. Subir o servidor
php -S 0.0.0.0:8888 -t public
```

### Acesso

| URL | Descrição |
|-----|-----------|
| http://localhost:8888 | Aplicação (login: `admin` / `123456`) |
| http://localhost:8888/painel/ | Painel TV (pressione `C` para configurar) |
| http://localhost:8888/api/ | API REST (OAuth2) |

### Configuração do Banco

Arquivo `config/database.php`:

```php
<?php
return array(
    'driver'   => 'pdo_pgsql',
    'host'     => '127.0.0.1',
    'port'     => 5432,
    'dbname'   => 'novosga',
    'user'     => 'novosga',
    'password' => 'novosga',
    'charset'  => 'UTF8',
);
```

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

## Correções de Compatibilidade (PHP 8.x)

### Vendor patches (necessários após `composer install`)

Estes arquivos do vendor precisam de correção manual para rodar em PHP 8.x:

**`vendor/slim/slim/Slim/Http/Util.php` linha 60:**
```php
// DE:
$strip = is_null($overrideStripSlashes) ? get_magic_quotes_gpc() : $overrideStripSlashes;
// PARA:
$strip = is_null($overrideStripSlashes) ? false : $overrideStripSlashes;
```

**`vendor/slim/views/Twig.php`:**
- Remover bloco `Twig_Autoloader` (linhas 116-120)
- Trocar `\Twig_Loader_Filesystem` → `\Twig\Loader\FilesystemLoader`
- Trocar `\Twig_Environment` → `\Twig\Environment`
- Trocar `$env->loadTemplate()` → `$env->load()`

**`vendor/slim/views/TwigExtension.php`:**
- Trocar `\Twig_Extension` → `\Twig\Extension\AbstractExtension`
- Trocar `\Twig_SimpleFunction` → `\Twig\TwigFunction`
- Remover método `getName()`

### Correções no código-fonte

| Arquivo | Correção |
|---------|----------|
| `src/Novosga/Twig/Extensions.php` | `\Twig_Extension` → `\Twig\Extension\AbstractExtension` |
| `src/Novosga/Twig/SecFormat.php` | `\Twig_SimpleFilter` → `\Twig\TwigFilter`, `\Twig_Environment` → `\Twig\Environment` |
| `src/Novosga/Twig/ResourcesFunction.php` | `\Twig_SimpleFunction` → `\Twig\TwigFunction`, `\Twig_Environment` → `\Twig\Environment` |
| `src/Novosga/App.php` | `\Twig_Extensions_Extension_I18n` → `\Twig\Extensions\I18nExtension`, `\Twig_Extension_Debug` → `\Twig\Extension\DebugExtension` |
| `src/Novosga/Model/AbstractAtendimento.php` | `new \DateInterval()` → `new \DateInterval('PT0S')` |

---

## Melhorias Implementadas

### 1. Chamar Senha Específica (Etapa 5)

Permite ao atendente chamar qualquer senha da fila, não apenas a próxima.

- **Configuração**: Unidade > Avançado > "Permitir chamar senha específica da fila"
- **Armazenamento**: tabela `uni_meta`, chave `permitir_chamar_senha_direta`
- **Endpoint**: `POST /modules/sga.atendimento/chamar_especifico/{id}`
- **Controller**: `AtendimentoController::chamar_especifico()`
- **UI**: botão "Chamar" ao lado de cada senha na fila (com title "Chamar esta senha: AAA0001")

### 2. Rechamar Senha (Etapa 6)

Permite rechamar qualquer senha chamada nas últimas 2 horas, republicando no painel.

- **Endpoint**: `POST /modules/sga.atendimento/rechamar/{id}`
- **Controller**: `AtendimentoController::rechamar()`
- **Validação**: verifica se `dataChamada` está dentro das últimas 2 horas

### 3. API REST para Chamar Senha (Etapa 7)

Endpoint para sistemas externos chamarem uma senha via API OAuth2.

- **Endpoint**: `POST /api/atendimentos/{id}/chamar`
- **Autenticação**: Bearer token OAuth2
- **Parâmetros**: `local` (número do guichê)
- **Resposta**: `{ "success": true, "senha": "AAA0001", "guiche": "01", "chamadaEm": "2026-03-24T10:00:00" }`
- **Método**: `ApiV1::chamarSenha()`

### 4. Campo Nome do Cliente (Etapa 8)

Campo opcional para informar o nome do cliente durante o atendimento.

- **Campo já existia**: `AbstractAtendimento::$nomeCliente` (coluna `nm_cli`)
- **Novo endpoint**: `POST /modules/sga.atendimento/salvar_nome_cliente`
- **UI**: campo de texto + botão "Salvar" na tela de atendimento (status 2)

### 5. Painel TV com Polling (Etapa 9)

Painel de senhas para TV/monitor com atualização automática.

- **Página**: `public/painel/index.html`
- **Endpoint**: `GET /api/painel/{unidade}/latest?servicos=1,2,3&lastId=0`
- **Atualização**: polling a cada 2 segundos (compatível com PHP built-in server)
- **Configuração**: pressionar tecla `C` para definir unidade e serviços
- **Interface**: senha atual em destaque + histórico de chamadas anteriores

### 6. Codificação Opcional (Etapa extra)

Permite desabilitar a etapa de codificação de serviços ao encerrar o atendimento.

- **Configuração**: Unidade > Avançado > "Exigir codificação do serviço ao encerrar"
- **Armazenamento**: tabela `uni_meta`, chave `exigir_codificacao`
- **Comportamento**: quando desativado, `encerrar` muda direto para status 8 (ENCERRADO_CODIFICADO) com `dataFim`, pulando a tela de seleção de serviços

### 7. Cronômetro de Atendimento (Etapa extra)

Timer visual no canto superior direito da tela de atendimento.

- **Vermelho** "Espera": tempo desde a chegada do cliente (status 2)
- **Verde** "Atendimento": tempo desde o início do atendimento (status 3)
- **Amarelo** "Codificação": tempo durante a seleção de serviços (status 4)
- **Persistência**: ao recarregar a página, o cronômetro retoma do tempo correto usando `tempoAtendimento` (segundos calculados pelo servidor)
- **Campo JSON**: `tempoAtendimento` adicionado no `AbstractAtendimento::jsonSerialize()`

### 8. Correção de Senhas Duplicadas (Etapa extra)

Reescrita da transação de geração de senhas para eliminar race conditions.

**Problema original:**
- Número da senha calculado fora da transação
- `SELECT` do último número por serviço sem lock
- `commit()` antes de `flush()` (invertido)
- Retry por `OptimisticLockException` com lock pessimista (contraditório)

**Solução:**
- `SELECT FOR UPDATE` nativo no contador da unidade
- Cálculo do `numeroSenha` e `numeroSenhaServico` dentro da transação
- `flush()` antes de `commit()`
- `em->clear()` após rollback

### 9. Sigla de 3 Caracteres (Etapa extra)

Expansão da sigla da senha de 1 para 3 caracteres (ex: `AAA0001`).

**Alterações no banco:**
```sql
ALTER TABLE uni_serv ALTER COLUMN sigla TYPE varchar(3);
-- Para atendimentos e historico: dropar views, alterar, recriar views
-- (ver script na seção de instalação)
ALTER TABLE painel_senha ALTER COLUMN sig_senha TYPE varchar(3);
```

**Alterações no código:**
- `AbstractAtendimento`: annotation `length=1` → `length=3`
- `ServicoUnidade`: annotation `length=1` → `length=3`
- `PainelSenha`: annotation `length=1` → `length=3`
- `Senha::setSigla()`: aceita 1 a 3 caracteres
- `Senha::LENGTH`: 3 → 4 (dígitos do número)
- View da unidade: `maxlength="3"`, CSS com fonte maior

---

## Configurações por Unidade (tabela uni_meta)

| Chave | Tipo | Default | Descrição |
|-------|------|---------|-----------|
| `permitir_chamar_senha_direta` | `0`/`1` | `0` | Habilita botão "Chamar" na fila |
| `exigir_codificacao` | `0`/`1` | `1` | Exige seleção de serviços ao encerrar |

Gerenciadas em: **Unidade > aba Avançado**

---

## API REST

### Autenticação

```bash
# Obter token
curl -X POST http://localhost:8888/api/token \
  -d "grant_type=password&username=admin&password=123456&client_id=novosga-client&client_secret=novosga-secret"
```

### Endpoints

| Método | Rota | Auth | Descrição |
|--------|------|------|-----------|
| GET | `/api/unidades` | Não | Listar unidades |
| GET | `/api/servicos(/:unidade)` | Não | Listar serviços |
| GET | `/api/prioridades` | Não | Listar prioridades |
| GET | `/api/painel/:unidade?servicos=1,2` | Não | Senhas do painel |
| GET | `/api/painel/:unidade/latest?servicos=1&lastId=0` | Não | Polling do painel TV |
| POST | `/api/distribui` | Bearer | Emitir nova senha |
| POST | `/api/atendimentos/:id/chamar` | Bearer | Chamar senha específica |
| GET | `/api/atendimento/:id` | Bearer | Visualizar atendimento |
| GET | `/api/fila/usuario/:unidade/:usuario` | Não | Fila do usuário |
| POST | `/api/token` | Não | Obter token OAuth2 |

---

## Observações Importantes

- **Limpar cache**: ao alterar templates Twig, executar `rm -rf var/cache/*`
- **Vendor patches**: após `composer install`, reaplicar os patches em `vendor/slim/`
- **Senha admin**: MD5 de `123456` = `e10adc3949ba59abbe56e057f20f883e`
- **Schema do banco**: usar o script SQL nativo (`src/Novosga/Install/sql/create/pgsql.sql`), não o `doctrine:schema:create` (este não cria sequences/serial corretamente)
- **PHP built-in server**: single-threaded, não usar SSE/long-polling (usar polling)
- **Idioma do código**: variáveis e métodos em português
