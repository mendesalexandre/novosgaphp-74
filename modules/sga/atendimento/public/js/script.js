/**
 * Novo SGA - Atendimento
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
var SGA = SGA || {};

SGA.Atendimento = {
    
    filaVazia: '',
    remover: '',
    marcarNaoCompareceu: '',
    marcarErroTriagem: '',
    nenhumServicoSelecionado: '',
    defaultTitle: '',
    timeoutId: 0,
    tiposAtendimento: {},
    permitirChamarDireta: false,
    exigirCodificacao: true,

    // Cronômetro — usa segundos decorridos para evitar problemas de timezone
    cronometro: {
        interval: null,
        segundosIniciais: 0,
        iniciadoEm: null,

        // segundosDecorridos: segundos já passados desde o inicio (calculados pelo servidor via campo 'espera' ou diff)
        iniciar: function(segundosDecorridos, tipo) {
            SGA.Atendimento.cronometro.parar();
            SGA.Atendimento.cronometro.segundosIniciais = segundosDecorridos || 0;
            SGA.Atendimento.cronometro.iniciadoEm = Date.now();

            var el = $('#cronometro');
            el.removeClass('cronometro-espera cronometro-atendimento cronometro-encerramento');
            if (tipo === 'espera') {
                el.addClass('cronometro-espera');
                $('#cronometro-label').text('Espera');
            } else if (tipo === 'atendimento') {
                el.addClass('cronometro-atendimento');
                $('#cronometro-label').text('Atendimento');
            } else if (tipo === 'encerramento') {
                el.addClass('cronometro-encerramento');
                $('#cronometro-label').text('Codificação');
            }

            $('#cronometro-container').show();
            SGA.Atendimento.cronometro.atualizar();
            SGA.Atendimento.cronometro.interval = setInterval(SGA.Atendimento.cronometro.atualizar, 1000);
        },

        atualizar: function() {
            if (SGA.Atendimento.cronometro.iniciadoEm === null) return;
            var decorrido = Math.floor((Date.now() - SGA.Atendimento.cronometro.iniciadoEm) / 1000);
            var segundos = SGA.Atendimento.cronometro.segundosIniciais + decorrido;
            var h = Math.floor(segundos / 3600);
            var m = Math.floor((segundos % 3600) / 60);
            var s = segundos % 60;
            $('#cronometro-tempo').text(('0' + h).slice(-2) + ':' + ('0' + m).slice(-2) + ':' + ('0' + s).slice(-2));
        },

        // converte string HH:MM:SS para segundos
        parseEspera: function(espera) {
            if (!espera) return 0;
            var partes = espera.split(':');
            return (parseInt(partes[0], 10) || 0) * 3600 +
                   (parseInt(partes[1], 10) || 0) * 60 +
                   (parseInt(partes[2], 10) || 0);
        },

        parar: function() {
            if (SGA.Atendimento.cronometro.interval) {
                clearInterval(SGA.Atendimento.cronometro.interval);
                SGA.Atendimento.cronometro.interval = null;
            }
            SGA.Atendimento.cronometro.iniciadoEm = null;
            $('#cronometro-container').hide();
        }
    },

    init: function(status, atendimento) {
        SGA.Atendimento.ajaxUpdate();
        SGA.Atendimento.updateControls(status, atendimento);
        $('#dialog-busca').on('show.bs.modal', function () {
            $('#numero_busca').val('');
            $('#result_table tbody').html('');
        });
        SGA.Atendimento.defaultTitle = document.title;
        if (!SGA.Notification.allowed()) {
            $('#notification').show();
        }
    },
    
    ajaxUpdate: function() {
        clearTimeout(SGA.Atendimento.timeoutId);
        if (!SGA.paused) {
            SGA.ajax({
                url: SGA.url('ajax_update'),
                success: function(response) {
                    response.data = response.data || {};
                    var atendimentos = response.data.atendimentos || [],
                            usuario = response.data.usuario || {};
                    var list = $("#fila ul");
                    // gerar hash simples para comparar se mudou
                    var novoHash = atendimentos.map(function(a) { return a.id + ':' + a.espera; }).join(',');
                    var hashAtual = list.data('hash') || '';

                    // habilitando botao chamar
                    if (atendimentos.length > 0) {
                        $('#chamar .chamar').prop('disabled', false);
                        // se a fila estava vazia e chegou um novo atendimento, entao toca o som
                        if (list.find('li.empty').length > 0) {
                            document.getElementById("alert").play();
                            SGA.Notification.show('Atendimento', 'Novo atendimento na fila');
                        }
                    }

                    // só reconstroi a fila se mudou (evita flash)
                    var mudou = (novoHash !== hashAtual);
                    if (mudou) {
                    list.data('hash', novoHash);
                    list.html('');
                    }
                    if (mudou && atendimentos.length > 0) {
                        document.body.focus();
                        for (var i = 0; i < atendimentos.length; i++) {
                            var atendimento = atendimentos[i];
                            var cssClass = atendimento.prioridade ? 'fila-card prioridade' : 'fila-card';
                            if (i == 0) cssClass += ' proximo';
                            var onclick = 'SGA.Atendimento.infoSenha(' + atendimento.id + ')';
                            var item = '<li class="' + cssClass + '">';
                            item += '<div class="fila-card-header" onclick="' + onclick + '">';
                            item += '<span class="fila-senha">' + atendimento.senha + '</span>';
                            if (atendimento.prioridade) {
                                item += '<span class="fila-badge-prio">Preferencial</span>';
                            }
                            item += '</div>';
                            item += '<div class="fila-card-body">';
                            item += '<div class="fila-servico">' + atendimento.servico + '</div>';
                            item += '<div class="fila-espera"><span class="glyphicon glyphicon-time"></span> ' + atendimento.espera + '</div>';
                            if (atendimento.cliente && atendimento.cliente.nome) {
                                item += '<div class="fila-cliente">' + atendimento.cliente.nome + '</div>';
                            }
                            item += '</div>';
                            if (SGA.Atendimento.permitirChamarDireta) {
                                item += '<div class="fila-card-footer"><button class="btn btn-xs btn-primary btn-chamar-direto" onclick="SGA.Atendimento.chamarEspecifico(' + atendimento.id + ', this)" title="Chamar esta senha: ' + atendimento.senha + '"><span class="glyphicon glyphicon-bullhorn"></span> Chamar</button></div>';
                            }
                            item += '</li>';
                            list.append(item);
                        }
                        document.title = "(" + atendimentos.length + ") " + SGA.Atendimento.defaultTitle;
                    } else if (mudou) {
                        $('#chamar .chamar').prop('disabled', true);
                        list.append('<li class="empty">' + SGA.Atendimento.filaVazia + '</li>');
                        document.title = SGA.Atendimento.defaultTitle;
                    }
                    if (usuario.numeroLocal) {
                        $('span.config-numero-local').text(usuario.numeroLocal);
                        $('.config-numero-local:input').val(usuario.numeroLocal);
                    }
                    if (usuario.tipoAtendimento) {
                        $('span.config-tipo-atendimento')
                                .removeClass('tipo-1 tipo-2 tipo-3')
                                .addClass('tipo-' + usuario.tipoAtendimento)
                                .text(SGA.Atendimento.tiposAtendimento[usuario.tipoAtendimento]);
                        $('.config-tipo-atendimento:input').val(usuario.tipoAtendimento);
                        ;
                    }
                },
                complete: function() {
                    SGA.Atendimento.timeoutId = setTimeout(SGA.Atendimento.ajaxUpdate, SGA.updateInterval);
                }
            });
        } else {
            SGA.Atendimento.timeoutId = setTimeout(SGA.Atendimento.ajaxUpdate, SGA.updateInterval);
        }
    },
    
    updateControls: function(status, atendimento) {
        $('#controls .control').hide();
        switch (status) {
            case 1: // nenhum atendimento, chamar
                $('#chamar').show();
                $('#redirecionar_servico').val(0);
                $('#encerrar-redirecionar').prop('checked', false);
                SGA.Atendimento.cronometro.parar();
                break;
            case 2: // senha chamada
                if (atendimento) {
                    var info = $('.senha .info');
                    info.removeClass('prioridade');
                    if (atendimento.prioridade) {
                        info.addClass('prioridade');
                    }
                    info.find('.numero .atend-value').text(atendimento.senha);
                    info.find('.nome-prioridade .atend-value').text(atendimento.nomePrioridade);
                    info.find('.servico .atend-value').text(atendimento.servico);
                    info.find('.nome .atend-value').text(atendimento.cliente.nome || '-');
                    SGA.Atendimento.cronometro.iniciar(SGA.Atendimento.cronometro.parseEspera(atendimento.espera), 'espera');
                }
                $('#iniciar').show();
                break;
            case 3: // atendimento iniciado
                $('#encerrar').show();
                var btnEncerrar = $('#encerrar .encerrar');
                btnEncerrar.prop('disabled', true);
                // habilita o botao encerrar depois de X segundos (issue #249)
                setTimeout(function() {
                    btnEncerrar.prop('disabled', false);
                }, 3000);
                if (atendimento && atendimento.tempoAtendimento !== undefined) {
                    SGA.Atendimento.cronometro.iniciar(atendimento.tempoAtendimento, 'atendimento');
                } else {
                    SGA.Atendimento.cronometro.iniciar(0, 'atendimento');
                }
                break;
            case 4: // atendimento encerrado (faltando codificar)
                $("#codificar").show();
                $("#macro-servicos li").show();
                $("#servicos-realizados").html('');
                if (atendimento && atendimento.tempoAtendimento !== undefined) {
                    SGA.Atendimento.cronometro.iniciar(atendimento.tempoAtendimento, 'encerramento');
                }
                break;
        }
    },
    
    control: function(prop) {
        prop = prop || {};
        $(prop.button).prop('disabled', true);
        SGA.ajax({
            url: SGA.url(prop.action),
            type: 'post',
            data: prop.data || {},
            success: function(response) {
                if (prop.success) {
                    prop.success(response);
                }
            },
            complete: function() {
                var delay = prop.enableDelay || 0;
                window.setTimeout(function() {
                    $(prop.button).prop('disabled', false);
                }, delay);
            }
        });
    },
    
    chamar: function(btn) {
        SGA.Atendimento.control({
            button: btn,
            enableDelay: 5000,
            action: 'chamar', 
            success: function(response) {
                // remove o proximo da lista se for o mesmo do atendimento
                var proximo = $("#fila ul li:first");
                if (response.data.senha == proximo.text()) {
                    proximo.remove();
                    if ($("#fila ul li").length == 0) {
                        // fila vazia
                        $("#fila ul").append('<li class="empty">' + SGA.Atendimento.filaVazia + '</li>')
                    } else {
                        // novo proximo
                        $("#fila ul li:first a").addClass('proximo'); 
                    }
                }
                SGA.Atendimento.updateControls(2, response.data);
            }
        });
    },
    
    chamar_novamente: function(btn) {
        SGA.Atendimento.control({
            button: btn,
            enableDelay: 5000,
            action: 'chamar'
        });
    },

    salvarNomeCliente: function() {
        SGA.ajax({
            url: SGA.url('salvar_nome_cliente'),
            type: 'post',
            data: {
                nome_cliente: $('#nome-cliente').val()
            }
        });
    },

    chamarEspecifico: function(id, btn) {
        SGA.Atendimento.control({
            button: btn,
            enableDelay: 5000,
            action: 'chamar_especifico/' + id,
            success: function(response) {
                SGA.Atendimento.updateControls(2, response.data);
            }
        });
    },
    
    iniciar: function(btn) {
        SGA.Atendimento.control({
            button: btn,
            action: 'iniciar', 
            success: function(response) {
                SGA.Atendimento.updateControls(3, response.data)
            }
        });
    },
    
    nao_compareceu: function(btn) {
        if (window.confirm(SGA.Atendimento.marcarNaoCompareceu)) {
            SGA.Atendimento.control({
                button: btn,
                action: 'nao_compareceu', 
                success: function(response) {
                    SGA.Atendimento.updateControls(1, response.data)
                }
            });
        }
    },
    
    encerrar: function(btn) {
        SGA.Atendimento.control({
            button: btn,
            action: 'encerrar',
            success: function(response) {
                if (SGA.Atendimento.exigirCodificacao) {
                    SGA.Atendimento.updateControls(4, response.data);
                } else {
                    // sem codificação: backend já finalizou, volta para chamar
                    SGA.Atendimento.updateControls(1);
                }
            }
        });
    },
    
    encerrar_voltar: function() {
        $("#encerrar").show();
        $("#codificar").hide();
    },
    
    codificar: function(btn, isRedirect) {
        var servicos = [];
        $('#servicos-realizados input.servicos').each(function(i, e) {
            servicos.push($(e).val());
        });
        if (servicos.length == 0) {
            alert(SGA.Atendimento.nenhumServicoSelecionado);
            return;
        }
        var data = {
            servicos: servicos.join(',')
        };
        // se foi submetido via modal de redirecionamento
        if (isRedirect) {
            var servico = $('#redirecionar_servico').val();
            if (isNaN(servico) || servico <= 0) {
                alert(SGA.Atendimento.nenhumServicoSelecionado);
                return;
            }
            data.redirecionar = true;
            data.novoServico = servico;
            // definindo o botao da dialog para ser desabilitado
            btn = $('#dialog-redirecionar').parent().find(':button');
        } else {
            // verifica se checkbox redirecionar esta marcado, para abrir a modal
            var redirecionar = $('#encerrar-redirecionar').is(':checked');
            if (redirecionar) {
                var modal = SGA.dialogs.modal('#dialog-redirecionar');
                modal.find('button').hide();
                modal.find('.btn-codificar').show();
                return;
            }
        }
        SGA.Atendimento.control({
            button: btn,
            action: 'codificar', 
            data: data,
            success: function() {
                SGA.Atendimento.updateControls(1);
                if (isRedirect) {
                    $('#dialog-redirecionar').modal('hide');
                }
            }
        });
    },
    
    infoSenha: function(id) {
        SGA.ajax({
            url: SGA.url('info_senha'),
            data: {
                id: id
            },
            success: function(response) {
                if (response.success) {
                    var a = response.data;
                    var dialog = $('#dialog-senha');
                    dialog.find('.numero').text(a.senha);
                    dialog.find('.nome-prioridade').text(a.nomePrioridade);
                    dialog.find('.servico').text(a.servico);
                    dialog.find('.chegada').text(SGA.formatDate(a.chegada));
                    dialog.find('.espera').text(a.espera);
                    SGA.dialogs.modal(dialog);
                }
            }
        });
    },
    
    erro_triagem: function() {
        var modal = SGA.dialogs.modal('#dialog-redirecionar');
        modal.find('button').hide();
        modal.find('.btn-redirecionar').show();
    },
    
    redirecionar: function(btn) {
        var servico = $('#redirecionar_servico').val();
        if (servico > 0 && window.confirm(SGA.Atendimento.marcarErroTriagem)) {
            SGA.Atendimento.control({
                button: btn,
                action: 'redirecionar', 
                data: {servico: servico},
                success: function() {
                    SGA.Atendimento.updateControls(1);
                    $('#dialog-redirecionar').modal('hide');
                }
            });
        }
    },
    
    addServico: function(item) {
        item = $(item);
        $("#servicos-realizados").append('<li><a href="javascript:void(0)" onclick="SGA.Atendimento.delServico(this)" title="' + SGA.Atendimento.remover + '"><input type="hidden" class="servicos" value="' + item.data('id') + '" />' + item.text() + '</a></li>');
        $(item).parent().hide();
    },
    
    delServico: function(item) {
        item = $(item); 
        $('#servico-' + item.find('input').val()).show();
        item.parent().remove();
    },

    consultar: function() {
        SGA.ajax({
            url: SGA.url('consulta_senha'),
            data: {
                numero: $('#numero_busca').val()
            },
            success: function(response) {
                var result = $('#result_table tbody');
                result.html('');
                if (response.data.total > 0) {
                    for (var i = 0; i < response.data.total; i++) {
                        var atendimento = response.data.atendimentos[i];
                        var tr = '<tr>';
                        tr += '<td>' + atendimento.senha + '</td>';
                        tr += '<td>' + atendimento.servico + '</td>';
                        tr += '<td>' + SGA.formatDate(atendimento.chegada) + '</td>';
                        tr += '<td>' + SGA.formatTime(atendimento.inicio) + '</td>';
                        tr += '<td>' + SGA.formatTime(atendimento.fim) + '</td>';
                        tr += '<td>' + (atendimento.triagem ? atendimento.triagem : '-') + '</td>';
                        tr += '<td>' + (atendimento.usuario ? atendimento.usuario : '-') + '</td>';
                        tr += '<td>' + atendimento.nomeStatus + '</td>';
                        tr += '</tr>';
                        result.append(tr);
                    }
                }
            }
        });
    }
    
};