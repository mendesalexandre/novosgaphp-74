/** 
 * Vetor Panel
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
(function() {
    "use strict";
    
    window.VetorPanel = function(config) {
        
        var timestamp = 0,
            newConfig = null, 
            timeoutId = 0, 
            attached = false, 
            feed = null,
            slider = $("#media");

        slider.index = 0;
        slider.widgets = [];
    
        // mostra o widget a partir do index
        slider.show = function(index) {
            if (slider.widgets && slider.widgets[index]) {
                slider.find('.widget').fadeOut(500);
                slider.widgets[index].elem.fadeIn(500);
                slider.index = index;
                checkWidget(slider.index);
            }
        };
        // passa para o proximo widget
        slider.nextSlide = function() {
            // se tiver novos widgets carregados, substitui
            if (newConfig !== null) {
                createContents(newConfig);
                newConfig = null;
            } else {
                if (slider.widgets.length > 1) {
                    // proximo widget da lista
                    if (slider.index < slider.widgets.length - 1) {
                        slider.index++;
                    } else {
                        slider.index = 0;
                    }
                    slider.show(slider.index);
                } else {
                    checkWidget(0);
                }
            }
        };
        slider.currentWidget = function() {
            return slider.widgets[slider.index];
        };

        var loadConfig = function(callback) {
            var url = PainelWeb.Storage.get('url');
            if (!url || url === '') {
                // fallback: usar a URL base do painel-web
                url = window.location.protocol + '//' + window.location.host;
            }
            if (url && url !== '') {
                $.ajax({
                    url: url + '/api/extra/vetor.panel',
                    cache: false,
                    success: function(data) {
                        if (data) {
                            if (timestamp !== data.timestamp) {
                                callback(data);
                            }
                            timestamp = data.timestamp;
                        }
                    },
                    error: function() {
                        $('#media').html('<span class="label label-danger">Módulo Vetor Panel não encontrado</span>');
                    }
                });
            }
        };

        var createContents = function(config) {
            config = config || {};
            /*
             * widgets
             */
            if (config.widgets) {
                slider.html('');
                slider.widgets = [];
                for (var i = 0, j = 0; i < config.widgets.length; i++) {
                    var widget = config.widgets[i];
                    if (widget.active) {
                        widget.index = j++;
                        widget.elem = createContent(widget).hide();
                        slider.append(widget.elem);
                        widget.elem.css('line-height', widget.elem.parent().height() + 'px');
                        slider.widgets.push(widget);
                    }
                }
                if (slider.widgets.length) {
                    slider.show(0);
                }
            }

            /*
             * news
             */
            if (config.news) {
                if (!feed) {
                    feed = new Feed(config.news.sources, config.news.interval);
                } else {
                    feed.update(config.news.sources, config.news.interval);
                }
            }
        };

        var createContent = function(widget) {
            var elem = $('<div class="widget widget-' + widget.type +'" style="background-color:' + widget.background + '"></div>');
            switch (widget.type) {
                case 'html':
                    elem.append(widget.content.replace(/<script.*?>.*?<\/script>/im, ''))
                        .addClass('loaded');
                    break;
                case 'image':
                    elem.append(
                        $("<img/>")
                            .prop('alt', widget.title)
                            .css({ "width": slider.width() })
                            .on('load', function() {
                                elem.addClass('loaded');
                                if (widget.index === slider.currentWidget().index) {
                                    checkWidget(widget.index);
                                }
                            })
                            .prop('src', widget.content)
                    );
                    break;
                case 'video':
                case 'audio':
                    elem.append(
                        $('<' + widget.type + '></' + widget.type + '>')
                            .prop('autoplay', true)
                            .prop('muted', true)
                            .on('ended error', function() { slider.nextSlide(); })
                            .on('loadeddata canplay', function() {
                                elem.addClass('loaded');
                                if (widget.index === slider.currentWidget().index) {
                                    checkWidget(widget.index);
                                }
                            })
                            .css({ width: slider.width() })
                            .append('<source src="' + widget.content + '?_=' + ((new Date()).getTime()) + '" type="' + mimetype(widget.content) + '">')
                    );
                    break;
                case 'youtube':
                    elem.append('<div id="yt-player-' + widget.index + '"></div>');
                    elem.addClass('loaded');
                    break;
                case 'iptv':
                    elem.append(
                        $('<video></video>')
                            .on('error', function() { slider.nextSlide(); })
                            .on('loadeddata canplay', function() {
                                elem.addClass('loaded');
                                if (widget.index === slider.currentWidget().index) {
                                    checkWidget(widget.index);
                                }
                            })
                            .css({ width: slider.width() })
                            .prop('src', widget.content)
                    );
                    elem.addClass('loaded');
                    break;
                case 'comunicado':
                    var fontSize = widget.fontSize || 4;
                    var textColor = widget.textColor || '#FFFFFF';
                    var html = '<div class="comunicado-content" style="' +
                        'color:' + textColor + ';font-size:' + fontSize + 'vw;' +
                        'padding:40px;text-align:center;display:flex;align-items:center;justify-content:center;height:100%">';
                    if (widget.title) {
                        html += '<div><h2 style="color:' + textColor + ';margin-bottom:30px;font-size:' + (fontSize * 1.2) + 'vw">' + widget.title + '</h2>';
                        html += '<p>' + (widget.content || '').replace(/\n/g, '<br>') + '</p></div>';
                    } else {
                        html += '<p>' + (widget.content || '').replace(/\n/g, '<br>') + '</p>';
                    }
                    html += '</div>';
                    elem.append(html).addClass('loaded');
                    break;
                case 'clima':
                    var climaHtml = '<div class="clima-content" style="display:flex;align-items:center;justify-content:center;height:100%;color:#fff;font-size:3vw">';
                    climaHtml += '<div id="clima-widget-' + widget.index + '" style="text-align:center">';
                    climaHtml += '<div class="clima-loading">Carregando clima...</div>';
                    climaHtml += '</div></div>';
                    elem.append(climaHtml).addClass('loaded');
                    // carregar dados do clima quando exibido
                    widget.climaLoaded = false;
                    break;
                case 'noticias':
                    var newsHtml = '<div class="noticias-content" style="padding:30px;color:#fff;height:100%;overflow:hidden">';
                    newsHtml += '<h3 style="color:#aaa;text-align:center;margin-bottom:20px">' + (widget.title || 'Notícias') + '</h3>';
                    newsHtml += '<div id="noticias-widget-' + widget.index + '" style="font-size:2vw"></div>';
                    newsHtml += '</div>';
                    elem.append(newsHtml).addClass('loaded');
                    widget.newsLoaded = false;
                    break;
                default:
                    elem.append('<div style="color:#fff;text-align:center;padding:40px">Widget desconhecido: ' + widget.type + '</div>')
                        .addClass('loaded');
            }
            return elem;
        };

        var checkWidget = function(index) {
            var widget = slider.widgets[index];
            if (widget) {
                clearTimeout(timeoutId);
                switch (widget.type) {
                    case 'html':
                    case 'image':
                    case 'comunicado':
                        timeoutId = setTimeout(function() {
                            slider.nextSlide();
                        }, widget.duration);
                        break;
                    case 'video':
                    case 'audio':
                        if (widget.elem.hasClass('loaded')) {
                            var elem = widget.elem.find(widget.type);
                            elem.trigger('play');
                        } else {
                            timeoutId = setTimeout(function() {
                                slider.nextSlide();
                            }, 5 * 1000);
                        }
                        break;
                    case 'iptv':
                        // IPTV: toca o stream e avança após a duração (ou fica indefinido se duration=0)
                        var iptvVideo = widget.elem.find('video');
                        iptvVideo.trigger('play');
                        if (widget.duration > 0) {
                            timeoutId = setTimeout(function() {
                                iptvVideo.trigger('pause');
                                slider.nextSlide();
                            }, widget.duration);
                        }
                        break;
                    case 'youtube':
                        Youtube.play(widget);
                        break;
                    case 'clima':
                        if (!widget.climaLoaded && widget.apiKey && widget.cidade) {
                            loadClima(widget);
                        }
                        timeoutId = setTimeout(function() {
                            slider.nextSlide();
                        }, widget.duration);
                        break;
                    case 'noticias':
                        if (!widget.newsLoaded && widget.content) {
                            loadNoticias(widget);
                        }
                        timeoutId = setTimeout(function() {
                            slider.nextSlide();
                        }, widget.duration);
                        break;
                }
                changeVolume(widget.volume);
            }
        };

        // Carregar dados do clima via OpenWeatherMap
        var loadClima = function(widget) {
            widget.climaLoaded = true;
            var container = $('#clima-widget-' + widget.index);
            $.ajax({
                url: 'https://api.openweathermap.org/data/2.5/weather?q=' + encodeURIComponent(widget.cidade) + '&appid=' + widget.apiKey + '&units=metric&lang=pt_br',
                dataType: 'json',
                success: function(data) {
                    var html = '<div style="font-size:2vw;color:#aaa;margin-bottom:10px">' + data.name + '</div>';
                    html += '<img src="https://openweathermap.org/img/wn/' + data.weather[0].icon + '@4x.png" style="width:120px">';
                    html += '<div style="font-size:6vw;font-weight:bold">' + Math.round(data.main.temp) + '°C</div>';
                    html += '<div style="font-size:2vw;text-transform:capitalize">' + data.weather[0].description + '</div>';
                    html += '<div style="font-size:1.2vw;color:#aaa;margin-top:10px">Umidade: ' + data.main.humidity + '% | Vento: ' + Math.round(data.wind.speed * 3.6) + ' km/h</div>';
                    container.html(html);
                },
                error: function() {
                    container.html('<div style="color:#f66">Erro ao carregar clima</div>');
                }
            });
        };

        // Carregar notícias RSS via proxy
        var loadNoticias = function(widget) {
            widget.newsLoaded = true;
            var container = $('#noticias-widget-' + widget.index);
            var url = PainelWeb.Storage.get('url');
            var feedUrl = url + '/api/extra/vetor.panel/feed?url=' + encodeURIComponent(widget.content);
            $.ajax({
                url: feedUrl,
                dataType: 'xml',
                success: function(data) {
                    var items = data.getElementsByTagName('item');
                    var maxItems = widget.maxItems || 10;
                    var html = '';
                    for (var i = 0; i < Math.min(items.length, maxItems); i++) {
                        var titulo = items[i].getElementsByTagName('title')[0];
                        if (titulo) {
                            html += '<div style="padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.1)">' + titulo.textContent + '</div>';
                        }
                    }
                    container.html(html || '<div style="color:#aaa">Nenhuma notícia encontrada</div>');
                },
                error: function() {
                    container.html('<div style="color:#f66">Erro ao carregar notícias</div>');
                }
            });
        };

        var mimetype = function(url) {
            var ext = url.split(".").reverse()[0];
            switch (ext) {
                case 'mp3':
                    return 'audio/mp3';
                case 'mp4':
                    return 'video/mp4';
                case 'ogg':
                case 'ogv':
                    return 'video/ogg';
            }
            return "application/" + ext;
        };

        var changeVolume = function(volume) {
            var widget = slider.currentWidget();
            if (widget) {
                switch (widget.type) {
                    case 'audio':
                    case 'video':
                        widget.elem.find(widget.type).prop('volume', volume / 100);
                        break;
                    case 'youtube':
                        var player = Youtube.players[widget.index];
                        if (player && player.loaded) {
                            player.setVolume(volume);
                        }
                        break;
                }
            }
        };

        /**
         * @constructor
         */
        var Feed = function(sources, interval) {
            var self = this;
            var timeoutId = 0;

            this.index = -1;
            this.newConfig = null;

            this.currentSource = function() {
                return self.sources[self.index];
            };

            this.nextSource = function() {
                if (self.newConfig) {
                    self.index = -1;
                    var config = self.newConfig;
                    self.newConfig = null;
                    self.update(config.sources, config.interval);
                } else {
                    self.index++;
                    if (self.index >= self.sources.length) {
                        self.index = 0;
                    }
                }
            };

            this.show = function(item, source) {
                $('#news .panel-heading span').text((source.index + 1) + '/' + source.items.length);
                $('#news .panel-body').text(item.title);
            };
            
            this.listen = function() {
                clearTimeout(self.timeoutId);
                self.timeoutId = setTimeout(function() {
                    var source = self.currentSource();
                    if (source && source.items) {
                        if (source.index < source.items.length - 1) {
                            source.index++;
                            self.show(source.items[source.index], source);
                            self.listen();
                        } else {
                            // acabou o feed atual, indo pro proximo
                            self.nextSource();
                            self.load();
                        }
                    } else {
                        // nenhum item no feed, tenta o proximo
                        self.nextSource();
                    }
                }, self.interval);
            };

            this.update = function(sources, interval) {
                if (self.index === -1) {
                    // creating
                    self.sources = sources || [];
                    self.interval = interval;
                    if (self.sources.length === 0) return;
                    self.index = 0;
                    self.sources[self.index].items = [];
                    self.sources[self.index].index = 0;
                    self.load();
                } else {
                    // updating (wait transition)
                    self.newConfig = {
                        sources: sources, 
                        interval: interval
                    };
                }
            };

            this.load = function() {
                var source = self.currentSource();
                if (source) {
                    // Usar proxy para evitar CORS
                    var baseUrl = PainelWeb.Storage.get('url') || '';
                    var url = baseUrl + '/api/extra/vetor.panel/feed?url=' + encodeURIComponent(source.url);
                    var dataType = 'xml';
                    var parser;

                    var tagName = (source.type === 'atom') ? 'entry' : 'item';
                    var titleTag = 'title';
                    var descTag = (source.type === 'atom') ? 'content' : 'description';

                    parser = function(data) {
                        var items = [];
                        var nodes = data.getElementsByTagName(tagName);
                        for (var i = 0; i < nodes.length; i++) {
                            var node = nodes[i];
                            var titleEl = node.getElementsByTagName(titleTag)[0];
                            var descEl = node.getElementsByTagName(descTag)[0];
                            items.push({
                                title: titleEl ? (titleEl.textContent || titleEl.innerHTML) : '',
                                content: descEl ? (descEl.textContent || descEl.innerHTML) : ''
                            });
                        }
                        return items;
                    };

                    $.ajax({
                        url: url,
                        dataType: dataType,
                        success: function(data) {
                            if (data) {
                                source.index = 0;
                                source.items = parser(data);
                                if (source.items.length > 0) {
                                    self.show(source.items[0], source);
                                } else {
                                    // se nao tiver noticias no feed atual, joga pro proximo
                                    self.nextSource();
                                }
                            };
                        },
                        complete: function() {
                            self.listen();
                        },
                        error: function() {
                            $('#news .panel-body').html('<span class="text-danger">Não foi possível carregar as notícias, verifique a conectividade com a Internet.</span>');
                        }
                    });
                }
            };
            
            this.update(sources, interval);

        };

        var Youtube = { 
            loaded: false, 
            interval: 0,
            referenced: false,
            players: [],
            loadapi: function() {
                var tag = document.createElement('script');
                tag.src = "//www.youtube.com/iframe_api";
                window.onYouTubeIframeAPIReady = function() {
                    Youtube.loaded = true;
                };
                var firstScriptTag = document.getElementsByTagName('script')[0];
                firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
            },
            play: function(widget) {
                if (this.loaded) {
                    var player = this.players[widget.index];
                    if (!player) {
                        player = new YT.Player('yt-player-' + widget.index, {
                            height: slider.height(),
                            width: slider.width(),
                            videoId: widget.content,
                            playerVars: {
                                disablekb: 1,
                                controls: 0,
                                showinfo: 0,
                                rel: 0,
                                iv_load_policy: 3
                            },
                            events: {
                                onReady: function() {
                                    if (widget.index === slider.currentWidget().index) {
                                        player.loaded = true;
                                        checkWidget(widget.index);
                                        player.playVideo();
                                    }
                                },
                                onStateChange: function(event) {
                                    if (event.data === YT.PlayerState.ENDED) {
                                        Youtube.players[widget.index].destroy();
                                        Youtube.players[widget.index] = null;
                                        slider.nextSlide();
                                    }
                                }
                            }
                        });
                    }
                    this.players[widget.index] = player;
                    if (player.loaded) {
                        player.playVideo();
                    }
                } else {
                    if (!this.referenced) {
                        this.loadapi();
                        this.referenced = true;
                    }
                    clearTimeout(Youtube.interval);
                    Youtube.interval = setTimeout(function() {
                        Youtube.play(widget);
                    }, 100);
                }
            }
        }

        var init = function() {
            // Recalcular o slider caso o #media tenha sido re-renderizado
            var newSlider = $("#media");
            if (newSlider.length) {
                slider = newSlider;
                slider.index = 0;
                slider.widgets = [];
                slider.show = function(index) {
                    if (slider.widgets && slider.widgets[index]) {
                        slider.find('.widget').fadeOut(500);
                        slider.widgets[index].elem.fadeIn(500);
                        slider.index = index;
                        checkWidget(slider.index);
                    }
                };
                slider.nextSlide = function() {
                    if (newConfig !== null) {
                        createContents(newConfig);
                        newConfig = null;
                    } else if (slider.widgets.length > 1) {
                        slider.index = (slider.index < slider.widgets.length - 1) ? slider.index + 1 : 0;
                        slider.show(slider.index);
                    } else {
                        checkWidget(0);
                    }
                };
                slider.currentWidget = function() {
                    return slider.widgets[slider.index];
                };
            }

            // callbacks
            if (!attached) {
                PainelWeb.on('callstart', function() {
                    changeVolume(0);
                });

                PainelWeb.on('callend', function() {
                    var widget = slider.currentWidget();
                    changeVolume(widget && widget.volume ? widget.volume : 1);
                });
                attached = true;
            }

            // iniciando slider
            loadConfig(function(config) {
                createContents(config);
            });
            // verificacao de alteracao
            setInterval(function() {
                loadConfig(function(config) {
                    newConfig = config;
                });
            }, 30 * 1000);
        };

        // update media size
        $(window).resize(function() {
            slider.find('img, iframe').css({
                "width": slider.width(),
                "height": slider.height()
            });
            slider.find('video').css({
                "width": slider.width()
            });
            slider.find('.widget').each(function(i, e) {
                var elem = $(e);
                elem.css('line-height', elem.parent().height() + 'px');
            });
        });

        PainelWeb.on('save', function() {
            init();
        });

        // Relógio do cartório
        var diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
        var meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

        setInterval(function() {
            var now = new Date();
            var h = ('0' + now.getHours()).slice(-2);
            var m = ('0' + now.getMinutes()).slice(-2);
            var s = ('0' + now.getSeconds()).slice(-2);
            $('#cart-h').text(h);
            $('#cart-m').text(m);
            $('#cart-s').text(s);
            $('#cart-data-ext').text(diasSemana[now.getDay()] + ', ' + now.getDate() + ' de ' + meses[now.getMonth()] + ' de ' + now.getFullYear());
        }, 1000);

        // Mostrar cliente ou histórico conforme a senha atual
        PainelWeb.on('callend', function() {
            var scope = angular.element(document.getElementById('layout')).scope();
            if (scope && scope.ultima && scope.ultima.nomeCliente && scope.ultima.nomeCliente.trim() !== '') {
                $('#nome-cliente-valor').text(scope.ultima.nomeCliente);
                $('#area-cliente').show();
                $('#area-historico').hide();
            } else {
                $('#area-cliente').hide();
                $('#area-historico').show();
            }
        });

        PainelWeb.on('callstart', function() {
            var scope = angular.element(document.getElementById('layout')).scope();
            if (scope && scope.ultima && scope.ultima.nomeCliente && scope.ultima.nomeCliente.trim() !== '') {
                $('#nome-cliente-valor').text(scope.ultima.nomeCliente);
                $('#area-cliente').show();
                $('#area-historico').hide();
            } else {
                $('#area-cliente').hide();
                $('#area-historico').show();
            }
        });

        // Estado inicial: mostra histórico
        $('#area-historico').show();
        $('#area-cliente').hide();

        // Nome da empresa via config
        var url = PainelWeb.Storage.get('url');
        if (url && url !== '') {
            $.ajax({
                url: url + '/api/extra/vetor.panel',
                cache: false,
                success: function(data) {
                    if (data && data.nomeEmpresa) {
                        $('#nome-empresa-painel').text(data.nomeEmpresa);
                    }
                }
            });
        }

        init();
    };

})();
