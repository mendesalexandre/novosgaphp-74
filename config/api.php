<?php
return array(
    'extra' => array(
        'routes' => array(
            'vetor.panel' => function() {
                $api = new modules\vetor\panel\PanelApi();
                $api->api();
            },
            'vetor.panel/feed' => function() {
                $url = isset($_GET['url']) ? $_GET['url'] : '';
                $api = new modules\vetor\panel\PanelApi();
                $api->feed($url);
            },
        ),
    ),
);
