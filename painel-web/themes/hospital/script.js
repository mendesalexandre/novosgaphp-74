/**
 * Hospital Panel
 * Baseado no layout Gov-CE do Hospital Albert Sabin
 */
var HospitalPanel = function(config) {
    // Relógio
    var monthNames = [
        "<span>de</span> Janeiro", "<span>de</span> Fevereiro", "<span>de</span> Março",
        "<span>de</span> Abril", "<span>de</span> Maio", "<span>de</span> Junho",
        "<span>de</span> Julho", "<span>de</span> Agosto", "<span>de</span> Setembro",
        "<span>de</span> Outubro", "<span>de</span> Novembro", "<span>de</span> Dezembro"
    ];
    var dayNames = ["Domingo", "Segunda-Feira", "Terça-Feira", "Quarta-Feira", "Quinta-Feira", "Sexta-Feira", "Sábado"];

    var newDate = new Date();
    $('#date').html(newDate.getDate() + ' ' + monthNames[newDate.getMonth()]);
    $('#day').html(dayNames[newDate.getDay()]);

    setInterval(function() {
        var now = new Date();
        var hours = now.getHours();
        var minutes = now.getMinutes();
        $("#hours").html((hours < 10 ? "0" : "") + hours);
        $("#min").html((minutes < 10 ? "0" : "") + minutes);
    }, 1000);

    // Configuração do nome do hospital via Vetor Panel
    var url = PainelWeb.Storage.get('url');
    if (url && url !== '') {
        $.ajax({
            url: url + '/api/extra/vetor.panel',
            cache: false,
            success: function(data) {
                if (data && data.hospitalNome) {
                    $('#nome-empresa-painel').text(data.hospitalNome);
                }
            }
        });
    }
};
