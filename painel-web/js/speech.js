/**
 * Speech.js - Vocalização de senhas usando Web Speech API
 * Baseado em useSpeech.js do projeto Senha
 */
var SpeechService = (function() {
    "use strict";

    var isSuportado = 'speechSynthesis' in window;
    var isFalando = false;

    // Números por extenso em português
    var unidades = ['zero', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];
    var dezenas = ['dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];
    var dezADez = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
    var centenas = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];

    function numeroParaExtenso(num) {
        num = parseInt(num, 10);
        if (isNaN(num)) return '';
        if (num === 0) return 'zero';
        if (num === 100) return 'cem';
        if (num < 10) return unidades[num];
        if (num >= 10 && num < 20) return dezenas[num - 10];
        if (num >= 20 && num < 100) {
            var dezena = Math.floor(num / 10);
            var unidade = num % 10;
            return unidade === 0 ? dezADez[dezena] : dezADez[dezena] + ' e ' + unidades[unidade];
        }
        if (num >= 100 && num < 1000) {
            var centena = Math.floor(num / 100);
            var resto = num % 100;
            if (resto === 0) return centenas[centena];
            return centenas[centena] + ' e ' + numeroParaExtenso(resto);
        }
        if (num >= 1000) {
            var milhar = Math.floor(num / 1000);
            var resto = num % 1000;
            var prefixo = milhar === 1 ? 'mil' : numeroParaExtenso(milhar) + ' mil';
            if (resto === 0) return prefixo;
            return prefixo + ' e ' + numeroParaExtenso(resto);
        }
        return num.toString();
    }

    // Processa código da senha letra por letra / dígito por dígito
    function processarCodigoSenha(codigo) {
        codigo = String(codigo);
        var resultado = '';
        for (var i = 0; i < codigo.length; i++) {
            var char = codigo[i];
            if (/\d/.test(char)) {
                resultado += unidades[parseInt(char)] + ' ';
            } else {
                resultado += char + ' ';
            }
        }
        return resultado.trim();
    }

    function falar(texto, options, callback) {
        if (!isSuportado) {
            if (callback) callback();
            return;
        }

        options = options || {};
        window.speechSynthesis.cancel();

        var utterance = new SpeechSynthesisUtterance(texto);
        utterance.lang = options.lang || 'pt-BR';
        utterance.rate = options.rate || 0.9;
        utterance.pitch = options.pitch || 1.0;
        utterance.volume = options.volume || 1.0;

        utterance.onstart = function() {
            isFalando = true;
        };

        utterance.onend = function() {
            isFalando = false;
            if (callback) callback();
        };

        utterance.onerror = function() {
            isFalando = false;
            if (callback) callback();
        };

        window.speechSynthesis.speak(utterance);
    }

    function cancelar() {
        if (isSuportado) {
            window.speechSynthesis.cancel();
        }
        isFalando = false;
    }

    /**
     * Vocaliza uma senha com guichê
     * @param {object} senha - { sigla, numero, local, numeroLocal, length }
     * @param {object} params - { zeros, local, lang, mode }
     * @param {function} callback - chamado ao terminar
     *
     * mode: 'extenso' (padrão) = "Senha cento e vinte e três, guichê um"
     *       'soletrado' = "Senha A A A zero um dois três, guichê um"
     */
    function falarSenha(senha, params, callback) {
        if (!isSuportado) {
            if (callback) callback();
            return;
        }

        params = params || {};
        var mode = params.mode || 'extenso';
        var lang = params.lang || 'pt';
        var langCode = lang === 'pt' ? 'pt-BR' : lang === 'es' ? 'es-ES' : 'en-US';

        var texto = '';

        if (mode === 'extenso') {
            // "Senha cento e vinte e três"
            var numero = parseInt(senha.numero, 10);
            var sigla = String(senha.sigla || '').toUpperCase();
            var siglaTexto = '';
            for (var i = 0; i < sigla.length; i++) {
                siglaTexto += sigla[i] + ' ';
            }
            texto = 'Senha ' + siglaTexto + numeroParaExtenso(numero);
        } else {
            // "Senha A A A zero um dois três"
            var codigoCompleto = params.zeros
                ? $.painel().format(senha)
                : (senha.sigla || '') + senha.numero;
            texto = 'Senha ' + processarCodigoSenha(codigoCompleto);
        }

        if (params.local && senha.local) {
            var localNome = String(senha.local).toLowerCase();
            var isCaixa = localNome.indexOf('caixa') >= 0;
            if (isCaixa) {
                texto += ', dirija-se ao caixa ' + senha.numeroLocal + ' por favor';
            } else {
                texto += ', guichê ' + senha.numeroLocal;
            }
        }

        falar(texto, { lang: langCode, rate: 0.9 }, callback);
    }

    return {
        isSuportado: isSuportado,
        isFalando: function() { return isFalando; },
        falar: falar,
        falarSenha: falarSenha,
        cancelar: cancelar,
        numeroParaExtenso: numeroParaExtenso,
        processarCodigoSenha: processarCodigoSenha
    };
})();
