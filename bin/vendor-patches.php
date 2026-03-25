#!/usr/bin/env php
<?php
/**
 * Aplica patches de compatibilidade PHP 8.x nos vendors.
 * Executar após composer install/update.
 *
 * Uso: php bin/vendor-patches.php
 */

$root = dirname(__DIR__);
$patches = 0;

// =============================================================================
// Patch 1: vendor/slim/slim/Slim/Http/Util.php
// Remover get_magic_quotes_gpc() que não existe no PHP 8+
// =============================================================================
$file = "$root/vendor/slim/slim/Slim/Http/Util.php";
if (file_exists($file)) {
    $content = file_get_contents($file);
    // Detectar se ainda usa get_magic_quotes_gpc ou magic_quotes_gpc
    if (strpos($content, 'get_magic_quotes_gpc') !== false || strpos($content, 'magic_quotes_gpc') !== false) {
        $content = preg_replace(
            '/\$strip\s*=\s*is_null\(\$overrideStripSlashes\)\s*\?\s*.*?\s*:\s*\$overrideStripSlashes;/',
            '$strip = is_null($overrideStripSlashes) ? false : $overrideStripSlashes;',
            $content
        );
        file_put_contents($file, $content);
        echo "[OK] Patch Slim/Http/Util.php - get_magic_quotes_gpc removido\n";
        $patches++;
    } else {
        echo "[--] Slim/Http/Util.php - já patcheado\n";
    }
}

// =============================================================================
// Patch 2: vendor/slim/views/Twig.php
// Atualizar para namespaces Twig 2.x e usar $env->load() em vez de loadTemplate()
// =============================================================================
$file = "$root/vendor/slim/views/Twig.php";
if (file_exists($file)) {
    $content = file_get_contents($file);
    $changed = false;

    // Remover Twig_Autoloader::register() se existir
    if (strpos($content, 'Twig_Autoloader') !== false) {
        $content = preg_replace('/.*Twig_Autoloader.*\n/', '', $content);
        $changed = true;
    }

    // Twig_Loader_Filesystem -> \Twig\Loader\FilesystemLoader
    if (preg_match('/\bTwig_Loader_Filesystem\b/', $content)) {
        $content = preg_replace('/\bTwig_Loader_Filesystem\b/', '\\Twig\\Loader\\FilesystemLoader', $content);
        $changed = true;
    }

    // Twig_Environment -> \Twig\Environment (não pegar \Twig\Environment já corrigido)
    if (preg_match('/\bTwig_Environment\b/', $content)) {
        $content = preg_replace('/\bTwig_Environment\b/', '\\Twig\\Environment', $content);
        $changed = true;
    }

    // loadTemplate -> load (só no código, não em comentários)
    if (preg_match('/->loadTemplate\(/', $content)) {
        $content = str_replace('->loadTemplate(', '->load(', $content);
        $changed = true;
    }

    if ($changed) {
        file_put_contents($file, $content);
        echo "[OK] Patch slim/views/Twig.php - namespaces Twig 2.x\n";
        $patches++;
    } else {
        echo "[--] slim/views/Twig.php - já patcheado\n";
    }
}

// =============================================================================
// Patch 3: vendor/slim/views/TwigExtension.php
// Atualizar classes Twig 1.x para namespaces Twig 2.x
// =============================================================================
$file = "$root/vendor/slim/views/TwigExtension.php";
if (file_exists($file)) {
    $content = file_get_contents($file);
    $changed = false;

    if (preg_match('/\bTwig_Extension\b/', $content)) {
        $content = preg_replace('/\\\\?Twig_Extension\b/', '\\Twig\\Extension\\AbstractExtension', $content);
        $changed = true;
    }

    if (preg_match('/\bTwig_SimpleFunction\b/', $content)) {
        $content = preg_replace('/\bTwig_SimpleFunction\b/', '\\Twig\\TwigFunction', $content);
        $changed = true;
    }

    // Caso use getName() que foi removido no Twig 2.x — não obrigatório mas limpo
    if (strpos($content, 'function getName') !== false && strpos($content, 'AbstractExtension') !== false) {
        // já usa AbstractExtension, getName é herdado — não precisa mexer
    }

    if ($changed) {
        file_put_contents($file, $content);
        echo "[OK] Patch slim/views/TwigExtension.php - namespaces Twig 2.x\n";
        $patches++;
    } else {
        echo "[--] slim/views/TwigExtension.php - já patcheado\n";
    }
}

echo "\n$patches patch(es) aplicado(s).\n";
