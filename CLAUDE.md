# Prompt — NovoSGA v1.5.2: Instalação, Compatibilidade PHP 7.4 e Melhorias

## Contexto do projeto

Vamos trabalhar com o **NovoSGA v1.5.2**, um sistema de gerenciamento de fila de atendimento (senhas) open source, escrito em **PHP com Symfony 3.x** e **Doctrine ORM**, com suporte nativo a **PostgreSQL**.

O repositório original está em: https://github.com/novosga/novosga/releases/tag/v1.5.2

O objetivo é:

1. Baixar e configurar o projeto
2. Atualizar as dependências para rodar em **PHP 7.4**
3. Corrigir incompatibilidades com PHP 7.4 e Symfony 3.4
4. Implementar melhorias funcionais listadas abaixo

---

## ETAPA 1 — Obter o projeto

Clone o repositório na tag v1.5.2:

```bash
git clone --branch v1.5.2 --depth 1 https://github.com/novosga/novosga.git novosga
cd novosga
```

Antes de qualquer coisa, explore a estrutura completa do projeto:

- Leia o `composer.json` raiz
- Leia o `app/AppKernel.php` para mapear todos os bundles registrados
- Liste todos os diretórios em `src/` e `app/`
- Leia o `app/config/config.yml` e `app/config/parameters.yml.dist`
- Leia o `web/app.php` e `web/app_dev.php`
- Liste os controllers existentes em cada Bundle dentro de `src/`

Documente a estrutura encontrada antes de fazer qualquer modificação.

---

## ETAPA 2 — Atualizar composer.json para PHP 7.4

Abra o `composer.json` e faça as seguintes alterações:

### 2.1 Versão do PHP

Altere o requisito de PHP para aceitar 7.4:

```json
"php": ">=7.1 <8.0"
```

### 2.2 Travar Symfony na 3.4 LTS

Localize todas as dependências `symfony/*` e trave na versão 3.4 LTS que é a mais estável para PHP 7.4:

```json
"symfony/symfony": "3.4.*"
```

Se houver outros pacotes symfony separados (não o metapacote), trave todos em `"3.4.*"`.

### 2.3 Doctrine ORM

Trave o Doctrine em versões compatíveis com PHP 7.4 e Symfony 3.4:

```json
"doctrine/orm": "^2.7",
"doctrine/dbal": "^2.10",
"doctrine/doctrine-bundle": "^1.12"
```

### 2.4 Twig

```json
"twig/twig": "^1.44 || ^2.12"
```

### 2.5 Remover ou atualizar pacotes conflitantes

Verifique se existem no composer.json os seguintes pacotes problemáticos em PHP 7.4 e atualize se necessário:

- `sensio/distribution-bundle` → remover ou atualizar para `^5.0`
- `sensio/framework-extra-bundle` → atualizar para `^5.5`
- `sensio/generator-bundle` → apenas dev, pode remover
- `incenteev/composer-parameter-handler` → manter se existir, compatível

### 2.6 Após editar, rodar:

```bash
composer update --no-scripts -W
```

Se houver conflitos de dependências, resolva um por um, priorizando manter Symfony em 3.4.x.

---

## ETAPA 3 — Corrigir incompatibilidades com PHP 7.4

Após instalar as dependências, execute a aplicação e corrija os erros encontrados. Os mais comuns nessa migração de PHP 5.x → 7.4 com Symfony 3.x são:

### 3.1 Tipagem de exceções

PHP 7+ separou `Error` de `Exception`. Procure em todo o código por:

```php
catch (Exception $e)
```

E onde necessário, troque por:

```php
catch (\Throwable $e)
```

Isso se aplica especialmente a handlers globais de erro. Use `grep -r "catch (Exception" src/` para localizar.

### 3.2 Funções removidas/depreciadas

Procure e substitua:

- `each()` → reescrever com `foreach`
- `create_function()` → reescrever com closure `function() {}`
- `ereg()`, `eregi()` → substituir por `preg_match()`
- `split()` → substituir por `explode()` ou `preg_split()`
- `mysql_*` → não deve existir (usa Doctrine), mas verificar

Use:

```bash
grep -rn "each(" src/ web/
grep -rn "create_function" src/
grep -rn "ereg\b" src/
```

### 3.3 Construtor de objetos não tipados (PHP 7.4 strict)

Se encontrar propriedades de classe sem tipo inicializadas com `null` e depois usadas sem verificação, adicione inicialização no construtor ou declare como `?TipoOuNullable`.

### 3.4 Twig deprecations

Com Twig 2.x, algumas funções do Twig 1.x mudaram. Verifique as views em `src/*/Resources/views/` e corrija:

- `{% spaceless %}` → envolver com `{% apply spaceless %}`
- Filtros não encontrados → verificar extensões registradas

### 3.5 Limpar cache após correções

```bash
php app/console cache:clear --env=dev
php app/console cache:clear --env=prod
```

---

## ETAPA 4 — Configurar para PostgreSQL

Edite o arquivo `app/config/parameters.yml` (copiar do `.dist` se não existir):

```yaml
parameters:
  database_driver: pdo_pgsql
  database_host: 127.0.0.1
  database_port: 5432
  database_name: novosga
  database_user: novosga
  database_password: novosga
  mailer_transport: smtp
  mailer_host: 127.0.0.1
  mailer_user: ~
  mailer_password: ~
  secret: TROQUE_POR_UMA_STRING_ALEATORIA_LONGA
```

Verificar se o Doctrine DBAL tem o driver `pdo_pgsql` mapeado. No `app/config/config.yml`, a seção doctrine deve estar assim:

```yaml
doctrine:
  dbal:
    driver: "%database_driver%"
    host: "%database_host%"
    port: "%database_port%"
    dbname: "%database_name%"
    user: "%database_user%"
    password: "%database_password%"
    charset: UTF8
```

Rodar as migrations/schema:

```bash
php app/console doctrine:schema:create
# ou se já existir banco:
php app/console doctrine:schema:update --force
```

---

## ETAPA 5 — Melhoria: Chamar senha específica diretamente

Esta é a melhoria principal. Atualmente o sistema só permite "chamar próximo" (o primeiro da fila). Queremos permitir que o atendente clique em qualquer senha na fila e a chame diretamente.

### 5.1 Localizar o AtendimentoBundle

Abra e leia completamente os seguintes arquivos:

- `src/NovoSGA/AtendimentoBundle/Controller/AtendimentoController.php`
- `src/NovoSGA/AtendimentoBundle/Resources/views/` (todas as views)
- `src/NovoSGA/CoreBundle/Service/AtendimentoServiceInterface.php` (ou similar)
- `src/NovoSGA/CoreBundle/Entity/Atendimento.php`
- `src/NovoSGA/CoreBundle/Entity/Senha.php` (ou nome equivalente)

Entenda completamente o fluxo de `chamarProximo()` antes de implementar qualquer coisa.

### 5.2 Adicionar configuração por unidade

No bundle de configurações (`ConfiguracaoBundle` ou similar), adicionar campo booleano:

```
permitir_chamar_senha_direta (boolean, default: false)
```

- Adicionar na entidade de configuração da unidade
- Adicionar migration/update no schema
- Adicionar campo no formulário de configuração da unidade
- Salvar e recuperar via repositório

### 5.3 Novo endpoint no AtendimentoController

Adicionar action `chamarEspecificoAction` no `AtendimentoController`:

```php
/**
 * @Route("/chamar/{id}", name="novosga_atendimento_chamar_especifico", methods={"POST"})
 */
public function chamarEspecificoAction(Request $request, $id)
{
    // 1. Verificar se configuração permite chamar direto (verificar flag da unidade)
    // 2. Buscar o Atendimento/Senha pelo $id
    // 3. Verificar se a senha pertence à unidade atual do usuário logado
    // 4. Verificar se a senha está no status correto (aguardando)
    // 5. Chamar a mesma lógica que chamarProximo usa, mas passando a senha específica
    // 6. Retornar JSON com resultado (padrão do sistema)
}
```

Seguir exatamente o padrão de retorno JSON que os outros endpoints do AtendimentoController usam.

### 5.4 Adicionar botão na view da fila

Na view que exibe a fila de espera do módulo Atendimento, adicionar para cada senha na lista um botão "Chamar esta" que só aparece quando a configuração `permitir_chamar_senha_direta` estiver ativa. O botão deve fazer POST via AJAX para o novo endpoint.

---

## ETAPA 6 — Melhoria: Rechamar senha (chamar novamente qualquer senha recente)

Adicionar a capacidade de rechamar qualquer senha que foi chamada nas últimas 2 horas, não apenas a última chamada.

### 6.1 No MonitorBundle ou AtendimentoBundle

Criar endpoint:

```
POST /atendimento/rechamar/{id}
```

Que republica o evento de chamada da senha para o painel, sem alterar o status dela.

### 6.2 Na view

Exibir na área de "histórico de chamadas" (se existir) um botão "Rechamar" por senha.

---

## ETAPA 7 — Melhoria: API para chamar senha externamente

O sistema já tem uma `ApiBundle` com OAuth2. Adicionar endpoint REST para que sistemas externos (como outro sistema do cartório) possam chamar uma senha via API.

### 7.1 Localizar o ApiBundle

Leia todos os controllers e rotas de `src/NovoSGA/ApiBundle/`.

### 7.2 Adicionar rota de chamada de senha

```
POST /api/v1/atendimentos/{id}/chamar
```

Autenticação: Bearer token OAuth2 (padrão já existente no ApiBundle).

Retorno JSON:

```json
{
  "success": true,
  "senha": "A001",
  "guiche": "01",
  "chamadaEm": "2026-03-24T10:00:00"
}
```

Reaproveitar a mesma lógica do AtendimentoController, sem duplicar código — extrair para um Service se necessário.

---

## ETAPA 8 — Melhoria: Campo "nome do cliente" opcional no Atendimento

Adicionar a possibilidade de informar o nome do cliente no momento de chamar a senha (ou no início do atendimento).

### 8.1 Na entidade Atendimento

Verificar se já existe campo `nomeCliente` ou similar. Se não existir, adicionar:

```php
/**
 * @ORM\Column(name="nome_cliente", type="string", length=100, nullable=true)
 */
private $nomeCliente;
```

Gerar migration e atualizar schema.

### 8.2 No painel de atendimento

Exibir um campo de texto opcional "Nome do cliente" logo antes ou após chamar a senha. O preenchimento deve ser opcional — não bloquear o fluxo.

### 8.3 No Monitor

Se o campo estiver preenchido, exibir o nome junto à senha no monitor interno (não no painel TV, apenas no monitor do atendente).

---

## ETAPA 9 — Melhoria: Painel TV com auto-refresh via SSE

O painel atual usa polling (requisições periódicas). Melhorar para usar **Server-Sent Events (SSE)** para atualização em tempo real.

### 9.1 Criar endpoint SSE

No bundle mais adequado (ApiBundle ou um novo PainelBundle), criar:

```
GET /painel/stream
```

Que retorna `Content-Type: text/event-stream` e envia evento sempre que uma senha for chamada.

Formato do evento:

```
data: {"senha":"A001","guiche":"1","servico":"Atendimento Geral","chamadaEm":"10:00"}

```

### 9.2 Verificar se já existe mecanismo de evento

Antes de implementar, verificar se o sistema já usa algum mecanismo de notificação (ex: tabela de eventos no banco, pub/sub). Adaptar em vez de criar do zero.

### 9.3 No painel web (`web/painel/`)

Substituir o polling por `EventSource`:

```javascript
const source = new EventSource("/painel/stream");
source.onmessage = function (event) {
  const data = JSON.parse(event.data);
  exibirSenha(data);
};
```

---

## ETAPA 10 — Verificação final e testes manuais

Após implementar tudo, fazer checklist de verificação:

```
[ ] composer install roda sem erros
[ ] php app/console doctrine:schema:validate passa
[ ] Aplicação sobe sem erros no app_dev.php
[ ] Login funciona
[ ] Emissão de senha (TriagemBundle) funciona
[ ] Chamar próximo funciona
[ ] Chamar senha específica funciona (quando habilitado)
[ ] API retorna 401 sem token
[ ] API retorna senha chamada com token válido
[ ] Painel TV exibe a última senha chamada
[ ] SSE recebe eventos em tempo real
[ ] Cache limpo em prod
```

Rodar:

```bash
php app/console cache:clear --env=prod --no-debug
php app/console assets:install web --symlink
```

---

## Observações gerais para implementação

- **Idioma do código**: variáveis, métodos e comentários em **português**, exceto chaves de API JSON
- **Não duplicar lógica**: sempre verificar se já existe um Service ou método que faz o que precisa antes de criar novo
- **Padrão Symfony 3**: usar injeção de dependência via container, anotações de rota `@Route`, formulários com FormType, eventos com EventDispatcher
- **Não quebrar fluxo existente**: as melhorias são aditivas — o sistema original deve continuar funcionando como estava
- **Segurança**: toda ação de chamar senha deve verificar se o usuário logado tem permissão na unidade atual
- **PostgreSQL**: não usar funções ou sintaxe exclusiva de MySQL. Usar Doctrine DBAL/ORM sempre que possível

---

## Resultado esperado

Ao final, o projeto deve:

- Rodar em PHP 7.4 com PostgreSQL
- Ter o fluxo original 100% funcional
- Permitir chamar senha específica da fila (quando habilitado por configuração)
- Ter API REST para integração externa
- Ter painel TV com atualização via SSE
- Código limpo, sem erros de depreciação no log
