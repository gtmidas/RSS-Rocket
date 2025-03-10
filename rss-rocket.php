<?php
/*
 Plugin Name: RSS Rocket
 Description: FREE AGREGGATOR - Imports posts from RSS feeds with security and efficiency to boost your traffic now!
 Version: 1.0
 Author: Otavio (The Golden Game)
 Author URI: https://thegoldengame.com
 License: GPL-2.0+
 License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Registrar opções no banco de dados
// Esta função registra as opções 'rss_feeds' e 'rss_frequency' no banco de dados do WordPress,
// permitindo que os valores sejam salvos e recuperados pelo plugin. Ela é chamada durante a inicialização do admin.
function rss_rocket_register_settings() {
    register_setting('rss_rocket_options_group', 'rss_feeds', array(
        'sanitize_callback' => 'rss_rocket_sanitize_feeds'
    ));
    register_setting('rss_rocket_options_group', 'rss_frequency', array(
        'sanitize_callback' => 'sanitize_text_field'
    ));
}
add_action('admin_init', 'rss_rocket_register_settings');

// Função de sanitização para os feeds
// Esta função valida e limpa os URLs dos feeds para evitar injeções maliciosas.
function rss_rocket_sanitize_feeds($input) {
    $sanitized = array();
    foreach ((array)$input as $index => $feed) {
        $sanitized[$index] = array(
            'url' => esc_url_raw($feed['url']),
            'category' => absint($feed['category'])
        );
    }
    return $sanitized;
}

// Adicionar menu no admin
// Esta função adiciona uma nova página de menu chamada 'RSS Rocket' no painel de administração,
// usando o ícone RSS e restrita a usuários com permissão 'manage_options' (administradores).
function rss_rocket_add_menu() {
    add_menu_page('RSS Rocket', 'RSS Rocket', 'manage_options', 'rss-rocket', 'rss_rocket_options_page', 'dashicons-rss');
}
add_action('admin_menu', 'rss_rocket_add_menu');

// Incluir arquivo de funções adicionais
require_once plugin_dir_path(__FILE__) . 'rss-rocket-functions.php';

// Página de opções
// Esta função gera a interface de administração exibida no menu 'RSS Rocket'.
// Ela exibe um formulário para configurar até 5 feeds RSS com suas categorias e a frequência de importação,
// além de botões para salvar e importar posts. A importação é processada aqui, extraindo imagens como capa,
// preenchendo metadados de SEO automaticamente e exibindo mensagens diretamente.
function rss_rocket_options_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Você não tem permissão para acessar esta página.'));
    }

    $feeds = get_option('rss_feeds', array_fill(0, 5, array('url' => '', 'category' => 0)));
    $frequency = get_option('rss_frequency', 'daily');
    $categories = get_categories(array('hide_empty' => 0));

    // Gerar nonce para proteger contra CSRF
    $nonce = wp_create_nonce('rss_rocket_import_nonce');

    // Processa a importação se o botão for clicado
    if (isset($_POST['rss_import']) && check_admin_referer('rss_rocket_import_nonce', 'rss_rocket_nonce')) {
        $imported = false;
        foreach ($feeds as $index => $feed) {
            if (!empty($feed['url']) && filter_var($feed['url'], FILTER_VALIDATE_URL)) {
                $rss = fetch_feed($feed['url']);
                if (!is_wp_error($rss)) {
                    $max_items = $rss->get_item_quantity(1); // Pega apenas 1 item (o mais recente) por feed
                    $rss_items = $rss->get_items(0, $max_items);
                    foreach ($rss_items as $item) {
                        $title = sanitize_text_field($item->get_title());
                        $existing_post = get_page_by_title($title, OBJECT, 'post');
                        if ($existing_post) {
                            echo '<div class="updated"><p>Post "' . esc_html($title) . '" já existe. Pulando importação.</p></div>';
                            continue;
                        }
                        $content = $item->get_content();
                        if (empty($content)) {
                            $content = $item->get_description();
                            if (empty($content)) $content = 'Conteúdo completo não disponível. <a href="' . esc_url($item->get_link()) . '" target="_blank">Veja o artigo original</a>.';
                        }
                        $content = wp_kses_post($content); // Sanitiza HTML do conteúdo
                        $link = esc_url($item->get_link());

                        // Extrair a imagem da capa (featured image) do feed
                        $image_url = '';
                        if ($enclosure = $item->get_enclosure()) {
                            $image_url = $enclosure->get_link(); // Pega a URL da imagem do enclosure
                        } elseif (preg_match('/<img[^>]+src=["\'](.*?)["\']/i', $content, $matches)) {
                            $image_url = $matches[1]; // Extrai a URL da imagem do conteúdo HTML
                        }

                        $post_data = array(
                            'post_title' => $title,
                            'post_content' => $content . '<p><em>Nota: Este artigo foi importado de <a href="' . $link . '" target="_blank">este site</a>.</em></p>',
                            'post_status' => 'publish',
                            'post_author' => get_current_user_id(),
                            'post_category' => array(absint($feed['category'])),
                        );
                        $post_id = wp_insert_post($post_data);
                        if ($post_id) {
                            $imported = true;
                            // Definir a imagem como capa se a URL for válida
                            if (!empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
                                $image_id = media_sideload_image($image_url, $post_id, null, 'id');
                                if (!is_wp_error($image_id)) {
                                    set_post_thumbnail($post_id, $image_id);
                                }
                            }

                            // Preencher metadados de SEO automaticamente
                            $seo_title = wp_trim_words($title, 10, '') . ' | The Golden Game'; // Limita a ~60 caracteres
                            $seo_description = wp_trim_words(strip_tags($content), 20, '...'); // Limita a ~160 caracteres
                            $focus_keyphrase = implode(', ', array_slice(explode(' ', strip_tags($content)), 0, 5)); // 5 palavras-chave principais

                            update_post_meta($post_id, '_yoast_wpseo_title', $seo_title);
                            update_post_meta($post_id, '_yoast_wpseo_metadesc', $seo_description);
                            update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus_keyphrase);

                            echo '<div class="updated"><p>Post "' . esc_html($title) . '" importado com sucesso!</p></div>';
                        } else {
                            echo '<div class="error"><p>Falha ao importar o post "' . esc_html($title) . '".</p></div>';
                        }
                    }
                } else {
                    echo '<div class="error"><p>Erro ao carregar o feed ' . esc_html($feed['url']) . '.</p></div>';
                }
            }
        }
        if (!$imported) {
            echo '<div class="updated"><p>Nenhum novo post importado. Verifique os feeds ou se já foram importados.</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>RSS Rocket</h1>
        <h2>Importar Novo Post</h2>
        <p>Insira até 5 URLs de feeds RSS abaixo com suas respectivas categorias, busque e substitua, e clique <strong>Importar Post</strong>.</p>
        <?php if (isset($_GET['imported']) && $_GET['imported'] == 'success') : ?>
            <div class="updated"><p>Importação concluída!</p></div>
        <?php endif; ?>
        <form method="post" action="options.php">
            <?php settings_fields('rss_rocket_options_group'); ?>
            <table class="form-table">
                <?php for ($i = 0; $i < 5; $i++) : ?>
                    <tr>
                        <th><label for="rss_feeds[<?php echo $i; ?>][url]">Feed RSS #<?php echo $i + 1; ?>:</label></th>
                        <td><input type="text" name="rss_feeds[<?php echo $i; ?>][url]" value="<?php echo esc_attr($feeds[$i]['url']); ?>" class="regular-text" placeholder="Insira uma URL do feed RSS"></td>
                    </tr>
                    <tr>
                        <th><label for="rss_feeds[<?php echo $i; ?>][category]">Categoria:</label></th>
                        <td>
                            <select name="rss_feeds[<?php echo $i; ?>][category]" id="rss_feeds[<?php echo $i; ?>][category]">
                                <option value="0">Nenhuma</option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo $category->term_id; ?>" <?php selected($feeds[$i]['category'], $category->term_id); ?>><?php echo $category->name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endfor; ?>
                <tr>
                    <th><label for="rss_frequency">Frequência de Importação:</label></th>
                    <td>
                        <select name="rss_frequency" id="rss_frequency">
                            <option value="6hours" <?php selected($frequency, '6hours'); ?>>A cada 6 horas</option>
                            <option value="12hours" <?php selected($frequency, '12hours'); ?>>A cada 12 horas</option>
                            <option value="daily" <?php selected($frequency, 'daily'); ?>>Diariamente</option>
                            <option value="2days" <?php selected($frequency, '2days'); ?>>A cada 2 dias</option>
                            <option value="weekly" <?php selected($frequency, 'weekly'); ?>>Semanalmente</option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button('Salvar Feeds'); ?>
        </form>
        <form method="post">
            <?php wp_nonce_field('rss_rocket_import_nonce', 'rss_rocket_nonce'); ?>
            <input type="hidden" name="rss_import" value="1">
            <?php submit_button('Importar Post'); ?>
        </form>
    </div>
    <?php
}