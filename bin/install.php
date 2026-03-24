#!/usr/bin/env php
<?php
/**
 * NovoSGA - Instalação automatizada
 *
 * Uso:
 *   php bin/install.php [--db-host=127.0.0.1] [--db-port=5432] [--db-name=novosga]
 *                       [--db-user=novosga] [--db-pass=novosga]
 *                       [--admin-user=admin] [--admin-pass=123456]
 *                       [--admin-nome=Admin] [--admin-sobrenome=Istrador]
 *
 * Faz tudo automaticamente:
 *   1. Cria o schema do banco (PostgreSQL)
 *   2. Instala todos os módulos
 *   3. Insere dados iniciais (prioridades, grupo, cargo, unidade, serviço)
 *   4. Cria o usuário administrador
 *   5. Gera o arquivo config/database.php
 *   6. Cria cliente OAuth2 para API/Totem
 */

require_once __DIR__ . '/../bootstrap.php';

// Parâmetros com defaults
$opts = getopt('', array(
    'db-host::', 'db-port::', 'db-name::', 'db-user::', 'db-pass::',
    'admin-user::', 'admin-pass::', 'admin-nome::', 'admin-sobrenome::',
    'sigla::', 'servico::',
));

$dbHost   = isset($opts['db-host']) ? $opts['db-host'] : '127.0.0.1';
$dbPort   = isset($opts['db-port']) ? $opts['db-port'] : '5432';
$dbName   = isset($opts['db-name']) ? $opts['db-name'] : 'novosga';
$dbUser   = isset($opts['db-user']) ? $opts['db-user'] : 'novosga';
$dbPass   = isset($opts['db-pass']) ? $opts['db-pass'] : 'novosga';

$adminUser = isset($opts['admin-user']) ? $opts['admin-user'] : 'admin';
$adminPass = isset($opts['admin-pass']) ? $opts['admin-pass'] : '123456';
$adminNome = isset($opts['admin-nome']) ? $opts['admin-nome'] : 'Admin';
$adminSobrenome = isset($opts['admin-sobrenome']) ? $opts['admin-sobrenome'] : 'Istrador';

$sigla   = isset($opts['sigla']) ? $opts['sigla'] : 'AAA';
$servico = isset($opts['servico']) ? $opts['servico'] : 'Atendimento';

echo "=== NovoSGA - Instalação Automatizada ===\n\n";

// 1. Gerar config/database.php
echo "[1/6] Gerando config/database.php...\n";
$configDir = NOVOSGA_CONFIG;
$dbConfig = array(
    'driver'   => 'pdo_pgsql',
    'host'     => $dbHost,
    'port'     => (int) $dbPort,
    'dbname'   => $dbName,
    'user'     => $dbUser,
    'password' => $dbPass,
    'charset'  => 'UTF8',
);

$configContent = "<?php\nreturn " . var_export($dbConfig, true) . ";\n";
file_put_contents($configDir . '/database.php', $configContent);
echo "  OK\n";

// 1b. Gerar config/app.php se não existir
if (!file_exists($configDir . '/app.php')) {
    file_put_contents($configDir . '/app.php', "<?php\nreturn array(\n    'auth.factory' => null,\n    'template.dir' => null,\n    'hooks' => array(),\n    'queue' => array(\n        'ordering' => null,\n        'limits' => array(\n            'priority' => 0,\n        ),\n    ),\n);\n");
}

// 2. Criar schema
echo "[2/6] Criando schema do banco de dados...\n";
try {
    $pdo = new PDO("pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verifica se já tem tabelas
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'usuarios'");
    $existe = (int) $stmt->fetchColumn() > 0;

    if ($existe) {
        echo "  Schema já existe, pulando...\n";
    } else {
        $sqlFile = __DIR__ . '/../src/Novosga/Install/sql/create/pgsql.sql';
        if (!file_exists($sqlFile)) {
            die("  ERRO: Arquivo SQL não encontrado: $sqlFile\n");
        }
        $sql = file_get_contents($sqlFile);
        $pdo->exec($sql);
        // Expandir sigla de 1 para 3 caracteres
        $pdo->exec("DROP VIEW IF EXISTS view_historico_atendimentos CASCADE");
        $pdo->exec("DROP VIEW IF EXISTS view_historico_atend_codif CASCADE");
        $pdo->exec("DROP VIEW IF EXISTS view_historico_atend_meta CASCADE");
        $pdo->exec("ALTER TABLE uni_serv ALTER COLUMN sigla TYPE varchar(3)");
        $pdo->exec("ALTER TABLE atendimentos ALTER COLUMN sigla_senha TYPE varchar(3)");
        $pdo->exec("ALTER TABLE historico_atendimentos ALTER COLUMN sigla_senha TYPE varchar(3)");
        $pdo->exec("ALTER TABLE painel_senha ALTER COLUMN sig_senha TYPE varchar(3)");
        // Recriar views
        $pdo->exec("CREATE VIEW view_historico_atend_codif AS SELECT atend_codif.atendimento_id, atend_codif.servico_id, atend_codif.valor_peso FROM atend_codif UNION ALL SELECT historico_atend_codif.atendimento_id, historico_atend_codif.servico_id, historico_atend_codif.valor_peso FROM historico_atend_codif");
        $pdo->exec("CREATE VIEW view_historico_atend_meta AS SELECT atend_meta.atendimento_id, atend_meta.name, atend_meta.value FROM atend_meta UNION ALL SELECT historico_atend_meta.atendimento_id, historico_atend_meta.name, historico_atend_meta.value FROM historico_atend_meta");
        $pdo->exec("CREATE VIEW view_historico_atendimentos AS SELECT id, unidade_id, usuario_id, usuario_tri_id, servico_id, prioridade_id, atendimento_id, status, sigla_senha, num_senha, num_senha_serv, nm_cli, num_local, dt_cheg, dt_cha, dt_ini, dt_fim, ident_cli FROM atendimentos UNION ALL SELECT id, unidade_id, usuario_id, usuario_tri_id, servico_id, prioridade_id, atendimento_id, status, sigla_senha, num_senha, num_senha_serv, nm_cli, num_local, dt_cheg, dt_cha, dt_ini, dt_fim, ident_cli FROM historico_atendimentos");
        echo "  OK (schema criado + sigla expandida para 3 caracteres)\n";
    }
} catch (PDOException $e) {
    die("  ERRO ao conectar ao banco: " . $e->getMessage() . "\n");
}

// 3. Instalar módulos
echo "[3/6] Instalando módulos...\n";
$db = \Novosga\Config\DatabaseConfig::getInstance($dbConfig);
$em = $db->createEntityManager();
$moduloService = new \Novosga\Service\ModuloService($em);

$modules = glob(MODULES_PATH . '/sga/*', GLOB_ONLYDIR);
$instalados = 0;
foreach ($modules as $dir) {
    $key = 'sga.' . basename($dir);
    try {
        // Verifica se já está instalado
        $modulo = $em->createQuery("SELECT e FROM Novosga\\Model\\Modulo e WHERE e.chave = :key")
            ->setParameter('key', $key)
            ->getOneOrNullResult();
        if (!$modulo) {
            $moduloService->install($dir, $key, 1);
            $instalados++;
        }
    } catch (Exception $e) {
        echo "  Aviso ({$key}): " . $e->getMessage() . "\n";
    }
}
echo "  {$instalados} módulos instalados\n";

// Módulo vetor.panel
$vetorDir = MODULES_PATH . '/vetor/panel';
if (is_dir($vetorDir)) {
    try {
        $modulo = $em->createQuery("SELECT e FROM Novosga\\Model\\Modulo e WHERE e.chave = 'vetor.panel'")
            ->getOneOrNullResult();
        if (!$modulo) {
            $moduloService->install($vetorDir, 'vetor.panel', 1);
            echo "  Módulo vetor.panel instalado\n";
        }
    } catch (Exception $e) {
        // ignora
    }
}

// 4. Inserir dados iniciais
echo "[4/6] Inserindo dados iniciais...\n";
$conn = $em->getConnection();

try {
    // Prioridades
    $stmt = $conn->query("SELECT COUNT(*) FROM prioridades");
    if ((int) $stmt->fetchColumn() === 0) {
        $conn->exec("INSERT INTO prioridades (nome, descricao, peso, status) VALUES ('Normal', 'Atendimento normal', 0, 1)");
        $conn->exec("INSERT INTO prioridades (nome, descricao, peso, status) VALUES ('Idoso', 'Prioritário idosos', 1, 1)");
        $conn->exec("INSERT INTO prioridades (nome, descricao, peso, status) VALUES ('Gestante', 'Prioritário gestantes', 1, 1)");
        $conn->exec("INSERT INTO prioridades (nome, descricao, peso, status) VALUES ('Deficiente', 'Prioritário deficientes', 1, 1)");
        echo "  Prioridades criadas\n";
    }

    // Grupo
    $stmt = $conn->query("SELECT COUNT(*) FROM grupos");
    if ((int) $stmt->fetchColumn() === 0) {
        $conn->exec("INSERT INTO grupos (nome, descricao, esquerda, direita, nivel) VALUES ('Raiz', 'Grupo raiz', 1, 4, 0)");
        echo "  Grupo criado\n";
    }

    // Cargo
    $stmt = $conn->query("SELECT COUNT(*) FROM cargos");
    if ((int) $stmt->fetchColumn() === 0) {
        $conn->exec("INSERT INTO cargos (nome, descricao, esquerda, direita, nivel) VALUES ('Administrador', 'Administrador do sistema', 1, 2, 0)");
        echo "  Cargo criado\n";
    }

    // Permissões do cargo admin em todos os módulos
    $conn->exec("INSERT INTO cargos_mod_perm (cargo_id, modulo_id, permissao) SELECT (SELECT id FROM cargos LIMIT 1), id, 3 FROM modulos WHERE id NOT IN (SELECT modulo_id FROM cargos_mod_perm)");

    // Local
    $stmt = $conn->query("SELECT COUNT(*) FROM locais");
    if ((int) $stmt->fetchColumn() === 0) {
        $conn->exec("INSERT INTO locais (nome) VALUES ('Guichê')");
        echo "  Local criado\n";
    }

    // Unidade
    $stmt = $conn->query("SELECT COUNT(*) FROM unidades");
    if ((int) $stmt->fetchColumn() === 0) {
        $conn->exec("INSERT INTO unidades (grupo_id, codigo, nome, status, stat_imp, msg_imp) VALUES ((SELECT id FROM grupos LIMIT 1), '1', 'Unidade Padrão', 1, 0, '')");
        echo "  Unidade criada\n";
    }

    // Serviço
    $stmt = $conn->query("SELECT COUNT(*) FROM servicos");
    if ((int) $stmt->fetchColumn() === 0) {
        $conn->exec("INSERT INTO servicos (descricao, nome, status, peso) VALUES ('Atendimento geral', " . $conn->quote($servico) . ", 1, 1)");
        $conn->exec("INSERT INTO uni_serv (unidade_id, servico_id, local_id, sigla, status, peso) VALUES ((SELECT id FROM unidades LIMIT 1), (SELECT id FROM servicos LIMIT 1), (SELECT id FROM locais LIMIT 1), " . $conn->quote($sigla) . ", 1, 1)");
        echo "  Serviço criado (sigla: {$sigla})\n";
    }

    // Versão
    $stmt = $conn->query("SELECT COUNT(*) FROM config WHERE chave = 'version'");
    if ((int) $stmt->fetchColumn() === 0) {
        $conn->exec("INSERT INTO config (chave, valor, tipo) VALUES ('version', '" . \Novosga\App::VERSION . "', 1)");
    }

} catch (Exception $e) {
    echo "  Aviso: " . $e->getMessage() . "\n";
}

// 5. Criar usuário admin
echo "[5/6] Criando usuário administrador...\n";
$stmt = $conn->query("SELECT COUNT(*) FROM usuarios WHERE login = " . $conn->quote($adminUser));
if ((int) $stmt->fetchColumn() === 0) {
    $senhaHash = \Novosga\Security::passEncode($adminPass);
    $conn->exec("INSERT INTO usuarios (login, nome, sobrenome, senha, ult_acesso, status, session_id) VALUES (" . $conn->quote($adminUser) . ", " . $conn->quote($adminNome) . ", " . $conn->quote($adminSobrenome) . ", '{$senhaHash}', NULL, 1, '')");
    $conn->exec("INSERT INTO usu_grup_cargo (usuario_id, grupo_id, cargo_id) SELECT id, (SELECT id FROM grupos LIMIT 1), (SELECT id FROM cargos LIMIT 1) FROM usuarios WHERE login = " . $conn->quote($adminUser));
    try {
        $conn->exec("INSERT INTO usu_serv (unidade_id, servico_id, usuario_id) SELECT (SELECT id FROM unidades LIMIT 1), (SELECT id FROM servicos LIMIT 1), id FROM usuarios WHERE login = " . $conn->quote($adminUser));
    } catch (Exception $e) {
        // pode falhar se o serviço/unidade não existir ainda
    }
    echo "  Usuário criado: {$adminUser} / {$adminPass}\n";
} else {
    echo "  Usuário '{$adminUser}' já existe, pulando...\n";
}

// 6. Cliente OAuth2
echo "[6/6] Configurando OAuth2...\n";
$stmt = $conn->query("SELECT COUNT(*) FROM oauth_clients WHERE client_id = 'novosga-client'");
if ((int) $stmt->fetchColumn() === 0) {
    $conn->exec("INSERT INTO oauth_clients (client_id, client_secret, redirect_uri, grant_types) VALUES ('novosga-client', 'novosga-secret', '', 'password refresh_token')");
    echo "  Cliente OAuth2 criado\n";
} else {
    echo "  Cliente OAuth2 já existe\n";
}

echo "\n=== Instalação concluída! ===\n";
echo "\nAcesso:\n";
echo "  URL:    http://localhost:8888\n";
echo "  Login:  {$adminUser}\n";
echo "  Senha:  {$adminPass}\n";
echo "\nAPI OAuth2:\n";
echo "  Client ID:     novosga-client\n";
echo "  Client Secret: novosga-secret\n";
echo "\nPróximos passos:\n";
echo "  1. Inicie o servidor: php -S 0.0.0.0:8888 -t public\n";
echo "  2. Acesse e troque a senha do admin\n";
echo "  3. Configure a unidade em Configuração > Avançado\n";
