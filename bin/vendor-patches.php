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

    // Remover bloco inteiro do Twig_Autoloader (if + require + register)
    if (strpos($content, 'Twig_Autoloader') !== false) {
        // Remove o bloco: if (!class_exists(...)) { require_once ...; } \Twig_Autoloader::register();
        $content = preg_replace(
            '/\s*\/\*\*\s*\n\s*\*\s*Check if Twig_Autoloader.*?\*\/\s*\n' .
            '\s*if\s*\(\!class_exists.*?Twig_Autoloader.*?\)\s*\{\s*\n' .
            '\s*require_once.*?Autoloader\.php.*?\n' .
            '\s*\}\s*\n' .
            '\s*\\\\?Twig_Autoloader::register\(\);\s*\n/s',
            "\n",
            $content
        );
        // Fallback: remove any remaining Twig_Autoloader lines
        $content = preg_replace('/.*Twig_Autoloader.*\n/', '', $content);
        $changed = true;
    }

    // \Twig_Loader_Filesystem -> \Twig\Loader\FilesystemLoader (com barra na frente)
    if (strpos($content, '\Twig_Loader_Filesystem') !== false) {
        $content = str_replace('\Twig_Loader_Filesystem', '\Twig\Loader\FilesystemLoader', $content);
        $changed = true;
    }
    // Twig_Loader_Filesystem -> \Twig\Loader\FilesystemLoader (sem barra na frente)
    if (strpos($content, 'Twig_Loader_Filesystem') !== false) {
        $content = str_replace('Twig_Loader_Filesystem', '\Twig\Loader\FilesystemLoader', $content);
        $changed = true;
    }

    // \Twig_Environment -> \Twig\Environment (com barra na frente)
    if (strpos($content, '\Twig_Environment') !== false) {
        $content = str_replace('\Twig_Environment', '\Twig\Environment', $content);
        $changed = true;
    }
    // Twig_Environment -> \Twig\Environment (sem barra na frente)
    if (strpos($content, 'Twig_Environment') !== false) {
        $content = str_replace('Twig_Environment', '\Twig\Environment', $content);
        $changed = true;
    }

    // ->loadTemplate( -> ->load(
    if (strpos($content, '->loadTemplate(') !== false) {
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

    // \Twig_Extension -> \Twig\Extension\AbstractExtension (com barra)
    if (strpos($content, '\Twig_Extension') !== false) {
        $content = str_replace('\Twig_Extension', '\Twig\Extension\AbstractExtension', $content);
        $changed = true;
    }
    // Twig_Extension -> \Twig\Extension\AbstractExtension (sem barra)
    if (strpos($content, 'Twig_Extension') !== false) {
        $content = str_replace('Twig_Extension', '\Twig\Extension\AbstractExtension', $content);
        $changed = true;
    }

    // \Twig_SimpleFunction -> \Twig\TwigFunction (com barra)
    if (strpos($content, '\Twig_SimpleFunction') !== false) {
        $content = str_replace('\Twig_SimpleFunction', '\Twig\TwigFunction', $content);
        $changed = true;
    }
    // Twig_SimpleFunction -> \Twig\TwigFunction (sem barra)
    if (strpos($content, 'Twig_SimpleFunction') !== false) {
        $content = str_replace('Twig_SimpleFunction', '\Twig\TwigFunction', $content);
        $changed = true;
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
