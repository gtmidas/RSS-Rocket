<?php
// Agendar a importação
// Esta função configura o agendamento da importação automática baseada na frequência escolhida no admin.
// Ela verifica se o evento já está agendado e o cria se necessário, usando os intervalos definidos.
function rss_rocket_schedule_import() {
    if (!wp_next_scheduled('rss_rocket_import_hook')) {
        $frequency = get_option('rss_frequency', 'daily');
        $schedule = [
            '6hours' => '6hours',
            '12hours' => '12hours',
            'daily' => 'daily',
            '2days' => 'twicedaily', // Usamos 'twicedaily' como base para 2 dias (aproximação)
            'weekly' => 'weekly'
        ];
        wp_schedule_event(time(), $schedule[$frequency], 'rss_rocket_import_hook');
    }
}
add_action('wp', 'rss_rocket_schedule_import');

// Função de importação agendada
// Esta função é executada nos intervalos agendados para importar posts automaticamente.
// Processa cada feed e importa um post, similar à importação manual, mas sem interação direta.
function rss_rocket_do_import() {
    $feeds = get_option('rss_feeds', array_fill(0, 5, array('url' => '', 'category' => 0)));
    $imported = false;
    foreach ($feeds as $feed) {
        if (!empty($feed['url']) && filter_var($feed['url'], FILTER_VALIDATE_URL)) {
            $rss = fetch_feed($feed['url']);
            if (!is_wp_error($rss)) {
                $max_items = $rss->get_item_quantity(1); // Pega apenas 1 item (o mais recente) por feed
                $rss_items = $rss->get_items(0, $max_items);
                foreach ($rss_items as $item) {
                    $title = sanitize_text_field($item->get_title());
                    $existing_post = get_page_by_title($title, OBJECT, 'post');
                    if ($existing_post) continue;
                    $content = $item->get_content();
                    if (empty($content)) {
                        $content = $item->get_description();
                        if (empty($content)) $content = 'Conteúdo completo não disponível. <a href="' . esc_url($item->get_link()) . '" target="_blank">Veja o artigo original</a>.';
                    }
                    $content = wp_kses_post($content); // Sanitiza HTML do conteúdo
                    $link = esc_url($item->get_link());
                    $post_data = array(
                        'post_title' => $title,
                        'post_content' => $content . '<p><em>Nota: Este artigo foi importado de <a href="' . $link . '" target="_blank">este site</a>.</em></p>',
                        'post_status' => 'publish',
                        'post_author' => get_current_user_id(),
                        'post_category' => array(absint($feed['category'])),
                    );
                    $post_id = wp_insert_post($post_data);
                    if ($post_id) $imported = true;
                }
            }
        }
    }
    if ($imported) {
        // Opcional: Adicionar log ou notificação (pode ser implementado depois)
    }
}
add_action('rss_rocket_import_hook', 'rss_rocket_do_import');

// Remover agendamento ao desativar o plugin
// Esta função limpa o agendamento quando o plugin é desativado, evitando execuções indesejadas.
function rss_rocket_deactivation() {
    wp_clear_scheduled_hook('rss_rocket_import_hook');
}
register_deactivation_hook(dirname(__FILE__) . '/rss-rocket-functions.php', 'rss_rocket_deactivation');